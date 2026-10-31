/** Offline Markdown/Mermaid -> PDF. Tool dependencies live outside the application. */
import { createRequire } from 'node:module';
import { createServer } from 'node:http';
import { readFile, writeFile, mkdir, realpath, stat } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const directory = path.dirname(fileURLToPath(import.meta.url));
const project = path.resolve(directory, '../..');
const options = new Map();
for (let i = 2; i < process.argv.length; i += 2) {
  if (!['--tools-dir', '--output-dir', '--chrome', '--toc-pages'].includes(process.argv[i]) || !process.argv[i + 1]) throw new Error('Use --tools-dir PATH [--output-dir PATH] [--chrome PATH] [--toc-pages JSON].');
  options.set(process.argv[i], process.argv[i + 1]);
}
if (!options.has('--tools-dir')) throw new Error('An external tools directory is required. See README.md.');
const tools = createRequire(path.resolve(options.get('--tools-dir'), 'package.json'));
const projectRequire = createRequire(path.join(project, 'package.json'));
const MarkdownIt = tools('markdown-it');
let chromium;
try { ({ chromium } = projectRequire('@playwright/test')); } catch { ({ chromium } = tools('playwright')); }
const mermaidDist = path.dirname(tools.resolve('mermaid'));
const sourcePath = path.join(directory, 'guia-completa.md');
const source = await readFile(sourcePath, 'utf8');
const sourceSha = createHash('sha256').update(source).digest('hex');
const output = path.resolve(options.get('--output-dir') || path.join(project, 'storage/manual-build'));
const relative = path.relative(project, output);
if (relative.startsWith('..') || path.isAbsolute(relative)) throw new Error('Output must remain inside this project.');
await mkdir(output, { recursive: true });
if (await realpath(output) !== output) throw new Error('Output cannot traverse a symlink.');
await mkdir(path.join(directory, 'diagramas'), { recursive: true });

const escape = value => String(value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const md = new MarkdownIt({ html: false, linkify: false, typographer: false });
const tokens = md.parse(source, {});
const headings = [];
const ids = new Map();
let title = 'Emailing: manual completo';
for (let i = 0; i < tokens.length; i++) {
  const token = tokens[i];
  if (token.type !== 'heading_open') continue;
  const text = (tokens[i + 1].children || []).filter(x => ['text', 'code_inline'].includes(x.type)).map(x => x.content).join('');
  const base = text.toLowerCase().replace(/[^\p{L}\p{N}\s_-]/gu, '').trim().replace(/\s+/g, '-');
  const count = ids.get(base) || 0; ids.set(base, count + 1);
  const id = base + (count ? '-' + count : '');
  token.attrSet('id', id);
  if (token.tag === 'h1') title = text;
  if (token.tag === 'h2' && !/^(índice|indice)(\s|$)/i.test(text)) { token.attrSet('class', 'chapter'); headings.push({ id, text }); }
}
if (headings.length < 8) throw new Error('The complete manual must contain its chapters.');
const diagrams = tokens.filter(t => t.type === 'fence' && t.info.trim().toLowerCase() === 'mermaid');
if (diagrams.length < 6 || diagrams.length > 30 || diagrams.some(d => d.content.length > 24000)) throw new Error('Expected 6-30 bounded Mermaid diagrams.');
const stableLinks = [];
async function resolveDocumentLinks(items) {
  for (const token of items) {
    if (token.type === 'link_open') {
      const href = token.attrGet('href') || '';
      if (href && !href.startsWith('#') && !/^[a-z][a-z0-9+.-]*:/i.test(href)) {
        const reference = new URL(href, 'https://manual-source.invalid/docs/manual/guia-completa.md');
        if (reference.origin !== 'https://manual-source.invalid') throw new Error('Protocol-relative links must use an explicit HTTPS URL.');
        const target = path.resolve(project, '.' + decodeURIComponent(reference.pathname));
        const rel = path.relative(project, target);
        const name = path.basename(target);
        if (rel.startsWith('..') || path.isAbsolute(rel) || /^(storage|vendor|node_modules|\.git)(?:[\\/]|$)/.test(rel)
          || name.startsWith('.env') && name !== '.env.example'
          || ['request.json', 'owner.json', 'process.json', 'settlement.json', 'qa.private.env'].includes(name)
          || name.endsWith('-request.json')) throw new Error('A source link is outside the public documentation.');
        const info = await stat(target);
        if (!info.isFile() && !info.isDirectory()) throw new Error('A source link does not identify a public file or directory.');
        const url = 'https://github.com/daycry/emailing/' + (info.isDirectory() ? 'tree' : 'blob') + '/feature/plataforma-emailing' + reference.pathname + reference.search + reference.hash;
        token.attrSet('href', url);
        stableLinks.push({ from: href, to: url });
      }
    }
    if (token.children) await resolveDocumentLinks(token.children);
  }
}
await resolveDocumentLinks(tokens);

let browser;
let server;
const report = { source: 'docs/manual/guia-completa.md', source_sha256: sourceSha, chapters: headings, diagrams: [], stable_document_links: stableLinks, network: 'owned loopback renderer only; no external requests', application_runtime_changed: false };
try {
  server = createServer(async (request, response) => {
    try {
      if (request.method !== 'GET') { response.writeHead(405).end(); return; }
      const name = new URL(request.url, 'http://localhost').pathname;
      if (name === '/') {
        response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        response.end('<!doctype html><meta charset="utf-8"><script type="module">import mermaid from "/mermaid/mermaid.esm.min.mjs";mermaid.initialize({startOnLoad:false,securityLevel:"strict",htmlLabels:false,theme:"base",fontFamily:"Segoe UI, Arial, sans-serif",themeVariables:{primaryColor:"#e6f3f0",primaryTextColor:"#10273e",primaryBorderColor:"#168579",lineColor:"#52778e",fontSize:"17px"},flowchart:{htmlLabels:false,useMaxWidth:true},sequence:{useMaxWidth:true}});window.manualMermaid=mermaid;</script>'); return;
      }
      if (!name.startsWith('/mermaid/')) { response.writeHead(404).end(); return; }
      const target = path.resolve(mermaidDist, '.' + decodeURIComponent(name.slice('/mermaid'.length)));
      const rel = path.relative(mermaidDist, target);
      if (rel.startsWith('..') || path.isAbsolute(rel) || !/\.(mjs|js|css|wasm)$/.test(target) || await realpath(target) !== target) { response.writeHead(404).end(); return; }
      const body = await readFile(target);
      response.writeHead(200, { 'Content-Type': target.endsWith('.css') ? 'text/css' : target.endsWith('.wasm') ? 'application/wasm' : 'text/javascript' }); response.end(body);
    } catch { response.writeHead(404).end(); }
  });
  await new Promise((resolve, reject) => { server.once('error', reject); server.listen(0, '127.0.0.1', resolve); });
  const origin = 'http://127.0.0.1:' + server.address().port;
  report.renderer_origin = origin;
  browser = await chromium.launch({ ...(options.has('--chrome') ? { executablePath: options.get('--chrome') } : { channel: 'chrome' }), headless: true, args: ['--no-proxy-server'] });
  const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 658, height: 1000 } });
  await context.route('**/*', route => route.request().url().startsWith(origin + '/') && route.request().method() === 'GET' ? route.continue() : route.abort());
  const page = await context.newPage(); page.setDefaultTimeout(30000);
  await page.goto(origin, { waitUntil: 'networkidle' });
  await page.waitForFunction(() => !!window.manualMermaid);
  const rendered = [];
  for (let i = 0; i < diagrams.length; i++) {
    const result = await page.evaluate(async ({ code, id }) => {
      const { svg } = await window.manualMermaid.render(id, code);
      const container = document.createElement('div'); container.innerHTML = svg; document.body.append(container);
      const graphic = container.querySelector('svg');
      const view = graphic.viewBox.baseVal;
      const value = { svg, width: view.width, height: view.height, labels: [...graphic.querySelectorAll('text')].map(n => n.textContent) };
      container.remove(); return value;
    }, { code: diagrams[i].content, id: 'manual-diagram-' + (i + 1) });
    if (!result.width || !result.height || !result.labels.length) {
      await writeFile(path.join(output, 'failed-diagram-' + (i + 1) + '.svg'), result.svg, 'utf8');
      throw new Error('A diagram is empty: ' + JSON.stringify({ index: i + 1, width: result.width, height: result.height, text_labels: result.labels.length }));
    }
    const name = 'diagrama-' + String(i + 1).padStart(2, '0') + '.svg';
    await writeFile(path.join(directory, 'diagramas', name), result.svg, 'utf8');
    const scale = Math.min(658 / result.width, 850 / result.height);
    report.diagrams.push({ file: 'docs/manual/diagramas/' + name, width: result.width, height: result.height, estimated_print_font_pt: +(17 * Math.min(1, scale) * .75).toFixed(2), labels: result.labels });
    rendered.push('<figure>' + result.svg + '<figcaption>Diagrama ' + (i + 1) + '. Elaborado con Mermaid a partir del bloque de la versión Markdown.</figcaption></figure>');
  }
  const originalFence = md.renderer.rules.fence.bind(md.renderer.rules);
  let diagramIndex = 0;
  md.renderer.rules.fence = (items, idx, opts, env, self) => items[idx].info.trim().toLowerCase() === 'mermaid' ? rendered[diagramIndex++] : originalFence(items, idx, opts, env, self);
  md.renderer.rules.image = () => { throw new Error('Use reviewed inline diagrams; external images are not loaded.'); };
  // The paginated TOC replaces the same list of internal chapter links in print.
  // Its text remains in the PDF; ordinary functional lists are preserved.
  const printTokens = [...tokens];
  const chapterIds = new Set(headings.map(h => '#' + h.id));
  const isChapterLink = token => {
    if (token.type !== 'link_open') return false;
    try { return chapterIds.has(decodeURIComponent(token.attrGet('href') || '')); } catch { return false; }
  };
  for (let i = 0; i < printTokens.length; i++) {
    if (printTokens[i].type !== 'bullet_list_open') continue;
    let depth = 1, end = i + 1;
    for (; end < printTokens.length && depth; end++) {
      if (printTokens[end].type === 'bullet_list_open') depth++;
      if (printTokens[end].type === 'bullet_list_close') depth--;
    }
    const inlines = printTokens.slice(i, end).filter(t => t.type === 'inline');
    if (inlines.length >= 8 && inlines.every(t => t.children?.filter(c => c.type === 'link_open').length === 1 && t.children.some(isChapterLink))) {
      const replacement = new printTokens[i].constructor('html_block', '', 0);
      replacement.content = '<p>Consulta el índice paginado al comienzo de este PDF. La versión Markdown conserva sus enlaces por capítulo.</p>';
      printTokens.splice(i, end - i, replacement);
    }
  }
  let body = md.renderer.render(printTokens, md.options, {});
  body = body.replace(/<h1\b[^>]*>[\s\S]*?<\/h1>\s*/, '').replace(/<h2\b[^>]*>(?:Índice|Indice)[\s\S]*?(?=<h2\b)/i, '');
  const firstChapter = body.indexOf('<h2');
  const sourceIntro = firstChapter < 0 ? '' : body.slice(0, firstChapter);
  if (sourceIntro) body = body.slice(firstChapter);
  const pages = options.has('--toc-pages') ? JSON.parse(await readFile(path.resolve(options.get('--toc-pages')), 'utf8')) : {};
  const toc = headings.map(h => '<li><a href="#' + escape(h.id) + '">' + escape(h.text) + '</a><span class="page">' + escape(pages[h.text] || '·') + '</span></li>').join('');
  const css = await readFile(path.join(directory, 'tema.css'), 'utf8');
  const visibleText = children => (children || []).map(t => ['text', 'code_inline'].includes(t.type) ? t.content : ['softbreak', 'hardbreak'].includes(t.type) ? ' ' : t.children ? visibleText(t.children) : '').join('');
  const expected = tokens.filter(t => t.type === 'inline' || t.type === 'fence' && t.info.trim().toLowerCase() !== 'mermaid').map(t => ({ kind: t.type, text: t.type === 'inline' ? visibleText(t.children) : t.content })).filter(t => t.text.trim());
  await writeFile(path.join(output, 'expected-content.json'), JSON.stringify({ source_sha256: sourceSha, blocks: expected }, null, 2) + '\n', 'utf8');
  const html = '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>' + escape(title) + '</title><style>' + css + '</style></head><body><section class="cover"><div class="brand">EMAILING · GUÍA DE USO</div><h1>' + escape(title) + '</h1><p class="subtitle">Instalación desde cero, uso diario y administración</p><div class="description">Recorridos paso a paso, ejemplos y diagramas para comprender todas las funciones disponibles.</div><div class="edition">Edición de laboratorio · 31 de octubre de 2026<br>Incluye Docker, Laragon, infraestructura, Brevo, SendGrid, API y MCP.</div><div class="source-intro">' + sourceIntro + '</div></section><nav class="toc"><h2>Contenido</h2><ol>' + toc + '</ol></nav><main>' + body + '</main></body></html>';
  await page.setContent(html, { waitUntil: 'load' }); await page.evaluate(() => document.fonts.ready);
  const overflow = await page.evaluate(() => [...document.querySelectorAll('pre,table,figure')].filter(n => n.scrollWidth > n.clientWidth + 2).map(n => ({ tag: n.tagName, text: n.textContent.slice(0, 80) })));
  if (overflow.length) throw new Error('Content exceeds its printable container: ' + JSON.stringify(overflow));
  await writeFile(path.join(output, 'guia-completa.html'), html, 'utf8');
  const destination = path.join(output, 'guia-completa.pdf');
  await page.pdf({ path: destination, format: 'A4', preferCSSPageSize: true, printBackground: true, tagged: true, outline: true, displayHeaderFooter: true, headerTemplate: '<span></span>', footerTemplate: '<div style="width:100%;font:8px Arial;color:#52677a;padding:0 18mm;display:flex;justify-content:space-between"><span>Emailing · Manual completo</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span></div>' });
  if (sourceSha !== createHash('sha256').update(await readFile(sourcePath)).digest('hex')) throw new Error('The Markdown source changed while exporting.');
  report.pdf_sha256 = createHash('sha256').update(await readFile(destination)).digest('hex');
  report.output = path.relative(project, destination).replaceAll(path.sep, '/');
  report.pdf_bytes = (await stat(destination)).size;
  await writeFile(path.join(output, 'export-receipt.json'), JSON.stringify(report, null, 2) + '\n', 'utf8');
  console.log(JSON.stringify({ output: report.output, source_sha256: sourceSha, chapters: headings.length, diagrams: report.diagrams.length, pdf_bytes: report.pdf_bytes, minimum_diagram_font_pt: Math.min(...report.diagrams.map(x => x.estimated_print_font_pt)) }));
} finally {
  if (browser) await browser.close();
  if (server) { server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); }
}
