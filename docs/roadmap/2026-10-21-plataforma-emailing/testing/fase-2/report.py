from pathlib import Path
import json,subprocess,re,datetime
out=Path(__file__).resolve().parent;root=out.parents[4]
plugin=Path.home()/'.codex/plugins/cache/daycry/custom-agents/1.22.0'
qa=json.loads((out/'raw/qa-gate.json').read_text(encoding='utf-8'))
with (out/'raw/qa-gate-public-attempt-1.json').open('wb') as log:
 p=subprocess.run(['python',str(plugin/'agent-kits/qa/qa-gate.py'),str(out/'raw/results.json')],cwd=root,stdout=log,stderr=subprocess.STDOUT)
public_gate=json.loads((out/'raw/qa-gate-public-attempt-1.json').read_text(encoding='utf-8'));assert public_gate==qa
data=json.loads((out/'raw/results.json').read_text(encoding='utf-8'))
def specs(suites):return [x for suite in suites for x in suite.get('specs',[])+specs(suite.get('suites',[]))]
rows=[]
for spec in specs(data['suites']):
 for test in spec['tests']:
  result=test['results'][-1];errors=result.get('errors',[])
  rows.append({'title':spec['title'],'project':test['projectName'],'status':test['status'],'seconds':round(result['duration']/1000,1),'error':re.sub(r'\x1b\[[0-9;]*m','',errors[0].get('message','')).splitlines()[0] if errors else ''})
lines=['# QA T-09 · Fase 2 · Intento 1','',
 '**NO-VERDE: 8 pruebas aprobadas, 12 fallidas, 0 flaky, 0 omitidas y 0 interrumpidas.** El gate original y el de la copia pública coinciden. T-09 permanece en progreso; esta evidencia no cierra la iniciativa.', '',
 'Código auditado: `d293b88121481eca0cd4aa5709263d6fc5b88f63`. Dos perfiles locales: nativo `127.0.0.1:8082` y Docker `127.0.0.1:8083`. Chromium, un worker, sin reintentos automáticos. Duración Playwright: 11,8 minutos. Base aislada `emailing_testing`; ningún refresh, migración ni modificación de código de aplicación por QA.', '',
 '## Veredicto determinista','', '```json',json.dumps(qa,ensure_ascii=False,indent=2),'```','',
 'Evidencia: [gate sobre JSON original](raw/qa-gate-attempt-1.json), [gate sobre copia pública](raw/qa-gate-public-attempt-1.json), [resultados públicos](raw/results-attempt-1.json), [recibo de redacción](raw/redaction-attempt-1.json). El JSON original y stdout completos permanecen en `raw/artifacts/`, excluido de Git. La redacción de rutas personales conserva estadísticas, resultados y aserciones.', '',
 '## Puertas de entrada','',
 '| Puerta | Resultado | Evidencia |','|---|---|---|',
 '| Ledger | Exit 0; avisos de Changelog pendientes T-09–T-20 | [stdout](raw/ledger-lint.txt) |',
 '| Cobertura de referencias | Exit 0; 0 referencias rotas y 0 GWT sin cobertura declarada | [JSON](raw/coverage-check.txt) |',
 '| Hosts locales | Exit 0 para ambos perfiles | [stdout](raw/guardrail.txt) |',
 '| Memoria técnica | Exit 0; sin gotchas aplicables | [stdout](raw/knowledge.txt) |','',
 'La puerta de cobertura valida el plan declarado. Los escenarios futuros E2E-08–11 aún no se han ejecutado en esta fase. No existe `docs/CONSTITUTION.md`.', '',
 '## Escenarios ejecutados','',
 '| Escenario | Perfil | Resultado | Duración |','|---|---|---|---|']
for row in rows:
 title=row['title'];identifier=title.split()[0]
 label={'E2E-01':'Registro y acceso','E2E-02':'Selector y aislamiento','E2E-03':'Invitaciones','E2E-04':'Roles y adaptación','E2E-07':'Transporte y secretos'}.get(identifier)
 if identifier=='E2E-05':label='Near 5 MiB / oversize' if 'near 5' in title else ('Handles y scopes' if 'upload requires' in title else 'CRUD y CSV mixto')
 if identifier=='E2E-06':label='Preview hostil' if 'hostile preview' in title else 'Formularios y variables'
 lines.append(f"| {identifier}: {label} | {row['project']} | {'APROBADO' if row['status']=='expected' else 'FALLIDO'} | {row['seconds']} s |")
lines += ['', 'E2E-01–03 pasan en ambos perfiles: registro, acceso, recuperación con token de un uso, CSRF negativo, selector/aislamiento, revocación e invitaciones. E2E-04 completa roles, transferencia y auditoría antes de fallar en adaptación móvil. E2E-06 hostil pasa en ambos perfiles: scripts/eventos, CSS remoto, imágenes/srcset, SVG/data, base/meta, enlaces, formularios, iframe/object/embed no ejecutan, no navegan ni alcanzan el observador local; también se comprueban sandbox y CSP.', '',
 '## Defecto de aplicación confirmado','',
 '**QA-01 · Important · Desbordamiento horizontal en navegación móvil.** En ambos perfiles, viewport 390 px, `innerWidth=390`, `scrollWidth=551` y navegación de 527 px con `flex-wrap: nowrap`. El assertion original `scrollWidth <= innerWidth` falla. La captura completa mide 551 × 2964 píxeles.', '',
 'Después de la suite se aplicó únicamente `nav.style.flexWrap="wrap"` en DOM transitorio: `scrollWidth` pasó a 390 y navegación a 341 en ambos perfiles. Esto aísla la causa en la navegación; QA no ha editado la aplicación. Evidencia: [mediciones antes/después](raw/diagnostic-attempt-1.json), [nativo móvil](screenshots/native-04-mobile.png), [Docker móvil](screenshots/docker-04-mobile.png). La captura tablet posterior no se alcanzó por el fallo móvil.', '',
 '## Fallos y correcciones del harness','',
 '| ID | Evidencia y causa | Preparación para siguiente intento |','|---|---|---|',
 '| H-01 | Cleanup asumía prefijo de cuenta; CSV usa `sources/UUID.csv`. Enmascaró el error inicial de E2E-05 nativo. | Guard de raíz y patrón UUID, más pertenencia SQL de cuenta/actor. Sólo archivos exactos propios. |',
 '| H-02 | CSV de fórmula esperaba `\'=SUM(1)`; el email se normaliza a minúsculas y descarga `\'=sum(1)`, conservando apóstrofe. Docker conserva el error completo. | Expectativa de normalización corregida; protección de fórmula mantenida. |',
 '| H-03 | Near 5 MiB llega a lectura de estado, pero 9990 contactos exceden `execFileSync` 1 MiB y producen ENOBUFS en ambos perfiles. | Buffer CLI de QA 32 MiB; ningún cambio de snapshot/DOM/límites de producto. CSV y oversize siguen sin acreditar como escenario completo. |',
 '| H-04 | Listener esperaba `/livewire/upload-file`; petición observada `/livewire-83ecc6d5/upload-file`. | Listener por sufijo observado; rechazo con scope revocado exige CSRF válido y 403. Reuso ajeno exige 403/404/422, excluye 500. |',
 '| H-05 | E2E-06 segundo alta falla: fila TEXT-only ausente o nombre obligatorio, con estado guardado previo. Probe posterior no reprodujo pérdida del nombre. Causa de readiness provisional. | Esperar respuesta propia create/save, reset visible y persistencia SQL. Positivos mantienen validación nativa y `checkValidity()`. Debe verificarse en siguiente intento. |',
 '| H-06 | Tras guardar settings, details pasa de abierto a cerrado; siguiente fill queda oculto. | Reabrir details para siguiente edición; se mantienen asserts de secretos limpios y errores. |',
 '| H-07 | Wrapper interpreta `/Date(ms)/` como ISO y no retira 8084 inicialmente. | Teardown separado verifica PID, milisegundos, padre, ruta y comando; incluye árbol PHP/cmd/conhost propio. |','',
 'El guard H-01 cambió durante la corrida, el 22 de octubre de 2026 a las 21:16:51 UTC. No cambiaron setup de aplicación ni aserciones durante la suite. [Recibo de hashes y momento](raw/harness-cleanup-fix-attempt-1.json). Por ello, el SHA inicial no representa un runtime único del teardown. Su fuente original se conserva y coincide con el SHA congelado.', '',
 'El resto de correcciones se preparó después de la suite y **no se ha vuelto a ejecutar E2E**. [Diff público](raw/harness-post-attempt-1.patch), [hashes actuales](raw/harness-post-attempt-1-sha256.json), [21 checks de sintaxis, cero fallos](raw/harness-post-attempt-1-lint.json). No regenerar con `prepare.py`; siguiente ejecución requiere número de intento explícito y no permite sobrescribir un JSON original.', '',
 '## Cobertura pendiente y límites','',
 '- E2E-05: repetir completo CRUD/consentimiento/listas, cuenta y scopes, descarga, near 5 MiB y oversize, sesión/actor/cuenta de handle privado. Los asserts posteriores al primer fallo no cuentan como aprobados.',
 '- E2E-06: completar TEXT-only/ambos vacíos por formulario, variables/escape/no recursión, inválida después de válida con retry limpio, IDs ajenos, versión guardada y developer con templates.read + mail.send sin settings.read/contacts.read. HTML-only se creó en ambos perfiles antes del fallo posterior; el escenario completo permanece rojo.',
 '- E2E-07: repetir log/Mailpit, descarga privada por cuenta/activity.read, aceptación SMTP sin afirmar entrega, cifrado y limpieza de secretos después de éxito/error, modo externo bloqueado incluso con evidencia simulada.',
 '- TLS inválido y no apertura SMTP en log tienen evidencia de integración previa de T-08. Esta suite de navegador no los reejecutó ni reemplaza esa evidencia. Suite backend previa 88/957: [stdout congelado](review/raw/phase2-feature-fix1.txt). No hubo envío a Internet ni habilitación de relay externo.',
 '- [ ] M-01: una persona debe revisar claridad, estados, contraste y adaptación en móvil/escritorio. Continúa pendiente.',
 '- [ ] M-02 y escenarios futuros de operación/campañas/API/carga se mantienen para T-10–T-20.', '',
 '## Evidencias visuales','',
 '| Escenario | Capturas limpias |','|---|---|',
 '| E2E-01 | [Nativo](screenshots/native-01-dashboard.png) · [Docker](screenshots/docker-01-dashboard.png) |',
 '| E2E-02 | [Nativo](screenshots/native-02-selector-admin.png) · [Docker](screenshots/docker-02-selector-admin.png) |',
 '| E2E-03 | [Nativo](screenshots/native-03-invitations.png) · [Docker](screenshots/docker-03-invitations.png) |',
 '| E2E-04 | [Móvil nativo](screenshots/native-04-mobile.png) · [Móvil Docker](screenshots/docker-04-mobile.png) |',
 '| E2E-05 | [Resumen nativo](screenshots/native-05-import-report.png) · [Resumen Docker](screenshots/docker-05-import-report.png) |',
 '| E2E-06 | [Hostil nativo](screenshots/native-06-hostile-preview-blocked.png) · [Hostil Docker](screenshots/docker-06-hostile-preview-blocked.png) |',
 '| E2E-07 | No alcanzó captura final; fallo original conservado en resultados. |','',
 'Se inspeccionaron capturas originales de panel, móvil y preview hostil. Sólo identidades ficticias y dominios `example.test`; sin valores de credenciales ni rutas de perfil.', '',
 '## Teardown y relevo','',
 'Fixtures propios eliminados por UUID, archivos privados exactos comprobados contra sus cuentas y raíces. Servidor temporal 8084 retirado con identidad CIM y árbol verificados; 8082 y 8083 conservan login HTTP 200. Mailpit: 12 mensajes de destinatarios UUID propios eliminados, con 404 posterior; mensajes ajenos preservados. No se borraron volúmenes ni servicios ajenos. [Teardown](raw/teardown-attempt-1.json) · [Mailpit/temporales](raw/teardown-extras-attempt-1.json).', '',
 'Relevo al coordinador: corregir QA-01, revisar el diff del harness, reconstruir Docker y despachar el intento siguiente. T-09 permanece en progreso. No corresponde handoff verde a documenter ni cerrar plan/spec global.']
(out/'report.md').write_text('\n'.join(lines)+'\n',encoding='utf-8')
print('Report written; original and public gate identical.')
