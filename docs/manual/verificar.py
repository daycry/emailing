"""Check an exported manual and optionally render every page for visual review."""
from pathlib import Path
import argparse
import hashlib
import json
import re
import unicodedata
from urllib.parse import unquote

import fitz

ROOT = Path(__file__).resolve().parents[2]
MANUAL = Path(__file__).resolve().parent


def normalized(value):
    return ''.join(re.findall(r'\w+', unicodedata.normalize('NFKC', value).casefold()))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--input', type=Path, required=True)
    parser.add_argument('--render', action='store_true')
    args = parser.parse_args()
    directory = args.input.resolve(strict=True)
    if not directory.is_relative_to(ROOT) or directory.is_symlink():
        parser.error('The export directory must remain inside this project.')
    receipt = json.loads((directory / 'export-receipt.json').read_text(encoding='utf-8'))
    expected = json.loads((directory / 'expected-content.json').read_text(encoding='utf-8'))
    source_hash = hashlib.sha256((MANUAL / 'guia-completa.md').read_bytes()).hexdigest()
    pdf_path = directory / 'guia-completa.pdf'
    pdf_hash = hashlib.sha256(pdf_path.read_bytes()).hexdigest()
    problems = []
    if source_hash != receipt['source_sha256'] or source_hash != expected['source_sha256']:
        problems.append('The current Markdown differs from the exported source.')
    if pdf_hash != receipt['pdf_sha256']:
        problems.append('The PDF differs from its export receipt.')
    texts, pages, outside, small, links = [], [], [], [], []
    with fitz.open(pdf_path) as document:
        outline = document.get_toc()
        toc = {row[1]: row[2] for row in outline}
        missing_titles = [h['text'] for h in receipt['chapters'] if h['text'] not in toc]
        if missing_titles:
            problems.append('A chapter is missing from the PDF outline.')
        (directory / 'toc-pages.json').write_text(json.dumps(toc, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
        for number, page in enumerate(document, 1):
            links.extend(link['uri'] for link in page.get_links() if link.get('uri'))
            raw = page.get_text()
            lines = [line for line in raw.splitlines()
                     if not line.startswith('Emailing \u00b7 Manual completo')
                     and not re.fullmatch(r'\d+\s*/\s*\d+', line.strip())]
            text = '\n'.join(lines)
            texts.append(text)
            pages.append({'page': number, 'characters': len(text), 'a4': abs(page.rect.width - 595.276) < 1 and abs(page.rect.height - 841.89) < 1})
            for block in page.get_text('dict')['blocks']:
                for line in block.get('lines', []):
                    for span in line.get('spans', []):
                        if not span['text'].strip():
                            continue
                        x0, y0, x1, y1 = span['bbox']
                        if x0 < 10 or y0 < 10 or x1 > page.rect.width - 8 or y1 > page.rect.height - 8:
                            outside.append({'page': number, 'text': span['text'][:100], 'bbox': span['bbox']})
                        if y1 < page.rect.height - 45 and span['size'] < 7.75:
                            small.append({'page': number, 'text': span['text'][:100], 'font_pt': span['size']})
        actual = normalized('\n'.join(texts))
        missing = [{'block': index, 'kind': block['kind'], 'text': block['text'][:160]}
                   for index, block in enumerate(expected['blocks'])
                   if len(normalized(block['text'])) > 3 and normalized(block['text']) not in actual]
        missing_labels = [{'diagram': index + 1, 'text': label}
                          for index, diagram in enumerate(receipt['diagrams']) for label in diagram['labels']
                          if normalized(label) and normalized(label) not in actual]
        if missing or missing_labels:
            problems.append('Some source blocks or diagram labels are missing from extracted PDF text.')
        renderer_origin = receipt.get('renderer_origin')
        renderer_links = [url for url in links if renderer_origin and url.startswith(renderer_origin + '/')]
        uri_set = {unquote(url) for url in links}
        missing_links = [item for item in receipt.get('stable_document_links', []) if unquote(item['to']) not in uri_set]
        if not renderer_origin or renderer_links or missing_links:
            problems.append('Regenerate: a document link is missing or still points to the temporary renderer.')
        if outside or small or any(not p['a4'] or p['characters'] < 45 for p in pages):
            problems.append('Inspect page dimensions, blank pages, text bounds or small body text.')
        if args.render:
            from PIL import Image, ImageDraw
            previews = directory / 'previews'
            previews.mkdir(exist_ok=True)
            for index, page in enumerate(document):
                page.get_pixmap(matrix=fitz.Matrix(1.15, 1.15), alpha=False).save(previews / f'page-{index + 1:03d}.png')
            for start in range(0, len(document), 16):
                sheet = Image.new('RGB', (1440, 2160), '#dce4eb')
                draw = ImageDraw.Draw(sheet)
                for index in range(start, min(start + 16, len(document))):
                    pix = document[index].get_pixmap(matrix=fitz.Matrix(.56, .56), alpha=False)
                    thumbnail = Image.frombytes('RGB', [pix.width, pix.height], pix.samples)
                    thumbnail.thumbnail((340, 500))
                    col, row = (index - start) % 4, (index - start) // 4
                    sheet.paste(thumbnail, (col * 360 + 10, row * 540 + 24))
                    draw.text((col * 360 + 10, row * 540 + 7), str(index + 1), fill='black')
                sheet.save(previews / f'pages-{start + 1:03d}-{min(start + 16, len(document)):03d}.png')
    result = {'status': 'PASS' if not problems else 'FAIL', 'source_sha256': source_hash, 'pdf_sha256': pdf_hash,
              'pages': pages, 'chapters': len(receipt['chapters']), 'diagrams': len(receipt['diagrams']),
              'source_blocks': len(expected['blocks']), 'matching_blocks': len(expected['blocks']) - len(missing),
              'missing_chapters': missing_titles, 'missing_blocks': missing, 'missing_diagram_labels': missing_labels,
              'temporary_renderer_links': renderer_links, 'missing_stable_document_links': missing_links, 'stable_document_links': len(receipt.get('stable_document_links', [])),
              'outside_page_text': outside, 'small_body_text': small, 'problems': problems,
              'all_pages_rendered': args.render, 'visual_review_required': True}
    (directory / 'validation.json').write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(json.dumps({'status': result['status'], 'pages': len(pages), 'chapters': result['chapters'], 'diagrams': result['diagrams'],
                      'matching_blocks': result['matching_blocks'], 'source_blocks': result['source_blocks'],
                      'missing_diagram_labels': len(missing_labels), 'text_bounds_errors': len(outside), 'small_body_spans': len(small), 'temporary_renderer_links': len(renderer_links), 'missing_stable_links': len(missing_links), 'problems': problems}))
    return 0 if not problems else 1


if __name__ == '__main__':
    raise SystemExit(main())
