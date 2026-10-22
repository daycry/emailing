import fs from 'node:fs';
import path from 'node:path';
import {homedir} from 'node:os';
import {createRequire} from 'node:module';
import {fileURLToPath} from 'node:url';
import {spawnSync} from 'node:child_process';
const here=path.dirname(fileURLToPath(import.meta.url)),phase=path.join(here,'fase-1');
const cycleLabel=process.argv[2]||'cycle-2-attempt-1';
const offReceiptPath=path.join(phase,'raw','off-profile-'+cycleLabel+'.json');
const offReceipt=fs.existsSync(offReceiptPath)?JSON.parse(fs.readFileSync(offReceiptPath)):{};
const data=JSON.parse(fs.readFileSync(path.join(phase,'raw/results.json'))),gates=JSON.parse(fs.readFileSync(path.join(phase,'raw/gates.json'))),qa=JSON.parse(gates.qa.stdout),coverage=JSON.parse(gates.coverage.stdout);
function specs(suites){return suites.flatMap(s=>[...(s.specs||[]),...specs(s.suites||[])]);}
const rows=specs(data.suites).flatMap(s=>s.tests.map(t=>({id:s.title.match(/E2E-\d+/)?.[0],project:t.projectName,status:t.status,result:t.results.at(-1)?.status,duration:t.results.at(-1)?.duration||0,errors:(t.results.at(-1)?.errors||[]).map(e=>(e.message||e.value||'Error sin texto').replace(/\u001b\[[0-9;]*m/g,''))})));
const names={'E2E-01':'Registro, acceso y recuperación','E2E-02':'Selector y aislamiento de cuentas','E2E-03':'Invitaciones y validez de identidad/token','E2E-04':'Roles, propiedad, revocación y auditoría'};
const visuals=fs.existsSync(path.join(phase,'raw/m01-visual.json'))?JSON.parse(fs.readFileSync(path.join(phase,'raw/m01-visual.json'))):[];
const responsiveComplete=rows.filter(r=>r.id==='E2E-04').length===2&&rows.filter(r=>r.id==='E2E-04').every(r=>r.status==='expected');
const feedbackVerified=cycleLabel==='cycle-2-attempt-2'&&responsiveComplete;
const image=(p,name,label)=>fs.existsSync(path.join(phase,'screenshots',name+'.png'))?`![${label}](${p}screenshots/${name}.png)`:`_Captura no alcanzada en el recorrido: ${label}._`;
function report(p){return `# Informe de QA — Plataforma interna de emailing · fase 1

| Campo | Resultado |
|---|---|
| Fecha | ${new Date().toISOString()} |
| Ciclo / intento | ${cycleLabel}; QA1 conservada íntegra en testing/archive/cycle-1 |
| Estado automático de fase 1 | ${gates.qa.exit===0?'VERDE':'NO VERDE'} según qa-gate; no cierra el roadmap completo |
| Regresión de feedback FB01 | ${feedbackVerified?'Ausencia del error anterior tras éxito acreditada en ambos perfiles; cierre de T-04 sólo por el orquestador':'Defecto de QA2-1 conservado como antecedente; no acreditar resolución sin completar FB01'} |
| URL nativa / Docker | http://127.0.0.1:8082 / http://127.0.0.1:8083 |
| Perfil temporal registro deshabilitado | http://127.0.0.1:8084; ${offReceipt.state==='closed'?'cerrado tras verificar ownership y perfiles estables':'conservado, pendiente del handoff al coordinador'} |
| Correo | Mailpit local http://127.0.0.1:18025; ningún SMTP externo |
| Alcance | T-01–T-04; E2E-01–04 en Chromium; workers=1, retries=0 |

## Veredicto (qa-gate)

Salida JSON real, sin umbrales modificados, sobre <code>${p}raw/results.json</code>:

\`\`\`json
${gates.qa.stdout.trim()}
\`\`\`

Automáticos: ${data.stats.expected}/${rows.length} pasan, ${data.stats.unexpected} fallan, ${data.stats.flaky} flaky, ${data.stats.skipped} omitidos. Duración total del último intento: ${(data.stats.duration/1000).toFixed(2)} s. Cada recorrido tiene 360 s de presupuesto y cada acción/navegación 30 s. La configuración no permite retries.

## Puertas y cobertura

| Puerta | Exit | Evidencia |
|---|---:|---|
| Guardrail oficial | ${gates.guardrail.exit} | Sólo hosts 127.0.0.1, puertos 8082/8083/8084/18025 |
| ledger-lint | ${gates.ledger.exit} | 0 incoherencias; avisos de Changelog no bloqueantes |
| coverage-check | ${gates.coverage.exit} | ${coverage.gwt_cubiertos.length} GWT trazados; ${coverage.broken_refs} referencias rotas; ${coverage.gwt_sin_cubrir.length} GWT huérfanos |
| qa-gate | ${gates.qa.exit} | Recuento real del runner |

Los 25 GWT están **mapeados**, lo que no acredita ejecución futura. E2E-05–11 y M-02 siguen pendientes. No hay configuración tests.coberturaMinima ni TDD; no se calcula ni inventa porcentaje de cobertura de código. Como complemento aportado por implementer: suite Feature final 33 passed / 262 assertions / 53.60 s y regresión HTTP de sesiones de registro/login anteriores a la primera navegación; esta pasada QA no repitió esa suite. La evidencia real de regresión backend está conservada en <code>${p}raw/team-feedback-red.txt</code>, <code>${p}raw/team-feedback-green.txt</code> y <code>${p}raw/team-feedback-feature.txt</code>.

## Resultados automáticos

${data.stats.unexpected?'**Los párrafos siguientes describen las aserciones previstas; sólo se acreditan los casos completos que pasan. Los pasos posteriores al primer fallo de un caso permanecen sin acreditar.**':''}

| Escenario | Perfil | Resultado | Duración |
|---|---|---|---:|
${rows.map(r=>`| ${r.id} — ${names[r.id]} | ${r.project} | ${r.status} / ${r.result} | ${(r.duration/1000).toFixed(2)} s |`).join('\n')}

${rows.filter(r=>r.status!=='expected').map(r=>`### Error real ${r.id} · ${r.project}\n\n\`\`\`text\n${r.errors.join('\n')}\n\`\`\``).join('\n\n')}

### E2E-01 — Registro, acceso y recuperación

${rows.some(r=>r.id==='E2E-01'&&r.status!=='expected')?'**Resultado no verde:** el error primario de cada perfil aparece arriba, tomado del JSON real. No se acredita ningún paso posterior al primer fallo ni se infiere una causa que no se haya probado.':'**PASA en ambos perfiles:** el registro deshabilitado devuelve 404 porque Fortify no publica la ruta y la comprobación posterior en DB confirma que no se creó el usuario.'}

Acceso anónimo y CSRF negativo real (419); alta UI con una cuenta y membresía owner/Admin únicas; login/logout y credencial inválida; email de recuperación recibido por SMTP en Mailpit; consumo y rechazo de reutilización; cookies anteriores al reset rechazadas y acceso nuevo correcto. Perfil temporal con registro deshabilitado rechaza POST real (404) y no crea usuario. Capturas sin token ni contraseña.

${image(p,'native-01-registration','Registro nativo')}

${image(p,'native-01-reset-confirmation','Confirmación de recuperación nativa · '+cycleLabel)}

### E2E-02 — Selector y aislamiento

Identidad Admin A / Read-only B sólo ve sus dos cuentas. Switch HTTP a C denegado (403), acción de Read-only forjada por Livewire denegada (403), modificación de membresía de C rechazada (404) y DB de C intacta. Revocación de la membresía activa en sesión abierta rechaza la siguiente acción y presenta una denegación legible.

${image(p,'native-02-readonly','Cuenta de solo lectura')}

### E2E-03 — Invitaciones

Invitación emitida desde UI y recibida en Mailpit; aceptación por destinatario previsto y una sola membresía. GET/POST rechazados para email incorrecto, token consumido, expirado, revocado y token antiguo tras reenvío; el enlace nuevo sí se acepta. DB comprueba ausencia de membresías negativas y longitud 64 del campo oculto token_hash; esa longitud por sí sola no demuestra el algoritmo. El hash SHA-256 y la concurrencia MySQL pertenecen al complemento Feature aportado; no se simulan con un resultado de fixture.

${image(p,'docker-03-invitations','Listado de invitaciones Docker')}

### E2E-04 — Roles y propiedad

${rows.some(r=>r.id==='E2E-04'&&r.status!=='expected')?'**Resultado no verde:** consultar el error primario real de cada perfil arriba. Los pasos posteriores al primer fallo no quedan acreditados y no se sustituyen por capturas manuales.':'**PASA en ambos perfiles:** después del rechazo por contraseña incorrecta, el harness reabre la confirmación si el render la cerró. Se reacreditan transferencia válida, roles resultantes, auditoría de actor/cuenta/acción y los asserts responsive finales.'} ${feedbackVerified?'FB01 comprueba además que el texto «La contraseña no es correcta» ya no esté visible tras «Propiedad transferida», conservando todos los asserts anteriores.':''}

Read-only y Restricted (Developer/Marketer/Accountant) carecen de controles de administración y reciben 403 ante invitación, elevación de rol/scopes, eliminación y transferencia forjadas. Los tres perfiles aplican el catálogo real en UI; scope ajeno rechazado. Owner protegido de eliminación/degradación. Retirada y reducción desde UI se aplican en la siguiente acción de sesiones abiertas, conservando la cuenta. Transferencia exige contraseña correcta, eleva al nuevo propietario a Admin y conserva Admin anterior. Auditoría comprueba actor, cuenta y acción en DB y texto visible.

${image(p,'native-04-permissions-desktop','Controles y matriz de permisos · '+cycleLabel)}

${responsiveComplete?image(p,'docker-04-audit','Auditoría Docker tras transferencia válida · '+cycleLabel):''}

## Evidencia responsive y checklist manual

${responsiveComplete?'El E2E-04 de este intento completó capturas con viewports de escritorio 1440×1000, móvil 390×844 y tableta 768×1024 en ambos perfiles. Los asserts de ancho de documento menor o igual que el viewport pasaron en móvil y tableta. Las imágenes son de página completa.':'Este intento no acredita el recorrido responsive completo; las capturas manuales previas permanecen como evidencia histórica en QA1.'} Estas medidas no sustituyen el juicio humano de claridad, contraste y lenguaje.

En móvil la tabla de miembros tiene desplazamiento horizontal **interno** (overflow-x-auto en la vista): las acciones a la derecha requieren desplazarla. Cero desbordamiento de la página no significa que todas las columnas se vean simultáneamente. La revisión del agente observa navegación y formularios legibles; M-01 sigue pendiente de una persona.

${feedbackVerified?'**FB01 resuelta y reacreditada:** QA2-1 conservó el defecto de mensajes contradictorios. En este intento el error previo desaparece al completar la transferencia válida: el nuevo assert de ausencia visible pasó en ambos perfiles y las capturas actuales muestran el estado de éxito sin la alerta anterior. El helper de captura normaliza scroll a (0,0) para evitar desplazar la barra fixed en imágenes de página completa. Esta regresión funcional automatizada no sustituye la aceptación humana M-01, que permanece pendiente.':'**Antecedente de feedback:** QA2-1 conserva el defecto observado de alerta previa y éxito simultáneos. Sin completar FB01 en ambos perfiles no se acredita su resolución. M-01 permanece pendiente de aceptación humana.'}

| Perfil | Vista | Viewport | Ancho documento | Desbordamiento horizontal |
|---|---|---|---:|---|
${responsiveComplete?['native','docker'].flatMap(profile=>[['mobile',390,844],['tablet',768,1024]].map(([view,w,h])=>`| ${profile} | ${view} | ${w}×${h} | ≤ ${w}, assert real | No |`)).join('\n'):'| QA1 | Histórico | Ver archivo cycle-1 | No medido de nuevo | No acreditado en este intento |'}

${image(p,responsiveComplete?'native-04-mobile':'native-M01-mobile',responsiveComplete?'Móvil nativo · E2E-04 actual; M-01 pendiente':'Móvil nativo · M-01 histórico QA1')}

${image(p,responsiveComplete?'docker-04-tablet':'docker-M01-tablet',responsiveComplete?'Tableta Docker · E2E-04 actual; M-01 pendiente':'Tableta Docker · M-01 histórico QA1')}

- [ ] **M-01 — Claridad y adaptación del panel español**: una persona debe aceptar formularios, mensajes, cuenta activa, permisos y legibilidad. Pendiente; capturas preparadas para revisión.
- [ ] **M-02 — Operación y límites**: pendiente de fase futura, fuera de C-01.

## Trazabilidad y límites

| Tareas | Escenarios | Estado de esta pasada |
|---|---|---|
| T-01 | E2E-01, M-01 | Automático fase 1 según tabla; M-01 humano pendiente |
| T-02 | E2E-01/02, M-01 | Automático fase 1 según tabla; M-01 humano pendiente |
| T-03 | E2E-02/03/04, M-01 | Automático fase 1 según tabla; M-01 humano pendiente |
| T-04 | E2E-01–04, M-01 | ${feedbackVerified?'Regresión FB01 y automáticos acreditados; documentación/cierre sólo por root. M-01 humana pendiente':'En progreso; root coordina siguiente decisión. Sin cierre de fase ni plan'} |
| T-05–T-20 | E2E-05–11 y complementos globales | No ejecutados ni aprobados en esta fase |

Pruebas aisladas en MySQL **emailing_testing** con usuario exclusivo y TestingDatabaseGuard, sin migrar/refrescar/truncar. Fixtures identificadas por UUID y limpieza limitada a sus usuarios/cuentas. Ningún acceso ni cambio a emailing/lab_smoke. El servidor temporal 8084 ${offReceipt.state==='closed'?'fue detenido tras verificar su árbol propio; 8084 quedó sin listener y 8082/8083 respondieron 200':'queda conservado hasta coordinación del diagnóstico'}. Recibo operativo: <code>${p}raw/off-profile-${cycleLabel}.json</code>. No hay trazas/vídeo con credenciales. JSON conserva estados, conteos y errores materiales; sólo redacta rutas de perfil privado y secretos de enlaces/fixtures si aparecen en errores. Originales privados ignorados.

## Historial de ejecución y del harness

Veredictos reales por intento: [gates-history.json](${p}raw/gates-history.json). Cada entrada conserva stdout completo y exit de qa-gate; las claves de QA1 se preservan y QA2 añade nombres únicos. El resultado actual es el mostrado arriba, sin combinar ni modificar conteos de ejecuciones distintas.

${fs.readdirSync(path.join(phase,'raw')).filter(n=>/^results-(?:attempt-\d+|cycle-\d+-attempt-\d+)\.json$/.test(n)).sort().map(n=>{const d=JSON.parse(fs.readFileSync(path.join(phase,'raw',n)));return `- ${n}: ${d.stats.expected} expected, ${d.stats.unexpected} unexpected, ${d.stats.skipped} skipped, ${d.stats.flaky} flaky; ${(d.stats.duration/1000).toFixed(2)} s.`;}).join('\n')}

QA1 permanece archivada íntegra con sus tres intentos y causas. QA2 fue autorizada explícitamente como un ciclo adicional después del diagnóstico y revisión A/B de dos cambios del harness: esperar 404 en el registro deshabilitado y reabrir la confirmación de transferencia si quedó cerrada tras validación. En el primer intento de QA2 no se modificó aplicación. No se elevaron presupuestos: se mantienen 360 s por caso, 30 s por acción/navegación, workers=1 y retries=0. El timeout inicial Docker de QA1 no adquiere una causa raíz retrospectiva por el resultado de QA2.

QA2-1 permanece archivada íntegra con su gate verde y el defecto de feedback descubierto en las capturas. Para QA2-2, implementer corrigió la limpieza del error de validación en la aplicación y aportó regresión MySQL roja/verde; revisión A/B aprobó el incremento y el assert FB01 de QA conservó los anteriores. QA no repitió el backend ni modificó producto. El contador sigue siendo QA2, intento 2 de un máximo de 3, sin retries automáticos.

## Evidencias y reproducción

- Resultados reales: <code>${p}raw/results.json</code>; gates y stdout completos: <code>${p}raw/gates.json</code> y <code>${p}raw/*.txt</code>.
- Fuentes ejecutables: <code>testing/playwright.config.mjs</code>, <code>testing/specs/E2E-01.spec.mjs</code>–<code>E2E-04.spec.mjs</code>, <code>testing/specs/helpers.mjs</code>, <code>testing/fixture.php</code>.
- Runner instalado en QA_CACHE_DIR o caché de usuario; ejecutar su cli.js con Node22 y --config testing/playwright.config.mjs desde la raíz del proyecto. Chromium local existente, sin descargas.
- Capturas de ambos perfiles: <code>${p}screenshots/</code>; informe de esta fase preservado en <code>testing/fase-1/report.md</code> y <code>report.pdf</code>.
`}
fs.writeFileSync(path.join(here,'report.md'),report('fase-1/'));
fs.writeFileSync(path.join(phase,'report.md'),report(''));
const require=createRequire(import.meta.url),cache=path.join(homedir(),'.claude/tool-cache/to-pdf');
for(const dep of ['markdown-it','mammoth','puppeteer'])require.resolve(path.join(cache,'node_modules',dep));
const MarkdownIt=require(path.join(cache,'node_modules/markdown-it'));
let html=new MarkdownIt({html:true}).render(fs.readFileSync(path.join(phase,'report.md'),'utf8'));
html=html.replace(/<img src="screenshots\/([^\"]+)"([^>]*)>/g,(_,name,attrs)=>fs.existsSync(path.join(phase,'screenshots',name))?`<img src="data:image/png;base64,${fs.readFileSync(path.join(phase,'screenshots',name)).toString('base64')}"${attrs}>`:`<span>Evidencia no alcanzada: ${name}</span>`);
html='<style>img{max-height:490px;width:auto;max-width:100%;object-fit:contain}p:has(img){break-inside:avoid}table{font-size:10px}pre{white-space:pre-wrap;overflow-wrap:anywhere}</style>'+html;
const input=path.join(here,'raw/artifacts/report.html');fs.writeFileSync(input,html);
const pdf=spawnSync(process.execPath,[path.join(cache,'to-pdf.mjs'),input,'--out',path.join(phase,'report.pdf'),'--title','QA fase 1 · Plataforma de emailing'],{encoding:'utf8',windowsHide:true,env:{...process.env,PUPPETEER_EXECUTABLE_PATH:path.join(process.env.LOCALAPPDATA,'ms-playwright/chromium-1243/chrome-win64/chrome.exe')}});
if(pdf.status!==0)throw new Error('to-pdf failed: '+pdf.stderr);
fs.copyFileSync(path.join(phase,'report.pdf'),path.join(here,'report.pdf'));console.log(JSON.stringify({cases:rows.length,qa,files:['testing/report.md','testing/report.pdf','testing/fase-1/report.md','testing/fase-1/report.pdf'],pdfBytes:fs.statSync(path.join(phase,'report.pdf')).size}));
