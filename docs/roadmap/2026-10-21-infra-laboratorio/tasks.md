---
generacion: # ventana compartida con improvement-plan.md
  inicio: 2026-10-21T13:22:31Z
  fin: 2026-10-21T13:25:04Z
  fuente: estimado
  tokens_reales: null
  eur: null
  horas_ia: null
  duracion: null
  duracion_reloj: 3m
  ratio_usado: 300000
  ratio_origen: default no calibrado (300000)
  nota: Sin transcripciones; consumo real no disponible
verificacion: obligatoria
---

# Checklist de Tareas — Infraestructura del laboratorio

| Campo | Valor |
|---|---|
| **Estado** | completado |
| **Fecha** | 2026-10-21 |
| **Plan** | [improvement-plan.md](improvement-plan.md) |
| **Diseño** | n/a |

> **⚠️ Ledger canónico de progreso.** Este fichero es la **fuente única de verdad** del avance del plan. **Cualquier** implementador debe marcar aquí cada tarea y actualizar el resumen. Otros ledgers son espejos.

## Resumen de progreso

| Fase | Completadas | Total | Progreso | H. humanas (real/est) | H. IA ejec. (real/est) | Supervisión (real/est) | Tokens (real/est) |
|---|---|---|---|---|---|---|---|
| Fase 1 — Configuración y arranque | 2 | 2 | 100% | — / 2,25h | — / 0,75h | — / 0,1875h | — / 30000 |
| Fase 2 — Verificación | 1 | 1 | 100% | — / 0,75h | — / 0,25h | — / 0,0625h | — / 10000 |
| **TOTAL base** | **3** | **3** | **100%** | **— / 3h** | **— / 1h** | **— / 0,25h** | **— / 40000** |

Estimaciones sin telemetría. Con margen del 20 %: 3,6 h humanas, 1,2 h IA y 0,3 h supervisión. Equivalente humano 180 € a tarifa supuesta de 50 €/h; coste de tokens sin verificar. No es cargo ni presupuesto aprobado.

## Fase 1 — Configuración y arranque

**Estado**: completado · **Estimado base**: 2,25h · **Real**: no medido · **Coste equivalente est.**: 112,50 € + tokens sin verificar · **Tokens est.**: 30000

### T-01 — Preparar Compose y configuración local

- **Descripción**: Crear cuatro servicios aislados del resto del laboratorio, con persistencia y secretos locales. Confirmar imágenes y release Cronicle oficiales antes de fijarlos.
- **Estado**: completado
- **Tiempo humano**: est. 1,5h · real —
- **Tiempo IA (ejec.)**: est. 0,5h · real no disponible; sin telemetría. Ventana de reloj 13:25:31Z–13:29:28Z (4 min); no equivale a horas IA medidas.
- **Supervisión**: est. 0,125h · real —
- **Previsión IA**: 15000 in / 5000 out tok · coste sin verificar
- **Dependencias**: Docker Desktop disponible
- **Tipo**: devops
- **Archivos**: `infra/compose.yaml`, `infra/.env.example`, `infra/.env`, `infra/cronicle/**`, `infra/init.ps1`, `infra/README.md`, `.gitignore`, `docs/roadmap/2026-10-21-infra-laboratorio/**`
- **Cubre (tests)**: API-01, E2E-01, M-01
- **Verificación**: `powershell -NoProfile -File infra/init.ps1` → genera configuración idempotente sin mostrar secretos · `docker compose --env-file infra/.env -f infra/compose.yaml config --quiet` → exit 0

**Criterios de aceptación**

- [x] CA-01/02/03/07: red estable, volúmenes, versiones fijadas, puertos locales, secretos ignorados y ausencia de socket Docker.
- [x] Cronicle dispone de build oficial fijado y configuración de identidad persistente.

**Subtareas**

- [x] Escribir Compose, Dockerfile, bootstrap y ejemplos.
- [x] Documentar puertos alternativos y acceso a secretos locales sin publicarlos.

- **Changelog**: El laboratorio dispone de servicios compartidos con persistencia, interfaces locales y credenciales aleatorias.
- **Resultado de verificación**: `init.ps1` → `Created infra/.env with random local credentials`; segunda ejecución → `infra/.env already exists; credentials preserved`; `git check-ignore infra/.env` → `infra/.env`; `docker compose ... config --quiet` → exit 0; `node --check` de bootstrap/health y parser PowerShell de smoke → exit 0.

**Notas**: TDD n/a: configuración y scripts operativos de infraestructura. No modificar `.git` ni servicios preexistentes.

### T-02 — Arrancar y completar el bootstrap persistente

- **Descripción**: Construir y arrancar los cuatro servicios. Esperar disponibilidad y comprobar que una segunda inicialización conserva los datos y la contraseña segura.
- **Estado**: completado
- **Tiempo humano**: est. 0,75h · real —
- **Tiempo IA (ejec.)**: est. 0,25h · real —
- **Supervisión**: est. 0,0625h · real —
- **Previsión IA**: 7500 in / 2500 out tok · coste sin verificar
- **Dependencias**: T-01
- **Tipo**: devops
- **Archivos**: `infra/**`, `.gitignore`, `docs/roadmap/2026-10-21-infra-laboratorio/**`
- **Cubre (tests)**: E2E-01, API-01
- **Verificación**: `docker compose --env-file infra/.env -f infra/compose.yaml up -d --build` → cuatro servicios arrancados · `docker compose --env-file infra/.env -f infra/compose.yaml ps` → ningún servicio caído; evidencia funcional detallada en T-03

**Criterios de aceptación**

- [x] CA-01/03/05: arranque correcto, bootstrap Cronicle idempotente y credencial predeterminada sustituida.
- [x] Contenedor `sobricontrol-mariadb` y puertos ocupados conservan estado previo.

**Subtareas**

- [x] Construir y levantar solo este proyecto Compose.
- [x] Revisar errores de inicialización sin volcar secretos en logs de evidencia.

- **Changelog**: Los cuatro servicios del laboratorio arrancan con healthchecks y conservan datos y credenciales tras recrear sus contenedores.

## Fase 2 — Verificación y documentación

**Estado**: completado · **Estimado base**: 0,75h · **Real**: no medido · **Coste equivalente est.**: 37,50 € + tokens sin verificar · **Tokens est.**: 10000

### T-03 — Ejecutar smoke, recrear y documentar resultados

- **Descripción**: Probar operaciones reales y persistencia. Entregar URLs locales e instrucciones; pasar revisión y QA con la evidencia obtenida.
- **Estado**: completado
- **Tiempo humano**: est. 0,75h · real —
- **Tiempo IA (ejec.)**: est. 0,25h · real —
- **Supervisión**: est. 0,0625h · real —
- **Previsión IA**: 7500 in / 2500 out tok · coste sin verificar
- **Dependencias**: T-02
- **Tipo**: test
- **Archivos**: `infra/**`, `.gitignore`, `docs/roadmap/2026-10-21-infra-laboratorio/**`, `docs/roadmap/README.md`
- **Cubre (tests)**: E2E-01, API-01, M-01
- **Verificación**: `powershell -NoProfile -File infra/smoke.ps1` → publicación/consumo RabbitMQ, consulta SQL, SMTP/Mailpit y job Cronicle correctos · `docker compose --env-file infra/.env -f infra/compose.yaml up -d --force-recreate` → datos conservados; repetir smoke y comprobar marcadores previos

**Criterios de aceptación**

- [x] CA-04/05/06: resultados funcionales y marcadores previos persisten tras recreación sin borrar volúmenes.
- [x] E2E-01 muestra UIs accesibles y login Cronicle; secretos ausentes de la evidencia publicada.
- [x] README describe operación, red externa por aplicaciones y límites del laboratorio; revisión y QA no tienen defectos bloqueantes abiertos.

**Subtareas**

- [x] Crear smoke acotado con limpieza únicamente de sus recursos temporales.
- [x] Ejecutar prueba inicial y tras recreación; conservar evidencia resumida sin secretos.
- [x] Registrar revisión, QA y cierre del ledger conforme a resultados reales.

- **Changelog**: Las pruebas de protocolos, persistencia y tres interfaces validan la infraestructura compartida; se documentan operación y conexión de aplicaciones.

## Notas de implementación

- Sin rama ni commits: `.git` es de solo lectura y no existe commit inicial; no se modificó Git. `scope-check.py` no puede resolver `HEAD` en este repositorio sin commits. La revisión acota explícitamente `infra/**`, `.gitignore` y esta iniciativa.
- `ledger-lint.py` ejecutado antes del handoff: 0 incoherencias y 0 avisos. T-02/T-03 completadas tras ejecución real, revisión y QA verdes.
- Adaptación de roles: README operativo incluido por asignación expresa del coordinador en T-01; no se documenta producto ni se declara QA verde anticipadamente.

## Evidencia de ejecución del coordinador

- Docker Desktop 29.8.2 y Compose 5.5.1 disponibles; acceso al daemon mediante elevación autorizada. Puertos locales elegidos evitan MySQL 3306/3307 y Mailpit 1025/8025 existentes.
- `docker compose --env-file infra/.env -f infra/compose.yaml config --quiet` → exit 0.
- `docker compose --env-file infra/.env -f infra/compose.yaml up -d --build` → imágenes construidas y cuatro contenedores creados. Corregida ruta inicial inexistente `bin/cronicled.js` por el punto de entrada oficial `lib/main.js`.
- Readiness Cronicle: `/api/app/ping` respondía antes de estar listo como principal; logs y `lib/engine.js` evidenciaron espera de elección de 60 segundos. Configuración de principal único y healthcheck que verifica `master_hostname` corrigen el arranque del laboratorio. Se retiró del smoke el endpoint inexistente `get_server_groups`, usando los grupos de la respuesta autenticada.
- `docker compose --env-file infra/.env -f infra/compose.yaml up -d --wait --wait-timeout 120` → RabbitMQ, Cronicle, MySQL y Mailpit Healthy.
- `powershell -NoProfile -File infra/smoke.ps1` → exit 0: PASS RabbitMQ publicación/consumo y requeue; PASS MySQL escritura/lectura con usuario de aplicación; PASS Mailpit SMTP y búsqueda API; PASS Cronicle login y trabajo shell finito con código 0.
- `docker compose --env-file infra/.env -f infra/compose.yaml up -d --force-recreate --wait --wait-timeout 150` y `powershell -NoProfile -File infra/smoke.ps1 -VerifyPersistence` → exit 0; marcadores anteriores conservados en los cuatro servicios. No se borraron volúmenes. `sobricontrol-mariadb` seguía Up y publicado en 3307 tras la prueba.
- Se conservan únicamente fixtures de laboratorio identificables para verificar persistencia; `.runtime` queda ignorado. Las evidencias no contienen contraseñas ni sesiones. Sin telemetría de tokens, no se atribuye coste real.

## Revisión de dos lentes — intento 1: Infraestructura (T-01, T-02, T-03) — corrección de smoke

Lentes ejecutadas: A+B+C+D. C activada por Dockerfile y variables de configuración; D por lectura síncrona en bootstrap. Scope-check limitado por ausencia de HEAD; revisión completa de archivos explícitos, sin mezclar el scaffold anterior. Sin publicación Jira al no existir opt-in.

| ID | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| A1 | Minor | Estado/contador del plan sin sincronizar con ledger | T-03 | Corregido al cierre QA: plan/ledger completados y 3/3 tareas | Lente A: improvement-plan.md borrador/0 frente a ledger en progreso/1 |
| B1 | Important | Smoke normal reutilizaba marcadores y omitía envío/publicación nuevos | T-03 | Implementer limita lectura previa al modo `-VerifyPersistence`; pendiente relectura B2 y prueba | infra/smoke.ps1, carga condicional del estado |
| C | — | Sin hallazgos de seguridad introducida | Todas | No requiere corrección | Localhost, secretos aleatorios ignorados, sin socket Docker, bootstrap antes del servidor |
| D | — | Sin hallazgos de rendimiento para el laboratorio | Todas | No requiere corrección | Bootstrap finito, sondeo acotado; no se atribuye benchmark |

## Revisión de dos lentes — intento 2: Infraestructura (T-03) — B1 corregido

Relectura B de la única corrección; se mantienen los veredictos A/C/D del intento 1 al no cambiar sus criterios. `infra/smoke.ps1` carga estado previo únicamente con `-VerifyPersistence`; el modo normal genera nuevos identificadores y realiza publicación, INSERT, SMTP y job nuevos. B1 corregido, sin gaps Critical/Important pendientes. A1 documental corregido al cierre QA: plan/ledger completados y contador 3/3. Las pruebas de ejecución y UI se registran por separado; la revisión no sustituye QA.


## QA y cierre — 2026-10-21

- E2E-01: tres subcasos Playwright pasaron al primer intento (15,8 s). Login RabbitMQ y Cronicle, job exitoso y contenido SMTP Mailpit verificados con capturas sin secretos.
- `qa-gate.py testing/raw/results.json` → exit 0, VERDE: 3 passed, 0 failed/flaky/skipped/interrupted. `coverage-check.py` → exit 0, 0 referencias rotas/huérfanos. Guardrail de las tres URLs localhost → exit 0.
- B1 regresión posterior al fix: coordinador ejecutó smoke normal dos veces con exit 0 y marcadores diferentes; operaciones nuevas en cada ejecución. `-VerifyPersistence` posterior exit 0. El smoke previo y la recreación completa también pasaron.
- Conexión desde contenedor temporal en red `emailing-lab` a los cuatro servicios → PASS según ejecución del coordinador. `docker compose ps` de QA confirma cuatro Healthy. No se afectó al contenedor ajeno.
- M-01 lectura de operación/puertos/red/secretos → PASS coordinador y QA. Sin pruebas manuales pendientes del alcance.
- [Informe QA](testing/report.md), [resultados](testing/raw/results.json), [capturas](testing/screenshots/). A1 documental corregido: estados de spec/plan/ledger sincronizados, 3/3 completadas. Sin telemetría real de tokens/horas.
