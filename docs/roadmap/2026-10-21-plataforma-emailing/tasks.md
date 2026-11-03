---
estado: en-progreso
generacion:
  inicio: 2026-10-21T16:28:21Z
  fin: 2026-10-21T16:34:48Z
  fuente: estimado
  tokens_reales: null
  eur: null
  horas_ia: null
  duracion: null
  duracion_reloj: 6m
  ratio_usado: 300000
  ratio_origen: default no calibrado (300000)
  nota: Sin telemetría compatible; no cifras reales de consumo.
  avisos: [carpeta de transcripciones no disponible]
estimacion_generacion:
  tokens: { entrada: 22000, salida: 15000 }
  horas_ia: 0.123333
  duracion: 7m
  metodo: Juicio sin telemetría; 37000 / 300000 tok/h, no duración de reloj.
# ventana compartida con improvement-plan.md
verificacion: obligatoria
---

# Checklist de tareas — Plataforma interna de emailing

| Campo | Valor |
|---|---|
| Estado | en-progreso |
| Fecha | 2026-10-21 |
| Plan | [improvement-plan.md](improvement-plan.md) |
| Diseño | [design.md](design.md), O1 + D2 |

> **Ledger canónico de progreso.** Este fichero es la fuente única de verdad. Cualquier implementador marca aquí cada tarea, criterios, estado y resumen. Las otras herramientas son espejo.

## Resumen de progreso

| Fase | Completadas | Total | Progreso | H. humanas (real/est) | H. IA ejec. (real/est) | Supervisión (real/est) | Tokens (real/est) |
|---|---:|---:|---:|---|---|---|---|
| Fase 1 — Runtime, cuentas y Teammates | 4 | 4 | 100% | — / 104h | — / 3.200000h | — / 0.800000h | — / 960000 |
| Fase 2 — Contactos, plantillas y SMTP | 5 | 5 | 100% | — / 92h | — / 3.016667h | — / 0.754167h | — / 905000 |
| Fase 3 — Campañas, entrega y actividad | 7 | 7 | 100% | — / 120h | — / 3.783333h | — / 0.945833h | — / 1135000 |
| Fase 4 — API y validación global | 2 | 4 | 50% | — / 60h | — / 2.400000h | — / 0.600000h | — / 720000 |
| **TOTAL** | **18** | **20** | **90%** | **— / 376h** | **— / 12,4h** | **— / 3,1h** | **— / 3720000** |

Reales desconocidos: el guion no representa consumo cero medido. Cifras de tarea base sin margen; total con reserva 451,2 h / 22.560 € equivalentes + tokens. Horas IA por tokens/300.000; redondeo decimal solo de presentación. Supervisión 25 %.

## Fase 1 — Runtime, cuentas y Teammates

**Estado**: completado · **Estimado**: 104h · **Real**: — · **Coste est.**: 5200 € equivalentes + tokens · **Tokens est.**: 960000

### T-01 — Runtime Laravel, Laragon y Compose propio

- **Descripción**: Sustituir scaffold por Laravel 13/PHP 8.4 y Livewire, con Fortify/Sanctum y versiones estables bloqueadas. Crear Compose propio Nginx/PHP-FPM/worker sin otros servicios de infra, perfiles Laragon/Docker y provisión idempotente de vhost/usuario RabbitMQ no administrador y base exclusiva de pruebas. Verificar un job real con el adaptador.
- **Estado**: completado
- **Tiempo humano**: est. 24h · real —
- **Tiempo IA (ejec.)**: est. 0.600000h · real —
- **Supervisión**: est. 0.150000h · real —
- **Previsión IA**: 145000 in / 35000 out tok · coste IA por verificar: 0.145 p_in + 0.035 p_out €; humano base 1200 € equivalente
- **Dependencias**: ninguna
- **Tipo**: devops
- **Archivos**: `.dockerignore`, `.editorconfig`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `artisan`, `app/**`, `bootstrap/**`, `config/**`, `routes/**`, `database/**`, `resources/**`, `public/**`, `storage/**`, `tests/**`, `phpunit.xml`, `pint.json`, `lang/**`, `vite.config.*`, `.env.example`, `.gitignore`, `.htaccess`, `compose.yaml`, `docker/**`, `scripts/**`, `README.md`, `docs/**`, `infra/README.md`
- **Cubre (tests)**: E2E-01 / M-01
- **Criterios spec**: CA-12/21/25
- **Verificación**: `php artisan about` → Laravel 13 y MySQL configurados · `npm run build` → assets compilados · `docker compose config --services` → solo servicios de aplicación · `php artisan test --filter=RuntimeIntegrationTest` → job real/ACL y guard de base pasan

**Criterios de aceptación**

- [x] MySQL de integración usa emailing_testing y rechaza migrar/refrescar cualquier otra base; lab_smoke permanece intacta.
- [x] Web Laragon y Docker muestran acceso válido; RabbitMQ runtime no usa administrador ni topología ajena.
- [x] Dependencias y lock incluyen adaptador estable; prueba publish/consume/reentrega real, no solo Composer.

**Subtareas**

- [x] Copiar selectivamente skeleton oficial y retirar scaffold provisional preservando infra/docs/secretos.
- [x] Configurar sockets, dependencias, build y provisión local sin exponer claves.
- [x] Arrancar servicios propios y ejecutar smoke de runtime, ACL y perfiles.


**Evidencia T-01 (implementer)**: Laravel 13.38.0/PHP 8.4.18, MySQL/rabbitmq/SMTP configurados; RuntimeIntegrationTest 3 passed (5 assertions), npm build PASS. Compose config: emailing-php, emailing-web, emailing-worker. Docker build lanzado por implementer e imagenes construidas; arranque y health de perfiles a cargo del coordinador al parar la jornada; criterio de perfiles sin cerrar. DB de producto solo migraciones incrementales.


**Medicion T-01**: ventana 2026-10-21T16:35:48Z a 16:41:50Z cerrada; fuente estimado, tokens_reales/horas_ia/eur null por falta de transcripciones compatibles. La duracion de reloj no se imputa como horas IA.

**Cierre T-01 (2026-10-22)**: la evidencia anterior describe el checkpoint histórico. Runtime final: Laravel 13.38.0, PHP de Laragon 8.4.18, Composer 2.9.4, Livewire 4.4.8, Fortify 1.41.0, Sanctum 4.3.3 y adaptador RabbitMQ 15.0.2 bloqueados. `composer validate --no-check-publish`: `./composer.json is valid`. `npm run build` con Node 22: `58 modules transformed`, `built in 4.13s`; Pint PASS. `RuntimeIntegrationTest` tiene cinco casos incluidos en la suite final `19 passed (136 assertions)`: guard de base/usuario y rechazo DB_URL antes de refrescar, conexión MySQL exclusiva, job Rabbit real con reentrega y ACL negativa.

**Perfiles verificados**: PHP nativo de Laragon en `http://127.0.0.1:8082` y Docker QA en `http://127.0.0.1:8083`, ambos con registro/dashboard/Teammates y CSRF reales contra `emailing_testing`. No se presupone un virtualhost Apache `emailing.test` configurado en esta máquina. Docker build final PASS; `up -d --wait` dejó PHP, Nginx y worker healthy. `docker compose config --services`: `emailing-php`, `emailing-web`, `emailing-worker`; no nuevas instancias de infra. Smoke Linux: `RabbitMQ publish/release/reconnect/consume/ack: PASS (application identity)`. La imagen excluye `.claude`, `.codex`, `.agents`, `.aws` y secretos, comprobado dentro del contenedor.

**Aislamiento DNS**: el alias `php-runtime` existe solo en la red privada y es el destino FastCGI. La web tiene un alias HTTP único en la red compartida: `emailing-app-web` o `emailing-qa-web`, verificados mediante Compose. Así no se resuelve accidentalmente el PHP de otro despliegue. La integración autenticada con Cronicle permanece en T-15; hoy solo se garantiza su ruta de red futura.

**Preservación**: la revisión automática rechazó el intento de migración incremental operativa por riesgo sobre la base compartida; no se ejecutó. Se completó la alternativa segura aprobada con proyecto `emailing-qa`, override ignorado `tests/.runtime/compose.qa.yaml`, base/usuario exclusivos, puerto 8083 y worker `emailing.qa.jobs`. Comprobación del coordinador solo lectura: `emailing.lab_smoke`, cinco filas conservadas, sin mostrar contenido. Ninguna prueba destructiva usa `emailing`.

**Medición de reanudación T-01**: ventanas 2026-10-22T07:52:15Z–07:54:25Z y 08:21:58Z–08:26:04Z cerradas; fuente estimado, tokens_reales/horas_ia/eur null. No se imputa reloj como consumo.

- **Changelog**: Docker mantiene FastCGI privado y consume la cola configurada; los perfiles local y Docker conservan pruebas aisladas y acceso a la infraestructura compartida.

**Corrección T-01-fix1 (2026-10-22, en validación)**: C2 sustituye FPM TCP por socket Unix en volumen privado de cada proyecto, montado solo en PHP/Nginx (Nginx en lectura), manteniendo PHP no root y salida hacia MySQL/RabbitMQ/SMTP. B-01 retira `--queue` fijo; el override ignorado de QA configura `emailing.qa.jobs` para productor y worker. `RuntimeIntegrationTest`: `6 passed (14 assertions)`, incluye productor y `queue:work --once` sin argumento de cola con cola alternativa real. Docker QA reconstruido y PHP/Nginx healthy; socket local accesible, TCP 9000 cerrado en loopback y desde IP de lab, worker sin socket/montaje, MySQL/SMTP y smoke RabbitMQ Linux PASS. El despliegue antiguo `emailing-app` se detiene reversiblemente (PHP/web/worker exited), conservando sus volúmenes e infraestructura de cuatro servicios healthy; no se ejecutan migraciones operativas. ADR-004 queda propuesta.

**Medición T-01-fix1**: ventana 2026-10-22T14:14:47Z–14:17:16Z cerrada; fuente estimado, tokens_reales/horas_ia/eur null. La validación común posterior se mide en T-02-fix1, sin duplicar ni convertir reloj en consumo.

### T-02 — Identidades, cuentas y selector multi-cuenta

- **Descripción**: Crear registro interno configurable, login/logout y recuperación Fortify; alta transaccional de cuenta y membresía owner/Admin. Resolver cuenta activa desde membresía vigente y proteger cada acción Livewire/HTTP.
- **Estado**: completado
- **Tiempo humano**: est. 22h · real —
- **Tiempo IA (ejec.)**: est. 0.493333h · real —
- **Supervisión**: est. 0.123333h · real —
- **Previsión IA**: 120000 in / 28000 out tok · coste IA por verificar: 0.12 p_in + 0.028 p_out €; humano base 1100 € equivalente
- **Dependencias**: T-01
- **Tipo**: backend
- **Archivos**: `app/**`, `bootstrap/**`, `config/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/desarrollo/**`
- **Cubre (tests)**: E2E-01 / E2E-02 / M-01
- **Criterios spec**: CA-01/13/16/18
- **Verificación**: `php artisan test --filter=AccountAuthenticationTest` → registro/login/reset y selector MySQL pasan

**Criterios de aceptación**

- [x] Registro autorizado crea identidad/cuenta/owner atómicos; deshabilitado no concede alta operativa.
- [x] Solicitudes sin auth/CSRF o IDs ajenos se deniegan; selector no arrastra permisos de otra cuenta.
- [x] Reset por Mailpit permite recuperar acceso con token válido y único.

**Subtareas**

- [x] Crear migraciones/constraints y contexto de cuenta servidor.
- [x] Implementar Fortify, formularios y selector en español.
- [x] Probar dos cuentas y permisos distintos con MySQL y Mailpit.


**Pausa solicitada por el usuario (2026-10-21)**: checkpoint parcial, NO terminado. Creados modelos Account/Membership/AuditEvent, migracion incremental, AccountContext, acciones Fortify y vistas base/selector. Falta ejecutar/verificar AccountAuthenticationTest (aun no existe), CSRF real, reset via Mailpit y permisos efectivos; no hay QA ni revision. T-03 invitaciones/roles/Livewire aun sin iniciar.

**Medicion T-02**: ventana 2026-10-21T16:41:50Z a 16:45:50Z cerrada; fuente estimado; tokens_reales/horas_ia/eur null. Sin consumo real inventado.

**Reanudación 2026-10-22**: la nota de pausa anterior es histórica. Cuentas, autenticación y Teammates implementados; revisión y QA independientes permanecen en T-04. Toda prueba destructiva usa únicamente `emailing_testing`.

**Evidencia T-02 (2026-10-22)**: `AccountAuthenticationTest`: 6 passed. Registro/login/logout, rollback transaccional, alta deshabilitada, selector con membresías vigentes, acceso ajeno/revocado, CSRF HTTP 419 y recuperación real por SMTP/Mailpit de un solo uso. Suite completa final: `Tests: 19 passed (136 assertions)`, `Duration: 48.72s`. Smoke HTTP nativo en `http://127.0.0.1:8082`: registro, sesión, dashboard y Teammates con CSRF real PASS; solo datos ficticios y base exclusiva.

**Medición de reanudación T-02**: ventana 2026-10-22T07:54:25Z a 07:59:09Z cerrada; fuente estimado; tokens_reales/horas_ia/eur null. Sin imputar duración de reloj como horas IA.

- **Changelog**: Los enlaces de recuperación e invitación usan el origen configurado; la recuperación invalida sesiones previas y los formularios rechazan emails de tipo inválido.

**Corrección T-02-fix1 (2026-10-22)**: C1 construye enlaces secretos mediante APP_URL confiable y ruta relativa, tanto en ResetPassword como en TeamInvitation. Cuatro casos HTTP con Host/esquema/cabeceras manipuladas inspeccionan las notificaciones realmente renderizadas; la invitación utiliza la ruta HTTP de actualización Livewire efectiva, sin notificaciones simuladas. B-02 añade validación de tipos previa al controlador Fortify y al limitador, con prioridad explícita y defensa en CreateNewUser. Registro/login HTTP rechazan array, array vacío, número y null con 422, manteniendo el login válido con mayúsculas.

**Verificación final de T-01/T-02**: `php artisan test --testsuite=Feature`: **30 passed (230 assertions), 174.31s**; incluye los seis RuntimeIntegrationTest, las nueve regresiones de tipos/origen, la regresión de sesiones, Mailpit de recuperación e invitación, atomicidad, CSRF, cuentas, roles y concurrencia MySQL. Composer: `./composer.json is valid`. Pint terminó exit 0, formateando únicamente los imports/estilo de los nuevos archivos. Assets y manifests no cambian; Docker reutiliza su etapa de assets en caché. Todos los refrescos de esquema quedan detrás del guard de base y usuario `emailing_testing`.

**Perfiles finales**: `tests/support/http-profile-smoke.py` pasa en 8082 y 8083: `/up` y `/login` HTTP 200, login sin CSRF 419, registro/login con email array y CSRF válido 422. `tests/support/password-reset-sessions.php` pasa en ambos perfiles: tres sesiones previas independientes rechazadas y nuevo login válido después del reset; cleanup exclusivo de su UUID. Docker QA: build y `up -d --wait` exit 0, FPM/Nginx healthy y worker running; `nginx -t` correcto. Checks de `tests/support/runtime-boundary.php` confirman socket local, FPM no root, TCP 9000 inaccesible por loopback/IP lab, worker sin socket, MySQL/SMTP accesibles y cola efectiva de QA. Smoke RabbitMQ Linux PASS; alias HTTP y colas independientes respecto del proyecto base. Viejo proyecto base detenido, sin borrar volúmenes; infra cuatro healthy.

**Medición T-02-fix1**: ventana 2026-10-22T14:17:16Z–14:36:55Z cerrada, inmediatamente después de T-01-fix1 sin solape; fuente estimado, tokens_reales/horas_ia/eur null. Incluye validación común posterior y C3, sin duplicar consumo ni imputar tiempo de reloj como horas IA. T-01/T-02 vuelven a implementadas; T-04 permanece en-progreso para revisión 2 y QA independientes, sin comenzar fase 2.

### T-03 — Teammates, invitaciones, roles y auditoría

- **Descripción**: Implementar invitaciones por email con token hash, siete días, reenvío/revocación y aceptación transaccional email-bound. Aplicar Admin/Read-only/Restricted, scopes y presets del catálogo servidor; proteger owner y auditar actor/cuenta/acción.
- **Estado**: completado
- **Tiempo humano**: est. 26h · real —
- **Tiempo IA (ejec.)**: est. 0.573333h · real —
- **Supervisión**: est. 0.143333h · real —
- **Previsión IA**: 140000 in / 32000 out tok · coste IA por verificar: 0.14 p_in + 0.032 p_out €; humano base 1300 € equivalente
- **Dependencias**: T-02
- **Tipo**: backend
- **Archivos**: `app/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/desarrollo/**`
- **Cubre (tests)**: E2E-02 / E2E-03 / E2E-04 / M-01
- **Criterios spec**: CA-13/15/17/18/20
- **Verificación**: `php artisan test --filter=Teammate` → permisos, invitaciones, revocación y auditoría MySQL pasan

**Criterios de aceptación**

- [x] Token vencido/usado/revocado/sustituido o identidad incorrecta no crea membresía; aceptación concurrente crea una.
- [x] Read-only/Restricted no elevan scopes ni administran miembros; último owner/Admin protegido.
- [x] Revocación y cambios de permisos se aplican en siguiente acción, conservan recursos y registran auditoría.

**Subtareas**

- [x] Crear políticas/scopes y UI de Teammates con estados observables.
- [x] Enviar invitaciones por Mailpit e implementar aceptación/reenvío/revocación.
- [x] Verificar matriz de miembros, transferencias protegidas y eventos Livewire manipulados.

**Evidencia T-03 (2026-10-22)**: `TeammateTest` + `TeammateConcurrencyTest`: `8 passed (77 assertions)`. Invitación enviada y leída por SMTP/Mailpit, hash y caducidad, email vinculado, enlace sustituido/revocado/consumido, dos procesos MySQL concurrentes con una sola membresía/un evento, matriz de roles, scopes falsificados y IDs ajenos, transferencia con contraseña actual y revocación efectiva. La cuenta se bloquea durante cambios y el permiso administrador se vuelve a leer con bloqueo bajo MySQL REPEATABLE READ. UI Livewire en español; sin recursos ficticios ni navegación a módulos inexistentes.

**Medición T-03**: ventana 2026-10-22T07:59:09Z a 08:21:58Z cerrada; fuente estimado; tokens_reales/horas_ia/eur null. El trabajo transversal de localización y runtime se verificó conjuntamente con T-01/T-02; no se duplica consumo medido.

- **Changelog**: Los administradores pueden invitar y gestionar Teammates con permisos específicos, revocar accesos y transferir la propiedad con auditoría de los cambios.

### T-04 — Revisión y QA de la primera fase

- **Descripción**: Consolidar plan y ejecutar revisión adversarial de alcance/corrección y lentes condicionales, correcciones y QA de C-01. Incluye 8 h equivalentes de proceso y 24 h de revisión/QA, sin declarar medición real. Publicar documentación de uso y evidencia; C-02 a C-07 permanecen pendientes.
- **Estado**: completado
- **Tiempo humano**: est. 32h · real —
- **Tiempo IA (ejec.)**: est. 1.533333h · real —
- **Supervisión**: est. 0.383333h · real —
- **Previsión IA**: 380000 in / 80000 out tok · coste IA por verificar: 0.38 p_in + 0.08 p_out €; humano base 1600 € equivalente
- **Dependencias**: T-01, T-02, T-03
- **Tipo**: test
- **Archivos**: `app/**`, `bootstrap/**`, `config/**`, `routes/**`, `database/**`, `resources/**`, `public/**`, `tests/**`, `scripts/**`, `docker/**`, `compose.yaml`, `composer.*`, `package*.json`, `phpunit.xml`, `vite.config.*`, `.env.example`, `.gitignore`, `.gitattributes`, `.htaccess`, `storage/**`, `README.md`, `infra/README.md`, `docs/**`, `CHANGELOG*`
- **Cubre (tests)**: E2E-01 / E2E-02 / E2E-03 / E2E-04 / M-01
- **Criterios spec**: CA-01/12/13/15/16/17/18/20/21/25
- **Verificación**: `php artisan test --testsuite=Feature` → fase 1 verde con base exclusiva · `python "$env:CUSTOM_AGENTS_PLUGIN_ROOT/agent-kits/shared/ledger-lint.py" docs\roadmap\2026-10-21-plataforma-emailing/tasks.md` → exit 0 · lectura: informe Playwright E2E-01–04 y qa-gate con cero fallos/flaky

**Criterios de aceptación**

- [x] No hallazgos Critical/Important abiertos; ambos perfiles verificados con evidencia.
- [x] E2E de registro/reset/cuentas/invitaciones/revocación/roles/aislamiento pasan sin flaky.
- [x] Documentación detallada de infra enlazada; secretos/volúmenes/datos ajenos preservados.

**Subtareas**

- [x] Registrar revisión y corregir hallazgos con evidencia.
- [x] QA implementa y ejecuta E2E-01 a E2E-04; revisar visual responsive.
- [x] Actualizar ledger/reportes, documentación y relevo de fase.

- **Changelog**: El acceso, las cuentas y Teammates cuentan con guías de uso y pruebas verificadas en Laragon y Docker.

## Fase 2 — Contactos, plantillas y SMTP

**Estado**: completado · **Estimado**: 92h · **Real**: — · **Coste est.**: 4600 € equivalentes + tokens · **Tokens est.**: 905000

### T-05 — Contactos y listas compartidos por cuenta

- **Descripción**: Crear CRUD de contactos/listas con pertenencia a cuenta, paginación, permisos y trazabilidad de origen/fecha de consentimiento.
- **Estado**: completado
- **Tiempo humano**: est. 14h · real —
- **Tiempo IA (ejec.)**: est. 0.366667h · real —
- **Supervisión**: est. 0.091667h · real —
- **Previsión IA**: 90000 in / 20000 out tok · coste IA por verificar: 0.09 p_in + 0.02 p_out €; humano base 700 € equivalente
- **Dependencias**: T-04
- **Tipo**: backend
- **Archivos**: `app/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/desarrollo/**`
- **Cubre (tests)**: E2E-05 / M-01
- **Criterios spec**: CA-03/13
- **Verificación**: `php artisan test --filter="TemplateTest|MailTransportTest|ContactListTest" --no-ansi` → exit 0, 42 passed (531 assertions), Duration: 142.36s; MySQL protegido; stdout nuevo `tests/.runtime/t09-fix1-green.txt`.

**Criterios de aceptación**

- [x] Un teammate autorizado utiliza recursos creados por otro en su cuenta.
- [x] Cuenta ajena o scope insuficiente no lee/escribe/lista/exporta/agrega.

**Subtareas**

- [x] Crear modelos/políticas y componentes.
- [x] Probar consentimiento y matriz por cuenta.

- **Changelog**: Contactos y listas compartidos por cuenta, con permisos, paginación y trazabilidad del consentimiento.
- **Evidencia T-05**: UI española en `/contacts`, CRUD y afiliación idempotente por cuenta. Los servicios autorizan cada lectura, agregado y escritura; las escrituras bloquean cuenta y membresía para respetar cambios de permisos. Los controles UI reflejan el scope vigente. No se incorpora exportación en esta tarea.
- **Política local de email**: `trim` + minúsculas Unicode, sin eliminar puntos/alias ni equiparar acentos; `utf8mb4_bin` distingue `jose` de `josé`. Email inmutable tras el alta; para otra dirección se crea otro contacto. UNIQUE(account_id, active_email), con columna almacenada generada solo para contactos activos, impide duplicados aun con concurrencia; la comprobación de colisión hace lectura bloqueante tras adquirir el lock de cuenta. MySQL real rechaza duplicado activo (1062) y las tres asociaciones entre cuentas probadas (1452), con UNIQUE(account_id,id) explícitos para ambas FKs compuestas.
- **Consentimiento y borrado**: desconocido por defecto; otorgado exige fuente y fecha UTC explícitas no futuras. Editar el nombre no cambia el consentimiento ni inventa fecha. Revocado no se reactiva desde CRUD. Los eventos de auditoría conservan antes/después y actor. Contacto borrado lógicamente y desvinculado; borrar lista o retirar afiliación conserva contactos y asociaciones no seleccionados. Recrear un email retirado crea otro ID desconocido y conserva el historial anterior; no modifica futuras supresiones.
- **Pruebas**: teammate gestiona recursos creados por otro; scopes/IDs forjados ajenos, listados, agregados y permisos retirados tras montar bloqueados sin efectos ajenos; paginación y búsqueda aisladas; reintentos inválido→válido sobre la misma instancia limpian errores y persisten el resultado. `TestingDatabaseGuard` se ejecuta antes de RefreshDatabase: solo MySQL/base/usuario `emailing_testing`; sin migraciones en `emailing`. Datos sintéticos `@example.test`. GOT-001 se usa como indicio propuesto confirmado por regresiones, sin cambios a autenticación ni Team.
- **Validación adicional**: Pint focalizado exit 0. `ledger-lint` exit 0: 0 incoherencias y 15 avisos, todos por Changelog ausente en T-06 a T-20 pendientes. `git diff --check` exit 0. Scope-check exit 0, sin fuera de alcance, avisos, info ni exclusiones de usuario. Consumo real permanece desconocido; los medidores los gestiona el orquestador.

- **Bordes de integración verificados**: `bail/string/max` valida tipos/tamaño de email y fuente antes de normalizarlos: array/número y email de 100k producen ValidationException sin escrituras. El enlace de contactos usa membresía vigente opcional; perder la cuenta activa no bloquea `/accounts`. Se prueba recuperar otra cuenta válida y que los contactos de la revocada siguen inaccesibles. Fuentes y tests congelados durante la pasada final.

Salida real de la verificación (capturada directamente de subprocess; ANSI retirado solo para lectura; `PHP_INI_SCAN_DIR=storage/php-conf`):

```text
PASS  Tests\Feature\ContactListTest
  ✓ contact and list crud are shared with authorized teammates                                                  14.97s
  ✓ contact form retry clears errors and requires explicit consent evidence                                      5.04s
  ✓ list and association retry clear previous errors on same instance                                            2.42s
  ✓ future consent is rejected and unknown consent stays unknown when name changes                               2.61s
  ✓ service rejects structured or numeric email and consent source before normalizing                            1.60s
  ✓ deleting a list preserves contacts and other list associations                                               1.91s
  ✓ deleted email can be recreated without restoring consent or history                                          1.55s
  ✓ email policy keeps accents distinct case normalized and account isolated                                     3.20s
  ✓ mysql rejects duplicate active email even when bypassing service                                             0.37s
  ✓ mysql composite foreign keys reject both cross account associations                                          0.33s
  ✓ foreign ids cannot be read edited deleted filtered or associated                                             4.46s
  ✓ read only and restricted readers cannot mutate any resource                                                  5.02s
  ✓ missing read scope and revoked membership block listings and aggregates                                      2.65s
  ✓ pagination search and aggregates stay inside active account                                                  2.50s

  Tests:    14 passed (208 assertions)
  Duration: 49.95s
```


**Correcciones T09-fix1 (revisión 1)**

- D-01 reproducido antes del cambio con 10 listas, 1.000 contactos y 10.000 afiliaciones: conteos correctos pero EXPLAIN real de la subconsulta usaba `index`, prefijo `(account_id, contact_id)` y 10.001 filas estimadas por recorrido. Consulta puntual medida: 0.1240237s. Nueva migración incremental `2026_10_22_000006_index_contact_list_membership_counts.php` añade `(contact_list_id, contact_id)` sin alterar migraciones publicadas ni FKs tenant. EXPLAIN posterior sin FORCE INDEX usa `ref`, `contact_list_memberships_list_contact_index`, `used_key_parts=[contact_list_id]`; los diez conteos siguen en 1.000 y hay 10.000 afiliaciones. Consulta puntual medida: 0.0496152s; no es un benchmark general ni se equiparan estimaciones EXPLAIN con filas reales. Planes y medidas privados: `tests/.runtime/t09-fix1-list-plan-before.json` y `tests/.runtime/t09-fix1-list-plan-after.json`. DDL aplicado por RefreshDatabase antes de transacciones de prueba, solo en MySQL de testing protegido.
- Candidato de coherencia UI confirmado: componente montado con lectura/escritura, retirada solo lectura, y llamada a deleteContact/saveList/deleteList/associate/detach persistía efectos y auditoría antes del 403 del render; saveContact ya se detenía por su lectura de servicio. `Contacts::boot` comprueba `contacts.read` vigente antes de cualquier acción. Regresión sobre la misma instancia cubre las seis acciones y exige datos, afiliaciones y auditoría intactos. No se cambiaron servicios de escritura ni API futura; write seguía autorizado y se clasifica como coherencia UI, no vulnerabilidad de cuenta.
- RED conjunto de seis regresiones: exit 2, 6 failed (22 assertions), 27.03s, `tests/.runtime/t09-fix1-red.txt`. GREEN conjunto: exit 0, 42 passed (531 assertions), 142.36s, incluye 16 pruebas ContactListTest y las clases TemplateTest/MailTransportTest. Se conservan logs anteriores del checkpoint.
- Pint focal exit 0 (`tests/.runtime/t09-fix1-pint.txt`), scope-check exit 0 sin archivos fuera de alcance ni avisos y git diff --check exit 0. Sin suite global, QA, build, Docker ni reinicio de servicios en este despacho.

### T-06 — Importación CSV validada y deduplicada

- **Descripción**: Importar CSV con resumen por fila, validación de email/consentimiento y deduplicación por cuenta; límite inicial configurable de 5 MB y 10.000 filas.
- **Estado**: completado
- **Tiempo humano**: est. 10h · real —
- **Tiempo IA (ejec.)**: est. 0.283333h · real —
- **Supervisión**: est. 0.070833h · real —
- **Previsión IA**: 70000 in / 15000 out tok · coste IA por verificar: 0.07 p_in + 0.015 p_out €; humano base 500 € equivalente
- **Dependencias**: T-05
- **Tipo**: backend
- **Archivos**: `app/**`, `config/**`, `scripts/**`, `docker/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/desarrollo/**`
- **Cubre (tests)**: E2E-05
- **Criterios spec**: CA-02/03/13
- **Verificación**: `php artisan test --filter=ContactImportTest` → exit 0; 13 passed (164 assertions), 37.67s, MySQL/base/usuario protegidos `emailing_testing`.

**Criterios de aceptación**

- [x] CSV inválido produce resumen consistente y no contamina otra cuenta.
- [x] No se infiere consentimiento de un email; origen/fecha obligatorios según política documentada.

**Subtareas**

- [x] Validar formato/tamaño y normalización.
- [x] Probar rollback parcial definido y archivos privados.

**Alcance técnico T-06**: config/scripts/docker se incluyen para alinear el
límite CSV de aplicación con Livewire, PHP del proyecto y Nginx/Docker. No se
modifica el php.ini global ni la infraestructura compartida. El soporte HTTP
real del límite se contrasta en QA de fase 2 después de actualizar los perfiles.

- **Changelog**: Importación CSV de contactos con límites, deduplicación por cuenta y resultados privados por registro.
- **Contrato CSV**: UTF-8 con/sin BOM, comas, comillas RFC4180 duplicadas y saltos LF/CRLF; parser nativo fgetcsv tras validación léxica estricta. Encabezados exactos sin duplicar: email obligatorio; name, consent_status, consent_source y consented_at opcionales. Columnas inconsistentes, quoting incorrecto, encoding/bytes nulos o exceso global de bytes/registros rechazan TODO antes de escribir contactos, resultados o fuente. Líneas físicas vacías se omiten; el resumen numera registros CSV, no líneas internas de un campo.
- **Contrato por registro**: validación semántica antes de locks/SQL. Registros inválidos se reportan; válidos nuevos se crean. Normalización trim + minúsculas Unicode, comparación binaria y acentos distintos como T05. Duplicados del archivo conservan la primera fila válida; duplicados activos de la cuenta se omiten enteros: no cambian campos, consentimiento NI afiliaciones. Solo altas nuevas van a la lista seleccionada; existentes se asocian manualmente desde Contactos. Otra cuenta puede tener el mismo email. Un tombstone no se restaura: la recreación tiene nuevo ID y unknown por defecto, sin copiar historial ni tocar supresiones.
- **Consentimiento**: unknown por defecto; granted exige fuente no vacía (hasta 255 caracteres) y fecha UTC explícita YYYY-MM-DDTHH:MM, año 1000..9999, calendario válido y no futura. Name hasta 150 y email hasta 254; longitudes acotadas ANTES del filtro de formato. CSV entrega strings; no hay coerciones de estructuras a strings. Filtro nativo FILTER_FLAG_EMAIL_UNICODE idéntico al usado por filter_unicode de Laravel instalado. Ningún duplicado reactiva revoked ni actualiza unknown a granted.
- **Atomicidad y rendimiento**: lectura/validación/almacenamiento del archivo antes de tomar Account mutex. Dentro de una transacción: Account + Membership actuales bloqueados, lista reautorizada, colisiones leídas con locking/current reads y SQL en lotes de 500. Sin insertIgnore/upsert ni excepciones silenciadas; una excepción revierte SQL y elimina la fuente creada. El mutex compartido con T05 serializa altas, así no se necesita un algoritmo de recuperación de carreras diferente. Prueba real 10.000 registros: 72 consultas, 10.3755973s en importador; caso completo 11.07s, sin umbral temporal artificial. Métricas privadas en tests/.runtime/t06-bulk-metrics.json.
- **Privacidad y permisos**: fuente en disco local dedicado contact-imports sin serve/preview público; metadatos/resultados SQL ligados por FK compuesta a la cuenta/importación. Auditoría registra actor, origen CSV, ID y conteos sin volcar CSV/emails a logs. UI pagina 25 resultados y 10 importaciones/listas, nunca 10.000 filas en snapshot. Lectura/selección/conteos/descarga reautorizan contacts.read; subida y acción/importador exigen contacts.read Y contacts.write antes de efectos y de nuevo bajo mutex. Middleware de subida vincula handle firmado con cuenta/actor en sesión; cambiar de cuenta o forjar un handle conocido ajeno devuelve403. Download numérico protegido, generado en streaming con scope antes de abrir salida y celdas de fórmula neutralizadas; no hay descarga pública de la fuente.
- **Límites y runtime**: CONTACT_IMPORT_MAX_BYTES inicial 5242880 (5 MiB exactos), CONTACT_IMPORT_MAX_ROWS 10000; Livewire deriva su máximo KiB del mismo bytes. Archivo común docker/emailing-uploads.ini: upload_max_filesize=6M, post_max_size=8M, memory_limit=256M; los wrappers PHP/composer y serve-testing lo copian a storage/php-conf, Docker lo carga en conf.d. Nginx admite8m, con margen multipart. CLI verificada exit0 con 6M/8M/256M. Para subir el límite de app, aumentar también ini/Nginx con margen, limpiar config cache y reconstruir/reiniciar los perfiles pertinentes. No se modifica php.ini global ni se reinician 8082/8083; root actualizará perfiles al cerrar fase2. UploadedFile fake y la ruta HTTP del test prueban la app/autorización, NO acreditan límite físico multipart HTTP de los servidores; pendiente QA fase2.
- **Correcciones con evidencia**: primera pasada 3 failed/9 passed,162.56s: harness esperaba HttpException en llamada directa que correctamente lanza ModelNotFoundException (kernel traduce404); Livewire fuerza tmp-for-tests y la prueba HTTP necesitaba fake explícito; actingAs reutilizaba sesión del owner y AuthenticateSession devolvía302 antes del scope (ahora sesiones separadas). La carga10.000 pasó pero tardaba139.92s: se sustituyó Validator por fila por comprobaciones acotadas equivalentes y fechas estrictas, manteniendo regresiones de Unicode, longitudes y fechas imposibles/futuras/bisiestas. Segunda pasada 1 failed/12 passed,20.44s: URL pública sin firma devuelve403 de Laravel, no404; la aserción exige ahora la denegación real403. Pasada final congelada toda verde; no se cambian criterios ni límites para pasar.
- **Validación adicional**: Pint focalizado exit0; ledger-lint exit0 (0 incoherencias,14 avisos por Changelog de tareas futuras T-07 a T-20); scope-check exit0 (sin fuera de alcance, avisos ni exclusiones de usuario); git diff --check exit0. Solo ContactImportTest; no suite T05/completa, build, Docker ni Playwright. No se ejecutan migraciones en emailing ni se tocan medidores/commits/push. Reales desconocidos, no cero medido.

Salida real de verificación (subprocess directo a tests/.runtime/t06-contact-import.txt; ANSI y espacios finales retirados solo en esta copia):

```text
PASS  Tests\Feature\ContactImportTest
  ✓ valid invalid and duplicate rows have consistent account scoped results                                      5.40s
  ✓ utf8 bom quoted commas newlines and distinct accents are parsed                                              0.73s
  ✓ global csv format errors never write partial contacts or files                                               1.46s
  ✓ global byte and record limits reject all rows before import                                                  2.74s
  ✓ semantic errors are reported without inventing or changing consent                                           1.05s
  ✓ recreating a tombstone does not copy historical consent                                                      0.72s
  ✓ optimized row validation preserves lengths and strict calendar dates                                         0.77s
  ✓ same livewire instance recovers from invalid to valid upload without old errors                              2.56s
  ✓ foreign list and report ids are rejected without affecting either account                                    4.75s
  ✓ removing read or write scope after mount blocks import before any effect                                     2.34s
  ✓ upload http route checks permissions and binds private temporary file to account                             0.99s
  ✓ reports are private paginated and download neutralizes csv formulas                                          1.35s
  ✓ ten thousand rows use bounded sql batches and consistent counts                                             11.07s

  Tests:    13 passed (164 assertions)
  Duration: 37.67s
```

### T-07 — Plantillas dinámicas y preview aislada

- **Descripción**: Implementar editor HTML sencillo y variables acotadas, preview sandbox y prueba con permiso de envío. Sin editor por bloques ni dependencias de pago.
- **Estado**: completado
- **Tiempo humano**: est. 20h · real —
- **Tiempo IA (ejec.)**: est. 0.500000h · real —
- **Supervisión**: est. 0.125000h · real —
- **Previsión IA**: 120000 in / 30000 out tok · coste IA por verificar: 0.12 p_in + 0.03 p_out €; humano base 1000 € equivalente
- **Dependencias**: T-05, T-08
- **Tipo**: frontend
- **Archivos**: `app/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/desarrollo/**`
- **Cubre (tests)**: E2E-06 / M-01
- **Criterios spec**: CA-04/12/13
- **Verificación**: `php artisan test --filter="TemplateTest|MailTransportTest|ContactListTest" --no-ansi` → exit 0, 42 passed (531 assertions), Duration: 142.36s; MySQL protegido; stdout nuevo `tests/.runtime/t09-fix1-green.txt`.

**Criterios de aceptación**

- [x] Scripts no se ejecutan en el panel ni escapan sandbox.
- [x] Variables se resuelven/escapan según contrato; preview/prueba requieren scopes y cuenta válidos.

**Subtareas**

- [x] Crear renderer de variables limitado y UI.
- [x] Probar HTML hostil, datos ausentes y acceso cruzado.

- **Changelog**: Añade plantillas compartidas por cuenta con editor HTML, variables acotadas, preview aislada y prueba del contenido guardado mediante el transporte de laboratorio autorizado.

**Evidencia T-07**

- Persistencia propia por cuenta y CRUD paginado; los Teammates autorizados comparten plantillas. Lectura, búsqueda, apertura, preview, escritura y prueba comprueban cuenta y scopes vigentes; mutaciones bajo mutex de Account con membresía vigente. IDs ajenos y retirada de permisos no producen escrituras ni mensajes. La prueba exige `templates.read` y `mail.send`, usa la versión guardada y un remitente autorizado de esa cuenta, sin exigir `settings.read` ni consultar contactos.
- Contrato limitado a `{{ contact.name }}`, `{{ contact.email }}` y `{{ account.name }}`; muestras explícitas y nombre de cuenta vigente. Ausencias dan vacío; variables desconocidas, incompletas y expresiones se rechazan; sustitución no recursiva. HTML escapa los valores y admite variables solo en nodos de texto del cuerpo, nunca atributos/CSS/scripts; asunto y texto conservan caracteres legibles. Asunto máximo 200 caracteres antes/después, sin CR/LF; HTML/texto hasta 100.000 bytes y 200 sustituciones, salida acotada a 200.000 bytes.
- Preview reconstruida por allowlist DOM/CSS, sin enlaces navegables, imágenes, recursos remotos, formularios ni contenido ejecutable; iframe `sandbox=""`, `referrerpolicy="no-referrer"` y CSP restrictiva. DOMDocument usa `LIBXML_NONET`, sin expansión de entidades/DTD, y restaura errores libxml. Pruebas estructurales comprueban HTML hostil, escape de textarea, CSP y ausencia de conexión al sentinel DTD. La ejecución/navegación/peticiones en navegador real queda para QA de fase en ambos perfiles.
- UI en español con vacío, carga, error, éxito, paginación y modo lectura. Regresión reproducida de preview obsoleta: editar HTML inválido con preview abierta provocaba error durante render (`Call to a member function with() on null`; rojo focal exit 2, 1 failed/2 assertions/8.56s). Ahora editar borrador/muestras invalida preview y la acción la cierra antes de validar. Misma instancia: preview válida → edición inválida → oculta/sin 500 → error validado → corrección → preview explícita válida; también guardado inválido→válido limpia errores.
- Fuentes: `EmailTemplate`, migración `000005`, `TemplateService`, `TemplateRenderer`, `TemplatePreview`, `HtmlDocument`, componente/vista `Templates`, ruta/nav y `TemplateTest`. Sin dependencias nuevas, envíos externos, migraciones de desarrollo ni cambios a credenciales.
- Stdout final capturado directamente del proceso en `tests/.runtime/t07-template.txt` (privado), fuentes congeladas:

```text
PASS  Tests\Feature\TemplateTest
  ✓ authorized teammates share crud and successful retry clears errors                                           9.34s
  ✓ html values escape quotes and tags while subject and text stay readable                                      0.49s
  ✓ unknown expressions malformed variables and attribute contexts are rejected                                  0.92s
  ✓ expansion limits and subject headers are checked after substitution                                          0.89s
  ✓ preview allowlist removes active content navigation remote urls and css functions                            0.54s
  ✓ editor escapes hostile html and iframe has no sandbox permissions                                            2.14s
  ✓ dom parser does not fetch external dtd and restores libxml error mode                                        1.61s
  ✓ preview is invalidated on draft change and invalid to valid retry clears errors                              2.77s
  ✓ foreign template ids are denied in open save delete preview and test                                         1.36s
  ✓ readers cannot mutate and revoked read blocks before writes or sends                                         3.63s
  ✓ developer can test saved template without settings or contacts permissions                                   3.50s
  ✓ pagination search and missing read scope keep account data private                                           1.00s

  Tests:    12 passed (126 assertions)
  Duration: 29.42s
```

- Gates focalizados: Pint `--test` exit 0 sobre las nueve fuentes PHP cambiadas; scope-check exit 0 sin archivos fuera de alcance ni avisos; git diff --check exit 0. ledger-lint corregido con contadores 8/20 (40%) y fase 2 4/5 (80%); doce avisos esperados por Changelog de futuras T-09 a T-20. Sin suite global, build, Docker ni QA de navegador en esta tarea.

**Correcciones T09-fix1 (revisión 1)**

- B-01 reproducido: plantilla HTML solo imagen y texto alternativo vacío se guardaba/previsualizaba, pero transporte exigía text y fallaba antes de persistir. TemplateService y MailInput aceptan HTML-only/text-only, exigen al menos un cuerpo no vacío y normalizan ausencia/null del otro a cadena vacía después de validar tipos/límites. AccountMailTransport agrega solo las partes presentes (incluido el texto literal `0`). Regresiones rechazan ambos vacíos/ausentes y tipos estructurados, y generan .eml privado con el HTML original de la imagen, sin SMTP externo. UI explica que basta uno de los dos cuerpos.
- B-02 reproducido: token raw en href compensado por llaves codificadas como entidades en texto. RED focal exacto exit 1, 1 failed (1 assertion), 7.87s, `tests/.runtime/t09-fix1-b02-red.txt`; también se reproducía rechazo indebido del positivo literal codificado. El renderer marca cada ocurrencia raw antes de parsear con prefijo ausente del origen y DOM canónico; el recorrido de contextos comprueba esas marcas, por lo que las entidades no compensan ubicaciones prohibidas. No hay escaneo del documento por cada token. Regresiones decimal/hex cubren atributos, comentarios, CSS, script y textarea, más texto codificado junto a token válido. Escape, sustitución no recursiva, HTML autorizado de envío y preview aislada permanecen. Defecto contractual, sin clasificarlo como vulnerabilidad adicional.
- GREEN congelado `TemplateTest|MailTransportTest|ContactListTest`: exit 0, 42 passed (531 assertions), Duration: 142.36s; 15 pruebas TemplateTest, 11 MailTransportTest y 16 ContactListTest. Stdout real privado nuevo `tests/.runtime/t09-fix1-green.txt`; conserva evidencia anterior. RED conjunto y Pint focal en `tests/.runtime/t09-fix1-red.txt` y `tests/.runtime/t09-fix1-pint.txt`.
- Pint focal exit 0 sobre nueve fuentes PHP, scope-check exit 0 sin hallazgos y git diff --check exit 0. No suite global/build/Docker/QA; el coordinador realiza regresión y revisión incremental antes de QA de navegador.

### T-08 — SMTP, remitentes y log privado

- **Descripción**: Guardar configuración SMTP/TLS y remitentes por cuenta con secretos cifrados. Modo log seguro por defecto, prueba Mailpit y diagnóstico depurado; no correo externo por defecto.
- **Estado**: completado
- **Tiempo humano**: est. 24h · real —
- **Tiempo IA (ejec.)**: est. 0.666667h · real —
- **Supervisión**: est. 0.166667h · real —
- **Previsión IA**: 165000 in / 35000 out tok · coste IA por verificar: 0.165 p_in + 0.035 p_out €; humano base 1200 € equivalente
- **Dependencias**: T-04
- **Tipo**: backend
- **Archivos**: `app/**`, `config/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/desarrollo/**`, `compose.yaml`, `.env.example`
- **Cubre (tests)**: E2E-07
- **Criterios spec**: CA-07/08/12/14
- **Verificación**: `php artisan test --filter=MailTransportTest` → exit 0; 10 passed (139 assertions), 29.46s, MySQL/base/usuario protegidos emailing_testing.

**Criterios de aceptación**

- [x] Log genera eml privado y no conecta SMTP; descarga exige cuenta/scope.
- [x] Secretos cifrados no aparecen en HTML/logs/errores/payloads; TLS inválido se rechaza.
- [x] Aceptación Mailpit se etiqueta SMTP aceptado, nunca entrega.
- [x] Correo externo exige dominio/remitentes autorizados y evidencia SPF/DKIM/DMARC; se concreta relay y mecanismo de feedback antes de habilitarlo. Mailpit/log siguen permitidos para laboratorio.

**Subtareas**

- [x] Crear transportes por cuenta/remitentes y cifrado.
- [x] Pruebas negativas de secretos/TLS y transporte por cuenta.

Ampliación T08 autorizada antes de editar: compose.yaml/.env.example separan el endpoint Mailpit confiable de MAIL_HOST/MAIL_PORT de autenticación; defaults nativos y override Docker, sin reiniciar infraestructura.

- **Changelog**: Transporte por cuenta con log privado, captura Mailpit, remitentes autorizados para laboratorio y credenciales SMTP cifradas.
- **Transporte y límite externo**: log por defecto, Mailpit explícito con host/puerto confiables de config del proyecto; nunca se conecta al host SMTP introducido por el usuario. Configuración SMTP externa puede prepararse, pero no hay modo externo activable y una configuración forjada en SQL queda blocked/external_not_enabled sin conexión. UI exige dominio/remitentes autorizados, evidencia SPF/DKIM/DMARC, relay y feedback concretos antes de una futura habilitación. Autorización de remitente para laboratorio no verifica DNS ni entrega externa.
- **Contrato para integración**: AccountMailTransport::send(Account, senderId, [recipient,subject,text,html?], actorId?) recibe cuenta explícita validada por su fachada; no depende de auth/sesión. Reconsulta remitente y modo por esa cuenta. T07 podrá autorizar templates.read+mail.send y usarlo sin añadir settings.read; la fachada de esta pantalla sí exige settings.read+mail.send. Prueba directa sin sesión crea .eml propios para dos cuentas/remitentes y bloquea el remitente cruzado. Guardados/remitentes reautorizan settings.read+settings.write y toman Account/Membership mutex compatible con TeamService; lecturas defaults no persisten nada. IO SMTP no mantiene ese lock.
- **Resultados sin promesas falsas**: logged significa archivo local sin conexión; smtp_accepted significa aceptación por Mailpit, NUNCA entrega. Una excepción del conector produce smtp_unconfirmed con diagnóstico fijo: puede haber aceptación y no se debe reintentar a ciegas. El conector no expone error SMTP, debug, DSN, AUTH ni causa cruda; NullLogger y diagnóstico genérico. Se conserva esa incertidumbre para la futura integración con ADR003, sin implementar workers aquí.
- **Secretos**: username/password cifrados con casts encrypted de Laravel y ocultos en serialización. No son propiedades Livewire: solo argumentos locales de la acción guardar, nunca hidratan valores persistidos. Inputs nuevos vacíos, conservación al dejar vacío y borrado explícito; Alpine los limpia en finally. El valor NUEVO viaja en la petición POST de guardado, como permite el handoff, y no se refleja en respuesta, HTML, snapshot ni auditoría, tampoco con otro campo inválido. Formulario declara POST+CSRF incluso sin JS, evitando secretos en query URL. Auditoría solo modo/IDs/banderas no sensibles.
- **.eml y permisos**: disco mail-artifacts privado sin serve, rutas/nombres generados por servidor, MIME message/rfc822, attachment y private/no-store. Descarga numérica exige activity.read vigente y cuenta antes de abrir streaming, y reconsulta dentro del callback. Pruebas de IDs ajenos, scope insuficiente y ruta pública sin firma denegados; cuenta ajena no lee/escribe/descarga recursos. Remitentes normalizados, tipos/longitudes antes de normalizar y cabeceras sin CR/LF. Teammate autorizado modifica remitente/config creados por otro; perder settings.read manteniendo write bloquea ANTES de efectos.
- **TLS y Mailpit reales**: Symfony instalado verifica certificado/nombre (verify_peer y verify_peer_name true, allow_self_signed false); STARTTLS obligatorio o TLS implícito sin fallback plano. Certificado/clave generados en tests/.runtime ignorado; procesos/socket locales aislados de la fixture prueban conexiones reales en SMTPS y STARTTLS rechazadas por TLS no confiable, sin subir claves a Git. Mailpit real capturó el mensaje con destinatario/asunto sintéticos únicos y remitente correcto; no se borra ninguna bandeja ni mensaje ajeno. Log usa socket centinela y acredita cero conexiones SMTP.
- **Perfiles**: config/.env.example definen endpoint Mailpit nativo separado de autenticación; compose.yaml lo cambia a mailpit:1025 (API interna8025). No se modifica MAIL_HOST/MAIL_PORT de autenticación ni se reinician perfiles. Configuración Docker se verificará en QA de fase2.
- **Verificación**: primera pasada 10/132 verde,30.65s; antes del cierre se añadió POST explícito + regresión DOM y positivo teammate. Pasada final con fuentes/tests congelados: 10/139 verde,29.46s. Pint focalizado exit0; ledger-lint exit0 (0 incoherencias,13 avisos de Changelog de tareas pendientes); scope-check exit0 (sin fuera de alcance/avisos/exclusiones de usuario); git diff --check exit0. Solo MailTransportTest; sin T05/fullFeature/build/Playwright/Docker ni migraciones en emailing. Sin commits/push ni cambios de medidores; reales desconocidos.

Salida real de verificación (subprocess directo a tests/.runtime/t08-mail-transport.txt; ANSI y espacios finales retirados solo en esta copia):

```text
PASS  Tests\Feature\MailTransportTest
  ✓ default log creates private eml without smtp socket and download is scoped                                   8.26s
  ✓ settings defaults and read only render do not persist configuration                                          0.90s
  ✓ secrets are encrypted hidden and never reflected even after invalid save                                     2.08s
  ✓ sender forms validate header types and retry on the same component                                           4.33s
  ✓ unauthorized foreign and revoked sender actions have no effects                                              4.57s
  ✓ account transport is sessionless and never uses another accounts mode or sender                              0.80s
  ✓ mailpit real acceptance is captured and is never labeled delivery                                            1.39s
  ✓ invalid message headers and external mode are rejected without artifacts                                     1.78s
  ✓ smtp failures are sanitized and keep uncertainty without echoing saved secrets                               1.44s
  ✓ real untrusted tls certificate is rejected for implicit tls and starttls                                     2.12s

  Tests:    10 passed (139 assertions)
  Duration: 29.46s
```

### T-09 — Revisión y QA de recursos y transportes

- **Descripción**: Revisar diff y validar recursos compartidos, importación, preview y SMTP antes de campañas.
- **Estado**: completado
- **Tiempo humano**: est. 24h · real —
- **Tiempo IA (ejec.)**: est. 1.200000h · real —
- **Supervisión**: est. 0.300000h · real —
- **Previsión IA**: 300000 in / 60000 out tok · coste IA por verificar: 0.3 p_in + 0.06 p_out €; humano base 1200 € equivalente
- **Dependencias**: T-05, T-06, T-07, T-08
- **Tipo**: test
- **Archivos**: `app/**`, `config/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/**`, `README.md`, `.gitattributes`, `CHANGELOG*`
- **Cubre (tests)**: E2E-05 / E2E-06 / E2E-07 / M-01
- **Criterios spec**: CA-02/03/04/07/08/12/13/14
- **Verificación**: `php artisan test --testsuite=Feature` → verde · lectura: E2E-05–07, revisión y qa-gate sin fallos/flaky
- **Changelog**: Se han validado contactos, importaciones, plantillas y pruebas de correo compartidas por cuenta en Laragon y Docker, con descargas privadas y permisos vigentes.

**Criterios de aceptación**

- [x] Matriz de dos cuentas, tres identidades y scopes pasa.
- [x] Revisión no deja hallazgos Critical/Important abiertos.

**Subtareas**

- [x] Revisión adversarial y correcciones.
- [x] Ejecutar E2E y actualizar evidencias/ledger.

## Fase 3 — Campañas, entrega y actividad

**Estado**: completado · **Estimado**: 120h · **Real**: — · **Coste est.**: 6000 € equivalentes + tokens · **Tokens est.**: 1135000

### T-10 — Bajas y supresiones antes de enviar

- **Descripción**: Crear baja pública con token ligado a cuenta/destinatario, idempotente y no enumerable; supresión manual por cuenta y consulta inmediata pretransporte.
- **Estado**: completado
- **Changelog**: Las bajas y supresiones detienen el envío pendiente de su cuenta, incluso después de publicar en RabbitMQ, sin bloquear el mismo email en otra cuenta.
- **Tiempo humano**: est. 16h · real —
- **Tiempo IA (ejec.)**: est. 0.446667h · real —
- **Supervisión**: est. 0.111667h · real —
- **Previsión IA**: 110000 in / 24000 out tok · coste IA por verificar: 0.11 p_in + 0.024 p_out €; humano base 800 € equivalente
- **Dependencias**: T-09
- **Tipo**: backend
- **Archivos**: `app/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/desarrollo/**`
- **Cubre (tests)**: E2E-08
- **Criterios spec**: CA-10/11/13/14
- **Verificación**: `php artisan test --filter=SuppressionTest` → bajas repetidas/alteradas y aislamiento pasan

**Criterios de aceptación**

- [x] Token alterado no revela/cambia datos; repetido es idempotente.
- [x] Mismo email en otra cuenta permanece sin modificar y pendientes se suprimen antes del envío.
- [x] El mecanismo de feedback elegido incorpora rebotes definitivos y quejas autenticadas como supresiones idempotentes; eventos duplicados o falsificados no alteran otras cuentas. Sin soporte de feedback se muestra la limitación, sin fingir una tasa saludable.

**Subtareas**

- [x] Definir tokens/rutas y retención inicial documentada.
- [x] Probar baja después de encolar y antes del transporte.

### T-11 — Campañas, revisión y programación

- **Descripción**: Crear borrador/revisión/programación con permisos, snapshot/referencias de destinatarios y fecha efectiva.
- **Estado**: completado
- **Changelog**: Las campañas requieren revisión y recursos autorizados; los borradores, fechas futuras y cambios de permisos no habilitan un envío prematuro.
- **Tiempo humano**: est. 12h · real —
- **Tiempo IA (ejec.)**: est. 0.286667h · real —
- **Supervisión**: est. 0.071667h · real —
- **Previsión IA**: 70000 in / 16000 out tok · coste IA por verificar: 0.07 p_in + 0.016 p_out €; humano base 600 € equivalente
- **Dependencias**: T-10
- **Tipo**: frontend
- **Archivos**: `app/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/desarrollo/**`, `.gitattributes` (preservación byte a byte de JSON de evidencia)
- **Cubre (tests)**: E2E-08 / M-01
- **Criterios spec**: CA-03/05/13
- **Verificación**: `php artisan test --filter=CampaignTest` → borrador/futura/autorizada y scopes pasan

**Criterios de aceptación**

- [x] Borradores o campañas futuras no envían prematuramente.
- [x] Destinatarios/plantillas/remitentes se validan contra cuenta y scopes efectivos.

**Subtareas**

- [x] UI de composición/resumen y transición de estados.
- [x] Pruebas de fechas y acciones concurrentes.

### T-12 — Outbox transaccional y publicación reconciliable

- **Descripción**: Persistir trabajo/outbox en MySQL y dispatcher con IDs estables, confirms, rechazo de no enrutables y reconciliación. Mensajes mínimos sin secretos/HTML completo.
- **Estado**: completado
- **Changelog**: Los pendientes y sus eventos se conservan en MySQL y se publican con confirmaciones, reconciliación e identidad estable ante cortes y duplicados.
- **Tiempo humano**: est. 20h · real —
- **Tiempo IA (ejec.)**: est. 0.486667h · real —
- **Supervisión**: est. 0.121667h · real —
- **Previsión IA**: 120000 in / 26000 out tok · coste IA por verificar: 0.12 p_in + 0.026 p_out €; humano base 1000 € equivalente
- **Dependencias**: T-11, T-01
- **Tipo**: backend
- **Archivos**: `app/**`, `config/**`, `routes/**`, `database/**`, `tests/**`, `scripts/**`, `docs/desarrollo/**`
- **Cubre (tests)**: E2E-09
- **Criterios spec**: CA-06/21/22
- **Verificación**: `php artisan test --filter=OutboxIntegrationTest` → fallos antes/después de publish y rutas inválidas recuperados
- RED: `tests/Feature/OutboxIntegrationTest.php::test_materializer_sql_cost_stays_bounded_for_recipient_batches` falló con 207 consultas para 100 destinatarios frente a presupuesto 26 · 2026-10-23.
- RED: `tests/Feature/OutboxIntegrationTest.php::test_global_claim_selection_work_stays_bounded_as_backlog_grows` falló con 101.050 lecturas Handler para diez claims frente a presupuesto de crecimiento 4.700 · 2026-10-23.

**Criterios de aceptación**

- [x] Caída SQL/broker entre pasos no pierde pendientes silenciosamente.
- [x] Publicación repetida converge al mismo trabajo; topología/ACL mantiene otras aplicaciones aisladas.

**Subtareas**

- [x] Implementar estado transaccional y dispatcher.
- [x] Inducir cortes y verificar confirms/no-routable/reconciliación.

### T-13 — Workers, reservas, reintentos y SMTP ambiguo

- **Descripción**: Consumir con reserva MySQL, deduplicación, ack después de resultado durable, backoff acotado y cola de fallos. Estado potencialmente aceptado queda ambiguo sin reenvío automático.
- **Estado**: completado
- **Changelog**: Los workers reservan cada entrega, aplican cuotas y reintentos acotados, y conservan como ambiguo un envío posiblemente aceptado para evitar reenvíos automáticos.
- **Tiempo humano**: est. 28h · real —
- **Tiempo IA (ejec.)**: est. 0.606667h · real —
- **Supervisión**: est. 0.151667h · real —
- **Previsión IA**: 150000 in / 32000 out tok · coste IA por verificar: 0.15 p_in + 0.032 p_out €; humano base 1400 € equivalente
- **Dependencias**: T-12, T-08, T-10
- **Tipo**: backend
- **Archivos**: `app/**`, `config/**`, `database/**`, `tests/**`, `scripts/**`, `docker/**`, `compose.yaml`, `.env.example`, `.gitattributes`, `docs/aplicacion/**`, `docs/knowledge/**`, `docs/README.md`, `docs/RAG-INDEX.md`, `docs/infraestructura/integracion-aplicaciones.md`, `docs/infraestructura/operacion.md` (actualizar los procesos de aplicación descritos conforme a T-13; preservar también los bytes originales de artefactos Markdown bajo `testing/**/raw/`)
- **Cubre (tests)**: E2E-09
- **Criterios spec**: CA-06/08/09/14/22/23
- **Verificación**: `php artisan test --filter=WorkerRecoveryTest` → dos consumidores/crash/ack/SMTP ambiguo pasan

**Criterios de aceptación**

- [x] Dos workers no ejecutan concurrentemente el mismo trabajo; recuperan reservas vencidas con política definida.
- [x] Crash entre aceptación/persistencia/ack no provoca retry ciego; fallo previo sí aplica límites.
- [x] Se respetan límites por cuenta y agregados por relay/credencial; una campaña pausada por incidencia no continúa enviando al ser reentregada.

**Subtareas**

- [x] Implementar reservas/heartbeats y recuperación.
- [x] Relay SMTP controlado y crash tests en procesos Linux.

### T-14 — Actividad y métricas demostrables

- **Descripción**: Mostrar actividad y agregados por cuenta con estados reales, origen/actor y errores depurados. No inventar entrega/aperturas/clics.
- **Estado**: completado
- **Tiempo humano**: est. 8h · real —
- **Tiempo IA (ejec.)**: est. 0.236667h · real —
- **Supervisión**: est. 0.059167h · real —
- **Previsión IA**: 60000 in / 11000 out tok · coste IA por verificar: 0.06 p_in + 0.011 p_out €; humano base 400 € equivalente
- **Dependencias**: T-13
- **Tipo**: frontend
- **Archivos**: `app/**`, `config/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/desarrollo/**`, `docs/aplicacion/**`, `docs/README.md`, `docs/RAG-INDEX.md`, `docs/infraestructura/operacion.md`, `docs/infraestructura/integracion-aplicaciones.md` (actividad, métricas, límites y corrección del estado documental de los componentes T-13 ya revisados)
- **Cubre (tests)**: E2E-09 / M-01
- **Criterios spec**: CA-08/12/13/14
- **Verificación**: `php artisan test --filter=ActivityTest` → estados y agregados aislados pasan

- **Changelog**: Add account-scoped activity, truthful delivery and attempt metrics, UTC filters, protected CSV and private artifact access, with durable attempt provenance and historical-data regression coverage.
- **Resultado**: 20 pruebas/237 aserciones de Activity; migración incremental 1/15; full literal 203/2.466; Pint y build exit0, mismas 196 fuentes finales sin mutación. Revisión15 A+B+D, intento acotado 2/3: 0 Critical/Important/Minor pendientes; A-01 corregido.
- **Evidencia**: [Revisión y gates de T-14](testing/fase-3/review-15.md), informes íntegros A/B/D y recibos seleccionados. La aceptación de navegador/E2E/M-01 pertenece a T-16; este cierre es del componente.
- **Medición**: T-14 CLOCK 2h7m; revisión14 CLOCK18m; fix1 CLOCK1h6m; revisión15 CLOCK14m. Tokens reales, coste y horas IA desconocidos sin transcripciones; CLOCK no es tiempo humano ni horas IA.

**Criterios de aceptación**

- [x] Aceptado SMTP, fallido, suprimido y ambiguo son distinguibles.
- [x] Listados/agregados/descargas solo incluyen la cuenta autorizada.
- [x] Alertas de reputación/bloqueo usan eventos comprobables; tasas conservan ventana, denominador y fuente, y ausencia de datos no aparece como cero incidencias.

**Subtareas**

- [x] Implementar consultas/índices y UI.
- [x] Comprobar estados y permisos por módulo.

### T-15 — Cronicle y acciones finitas autenticadas

- **Descripción**: Integrar disparador HTTP privado autenticado para acciones finitas de programación/dispatcher. Un solo programador por tarea, sin socket Docker ni consumidor permanente dentro de Cronicle.
- **Estado**: completado
- **Tiempo humano**: est. 4h · real —
- **Tiempo IA (ejec.)**: est. 0.120000h · real —
- **Supervisión**: est. 0.030000h · real —
- **Previsión IA**: 30000 in / 6000 out tok · coste IA por verificar: 0.03 p_in + 0.006 p_out €; humano base 200 € equivalente
- **Dependencias**: T-12
- **Tipo**: backend
- **Archivos**: `app/**`, `bootstrap/**`, `config/**`, `routes/**`, `database/migrations/**`, `tests/**`, `scripts/**`, `.env.example`, `infra/compose.emailing-cronicle.yaml`, `docs/aplicacion/**`, `docs/desarrollo/**`, `docs/infraestructura/**`, `docs/knowledge/**` (wiring de la ruta interna sin sesión, configuración y montaje del cliente privado en Cronicle; guía de integración real y memoria del diagnóstico de fuentes mezcladas en QA)
- **Cubre (tests)**: E2E-09
- **Criterios spec**: CA-05/21/25
- **Verificación**: `php artisan test --filter=SchedulerTriggerTest` → auth, idempotencia y acción finita pasan
- **Changelog**: Integra Cronicle con acciones autenticadas y finitas y checkpoint por revisión para evitar recorridos históricos repetidos.

**Criterios de aceptación**

- [x] Disparo exitoso confirma trabajo durable, no emails finalizados.
- [x] Repetir disparo no duplica trabajo; sin credencial válida no ejecuta.

**Subtareas**

- [x] Crear endpoint interno y secreto separado.
- [x] Documentar job Cronicle y probar con infraestructura existente.


### T-16 — Revisión y QA de envío y recuperación

- **Descripción**: Revisar estados y probar recuperación con broker/SMTP reales controlados, concurrencia y fallos.
- **Estado**: completado
- **Changelog**: Las campañas conservan la confirmación explícita de programación y muestran errores de fecha en español; envío y recuperación tienen pruebas reales de broker, SMTP y navegador.
- **Tiempo humano**: est. 32h · real —
- **Tiempo IA (ejec.)**: est. 1.600000h · real —
- **Supervisión**: est. 0.400000h · real —
- **Previsión IA**: 400000 in / 80000 out tok · coste IA por verificar: 0.4 p_in + 0.08 p_out €; humano base 1600 € equivalente
- **Dependencias**: T-10, T-11, T-12, T-13, T-14, T-15
- **Tipo**: test
- **Archivos**: `app/**`, `config/**`, `routes/**`, `database/**`, `resources/**`, `lang/**`, `tests/**`, `scripts/**`, `docker/**`, `compose.yaml`, `.dockerignore`, `.gitignore`, `docs/**`, `README.md`, `CHANGELOG*` (aislamiento de cachés bootstrap del host para construir una imagen Linux coherente; exclusión de trazas privadas en subdirectorios de QA; traducciones de validación y su incorporación a snapshots, inventarios y montajes tras P-01/P-02)
- **Cubre (tests)**: E2E-08 / E2E-09 / M-01
- **Criterios spec**: CA-05/06/08/09/10/11/14/22/23/25
- **Verificación**: `php artisan test --group=delivery` → integración/crash verdes · lectura: E2E-08–09 y revisión sin Critical/Important ni flaky

**Criterios de aceptación**

- [x] Evidencia de cada ventana de fallo y regla de recuperación disponible.
- [x] No hay pérdida silenciosa ni afirmación de exactly-once SMTP.

**Subtareas**

- [x] Revisión adversarial y correcciones del incremento fix1: revisión 19 A+B+C+D sin gaps; la revisión 18 anterior se conserva como histórica.
- [x] QA de concurrencia/fallos y actualización del ledger; ciclo 2, intento 1: 10/10 r0, gate VERDE, M-01 humana compartida pendiente.

**Seguimiento de T-16 — 2026-10-24**

La primera ejecución completa de `php artisan test --group=delivery` terminó
con 137 pruebas ejecutadas: 135 pasadas, 2 fallidas y 1837 aserciones. Los fallos
corresponden a la preparación TLS de `MailTransportTest` y a la barrera de
competencia de `WorkerRecoveryTest`. La tanda se conserva como diagnóstico;
no satisface la verificación de esta tarea. Se comprobaron los hashes de sus
232 fuentes archivadas y de sus salidas, sin cambios de fuentes durante la tanda.

También se contrastaron los controles archivados del inventario: 22 pruebas
nativas distintas y nueve casos Linux sin privilegios, con sus controles de
fallo esperado. Estos resultados no sustituyen la repetición del grupo sobre
las correcciones finales, la revisión independiente ni E2E-08/E2E-09/M-01.

La segunda ejecución completa terminó con 137 pruebas: 134 pasadas, tres
fallidas y 1980 aserciones. Sus 232 fuentes y salidas también se verificaron;
la tanda conserva su resultado fallido. Las trazas sitúan las incidencias en
la preparación de `WorkerRecoveryTest`: arranque del segundo worker dentro
de una reserva ya activa, inventario nativo dentro del plazo de una barrera
y presupuesto total insuficiente del proceso relay. Las correcciones mantienen
los límites del producto y requieren nuevas pruebas sobre sus propios archivos.
T-16 sigue en progreso; revisión independiente y QA de interfaz pendientes.

La tercera ejecución completa, sobre las correcciones finales, terminó con
137 pruebas pasadas y 1972 aserciones en 2038,66 segundos. Se verificaron sus
232 fuentes archivadas y las salidas; cinco documentos se contrastaron por
separado, sin contarlos como entradas ejecutadas. La comprobación independiente
de inventarios contrastó 45 documentos capturados con mapas esperados derivados
de las fuentes y dependencias archivadas. Las auditorías finales verificaron
la identidad de la base de pruebas y la ausencia de recursos de runtime propios
pendientes; las tablas ausentes no se presentan como recuentos medidos de cero.
Las dos tandas fallidas anteriores conservan su resultado e identidad.

El implementador ha liberado las fuentes, `emailing_testing` y el runtime
controlado para la revisión independiente. La medición de implementación se
cerró sin datos de tokens, coste ni horas IA verificables. Este resultado de
backend no cierra T-16: siguen pendientes la revisión adversarial, E2E-08,
E2E-09 y la revisión humana M-01.

## Fase 4 — API y validación global

**Estado**: en-progreso · **Estimado**: 60h · **Real**: — · **Coste est.**: 3000 € equivalentes + tokens · **Tokens est.**: 720000

### T-17 — Contrato OpenAPI y scopes de envío

- **Descripción**: Escribir contrato OpenAPI antes de endpoints bearer: creación/revocación de keys, envío, validación, idempotencia y errores. Versionar sin prometer compatibilidad íntegra SendGrid.
- **Estado**: completado
- **Changelog**: Define una API de cuenta para envíos idempotentes y llaves revocables, con permisos, límites y ejemplos sintéticos antes de implementar sus endpoints.
- **Tiempo humano**: est. 8h · real —
- **Tiempo IA (ejec.)**: est. 0.200000h · real —
- **Supervisión**: est. 0.050000h · real —
- **Previsión IA**: 50000 in / 10000 out tok · coste IA por verificar: 0.05 p_in + 0.01 p_out €; humano base 400 € equivalente
- **Dependencias**: T-16
- **Tipo**: backend
- **Archivos**: `docs/api/**`, `tests/**`, `docs/roadmap/**`, `docs/knowledge/adr/ADR-007-admision-api-y-destinatarios.md`, `docs/knowledge/README.md`
- **Cubre (tests)**: E2E-10
- **Criterios spec**: CA-19/20
- **Verificación**: `python "$env:CUSTOM_AGENTS_PLUGIN_ROOT/skills/api-contract/scripts/openapi-lint.py" docs/api/openapi.yaml` → exit 0

**Criterios de aceptación**

- [x] Operaciones documentan éxito/error, scopes, límites y cuenta derivada de key.
- [x] No se permite elegir cuenta ajena ni crear key más privilegiada que autorización del actor.

**Subtareas**

- [x] Definir contrato y ejemplos sin secretos.
- [x] Validar estructura y aprobación técnica antes de endpoints.

**Trabajo T-17 — 2026-10-24**: iniciada la preparación del contrato canónico
tras el cierre técnico de T-16 en `eece2a5`. Se mantienen las cinco operaciones,
CA-19/20 y el pipeline común; no se crean endpoints ni se ejecutan servicios.
TDD n/a: contrato y prosa, sin comportamiento ejecutable nuevo. La configuración
opcional de desarrollo no existe; se aplican los valores por defecto. La tarea
permanece en progreso hasta revisión técnica independiente y QA aplicable;
M-01 humana continúa pendiente y los contadores globales siguen en 16/20.

El contrato propuesto `docs/api/openapi.yaml` conserva cinco operaciones y los
scopes cerrados existentes. Documenta cuenta derivada del bearer sin fallback
de cookie, techo de concesión, vigencia independiente del creador, revocación
serializada e idempotencia durable por cuenta. La política de destinatarios
directos conserva controles vigentes de contacto/supresión y distingue
autorización de la petición de consentimiento acreditado. Sus consecuencias
transversales quedan en ADR-007, **propuesta** todavía, con su única fila nueva
en el índice de conocimiento. Se amplía `Archivos` a esas dos piezas.

Verificación estructural ejecutada sobre SHA-256
`4ccb9fe4cb9a4ba16d1c3582d456c44c3fa21f74a8d255bed9944f317cd0ce6e`:

- Linter oficial `openapi-lint.py docs/api/openapi.yaml --json`: exit 0,
  `{"modo":"validar","exit":0,"errores":[],"avisos":[]}`.
- No existe contrato en la base `eece2a5`; el diff adicional contra el candidato
  privado v3 da exit 0, `rompedores: 0`. El comparador no detecta la concreción
  semántica de límites, autenticación o política; se requiere revisión humana
  técnica del diff completo, sin afirmar compatibilidad publicada previa.
- Esquema oficial OpenAPI 3.0, edición 2024-10-18, validado offline: exit 0,
  cero errores; formatos de documento email/regex/uri/uri-reference activos y
  dos controles negativos URI. Se conserva el diagnóstico inicial de acceso
  al checker privado; la repetición autorizada no instala dependencias.
- Ejemplos y casos del subconjunto de schemas: **107/107**, exit 0. El primer
  pase fue **95/96**: la regex del nombre admitía NUL. Se preservó el rojo y se
  separaron ausencia de controles y condición no vacía; el final real de las
  regex usa `(?![\s\S])`, portable a ECMAScript, sin `\z`/`\Z`.
  El checker opcional de `date-time` no está disponible en ese subconjunto;
  los patrones/tipos no acreditan fecha válida ni comparación SQL UTC.

Estas comprobaciones no ejecutan HTTP, SQL, colas, SMTP ni permisos de negocio.
Los 15 casos de negocio y 65 de frontera bearer preparados siguen **sin
ejecutar**, y CA-19/20/E2E-10 permanecen pendientes de T-18. El medidor abrió
16:23:09Z y cerró 16:34:56Z sin transcripción de uso disponible: tokens, coste
y horas IA reales **desconocidos**; el intervalo de reloj no se convierte en
horas IA. El cierre de T-17 espera revisión independiente y QA del contrato.

**Corrección T-17 fix1 — B-01 de revisión 20 — 2026-10-24**: se añaden únicamente
los mappings explícitos del discriminator de contenido: `inline` a
`#/components/schemas/InlineContent` y `template` a
`#/components/schemas/TemplateContent`, conservando los payloads. Conforme a
[OpenAPI 3.0.3 §4.7.25.1](https://spec.openapis.org/oas/v3.0.3.html#discriminator-object),
el nombre de componente no se deduce de su enum. RED contra el contrato
congelado: ambos ejemplos tienen una única rama estructural válida, pero el
discriminator no resuelve ninguna (**0/2**, exit 1). GREEN tras el mapping:
**2/2**, exit 0; controles adicionales verifican nombres implícitos, valor
desconocido y rechazo de ambos payloads al cruzar los mappings. Se conserva un
diagnóstico intermedio de indentación y su corrección; no se reetiqueta como éxito.

Contrato corregido SHA-256
`e56b967b1a7796113460ef370c0a4a0a63d50539f9b113e8bc45d5d314836c5f`:
lint oficial exit 0, diff soportado frente al contrato congelado de revisión 20
exit 0 con cero rompedores, esquema oficial offline exit 0 y ejemplos **107/107**.
El diff estructural no prueba por sí solo la selección semántica; la evidencia
causal es independiente. Directorio privado nuevo `storage/phase4-t17-fix1/`;
implementación y cápsula anteriores permanecen inmutables. El medidor fix1
cerró sin tokens, coste ni horas IA reales disponibles. No se ejecutaron
backend, HTTP, SQL, browser, broker ni SMTP. B-01 espera verificación de la
revisión 21, pasada 2/3; T-17 sigue en progreso, ADR-007 propuesta y
CA-19/20/E2E-10 pendientes de implementación.

### T-18 — API keys, envío idempotente y gestión

- **Descripción**: Implementar bearer Sanctum/llaves hash de cuenta, scopes efectivos, revocación y límites. Reutilizar servicios de trabajo/outbox, no otra ruta de envío. Clave idempotente única por cuenta; payload distinto con misma clave produce conflicto.
- **Estado**: completado
- **Tiempo humano**: est. 20h · real —
- **Tiempo IA (ejec.)**: est. 0.466667h · real —
- **Supervisión**: est. 0.116667h · real —
- **Previsión IA**: 120000 in / 20000 out tok · coste IA por verificar: 0.12 p_in + 0.02 p_out €; humano base 1000 € equivalente
- **Dependencias**: T-17
- **Tipo**: backend
- **Archivos**: `app/**`, `bootstrap/**`, `config/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/api/**`, `docs/desarrollo/**`, `docs/knowledge/**`, `README.md`, `docs/README.md`
- **Nota de fuentes T-18**: `app/Support/ApiJsonNumber.php` preserva la integralidad exacta del JSON antes de normalizar identificadores; `tests/Fixtures/sending-api-number-cases.json` contiene el oráculo independiente de 142 lexemas. Ambos entran en los inventarios de fuentes y de runtime. `tests/Concerns/ReplaysMigrationHistory.php` restaura cronológicamente el esquema de los fixtures históricos. GOT-008/009/010/011 nacieron como propuestas de diagnóstico y fueron aceptadas en la revisión 23.
- **Nota de alcance**: los README describen el estado API y enlazan su guía; se actualizan con la implementación. `docs/knowledge/**` conserva los diagnósticos y su aceptación trazada en la revisión 23.
- **Archivos nuevos T-18-fix1**: `database/migrations/2026_10_24_000014_index_historical_contact_email.php`, `tests/Concerns/ExercisesSendingApiReviewFixes.php`; amplían el índice histórico y las regresiones causales dentro de `database/**` y `tests/**`. No se alteran migraciones históricas aceptadas.
- **Changelog**: Gestiona llaves de cuenta con permisos y caducidad y acepta envíos API idempotentes que conservan bajas, cuotas y estados verificables.
- **Cubre (tests)**: E2E-10
- **Criterios spec**: CA-01/12/13/19/20
- **Verificación**: `php artisan test --filter=SendingApiTest` → contrato, scopes, revocación y concurrencia idempotente pasan

**Criterios de aceptación**

- [x] Repetición concurrente crea un trabajo; key inválida/revocada/sin scope rechazada.
- [x] Read-only/Restricted no elevan permisos creando keys; cuenta se deriva de credencial válida.

**Subtareas**

- [x] Crear endpoints/middleware/políticas y UI de keys.
- [x] Verificar contrato y actualización de docs con ejemplos.

**Inicio de implementación — 2026-10-24**: árbol limpio y HEAD `dab4b84` verificados;
contrato SHA-256 `e56b967b1a7796113460ef370c0a4a0a63d50539f9b113e8bc45d5d314836c5f`
conservado como autoridad. Se aplica el rol implementer genérico heredado; no hay
`.claude/dev.json` (defaults false). Medidor abierto; tokens, coste y horas IA reales
desconocidos mientras no exista telemetría. Implementación, revisión y QA pendientes;
no se cierran CA-19/20 ni E2E-10. Runtime/testingDB exclusivo del implementador durante
esta tarea; únicamente log/Mailpit, sin SMTP externo.

**Evidencia intermedia de implementación — 2026-10-24**: se conservan los
recibos privados con ZIP/hash de fuentes y salidas originales en
`storage/qa-delivery/t18-*`. No son QA final ni evidencias retroactivas de T-17.

- `t18-http1-20261024T171153532435Z`: rojo **6/0 aserciones**, MySQL 3823,
  CHECK sobre FK de actor con `SET NULL`. `t18-http2-20261024T171446671828Z`:
  rojo **6/0**, MySQL 1419 al intentar un trigger sin los privilegios requeridos
  con binary logging. Se retiró el trigger sin cambiar privilegios/globales.
  Se conservan las FKs/CHECK de origen, más constructor y guard de actor API
  nulo en aplicación; no se atribuye esa última protección a SQL. GOT-008 nace
  **propuesta**, pendiente de revisión.
- `t18-http3-20261024T171830606140Z`: **5 pasadas/1 fallo, 150 aserciones**;
  Symfony añadía `private` al valor cerrado `Cache-Control: no-store`.
  `t18-http4-20261024T172421894057Z`: **9/1, 306 aserciones**; el fixture no
  ejecutaba realmente el soft-delete porque `deleted_at` no es fillable.
  Se corrigió el fixture mediante `delete()`, sin alterar la política.
- `t18-http5-20261024T173004967718Z`: **13/2, 509 aserciones**. Dos errores
  del fixture: comparación de objetos JSON por orden de miembros y confundir
  el retorno «evento procesado» del dispatcher con confirmación del broker.
  Cuatro procesos reales ya produjeron un recibo lógico, un `false` y tres
  replays; no se oculta la aserción fallida. Las comparaciones se hicieron
  canónicas y la publicación se comprueba por estado/confirm SQL/broker.
- `t18-broker6-20261024T173928935438Z`: **1 pasada, 57 aserciones, exit 0**;
  broker no disponible con `202`/outbox pendiente, publicación posterior,
  consumer y redelivery reales, cuotas, supresión, retry seguro y relay
  controlado con pérdida de respuesta final. La pérdida inyectada antes del
  resultado durable deja `ambiguous`, sin reenvío. Este último caso usa el
  observer de prueba, no se presenta como un crash SQL o de proceso.

Después de la quinta focal se unificó admisión HTTP y acción bajo el mismo
mutex/transacción de cuenta; un savepoint deshace el dominio fallido y conserva
el contador autenticado. `t18-full7-20261024T174407967789Z` pasó la focal literal:
**18 pruebas/613 aserciones, exit 0**, sin cambios de fuentes durante ejecución.
`t18-full8-20261024T175644179756Z` conservó **19 pasadas/1 fallo, 607 aserciones**:
el caso de cuota dependía de un envío en el minuto anterior y cruzó una ventana
UTC. Se corrigió la precondición con contador diario SQL explícito propio; no
se cambió la lógica válida de cuotas por ese fallo del fixture.

Un diagnóstico independiente reprodujo la aceptación errónea de una fracción
redondeada por PHP (`9007199254740990.1`). Se preservó el rojo y se corrigió el
lexer con integralidad decimal exacta y exponente acotado antes de cualquier
cast. `t18-number9-20261024T181800690531Z`: **2 pruebas/234 aserciones, exit 0**,
con 142 lexemas de oráculo Python Decimal independiente (fixture SHA-256
`9cc989cbe2a388b7e7309910ebe65c3b164f22fa96f3576b247a10c0eb741a0d`).

`t18-deadlock9` preserva un rojo de fixture (columna inexistente). Los rojos
`t18-deadlock10` y `t18-deadlock11-isolate` sí provocaron MySQL **40001/1213**
entre procesos y descubrieron nivel Laravel 2 después del error seguro HTTP.
El detector instalado reconoce la excepción original y no su traducción;
el rollback del savepoint perdido impedía limpiar el nivel interno. La
operación restaura explícitamente su límite y, si el savepoint ya no existe,
revierte toda la unidad. `t18-deadlock12`: **1/15, exit 0**;
`t18-deadlock13-20261024T182554145744Z`: **2 pruebas/35 aserciones, exit 0**,
con entrada 0 y transacción exterior propia de nivel 1. La escritura exterior
se revierte, su callback no continúa, no queda trabajo parcial y se permite
reintentar la misma idempotencia. Un aborto completo de infraestructura no
se presenta como admisión durable ni conserva ficticiamente el contador.

`t18-full15` conserva **22 pasadas/1 fallo, 711 aserciones**: una comparación
del fixture todavía dependía del orden de miembros de un objeto JSON. Se
corrigió la comparación canónica, conservando el orden de arrays.
`t18-exact17` pasa **1/10** y `t18-full18-20261024T184210464329Z` pasa la focal
literal completa **23 pruebas/717 aserciones, exit 0**, con 259 fuentes sin
cambios durante ejecución.

`t18-build14` conserva el fallo de infraestructura **spawn EPERM de esbuild**.
`t18-build16` ejecutó `npm run build` con **exit 0**, 58 módulos y Node 22.22.0.
El productor de evidencia terminó 1 por los tres artefactos compilados que
cambiaron de forma esperada; se distingue del resultado del comando.
`t18-runtime20-20261024T185508681490Z` terminó **exit 0**, con las 26 fuentes
afectadas comprobadas en seis inventarios de build/runtime nativo y Docker.
El wrapper privado omitió `-v` del teardown; verificó proyecto UUID y etiquetas
antes de retirar individualmente sus dos volúmenes. Se conservan el original,
diff y hashes del código compilado LF y del artefacto CRLF. Las imágenes propias
se retienen para la regresión y revisión; no se eliminaron volúmenes operativos.

La primera literal `t18-delivery21` conservó **161 pasadas/3 errores, 2907
aserciones**. Los tres errores MySQL **HY000/3959** tenían la misma causa:
fixtures históricos revertían la migración 000011 con el CHECK posterior de
000013 todavía aplicado. Se mantuvieron restricciones y migraciones históricas.
El helper de fixtures revierte todas las migraciones aplicadas desde el objetivo
en orden descendente y restaura en ascendente, con trazas UUID y hashes. La focal
`t18-migration22` pasa **3 pruebas/55 aserciones**, cuatro ventanas restauradas;
solo cambian el helper y dos fixtures. GOT-011 queda propuesta.

El descubrimiento `t18-discovery23` conserva exactamente los mismos **164**
métodos que discovery19. La segunda literal
`t18-delivery24-20261024T200618829417Z` termina **164 pruebas pasadas/3071
aserciones, exit 0**, en 3227.83 segundos. Los 164 nombres coinciden con el
descubrimiento; las once clases pasan y las cuatro ventanas históricas se
restauran. Las **260 fuentes** permanecen sin cambios durante ejecución,
manifiesto SHA-256
`669e61b06dea973c032c6bf651e2c6f608e0108043796780ff5c5c0e1d5e13cb`.
Las 26 fuentes desplegadas siguen byte-idénticas a runtime20 y se revalidó
su imagen; no se repitieron build/runtime por el cambio limitado a fixtures.
El cierre `t18-settlement25-20261024T210053962986Z` termina **exit 0** sin cambios
de fuentes. La inspección de lectura verifica la identidad real de
`emailing_testing`: solo queda `migrations`, con cero migraciones aplicadas y
cero tablas de aplicación. Verifica ausentes **45 contenedores**, **44 colas**,
**22 exchanges** y **un ID Mailpit** propios; conserva 14 settlements API,
18 procesos hijos terminales y los 21 teardowns del worker sin errores.
Runtime20 ya retiró individualmente sus dos volúmenes propios. Se retienen solo
las dos imágenes UUID para revisión. No se ejecutó limpieza global ni se tocó
`lab_smoke`; estos resultados se limitan a recursos atribuidos.
Revisión independiente y QA de E2E-10 siguen pendientes. T-18 y sus criterios
no se cierran por estas comprobaciones; no se actualizan las cifras de T-17.
El marcador de ejecución del medidor se cerró antes de revisión. No encontró
telemetría de tokens: tokens, coste y horas IA reales permanecen desconocidos;
la duración de reloj no se convierte en horas IA ni se inventa una estimación.

### T-19 — Pruebas cruzadas, carga y restauración

- **Descripción**: Validar aislamiento de dos aplicaciones de prueba y reparto de cinco y diez workers, latencia/pendientes/recursos y fallos. Restaurar respaldo en entorno temporal aislado sin borrar volúmenes existentes.
- **Estado**: en-progreso
- **Tiempo humano**: est. 16h · real —
- **Tiempo IA (ejec.)**: est. 0.866667h · real —
- **Supervisión**: est. 0.216667h · real —
- **Previsión IA**: 220000 in / 40000 out tok · coste IA por verificar: 0.22 p_in + 0.04 p_out €; humano base 800 € equivalente
- **Dependencias**: T-18
- **Dependencia externa de cierre**: `2026-10-24-mcp-google/T-14`; la preparación de carga/restauración puede avanzar antes, pero su aceptación global incorporará la ampliación MCP y Google.
- **Tipo**: test
- **Archivos**: `tests/**`, `scripts/**`, `docs/**`, `docker/**`, `compose.yaml`
- **Cubre (tests)**: E2E-11
- **Criterios spec**: CA-21/22/23/24/25
- **Verificación**: `php artisan test --group=broker-isolation` → ACL/dedup pasan · lectura: informe de carga/restauración con parámetros y resultados reales

**Criterios de aceptación**

- [ ] Distribución 2/3 y 4/6 workers registrada; umbrales fijados antes de carga y resultados sin SLA inventado.
- [ ] Reinicio/restauración conserva estado esperado; definiciones RabbitMQ no se confunden con backup de mensajes.
- [ ] La validación global incluye la iniciativa [MCP y Google](../2026-10-24-mcp-google/tasks.md), sin sustituir las pruebas originales de dos aplicaciones y cinco/diez workers.

**Subtareas**

- [ ] Crear segunda aplicación mínima de protocolo, sin su negocio.
- [ ] Medir límites/recuperación y probar backup/restauración aislada.
- [ ] Integrar los escenarios y fixtures aceptados de MCP/Google en las pruebas globales de carga y restauración, comprobando aislamiento de cuenta, persistencia de identidades/grants y conservación de revocaciones sin cambiar las pruebas originales del broker compartido.

**Preparación local de T-19 — 2026-11-03**

El coordinador preparó un protocolo sintético B y ejecutó dos comprobaciones
AMQP acotadas, sin Laravel/MySQL ni reinicios de servicios compartidos:
tres publicaciones confirmadas produjeron dos efectos únicos, con una repetición
evitada; dos identidades temporales publicaron/consumieron en sus propios vhosts
y recibieron seis denegaciones cruzadas en la admisión al vhost (530). La
credencial B de la primera prueba recibió HTTP 401 en administración. Los tres
usuarios/vhosts temporales de las dos pruebas se eliminaron y su ausencia quedó
verificada. Se preservaron 14 originales sanitizados por selección literal,
incluidos recibos de supervisión y limpieza, con hashes en el
[informe preliminar](testing/fase-4/t19/preflight-20261103/report.md).

La preparación reproducible avanza en una rama y checkout aislados, con fuentes
en `scripts/broker_lab` y [guía de ejecución](../../desarrollo/carga-broker.md).
Incluye aplicación B, ronda, matriz secuencial 52/4/48, muestreo y supervisor de
regresión completa. El prerrequisito exige selección completa y coincidencia
con las fuentes actuales; cuatro negativos verifican selección incompleta,
manifiesto ausente y fuentes posteriores distintas. La revisión de preparación
detectó limpieza nativa insuficiente frente a contenedores Docker: se reprodujo
en un test sintético fallido antes de añadir atribución UUID y reconciliación
del daemon. También se rechazan junctions antecesoras antes de leer fuentes.
Pasaron 84 pruebas Python, 11 comprobaciones de descriptor (diez rechazos y un
dry-run correcto) y tres casos de paridad PHP/Python, sin SQL ni broker.
Las dos clases puras PHP pasaron diez pruebas/33 aserciones sin Laravel/SQL.
El grupo `broker-isolation` selecciona ahora protocolo, ACL real y deduplicación
real existentes; su ejecución integrada sigue pendiente. No se acredita carga
2/3 o 4/6, restauración, integración Google/MCP ni QA independiente; todos los
criterios y subtareas de T-19 conservan sus casillas pendientes. La regresión
completa de T-12 seguía ejecutándose y sus fuentes no se modificaron.

### T-20 — Revisión final, QA y documentación de aplicación

- **Descripción**: Cerrar revisión/QA completa, portabilidad y documentación de operación de aplicación; actualizar changelog y ledger con evidencia. Sin despliegue externo ni sincronización Jira/Confluence por defecto.
- **Estado**: borrador
- **Tiempo humano**: est. 16h · real —
- **Tiempo IA (ejec.)**: est. 0.866667h · real —
- **Supervisión**: est. 0.216667h · real —
- **Previsión IA**: 220000 in / 40000 out tok · coste IA por verificar: 0.22 p_in + 0.04 p_out €; humano base 800 € equivalente
- **Dependencias**: T-19
- **Tipo**: test
- **Archivos**: `app/**`, `bootstrap/**`, `config/**`, `routes/**`, `database/**`, `resources/**`, `public/**`, `tests/**`, `scripts/**`, `docker/**`, `compose.yaml`, `composer.*`, `package*.json`, `phpunit.xml`, `vite.config.*`, `.env.example`, `.gitignore`, `.htaccess`, `storage/**`, `README.md`, `infra/README.md`, `docs/**`, `CHANGELOG*`
- **Cubre (tests)**: E2E-01 / E2E-02 / E2E-03 / E2E-04 / E2E-05 / E2E-06 / E2E-07 / E2E-08 / E2E-09 / E2E-10 / E2E-11 / M-01 / M-02
- **Criterios spec**: CA-01–25
- **Verificación**: `php artisan test` → suite completa verde en base exclusiva · `npm run build` → éxito · `python "$env:CUSTOM_AGENTS_PLUGIN_ROOT/agent-kits/shared/ledger-lint.py" docs\roadmap\2026-10-21-plataforma-emailing/tasks.md` → exit 0 · lectura: revisión final y qa-gate sin fallos/flaky

**Criterios de aceptación**

- [ ] CA-01 a CA-25 tienen evidencia y limitaciones explicitadas.
- [ ] No Critical/Important abiertos; manuales no ejecutados no se marcan aprobados.
- [ ] Documentación consistente con versión final y secretos ausentes de Git.
- [ ] MCP y Google tienen contratos, permisos, revocación, perfiles de cliente y evidencias enlazadas; la iniciativa adicional está cerrada con sus propios criterios antes de declarar completo el objetivo global.
- [ ] Control de datos públicos pasa en la copia de trabajo y en el índice Git; fixtures/capturas son ficticios y la situación del historial publicado se verifica y documenta sin atribuir una limpieza que no se haya realizado.

**Subtareas**

- [ ] Revisión adversarial y regresión necesaria.
- [ ] Cerrar QA, documentación, changelog y transición final solo si todo pasa.

## Registro de revisión y QA

Cada puerta añade lentes ejecutadas, hallazgos, correcciones y comandos/resultados reales. Aún no ejecutadas. No cerrar fases por haber escrito el plan.

## Cierre de jornada solicitado por el usuario — 2026-10-21

El usuario solicita «haz commits y push y paramos por hoy». Se detiene el desarrollo y se guarda un checkpoint en la rama `feature/plataforma-emailing`; no es una entrega funcional de C-01. T-01/T-02 siguen en progreso, T-03 no ha empezado y T-04 conserva la revisión adversarial y QA pendientes. No se marca ninguna de las 20 tareas completa.

- Coordinador: comprobación de sintaxis de los PHP de aplicación/configuración/rutas/migraciones/tests y `artisan` sin errores. `RuntimeIntegrationTest` final: **3 passed, 6 assertions**; MySQL exclusivo de pruebas, publicación/release/reconexión/consumo/ack reales y denegación al vhost ajeno. Se corrigió únicamente la clase de excepción esperada por esa prueba de ACL tras observar `AMQPConnectionClosedException` con denegación real.
- Docker: build finalizado por implementer; coordinador ejecutó `docker compose up -d --wait --wait-timeout 120` con exit 0 y tres contenedores de aplicación saludables. `http://127.0.0.1:8081/up` y `/login` respondieron 200. Esa imagen precede los últimos cambios parciales de T-02; requiere reconstrucción al retomar.
- Vite compiló antes de los últimos cambios de vistas. No se atribuye esa compilación a una validación del panel final. Faltan pruebas de autenticación/selector/reset y la implementación de invitaciones y permisos.
- Pendiente también completar el arranque reproducible desde un clon limpio: directorios privados de Laravel y configuración local de sockets, dependencias y ambas modalidades web. No trasladar `storage/`, `.env` o credenciales por Git.
- Documentación de infraestructura: diez páginas, más README rápido; comprobación del coordinador de 73 enlaces locales sin roturas. Respaldos/restauración siguen como recetas no ensayadas; entregabilidad externa sigue pendiente.
- Todos los medidores están cerrados, sin cifras reales de consumo disponibles. El servidor PHP temporal del implementer está detenido. Los servicios Docker permanecen arrancados; no se han borrado volúmenes.

Al reanudar: completar T-01 y T-02 con sus verificaciones, después T-03 y revisión/QA T-04. Concretar proveedor y feedback de entregabilidad antes de la fase de correo externo. No interpretar este checkpoint como autorización de despliegue o como QA verde de la aplicación.

## Reanudación del objetivo completo — 2026-10-22

El usuario reanuda con el objetivo de implementar todo el roadmap y exige calidad
y ausencia de datos personales en el repositorio público. El cierre de jornada
anterior queda como registro histórico, no como una pausa vigente.

- Coordinador: perfiles personales sustituidos por configuración portable en
  los E2E de infraestructura, su informe JSON y comandos del ledger. La importación
  de la configuración Playwright y el gate del informe histórico pasan; sus
  resultados originales no se han cambiado.
- Nuevo `scripts/check-public-data.py`: inspecciona copia de trabajo o índice Git,
  rechaza rutas privadas, posibles identificadores personales, claves privadas,
  tokens de acceso y valores secretos locales sin imprimirlos. Cuatro regresiones
  Python pasan, incluida una versión del índice distinta del archivo saneado en
  disco. Comprobación actual de copia de trabajo: cero hallazgos.
- Historial: preparada copia local filtrada de siete commits, cinco cambian de
  identificador, con respaldo privado ignorado. El reemplazo de ramas públicas
  sigue pendiente de confirmación; no se declara el historial público saneado.
- Infraestructura: Docker Desktop y los cuatro servicios compartidos recuperados
  saludables. Lectura del coordinador de `emailing.lab_smoke`: cinco filas;
  no se modificaron sus datos.

## Cierre de jornada solicitado por el usuario — 2026-10-22

El usuario solicita guardar commits, hacer push y parar por hoy. Se conserva el
checkpoint en `feature/plataforma-emailing` sin presentar la aplicación como
terminada ni la fase 1 como revisada. El objetivo completo queda pausado.

- T-01, T-02 y T-03 implementadas: **3/20 tareas (15 %)**. Siguiente paso al
  reanudar: revisión adversarial y QA de fase 1, T-04; después, fase 2.
- Handoff del implementer: **19 pruebas Feature, 136 aserciones**, Composer,
  Pint, compilación de assets y build Docker correctos. Pruebas con MySQL
  exclusivo `emailing_testing`, RabbitMQ y SMTP local Mailpit. Portabilidad
  nativa y Docker comprobada; revisión independiente y E2E completos pendientes.
- No se han aplicado migraciones sobre la base operativa. La revisión automática
  rechazó esa acción combinada por el riesgo para datos existentes; se completó
  la verificación alternativa en la base exclusiva de pruebas.
- Control de datos públicos: copia de trabajo sin hallazgos; el índice se
  comprueba antes de cada commit. Entornos privados, respaldos y herramientas
  temporales permanecen ignorados.
- El historial publicado conserva las rutas personales históricas ya detectadas.
  Su reescritura sigue pendiente de autorización explícita; este push es normal,
  sin reemplazar el historial de ramas públicas.
- ADR-003 es una propuesta para la fase de entrega, aún no implementada. Todos
  los medidores del implementer están cerrados; no hay consumo real disponible.
- No se eliminan contenedores ni volúmenes al cerrar la jornada.

## Reanudación tras el checkpoint publicado — 2026-10-22

El usuario ha reactivado el objetivo completo. El cierre anterior es histórico;
se retoma T-04 desde `a19afff`, coincidente con la rama remota y sin cambios
locales al iniciar. No se reduce el alcance a la primera fase.

- Puerta de alcance previa a revisión: 120 archivos, cero fuera de alcance,
  cero avisos y sin exclusiones del usuario. Lentes A/B/C en contexto fresco;
  D se ejecuta también, ajustando el despacho al límite de agentes disponibles.
- La comprobación actual de datos públicos no encuentra incidencias en los
  141 archivos inspeccionados. La situación del historial antiguo no cambia.
- Infraestructura: cuatro servicios running/healthy. Docker QA responde HTTP
  200 en `/up` del puerto 8083; PHP nativo QA sigue en ejecución. Antes de QA
  se volverá a comprobar la exclusividad de `emailing_testing`.

## Handoff de correcciones para revisión 2 — 2026-10-22

Base incremental y HEAD sin commits nuevos: `a19afffa17a86d7d11549554634c6fcbba00858d`. Se mantienen los veredictos históricos de intento 1; las filas anteriores registran corrección del implementer, no aprobación del reviewer. T-01/T-02/T-03 implementadas, 3/20 (15%); T-04 continúa en-progreso y A1 queda para documenter después de QA. ADR-004 propuesta y ADR-003 propuesta; no promociones anticipadas. Sin QA browser, fase 2, commits ni push.

Puertas al entregar: Composer válido y Pint exit 0; suite 30/230; scope incremental `--base a19afff`: 23 archivos, cero fuera de alcance, cero avisos, info o exclusiones del usuario; datos públicos: 150 archivos, cero hallazgos. Comandos reproducibles: `php artisan test --testsuite=Feature`, `php tests/support/password-reset-sessions.php http://127.0.0.1:8082` y `:8083`, `python tests/support/http-profile-smoke.py` con cada URL QA. El fixture de sesiones limpia exclusivamente sus filas UUID y exige base/usuario de pruebas antes de cualquier operación. Las notas anteriores sobre migraciones operativas rechazadas y preservación de lab_smoke siguen vigentes.

`ledger-lint` termina exit 0: **0 incoherencias, 17 avisos** inspeccionados, todos por Changelog aún ausente en T-04 a T-20 no completadas; no se inventan changelogs de trabajo futuro. Ambos medidores de correcciones están cerrados y sin telemetría real. `git diff --check` exit 0. Estos resultados no sustituyen la revisión independiente 2 ni el QA browser pendiente.

Adenda D del mismo intento: completada la lectura restante por bloques, incluido
welcome sin ruta activa; locks inspeccionados estructuralmente en sus 134
paquetes Composer y 159 entradas npm. Sin hallazgos D ni nuevas ejecuciones.
No se atribuye lectura literal de hashes, geometría SVG o cada línea eliminada
de la evaluación histórica; sí se revisaron los caminos ejecutables introducidos.
Implementer verificó C1/C2/B-01/B-02 antes de corregir: confirma los cuatro,
sin rebate. A1 permanece para documentación tras QA.

## Revisión de dos lentes — intento 2: Fase 1 (T-01, T-02, T-03) — correcciones aprobadas

Revisión incremental sobre `a19afffa17a86d7d11549554634c6fcbba00858d` más
copia de trabajo y nueve archivos nuevos públicos: 23 archivos, puerta de alcance
exit 0, cero fuera, avisos o exclusiones del usuario. Los revisores A/B/C nuevos
recibieron la tabla completa del intento anterior y conservaron lo aprobado o
rebatido salvo evidencia nueva. Lente C activada por Docker, autenticación y
sesiones; D no se activa en este incremento, sin motivos. Su revisión inicial
se conserva. Lentes por subagentes genéricos con rol reviewer, sin override.

| Criterio de aceptación | Veredicto | Evidencia del intento 2 |
|---|---|---|
| T-01.1 base exclusiva y preservación | ✓ conservado | Sin migraciones nuevas ni cambios del guard; fixtures con guard y limpieza solo de UUID propio |
| T-01.2 perfiles y runtime aislado | ✓ corregido | compose.yaml:25/33/42, zzz-emailing-fpm.conf:2 y nginx.conf:11; ambos perfiles HTTP 200; socket privado y TCP cerrado verificados |
| T-01.3 locks y protocolo real | ✓ conservado y reforzado | RuntimeIntegrationTest:43 prueba productor y worker con cola alternativa; B ejecutó prueba individual: 1 passed, 5 assertions |
| T-02.1 alta atómica y configurable | ✓ conservado y reforzado | ValidateAuthEmail:14, CreateNewUser:20/27; cuatro entradas no escalares con respuesta 422 en registro y login |
| T-02.2 autenticación, CSRF y contexto | ✓ reforzado por C3 | bootstrap/app:17, PasswordResetSessionTest:23; cookies anteriores al reset rechazadas; perfil nativo y Docker con CSRF 419 |
| T-02.3 recuperación segura y única | ✓ corregido | TrustedUrl:7, AppServiceProvider:24; notificación real desde petición Host/esquema/proxy hostiles conserva APP_URL |
| T-03.1 invitaciones y concurrencia | ✓ conservado | TeamInvitation:24 y prueba HTTP Livewire real de notificación; token/concurrencia mantienen veredicto inicial |
| T-03.2 roles y owner | ✓ conservado | Lógica sin cambio; promoción a Admin y transferencia atómicas previamente aprobadas |
| T-03.3 revocación y auditoría | ✓ conservado | Lógica sin cambio; no nueva evidencia que reabra su aprobación |

| # | Grado inicial | Gap | Tarea | Corrección / veredicto | Evidencia |
|---|---|---|---|---|---|
| C1 | Critical | Origen controlado por Host en enlaces secretos | T-02 | corregido; A/B/C aprueban | TrustedUrl + callbacks de reset/invitación; AuthInputAndNotificationSecurityTest con notificaciones reales y origen, esquema, proxy y subruta |
| C2 | Critical | FPM TCP accesible desde laboratorio | T-01 | corregido; A/B/C aprueban | Socket Unix en volumen exclusivo PHP/Nginx, montaje web ro, PHP no root; pruebas operativas de TCP cerrado y conectividad saliente; despliegue antiguo detenido sin borrar volúmenes |
| B-01 | Important | Worker consume una cola fija distinta de configuración | T-01 | corregido; A/B aprueban | Argumento fijo eliminado; RuntimeIntegrationTest con RabbitMQ real y cola distinta, productor/worker sin queue explícita |
| B-02 | Important | Entrada email no escalar provoca 500 | T-02 | corregido; A/B/C aprueban | Middleware antes de throttle/Fortify, acción protegida y limitador defensivo; pruebas array, array vacío, número y null; smoke HTTP 422 |
| C3 | Critical | Sesiones anteriores al reset mantienen acceso | T-02 | corregido; A/B/C aprueban | AuthenticateSession estándar, HMAC sembrado por SessionGuard:571–578; rojo/verde Feature y HTTP; B verificó independientemente en 8083 tres cookiejars antiguos rechazados y login nuevo válido |
| A1 | Minor | Índices e integración describen runtime como pendiente | T-04 | pendiente para documenter después de QA | Contador del plan corregido; docs/RAG-INDEX.md:48 e integración-aplicaciones.md:49 aún requieren actualización |

Resultado: **cero Critical/Important abiertos y ningún gap nuevo**. Aprobados
alcance, pruebas significativas, verificaciones registradas y coherencia con
la memoria existente. Constitución, interop y contrato OpenAPI no aplican.
C ejecutó smokes HTTP de solo lectura en ambos perfiles y confirmó el orden de
middleware por route:list; B ejecutó el caso de cola y el fixture C3 aislados,
sin RefreshDatabase. La limitación del pipe Docker del revisor no se presenta
como defecto ni como inspección ejecutada: las pruebas operativas del
implementer y el contraste del código quedan identificados como tales.

Suite de implementación final: **30 passed, 230 assertions**; no se atribuye
esa suite al revisor. T-04 sigue abierta para E2E y documentación. ADR-002 y
ADR-004 se promoverán al cerrar la actualización documental; ADR-003 sigue
propuesta para la fase de entrega. Jira deshabilitado, sin publicaciones.

Medición revisión 1: 2026-10-22T13:50:16Z–14:13:18Z, fuente estimado,
tokens_reales/horas_ia/eur null. La revisión 2 usa ventana propia cerrada antes
de QA; su duración de reloj no se convierte en consumo IA.

## QA de fase 1 — intentos y diagnóstico del harness

Los resultados se conservan en `testing/fase-1/raw/`; los originales privados
permanecen ignorados. `qa-gate.py` ejecutado sobre cada JSON emite:

| Intento | Passed | Failed | Flaky | Skipped | Gate |
|---|---:|---:|---:|---:|---|
| 1 | 0 | 8 | 0 | 0 | NO-VERDE, exit 1 |
| 2 | 1 | 7 | 0 | 0 | NO-VERDE, exit 1 |
| 3 | 4 | 4 | 0 | 0 | NO-VERDE, exit 1 |

El segundo intento dura 919,634 s. E2E-03 en Docker pasa el recorrido completo
de invitaciones en 34,191 s. Los otros casos detectan literales y selectores
del harness que difieren de las vistas, o agotan sus límites de tiempo; no
se ha confirmado un nuevo defecto de aplicación. Los mensajes contrastados
son el rechazo genérico 403, el enlace de reset ya utilizado y el desplegable
de transferencia de propiedad. Los tiempos nativos incluyen muchas
navegaciones y peticiones consecutivas.

El tercer intento previsto conserva las aserciones HTTP, de permisos y de
base de datos; normaliza la URI Livewire y ajusta el presupuesto total del
recorrido a 360 s, con operaciones de 30 s y cero reintentos automáticos.
La limpieza elimina solo fixtures UUID propios de `emailing_testing`, incluso
tras timeout. T-04 permanece en progreso y no se inicia la fase 2 mientras
el gate no sea verde. No se declara M-01 aprobado por una persona.

El tercer intento dura 948,377 s, sin interrumpidos ni errores globales del
runner. E2E-02 y E2E-03 pasan en ambos perfiles. E2E-01 nativo espera 403 y
recibe 404 en el registro deshabilitado; E2E-01 Docker falla antes por timeout
de 30 s en la navegación inicial a `/dashboard`. E2E-04 falla en ambos perfiles
al rellenar el campo oculto después de un rechazo de contraseña. La atribución
inicial de Docker al mismo 404 se corrigió al contrastar el JSON real.

El [diagnóstico acotado](testing/diagnosis.md) prueba dos causas del harness y
deja sin resolver el timeout no reproducido. Incluye stdout real, scripts y
un diff propuesto que aún no se aplica. Se alcanzó el límite de tres intentos;
se espera decisión del usuario antes de la fase 4 y de un nuevo ciclo. No se
inicia T-05 ni se cierra T-04. Los informes MD/PDF preservan el gate rojo y los
pasos no ejecutados. La suite Feature anterior permanece en 30 passed / 230
assertions; no se volvió a ejecutar ni se migró la base operativa.

Medición QA1: 2026-10-22T14:47:40Z–15:46:38Z. Diagnóstico técnico imputado a
T-04: 2026-10-22T15:46:39Z–15:53:03Z, sin solapamiento de marcadores. En ambas
ventanas `tokens_reales`, `horas_ia` y `eur` son null; la duración de reloj no
se convierte en consumo IA. Tras el diagnóstico se cerró sólo el árbol
temporal de 8084; 8082/8083 siguen respondiendo 200. Sin nuevas suites, renders
o fixtures activos. Scope incremental a19afff: 89 archivos, 0 fuera de alcance,
0 avisos. Comprobación de datos públicos: 215 archivos, 0 hallazgos; no acredita
la limpieza del historial publicado, cuya autorización sigue separada.

Se solicitó aplicar el diff y abrir un único ciclo nuevo de hasta tres intentos.
Hasta recibir esa decisión no se modifica el harness ni se reinicia QA. El
estado de la iniciativa continúa en progreso; no se marca completa ni pausada.

## Reactivación del objetivo y segundo ciclo de QA

El 2026-10-22 el usuario reactivó explícitamente el objetivo después de la
solicitud con el diff concreto. Se retoma esa propuesta y un único ciclo nuevo
de hasta tres intentos. La autorización de reescritura del historial público
sigue siendo una decisión separada.

Se conserva el primer ciclo íntegro bajo `testing/archive/cycle-1/`. El
implementer aplica únicamente `proposed-harness-fix.patch`: expectativa 404
para la ruta de registro deshabilitada y reapertura visible del formulario
antes de confirmar la contraseña correcta. `git apply --check`, aplicación,
comprobación inversa, sintaxis Node 22 de ambos MJS y `git diff --check` salen
con exit 0. No cambian producción, timeouts, retries ni los demás asserts.

Medición de la corrección: 2026-10-22T17:13:17Z–17:15:32Z, cerrada antes de
iniciar la revisión 3 a las 17:15:33Z. Fuente estimado; `tokens_reales`,
`horas_ia` y `eur` null, sin convertir reloj en consumo. Scope: 135 archivos,
0 fuera de alcance, 0 avisos y 0 exclusiones de usuario. El selector del
incremento de dos MJS determina C=false y D=false; las lentes A/B revisan el
diff inmutable con las fuentes actuales.

T-04 sigue en progreso hasta revisión, QA y documentación. El timeout Docker
permanece abierto para observarlo durante el segundo ciclo; no se declara
resuelto. No se repiten migraciones ni suites con RefreshDatabase durante E2E.

## QA2, intento 1 y corrección del feedback de formularios

El intento 1 del segundo ciclo ejecuta los ocho recorridos completos: **8
passed, 0 failed, 0 flaky, 0 skipped, 0 interrupted**, 146,687 s; gate VERDE,
exit 0, contrastado por el coordinador con el JSON real. Se conserva íntegro
en `testing/archive/cycle-2-attempt-1/`. Los 25 criterios trazados no equivalen
a 25 recorridos ejecutados ni a la aceptación humana M-01.

La inspección posterior de la captura de auditoría detecta **FB-01 Important**:
tras una transferencia correcta, persiste el mensaje de contraseña incorrecta
del intento anterior junto al éxito. Propietario, roles y auditoría son
correctos; no se altera el resultado verde histórico del runner. T-04 continúa
abierta para corregir este defecto visual reproducible y verificarlo.

La regresión reutiliza la misma instancia Livewire tras rechazo y éxito.
**RED**: 3 failed, 1 passed, 31 assertions, 13,09 s; también fallan la invitación
y el guardado de permisos al corregir entradas inválidas. **GREEN focalizado**:
4 passed, 48 assertions, 18,08 s. **Feature completa**: 33 passed, 262
assertions, 53,60 s. El caso de retirada de acceso ya pasaba y no requiere
cambiar su implementación. `Team.php` añade únicamente `resetValidation()`
después del éxito en invite/saveMember/transfer; conserva los rechazos y la
reautenticación. E2E-04 exige que desaparezca el error anterior. Las capturas
normalizan el scroll antes de renderizar; no se cambia CSS de la aplicación.

Pint global, Composer y diff-check terminan con exit 0. Tres PHP del harness
se formatean por el rol QA. Docker QA reconstruido, sus tres servicios healthy,
sin migraciones ni pérdida de volúmenes. El incremento exacto queda en
`testing/diagnostics/feedback-fix.patch`; requiere revisión independiente y
**QA2 intento 2 de 3**, sin reiniciar el contador. Alcance global: 318 archivos,
cero fuera de alcance, avisos o exclusiones de usuario; C/D no aplican a este
incremento según el selector de siete archivos. A1 documental sigue pendiente.

Medición QA2-1: 2026-10-22T17:20:12Z–17:35:41Z. Corrección FB-01:
2026-10-22T17:35:42Z–17:49:28Z. Ambas ventanas cerradas sin solape;
tokens_reales/horas_ia/eur null por falta de telemetría compatible. La duración
de reloj no se imputa como consumo IA.

## QA2, intento 2 — fase 1 verde

Runner real: **8 passed, 0 failed, 0 flaky, 0 skipped, 0 interrupted**,
261,882275 s, cero errores globales. `qa-gate.py` sobre
`testing/fase-1/raw/results-cycle-2-attempt-2.json` emite VERDE, exit 0;
el coordinador vuelve a contrastar directamente el JSON y el gate. La primera
impresión auxiliar de su stdout falla por codificación cp1252 de consola;
se repite sólo la impresión con JSON ASCII, exit 0, sin repetir los tests ni
alterar resultados.

FB-01 queda verificado en ambos perfiles: transferencia válida después del
rechazo, ausencia del error anterior, propietario/roles/auditoría correctos
y capturas con scroll inicial. El coordinador inspecciona adicionalmente la
captura Docker. No se reproduce el timeout inicial de QA1; eso no prueba su
causa retrospectiva. Guardrail, ledger y cobertura trazada terminan exit 0.

Reportes `testing/report.md` y `testing/report.pdf`, con espejo en `fase-1/`:
10 páginas, ocho imágenes, 834743 bytes; inspección fitz de páginas 1/6/10,
cero recortes. Los ciclos previos permanecen archivados. Datos públicos: 354
archivos, cero hallazgos; no acredita limpiar el historial. M-01 humana sigue
pendiente; E2E-05 a E2E-11 y M-02 pertenecen a fases posteriores.

Sin fixtures huérfanas ni procesos QA activos. Perfil temporal 8084 cerrado
tras comprobar su árbol; 8082/8083 responden login 200. Sin migraciones
operativas ni suites backend simultáneas. La suite de implementación permanece
33/262, no atribuida a QA. T-04 continúa abierta sólo para el cierre documental
y A1. No se declara completo el roadmap.

Medición QA2-2: 2026-10-22T17:53:08Z–18:04:53Z. Documentación T-04-docs abre
a las 18:04:54Z, sin solape. Consumo real desconocido: tokens/horas IA/coste
null, sin convertir duración de reloj en consumo.

## Cierre de T-04 y relevo de fase 1 — 2026-10-22

T-04 completada y fase 1 cerrada: **4/20 tareas (20 %)**. Revisiones sin
Critical/Important abiertos, QA2-2 verde 8/8 y Feature 33/262 con stdout real.
A1 Minor corregida: los índices y la integración describen Compose, vhost,
usuario y worker existentes. La documentación actualiza nueve páginas y añade
`docs/aplicacion/{desarrollo,arquitectura,cuentas-y-equipo}.md`; comprobación
de 140 enlaces locales, cero rotos. El coordinador corrige además el namespace
de los comandos QA (`-p emailing-qa`); la plantilla de override no contiene
secretos. README raíz enlaza las guías y distingue QA de instalación operativa.

ADR-002 y ADR-004 pasan a aceptadas por revisión de dos lentes, intento 2,
tras actualizar documentación. ADR-003 continúa propuesta para la entrega
pendiente. GOT-001 registra la causa de FB-01 como propuesta, sin promoverla
sin revisión del documento. Sin candidatos de conocimiento que curar ni
publicaciones en Jira/Confluence, cuyo opt-in no está activo.

Comprobación final documental: ledger-lint exit 0, cero incoherencias y 16
avisos por Changelog de T-05 a T-20 todavía pendientes. Scope exit 0: 337
archivos, cero fuera de alcance, avisos o exclusiones de usuario. Datos
públicos: 358 archivos, cero hallazgos. Diff-check exit 0. Ningún cambio
productivo después del último QA; no se repiten suites por cambios de prosa.
Los tres commits de código son `3ff44be` (T-01), `b04e5a4` (T-02) y
`498978e` (T-03), con autor/committer público noreply verificado. El commit
documental y push se realizan después de esta comprobación.

Documentación: ventana 2026-10-22T18:04:54Z–18:18:51Z cerrada. Consumo real
desconocido, campos null, sin imputar reloj como horas IA. M-01 humana y
la instalación operativa/restore de T-20 siguen pendientes. El historial
publicado anterior conserva una solicitud separada de autorización para su
reescritura; los commits nuevos no la ejecutan. El roadmap completo continúa
en progreso. Siguiente tarea: T-05; orden de fase 2: T-05, T-06, T-08, T-07 y
puerta T-09, respetando la dependencia de las pruebas de plantilla con SMTP.

La primera comprobación del índice detecta espacios finales producidos por el
runner dentro del stdout RED y líneas de contexto del patch inmutable. No se
altera esa evidencia ni su hash. `.gitattributes`, añadido al alcance de T-04,
desactiva la comprobación de espacios sólo en patches y stdout crudo dentro
de `testing/`; las fuentes y documentación normal mantienen la comprobación.
Esto no cambia ninguna aserción, umbral o resultado de QA.

## Relevo T-05 → T-06

T-05 implementada y verificada: **14 passed, 208 assertions, 49,95 s**, exit 0;
stdout privado capturado directamente en `tests/.runtime/t05-contact-list.txt`.
El coordinador contrasta ese archivo y revisa autorización/locks, consentimiento
y validación previa a normalización. La inspección detectó y corrigió tipos
estructurados antes del cierre; también se conserva la navegación de recuperación
de cuenta activa revocada. Las cifras anteriores 13/184 y 14/199 son pasadas
históricas anteriores a esos bordes, no resultados de la versión final.

**5/20 (25 %)** implementadas; fase 2 en progreso, revisión y E2E de sus recursos
pendientes de T-09. Medición T-05: 2026-10-22T18:23:25Z–18:47:09Z, cerrada;
tokens/horas IA/coste null, sin convertir sus 24 minutos de reloj en consumo.
El checkpoint de fase 1 `6056343` ya está en origin/feature/plataforma-emailing;
el índice publicado tiene 359 archivos y cero hallazgos de datos privados.
No se ha reescrito el historial ni aplicado migraciones operativas.


## Continuidad de fase 2: T-06 a T-08

T-06 cerrada con 13 pruebas y 164 aserciones, exit 0; 37,67s. El lote de
10.000 registros usa 72 consultas y tarda 10,3755973s en la pasada final.
Los límites HTTP físicos siguen pendientes de T-09; no se confunden con
la configuración comprobada. Medición T-06: 2026-10-22T18:49:05Z a
2026-10-22T19:12:58Z, ventana cerrada; tokens, coste y horas IA reales null,
sin convertir tiempo de reloj en consumo. T-08 comienza con Mailpit/log;
envío externo deshabilitado por elección del usuario. El roadmap sigue
en progreso: 6/20 (30%). T-09 revisará y probará toda la fase antes de campañas.


## Continuidad de fase 2: transporte y plantillas

T-08 cerrada: 10 pruebas, 139 aserciones, 29,46s, exit 0. Incluye Mailpit
real, socket centinela para log y certificados no confiables reales en SMTPS
y STARTTLS. Medición T-08: 2026-10-22T19:15:10Z a 2026-10-22T19:35:09Z,
ventana cerrada; tokens/euros/horas IA reales null, sin imputar reloj como
consumo. Comprobación pública: 390 archivos, cero hallazgos. T-07 comienza
con transporte existente; 7/20 (35%) implementadas, revisión y QA conjunta
de fase 2 pendientes. Sin migraciones operativas, reinicios ni commits nuevos.


## Checkpoint de implementación de fase 2

T-07 cerrada con 12 pruebas, 126 aserciones y 29,42s, exit 0. La regresión
de preview obsoleta acredita primero un rojo real (1 fallo, 2 aserciones,
8,56s) y después el recorrido corregido sobre la misma instancia. Ventana
T-07: 2026-10-22T19:35:34Z a 19:57:54Z, cerrada; tokens/euros/horas IA reales
null, sin convertir reloj en consumo. Estado actual: **8/20 (40%)**.

T-09 comienza la validación conjunta: suite Feature **82 passed (899 assertions),
88,68s**, Pint global exit 0 y frontend con Node 22, 58 módulos, 1,72s, exit 0.
Los stdout reales quedan en `tests/.runtime/phase2-*-final.txt`. El primer build
fue bloqueado por `spawn EPERM` del sandbox; la ejecución autorizada con esbuild
fuera del sandbox pasó. No se cambió código para resolver esa restricción.

El usuario solicita commits y push conforme finalizan funcionalidades. Se
prepara un commit por tarea en la rama de trabajo, con T-09 en progreso. Este
checkpoint no cierra la fase: revisión adversarial y QA de navegador siguen
pendientes. Sin migraciones operativas, reescritura de historial ni datos
personales nuevos.


**Commits del checkpoint de fase 2**: `dd9aeb5` (T-05), `4b83d2b` (T-06),
`0681ba4` (T-08, incluida la fixture TLS reproducible) y `a86c465` (T-07).
Autor y committer públicos Daycry/noreply verificados; 399 archivos del
índice, cero hallazgos privados. Los borradores locales de T-08/T-07 se
reagruparon antes de publicarlos para incluir la fixture ya probada, sin
cambiar fuentes ni reescribir historial remoto. El siguiente push es normal
a feature/plataforma-emailing; no implica revisión ni QA de T-09 completadas.


## Correcciones verificadas del intento 1 · T-09

B-01/B-02/D-01 y el caso UI-01 se reprodujeron antes de corregirse. Los tres
módulos afectados pasan 42 pruebas /531 aserciones /142,36s. La regresión
completa posterior pasa 88 pruebas /957 aserciones /195,29s y Pint global exit0.
La única corrección posterior a esa suite elimina `required` del textarea HTML;
el formulario de solo texto se comprobará en QA. Build Node22 exit0: 58 módulos,
3,56s, con ejecución autorizada tras `spawn EPERM` del sandbox.

[Correcciones y límites de la evidencia](testing/fase-2/review/fix-1.md).
Ventana T-09-fix1: 2026-10-22T20:32:55Z–20:45:47Z cerrada, reales null.
T-05/T-07 vuelven a implementadas; **8/20 (40%)**. La revisión incremental
y el QA de navegador siguen pendientes; T-09 permanece en progreso.

## Revisión de dos lentes — intento 2: Fase 2 (T-05, T-06, T-07, T-08) — correcciones aprobadas

A+B+D con contexto fresco en paralelo; selector C=false, D=true por migración
incremental. Diff inmutable12archivos/38.509bytes, SHA256
`920ff79542e9ec722889b0d11c3b5f5818091cf2476e132e0285817b30d59da5`.
Alcance exit0, sin fuera de alcance/avisos/exclusiones. Tres lentes verifican
12/12 hashes y leen todo el diff. Subagentes genéricos con rol reviewer, runtime
heredado tras resolver tier; no se simula otro motor.

| ID | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| B-01 | Important | HTML-only rechazado | T-07 | Cuerpos alternativos normalizados | Regresión `.eml` y probes puros |
| B-02 | Important | Entidades compensan token prohibido | T-07 | Marcas por ocurrencia raw antes de DOM | Regresiones decimal/hex/contextos |
| D-01 | Important | Conteos sin índice por lista | T-05 | Nueva migración000006 | EXPLAIN poblado index→ref |
| UI-01 | Important | Efectos antes de403 al perder lectura | T-05 | Guard de entrada UI | Seis acciones sin cambios ni auditoría |

**Todos corregidos; cero Critical/Important abiertos y cero defectos nuevos.**
[Tabla completa, evidencia y límites](testing/fase-2/review/attempt-2.md).
La cita de tiempo posterior se alinea con el JSON final:0.0496152s; el snapshot
original queda inmutable. Las aprobaciones anteriores se conservan, sin reabrir
candidatos no confirmados. T-09 sigue en progreso hasta QA de navegador.
Ventana T-09-review2:20:51:05Z–20:56:31Z cerrada, reales desconocidos/null.
Sin Jira configurado ni issues mapeadas: no se publica ni envía ningún mensaje.

## QA de fase 2 — intento 1: no verde

Tanda de20 casos en native8082 y Docker8083, un worker y cero retries. Gate
original y público coinciden: **8 passed,12 failed,0 flaky,0 skipped,0
interrupted**; exit1,11,8min. Los originales privados y la copia pública con
redacción de rutas personales conservan estadísticas y resultados. Ver
[informe y clasificación](testing/fase-2/report.md),
[PDF verificado](testing/fase-2/report.pdf) y
[gate original](testing/fase-2/raw/qa-gate-attempt-1.json).

| ID | Grado | Resultado | Siguiente acción |
|---|---|---|---|
| QA-01 | Important | Móvil390px produce documento551px en ambos perfiles. Nav527px sinwrap; aplicar sólo flexWrap al DOM reduce documento390px y nav341px. | Corregir navegación conservando todos los enlaces y disposición desktop; revisión y QA2. |
| Harness | No se confunde con defecto app | Cleanup sources/UUID, bufferCLI, URLupload real, case de email normalizado, detalles tras render y readiness. Este último sigue provisional hasta QA2. | Conservar fallos originales; revisar cambios de harness sin bajar aserciones ni umbrales. |

Registro/recuperación, selector/aislamiento, invitaciones y preview hostil pasan
en ambos perfiles. No se acredita como verde ningún caso fallido ni la subida
cercana5MiB cuya lectura del resultado falló. Los positivos de plantillas del
harness siguiente conservan required y checkValidity del navegador.

QA confirmó cleanup de sus fixtures,12mensajes Mailpit propios y árbol8084
tras comprobar identidad de procesos;8082/8083 responden200. Cero residuos de
sus UUID. El JSON causal y los hashes antes/después del harness se conservan;
el cambio de guard de limpieza durante la tanda está registrado por separado.
No hubo cambios de aplicación ni migraciones durante QA. PDF final4páginas,
inspeccionado sin texto fuera de página; control público499archivos,0hallazgos.

Ventana T-09-qa1:2026-10-22T20:59:37Z–21:37:22Z cerrada, reales null. T-09 sigue
en progreso,8/20 tareas implementadas; M-01 humana y las fases3–4 pendientes.

## Corrección QA-01 — navegación responsive, pendiente revisión y QA2

Cambio mínimo en `resources/views/components/app-layout.blade.php`: nav añade
`flex-wrap` y `lg:flex-nowrap`, conservando columna desktop y todos los enlaces.
No se oculta overflow ni se recorta contenido. Causa previamente aislada por
QA1 mediante un único cambio transitorio DOM en ambos perfiles.

Build Node22 exit0,3,31s; `git diff --check` exit0. Chromium nativo con fixture
owner propio confirma:390/scroll390/nav341,768/scroll768/nav719 y
1440/scroll1440/nav207. Los seis enlaces quedan completos en cada ancho y el
último navega a `/accounts`; escritorio mantiene column/nowrap. Ver
[rectángulos y resultado](testing/fase-2/review/raw/nav-result-fix2.json),
[stdout browser](testing/fase-2/review/raw/nav-browser-fix2.txt),
[build](testing/fase-2/review/raw/nav-build-fix2.txt) y
[cleanup](testing/fase-2/review/raw/nav-cleanup-fix2.txt).

Fixture eliminado:0usuarios/0cuentas propios; browser/context cerrados y sin
servidor nuevo.8082/8083 siguen200. Los nombres de logs privados son etiquetas,
no marcas de reloj; la ventana se toma de usage-meter:2026-10-22T21:37:23Z–
21:44:41Z cerrada, tokens/euros/horas IA reales null. La primera ejecución de
esbuild/Chromium encontró restricciones de creación de proceso del sandbox;
los procesos autorizados pasaron sin cambiar código para evitar esa restricción.

No se repite suite backend por clases de layout; QA2 independiente queda
pendiente tras revisión del nuevo diff y reconstrucción de Docker. El harness
QA1 corregido conserva los fallos originales y no cambia umbrales. La hipótesis
de readiness E06 aún necesita confirmación por el siguiente recorrido.

## QA de fase 2 — intento 2 de 3: resultado conservado

QA2 ejecutó los veinte casos completos, sin reintentos ni exclusiones, en
Laragon y Docker: **13 aprobados, 7 fallidos, 0 flaky, 0 omitidos y 0
interrumpidos**. `qa-gate` original y público: **NO-VERDE, exit 1** en ambos;
la redacción de una ruta personal no alteró estadísticas ni veredictos.
Playwright terminó con exit 1; el wrapper terminó con exit 0 y no sustituye al
gate. Fuentes congeladas: 37/37, cero diferencias al finalizar.

| Caso | Perfiles | Punto de parada observado |
|---|---|---|
| E2E-05 CRUD/CSV | Ambos | Línea 32: URL `/storage/<id>.csv` devuelve 403 frente a 404 esperado |
| E2E-05 handles | Ambos | Línea 79: llamada de importación devuelve 200 frente a 403/404/422 esperado; la comprobación SQL posterior no se alcanzó |
| E2E-06 formularios | Docker | Línea 22: `checkValidity()` false; snapshot conserva nombre vacío y campos predeterminados |
| E2E-07 transportes | Ambos | Línea 22: URL `/storage/mail-artifacts/<id>.eml` devuelve 403 frente a 404 esperado |

Pasaron en ambos perfiles la geometría móvil/tablet, el CSV de 9.990 filas con
paginación y rechazo por tamaño, y el preview hostil. El recorrido completo de
formularios de E2E-06 pasó en nativo. Las comprobaciones posteriores al punto de
parada de un caso fallido permanecen pendientes; no se acredita aislamiento ni
transporte a partir de una aserción que no llegó a ejecutarse.

Limpieza de esta tanda terminada: 18 UUID conservados en recibos (los dos casos
E2E-02 eliminan directamente su JSON), comprobación adicional sólo de lectura
de residuos sintéticos de la ventana exclusiva, cero usuarios/cuentas propios
restantes y cero uploads registrados restantes. Doce mensajes propios de
Mailpit eliminados por IDs exactos; históricos ajenos preservados. Servidor
8084 propio retirado y 8082/8083 responden HTTP 200. Recibos:
[limpieza](testing/fase-2/raw/teardown-attempt-2.json),
[procesos](testing/fase-2/raw/terminal-attempt-2.json),
[integridad](testing/fase-2/raw/source-integrity-attempt-2.json) y
[gate](testing/fase-2/raw/qa-gate-attempt-2.json).

T-09 permanece en progreso, 8/20 tareas implementadas. Siguiente paso:
reproducción focal y corrección de los contratos/sincronización del harness,
revisión fresca y sólo después QA3 completa. QA1 y QA2 se conservan sin
reescribir sus resultados. [Informe](testing/fase-2/report-attempt-2.md) y
[PDF inspeccionado](testing/fase-2/report-attempt-2.pdf) congelados: once páginas,
36 capturas de esta tanda archivadas y verificadas. Copia privada adicional de
494 archivos originales comprobada byte a byte; ningún archivo público previo
fue alterado al archivarla. Recibo:
[archivo previo a QA3](testing/fase-2/raw/archive-private-attempt-2.json).
Control de privacidad de QA: 644 archivos, cero hallazgos.

Ventana QA2 cerrada: 2026-10-22T22:56:22Z–23:37:03Z (41 minutos de reloj).
Sin telemetría compatible: tokens, euros y horas IA reales desconocidos/null;
la duración de reloj no se presenta como esfuerzo IA medido.

## Corrección posterior a QA2 — diagnóstico y T-06 reabierta

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `67489dbe62d409b60a55022beb84f891826b6cb792cd43ba99e9dcd2117efcd0`. El estado actual permanece en su tarea y evidencia de aceptacion.

## Revisión de dos lentes — intento 6: Fase 2 (T-06, T-09) — cero gaps nuevos

Primer intento de tres del incremento posterior a QA2; revisión global 6.
Lentes A+B por revisores genéricos frescos, sin atribuir ejecución Claude.
Selector C=false/D=false sin avisos; scope exit 0, cero fuera de alcance,
avisos y exclusiones de usuario. Snapshot: 11 archivos, 44 fuentes y 23
evidencias; SHA256 `6710537c7f33756652bac7e003085d4e05e51bf396be4bc670eee38c19775fe8`.
Root verificó todos los hashes antes de añadir esta traza administrativa.

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| Anteriores | Important históricos | B-03/B-04 y criterios previamente aprobados | T-09 | Conservados; sin evidencia nueva que los reabra | [Revisión 5](testing/fase-2/review/attempt-5.md) |
| Nuevos | — | Ninguno: 0 Critical / 0 Important / 0 Minor | T-06, T-09 | Sin corrección adicional | [Dictamen y criterios](testing/fase-2/review/attempt-6.md), [patch](testing/fase-2/review/attempt-6.patch), [manifiesto](testing/fase-2/review/attempt-6.patch.json) |

GOT-001/GOT-002 revisados se promueven a aceptada con esta traza; ADR-003
sigue propuesta por su implementación futura pendiente. QA2 conserva 13/7;
focales conservan 6/2, 1/1 y E07 final 2/0. No se atribuye una causa probada
a la observación nativa intermedia. T-09 continúa en progreso, 8/20 tareas
implementadas; esta revisión permite QA3 y no concede su verde.

Ventana de revisión: 2026-10-23T00:29:04Z–00:37:26Z, ocho minutos de reloj.
Tokens, euros y horas IA reales desconocidos/null, sin telemetría compatible.
Jira no configurado; no se publica fuera del repositorio. Siguiente paso:
commit/push del bloque revisado y QA3 completa, intento 3/3, actor único.


## QA3 de fase 2 — verde automático y documentación pendiente

Única matriz completa del intento 3/3, código auditado `c24d227`:
**20 aprobados, 0 fallidos, 0 flaky, 0 omitidos y 0 interrumpidos**.
Los gates original y público son idénticos y terminan exit 0; Playwright y
wrapper también exit 0. Root verificó el JSON: diez casos nativos y diez Docker,
todos con un solo resultado aprobado, un worker y cero reintentos.
Duración Playwright: 850.043,555 ms (14,17 minutos), sin extrapolar rendimiento.

Ver [informe QA3](testing/fase-2/report-attempt-3.md),
[PDF de diez páginas](testing/fase-2/report-attempt-3.pdf),
[gates y exits](testing/fase-2/raw/gate-comparison-attempt-3.json),
[comprobación de root](testing/fase-2/raw/root-matrix-verification-attempt-3.json)
y [handoff terminal](testing/fase-2/raw/handoff-attempt-3.json).
La copia pública redacta una ruta personal y conserva estados, cuentas y errores;
el original privado y sus hashes se preservan. QA1 permanece 8/12 y QA2 13/7,
sin alterar los rojos focales ni atribuir causa a la observación nativa intermedia.

Integridad antes/después: 37 fuentes QA y dos de producto, cero diferencias.
Veinte UUID y sus copias verificados por identificadores y hashes; cero residuos SQL
propios, seis entradas temporales registradas sin archivos y cero mensajes
propios restantes en Mailpit (doce eliminados por sus IDs, ajenos preservados).
`lab_smoke` conserva cinco filas. Servicios 8082/8083 responden 200; wrapper y
árbol propio de 8084 están ausentes y no queda listener en 8084. DB liberada
explícitamente al coordinador; no se refrescó la base de producto.

PDF exportado una sola vez: veinte colocaciones visibles, diecinueve recursos
únicos por deduplicación de una imagen idéntica, sin contenido fuera de página.
La primera medición incorrecta de recursos terminó exit 1 y se conserva;
exportador exit 0 y comprobación corregida exit 0 sobre el mismo PDF.
Dos lecturas observacionales del inventario y una proyección inicial de PIDs
fallaron; sus recibos están separados de los veinte tests y no se maquillan.
Whitespace simple exit 2 conservado; criterio `cr-at-eol` exit 0 sobre el diff
real, sin inferir una verificación Git de los artefactos aún no preparados.
Control público final declarado: 777 archivos, cero hallazgos; se repetirá sobre
los bytes exactos del índice antes del commit.

Ventana QA3 cerrada: 2026-10-23T00:40:58Z–01:12:44Z, 32 minutos de reloj.
Tokens, euros y horas IA reales desconocidos/null, sin telemetría compatible.
Documentación de referencia abierta desde 2026-10-23T01:13:01Z con agente fresco.
T-09 sigue en progreso hasta comprobar esa entrega; 8/20 tareas implementadas.
M-01 humano continúa pendiente; T-10–T-20 y la iniciativa global siguen abiertos.


## Cierre de fase 2 — T-09 completada

Root acepta la documentación de referencia tras leer el diff y ambas guías
nuevas: contactos/importación y plantillas/transporte. Dos páginas nuevas y
doce actualizadas; 238 enlaces relativos comprobados por documenter, cero rotos.
La comprobación posterior de root cubre 300 enlaces en 17 documentos, incluidos
README, índice de roadmap y ledger: cero destinos relativos rotos.
Se precisan límites, permisos, recursos compartidos por cuenta, diferencias
preview/email, credenciales y estados de transporte. La inspección adicional
confirma Laravel v13.38.0/Livewire v4.4.8 y binding temporal de una hora contra
fuentes locales. README raíz incorpora módulos actuales y enlaces a las guías.

Revisión A+B sin Critical/Important pendientes; Feature 90/977 y QA3 automática
20/20 con ambos gates exit 0, limpieza e integridad acreditadas. T-09 pasa a
completado; fase 2, 5/5; total, 9/20 (45%). No se reescriben los resultados
históricos ni se deduce una causa para la observación nativa intermedia.

Ventana documental cerrada: 2026-10-23T01:13:01Z–01:25:42Z, trece minutos
de reloj. Tokens, euros y horas IA reales desconocidos/null. Las precisiones
editoriales posteriores de root no son un nuevo resultado de QA ni justifican
repetir las suites sin cambios de código.

M-01 continúa pendiente de una persona. El cierre corresponde a implementación,
revisión, QA automática y documentación de esta fase; no acepta manualmente
la aplicación ni implementa T-10–T-20. Plan/spec globales siguen abiertos.
Siguiente tarea: bajas y supresiones, con comprobación inmediata pretransporte
y prueba durable completa en la fase de entrega, sin habilitar SMTP externo.


## Inicio de T-10 — bajas y supresiones

Fases 1/2 publicadas y T-09 completada en `c585b78`. T-10 y fase 3 pasan a
en-progreso; contador 9/20 sin cambiar. Ventana de implementación abierta desde
2026-10-23T01:43:39Z; un único implementer con DB de pruebas exclusiva.
Se conserva log/Mailpit y el envío externo deshabilitado. La emisión de enlaces
de baja opacos y firmados, permisos vigentes, aislamiento y comprobación
pretransporte se implementarán sin afirmar todavía un worker durable ni
feedback de proveedor. M-01 humana y los módulos restantes siguen pendientes.


## T-10: componente implementado, cierre integral pendiente

Diecinueve archivos congelados para revisión independiente. La verificación
declarada `php artisan test --filter=SuppressionTest` termina exit 0:
**15 passed, 153 assertions, 55,47 s**. Suite Feature completa:
**107 passed, 1147 assertions, 418,22 s**, exit 0. Prueba de frontera entre
dos procesos con MySQL real: **2 passed, 17 assertions**, exit 0.
No se mantienen sesiones, procesos auxiliares ni propiedad exclusiva de la DB.

Pint acotado a los quince PHP de T-10 y build con Node 22 terminan exit 0.
Pint global termina **exit 1**: doce archivos de QA de fase 2, incluidos tres
helpers activos y nueve copias históricas privadas; ninguno fue modificado.
El formato global sigue pendiente de resolver distinguiendo fuente activa de
evidencia inmutable. Los dos intentos previos de build con spawn EPERM y el
primer arranque fallido del test de frontera se conservan como fallos de
lanzamiento/carga; no sustituyen los resultados finales ni constituyen QA E2E.

Las supresiones se conservan hasta retirada explícita auditada o eliminación
de la cuenta, independientemente del ciclo de vida del contacto. Los enlaces
de baja, opacos y firmados, no caducan automáticamente y pueden revocarse;
solo se guarda su hash. Un enlace vigente puede volver a suprimir después de
una retirada explícita. GET confirma y POST con CSRF ejecuta la baja.

El mutex de cuenta serializa la baja y el inicio durable del transporte;
no se mantiene transacción SQL durante E/S. La prueba completa «encolar,
dar de baja, ejecutar worker» necesita T-12/T-13/T-16. T-10 continúa
en-progreso, sus criterios de cierre integral y E2E-08 permanecen pendientes;
total sin cambios: 9/20. CA-10/14 no se declaran completados por esta prueba
de componente. Feedback real y envío externo siguen sin habilitar.

Ventana de implementación: 2026-10-23T01:43:39Z–02:16:16Z, 33 minutos
de reloj. Tokens, euros y horas IA reales desconocidos/null.
Siguiente puerta: revisión fresca del incremento; esta traza no concede
QA de fase 3 ni cierre de tarea.


## Corrección B-01 de T-10 — paginación verificada

Se añade `resetPage()` después de la retirada autorizada y una regresión
con dieciséis supresiones. RED contra componente original: **1 failed,
6 assertions**, exit 1. Mismo test tras fix: **1 passed, 12 assertions**,
exit 0; suite completa de supresiones **16 passed, 165 assertions**, exit 0.
Pint acotado a dos PHP: exit 0. Root verificó 28 archivos del freeze, hashes
del mismo test RED/GREEN y fuentes finales de la suite, sin discrepancias.

Solo cambian componente y test; no se reejecutaron ni relabelaron Feature
107/1147, build o frontera anteriores. Pint global sigue exit 1 por históricos
ajenos. DB liberada y procesos terminales. Ver [evidencia del fix](testing/fase-3/review/b01-fix.md).

Ventana fix1 2026-10-23T02:29:21Z–02:39:47Z, diez minutos de reloj;
tokens/euros/horas IA reales null. B-01 corregido en implementación, pendiente
de revisión 2/3. Se conservan dictámenes y tabla anteriores; T-10 integral
y total 9/20 no cambian. No se declara QA de fase 3 ni worker durable.


## Inicio de T-11 — campañas con revisión y fecha efectiva

Checkpoint técnico de T-10 publicado en `5a1a52f`, HEAD/origin comprobados
iguales y árbol limpio antes de empezar. El componente está aprobado por
A+B+D sin gaps pendientes tras corregir B-01. Se marcan solo sus criterios
comprobados de tokens/idempotencia, limitación de feedback y definición de
retención; la tarea sigue abierta por la prueba durable encolar→baja→worker.

T-11 pasa a en-progreso usando ese contrato técnico revisado. No se elimina
la dependencia de T-10 ni su cierre pendiente de T-12/T-13/T-16. Total 9/20
sin cambiar, M-01 humana y QA de fase 3 pendientes, ADR-003 propuesta.
Ventana de implementación desde 2026-10-23T07:13:27Z; implementer fresco y único actor
de `emailing_testing`. Root conserva ledger, contadores, medidores y Git.

La composición guardará borrador/revisión/autorización y programación UTC,
con recursos/snapshots de la cuenta y permisos efectivos. T-11 no publicará
en RabbitMQ ni enviará SMTP: outbox y worker corresponden a T-12/T-13.
Las pruebas deben incluir fechas, versiones/acciones concurrentes y permisos;
no acreditar el recorrido E2E-08 completo hasta existir esa integración.
Se mantienen log/Mailpit y envío externo bloqueado.


## T-11: verificación focal de campañas en verde, puertas restantes pendientes

Verificación declarada `php artisan test --filter=CampaignTest` ejecutada:
**25 passed, 247 assertions, 190,91 s**, exit 0. Incluye las dos clases
CampaignTest y ConcurrentCampaignTest y cuatro escenarios con procesos MySQL
reales: doble autorización, editar frente a autorizar, pausa concurrente y
revocación de scope mientras espera el mutex de cuenta. Final de la ejecución:
2026-10-23T08:10:58.370876+00:00. Root verificó stdout/stderr contra los hashes del recibo.
SHA256 stdout `a9ba720f9a652d20fa1cf1a77c8648995630d28953332a8326d554685d263312`.

Manifest final provisional: 16 fuentes, SHA256 `22042e586f36dcb9b563e9a14c2bc012acf84d23e0288e71b1f4c9f415c4b7cb`;
cero discrepancias al contrastarlas con el árbol actual. Se conservan los fallos
originales: primer arranque con colisión de helper PHPUnit, ejecución 13/9 por
Blade y comparación estricta del JSON del remitente, y ejecución 24/1 por una
aserción de audiencia que incluía el selector independiente. Se corrigió esa
comprobación acotándola a la audiencia paginada y verificando sus IDs; no se
eliminaron casos concurrentes. El build inicial con spawn EPERM se conserva;
el build posterior con Node 22 terminó exit 0, 58 módulos.

T-11 sigue en-progreso. Aún faltan el resultado del filtro de plantillas,
suite Feature final y revisión independiente del incremento. Estas pruebas
de componente no conceden QA E2E ni aceptación humana M-01. El recorrido
real de worker para borradores/futuras y baja tras encolar continúa pendiente
de T-12/T-13/T-16. Total 9/20; T-10 permanece abierta y ADR-003 propuesta.


## T-11: componente verificado; revisión independiente pendiente

El implementer liberó la base/usuario exclusivos `emailing_testing` a
2026-10-23T08:28:30.650416+00:00, tras terminar todos sus procesos propios.
Root contrastó los 16 SHA de fuentes contra árbol y ZIP final, y cada recibo
y stream original contra su índice. Manifest `22042e586f36dcb9b563e9a14c2bc012acf84d23e0288e71b1f4c9f415c4b7cb`.

Puertas finales ejecutadas sobre ese componente:

- CampaignTest (incluye ConcurrentCampaignTest): 25 passed / 247 assertions,
  exit 0; cuatro casos MySQL con procesos reales.
- TemplateTest (incluye CompiledTemplateTest): 20 passed / 185 assertions,
  exit 0; regresiones originales y nuevas del renderer.
- Suite Feature: 133 passed /
  1404 assertions, exit 0;
  stdout SHA256 `d8011866582040ffa87d3870f98fca5a3c37c1313295177c4cd555131b72a228`.
- Pint --test acotado a los 13 PHP del manifest: exit 0. El Pint global
  histórico continúa exit 1 por 12 ficheros QA; pendiente de T-20.
- Build de producción con Node 22: exit 0, 58 módulos. Intento anterior
  spawn EPERM preservado, sin relabelarlo como éxito.
- Benchmark puro del camino validateBatch: 100/200/10.000 destinatarios,
  HTML de 96.025 bytes; 0,2512206 / 0,2764786 / 3,9095198 s, pico 10 MiB.
  No mide SQL de review, envío SMTP, capacidad total ni SLA.

El handoff documenta scopes, fechas UTC estrictas, versiones y snapshots
inmutables, contratos para T-12/T-13 y resultados fallidos anteriores.
La ventana de implementación queda cerrada: inicio 2026-10-23T07:13:27Z,
fin 2026-10-23T08:29:47Z; reloj 1h 16m.
Tokens/coste/horas IA reales solo conforme a la salida del medidor; no se
deducen del tiempo de reloj ni se inventan cuando el medidor los deja NULL.

Pendiente revisión independiente del incremento. No son pruebas de navegador,
aceptación humana M-01 ni QA completa de fase 3. T-10/T-11 siguen abiertas por
la integración real con outbox/worker y sus pruebas literales T-12/T-13/T-16.
El total sigue 9/20 y ADR-003 continúa propuesta.


## Inicio de T-11-fix1 — recuperar selección eliminada desde el borrador

B-01 se corrige dentro del mismo incremento: retirada explícita de selección
desde la UI y regresión de lista eliminada/contacto con soft delete, sin poda
silenciosa ni relajación de scopes, cuenta, revisión o confirmación. El dominio,
renderer y migración aprobados no se modifican para resolver este problema.

Medidor de revisión 9 cerrado antes de abrir T-11-fix1 a
2026-10-23T08:49:50Z. Implementer único actor de `emailing_testing`; root conserva
ledger, medidores y Git. Brief determinista exit 0, con aviso permitido de
10.686 caracteres: la persona frontend conserva su suelo de 1.229 caracteres.
No se recorta el contrato. Tier sonnet/medium por frontmatter; runtime heredado.

Se exige reproducción/regresión focal RED→GREEN y verificación CampaignTest,
Pint acotado y build si cambia la vista. Feature 133/1404 y plantillas 20/185
permanecen ligadas al snapshot anterior; no se declaran reejecutadas sobre el
fix. T-10/T-11 siguen en-progreso, total 9/20 y ADR-003 propuesta. Commit/push
pendientes de la corrección y segunda revisión del mismo incremento.


## T-11 B-01 fix1 — recuperación explícita verificada, pendiente segunda revisión

B-01 corregido dentro de los tres archivos autorizados: limpieza explícita de
listas/contactos del formulario, sin poda automática ni SQL antes de guardar.
Reconsulta scopes/cuenta/versión/estado y retira la confirmación anterior.
Mismo borrador recuperable con audiencia válida; cambios sin guardar no pueden
autorizar la revisión anterior. Regresión de permiso revocado incluida.

RED recuperación 1/9 exit 1 → GREEN 1/33 exit 0; RED revisión 1/1 exit 1 →
GREEN 1/16 exit 0, mismos métodos sin cambiar expectativas. CampaignTest final
**28 passed, 301 assertions, 284,72 s**, exit 0, con los cuatro casos MySQL
concurrentes. Pint --test de dos PHP y build Node 22 exit 0. Build EPERM previo
conservado. Root verificó 16 fuentes finales, 38 artefactos y ocho recibos con
16 streams; manifest `68275ea630a84269e7a36ddbb9182030170ff4b7b4e3298ae6dabe1e4348ec3c`.

[Fix, límites y evidencia](testing/fase-3/review/t11-b01-fix.md).
DB liberada por implementer a 2026-10-23T09:14:21.060181Z; todos sus handles
terminales. Ventana fix1 2026-10-23T08:49:50Z–2026-10-23T09:17:59Z; reloj
28m; tokens/euros/horas IA reales NULL.

Pendiente segunda revisión del mismo incremento. Feature 133/1404 y plantillas
20/185 son del snapshot anterior, no reejecutados; Pint global sigue pendiente
de T-20. No se concede QA E2E/M-01/worker ni cierre integral de T-10/T-11;
9/20 y ADR-003 propuesta sin cambios.


## Inicio de T-12 — outbox transaccional y publicación reconciliable

Checkpoint T-11 publicado en `1205e471f1ebf3d26a51ebe3b54278808aae1a87`; índice público de 917 archivos,
sin hallazgos de datos personales y 16 fuentes coincidentes con revisión 10.
Las evidencias JSON conservan sus bytes mediante `.gitattributes`; quedan
registrados los espacios de la vista para el formato global de T-20.

T-12 arranca sobre los contratos de campañas revisados. T-10/T-11 permanecen
en-progreso porque sus pruebas integrales necesitan el motor de envío;
no se elimina esa dependencia ni se concede su aceptación anticipada.
Brief determinista y resolución de tier exit 0, sin recorte manual del brief.
Config dev ausente: TDD/worktree/cobertura mínima no activados; ejecución por
implementer del workflow solicitado, fallback genérico/runtime heredado.
Root conserva ledger, medidores, Git y publicaciones; implementer es único
actor de emailing_testing y conserva todos los recibos/fallos originales.

La ventana T-12 empieza 2026-10-23T09:55:42Z, tras cerrar revisión 10. La tarea
debe demostrar rollback SQL, materialización concurrente idempotente,
confirm/return, fencing y reconciliación de la misma identidad, con payload
mínimo y recursos propios. Sin SMTP ni consumidor de entrega T-13 añadido
implícitamente; las pruebas Linux y E2E de cada ventana se registran con
su alcance real, sin sustituirlas por mocks ni cerrar T-16 anticipadamente.
Total 9/20, ADR-003 propuesta y envío externo deshabilitado.


## T-12: primera verificación focal roja, evidencia conservada

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `7edcf3c3c324a1ca47776ad516f1ab0db7ed20140175db429f3d28c4d9278ac2`. El estado actual permanece en su tarea y evidencia de aceptacion.

## T-12: segunda verificación focal, recuperación Linux aún pendiente

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `cc1f13e7eb017b26d2d6794b0a37311f7301b9413d217521ad307c4c6f6643a1`. El estado actual permanece en su tarea y evidencia de aceptacion.

## T-12: depuración causal de la señal de prueba Linux

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `6fffeddf4ab5c969d09ebdaf1456510f693addd9ff96450305eff2d2ea2d8af1`. El estado actual permanece en su tarea y evidencia de aceptacion.

## T-12: primer focal completo verde; congelación final y suite global pendientes

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `eb3de50032e922548154d4cac38d0c4484f68081a8d4ad2dec4853e05b6452c5`. El estado actual permanece en su tarea y evidencia de aceptacion.

## T-12: focal final congelado verde; suite completa en ejecución

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `a483b616e815420cb03cc5a66becdeebb9e521f74c004aa09aa6dbdc9a125eba`. El estado actual permanece en su tarea y evidencia de aceptacion.

## T-12: suite completa final verde; rendimiento y revisión pendientes

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `969d55c27d8a13569d6c336f3d5d3583fe0c4a9de63cb6e730e93e61ee30bade`. El estado actual permanece en su tarea y evidencia de aceptacion.

## T-12: coste medido del materializador antes de revisión 11

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `7bea4d97dcbfccac6366f9c7539da632cad5e46640c3909620076b452daefbc9`. El estado actual permanece en su tarea y evidencia de aceptacion.

## T-12: corrección del coste confirmado antes de la primera revisión

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `2d1ecaa2075ddae26c119083cb80f816ee5a0e2d8343df9dc91f67a71891cc7d`. El estado actual permanece en su tarea y evidencia de aceptacion.

## T-12: RED de presupuesto SQL comprobado antes de escribir el bulk

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `56faa2d5e9c376c03a40337e93f576fd0f997dd20eef2b3256ec6f4adce3aa6d`. El estado actual permanece en su tarea y evidencia de aceptacion.

## T-12: bulk verificado en regresión y benchmark; focal y suite pendientes

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `4328437fa8e0e7b960ff97f9ed71759faac01ba1526e811badd61b8e4e7cca96`. El estado actual permanece en su tarea y evidencia de aceptacion.

## T-12: focal final del bulk verde; suite completa en curso

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `6bbdd923f04dbd086bb7595cee32d033bffd21dae79e7ec25f8d576fae3fb1db`. El estado actual permanece en su tarea y evidencia de aceptacion.

## T-12: implementación final verificada y liberada para revisión 11

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `b22309497788cdf022b2f4bcb68e091643a429dbbd4ea22e5a3d04923f3969d3`. El estado actual permanece en su tarea y evidencia de aceptacion.

## Inicio de T-12-fix1 — contraste y corrección de D-01

Primera corrección tras revisión 11, sin reiniciar el límite de tres intentos.
Ventana T-12-fix1 inicia 2026-10-23T12:05:50Z, después de cerrar revisión. Brief
determinista y tier exit 0; fallback genérico/runtime heredado. Root mantiene
ledger, medidores, Git y publicación; implementer retoma exclusivamente
emailing_testing y sus handles. Ninguna otra prueba SQL se inicia en paralelo.

D-01 se contrasta primero con consulta/índices y plan o trabajo SQL real sobre
backlog propio de 10.000; no se corrige a ciegas ni se atribuye una estimación
a EXPLAIN. Si se confirma, se exige regresión RED antes de cambiar código,
corrección acotada de selección/índice y evidencia de coste con volumen e
historial publicado. Evitar medir solo número de consultas o exigir un nombre
de índice: debe comprobarse el trabajo de selección global sin eventId.

Se conservan Account/revisión/procedencia, available_at, leases, token/generación,
CAS, SQL UTC y publicación fuera de transacción. Cualquier ajuste del orden
de elección debe explicarse contra el contrato. No basta añadir un índice que
elimina filesort a cambio de recorrer todo el historial publicado. No se tocan
servicios compartidos, privilegios ni recursos ajenos. Originales, release,
índices y snapshot de revisión 11 permanecen inmutables; evidencias nuevas
en implementation/claim-fix1. Después, gates afectados/focal/full y segunda
revisión con tablas A/B/D completas, conservando aprobados salvo nueva evidencia.

Revisión 11 y plan Jira dieron ops vacías porque Jira está desactivado.
Controles posteriores: ledger-lint cero incoherencias/once avisos de tareas
abiertas; 998 archivos públicos sin hallazgos; siete documentos/65 enlaces
relativos sin rotos. No es auditoría de historial ni gate del futuro índice Git.
T-12 sigue en-progreso, total 9/20; GOT-003/ADR-003 propuestas y envío externo
deshabilitado. Los verdes 17/173 y 158/1.663 siguen ligados al snapshot previo.


## T-12-fix1: D-01 confirmado por MySQL y regresión RED

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `ca04b230986ce892456e290705a9e258cfd316099cd0096b356c5e563bec0339`. El estado actual permanece en su tarea y evidencia de aceptacion.

## T-12-fix1: selección indexada y regresiones verdes; gates completos pendientes

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `31389399aeb307329185b7110ba931cbc569258440b68045479d28f2e5d0e0ce`. El estado actual permanece en su tarea y evidencia de aceptacion.

## T-12-fix1: suite completa verificada y liberación para revisión 12

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `a754987fb5150c498bb9d643f5e1a1092674c06ff4cc811145863efb66da9695`. El estado actual permanece en su tarea y evidencia de aceptacion.

## Preparaci?n de publicaci?n T-12: conservar bytes de metadata

Tras la revisi?n de c?digo, la preparaci?n del ?ndice detect? que los metadata
*.patch.json originales usan CRLF y pod?an normalizarse por autocrlf. Se a?ade
una regla -text acotada a esos artefactos en .gitattributes, coherente con las
reglas ya existentes de patches y raw. git check-attr confirma text unset para
los metadata de intentos11/12. No cambia c?digo PHP ni la evidencia congelada;
la comprobaci?n exacta del ?ndice debe conservar sus hashes originales.
Los tres dict?menes completos se mantienen sin editar. Enlaces relativos de
los12Markdown cambiados:91destinos existentes, cero rotos. Ledger-lint: cero
incoherencias y11avisos esperados de Changelog en tareas abiertas. Jira plan
revision12: desactivado, ops[], sin comunicaciones externas.

La comprobaci?n del ?ndice se?al?9.381 avisos de CRLF en35JSON de evidencia
inmutable, ninguno en c?digo PHP, Markdown u otros archivos. Se conserva la
salida diagn?stica privada. Las reglas de metadata y rawJSON a?aden-whitespace
para preservar exactamente el formato original de herramientas, igual que los
patches/rawTXT; no excluyen c?digo de aplicaci?n. Los bytes/hash JSON permanecen
intactos. Se repetir? git diff --cached --check sobre el ?ndice preparado.


## Inicio de T-13 — workers y resultado SMTP durable

Checkpoint T-12 publicado por push normal: `0a5cce2fe5e7d363cf8794ab16c0267d925097bf`; ls-remote confirma la
misma rama remota. Autor/committer Daycry público; árbol igual al índice de
1.070 archivos,157cambios verificados y cero hallazgos de privacidad. Whitespace
del índice pasa preservando bytes de evidencia. De las154fuentes del full,
150coinciden raw con Git; cuatro Blade de contexto sin cambios respecto al
parent difieren solo CRLF/LF. Los15archivos de código cambiados y los25core
coinciden exactamente con las fuentes probadas. No se atribuye igualdad raw a
esos cuatro contextos normalizados ni limpieza de todo el historial Git.

T-13 comienza 2026-10-23T13:18:46Z, con medidores anteriores cerrados. Brief y tier
deterministas exit0; rol implementer por fallback genérico/runtime heredado.
No se activa tdd/worktree/subagentes en dev.json: configuración ausente conserva
sus defaults. El rol implementer de la cadena de custom-agents sigue vigente.
Root mantiene ledger/medidores/Git; implementer será único actor de fuentes
runtime y emailing_testing. No hay pruebas SQL de root ni handles previos vivos.

El componente de T-12 está aprobado y publicado; su criterio integral depende
del consumidor, por eso sigue abierto al iniciar T-13. Se conserva el alcance
literal: reservas/heartbeats/fencing, recovery reservado frente sending ambiguo,
ack tras resultado durable, retries con nueva generación/outbox, fallo durable
y cola de fallos real, checks vivos de consentimiento/supresión/contacto original/
remitente/pausa, cuotas atómicas por cuenta y pool explícito compartido. No basta
un consumidor que solo procesa el caso feliz ni un mock etiquetado como crash.

Se reconciliarán los servicios actuales AccountMailTransport/SmtpConnector con
el único arranque durable; sin SMTP bajo locks ni segundo camino de envío
independiente. Fuente Symfony local y los seis hashes de preparación siguen
vigentes. Se exigen relay controlado y procesos Linux reales en las ventanas de
aceptación, pérdida de respuesta, resultado SQL y ack; diagnósticos permitidos
sin AUTH/cuerpo/secretos. Mailpit/log; correo externo deshabilitado.

T-10/T-11/T-12/T-13 en progreso; total9/20. ADR-003 permanece propuesta hasta sus
pruebas completas; GOT-003 aceptada para barreras. QA de fase3, M-01, API y cierre
operativo conservan el plan completo. No se publican estadísticas inventadas.


## T-13: núcleo de recuperación en desarrollo; gates pendientes

El implementer informa de código en desarrollo para migración de reservas/
intentos/disponibilidad/message_id/diagnósticos, DeliveryLifecycle, DeliveryQuota
y observación SMTP por etapa dentro del conector existente. El arranque común
de AccountMailTransport integra send/sendDelivery, supresión y cuota; se añade
rechazo de transacción exterior antes de writes/IO. Esta última protección surge
de la lectura del código previo: DB::transaction anidada no garantiza commit
antes de SMTP si el llamador ya tiene una transacción abierta. Su regresión real
y el resto de los gates todavía no se han ejecutado en este checkpoint.

Diseño reportado: token/generación/estado/lease cercan reservas y resultados;
reserved vencido produce una nueva generación ready, sending vencido pasa a
ambiguous; la observación se marca antes del terminador DATA y preserva el250
final frente a errores de limpieza. Cuotas UTC por minuto/día con pool explícito
del servidor compartido entre cuentas. Estados informados: pending/reserved/
sending transitorios; logged/smtp_accepted/failed/ambiguous/suppressed/blocked
terminales. No constituyen aceptación ni prueba de concurrencia por declararse.

Quedan por demostrar pausa/reanudación durable y espera de cuota sin inflar
intentos; blocked no debe descartar una pausa recuperable. Se exige política
explícita de ventanas fijas y su burst de frontera, sin prometer rolling-window;
cuota agregada entre dos cuentas y entre envío directo/worker. El guard exterior
debe rechazar antes de writes/SMTP. Las pruebas usarán procesos/relay reales y
los escenarios completos del handoff; en este checkpoint todavía no hay gates
ni handles de test reportados por el owner. Root no ejecuta SQL ni edita runtime.

Preparación estática T-15: el due actual de CampaignService no filtra revisiones
ya materializadas y DeliveryMaterializer no cambia authorized/scheduled. Un
futuro escaneo que siempre limite al mismo prefijo necesita demostrar avance
hacia pendientes posteriores. Se conserva nota privada con blobs del commit
0a5cce2, no con fuentes live mientras T-13 las edita; no se ha implementado ni
cerrado Cronicle. T-13 sigue en-progreso, medidor abierto y total9/20. Correo
externo deshabilitado; no se atribuyen los verdes previos de T-12 al código nuevo.

## T-13: primer diagnóstico verificado; ampliación en ejecución

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `1cfa6f1eaa597e12c9e318066a6d5895a83595ac43b4837dcf5308bc7ca7325d`. El estado actual permanece en su tarea y evidencia de aceptacion.

## Revisión de dos lentes — intento 13: Fase 3 (T-10, T-11, T-12, T-13) — componentes aprobados

**Lentes:** A+B+C+D sobre el incremento T-13, intento acotado **1 de 3**.
Cuatro revisores frescos genéricos, runtime heredado: el tier del plugin no
acredita un modelo Claude ejecutado. Scope primario exit 0, 41 archivos sin
exclusiones aplicadas, avisos ni info oculta. Los informes completos y el patch
exacto están en [revisión 13](testing/fase-3/review-13.md).

**Veredicto:** 0 Critical, 0 Important, 0 Minor pendientes. Se conservan las
aprobaciones y rebates de las revisiones 10/12, sin reabrirlos sin evidencia
nueva. La revisión no sustituye al QA de fase T-16 ni cierra tareas posteriores.

| Criterio | Veredicto de cierre | Evidencia |
|---|---|---|
| T-10: baja posterior a encolar y aislamiento del mismo email | ✓ | [Adenda AMQP](testing/fase-3/review-13-supplement.md): ready 2 antes de POST 200, A suppressed/0 intentos/sin mensaje y B smtp_accepted/1 intento; único MAIL FROM de B, ACK y cola vacía. |
| T-11: borrador/futura, autorización y recursos | ✓ | El full final repite campañas y cinco concurrentes; los workers reales revalidan draft/future y políticas con cero transporte, cuotas e intentos. Aprobaciones UI/selección de revisión 10 conservadas. |
| T-12: SQL/broker, publicación repetida y topología/ACL | ✓ | Full final repite 20 Outbox; prueba real de identidad denegada en vhost ajeno y separación de jobs conservadas. Consumidor real duplicado converge a un inicio; cortes y reconciliación no pierden pendientes silenciosamente. La carga cruzada de dos aplicaciones sigue en T-19. |
| T-13: reservas, fencing y resultado durable | ✓ | Dos consumidores, propietario antiguo vivo, heartbeats/lease, recuperación reserved frente a sending ambiguo, dos KILL137 causales y corte SQL tras 250. Resultado/continuación durable antes de ACK; sin retry ciego. |
| T-13: cuotas, pausa, retry y fallo | ✓ | Cuenta/pool del servidor con locks ordenados, UTC y cruce real del minuto sin cargo anticipado; pausa/reanudación, backoff acotado y fallo/outbox sin consumidor de reenvío. |
| Compatibilidad y configuración | ✓ alcance de componente | Jobs Laravel ordinarios separados, CLI Linux continuo/SIGTERM y smoke nativo Windows. Compose definido y comprobado estáticamente; su despliegue operativo no se atribuye a estos gates. |

### Adenda probatoria y metadata de publicación

La prueba primaria demostraba materialización SQL → baja → publicación AMQP,
como precisó A después del informe inicial. Root exigió además el orden literal
publicación AMQP → baja → consumo, sin inventar un defecto de runtime.
La variante privada conservó los 185 archivos runtime 32f y pasó **1 prueba/51
aserciones**, exit 0. Dos revisores frescos A/B la contrastaron independientemente,
con 0 gaps. La adenda mantiene el intento 1/3: no corrige ni vuelve a revisar
todo el incremento primario.

Se ejecutan dos consumidores Linux secuenciales, uno por entrega; no se atribuye
concurrencia adicional ni replay al suplemento. El observador privado comprueba
el hash del relay público intacto y registra exclusivamente el MAIL FROM
sintético recibido, sin AUTH ni cuerpos. Sus 16 hashes loaded coinciden con el
runtime revisado. El rojo inicial de ReflectionClass, exit 2/14 aserciones, se
conserva como fallo del fixture; no acredita B ni aserciones finales.

La adenda inicial de 158 archivos omitía los originales terminales raíz. Antes
de aprobar suficiencia se incorporó una cápsula separada de 15 originales,
vinculada por hash; ambos revisores leyeron y verificaron recibos/streams. No se
reconstruyeron ni sobrescribieron los snapshots previos. Los dos diagnósticos
auxiliares fallidos de censo siguen identificados como históricos fallidos.

El censo adicional observa tres contenedores y dos relays propios ausentes,
topologías eliminadas y guard SQL de testing con la única tabla existente
migrations:0. Es observación fechada, no liveness actual ni conteo de tablas de
negocio ya retiradas. El owner liberó explícitamente fuentes y base, con todos
sus handles terminales. No se repitió el full ni el censo histórico de 342:
el runtime185 permaneció idéntico.

La metadata añade `raw/*.md -text -whitespace`, siguiendo txt/json, y declara
`.gitattributes` en Archivos T-13. A/B la revisaron; selector oficial de esas dos
rutas: C=false, D=false, sin avisos. El índice inicial detectó CRLF/LF en el
handoff y se recalculó con la política correcta antes de publicar. Los raw se
comparan por bytes exactos. Las diferencias de EOL de texto ordinario se
registran por separado y no se presentan como igualdad byte a byte.

### Gates, conocimiento y contabilidad

Los cinco gates finales comparten manifiesto
`32f798edf22b2e2385fa4846953c25814cdf79160aaa4c31d7cece8bdcd3996f`:
focal de campañas **5/34**, Pint acotado **26 PHP**, full literal
`php artisan test` **183/2227**, CLI Windows/replay **8 comandos exit 0** y
censo atribuido **342 contenedores ausentes/0 helpers coincidentes**. El full
incluye **21 WorkerRecovery, 20 Outbox y 5 ConcurrentCampaign**. Las 184 fuentes
del proyecto coinciden con las fuentes revisadas; el miembro185 es el productor
privado. Los ZIP/productores e inputs de workers permanecen privados.

El comando focal exacto WorkerRecovery de **21/429** y Outbox de **20/299** se
conservan con sus fuentes históricas. La continuidad solo cambia dos fixtures
de campañas; los 21/20 casos vuelven a pasar en el full32f. Los verdes anteriores
no se reetiquetan como gates de otro snapshot. Tampoco se convierten los hashes,
los conteos de pruebas o el censo en cobertura porcentual ni aceptación de UI.

GOT-004 se promueve con la traza canónica de revisión13: el mecanismo Windows
reproducido y su regresión RED→GREEN están revisados. No identifica el actor del
fallo histórico ni recupera su error primario desconocido. ADR-003 conserva
propuesta porque actividad/totales, Cronicle y QA integral todavía faltan.

Medidor T-13 cerrado anteriormente con fuente estimado. El
[cierre de revisión13](testing/fase-3/raw/t13-review13-meter-close.json) también
degrada a fuente estimado: tokens reales, coste y horas IA reales no disponibles.
Sus **48m son duración de reloj**, no horas IA medidas ni coste cero. No hay
solapamiento con un medidor T-14; el reparto histórico de preparaciones privadas
permanece desconocido. Sin Jira activado, no se envía ningún mensaje externo.

**Estado resultante:** T-10, T-11, T-12 y T-13 completadas como componentes;
**13/20 tareas cerradas**. La fase3 sigue en progreso: T-14 actividad, T-15
Cronicle y T-16 QA permanecen pendientes, al igual que T-17–T-20. M-01, envío
externo/feedback, carga/restauración y despliegue final no se dan por hechos.


## Inicio de T-14 — actividad y métricas con procedencia durable

T-13 y el cierre de componentes T-10–T-13 se publicaron en `defc14e`; remote
verificado con ese SHA completo y worktree limpio antes de abrir T-14.
Contador formal **13/20**. La fase3 continúa en progreso; no se concede QA
integral, M-01, API, carga/restauración ni despliegue final.

El implementer recibe ownership exclusivo de fuentes runtime y emailing_testing
para T-14. Root no ejecutará SQL/app/SMTP en paralelo. Las cápsulas de revisión13
y sus originales permanecen inmutables. La preparación privada de tres overlays
de procedencia solo tenía syntax checks: debe contrastarse con las fuentes
actuales y probar MySQL, lifecycle de cuenta y compatibilidad de migraciones,
sin atribuirle implementación o aceptación previa.

La actividad incluirá entregas sin intento, intentos sucesivos y registros
históricos de procedencia desconocida sin inflar el denominador. Reutilizará la
autoridad activity.read para consultas/descargas y revalidará cuenta/permisos en
acciones posteriores al mount. SMTP aceptado, log, fallo, supresión y ambigüedad
deben distinguirse; sin feedback externo, entrega/aperturas/clics/reputación no
se presentan como cero incidencias o estado saludable.

Medidor de implementación T-14 abierto por root, separado de revisión13 ya
cerrada. La recuperación de tokens depende de las transcripciones disponibles;
no se atribuyen a este marcador preparaciones históricas de coste desconocido.

## T-14 — Primer diagnóstico de actividad, sin aceptación

La ejecución literal `php artisan test --filter=ActivityTest`, run privado
`activity-initial-20261023T210856468377Z`, termina con **9 passed, 4 failed,
98 assertions**, PHPUnit duration 343,76 s. El proceso hijo sale 2 y el wrapper
1. Su sesión 32369 fue confirmada terminal por el implementer antes de cambiar
fuentes. El verificador independiente de root contrasta request/receipt,
stdout/stderr y los **196 archivos** del ZIP contra su manifiesto SHA256
`3b84c8ba2921800d3f57adf8c2613d801ba7af07d05830d361ba159b15da01e8`;
no hubo cambios de fuentes durante ese run. Es evidencia diagnóstica roja,
no el gate final, ni revisión independiente, ni QA de fase.

Los cuatro fallos se conservan y se corrigen por su causa: el detalle de una
referencia ajena usa `Query.Builder.firstOrFail` sin conversión HTTP 404
(defecto del producto); la fixture de reintento asigna una expresión SQL a
un atributo datetime casteado; la fixture SMTP convierte el JSON de readiness
directamente a entero 0 en vez de extraer su campo port; y el test de stream
debe comprobar la causa HTTP 403 que Laravel envuelve, junto con salida vacía.
La observación escalar `php -n` de root confirma JSON→int 0 sin cargar aplicación,
SQL ni red. No se atribuye SMTP causal correcto a la ejecución inicial.

Los verdes parciales no cierran criterios adicionales: la siguiente versión
debe repetir la focal completa, ampliar los escenarios de fallo terminal,
CSV por lotes y cardinalidad/EXPLAIN, y superar la suite completa sobre las
fuentes finales. El formato inicial había corregido tres archivos; su child0
con cambios de fuentes se clasifica solo como formatter, no como gate inmutable.
El nuevo verificador rechaza expresamente ese caso en modo de aceptación.

T-14 conserva estado **en-progreso**, total **13/20**. El implementer mantiene
app/SQL en exclusiva para sus correcciones y siguientes gates; root solo ha
leído evidencias y ejecutado la observación PHP sin ini ni autoloader. El medidor
de implementación sigue abierto; consumo real no disponible, no cero medido.

## T-14 — Cuatro correcciones causales y prueba parcial SMTP

La sesión 14829 de `activity-four-causal-corrections-20261023T211914509456Z`
fue confirmada terminal por el implementer: **3 passed, 1 failed, 45 assertions**,
duration PHPUnit 49,50 s, hijo 1. Su filtro cubre exactamente los cuatro casos
antes rojos; no es la focal literal completa ni una revisión independiente.
Root verifica request/receipt, streams y ZIP de 196 fuentes sin mutación,
manifiesto `08515589326615648d9d887f97a0afba3895935fcbe9621877d57a02dd1863c5`.

Las observaciones SQL y del relay controlado prueban ahora, en este subcaso,
una Delivery, tres MailMessage de generaciones 1/2/3 y el actor autorizador
original distinto del viewer. El relay registra tres conexiones/RCPT, dos
rechazos seguros, un DATA y un 250 final. Otro relay registra un DATA y pérdida
deliberada de respuesta final, sin 250; pasan las aserciones de estado ambiguo
y su vista. Las fuentes copiadas del relay coinciden con el manifiesto de ese
run. Son llamadas directas a claim/transporte durable, no prueba nueva de
AMQP, CLI, concurrencia ni entrega al buzón. El producto ya responde 404 a la
referencia ajena en el caso antes fallido.

Queda rojo el getter de la fixture de stream: la clase instalada
`Illuminate.Routing.Exceptions.StreamedResponseException` conserva la causa en
`originalException`/`getInnerException()` y no en `getPrevious()`. Root ha leído
la implementación instalada. Se corrige el test para verificar HTTP 403 y
salida vacía mediante el getter real, sin relajar la autorización del producto.
El rojo anterior permanece archivado. El siguiente gate incluirá además los
casos pendientes de fallo terminal, CSV por lotes y planes de consulta.

Estos verdes parciales no alteran el estado de T-14, el contador **13/20** ni
los criterios posteriores del roadmap. El censo de procesos propios sigue
bajo el implementer; root no consulta procesos ni ejecuta aplicación/SQL.

## T-14 — Detalle acotado y siete regresiones causales

El diagnóstico de planes conserva dos focales rojos por una aserción de
metodología: exigir que MySQL eligiera un índice cuyo nombre comenzara por
`activity_`. Ese nombre no forma parte del contrato. El segundo diagnóstico
`mysql-activity-plan-observed-20261023T213148029982Z` sí guardó los planes reales
antes del assert: 1 failed, 10 assertions, PHPUnit 39,32 s. Root verifica su ZIP,
streams y manifiesto `2ad07cc318c89955b4c76070894be83709ce0166717498599a36f0b177ca1c75`.

Los planes revelan una mejora concreta del producto: el detalle por referencia
sintética conservaba ambas ramas del UNION y filtraba después. Para un punto de
campaña, la rama de mensajes usaba acceso ref por cuenta con 601 filas estimadas;
el derivado estimaba 975. Se parsea ahora tipo/ID, se limita la rama correcta por
identidad y se hace imposible la opuesta, manteniendo cuenta y scope. No se
fuerza un índice. Los planes posteriores muestran tres puntos d/m/d con acceso
const por PRIMARY y una fila estimada, sin el escaneo de la rama opuesta.
EXPLAIN aporta estimaciones del optimizador, no filas escaneadas medidas ni
capacidad acreditada. El fixture contiene 601 entregas y 1202 mensajes;
page25/page1 ejecutan seis consultas cada uno, antes y después.

La sesión 43235 termina **exit 0: 7 passed, 76 assertions**, PHPUnit 196,46 s,
en `activity-seven-causal-20261023T213727298095Z`. Root contrasta request/receipt,
streams y ZIP de 196 fuentes, sin mutación, manifiesto
`3b87d6f24dbb9cce488c52035b896bf47eadc2b2000e3013bf89f35bbb14a790`.
Pasan los casos de stream revocado sin bytes, CSV revocado entre lotes de 200,
agotamiento SMTP y alerta local, filtros de campaña ajena/cambio válido de cuenta,
planes de punto, backfill con poblaciones/generación desconocida y lifecycle de
borrado con intentos. Es un focal de regresiones, no el comando exacto completo
de Activity ni la suite global. Cambios posteriores de unidades/históricos y
feedback de filtros se verificarán sobre su propia versión final.

Root añade la guía `docs/aplicacion/actividad-y-metricas.md` y corrige los índices
de documentación que aún describían T-10–T-13 como futuros. La guía conserva
estado en validación, distingue registros/inicios/aceptación SMTP y explica
ventana, denominador, desconocidos, CSV parcial y controles de acceso. Primer
check: cuatro Markdown, 135 enlaces relativos existentes y cero hallazgos de
los patrones de datos públicos configurados; no es auditoría completa de PII,
historia Git, semántica ni QA de navegador. La revisión independiente permanece
sin iniciar; T-14 sigue en-progreso, total **13/20**, medidor abierto.

## T-14 — Unidades históricas y recuperación del filtro inválido

El focal `activity-units-filter-probe-20261023T214911544389Z` conserva
**1 passed, 1 failed, 24 assertions**, PHPUnit 58,57 s, hijo 2 y 196 fuentes
sin mutación, manifiesto
`d32b168aabdcfbcd6f7801dbcb9c23a830855ead3270bbc1a5b3255ae2bd1e61`.
Pasa el nuevo caso que separa registro independiente, cero inicios acreditados
en pruebas explícitas suprimidas/bloqueadas sin IO y legado sin marcador de
inicio desconocido. El marcador durable SQL no acredita por sí solo una conexión
SMTP. UI y CSV mantienen esas unidades separadas; no se deducen conexiones de
la existencia del registro.

El fallo restante reproduce un defecto del producto: ValidationException durante
render de Livewire deja una vista nula y termina en `with() on null`. Se corrige
capturando únicamente la validación de filtros, mostrando su error y ocultando
resultados/contadores/CSV mientras sean inválidos. Al corregirlos se limpia ese
error y se vuelve a consultar; la autorización de cuenta y los 403/409 quedan
fuera del catch. El caso causal
`activity-filter-correction-20261023T215206878181Z` pasa **1 test, 8 assertions**,
PHPUnit 35,97 s, hijo 0, 196 fuentes sin mutación, manifiesto
`59e8aecd5ed47deefad1d001683063b22ee1a56e38a77329d8454a15f0117c21`.
Root contrasta ambos request/receipt, streams y ZIP; no ejecuta aplicación/SQL.

La clase focal ampliada contiene 19 casos pendientes de ejecutar conjuntamente
en su versión final, seguida del comando completo, checks y revisión. El build
final debe incorporar las últimas vistas y sus unidades/foco. La guía ya explica
el filtro inválido, los históricos desconocidos y el CSV sin snapshot global.
T-14 no se cierra ni se atribuye QA de fase; contador **13/20** y medidor abierto.

## T-14 — Focal final y migración incremental verificadas

El comando literal `php artisan test --filter=ActivityTest` termina con
**19 passed, 212 assertions**, PHPUnit 513,53 s, hijo 0 y stderr vacío, en
`activity-final-exact-20261023T215959177563Z`. La prueba focal de migración
`OutboxIntegrationTest::test_incremental_migration_down_up_is_repeatable_and_keeps_campaign_snapshots`
termina con **1 passed, 15 assertions**, PHPUnit 48,62 s, hijo 0 y stderr vacío,
en `migration-final-20261023T220944786414Z`. Root contrasta por separado sus
request/receipt, hashes de streams y ZIP: ambas conservan las mismas 196 fuentes,
sin mutaciones, manifiesto
`59e8aecd5ed47deefad1d001683063b22ee1a56e38a77329d8454a15f0117c21`.

El build final y Pint en modo comprobación ya tienen recibos estables y exitosos
sobre ese mismo manifiesto. El implementer ha iniciado después el comando literal
`php artisan test`, run `full-final-20261023T221135766386Z`, y confirma mediante
poll propio su handle vivo. Ese inicio no acredita el resultado global. Mantiene
la exclusividad de aplicación/SQL; root solo verifica archivos y documentación.
Tras el resultado terminal quedan el censo final atribuido, la liberación explícita,
la revisión independiente y las puertas de cierre. T-14 permanece en-progreso,
contador **13/20**, medidor abierto; no hay commit ni push de esta tarea todavía.

## T-14 — Suite completa final sin mutaciones, liberación pendiente

El implementer confirma el handle 36110 terminal con hijo 0 para el comando
literal `php artisan test`, run `full-final-20261023T221135766386Z`:
**202 passed, 2441 assertions**, PHPUnit 2598,46 s, stderr vacío. Root verifica
independientemente request/receipt, streams y ZIP de 196 fuentes sin mutaciones,
manifiesto `59e8aecd5ed47deefad1d001683063b22ee1a56e38a77329d8454a15f0117c21`.
Se verifican además 494 copias atribuidas y 236 orígenes no copiados todavía
disponibles; la atribución cubre archivos nuevos observados tras el run, no
archivos transitorios eliminados antes de su inventario.

El resumen de consola contiene 18 clases y sus 202 casos suman el total:
Activity 19, WorkerRecovery 21, OutboxIntegration 20 y ConcurrentCampaign 5,
entre las regresiones existentes. Los conteos de casos no acreditan cobertura
semántica completa ni QA de navegador. Los gates focal, migración, full, Pint
y build final comparten el mismo manifiesto de fuentes.

El censo atribuido final y el guard de la base de pruebas se ejecutan después,
bajo propiedad del implementer; root no inicia aplicación, SQL ni procesos de
servicio. La revisión independiente aún no ha empezado y la liberación explícita
sigue pendiente. T-14 conserva estado en-progreso, contador **13/20** y medidor
abierto. E2E-08/09 y QA de fase T-16 mantienen su alcance posterior completo.

## T-14 — Censo contrastado, handoff y cierre del medidor de implementación

El run `final-owned-settlement-20261023T225545600570Z` termina con hijo 0,
196 fuentes sin mutación y el mismo manifiesto final. Root contrasta sus
request/receipt/streams y, mediante un auditor de archivos independiente,
722 huellas de procedencia, 10 runs de tests y los 28 runs de productor que
ya eran terminales al iniciar el censo. Se verifican las consultas Docker de
46 nombres propios ausentes y las 84 comprobaciones pasivas de cola/exchange
para 42 nombres broker registrados. La consulta nativa archivada no encuentra
huellas T-14; el filtro CIM excluye PID30492. El guard ejecutado acredita
`emailing_testing` / `emailing_testing@%`, únicamente `migrations=0`.

La lista de 99 PID históricos no implica que todos esos números sigan ausentes:
PID27972 aparece sin firma propia. Su proceso original tiene recibo terminal
de un auxiliar fallido; no se atribuye el proceso presente ni se actúa contra él.
La comprobación se limita a la procedencia registrada, no a todos los recursos
del host. Root lee los resultados archivados sin consultar procesos, SQL ni
broker de nuevo. El primer intento del auditor root esperaba formato Docker
`.Name`, mientras la petición real usa `json .State`; se conserva su diagnóstico
y se corrige esa expectativa, manteniendo nombres exactos, stdout vacío,
errores de objeto ausente y códigos originales. No es un fallo del producto
ni un intento de revisión.

El escritor del handoff final termina con hijo 0. El propietario confirma
después explícitamente todos sus handles terminales y libera fuentes, app/UI,
tests y la base de pruebas. Root verifica el hash del handoff, las 41 fuentes
de su índice privado y la continuidad en vivo de las 196 fuentes del full.
No hay nuevo código suplementario ejecutado fuera del árbol T-14 declarado.

Se cierra el marcador `2026-10-21-plataforma-emailing/T-14` a
`2026-10-23T23:00:46Z`, antes de abrir revisión. El recibo degrada a
`fuente: estimado` por transcripciones no disponibles: tokens, euros y horas IA
reales siguen desconocidos/null, no cero medido. **CLOCK: 2h 7m** es duración
de ventana, no consumo IA medido. T-14 sigue en-progreso y el total **13/20**;
la revisión independiente se abrirá sobre la versión liberada.

## Inicio de T-14-fix1 — ausencia de asociaciones y transporte histórico

El señalamiento A-01 se confirma contra la consulta y el backfill: una entrega
puede conservar `attempts > 0` sin registros de intento asociados de forma
reconstruible. El texto vacío actual deduce una ausencia de transporte que esos
datos no acreditan. Se corrige la presentación y se añade una regresión causal;
no se cambian asociaciones, contadores ni decisiones de envío.

Se abre `2026-10-21-plataforma-emailing/T-14-fix1` a
`2026-10-23T23:26:36Z`, sin solapar la revisión14 cerrada. El implementer confirma
recepción y recupera propiedad exclusiva del runtime, vistas, pruebas y
`emailing_testing`. El coordinador mantiene documentación, ledger y Git; no
ejecutará aplicación, SQL, SMTP ni suites mientras dure esa propiedad. El tier
declarado del plugin es sonnet/medium por frontmatter; al no existir ese rol
nativo se reutiliza el agente genérico con runtime heredado, sin afirmar un
modelo Claude efectivo ni aplicar un override.

Las fuentes, cápsula de revisión14, handoff y evidencia previos se conservan.
Los gates sobre la implementación anterior siguen siendo históricos. Antes de
la revisión15, pasada acotada **2/3**, se verificarán la corrección, ActivityTest,
migración incremental, suite completa, formato, build y liberación del entorno
con evidencia nueva sobre las fuentes finales. T-14 continúa **en-progreso**,
total **13/20**, sin commit ni push de este incremento todavía.

## T-14-fix1 — regresión causal verde; gates finales pendientes

Se conserva RED sobre el nuevo test y la vista original:
`fix1-causal-red-20261023T232829154345Z`, hijo 1, **1 fallo/7 aserciones**,
18.86 s del runner. Tras comprobar la migración real con puntero compartido,
dos inicios conservados y cero asociaciones, falla precisamente la exclusión
de la frase que negaba el transporte. Ese rojo no prueba los escenarios que
quedan después de la primera aserción fallida.

El texto se sustituye por «Sin registros de intento vinculados en esta página».
El GREEN `fix1-causal-green-20261023T233018388447Z` pasa **1 prueba/25
aserciones**, 19.21 s del runner, hijo 0. Conserva las comprobaciones anteriores
al fallo y añade después el caso de página fuera de rango con un registro
vinculado existente. Se contrastan: dos entregas históricas, `smtp_accepted` y
`ambiguous`, con dos inicios y cero vínculos; una entrega pendiente con cero
inicios; y una entrega real del transporte log con un inicio, total de un
registro y cero filas visibles en su segunda página.

Root verifica recibos, streams, ZIP de 196 fuentes, hashes y ausencia de
mutaciones de ambos runs, además del protocolo conservado y los hashes de
clases cargadas/vista contra el ZIP verde. Los dos inicios históricos son datos
de fixture; no se presentan como una conversación SMTP observada. El caso de
página utiliza transporte log. La fuente verde tiene manifiesto
`30d3025450edd434386fb0a045f45cf5cd95a92e1f010e5fd033fbd86f8e594f`.

El auditor auxiliar de root tuvo dos errores de interpretación de la etiqueta
del diagnóstico PHPUnit. Los originales y sus diagnósticos se conservan; la
versión v3 contrasta el texto crudo y termina 0. No son fallos nuevos de la
aplicación ni consumen intentos de revisión. Se prepara un nuevo freezer de
revisión2, todavía sin ejecutarlo: exigirá fuentes finales, liberación y
medidor cerrado, y trasladará todos los veredictos de revisión14.

Pint focal y build nuevos terminan 0 según sus recibos del propietario; el
ActivityTest literal de 20 métodos sigue en ejecución. Migración, full, censo,
handoff, nueva revisión y publicación continúan pendientes. T-14 permanece
**en-progreso**, contador formal **13/20**, medidor fix1 abierto.

## T-14-fix1 — focal final y migración verdes; full en ejecución

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `ad194654636cd37585272c60508e26fa1866c907cc1d68ac23781ddaba721f39`. El estado actual permanece en su tarea y evidencia de aceptacion.

## Revisión de dos lentes — intento 15: Fase 3 (T-14) — A-01 corregido, sin gaps pendientes

Intento acotado **2/3** de T14; revisión global15. Lentes **A+B+D** por
selección oficial, C no activada. Scope24/24, sin fuera de alcance, avisos,
info ni exclusiones efectivas. Tres revisores genéricos independientes,
contexto fresco y runtime heredado; tier declarado del plugin sin afirmar
runtime Claude ejecutado. Se trasladaron los veredictos anteriores completos,
sin rebuttals ni deuda aceptada, y el diff de corrección.

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| A-01 | Important, corregido | Una página sin asociaciones negaba indebidamente el inicio histórico del transporte | T-14 | Texto neutral por página; regresión down/up con dos históricos de dos comienzos/cero vínculos, cero real y página2 vacía con total1 | [Informes A](testing/fase-3/review-15-A.md), [B](testing/fase-3/review-15-B.md), [D](testing/fase-3/review-15-D.md); [causalidad y gates](testing/fase-3/review-15.md) |

| Criterio | Veredicto final |
|---|---|
| Estados aceptado SMTP/fallido/suprimido/ambiguo distinguibles | ✓ Conservado |
| Cuenta y activity.read revalidados en consultas, acciones y descargas | ✓ Conservado |
| Filtros UTC, cohortes y denominadores; desconocido distinto de cero | ✓ Conservado |
| Procedencia durable en la transacción de inicio; histórico sin conjeturas | ✓ Conservado |
| Migración/FK/lifecycle, registros y comienzos separados | ✓ Conservado |
| CSV acotado, reautorizado y protegido contra fórmulas | ✓ Conservado |
| Vacío por página compatible con comienzos históricos y total no vacío | ✓ A-01 corregido |
| Consultas e índices sin nueva regresión de rendimiento | ✓ Conservado, planes sintéticos no son benchmark |

Las tres lentes reportan **0 Critical, 0 Important y 0 Minor pendientes**.
Root verificó el escenario contra vista/test y evidencia causal rojo1/7 →
verde1/25, focal20/237 y full203/2.466 de las mismas196 fuentes sin mutación.
La verificación completa del sello tras recibir los informes conserva
4.420 entradas intactas. A declara su omisión voluntaria de .env.example
(4.419/4.420); B/D y root contrastan los inventarios completos. Ningún
inventario acredita lectura semántica de todas las dependencias ni cobertura
de código. Los informes completos conservan sus alcances y límites.

Revisión15 medida 2026-10-24 00:34:32Z → 00:48:09Z: **CLOCK14m**.
Sin transcripciones, tokens reales, coste y horas IA siguen desconocidos.
Se publican informes íntegros y evidencia seleccionada con verificación de
privacidad y bytes; productores, ZIPs, credenciales y streams privados no
seleccionados permanecen fuera de Git.

T14 cierra como componente, contador **14/20**, fase3 **5/7**. Este cierre
no concede QA de fase/navegador/E2E-08/09/M-01, feedback externo ni despliegue.
ADR-003 conserva su propuesta por invariantes pendientes de T15/T16; no se
promueve por este incremento parcial. No hay Jira activo ni publicación
externa de issues. Commit/push pendientes del control del índice exacto.
T15–T20 siguen íntegros y pendientes.

## Publicación T-14 e inicio formal de T-15

T14 publicado en `feature/plataforma-emailing`, commit
`2a4350871331614cf9873d0d3b563cf4a008940a`, padre
`defc14eb7ad40ef1b8c651a36d338a8365d75669`. Push exit0 y consulta independiente
de la referencia remota confirman ese SHA exacto. Autor y committer usan el
alias público Daycry y noreply; el árbol quedó limpio tras publicar.
El índice exacto incluía56 archivos y pasó privacidad sobre1.293 archivos,
sin hallazgos configurados, y whitespace exit0. La primera preparación del
índice detectó cuatro líneas vacías extra al final de informes/ledger; root
normalizó solo ese cierre, conservando originales privados y diagnóstico.
No cambió el código probado ni el contenido de los veredictos. Los checks
no constituyen una auditoría exhaustiva de PII o historial.

El borrador aislado de T15 se adelantó de defc a2a435 mediante cambio de base
sin fuerza, conservando byte a byte sus15 overlays y dejando intactos HEAD
e índice principal. El recibo privado conserva preimágenes y streams; no
ejecutó aplicación, SQL, red ni pruebas. Las pruebas puras históricas del
borrador no se promueven a aceptación de integración post-T14.

T15 pasa a **en-progreso**. Medidor formal iniciado el **2026-10-24
00:59:32Z**, después del cierre de revisión15. La preparación anterior no
tenía desglose propio: su consumo sigue desconocido. El propietario existente
acepta en exclusiva fuentes/app/SQL/emailing_testing e integración Cronicle;
root conserva ledger, medidores, revisión y Git. Configuración clásica,
sin TDD/worktree/subagentes activados; tier sonnet/medium de frontmatter y
runtime genérico heredado. La ampliación de Archivos declara bootstrap,
plantilla de entorno, override de montajes y guía de Cronicle necesarios.

El brief exige auth stateless y secreto dedicado, materialización con progreso,
dispatcher finito con más de un claim por disparo y presupuesto honesto,
replay/concurrencia, daemon Cronicle real y rutas nativa/Docker. Trabajos
recurrentes permanecen desactivados durante las pruebas destructivas de SQL.
Focal literal SchedulerTriggerTest, integración, full, censo, revisión y
publicación siguen pendientes. No se acredita QA de fase ni despliegue.
Contador formal **14/20**; T16–T20 mantienen íntegro su alcance.

## T-15 — primer focal real y diagnóstico separado

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `a85e2b8fe80eb7d991e8031e5fce663409bcd97f2f72665c7f150a8a89b42081`. El estado actual permanece en su tarea y evidencia de aceptacion.

## Revisión de dos lentes — intento 2: Fase 3 (T-15) — D-01 corregido

Fecha: 2026-10-24. Revisión global 17; lentes **A+B+C+D**, con contexto
fresco y sólo lectura. Revisores genéricos por indisponibilidad del tipo
dedicado, runtime heredado; `opus/high` del frontmatter no identifica el
modelo ejecutado. C se activa por configuración/Authorization; D por el
selector y la migración. El alcance previo contiene 33 paths y no presenta
fuera de alcance, avisos, información oculta ni exclusiones de usuario.

| Criterio | Veredicto | Evidencia |
|---|---|---|
| Trabajo durable sin afirmar correo finalizado | ✓ | `SchedulerRunResult`, fallo posterior a commit y `emails_completed: null`. |
| Autenticación dedicada y separación sesión/CSRF | ✓ | Middleware, rutas y pruebas de credenciales inválidas. |
| Entrada y acciones finitas | ✓ | JSON cerrado, acciones y límites del servidor, cliente fijo. |
| Replay y autorización bajo locks | ✓ | `DeliveryMaterializer` revalida antes del checkpoint; rollback y concurrencia. |
| Checkpoint por revisión y actualización incremental | ✓ | Indicador generado, backfill correlacionado, conservación de valores y pruebas down/up. |
| Exclusión del histórico completo | ✓ | Índice `campaign_materialization_due` y prueba poblada antes/después. |
| Cronicle compartido sin consumidor permanente | ✓ | Dos binds RO y 15 jobs reales, perfiles nativo/Docker. |
| Contratos anteriores y alcance | ✓ | Sin evidencia nueva para reabrir aprobados de revisión16; 33 paths revisados. |

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| D-01 | Important, previo | Cada tick recorría destinatarios de campañas completas | T-15 | **Corregido**: checkpoint durable y selector indexado por revisión pendiente | 365 campañas/73.000 pares conservados; claves por tick 73.366→2 y lecturas siguientes 73.000→0, tres repeticiones. |
| Nuevos | Ninguno | 0 Critical / 0 Important / 0 Minor | T-15 | Sin rebate ni deuda aceptada | Informes finales A, B, C y D coincidentes; límites declarados. |

Los informes íntegros y el contraste se publican en
[revisión17](testing/fase-3/review-17.md). Los cuatro revisores recorrieron
todos los hunks del diff de 2.866 líneas. La lectura de cuerpos históricos
intactos es parcial donde cada informe lo declara; no se infiere cobertura
semántica de hashes o verdes. La cápsula privada inmutable conserva 7.690
entradas, SHA `047d3fd6529eeb809688408e07718f874415756bcd7ce506f4ff5423f538c4ab`,
y patch SHA `ab3c39463a16f14286bdbdb505754e94411e6ed3a4ee17e6025191ba11f71bc1`.

Los nueve gates finales mantienen 217 fuentes del manifiesto histórico
`c074241dbca980962d401bac2957f1aea0330765044b7612bd40c439692f0177`.
El focal literal pasa **18/247**, outbox **20/299**, actividad **20/237**,
PHP puro **11/80**, Node nativo/Linux **7 por perfil**, Pint sobre 17 archivos
y full **232/2.793**. Se publican 25 archivos de recibos/streams/manifiesto
y los cinco informes/resumen, con privacidad previa sin hallazgos. No se
publican ZIP, helpers privados, credenciales ni respuestas autenticadas.

Los datos del daemon acreditan 16 migraciones por perfil. El campo secundario
`corrected_runtime_migration_hashes_checked` de dos auditorías conserva el
literal15; se registra la discrepancia y no se altera su historia. El verde
poblado `42e71e8f…` tampoco se reetiqueta como el full final: sólo cambia
`SchedulerTriggerTest.php` entre esas fotos. Ningún contador equivale a
capacidad o SLA. Un legado sin outbox requiere reconciliación controlada;
el presupuesto sigue siendo cooperativo. No se acredita SMTP ni bandeja.

El medidor de revisión `T-15-review17` cierra de **06:16:45 a 06:43:08 UTC**:
26 min 23 s de reloj. Tokens, coste y horas efectivas de IA permanecen
desconocidos; no se convierten a cero ni se imputan como tiempo de ejecución.
No hay Jira habilitado ni issues vinculados: no se publica ningún evento
externo. ADR-005 y GOT-005 se promueven al formato canónico de aceptadas;
ADR-003 sigue propuesta hasta su validación más amplia en T-16.

T-15 queda **completada como componente**, contador **15/20**, fase3 **6/7**.
La aceptación global de la fase sigue pendiente de T-16. El commit/push y el
inicio de T-16 se registrarán tras las comprobaciones de publicación e índice.

## Publicación T-15 e inicio formal de T-16

T-15 se publicó en `feature/plataforma-emailing`, commit
`0cf2bd5bc42e8090e42f2b20a67b3e143bf7929d`. El push terminó con código0 y una
lectura posterior de la referencia remota confirmó ese mismo SHA. Los 63
archivos del commit coinciden con el alcance incremental declarado. El índice
exacto pasó la comprobación de datos públicos: **1.346 archivos, cero
hallazgos**; autor y committer usan la identidad pública noreply del proyecto.
No se afirma una auditoría de todo el historial del repositorio.

Tras la revisión sólo cambiaron tres documentos de conocimiento para promover
su estado/índice. De las 217 fuentes de los gates, 215 siguen byte a byte y
las dos diferencias son ADR-005 e índice; GOT-005 estaba en la cápsula de
revisión, fuera de ese manifiesto de ejecución. Se contrastaron los tres
preimages para comprobar que sólo cambió el estado. Código y tests permanecen
idénticos; no se repite la suite por esta promoción de prosa ni se cambia el
SHA histórico de sus ejecuciones.

T-16 comienza con medidor propio y conserva **E2E-08, E2E-09 y M-01**, además
del comando literal no vacío `php artisan test --group=delivery`. Se reforzará
el caso de baja pública después de publicar en cola y antes de consumir,
se comprobará coherencia de fuentes de cada servicio nativo/Docker y se
prepararán revisión independiente y QA de concurrencia/fallos. La preparación
privada no constituye ejecución ni aceptación. M-01 sigue pendiente de
verificación humana sobre una superficie concreta. Contador **15/20**;
T-17–T-20 mantienen íntegro su alcance y dependencias.

#### T-16 — primeros controles de desarrollo y alcance del despliegue

El descubrimiento literal `php artisan test --group=delivery --list-tests`
terminó con código0 e identificó **137 pruebas distintas**; no equivale a
ejecutarlas. Se añadieron los atributos tras contrastar las nueve preimágenes
de tests aceptadas en T-15. Su inventario de214fuentes corresponde a esta
ejecución y no se reetiqueta como el217histórico de T-15.

Sobre la instantánea intermedia de219fuentes `9b601b6d…`, el focal de baja
pública terminó **1/34**. La evidencia archivada observa cola lista2 y
consumidores0 antes y después de la baja; tras los workers reales observa
cola vacía, fallos0 y A0/B1intentos para el mismo destinatario en cuentas
distintas. El mensaje de B se observó en Mailpit y se retiró por su ID propio.
Se preserva el fallo previo por endpoint DELETE singular y la recuperación
atribuida de ese mensaje, con verificación de remitente/destinatario.
Root contrastó recibos, hashes,657entradas ZIP de tres padres intermedios,
el método focal y la secuencia del artefacto; no es aceptación de fuentes
posteriores ni QA formal.

El gate Linux corregido terminó con padre0, nueve casos verdes y tres
diferencias esperadas contra el reportero de preparación. Se preservan
los diagnósticos de permisos/driver anteriores. Los18casos nativo/comparador
iniciales tampoco prueban las ampliaciones posteriores: la regresión de
junction Windows y el productor independiente se verifican por separado.
Una tanda posterior descubrió seis casos duplicados por un import de clase;
no se presentan28ejecuciones como28comportamientos distintos.

Se amplía el campo Archivos de T-16 con `.dockerignore`: excluir las cachés
bootstrap del host evita introducir artefactos Windows en la imagen Linux.
El primer intento del harness de perfiles terminó antes del build al faltar
`-f docker/Dockerfile`; se conservó su identidad y el asentamiento sin
servicios propios arrancados. La repetición y el despliegue coherente de
cuatro servicios/Apache nativo siguen en validación. No hay verde formal de
QA ni aceptación humana M-01; el contador permanece **15/20**.

## Ampliación solicitada: MCP y acceso con Google

El 24-10-2026 el usuario solicitó un servidor MCP para que agentes (Claude, Codex y OpenCode) utilicen la aplicación, y acceso con Google tanto en la web como al autorizar el MCP. Esta ampliación forma parte del objetivo global y sigue el workflow completo de custom-agents en [la iniciativa MCP y Google](../2026-10-24-mcp-google/spec.md).

La especificación, evaluación y [plan de 14 tareas](../2026-10-24-mcp-google/tasks.md) están preparados; todavía no hay implementación ni pruebas de compatibilidad. No modifica la evidencia, los criterios ni el estado de T-16. Las 20 tareas originales mantienen su alcance. La ampliación consume los contratos y servicios de T-17/T-18 y su aceptación precede al cierre global de T-19/T-20, sin dependencia inversa desde T-18. No se declara completado el objetivo por terminar sólo las 20 tareas originales.

Google identifica al usuario; las cuentas, membresías Teammates y permisos actuales autorizan las operaciones. El agente recibirá una autorización delegada para una cuenta concreta, revocable y auditada. No se usará el token de Google como credencial del recurso MCP. Los envíos MCP reutilizarán los servicios y las colas de la aplicación, con Mailpit/log mientras siga vigente el laboratorio.

La preferencia de acceso Google (cuentas autorizadas de Gmail/Workspace o sólo un dominio Workspace) está pendiente. La propuesta avanza bajo el supuesto explícito de cuentas autorizadas de ambos tipos con restricción opcional por dominio; no concede permisos automáticamente a ninguna identidad Google.

## QA de T-16 — tres intentos y diagnóstico acotado

Fecha: 24-10-2026. La tercera ejecución de navegador terminó con **6 casos
pasados, 3 fallidos y 1 intermitente**, sin omitidos ni interrumpidos. El
`qa-gate.py` ejecutado sobre el JSON original devuelve **NO-VERDE, exit 1**;
no se justificó el intermitente para obtener otro veredicto. El resultado, las
capturas y sus límites están en el [informe QA](testing/fase-3/t16-attempt-1/report.md)
y su [PDF](testing/fase-3/t16-attempt-1/report.pdf). Las dos ejecuciones
anteriores, interrumpidas tras timeout del selector, conservan su identidad.
No se ha iniciado una cuarta ejecución.

Se confirmaron dos defectos de producto: **P-01**, mensajes que muestran claves
de traducción como `validation.before_or_equal` y `validation.accepted`; y
**P-02**, el hook de fecha que borra la confirmación explícita recibida en el
mismo batch Livewire al programar una campaña. La traza observa
`confirmed=true` en el envío y `confirmed=false` en la respuesta. Los reintentos
del caso quedaron esperando el nombre antiguo del fixture ya renombrado; no
equivalen a tres reproducciones independientes de P-02.

La carrera **D-02** pertenece al driver: un GET posterior cancela el POST de
cambio de cuenta antes de terminar la navegación. Explica dos fallos y el
intermitente de permisos; no prueba acceso indebido de la cuenta B. El selector
por etiqueta envolvente se diagnosticó y corrigió antes del tercer intento.
La hipótesis inicial de presupuesto de arranque insuficiente quedó descartada
como explicación única del selector sin coincidencias.

Pasaron nuevos protocolos reales de baja encolada, token alterado/replay,
aislamiento de destinatario entre cuentas y revocación inmediata del lector.
Se obtuvieron un fallo SMTP y un resultado ambiguo mediante relay/consumidores
reales, y tres trabajos finitos nuevos del daemon Cronicle. La carrera del
driver impidió completar el detalle ambiguo en navegador. P-02 impidió alcanzar
la materialización futura y el CSV del caso programado. El grupo histórico
**137/1972** conserva su resultado de backend; no se presenta como una nueva
ejecución de QA. Las 232 entradas no incluyen `lang/`: su coincidencia no
acredita retrospectivamente las traducciones.

El propietario de QA liberó explícitamente las fuentes, `emailing_testing` y
el runtime tras cerrar el mismo handle retenido con exit 0, sin cambios de
fuentes ni errores de limpieza. El [recibo de recursos](testing/fase-3/t16-attempt-1/raw/resource-settlement.json)
registra cuatro namespaces propios sin filas atribuibles, sus doce colas y
cuatro exchanges ausentes, cinco contenedores propios ausentes y dos tags de
imagen retirados. Mailpit no conserva mensajes atribuibles a esos fixtures.
Apache se cerró intencionalmente por su handle propio con exit 1; no se
reetiqueta como exit 0 ni se hizo limpieza global de infraestructura.

La pasada acotada de `custom-agents:debug-root-cause` dejó propuestas concretas
sin aplicar: preservar la confirmación explícita dentro del batch, mantener su
invalidación ante cambios posteriores, traducir fechas/confirmación, incluir
`lang/` en snapshots e inventarios/montajes y esperar el cambio de cuenta del
driver. Se prepararon regresiones por batch en ambos órdenes y controles de
confirmación falsa/composición pendiente. Solo se comprobó su sintaxis;
**no se han ejecutado ni aplicado los fixes**. Se pidió al usuario la decisión
«seguir con el fix» que exige el flujo tras tres intentos; sigue pendiente.
La propuesta privada no sustituye el informe ni se publica como prueba verde.

T-16 continúa **en-progreso**, con revisión del incremento anterior conservada,
QA sin aprobar y **M-01 pendiente de una persona** sobre vistas corregidas.
No se cierran T-17–T-20 ni la ampliación Google/MCP. El contador sigue **15/20**
y **0/14** respectivamente.

## T-16 — reparación autorizada tras diagnóstico (fix1)

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `6fc810695fa0366782bb23a976fcc599023b8e03463e4dd9468c564d409bd109`. El estado actual permanece en su tarea y evidencia de aceptacion.

## Revisión de dos lentes — intento 19: Fase 3 (T-16) — fix1 sin gaps

Intento acotado **2 de 3 de T-16**, numeración global 19. Lentes A+B+C+D de
contexto fresco mediante fallback genérico; runtime heredado, sin atribuir el
modelo del frontmatter. Las cuatro recorrieron **87/87 archivos y 7.365
líneas**. Veredictos: **0 Critical, 0 Important y 0 Minor**. Se entregó el
dictamen completo de revisión 18 y no se reabrieron aprobados sin evidencia
nueva. No hubo correcciones ni rebates nuevos.

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| — | — | Sin gaps pendientes | T-16 | No requerida | [Dictamen 19](testing/fase-3/review/attempt-19.md): requisitos, corrección, seguridad y rendimiento |

Scope-check **exit 0**, sin fuera de alcance, avisos, info o exclusiones de
usuario. C activada por `.dockerignore`; D por SchedulerTriggerTest y
WorkerRecoveryTest. No hay constitución, cambios interop ni contrato OpenAPI
tocado. No se informa porcentaje de código cubierto sin medición.

Root verificó las **3.375 entradas** de la cápsula inmutable, sello
`783dff7a2e93ad0bb49d7a632d787e67ecd5b5a0dfe1cbbb22203e74d9b7d6e7`,
y las **235 fuentes ejecutadas**, incluidas tres traducciones de `lang/`.
Admitió 20 runs con ZIP, recibos, salidas y atribuciones: 13 aprobados y siete
diagnósticos/observaciones con su estado original. El grupo literal final
**139/2009**, discovery real **139 IDs/nueve clases**, perfiles y auditorías
comparten manifiesto; la regresión focal GREEN mantiene su relación histórica.

| Criterio de revisión | Veredicto |
|---|---|
| T-01–T-15 cerradas y verificaciones registradas | ✓ conservado, sin suplir evidencia mediante ejecuciones del revisor |
| Ventanas de fallo y reglas de recuperación | ✓ backend; aceptación integral de T-16 pendiente |
| Durabilidad, ambigüedad y ausencia de promesa exactly-once SMTP | ✓ en código/tests/documentación revisados |
| P-02: batch explícito, ambos órdenes, falso, fecha posterior y composición | ✓ regresiones causales y lectura del guard |
| P-01: validación española y recuperación en la misma instancia | ✓ actividad y consentimiento |
| Procedencia lang y perfiles/inventarios | ✓ 25 controles nativos, 10 Linux y 45 observaciones runtime |
| D-02 del driver | ✓ regresión aislada A→B→A; no equivale a QA de aplicación |
| E2E-08/E2E-09 nuevos y M-01 | Pendientes correctamente declarados |

**GOT-007 promovida** a aceptada por revisión de dos lentes, 24-10-2026,
intento 19; índice actualizado. **ADR-006 permanece propuesta**, sin atribuir
seguridad OAuth/MCP ni compatibilidad con clientes reales. Estos cambios de
estado y el informe son documentación posterior al dictamen, no código nuevo.

Medidor de revisión cerrado: **15:07:47Z–15:19:09Z**, sin solape con fix1 ni
QA. Tokens reales, euros y horas IA permanecen `null` por transcripciones no
disponibles; el reloj no se convierte en consumo. Jira no está activado ni el
plan volcado; no se publican operaciones externas.

El siguiente paso es el **intento 1/3 del segundo ciclo QA** autorizado por
«adelante», con runtime nuevo, datos nuevos por escenario/retry, guardrails,
daemon real y gate sobre resultados originales. El QA NO-VERDE anterior
permanece intacto. **T-16 sigue en progreso, 15/20; MCP 0/14**. M-01 necesita
juicio de una persona; esta revisión no lo aprueba ni cierra el roadmap.

## Cierre técnico de T-16 — QA ciclo 2, intento 1 — 2026-10-24

**T-16 completada técnicamente, 16/20; fase 3, 7/7.** La revisión 19
A+B+C+D sin gaps coincide con el nuevo gate real **VERDE: 10/10 casos**,
cero fallos, flaky, omitidos o interrumpidos. Retries configurados 2, usados 0.
El [informe](testing/fase-3/t16-attempt-2/report.md), su
[PDF](testing/fase-3/t16-attempt-2/report.pdf) y el
[índice de 33 capturas](testing/fase-3/t16-attempt-2/screenshots.md) conservan
resultados, límites, protocolos y checklist manual.

El navegador comenzó a las **15:37:50.087Z**, duración **551,92 s**.
Root comprobó JSON original y proyección, títulos, estados, retries, duraciones,
contadores, salida terminal 0 y siete helpers congelados. Original SHA256
`172733ed04fa9a1ec6d5313c969ab0060afc1229b00fd326b06ac6bab6e03a98`;
proyección `39efcaab3aae649653717421e718a2205d0ffb717f2d30e89cdae1c987a7c66e`.
El gate se ejecutó sobre el original, sin justificaciones ni cambio de umbral.
Trazas y originales con valores privados siguen ignorados. El NO-VERDE anterior
se mantiene íntegro con su identidad y sus tres intentos.

Se acreditan los seis recorridos de lectura nativo/Docker, rechazo real,
aceptación SMTP incierta sin reenvío, baja tras encolar con cuenta B independiente,
programación futura con UTC real, CSV seguro y revocación siguiente 403. El primer
submit de autorización conservó `confirmed=true` y `effectiveUtc` juntos y recibió
200. Antes de la fecha hubo cero entregas; después, una entrega pendiente y una
aceptación SMTP real con un intento. No se sembraron estados ni se atrasaron fechas.

Cronicle ejecutó **tres jobs nuevos reales** antes de los casos mutables:
autenticación ausente 401 y válida/repetida `created=0`. La evidencia es finita
y de trabajo vacío, sin atribuir materialización nueva al daemon. Eventos y
configuración propios retirados, definiciones ajenas conservadas. El grupo
backend **139/2009** no se repitió: root verificó 17 ventanas mapeadas a sus IDs
reales y hashes de fuente, y las **235 entradas ejecutadas** siguen iguales.

Hold **18561**, navegador **66613**, daemon **45423**, captura suplementaria
**57127** y asentamiento **12383** terminaron con código 0. Seis namespaces
verificados: SQL propio a cero, 24 comprobaciones de topología ausente, journals
ausentes y Mailpit propio vacío. Cinco contenedores ausentes y dos tags del QA
eliminados. Los dos tags de fix1 retenidos como artefactos se distinguen de esa
limpieza. Apache conserva su salida 1 por parada solicitada; no se reetiqueta.

El PDF final tiene **11 páginas, ocho imágenes y cero bloques fuera del área**;
se inspeccionaron páginas 1, 2 y 11. Su HTML se regeneró desde el MD actual con
bytes idénticos y se verificaron ocho checks de contenido. El productor escribió
el PDF antes de fallar con `EBUSY` al retirar su perfil; la conversión posterior
falló `Target closed` sin reemplazarlo. Ambos exits **1** y sus diagnósticos se
conservan en el [recibo](testing/fase-3/t16-attempt-2/execution-1/raw/pdf-generation-receipt.json).
Los dos perfiles propios quedaron retirados y todos sus procesos terminales.
Se acepta el artefacto validado, sin inventar éxito de esos comandos.

Hash MD `055d7e56ad2b45fa280c9ca34dcee7ff0663b5931f22a31bff72662f0b2a45db`;
PDF `2cf487dcc56f055ef79188dfc387ab333c4e0960bc3c4f4ad3cfea0691c07b7d`.
Admisión independiente de root:
`storage/phase3-t16/root-cycle2-handoff-admission.json`. QA liberó explícitamente
fuentes, `emailing_testing` y runtime a las **16:06:02Z**; no abrió medidor propio.
Tokens, coste y horas IA no medidos permanecen `null`.

**M-01 sigue pendiente de una persona**, como revisión compartida de T-04/T-09/
T-16/T-20 y demás tareas relacionadas. Las cuatro capturas suplementarias muestran
cuenta, resumen y permisos ya asentados; no aprueban claridad visual automáticamente.
Este cierre técnico satisface las dos aceptaciones y la verificación de T-16;
no marca M-01 ni el plan completo como aprobados. T-17–T-20 y MCP **0/14** conservan
todo su alcance. Siguiente tarea: contrato T-17 antes de endpoints T-18.


## Revisión de dos lentes — intento 20 — T-17, pasada 1 de 3 — 2026-10-24

Puertas de alcance y ledger: exit 0, sin avisos ni exclusiones de usuario.
Selector C/D desactivado por prosa. Lentes A/B frescas y paralelas mediante
fallback genérico (reviewer nombrado no disponible), con 95 hashes verificados.
A: requisitos y evidencia conformes al contrato, cero gaps. B: Important B-01,
`docs/api/openapi.yaml:529`, discriminador inline/template sin mapping explícito
a `InlineContent`/`TemplateContent`. Root acepta el defecto tras contrastarlo con
OpenAPI 3.0.3; no lo descartan el lint ni 107 ejemplos JSON.

La tabla completa y su evidencia están en [revisión 20](testing/fase-4/review/attempt-20.md).
T-17 permanece en progreso, ADR-007 propuesta. Corregir B-01 y relanzar revisión
(pasada 2 de 3), conservando criterios aprobados y este veredicto. No se cierran
CA-19/20 ni E2E-10 de runtime. Tokens, coste y horas IA reales desconocidos.

## Revisión de dos lentes — intento 21 — cierre técnico de T-17 — 2026-10-24

Pasada 2 de 3 de T-17. Las lentes A/B frescas conservaron la tabla completa de
revisión 20 y verificaron los 81 hashes del nuevo input. Alcance exit 0, sin
avisos ni exclusiones de usuario; C/D desactivadas por prosa. B-01 corregido:
únicamente tres líneas de mapping, payloads intactos. Comprobaciones causales
RED 0/2 exit 1 y GREEN final 2/2 exit 0, con rechazo del mapping cruzado. El
diagnóstico intermedio se conserva sin convertirlo en verde.

Lint y diff independientes: exit 0, cero errores o rompedores. Esquema oficial
y 107/107 ejemplos ligados al contrato SHA-256
`e56b967b1a7796113460ef370c0a4a0a63d50539f9b113e8bc45d5d314836c5f`.
Root admitió los 50 archivos de evidencia del fix y sus 12 recibos, además del
handoff original. [Revisión 21](testing/fase-4/review/attempt-21.md): cero
Critical/Important/Minor pendientes en ambas lentes. ADR-007 se promueve como
decisión revisada. Métricas reales de tokens, coste y horas IA desconocidas.

T-17 queda completada como contrato validado y aprobado antes de endpoints:
**17/20 tareas principales**. La QA documental no acredita HTTP, SQL, SMTP,
concurrencia ni permisos efectivos de la futura API: CA-19/20 y E2E-10 siguen
pendientes de T-18 y del cierre de fase 4. M-01 humana, M-02 y las 14 tareas
Google/MCP siguen pendientes. Próximo paso: implementar T-18 contra este
contrato y conservar el pipeline común de entrega.


## Implementación T-18-fix1 — 2026-10-25 (sellos UTC)

Narrativa historica de una tarea ya completada, conservada integra en [execution-history.md](execution-history.md). SHA-256 del bloque LF: `fa67aca6f9664afd7e5a92656fec268248cf99d5b5fadf41900bd355942586ae`. El estado actual permanece en su tarea y evidencia de aceptacion.

## Revisión de dos lentes — intento 23: Fase 4 (T-18) — correcciones verificadas, sin gaps pendientes

Revisión global23, segundo intento de tres para T-18. Lentes **A+B+C+D** con
contexto fresco y fallback genérico heredado: Agent(reviewer) no disponible;
la configuración Opus/high no acredita ejecución de ese modelo. Las cuatro
lentes verificaron prompt, sello y 765 hashes antes/después, ocho archivos
corregidos y sus tests. El cambio de finales de línea del ledger se comparó
normalizado sin alterar la evidencia original. No se reabren criterios previos
sin evidencia nueva. Ningún revisor ejecutó aplicación, SQL, broker, Docker
ni navegador.

Cápsula inmutable `storage/phase4-t18-review23`, manifiesto SHA-256
`c82c60a4949250bc7e130e8993d7698d9ba698a9bfcfc338bd88b283ba156a87`. Raíz verificó sus 765 hashes y las 278 fuentes actuales antes de
esta anotación. Base `dab4b84c7750f79d859762f991b79587f16a3d91`. Puertas previas: alcance, selección,
tier y ledger exit0; 50 entradas de estado, 49 archivos reales y README sin
cambio de contenido normalizado; sin exclusiones, avisos de alcance ni archivos
fuera de alcance. Se conservan cuatro avisos históricos de ledger.

| Tarea/criterio | Veredicto | Evidencia |
| --- | --- | --- |
| T18: repetición concurrente crea un trabajo; credencial inválida/revocada/sin scope rechazada | ✓ conservado | `app/Services/SendingApiService.php:23`, `AccountKeyService.php:36`; `tests/Concerns/ExercisesSendingApiProcesses.php:201`, `ExercisesSendingApi.php:275`. |
| T18: Read-only/Restricted no elevan permisos; cuenta derivada de credencial | ✓ conservado | `app/Services/AccountKeyService.php:106`,`:163`; `tests/Concerns/ExercisesSendingApi.php:176`, `tests/Feature/SendingApiTest.php:161`. |
| T18: endpoints/middleware/políticas/UI | ✓ | `routes/api.php:7`, `app/Http/Middleware/AccountBearer.php:15`, `resources/views/api-keys.blade.php:1`. Policy:20 conserva cuenta/withTrashed/bloqueo; bound/consentimiento:24. Regresiones direct/bound/replay en traitReviewFixes:94,:146,:202. |
| T18: contrato/documentación/ejemplos | ✓ actualizado | OpenAPI:1104, `docs/api/implementacion.md:1`; `app/Support/StrictApiJson.php:58`,`:65` conserva propiedades hasta conversión completa; SendingApiInput:12 rechaza por esquema cerrado. HTTP en traitReviewFixes:20; tipos/números/NUL/duplicados en ApiJsonNumberTest:50. |
| CA01: autenticación/CSRF | ✓ conservado | `app/Services/AccountKeyService.php:24`, `resources/views/api-keys.blade.php:19`, traitExercisesSendingApi:176. |
| CA12: secretos/errores/autorización de datos | ✓ | Evidencia anterior en AccountKeyService:100, ApiError:15, SendingApiTest:142. Nuevo rechazoNUL sin trabajo parcial en traitReviewFixes:49. |
| CA13: cuenta/scopes | ✓ | Evidencia anterior SendingApiService:82, traitExercisesSendingApi:124. Proyección:88 mantiene filtros cuenta/petición y relaciónID; boundajeno en traitReviewFixes:146. |
| CA19: idempotencia/scopes/revocación | ✓ conservado | SendingApiService:23; traitExercisesSendingApiProcesses:201,:235. |
| CA20: concesión/revocación sin borrar trabajo | ✓ conservado | AccountKeyService:106,:133; SendingApiTest:161, traitExercisesSendingApiProcesses:235. |
| Alcance | ✓ | `docs/roadmap/2026-10-21-plataforma-emailing/improvement-plan.md:160`, `tasks.md:833`,`:836`; scope23receipt exit0 sin exclusiones/avisos/fuera de alcance. |
| Tests/verificación ejecutada | ✓ | `tasks.md:5142`: literal28/767, unit3/244, focal19/282, todos0. Stdout/recibos y262 archivos de ZIP/manifiesto contrastados contra source/. Aserciones nuevas leídas; verde no sustituye análisis. |
| Constitución | N/A | No existe docs/CONSTITUTION.md. |
| E2/E3 generados/interoperabilidad | N/A | No commands/agents/hooks. |
| Cobertura porcentual | N/A | Sin configuración; no se inventa porcentaje. |

| # | Grado | Gap | Tarea | Corrección | Evidencia |
| --- | --- | --- | --- | --- | --- |
| AB-01 | Important histórico, resuelto | Propiedad JSON válida iniciada por NUL producía503 | T-18 | Corregido: acumular propiedades antes de convertir a objeto; el esquema cerrado conserva su rechazo | `app/Support/StrictApiJson.php:58`, `tests/Concerns/ExercisesSendingApiReviewFixes.php:20`, `tests/Unit/ApiJsonNumberTest.php:50`; seis422, dos400, cero trabajo parcial |
| D-01 | Important histórico, resuelto | Status hidrataba cuerpos completos para utilizar email | T-18 | Corregido: proyección id/email, sin cambiar respuesta | `app/Services/SendingApiService.php:88`, traitReviewFixes:57; strings hidratados40.011.392→5.792 bytes, respuesta17.115 bytes idéntica |
| D-02 | Important histórico, resuelto | Búsqueda histórica repetía escaneos dentro del bucle | T-18 | Corregido: índice no único(account_id,email) y consultas email/PK separadas bajo el mutex; merge por PK | `app/Services/ApiRecipientPolicy.php:20`, migración000014:13, traitReviewFixes:94,:146,:202; directos1.000.301→301 lecturas, seis escenarios bound5–7; probe índice+OR permanece rojo |

**Recepción y fusión:** los tres gaps previos están corregidos, sin rebate ni
deuda aceptada. No hay gaps nuevos/reabiertos. Total pendiente: **0 Critical,
0 Important, 0 Minor**. Root ya admitió 11 cápsulas conservando seis rojos y
cinco verdes: literal API28/767, unit3/244, focal19/282 y runtime/settlement0,
con manifiesto final común de262 fuentes. Las métricas están ligadas a sus
fixtures, recibos y fuentes; no se afirma latencia, capacidad máxima u OOM.
Delivery24 sigue siendo regresión anterior al fix, no evidencia del fix actual.

GOT-008/009/010/011 se promueven con traza de esta revisión y su índice en el
mismo cambio; solo cambia metadata de aceptación. **T-18 sigue en-progreso**:
esta revisión no acredita E2E-10 ni QA. Principal17/20 y Google/MCP0/14
conservan todo su alcance; M01/M02 siguen pendientes humanas. Jira no está
activado; no se publica nada externo.

Worklog `[revisión]`: medidor review23 cerrado antes de QA. Tokens, coste y
horas IA reales siguen desconocidos; no se convierten minutos de reloj en
horas IA. Recibos privados de puertas, prompts, veredictos completos y cierre
en `storage/phase4-t18-review23-control/`, ignorado por Git.

Siguiente paso: QA formal E2E-10 en Laragon y Docker con fuentes calificadas,
guardrail local, evidencia sin secretos, qa-gate real e informe/PDF. Solo tras
QA se podrá cerrar T-18 y realizar commit/push del incremento validado.


## Cierre técnico de T-18 — API y QA de fase aceptados, 2026-10-25

T-18 se marca **completada** tras la revisión 23 A+B+C+D sin gaps pendientes
y la admisión independiente del QA de fase. Principal **18/20**; fase 4 **2/4**,
todavía en-progreso. Google/MCP **0/14**, T-19 y T-20 conservan todo su alcance.
No se cierra la iniciativa ni se equipara el 90% principal al progreso global.

E2E-10: [informe](testing/fase-4/t18-api/report.md) y
[PDF](testing/fase-4/t18-api/report.pdf). Run5 ejecutó dos recorridos completos:
Laragon 1/1 y Docker 1/1, 0 fallos, 0 flaky, 0 omitidos, 0 interrumpidos y 0
retries. Q10-01–11 se verificaron en ambos perfiles y Q10-12 se complementó con
backend actual. Se conservan los rojos anteriores de harness y publicación,
sin ocultarlos ni atribuirlos a defectos de aplicación. Doce capturas muestran
GET reales posteriores a la creación, sin bearer ni manipulación del DOM.

Backend actual: focal literal API **28 pruebas/767 aserciones**, unitario de
números **3/244**, focal migración/contactos **19/282** y runtime/settlement
exit0. Las cápsulas finales comparten 262 fuentes, manifiesto SHA-256
`6ecda20e3bc2055f7a101ec981d64c08fe4555c3159b95f28edc094ea297b2fc`.
Delivery24 **164/3071** conserva su identidad anterior a los tres fixes;
no se presenta como regresión ejecutada después de ellos.

Raíz verificó las fuentes actuales y el ZIP, 69 artefactos runtime, 54 recibos,
106 hashes de streams, inventarios de runtime y build calificado, cuatro
resultados Playwright completos y su única sustitución de prefijo de ruta
personal. El gate oficial sobre el resultado publicado termina exit0.
La corrección documental UTF8 del threshold conserva el objeto completo del
gate. Informe y PDF finales se verifican por hash, texto y renderizado.
La corrección no ejecutó de nuevo aplicación, SQL, broker ni navegador.

Doce fixtures y sus mensajes Mailpit quedaron liquidados; ocho sondas de
topología propia acreditan ausencia, los dos volúmenes UUID se retiraron
individualmente y los procesos propios terminaron. La base exclusiva de QA
quedó con solo `migrations`, sin migraciones aplicadas; esto no equivale a
afirmar cero filas de tablas ausentes. No hay prueba de migración/despliegue
de la base operativa. SMTP externo sigue deshabilitado.

CA-19/20 se marcan satisfechos técnicamente por esta implementación y pruebas;
las revisiones humanas **M-01/M-02 siguen pendientes** para el cierre global.
Tokens, coste y horas IA reales desconocidos sin telemetría. No hay integración
Jira/Confluence activada ni mensajes externos. El siguiente bloque es Google/MCP
antes de las pruebas cruzadas, carga, restauración y cierre de T-19/T-20.

## Historial archivado

- 2026-11-03 · «Revisión de dos lentes — intento 1: Fase 1 (T-01, T-02, T-03) — correcciones necesarias» → [execution-history.md](execution-history.md) · SHA-256 `666aca68535d3c1d6fcf6088e042ae27c65bb797cc69861d3f1be4517fc2da23`
- 2026-11-03 · «Revisión de dos lentes — intento 3: corrección del harness» → [execution-history.md](execution-history.md) · SHA-256 `bcbb368a82e7ab7fbb050cc585799cbdbac2e31ecd2e6191da00bc8b784d63ac`
- 2026-11-03 · «Revisión de dos lentes — intento 4: incremento FB-01 de fase 1» → [execution-history.md](execution-history.md) · SHA-256 `d82dda0a1afec42ecb6823dca1bba64e2329c76726f81e44ec240899f0e82f1a`
- 2026-11-03 · «Revisión de dos lentes — intento 1: Fase 2 (T-05, T-06, T-07, T-08) — correcciones necesarias» → [execution-history.md](execution-history.md) · SHA-256 `7447e3ba9fd9fa9a273b09dc388272ccec24100924e4d3549869950da24d7078`
- 2026-11-03 · «Revisión de dos lentes — intento 3: Fase 2 (T-09) — corregir teardown del harness» → [execution-history.md](execution-history.md) · SHA-256 `186896afd148cbbd0675c335ee319686f11d724d2ec85721c1b345910a7a8d34`
- 2026-11-03 · «Revisión de dos lentes — intento 4: Fase 2 (T-09) — aislar errores del teardown» → [execution-history.md](execution-history.md) · SHA-256 `4b6bd742875ca9908424b8c3ae034a11e89bdb590e8ce7ba034643fbddab8579`
- 2026-11-03 · «Revisión de dos lentes — intento 5: Fase 2 (T-09) — B-04 aprobado» → [execution-history.md](execution-history.md) · SHA-256 `4f5f618ad89e0bdbf8445847458a01e603ba3413d3ac7e4f32462542f75310d2`
- 2026-11-03 · «Revisión de dos lentes — intento 7: Fase 3 (T-10) — B-01 menor» → [execution-history.md](execution-history.md) · SHA-256 `163b6eac817a48ea8cb7f42e73ca00f3105728505b283cdd03bbfb9103e17ec7`
- 2026-11-03 · «Revisión de dos lentes — intento 8: Fase 3 (T-10) — B-01 aprobado» → [execution-history.md](execution-history.md) · SHA-256 `53f993c63f5ad41394ad7cc2e230b2aec56e0bda85e19159862ebc975e1131a7`
- 2026-11-03 · «Revisión de dos lentes — intento 9: Fase 3 (T-11) — recuperar selecciones desaparecidas» → [execution-history.md](execution-history.md) · SHA-256 `b0a26ebcf2e92b0a9722b1e4eb21d3315211ddf91cc9295c40544858ceee46e5`
- 2026-11-03 · «Revisión de dos lentes — intento 10: Fase 3 (T-11) — componente aprobado, integración pendiente» → [execution-history.md](execution-history.md) · SHA-256 `b4ea5b09dbfed5f88d2c0b4ea946697971a2242c69458bb000c3e7758e9af794`
- 2026-11-03 · «Revisión de dos lentes — intento 11: Fase 3 (T-12) — corregir selección global» → [execution-history.md](execution-history.md) · SHA-256 `7b06d4f887839baf9397e5fd1ba4c7776be7d759cf18a7d59f19046594f73a89`
- 2026-11-03 · «Revisión de dos lentes — intento 12: Fase 3 (T-12) — D-01 corregido, componente aprobado» → [execution-history.md](execution-history.md) · SHA-256 `18c0822758047954cdb18f4026941bfc3d740dede9fe5ed41e714724a1027842`
- 2026-11-03 · «Revisión de dos lentes — intento 14: Fase 3 (T-14) — una corrección requerida» → [execution-history.md](execution-history.md) · SHA-256 `ffbcb94de7cfe6fce791370107c34cb9a2960c3132f905c6c7f86458f4c7d67a`
- 2026-11-03 · «Revisión de dos lentes — intento 1: Fase 3 (T-15) — recorrido histórico por corregir» → [execution-history.md](execution-history.md) · SHA-256 `b51f2ca052fa985a430ed50ce5f7b12ac19e38df20f6b9199367ca48861e4960`
- 2026-11-03 · «Revisión de dos lentes — intento 18: Fase 3 (T-16) — sin gaps pendientes» → [execution-history.md](execution-history.md) · SHA-256 `d8d893a3f1b1004a212a2b591ee29f9388e799612f24d9f2c3fe7186dd113b34`
- 2026-11-03 · «Revisión de dos lentes — intento 22: Fase 4 (T-18) — tres correcciones necesarias» → [execution-history.md](execution-history.md) · SHA-256 `e09d7a32af523e7310684091296f04c39ce97fd57973a4e18ce0c64107ac4f5d`

## Revisión de dos lentes — intento 1: Preparación T-19 — correcciones por revalidar

Fecha: 2026-11-03. Se revisa únicamente la preparación de herramientas de carga
y su documentación, no el cierre de T-19. Tres revisores nativos de contexto
fresco recorrieron los 26 ficheros del patch
`65d6496c938eda5c583af46ad9f61a66f3b59451d22915a3c0a09fd6bba76907`.
Puerta de alcance: exit 0, sin exclusiones, avisos ni criterios alterados.
Lentes A+B+D; C no disparada. El selector activó D por batch, worker y esperas
de supervisión. Perfil `fresh_parallel`; tier del paquete no resolvió `agents/`,
por lo que se utilizaron roles nativos reviewer con modelo heredado.

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| A-01 | Minor | La nota contaba once rechazos de descriptor en lugar de diez rechazos y un dry-run correcto. | T-19 | Corregida la nota; pendiente de revalidación del texto. | `check_descriptors.py`; once comprobaciones, sin SQL/broker. |
| B-01 | Important | `regression.py` podía afirmar limpieza tras cerrar sólo el Job nativo, dejando contenedores del daemon activos al interrumpirse PHPUnit. | T-19 | Atribución UUID opcional en ambos launchers Docker; ausencia previa, verificación de etiqueta/ID/nombre, parada y ausencia final limitada a sus contenedores. La carga exige comprobación del daemon. Pendiente de revisión2 e integración. | Test sintético focal falló primero, 1 caso/1 fallo; guardas y supervisor pasan después. No se confunde Job Object con Docker. |
| ROOT-01 | Important | El inventario no rechazaba una junction antecesora antes de intentar hash de un archivo fuera del checkout. | T-19 | Se comprueba cada componente, contención y hardlinks antes de leer. Pendiente de revisión2. | Test con junction Windows propia y cuerpo sustituido por un doble; no se leen datos privados externos. |

La tabla A conserva pendientes los tres criterios de aceptación global de T-19
y sus ensayos no ejecutados. D no encontró defectos: 20 tests puros y una
reconciliación sintética máxima, sin SQL ni carga real. B reprodujo el recibo
incorrecto con dobles en memoria y siguió ambos launchers/teardowns existentes.
Sus informes se recibieron como mensajes de agentes; cualquier representación
escrita por el coordinador es una proyección de esos mensajes, no una salida
original creada por el revisor. Tras las correcciones pasan 84 tests Python y
diez tests PHP/33 aserciones. Una
[sonda posterior acotada](../../desarrollo/evidencias/t19-daemon-20261103/native-result.public.json)
reprodujo con un contenedor PHP sin red su supervivencia al cierre nativo y
verificó la limpieza atribuida. El primer intento falló sólo al guardar su
índice auxiliar después de limpiar; el segundo terminó en cero, con fuentes
estables y sin SQL/broker. No acredita ejecución de la suite completa ni carga
integrada. No se acepta T-19 ni se promueve conocimiento por esta revisión.

## Revisión de dos lentes — intento 2: Preparación T-19 — sin gaps pendientes

Fecha: 2026-11-03. Cuatro revisores nativos con contexto fresco reevaluaron
únicamente correcciones y novedades, con la tabla completa del intento1. Patch
`dc75e2ff1312158ea30632b0512ef970556cc51df25a6161b4eaf1b6025faf83`,
36 archivos y sus hashes comprobados. Alcance exit0 sin exclusiones, avisos ni
criterios alterados. Lentes A+B+C+D: C activada por DockerScope y D por los
launchers, batch y sondeo. D se despachó al liberarse un hueco de concurrencia;
conservó contexto fresco. La proyección privada inicial de la cápsula arrastraba
la lista A/B/D del intento1; se conservó y se añadió una corrección explícita de
despacho, sin modificar patch, snapshot ni recibos de comandos.

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| A-01 | Minor | Recuento incorrecto de rechazos. | T-19 | Corregido y revalidado por A. | Diez rechazos y un dry-run; salida11/exit0. |
| B-01 | Important | Limpieza nativa no cubría contenedores del daemon. | T-19 | Corregido y revalidado por A/B/C. | Etiqueta UUID en ambos launchers; ausencia previa, identidad y parada por ID; prueba de orden Job→daemon; carga rechaza ausencia de comprobación. Sonda real limitada y sus dos intentos conservados. |
| ROOT-01 | Important | Junction antecesora podía redirigir el inventario. | T-19 | Corregido y revalidado por A/B/C. | Componentes, contención y hardlinks antes del hash; test Windows con cuerpo doblado, ejecutado por A. |

| Criterio de preparación | A | B | C | D |
|---|---|---|---|---|
| Contratos y cifras descritas coinciden con evidencia | ✓ | ✓ | — | — |
| Atribución y reconciliación propias tras cierre nativo | ✓ | ✓ | ✓ | ✓ |
| Inventario rechaza redirecciones antes del contenido | ✓ | ✓ | ✓ | ✓ |
| Pruebas existentes conservadas, sin puertas relajadas | ✓ | ✓ | ✓ | — |
| Carga integrada, ACL final, reinicio/restore y Google | Pendiente | Pendiente | Pendiente | Pendiente |

Sin hallazgos nuevos: **0 Critical / 0 Important / 0 Minor**. A ejecutó cuatro
pruebas focales, incluida junction real; B tres comprobaciones en memoria;
C seis casos de identidad/atribución en memoria. D comprobó cardinalidades
0/1/10 contenedores y550/1100 fuentes sintéticas: crecimiento lineal y trabajo
fuera del procesamiento de mensajes. No ejecutaron SQL/red/Docker ni suite de
aplicación. Las salidas de preparación conservan84 Python,10 PHP/33 aserciones,
11 descriptores y3 paridades verdes. No existe porcentaje de cobertura aportado.
La ruta de integraciones del proyecto está ausente; la traza permanece local.

Se aprueba esta preparación para su integración y QA posteriores. T-19 mantiene
todo su alcance, estado en-progreso y casillas pendientes; ninguna aprobación
global ni manual se deriva de esta revisión. Los informes recibidos son mensajes
nativos; las tablas del coordinador son proyecciones explícitas de esos mensajes.
