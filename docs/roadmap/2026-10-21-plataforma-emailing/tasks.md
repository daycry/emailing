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
| Fase 3 — Campañas, entrega y actividad | 5 | 7 | 71% | — / 120h | — / 3.783333h | — / 0.945833h | — / 1135000 |
| Fase 4 — API y validación global | 0 | 4 | 0% | — / 60h | — / 2.400000h | — / 0.600000h | — / 720000 |
| **TOTAL** | **14** | **20** | **70%** | **— / 376h** | **— / 12,4h** | **— / 3,1h** | **— / 3720000** |

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

**Estado**: en-progreso · **Estimado**: 120h · **Real**: — · **Coste est.**: 6000 € equivalentes + tokens · **Tokens est.**: 1135000

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
- **Estado**: borrador
- **Tiempo humano**: est. 4h · real —
- **Tiempo IA (ejec.)**: est. 0.120000h · real —
- **Supervisión**: est. 0.030000h · real —
- **Previsión IA**: 30000 in / 6000 out tok · coste IA por verificar: 0.03 p_in + 0.006 p_out €; humano base 200 € equivalente
- **Dependencias**: T-12
- **Tipo**: backend
- **Archivos**: `app/**`, `config/**`, `routes/**`, `tests/**`, `scripts/**`, `docs/desarrollo/**`, `docs/infraestructura/**`
- **Cubre (tests)**: E2E-09
- **Criterios spec**: CA-05/21/25
- **Verificación**: `php artisan test --filter=SchedulerTriggerTest` → auth, idempotencia y acción finita pasan

**Criterios de aceptación**

- [ ] Disparo exitoso confirma trabajo durable, no emails finalizados.
- [ ] Repetir disparo no duplica trabajo; sin credencial válida no ejecuta.

**Subtareas**

- [ ] Crear endpoint interno y secreto separado.
- [ ] Documentar job Cronicle y probar con infraestructura existente.

### T-16 — Revisión y QA de envío y recuperación

- **Descripción**: Revisar estados y probar recuperación con broker/SMTP reales controlados, concurrencia y fallos.
- **Estado**: borrador
- **Tiempo humano**: est. 32h · real —
- **Tiempo IA (ejec.)**: est. 1.600000h · real —
- **Supervisión**: est. 0.400000h · real —
- **Previsión IA**: 400000 in / 80000 out tok · coste IA por verificar: 0.4 p_in + 0.08 p_out €; humano base 1600 € equivalente
- **Dependencias**: T-10, T-11, T-12, T-13, T-14, T-15
- **Tipo**: test
- **Archivos**: `app/**`, `config/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `scripts/**`, `docker/**`, `compose.yaml`, `docs/**`, `README.md`, `CHANGELOG*`
- **Cubre (tests)**: E2E-08 / E2E-09 / M-01
- **Criterios spec**: CA-05/06/08/09/10/11/14/22/23/25
- **Verificación**: `php artisan test --group=delivery` → integración/crash verdes · lectura: E2E-08–09 y revisión sin Critical/Important ni flaky

**Criterios de aceptación**

- [ ] Evidencia de cada ventana de fallo y regla de recuperación disponible.
- [ ] No hay pérdida silenciosa ni afirmación de exactly-once SMTP.

**Subtareas**

- [ ] Revisión adversarial y correcciones.
- [ ] QA de concurrencia/fallos y actualización del ledger.

## Fase 4 — API y validación global

**Estado**: borrador · **Estimado**: 60h · **Real**: — · **Coste est.**: 3000 € equivalentes + tokens · **Tokens est.**: 720000

### T-17 — Contrato OpenAPI y scopes de envío

- **Descripción**: Escribir contrato OpenAPI antes de endpoints bearer: creación/revocación de keys, envío, validación, idempotencia y errores. Versionar sin prometer compatibilidad íntegra SendGrid.
- **Estado**: borrador
- **Tiempo humano**: est. 8h · real —
- **Tiempo IA (ejec.)**: est. 0.200000h · real —
- **Supervisión**: est. 0.050000h · real —
- **Previsión IA**: 50000 in / 10000 out tok · coste IA por verificar: 0.05 p_in + 0.01 p_out €; humano base 400 € equivalente
- **Dependencias**: T-16
- **Tipo**: backend
- **Archivos**: `docs/api/**`, `tests/**`, `docs/roadmap/**`
- **Cubre (tests)**: E2E-10
- **Criterios spec**: CA-19/20
- **Verificación**: `python "$env:CUSTOM_AGENTS_PLUGIN_ROOT/skills/api-contract/scripts/openapi-lint.py" docs/api/openapi.yaml` → exit 0

**Criterios de aceptación**

- [ ] Operaciones documentan éxito/error, scopes, límites y cuenta derivada de key.
- [ ] No se permite elegir cuenta ajena ni crear key más privilegiada que autorización del actor.

**Subtareas**

- [ ] Definir contrato y ejemplos sin secretos.
- [ ] Validar estructura y aprobación técnica antes de endpoints.

### T-18 — API keys, envío idempotente y gestión

- **Descripción**: Implementar bearer Sanctum/llaves hash de cuenta, scopes efectivos, revocación y límites. Reutilizar servicios de trabajo/outbox, no otra ruta de envío. Clave idempotente única por cuenta; payload distinto con misma clave produce conflicto.
- **Estado**: borrador
- **Tiempo humano**: est. 20h · real —
- **Tiempo IA (ejec.)**: est. 0.466667h · real —
- **Supervisión**: est. 0.116667h · real —
- **Previsión IA**: 120000 in / 20000 out tok · coste IA por verificar: 0.12 p_in + 0.02 p_out €; humano base 1000 € equivalente
- **Dependencias**: T-17
- **Tipo**: backend
- **Archivos**: `app/**`, `bootstrap/**`, `config/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/api/**`, `docs/desarrollo/**`
- **Cubre (tests)**: E2E-10
- **Criterios spec**: CA-01/12/13/19/20
- **Verificación**: `php artisan test --filter=SendingApiTest` → contrato, scopes, revocación y concurrencia idempotente pasan

**Criterios de aceptación**

- [ ] Repetición concurrente crea un trabajo; key inválida/revocada/sin scope rechazada.
- [ ] Read-only/Restricted no elevan permisos creando keys; cuenta se deriva de credencial válida.

**Subtareas**

- [ ] Crear endpoints/middleware/políticas y UI de keys.
- [ ] Verificar contrato y actualización de docs con ejemplos.

### T-19 — Pruebas cruzadas, carga y restauración

- **Descripción**: Validar aislamiento de dos aplicaciones de prueba y reparto de cinco y diez workers, latencia/pendientes/recursos y fallos. Restaurar respaldo en entorno temporal aislado sin borrar volúmenes existentes.
- **Estado**: borrador
- **Tiempo humano**: est. 16h · real —
- **Tiempo IA (ejec.)**: est. 0.866667h · real —
- **Supervisión**: est. 0.216667h · real —
- **Previsión IA**: 220000 in / 40000 out tok · coste IA por verificar: 0.22 p_in + 0.04 p_out €; humano base 800 € equivalente
- **Dependencias**: T-18
- **Tipo**: test
- **Archivos**: `tests/**`, `scripts/**`, `docs/**`, `docker/**`, `compose.yaml`
- **Cubre (tests)**: E2E-11
- **Criterios spec**: CA-21/22/23/24/25
- **Verificación**: `php artisan test --group=broker-isolation` → ACL/dedup pasan · lectura: informe de carga/restauración con parámetros y resultados reales

**Criterios de aceptación**

- [ ] Distribución 2/3 y 4/6 workers registrada; umbrales fijados antes de carga y resultados sin SLA inventado.
- [ ] Reinicio/restauración conserva estado esperado; definiciones RabbitMQ no se confunden con backup de mensajes.

**Subtareas**

- [ ] Crear segunda aplicación mínima de protocolo, sin su negocio.
- [ ] Medir límites/recuperación y probar backup/restauración aislada.

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

## Revisión de dos lentes — intento 1: Fase 1 (T-01, T-02, T-03) — correcciones necesarias

Lentes A+B+C+D mediante subagentes genéricos con rol reviewer y contexto fresco;
la herramienta no ofrece selector de tipo de agente. El tier procede del
frontmatter, sin override. A/B/C en paralelo, D al liberar una plaza; las dos
reanudaciones por capacidad del modelo continuaron el mismo intento. Base
`master...HEAD` más copia de trabajo; 120 archivos recorridos por bloques.
Puerta de alcance: exit 0, sin avisos, exclusiones del usuario ni archivos fuera.
C activada por autenticación, permisos, secretos e IaC; D por colas, caché y
migraciones. No hay configuración de cobertura mínima ni constitución.

| Criterio de aceptación | Veredicto fusionado | Evidencia |
|---|---|---|
| T-01.1 base exclusiva y preservación | ✓ | TestingDatabaseGuard:7, tests/TestCase:11, RuntimeIntegrationTest:13/21/28; lectura lab_smoke registrada arriba |
| T-01.2 perfiles y runtime aislado | ✗ | HTTP de ambos perfiles y RabbitMQ verificados; C2 rompe el aislamiento de PHP-FPM, B-01 impide consumir la cola configurada |
| T-01.3 locks y protocolo real | ✓ | composer.lock adaptador 15.0.2, RuntimeSmoke:19 y evidencia de publish/reentrega/ack |
| T-02.1 alta atómica y configurable | ✓ | CreateNewUser:20/27, AccountAuthenticationTest:24/41/49; B-02 exige además rechazar tipos inválidos |
| T-02.2 autenticación, CSRF y contexto | ✓ | routes/web:13/31, AccountContext:10, AccountAuthenticationTest:65/83 |
| T-02.3 recuperación por correo segura y única | ✗ | Token válido/único probado; C1 permite cambiar su origen por Host |
| T-03.1 invitaciones y concurrencia | ✓ | TeamService:100/110, TeammateTest:46/72 y TeammateConcurrencyTest:18 |
| T-03.2 roles y owner | ✓ | Permissions:34, TeamService:128/141/154 y TeammateTest:93/109/122 |
| T-03.3 revocación y auditoría | ✓ | TeamService:46/128/141, TeammateTest:139/159 |

| # | Grado | Gap | Tarea | Corrección / veredicto | Evidencia |
|---|---|---|---|---|---|
| C1 | Critical | Host de la petición determina enlaces de recuperación e invitación | T-02 | corregido por implementer; pendiente revisión 2 | Evidencia roja original conservada: notificaciones con origen attacker.example, CWE-346/640. TrustedUrl + AppServiceProvider:24 + TeamInvitation:24; cuatro regresiones de notificaciones reales por HTTP con Host/esquema manipulados PASS, suite 30/230 |
| C2 | Critical | FastCGI accesible a otras aplicaciones de la red común | T-01 | corregido por implementer; pendiente revisión 2 | Evidencia original: docker.conf listen=9000 sin allowed_clients, PHP conectado a lab/app, CWE-668/306. compose:25/42 y zzz-emailing-fpm.conf:2; socket privado no root, TCP 9000 inaccesible desde lab; viejo despliegue detenido; MySQL/RabbitMQ/SMTP PASS |
| B-01 | Important | Worker consume emailing.jobs aunque el productor usa RABBITMQ_QUEUE | T-01 | corregido por implementer; pendiente revisión 2 | Se conserva causa original compose fijo frente config/queue.php. compose:33 sin --queue; RuntimeIntegrationTest:43 demuestra productor/worker reales con cola alternativa sin argumento explícito, PASS; QA configura su cola independiente |
| B-02 | Important | Email no escalar causa TypeError antes de validación en alta/login | T-02 | corregido por implementer; pendiente revisión 2 | Evidencia roja original: email array causa TypeError. ValidateAuthEmail + bootstrap:16 validan antes de Fortify/throttle; CreateNewUser valida tipo; cuatro datasets HTTP array/vacío/número/null PASS, perfiles 8082/8083 rechazan array con 422 |
| C3 | Critical | Sesiones previas conservan acceso después del reset; origen coordinador, ver adenda | T-02 | corregido por implementer; pendiente revisión 2 | HTTP rojo con tres cookiejars conservando acceso; bootstrap:17 AuthenticateSession estándar. PasswordResetSessionTest:23 rojo 200 y verde tras fix; tres cookies antiguas rechazadas y nuevo login válido en 8082/8083 |
| A1 | Minor | Documentos de integración e índice describen runtime terminado como pendiente | T-04 | pendiente: documenter actualiza tras QA; plan refleja progreso desde ledger | docs/RAG-INDEX.md:48, docs/infraestructura/integracion-aplicaciones.md:49–54, improvement-plan.md:51 |

Aprobados con evidencia: alcance semántico, pruebas significativas y ejecución
de verificaciones declaradas. Interop/OpenAPI no aplican en este diff. La
transferencia a una membresía promovida a Admin en la misma transacción respeta
el estado final del plan, bloqueo y reautenticación; no se exige rol previo.
ADR-003 conserva estado propuesta para la fase de entrega. No se promueven ADR
con Critical/Important abiertos. Jira no está habilitado; no se publica nada.

T-01/T-02 se reabren y el progreso vuelve a 1/20; no se ocultan los defectos
mediante el verde de la suite anterior. La duración de revisión se registra
aparte de implementación, con tokens_reales/horas_ia/eur null por falta de
telemetría compatible. El rechazo de permisos del test Python de índice de D
es ambiental: esa regresión pasó con permisos adecuados en el checkpoint; no
se atribuye un fallo de rendimiento ni se borra su temporal ignorado.

### Adenda del coordinador al intento 1 — C3: sesiones anteriores al reset

Origen: comprobación adicional del coordinador, posterior a los informes A/B/C/D; no se atribuye a sus autores ni supone su aprobación. Grado **Critical**, T-02: una sesión autenticada con el email de otra persona conservaba acceso después de que el destinatario recuperase la contraseña; podría heredar sus futuras membresías compartidas. La ausencia de verificación previa del email permite esa preparación en el registro interno habilitado.

Reproducción HTTP real en el perfil nativo 8082 con `tests/support/password-reset-sessions.php`: `FAIL: old registration before first navigation retained access`, `FAIL: old login before first navigation retained access`, `FAIL: old login after navigation retained access`; el login con contraseña nueva sí funciona. Tres cookiejars antiguos y un cliente de recuperación separados; fixture UUID con limpieza exclusiva, guard de base/usuario `emailing_testing`, sin migraciones ni transacción que oculte datos al servidor.

Causa: `ResetUserPassword` cambia el hash y Fortify rota remember_token, pero no se habilitó `AuthenticateSession`. El `SessionGuard::login` instalado ya guarda `password_hash_web` al autenticar, incluyendo registro/login antes de primera navegación; basta activar la comprobación estándar central. Estado de la corrección: en-progreso dentro de T-02-fix1; regresión Feature adicional con guards y estados de sesión separados roja antes del cambio. La revisión 2 debe reevaluar C3 junto con C1/C2/B-01/B-02.

**Resultado posterior de C3 (implementer)**: `PasswordResetSessionTest` produjo antes del fix `Expected response status code [201, 301, 302, 303, 307, 308] but received 200` en la sesión antigua. Se activa únicamente `authenticateSessions()` del framework en todas las rutas web; no se añade epoch, columna ni migración. La regresión pasa con **24 aserciones**; la suite final completa tiene 30 pruebas/230 aserciones. Los clientes HTTP reales de 8082 y 8083 producen `PASS` para sesiones antiguas de registro antes de navegar, login antes de navegar y login después de navegar, y para el login nuevo con contraseña nueva. La evidencia roja previa queda histórica; corrección completada dentro de T-02-fix1, pendiente de revisión 2.

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

## Revisión de dos lentes — intento 3: corrección del harness

Revisión incremental del diff inmutable aplicado a E2E-01/E2E-04. Lentes A y
B con contexto fresco y sólo lectura; C/D no aplican según el selector. No se
reabren los criterios de producción aprobados en el intento 2.

| Criterio | Lente A | Lente B | Evidencia |
|---|---|---|---|
| Registro deshabilitado rechaza alta y conserva ausencia SQL | ✓ | ✓ | E2E-01:27, config/fortify.php:9 y diagnóstico con CSRF/404/conteos cero |
| Formulario visible antes de la segunda confirmación | ✓ | ✓ | E2E-04:37–40 y diagnóstico oculto/abierto con propietario intacto |
| Reautenticación, roles y auditoría mantienen sus asserts | ✓ | ✓ | E2E-04:35–43; no se fuerza relleno ni se ocultan fallos |
| Alcance y datos sintéticos | ✓ | ✓ | Sólo dos MJS; UUID@example.test, sin cambios en producción |
| Estados y pasos pendientes se declaran fielmente | ✓ | ✓ | T-04 abierta, QA1 roja conservada; transferencia válida y timeout pendientes de QA2 |

Resultado: 0 Critical, 0 Important y 0 Minor nuevos. A1 documental anterior
continúa pendiente para documenter tras QA. Constitución, interop y OpenAPI
no aplican. Comprobación inversa del patch y sintaxis sin errores; la sintaxis
de Node 22 se ejecutó por implementer, y B identifica su comprobación adicional
con el Node 23 de su sesión. Ninguna lente ejecutó navegador o migraciones.

Medición revisión 3: 2026-10-22T17:15:33Z–2026-10-22T17:20:11Z, cerrada antes de QA2.
Fuente estimado, tokens_reales/horas_ia/eur null; no se imputa reloj como IA.
QA2 abre su primer intento el 2026-10-22T17:20:12Z y conserva un máximo de
tres intentos, sin segundo diagnóstico automático si agotase ese ciclo.

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

## Revisión de dos lentes — intento 4: incremento FB-01 de fase 1

Primera revisión del incremento surgido de la inspección visual de QA2; no
reinicia el contador de QA ni reabre la revisión productiva anterior. Lentes
A/B nuevas, genéricas con rol reviewer, runtime heredado; C/D false según
selector. Ambas verifican el patch completo de siete archivos, SHA-256
`71ceb21d19f12a29dd7845b1e4a72aa8abeaca2490188384e1cbe655205e42a0`.

| Criterio | A | B | Evidencia |
|---|---|---|---|
| Errores se limpian sólo después del éxito | ✓ | ✓ | Team.php:42/71/88; excepciones mantienen feedback |
| Reintento sobre la misma instancia y efectos persistidos | ✓ | ✓ | TeammateTest:123/140/154/168; RED 3 fallos, GREEN 4/48, Feature 33/262 |
| Reautenticación, propietario, roles y auditoría conservados | ✓ | ✓ | Servicio sin cambios; rechazo antes del éxito y asserts persistidos |
| E2E detecta el error residual y capturas parten del inicio | ✓ | ✓ | E2E-04:41 y helpers:18; nueva ejecución pendiente |
| PHP de QA sólo cambia formato | ✓ | ✓ | Lectura íntegra de fixture, server-off y registration-count |
| Alcance y estados fieles | ✓ | ✓ | T-03/T-04 declaran los archivos; T-04 sigue abierta |

FB-01: corrección aprobada por ambas lentes, pendiente de QA2 intento 2 de 3.
Cero Critical/Important/Minor nuevos. A ejecuta comprobación inversa del patch
y sintaxis de cinco PHP/dos MJS, exit 0; B lee fuentes y stdout sin ejecutar
suites. Ninguno usa navegador, DB o migraciones. Constitución ausente;
interop/OpenAPI no aplican; Jira deshabilitado. A1 documental permanece
pendiente. No se promueven ADR de entrega futura.

Medición de la revisión del incremento: 2026-10-22T17:49:55Z–17:53:06Z,
cerrada antes de abrir QA2-2 a las 17:53:08Z. Fuente estimado, consumo real
desconocido y campos de tokens/horas IA/coste null. Datos públicos del estado
revisado: 340 archivos, cero hallazgos; no es una comprobación del historial.

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


## Revisión de dos lentes — intento 1: Fase 2 (T-05, T-06, T-07, T-08) — correcciones necesarias

A+B+C+D sobre snapshot54archivos, hash46b233731c5e7d2fff50d34d4dedf20159485a6f83bab92bf58f10026172403f.
**Correcciones requeridas: 3 Important abiertos, 0 Critical.** B-01: HTML-only
rechazado por text requerido (T-07); B-02: conteos no aseguran ubicación de
tokens (T-07); D-01: conteos por lista sin índice de prefijo utilizable (T-05).
[Tabla, escenarios y artefacto inmutable](testing/fase-2/review/attempt-1.md).
C no confirma vulnerabilidades; B-02 procede de su observación fuera de lente,
confirmada después por B como corrección. A inicial verde no invalida esa
evidencia nueva. T-05 y T-07 se reabren; T-06/T-08 siguen implementadas.
T-09 permanece en progreso; QA de navegador todavía no se ha ejecutado.

Verificación/checkpoint: ventana T-09-verify1 19:57:56Z a20:09:52Z cerrada.
Revisión: 20:10:24Z a20:31:32Z cerrada (2026-10-22); cifras reales null.
Lentes por subagentes genéricos/reviewer; D al liberarse slot trasA/B/C.
El checkpointdb18d4a está confirmado en remoto, push normal sin reescritura.

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

## Revisión de dos lentes — intento 3: Fase 2 (T-09) — corregir teardown del harness

A+B sobre31fuentes/125.761bytes/1.369líneas, hash
`1bc528a8bf60bfc6cfb6469afc6a0ea60c0234ea462b6c74cf02e6e852af869f`.
Integridad31/31 y lectura completa; A además reconstruye el patch y comprueba
29/29 hashes del handoffQA. Scopeexit0sinfuera/avisos/exclusiones; C/Dfalse.

| ID | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| QA-01 | Important | Overflow móvil | T-09 | Navwrap móvil y nowrapdesktop; focal aprobada | Rects390/768/1440 y seis enlaces completos |
| B-03 | Important | Finalizador busca temporales en raíz equivocada y diagnóstico sinUUID no atribuible | T-09 | Pendiente, QA2 no arranca | config/livewire.php7–8,filesystems.php43,finalize-extras.py27/35,diagnostic.mjs18 |

A no encuentra gaps nuevos. B confirmaB-03; las aprobaciones anteriores se
conservan y readinessE06 sigue provisional. Ver [dictamen y decisión del
orquestador](testing/fase-2/review/attempt-3.md). Tras el tercer intento el
orquestador decide continuar con reparación específica deB-03 dentro del alcance
autorizado, sin aceptar deuda ni relajar pruebas. No se atribuye la limpieza de
temporales como completa: se verificó una ruta distinta de la real. Originales
QA1 conservados; T-09 permanece en progreso.

**Publicación parcial validada:** `9109d87`, publicado y comprobado en la rama
`feature/plataforma-emailing`, contiene únicamente la corrección de navegación
móvil y sus cuatro evidencias focales. El cambio de producto coincide con el
snapshot aprobado de revisión 3. El control de datos públicos del índice examinó
421 archivos sin hallazgos; autor y committer usan la identidad pública del
proyecto. Esta publicación no cierra T-09 ni sustituye la segunda pasada completa
de QA, pendiente de resolver B-03.

La captura incremental incluye `.gitattributes`: Git normalizaba los finales de
línea de fuentes QA y alteraba los bytes cuyos hashes constan en los manifiestos.
Se preservan los bytes del árbol de pruebas de fase 2 al publicarlo. La regla no
desactiva las comprobaciones de espacios de su código; las excepciones anteriores
para patches y stdout originales siguen acotadas a esas evidencias.

## Revisión de dos lentes — intento 4: Fase 2 (T-09) — aislar errores del teardown

Primer intento acotado del incremento B-03 tras la decisión del orquestador del
intento 3. Lentes A+B frescas; C/D false. Scope exit 0, 145 cambios incluidas
evidencias, cero fuera de alcance/avisos/exclusiones de usuario. Diff completo
16 archivos/54.636 bytes/572 líneas, SHA256
`c801a6a284ed0b9cea3e2dce639daeecf7441ceb5f8e3de1200417789ce3132e`.
Ambas lentes comprueban 42/42 fuentes congeladas; A verifica 36/36 hashes de
handoff y publicación exacta de bytes para 38/38 fuentes QA.

| ID | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| B-03 | Important anterior | Raíz equivocada y uploads sin registro | T-09 | Objetivo focal aprobado | RED/GREEN4 y cleanup tras SQL eliminado en ambos perfiles |
| B-04 | Important nuevo | Fallo de cleanup temporal impide limpiezas independientes | T-09 | Pendiente | specs/helpers.mjs:17; prueba pura calls=[temporary] |

A no encuentra gaps nuevos; B encuentra B-04, verificado por el orquestador en
la función congelada. Ver [dictamen completo](testing/fase-2/review/attempt-4.md).
Se conserva lo aprobado, incluida la navegación y las correcciones anteriores.
La siguiente revisión será el intento 2 de 3 del incremento específico. QA2 no
arranca. QA1 permanece roja 8/12 y T-09 en progreso. Medición review4:
2026-10-22T22:35:48Z–22:42:31Z, reales desconocidos/null.

La verificación focal B-03 final acredita ocho registros propios, cero archivos
propios restantes y cero fixtures. Se preservan seis archivos nativos y cuatro
Docker no atribuibles; no se afirma cero global ni se reescribe la evidencia de
QA1. Ventana harness-fix2 cerrada: 2026-10-22T22:01:14Z–22:35:26Z, reales null.
El archivo privado adicional de 320 salidas originales, verificado por hash,
protege los nombres reutilizables del runner antes de QA2.

## Revisión de dos lentes — intento 5: Fase 2 (T-09) — B-04 aprobado

Segundo intento de tres del incremento específico, sin reiniciar el contador.
Lentes A+B frescas: cero Critical/Important/Minor. Scope exit 0, 159 cambios
incluidas evidencias y cero fuera de alcance/avisos/exclusiones de usuario;
C/D false. Diff completo tres archivos/10.549 bytes/148 líneas, hash
`0b197a8aa7cf2214137b65de21554d20a3309136607618898b9719177166d279`.
Ambas lentes verifican 43/43 fuentes y preimágenes; A comprueba el manifiesto
B-04 37/37. Se conserva lo aprobado en intentos anteriores.

| ID | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| B-04 | Important anterior | Excepción temporal bloqueaba otras limpiezas | T-09 | Aprobada por A+B | Siete escenarios sobre el módulo real; RED 5/7 fallan y GREEN 7/7 pasan, mismo test |
| Nuevos | — | Ninguno | T-09 | Sin hallazgos | Integridad y lectura completa del incremento |

La función intenta las cuatro operaciones independientes y conserva causas
originales de contextos y etapas en AggregateError. B repite RED/GREEN en
memoria con resultados idénticos. Ver [dictamen](testing/fase-2/review/attempt-5.md)
y [evidencia focal](testing/fase-2/raw/b04-fix/report.md). Sin DB, servicios ni
fixtures durante esta corrección. Ventana harness-fix3:
2026-10-22T22:43:38Z–22:48:26Z; review5:
2026-10-22T22:48:45Z–22:53:03Z; reales desconocidos/null.

La revisión permite QA2 completa; no acredita su resultado. T-09 sigue abierta,
con 8/20 tareas implementadas, QA1 roja preservada y M-01 humano pendiente.

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

La reproducción HTTP con referencias firmadas reales confirmó en ambos
perfiles que `_finishUpload` produce metadata `fil` y que la importación por
otro actor devuelve **403**, sin diferencias SQL ni de los archivos exactos.
El payload anterior dejaba `file` como string sin esa metadata y devolvía 200
por validación. En esta reproducción el snapshot activo coincidió con el
atributo DOM: la hipótesis de snapshot obsoleto no se acredita como causa.
Las tres pruebas puras iniciales expresaban un contrato alternativo propuesto;
no se presentan como regresiones de un defecto observado del helper.

Una barrera sobre la respuesta real de `create` reprodujo la pérdida de los
valores rellenados antes del reset del formulario, en ambos perfiles. Las
comprobaciones de validez y rechazo de cuerpos vacíos se conservan; se esperará
la finalización de esa llamada antes de rellenar.

Defecto de producto adicional confirmado: `ContactImports::render()` valida
`listSearch` mediante la validación global del componente y borra errores de
importación antes de mostrarlos. **RED: dos pruebas Feature de importación sin
archivo o con valor inválido fallan al buscar el aviso visible (8 aserciones,
12,99 s).** T-06 vuelve a en-progreso mientras se corrige y verifica este
comportamiento; resumen actual 7/20. La corrección mínima mantendrá la
validación del buscador sin borrar los errores de otros campos. T-09 y QA3
siguen pendientes. Ventana harness-fix4 abierta desde 2026-10-22T23:39:09Z.

Corrección mínima de T-06 verificada: la búsqueda usa `validateOnly` y conserva
los errores de archivo. Las mismas dos regresiones definitivas fallan sobre el
producto anterior; con el arreglo, `php artisan test
tests/Feature/ContactImportTest.php --no-ansi` termina **exit 0, 15 passed,
184 assertions, 47,69 s**. Root comprobó el recibo y stdout originales de
`import-errors-green-2`; el primer intento de pruebas con precondiciones
incorrectas se conserva y no sustituye este par RED2/GREEN2. B-04 mantiene sus
siete escenarios aprobados sin cambios. T-06 vuelve a completado: 8/20 tareas
implementadas; T-09 continúa en progreso a la espera de revisión y QA3.

La primera tanda focal posterior a QA2 terminó completa: **6 aprobados y 2
fallidos**, sin reintentos; no se presenta como QA3. E2E-05 (CRUD y binding) y
E2E-06 pasan completos en ambos perfiles. E2E-07 alcanza log, Mailpit, descargas
ajenas y scopes; se detiene después en la línea 37, que espera 422 al rechazar
SMTP externo y observa 200. El original se conserva. Se está comprobando el
contrato de validación de Livewire y la ausencia de mutaciones antes de cambiar
la expectativa; no se atribuye envío externo habilitado a ese estado aislado.

### Cierre de la corrección posterior a QA2

El diagnóstico de E2E-07 confirma en ambos perfiles HTTP 200 con aviso concreto
en `effects.html` y sin cambios SQL; el mapa `memo.errors` no acredita por sí
solo el rechazo de un campo que no se hidrata como propiedad. La prueba exige
el mensaje del modo no permitido, igualdad de los recursos y un fingerprint
de las filas de configuración ordenadas y limitadas a las cuentas A/B del
fixture. No publica valores crudos ni secretos cifrados.

La ronda siguiente de E2E-07 conserva **1 aprobado / 1 fallido**: nativo se
detuvo en el primer guardado con estado de carga. La reproducción nativa
instrumentada posterior pasa sin cambiar esa fuente; **la causa exacta de la
observación anterior no se demostró**. No se atribuye a lentitud del backend
ni se convierte ese resultado original en verde. Correlacionar las respuestas
de los tres guardados mediante `clickCall` refuerza el contrato de la prueba,
con los presupuestos existentes, sin presentarlo como arreglo causal probado
de aquella observación.

Sobre los mismos bytes finales, E2E-07 pasa en **nativo (50,7 s) y Docker
(33,0 s)**. Junto con los seis recorridos completos anteriores, queda cubierta
la matriz focal de ocho casos; no se afirma que la primera ejecución fuera
8/8. Los veinte casos completos, sus límites y sus reintentos permanecen
intactos para QA3. El helper y las fuentes B-03/B-04 conservan sus bytes.

Verificación declarada de T-09 ejecutada una vez sobre el producto final:
`php artisan test --testsuite=Feature` → **exit 0, 90 passed, 977 assertions,
235,91 s**. Root inspeccionó el stdout original. Pint de los dos PHP y el
control de whitespace terminan con exit 0; privacidad: 689 archivos, cero
hallazgos. El formato del test se registra aparte del par RED2/GREEN2.
Integridad final comprobada por root: **37 fuentes QA y 2 de producto, cero
diferencias** frente a sus manifests.

Limpieza final de 17 UUID propios: cero residuos SQL, siete entradas temporales
registradas sin archivos restantes y cero mensajes propios en Mailpit.
`lab_smoke` conserva cinco filas; un recuento de tablas no establece el estado
de migraciones de producto. Ver [informe focal](testing/fase-2/raw/qa2-fix/report.md),
[handoff](testing/fase-2/raw/qa2-fix/handoff.json) y
[validación](testing/fase-2/raw/qa2-fix/validation.json).

Ventana harness-fix4 cerrada: 2026-10-22T23:39:09Z–2026-10-23T00:27:44Z,
49 minutos de reloj; tokens, euros y horas IA reales desconocidos/null.
T-09 sigue en progreso y la iniciativa conserva 8/20 tareas implementadas.
Siguiente puerta: revisión fresca del incremento y QA3 completa, intento 3/3.


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


## Revisión de dos lentes — intento 7: Fase 3 (T-10) — B-01 menor

Primer intento de tres del incremento. A+B+D frescas: **0 Critical, 0 Important,
1 Minor**. C false; D por migración. Scope exit 0, cero fuera de alcance, avisos
y exclusiones de usuario. Integridad 94 fuentes/25 evidencias/10 preimágenes,
sin diferencias; patch completo 21 archivos, SHA256
`026ec95c3821715aac9a5bbce5368591af7458186253b6ec86f1b966605e33e1`.

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| B-01 | Minor | Página vacía al retirar la última supresión de página 2 | T-10 | Verificado; pendiente de corrección, sin aceptar deuda | [Lente B](testing/fase-3/review/attempt-7-lens-b.md), `app/Livewire/Suppressions.php:35` |
| A/D | — | Sin hallazgos nuevos | T-10 | Aprobaciones del componente conservadas | [Dictamen completo](testing/fase-3/review/attempt-7.md) |

El siguiente intento conservará la tabla completa y revisará lo corregido sin
reabrir aprobaciones sin nueva evidencia. T-10/E2E-08/CA-10/14 integrales siguen
abiertos; 9/20 sin cambiar, Pint global exit 1 conservado y ADR-003 propuesta.
Una interrupción por capacidad de B se reanudó sobre el mismo snapshot y no es
un nuevo intento de revisión. Roles genéricos, runtime heredado; no se atribuye
ejecución Claude. Ventana 2026-10-23T02:17:58Z–02:26:38Z, nueve minutos de
reloj; tokens/euros/horas IA reales null. Jira desactivado/no configurado.


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


## Revisión de dos lentes — intento 8: Fase 3 (T-10) — B-01 aprobado

Segundo intento de tres del mismo incremento. Lentes A+B+D frescas:
**0 Critical, 0 Important, 0 Minor pendientes**. B-01 corregido y aprobado;
ningún criterio reabierto sin evidencia nueva. Scope acumulado exit 0,
51 archivos, cero fuera de alcance/avisos/exclusiones de usuario. C false;
D por migración. Incremento 33 archivos, solo dos PHP funcionales modificados;
124 fuentes, 30 evidencias y tres preimágenes con SHA verificados sin diferencias.

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| B-01 | Minor anterior | Página vacía tras última retirada de página 2 | T-10 | Aprobado A+B+D | `resetPage()` tras servicio autorizado; [regresión RED/GREEN](testing/fase-3/review/b01-fix.md) |
| Nuevos | — | Ninguno | T-10 | 0 Critical/Important/Minor | [Dictamen completo](testing/fase-3/review/attempt-8.md) |

Patch SHA256 `7762bfa5d4f9d67a1114e94474fa21e12ef85143a9ce85ba9f2d5fb2cc643c67`.
Root verificó integridad antes de añadir esta traza. La tabla anterior completa
y todas sus aprobaciones/pendientes se conservan. Regresión 1/6 RED→1/12 GREEN,
suite supresiones 16/165 y Pint de dos PHP exit 0. Feature 107/1147, build y
frontera corresponden al snapshot anterior; Pint global sigue exit 1 histórico.

Se satisface la dependencia técnica para desarrollar T-11, conservando T-10
abierta para el recorrido durable pendiente de T-12/T-13/T-16. No se rebaja el
alcance ni se cambian dependencias. T-10/E2E-08/CA-10/14 integrales pendientes,
9/20 sin cambiar; ADR-003 propuesta y M-01 humana pendiente. Esto no concede
QA de fase 3 ni feedback real de proveedor.

Ventana review8 2026-10-23T02:42:41Z–02:49:49Z, siete minutos de reloj;
tokens/euros/horas IA reales null. Runtime genérico heredado, sin atribuir
ejecución Claude; Jira no configurado. Siguiente paso: commit/push normal del
checkpoint revisado y desarrollo de campañas.


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


## Revisión de dos lentes — intento 9: Fase 3 (T-11) — recuperar selecciones desaparecidas

Primer intento de tres del incremento T-11. Lentes A+B+D frescas:
**0 Critical, 1 Important (B-01), 0 Minor**. C false; D por migración.
Scope exit 0 sobre 18 archivos, sin fuera/avisos/exclusiones de usuario.
Patch SHA256 `921f5f99495aa2f41a656111bd70b8f57e5856672219e2036be7e9eab9627ca2`.
Integridad: 107 fuentes, cinco preimágenes, 89 evidencias y patch (202 rutas
únicas), sin discrepancias. Las tablas A/B/D completas se conservan en el informe.

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| B-01 | Important | Lista/contacto borrado conserva ID invisible y bloquea el borrador | T-11 | Confirmado; pendiente fix1 con retirada explícita de selección | [Revisión y tablas completas](testing/fase-3/review/attempt-9.md) |

La solución debe permitir recuperar la edición desde la UI, sin poda silenciosa
de audiencia ni relajación de cuenta/scopes/versiones/confirmación. Se mantiene
T-11 en-progreso. T-10/CA-05/E2E-08/M-01/QA de fase 3 permanecen pendientes;
9/20 y ADR-003 propuesta. Pruebas previas 25/247, 20/185 y Feature 133/1404,
Pint acotado/build verdes; Pint global anterior mantiene exit 1 separado.

A se reanudó tras una interrupción de capacidad sobre el mismo snapshot y
modelo, sin reiniciar intento. Runtime genérico heredado; sin Jira opt-in.
Ventana de revisión 2026-10-23T08:31:32Z–2026-10-23T08:48:37Z; reloj
17m; tokens/euros/horas IA reales NULL. Siguiente paso:
corrección medida T-11-fix1 y segunda revisión con traspaso completo.

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


## Revisión de dos lentes — intento 10: Fase 3 (T-11) — componente aprobado, integración pendiente

Segundo intento de tres del mismo incremento. Lentes A+B+D frescas,
**0 Critical / 0 Important / 0 Minor pendientes**. C false; D por migración
acumulada sin modificar en el fix. Scope exit 0, 89/89 en alcance; sin
exclusiones, avisos ni información oculta. Las tablas completas anteriores
se traspasaron; solo se reevaluó la corrección con continuidad explícita.

| # | Grado previo | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| B-01 | Important | IDs eliminados bloqueaban el borrador | T-11 | Corregido: retirada explícita, nueva selección y revisión de la misma campaña; scopes/versión/confirmación conservados | [Informe y tablas A/B/D](testing/fase-3/review/attempt-10.md) |

Root verificó 178 fuentes, cuatro preimágenes, 41 evidencias y patch,
224 rutas únicas sin discrepancias. Patch SHA256 `6331c8711dc88d90935e5f17da780d14fd2f57d530ffbd31fd86e016556a62db`.
75 archivos incrementales; tres de código/pruebas. Se mantienen las fuentes
del dominio/renderer/migración y los resultados anteriores con su snapshot.
La errata de B («cinco preimágenes») se aclara en la fusión sin alterar su
dictamen original. Los cinco ajustes de LF final del patch tampoco cambian
los bytes originales de evidencia.

Se marcan únicamente scopes/recursos, UI y fechas/concurrencia de T-11.
Campaign final 28/301, cuatro casos MySQL concurrentes, Pint acotado/build
exit 0. Feature 133/1404 y plantillas 20/185 corresponden al snapshot previo.
Pint global anterior exit 1 se conserva, pendiente T-20. Ninguna prueba
de navegador/worker/M-01/QA integral se infiere de esta revisión.

Ventana revisión 2026-10-23T09:19:21Z–2026-10-23T09:48:46Z; reloj
29m; tokens/euros/horas IA reales NULL. Revisores
genéricos con runtime heredado; tier frontmatter, sin Jira opt-in.
T-10/T-11 continúan abiertas; 9/20, ADR-003 propuesta, envío externo
deshabilitado. Siguiente paso: commit/push del checkpoint y T-12.


### Preparación del checkpoint T-11 — bytes de evidencia y formato pendiente

La comprobación exacta del índice detectó que Git normalizaba los CRLF de
`raw/t11-b01-method-verification.json`. Se añade a `.gitattributes` la regla
`docs/roadmap/**/testing/**/raw/*.json -text`, como las reglas existentes
de streams/patches, para conservar los bytes originales de evidencia.
El cambio se declara en Archivos de T-11; no cambia runtime ni resultados.

El control adicional `git diff --cached --check` detectó espacios al final
de 29 líneas de la vista de campañas, incluidas 24 líneas vacías y cinco
líneas con HTML/texto. El control queda rojo y se incorpora al formato
global pendiente T-20; no se altera la vista congelada ni se afirma una
verificación global de formato verde. Los snapshots, manifests y streams
originales permanecen intactos. No es un defecto funcional de revisión ni
un cierre de QA; debe corregirse y verificarse antes del cierre global.


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

`php artisan test --filter=OutboxIntegrationTest`: **11 failed / 13 assertions**,
112,82 s, exit 2. Ventana 2026-10-23T10:06:49.136993+00:00–2026-10-23T10:08:48.077250+00:00.
Root verificó manifest `9d40416af9d8d0105a44c228258a095d17ff11322bf5923b69dbae916680b219`, 24 fuentes en ZIP y ambos
streams originales contra sus hashes; stdout `595ecf7d04dd4119201f211ee7ae5c9677f2dc8ce2e6723150e0782ce3c6d9fc`.
El fallo al imprimir Unicode del wrapper ocurrió después de guardar el recibo
y no sustituye el exit 2 real de PHPUnit. No se acredita ninguna ventana del
broker ni se presenta esta suite focal como un intento QA/adversarial.

Errores constatados: cast Eloquent de Expression al guardar fechas calculadas
y rollback de índice recipient_provenance aún usado por una FK. El implementer
confirmó la terminación de su handle antes de editar; ajusta fechas mediante
query builder/reloj DB y restaura soporte de índices al deshacer la migración.
Fuentes T-11 publicadas sin cambios. Los resultados del siguiente intento se
registrarán por separado, sin sobrescribir estos fallos ni sus fuentes.
T-12 permanece en-progreso, 9/20; envío externo deshabilitado.


## T-12: segunda verificación focal, recuperación Linux aún pendiente

Mismo comando declarado: **10 passed / 1 failed / 76 assertions**, 140,14 s,
exit 1. Ventana 2026-10-23T10:10:00.648364+00:00–2026-10-23T10:12:25.117995+00:00.
Root contrastó 24 fuentes del ZIP, manifest y ambos streams contra
el recibo. Manifest `fe549f1dc9ae7dde54735b91dfa639528df8747e498838edd471732594476a4d`; stdout
`da2fb1b943cd12857702f14b451206df33af250934d33508d750e79e018c37be`. La salida original se conserva solo en privado:
el entorno nativo produjo un stack con argumentos potencialmente sensibles.
No se copia a documentación pública ni se cambia su resultado; los próximos
recibos usarán configuración PHP aislada, sin cambiar el php.ini compartido.

El caso Linux falló por permission denied al acceder a la API Docker del
entorno restringido y no creó contenedor; no acredita crash/recuperación.
Los diez casos aprobados son evidencia focal pendiente de la revisión de sus
fuentes y trazas, no QA integral ni aceptación del worker. El implementer
confirmó handle terminal antes de corregir y repetirá con acceso Docker para
recursos propios. T-12 permanece en-progreso, 9/20 y ADR-003 propuesta.


## T-12: depuración causal de la señal de prueba Linux

Tercera suite focal: **13 passed / 1 failed / 108 assertions**, 162,76 s,
exit 1. Root verificó las 24 fuentes en ZIP y streams originales; manifest
`8c49807e98c82c42a4e8c68106c0b11cd4f9e05e68b98ae242da38a45be588b3`. El corte SQL tras ACK y rollback/up repetido
están entre los casos focales aprobados, pendientes de revisión de sus trazas.
El caso Linux aún no acredita la ventana de crash: su limpieza con exit 137
no demuestra que el kill ocurriera después de la barrera requerida.

Se aplica `custom-agents:debug-root-cause` como depuración ordinaria, fuera
del gancho de tercer QA: no ha comenzado QA integral ni revisión de T-12.
Reproducción mínima aislada y marcas seguras descartan bloqueo de STDIN.
El contraste conserva test/expectativas/plazo de 40 s y compara vendor del
bind con vendor interno Linux: ambos fallan (29,22 y 23,00 s). Las dependencias
y fuentes cargadas se contrastan por hash; no se atribuye todo el problema
a rendimiento ni se aumenta el plazo para ocultar el fallo.

La segunda hipótesis se prueba en el instante de lectura: el archivo de
barrera ya existe pero contiene **0 bytes**, SHA vacío
`e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`, `json_valid=false`; leerlo como JSON falla antes de
que termine la escritura. Hash del recibo de esa observación
`ee7591aaef9e5544f4dc2342581bc196a0242059a52291cff82d76fc8dc565ed`. La lentitud del montaje es condición
secundaria; no fue causa suficiente, pues el caso también falla con vendor
interno y confirmación dentro del plazo.

Fase 4 en curso: regresión determinista con escritura parcial y las mismas
expectativas, publicación mediante temporal/rename y espera acotada de JSON,
identidad y secuencia válidos. Las evidencias RED/GREEN y el caso Linux real
se registrarán por separado. Después deben pasar el comando declarado y la
suite completa; no se concede conocimiento aceptado ni cierre por un fix aún
sin verificar. T-12 sigue en-progreso, 9/20, ADR-003 propuesta.


## T-12: primer focal completo verde; congelación final y suite global pendientes

Comando declarado `php artisan test --filter=OutboxIntegrationTest`:
**16 passed / 141 assertions**, 287,95 s, exit 0. Ventana
2026-10-23T10:39:07.308732+00:00–2026-10-23T10:43:59.704898+00:00.
Root contrastó 25 fuentes de ZIP/manifest y ambos streams; manifest
`7d7dcb522939795be482d829ee1708a69f6f9bfc0c8db31afabd1b681705115a`, stdout
`ff9e947aada776f625fdb8cc4dd7a657ae4a3bbf7a1e27db98b11f806ba8bea8`. No hay hallazgos de privacidad
en estos streams; el segundo run sensible conserva su tratamiento privado.

La regresión de marcador parcial tiene exactamente el mismo cuerpo en sus
snapshots RED, GREEN y este focal: SHA `42378233f0bf4555eddfa7f292c7a3aad8924cc09986c869c4682a156e13be0c`.
RED 1 failed / 2 assertions → GREEN 1 passed / 3 assertions. El archivo
completo no es idéntico: se refuerza la lectura de barreras del caso Linux
y se añade cobertura de señal inválida permanente/evento ajeno. El Linux
aislado obtuvo 1 passed / 26 assertions; el focal actual incluye ese camino.
Las fuentes/procedencia no se infieren de un cambio de expectativas.

Después de este snapshot se aplica la misma publicación atómica a las señales
ready/observation del proxy y se prueba el comando CLI finito en el caso feliz.
Se requiere nuevo focal declarado sobre esa fuente y suite completa sin editar
entre ambos. El primer verde no se presenta como validación de esos cambios
posteriores ni como QA E2E, aceptación humana o revisión independiente.
T-12 permanece en-progreso; 9/20, ADR-003 propuesta y correo externo bloqueado.


## T-12: focal final congelado verde; suite completa en ejecución

`php artisan test --filter=OutboxIntegrationTest`: **16 passed / 143 assertions**,
235,98 s, exit 0. Ventana 2026-10-23T10:45:25.788572+00:00–2026-10-23T10:49:26.006462+00:00.
Manifest final focal `23f86d599c48330b394d961c9222a7bc599493e2e5f4522aa36df7aac6de9ce1`; stdout
`d5d6ecbe3662029b1f711e7dfade08f86a419246996ca20af357bb96eae32725`. Root verificó fuentes/ZIP/streams
y ausencia de hallazgos de privacidad en esas salidas. Son 25 fuentes
capturadas: 15 cambios y diez dependencias conservadas, no 25 cambios.

Este snapshot incorpora señales atómicas del proxy y el camino CLI finito.
La regresión de señal parcial mantiene el mismo cuerpo que RED/GREEN:
`42378233f0bf4555eddfa7f292c7a3aad8924cc09986c869c4682a156e13be0c`. La prueba Linux acredita su
barrera de confirmación y kill propio antes del CAS; los casos de pérdida
de confirm y conexión SQL disponen de trazas causales por run. Estos detalles
siguen pendientes de la revisión independiente; no equivalen a SMTP/worker.

El implementer ha iniciado `php artisan test` completo sin filtros, de forma
secuencial, con snapshot ampliado de 189 fuentes. Root contrastó sus 189
entradas en ZIP y la igualdad de los 25 SHA focales. Manifest ampliado
`1389517c2f66f6f8c256acb7bb069760015bc7489de143a31b611d6a3cb6f588`. Se excluyen entornos privados,
storage y caché compilada de bootstrap. No se editan fuentes durante la suite;
emailing_testing permanece reservada por el implementer hasta terminal y
limpieza expresa de sus recursos. No se afirma todavía su resultado.

Control preventivo del árbol público: 932 archivos, cero hallazgos. Debe
repetirse sobre el índice exacto antes de un futuro commit; no es auditoría
de historial. La salida sensible del segundo run sigue ignorada por Git y
fuera del contexto de build Docker. Root conserva originales privados y
publicará únicamente evidencia saneada, sin valores de credenciales.

T-12 sigue en-progreso. Pendientes suite completa, handoff/liberación DB,
cierre del medidor de implementación y revisión A/B/condicionales antes de
commit/push. El diagnóstico ordinario completará su fase 4 y memoria solo
con esas verificaciones, sin reiniciar contadores QA/adversariales.
Total 9/20, ADR-003 propuesta y envío externo deshabilitado.


## T-12: suite completa final verde; rendimiento y revisión pendientes

`php artisan test`, sin filtros: **157 passed / 1.633 assertions**, 548,03 s,
exit 0. Ventana 2026-10-23T10:50:09.520470+00:00–2026-10-23T10:59:20.460117+00:00.
Root verificó las 189 fuentes del ZIP, manifest y ambos streams originales;
manifest `1389517c2f66f6f8c256acb7bb069760015bc7489de143a31b611d6a3cb6f588`, stdout
`3b38f6038f2afefd7855f10ac5cb944806e03262f87949f6c8eeb6bc431d1846`. Las 25 fuentes del focal final
16/143 coinciden con sus entradas del snapshot ampliado. No hay hallazgos
de privacidad en los streams de esta ejecución; el segundo run sensible
conserva su tratamiento privado y no se publica por este resultado.

La suite completa termina la verificación funcional de la corrección de
barreras de prueba. Se registra GOT-003 como propuesta, con el síntoma,
causa demostrada y regresiones; no se promueve antes de la revisión.
La iniciativa supera la orientación de 0–2 entradas de conocimiento:
debe valorarse ese umbral en la retrospectiva final, sin omitir una causa
que ha consumido varios ciclos de depuración ni duplicar una existente.

El implementer mantiene emailing_testing reservada para medir materialize()
con 100, 200 y 10.000 destinatarios. Preparación y limpieza de fixtures
propios quedan fuera del tiempo medido; se cuentan verbos SQL sin guardar
consultas, bindings ni secretos. No se optimiza ni se cambia el snapshot
durante esa medición. Este benchmark no prueba capacidad SMTP ni un SLA.

Pendientes resultado de rendimiento, limpieza atribuible, handoff y
liberación expresa de DB/fuentes; después se cierra el medidor y se
congela la revisión 11, primer intento del incremento T-12. El verde
funcional no sustituye revisión independiente, E2E, QA de fase 3 ni M-01.
Total 9/20, T-10/T-11/T-12 en-progreso, ADR-003 propuesta y envío externo
deshabilitado. No se cierra todavía ninguna tarea por este checkpoint.


## T-12: coste medido del materializador antes de revisión 11

Benchmark propio terminal exit 0, una muestra por tamaño, PHP 8.4.18
nativo Windows y MySQL de pruebas. Se mide solo materialize(), incluida
su transacción; preparación por CampaignService, comprobaciones de filas,
repetición idempotente y limpieza quedan fuera. El contador QueryExecuted
cuenta sentencias por verbo, sin SQL/bindings y sin begin/commit de PDO.

| Destinatarios | Tiempo de materialize() | SELECT | INSERT | Total SQL |
|---|---|---|---|---|
| 100 | 1.1661848 s | 7 | 200 | 207 |
| 200 | 3.6606002 s | 7 | 400 | 407 |
| 10000 | 214.0082381 s | 7 | 20000 | 20007 |

Las tres campañas producen exactamente una entrega y un outbox por
destinatario y cero nuevas filas al repetir. Quedan cero cuentas/usuarios
de esos tres fixtures propios. No se usó broker ni SMTP en la medición.
Root verificó ZIP/25 fuentes/receipt/streams y privacidad de estas salidas;
stdout `b2fde8108351fc09d995fa097ca31f5d2f02471427c133445cc65a2ad80f9d00`.

El materializador conserva el mutex de Account durante la transacción:
10.000 destinatarios admitidos generan 20.000 INSERT y lo retienen unos
214 segundos en esta muestra. Es una preocupación de rendimiento medible
para acciones de cuenta que comparten ese mutex. Se entrega a la lente D
sin ocultarla tras el verde funcional ni extrapolar un SLA de esta máquina.
No se ha optimizado ni cambiado el snapshot final para esta observación;
la revisión determinará los gaps, su gravedad y la corrección exigible.
T-12 continúa en-progreso y aún no se cierra su medidor de implementación.


## T-12: corrección del coste confirmado antes de la primera revisión

El handoff histórico liberó los recursos propios a
2026-10-23T11:15:34.910663+00:00; gates y teardown quedan sellados en su índice
original. Root ha contrastado fuente, consultas y tiempo del benchmark:
el patrón de dos INSERT por destinatario mantiene el mutex de cuenta
unos 214 segundos para 10.000 destinatarios admitidos. Se considera un
defecto de rendimiento reproducido que debe corregirse, sin esperar una
revisión independiente para descubrirlo otra vez.

El implementer retoma en exclusiva emailing_testing y las fuentes de T-12
para una corrección acotada del materializador y sus pruebas: inserciones
en lotes limitados, misma transacción, orden de locks, DB UTC, procedencia,
identidades, generación e idempotencia. Debe conservar la prueba de rollback
tras un INSERT real y añadir regresión de coste SQL, sin umbrales de tiempo
frágiles ni mecanismos de producto cuya única finalidad sea probar.

Los originales release.json, evidence-index.json, handoff y resultados
anteriores permanecen intactos; la corrección conserva evidencias nuevas
separadas. Se repetirán benchmark 100/200/10.000, focal declarado y suite
completa sobre una fuente final idéntica. Los verdes 16/143 y 157/1.633
continúan vinculados al snapshot anterior, no validan cambios posteriores.

La ventana de implementación T-12 sigue abierta; no se ha iniciado revisión
11, QA de fase 3 ni se reinicia un contador. Root mantiene ledger, medidor,
Git y publicaciones; no inicia otro actor SQL. La liberación histórica no
autoriza operaciones simultáneas mientras el implementer tiene de nuevo
la base reservada. T-12 en-progreso, total 9/20 y envío externo deshabilitado.


## T-12: RED de presupuesto SQL comprobado antes de escribir el bulk

Un único test: **1 failed / 3 assertions**, 16,20 s, exit 1. El snapshot RED
contiene exactamente el materializador anterior, comprobado por SHA contra
el focal final previo. La traza registra 100 destinatarios, 207 consultas y
presupuesto 26. Root verificó ZIP/25 fuentes/streams sin hallazgos de
privacidad. Manifest `5217ccb5783aaaecfba75df90635ca734a735bdf178f40318a225d6fee526598`.

Se conserva el cuerpo del test para contrastarlo con GREEN/final, sin
cambiar expectativas para lograr el verde. Esta evidencia aplica TDD a la
corrección concreta, no activa TDD global ni sustituye QA. La base sigue
reservada por el implementer; revisión 11 aún no iniciada y T-12 abierta.


## T-12: bulk verificado en regresión y benchmark; focal y suite pendientes

Regresión aislada final: **2 passed / 35 assertions**, 16,86 s, exit 0.
El método de presupuesto SQL conserva exactamente los mismos bytes entre
RED y GREEN final: SHA `8acd4ed7b3b83ef454e4d8df7c810ab7fa8802fc7e1c38d94427c734ee29702f`. El primer GREEN tiene CRLF; su cuerpo
es idéntico después de normalizar únicamente CRLF a LF. Se conservan los
originales sin sustituirlos ni afirmar igualdad raw de los tres. Los tamaños dan
100→9 consultas (presupuesto 26), 200→9 (27), 1.000→11 (35), con conteos,
UUIDs, generación e idempotencia. No se relajan expectativas para el verde.

El mecanismo inicial de trigger falló por SQLSTATE HY000 / código 1419
antes de crearlo; el fallo se conserva y no se ampliaron privilegios.
El primer GREEN usó CHECK por account_id y pasó. La fixture final usa type,
un campo no FK, y comprueba previamente outbox vacío para evitar depender
de restricciones de CHECK con acciones referenciales. Verifica un INSERT
de deliveries realmente ejecutado, rechazo SQL 3819 al insertar outbox,
cero filas de ambas tablas tras rollback y recuperación posterior. La
constraint con nombre propio se retira en finally; no es un hook de producto.

El materializador usa lotes de hasta 500 pares de filas, UUIDs previos al
INSERT y timestamps UTC de MySQL. Mantiene la transacción original, locks,
procedencia, generación y claves únicas; no publica ni envía. Manifest final
del GREEN y benchmark `1e8b97a03062a91f09b28ba787cd18749d2eacdb73387ad5d369b20fa4dd32db`.

Benchmark posterior, una muestra por tamaño en el mismo laboratorio:

| Destinatarios | Tiempo de materialize() | SELECT | INSERT | Total SQL |
|---|---|---|---|---|
| 100 | 0.0643694 s | 7 | 2 | 9 |
| 200 | 0.1311307 s | 7 | 2 | 9 |
| 10000 | 3.2206176 s | 7 | 40 | 47 |

Para 10.000 se pasa de 214,0082381 s y 20.007 consultas a
3.2206176 s y 47 consultas en
estas muestras. Preparación, comprobaciones, repetición y limpieza quedan
fuera del tiempo; no es capacidad SMTP ni SLA. Las filas son correctas,
repetir crea cero y quedan cero cuentas/usuarios propios del benchmark.
Root verificó los ZIP/25 fuentes y streams de ambos gates finales, sin
hallazgos de privacidad; originales previos e índice histórico inmutables.

Focal declarado OutboxIntegrationTest en curso sobre este snapshot; después
debe pasar la suite completa sin editar entre ambos. Los verdes previos
16/143 y 157/1.633 pertenecen a la fuente anterior. Pendientes focal/full,
cleanup/handoff y revisión 11 antes de commit/push. T-12 en-progreso,
total 9/20, medidor de implementación abierto y correo externo deshabilitado.


## T-12: focal final del bulk verde; suite completa en curso

`php artisan test --filter=OutboxIntegrationTest`: **17 passed / 173 assertions**,
156,46 s, exit 0. Ventana 2026-10-23T11:31:22.612169+00:00–2026-10-23T11:34:01.208933+00:00.
Root verificó ZIP/25 fuentes/streams y privacidad de estas salidas; manifest
`1e8b97a03062a91f09b28ba787cd18749d2eacdb73387ad5d369b20fa4dd32db`, stdout
`39f8dfb4900a769fa40ccb0d3fd8588668df810ed684a6d1d352315f35074d68`. Incluye regresión del bulk,
rollback SQL real y los casos de protocolo/concurrencia/recuperación previos.

La suite completa `php artisan test` arranca después, sin filtros ni cambios
de fuentes entre ambos. Root contrastó 193 entradas de ZIP/manifest:
154 fuentes y 39 JSON de ejecución en tests/.runtime. Las 189 entradas del
snapshot previo ya incluían 35 JSON de ejecución; la diferencia de inventario
son cuatro JSON TLS nuevos, no cuatro fuentes nuevas. Se aclara aquí que los
recuentos históricos llamados «189 fuentes» contaban entradas de captura.
Las 154 fuentes actuales y los 25 SHA focales coinciden. Los JSON de ejecución
se conservan privados, se distinguen del código y no se exige su igualdad
live ni se borran sin atribución demostrada. Manifest ampliado
`3d4f1ad799f39ab5d1239a47dbad3b125813440201558cf604eba9109664b1b3`. El cuerpo de la regresión de coste conserva
el SHA del RED/final; no se cambia su presupuesto para lograr el verde.

El implementer conserva el handle de la suite completa y la reserva exclusiva
de emailing_testing hasta terminal y cleanup. Este registro no afirma todavía
su resultado. Root no inicia pruebas/SQL simultáneos. Los 354 artefactos del
índice histórico anterior siguen intactos; la corrección tiene recibos nuevos.

Últimos controles administrativos: ledger-lint exit 0, cero incoherencias y
once avisos de Changelog de tareas todavía abiertas; 933 archivos públicos
sin hallazgos; tres documentos modificados con 60 destinos relativos y cero
enlaces rotos. No son auditoría de historial ni verificación del índice Git
futuro. El stream sensible original sigue privado y no se copia a docs.

Pendientes terminal full, cleanup/handoff final, cierre de medidor y revisión
11 antes de commit/push. No existe aún snapshot de revisión T-12, no se
reinician contadores y no se concede QA integral/M-01. Total 9/20, T-12
en-progreso, GOT-003/ADR-003 propuestas y correo externo deshabilitado.


## T-12: implementación final verificada y liberada para revisión 11

`php artisan test`, sin filtros: **158 passed / 1.663 assertions**, 529,06 s,
exit 0. Ventana 2026-10-23T11:34:43.100166+00:00–2026-10-23T11:43:34.219695+00:00.
Focal final: **17 passed / 173 assertions**, 156,46 s, exit 0; mismo manifest
de 25 fuentes que GREEN final y benchmark:
`1e8b97a03062a91f09b28ba787cd18749d2eacdb73387ad5d369b20fa4dd32db`. Full contiene 193 entradas de captura,
154 fuentes y 39 JSON de ejecución; los 25 SHA focales coinciden. Manifest
full `3d4f1ad799f39ab5d1239a47dbad3b125813440201558cf604eba9109664b1b3`, stdout
`63a71648b34d18b7f446604c2a2cb3370381031be2917a4acaf3e14d64c93996`. Las salidas finales verificadas no
presentan hallazgos de privacidad. El stream sensible histórico sigue privado.

El handoff final libera DB/fuentes a 2026-10-23T11:44:23.310708+00:00; cero
contenedores, colas, exchanges, cuentas de benchmark, directorios de fixture,
CHECKs y conexiones de prueba propias pendientes. Todos los handles propios
son terminales. No se atribuye ni elimina el PID histórico ajeno. Root
contrastó 184 artefactos nuevos, 354 históricos intactos y 90 recibos
con sus streams. Índice final SHA `7e5f2286d2a7e068b3037a053077929047ac9cb61924b4a8e5ad99d66271833f`. Las 154 fuentes
actuales coinciden; no se exige igualdad live de los JSON de ejecución.

Pint --test acotado posterior pasa sobre materializador y test cambiados;
las otras fuentes PHP conservan el snapshot previamente formateado. No se
presenta como Pint global: formato pendiente T-20 sigue registrado. No hay
cambios de assets ni build nuevo. Benchmark 10.000: 47 consultas / 3,2206176 s,
una muestra de laboratorio, sin extrapolar capacidad SMTP ni SLA.

Ventana de implementación 2026-10-23T09:55:42Z–2026-10-23T11:47:10Z; reloj
1h 51m, fuente estimado, tokens/euros/horas IA reales
NULL por falta de telemetría compatible. No se convierten horas de reloj en
consumo ni se reinician contadores. Queda preparada la primera revisión del
incremento T-12 (global 11), con A+B+D y fallback genérico/runtime heredado.
GOT-003 permanece propuesta y ADR-003 conserva la integración SMTP pendiente.

Pendientes revisión independiente, publicación del checkpoint y T-13/T-16
según sus criterios literales. Full y focal no equivalen a QA de fase 3,
E2E completo ni aceptación humana M-01. T-10/T-11/T-12 siguen en-progreso,
total 9/20 y correo externo deshabilitado. No se concede cierre anticipado.


## Revisión de dos lentes — intento 11: Fase 3 (T-12) — corregir selección global

Primer intento de tres del incremento. A+B+D frescas: **0 Critical,
1 Important (D-01), 0 Minor**. C false; D por migración. Scope exit 0,
18/18 en alcance, sin exclusiones/avisos/info. Patch
`2406d504e18ce1697e24a9780ea67937b1f78812c63d9bb99376c0e72dc05d5a`. Root contrastó 218 fuentes + 570 evidencias +
dos preimágenes + patch: 791 rutas únicas, cero discrepancias.

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| D-01 | Important | Claim global requiere resolver orden entre estados sin índice compatible por evento | T-12 | Pendiente de contraste SQL y fix1 | [Informe y tablas completas](testing/fase-3/review/attempt-11.md) |

Estimación D: 995.050 candidatos para 100 claims de 10.000 pendientes y
50.005.000 para drenarlos; no es EXPLAIN ni latencia medida. Se contrastará
en emailing_testing con fixtures propios antes de corregir/rebatir. Bulk
47 SQL/3,2206176 s sí aprobado como componente; no se reabre el histórico214s.
Focal17/173 y full158/1.663 pertenecen al snapshot revisado y no validan fix1.

La fusión corrige una errata de cobertura A sin modificar su dictamen:
el full154 tiene129 fuentes presentes en sources/ y25 resources leíbles en
ZIP; no138/16. Metadata y los tres informes completos se preservan. Evidencia
pública seleccionada59artefactos sin secretos; original sensible y ZIPs privados.

Revisión 2026-10-23T11:49:08Z–2026-10-23T12:03:29Z; reloj 14m,
tokens/euros/horas IA reales NULL. Sin Jira opt-in. GOT-003 y ADR-003 quedan
propuestas; T-10/T-11/T-12 siguen abiertas, total9/20, sin QA/E2E/M-01 concedidos.
Siguiente: implementación medida T-12-fix1 y segunda revisión, con traspaso de
las tablas completas y sin reabrir aprobados salvo evidencia nueva.


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

Triage real con fixture propio de 10.000, sin broker/SMTP y mismo PDO:
1/10/100 claims globales requieren 10.005/100.050/1.000.500 lecturas Handler.
EXPLAIN ANALYZE muestra Table scan con actual rows=10000 y Sort available_at,id
después de 100 resultados SQL completados. También se relee historial publicado;
las estimaciones de candidatos elegibles de D no se presentan como mediciones.
Las transiciones de completion y preparación quedan fuera de la medición.
Cero cuenta/usuario propios pendientes tras el triage; MySQL 8.4.11.

Regresión aislada RED: 1 failed / 86 assertions, 32,50 s, exit 1. Diez claims
con 100 pendientes requieren 1.050 Handler; con 10.000 más historial previo,
101.050, superando el presupuesto de crecimiento 4.700. No se mide solo número
de consultas ni se usa un tiempo frágil. Root verificó los ZIP/25 fuentes,
streams y trazas sin hallazgos de privacidad; dispatcher y migración del RED
son exactamente los de revisión 11. No había fix productivo antes del rojo.

Root aprueba corrección acotada: eligible_at generado será available_at para
pending, GREATEST(available_at,lease_expires_at) para publishing y NULL para
otros estados; índice eligible_at,id y selección hasta UTC SQL. Conserva el
conjunto elegible, valores nulos, fechas futuras y fencing sin sincronización
manual. El orden pasa explícitamente al instante de elegibilidad: un pendiente
antiguo puede preceder a una reserva recién vencida con available_at anterior.
Spec/diseño no prometen FIFO global. Se probarán leases/available futuros,
historial published, coste y SKIP LOCKED/concurrencia real. No se altera SMTP.

El implementer conserva DB/handles; revisión siguiente será global12, intento
2/3 del mismo incremento. Originales y revisión11 inmutables; verdes17/173 y
158/1.663 todavía solo validan la fuente previa. T-12 sigue abierta, total9/20.


## T-12-fix1: selección indexada y regresiones verdes; gates completos pendientes

GREEN final post-Pint: **3 passed / 120 assertions**, 44,16 s, exit 0.
Manifest25 `ebffe25a95fe99ca6848a26484ea13a2733bc21ee494a91a883d3b6af4e30e95`. El cuerpo del test de trabajo es
idéntico RED/primer GREEN/GREEN final después de normalizar solo CRLF→LF;
se conservan hashes raw separados y no se afirma igualdad del archivo entero.
Presupuestos y expectativas no se relajan. Diez claims: 100 pendientes→132
Handler; 10.000 con historial→30, frente a RED1.050/101.050.

Benchmark propio de selección global: 1/10/100 claims requieren3/30/300
Handler frente a10.005/100.050/1.000.500 anteriores. EXPLAIN ANALYZE muestra
range sobre eligible_at y actual1fila, sin Sort ni Table scan. Con10.000
published, no hay claim y solo1Handler; con9.999published y un pendingfuturo,
también1; reactivar un evento debido requiere3. Son contadores internos y
plan real, no número de consultas, capacidad SMTP ni un SLA. Preparación y
transiciones SQL de completion son fixtures sin broker, fuera de medición.

Las tres regresiones cubren fechas/leases/nulos/historial y dos procesos
globales que completan reclamaciones distintas mientras el padre retiene
el primer candidato con lock SQL. Al liberar ese lock se reclama el evento
restante. Esto acredita selección/concurrencia del outbox, no dos workersSMTP.
Root verificó ZIP/25fuentes/streams y trazas sin hallazgos de privacidad;
cleanup del benchmark cuenta/usuario cero. CAS y publicación conservan su código.

La fecha generada aplica la política documentada de elegibilidad y evita
sincronización manual. La migración/downup y todos los protocolos previos
se verificarán de nuevo en focal completo, seguido de suite global sin editar.
Esas ejecuciones y la segunda revisión12 siguen pendientes. D-01 se considera
corregido a nivel de componente, no cerrado por revisión todavía. T-12 abierta,
total9/20, medidorT-12-fix1 abierto y correo externo deshabilitado.


## T-12-fix1: suite completa verificada y liberación para revisión 12

`php artisan test`, sin filtros: **161 passed / 1.783 assertions**, 980,81 s,
exit 0. Focal final: **20 passed / 293 assertions**, 267,55 s. Ambas salidas,
recibos y ZIP fueron contrastados por root. Las 154 fuentes actuales coinciden
con el full final; los 25 SHA focales coinciden con regresión final, benchmarks,
focal, full y Pint. El nuevo full excluye tests/.runtime: 154 fuentes y cero
JSON efímeros. Los históricos 189/193 conservan sus capturas sin modificarse.
Manifest focal `ebffe25a95fe99ca6848a26484ea13a2733bc21ee494a91a883d3b6af4e30e95`; full
`3061d2703af95cc1037f866d0a86c7ed64c98e3312c52d7495019cfbf778ce9a`. No hay hallazgos de privacidad en las
salidas actuales verificadas; el stdout sensible histórico permanece privado.

Pint --test sobre las cuatro fuentes corregidas pasa; no equivale a Pint global.
Materializador actual: 9/9/47 consultas para 100/200/10.000; última muestra
0,278640/0,3224535/6,9351635 s. Las muestras anteriores conservan su fecha y
fuente; no son una comparación controlada de latencia ni capacidad SMTP.
Root contrastó 245 artefactos nuevos y 538 históricos intactos. Índice nuevo
SHA `769614068b6898574dbbec1cb1272cd0c9e1089d33ab62000ed6fa6362be4ea9`: 14 runs y 22 hijos. El primer helper de auditoría
falló antes del sellado al asumir un esquema de índice antiguo; no se guardaron
sus streams originales. La limitación está explícita, sin recibo reconstruido;
el helper corregido verifica ambos esquemas. No fue una suite ni un intento QA.

DB y fuentes liberadas a 2026-10-23T12:45:53.166976+00:00; cero recursos propios
pendientes y todos los handles propios terminales. Se conserva el PID histórico
ajeno sin afirmar su estado actual. Los gates de limpieza verifican contenedores,
colas, exchanges, fixtures, cuentas, CHECKs y conexiones de prueba propias cero.
Control público del worktree: 998 archivos y cero hallazgos; no acredita historial
Git ni el futuro índice. No se realizaron operaciones SQL por root.

Ventana de implementación 2026-10-23T12:05:50Z–2026-10-23T12:47:58Z, reloj
42m; fuente estimado, tokens/euros/horas IA reales
NULL por falta de telemetría. Siguiente paso: revisión global 12, intento acotado
2/3 del mismo T-12, con tablas completas del intento 11 y sin reiniciar contador.
D-01 corregido, pendiente de dictamen independiente. GOT-003 y ADR-003 siguen
propuestas. T-10/T-11/T-12 permanecen abiertas, total 9/20; full backend no cierra
E2E-08/09, QA de fase 3, workers, M-01 ni validación operativa. Correo externo
deshabilitado: Mailpit/log solamente.


## Revisión de dos lentes — intento 12: Fase 3 (T-12) — D-01 corregido, componente aprobado

Segundo intento de tres del mismo T-12, sin reiniciar contador. A+B+D frescas:
**0 Critical / 0 Important / 0 Minor actuales**. C false, D por migración;
scope exit 0, 83/83 en alcance, sin exclusiones, avisos ni info.

| # | Grado original | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| D-01 | Important | Selección global con scan+sort repetido | T-12 | Corregido; aprobado por A+B+D | [Fusión y tres tablas completas](testing/fase-3/review/attempt-12.md) |

El predicado y orden eligible_at,id usan un índice compatible; la fecha deriva
del estado/available_at/lease. Orden de elegibilidad explícito, sin promesa FIFO
global. 100 claims sobre 10.000 pasan de 1.000.500 a 300 lecturas Handler;
historial publicado/futuro requiere una lectura vacía. Plan real range sin Sort.
Regresión significativa RED→GREEN con método LF idéntico, fechas/leases y
SKIP LOCKED real. No se confunden contadores con consultas o capacidad SMTP.

Fuentes finales verificadas: focal20/293 y full161/1.783, exit 0; regresión3/120,
Pint acotado0. Full154/0runtime, comparación129directas+25resources ZIP. No se
repite la errata138/16 anterior. Materializador47SQL/6,9351635s para10.000 es
la última muestra;3,2206176s queda histórico. Sin comparación temporal controlada.
Paquete inmutable1.135archivos comprobados antes/después; patch
`93c5525c37f1c49b1bd54376009fd0dc4f684959dfc24b02febc165f5f596e3a`. Se conservan íntegros A/B/D, patch y metadata, más
66 evidencias públicas verificadas sin secretos. Limitación del helper audit
sin streams y captura inicial tardía de helpers expresas, sin recibos fabricados.

Revisión 2026-10-23T12:49:52Z–2026-10-23T13:04:24Z; reloj 15m.
Tokens/euros/horas IA reales NULL. GOT-003 aceptada para su método de barreras;
ADR-003 sigue propuesta, pues faltan worker/SMTP/cuotas/retries/scheduler.
Ningún pendiente anterior se reabre sin evidencia ni se convierte en aprobado.
T-10/T-11/T-12 permanecen abiertas, total9/20, E2E08/09/QA/M01 pendientes.
Siguiente: publicar checkpoint revisado y T-13 completo con Mailpit/log.


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

Tras la confirmación terminal de su propietario, root verifica recibos, hashes
de ambos streams y las 180 entradas de cada ZIP contra su manifiesto externo.
Los dos diagnósticos usan el manifiesto SHA256
`95d7b3b4b053abe2b24389016bddde8d5c699ea45e2a1317b283c0ee3d406039`:

- `worker-diagnostic-20261023T133439857988Z`: exit 2, **5 failed, 1 passed
  (15 assertions)**, 66,19 s. Se conserva el intento limitado por permisos de
  Docker; no demuestra un fallo de los escenarios Linux que no llegaron a arrancar.
- `worker-diagnostic-docker-20261023T133623588306Z`: exit 0, **6 passed
  (92 assertions)**, 125,94 s. Recursos propios terminados según el owner.

Los streams verificados no producen etiquetas del comprobador de datos privados.
El verificador privado de T-13 se adapta al esquema real del ZIP: contiene
exactamente las entradas del manifiesto externo, sin manifiesto embebido;
rechaza duplicados y verifica todos los hashes. Sus dos primeras ejecuciones
fallaron por asumir el esquema anterior y no generaron un resultado de producto.
Las comprobaciones correctas son históricas: no afirman igualdad con las fuentes
actuales ni sustituyen la suite completa o la revisión independiente.

La ampliación `worker-expanded-20261023T134358019318Z` sigue a cargo del mismo
owner, con fuentes congeladas y acceso exclusivo a `emailing_testing`. Incluye
más ventanas de fallo SMTP/SQL/ack, pausas, cuotas y políticas vigentes. No hay
resultado global terminal ni liberación de la base en este checkpoint. Quedan
además la prueba CLI/consumo continuo y la relación entre timeout SMTP y lease.
La revisión estática señala también que una espera por locks de cuota podría
cruzar una ventana: se debe comprobar la época después de adquirir los locks.
No se modifica runtime durante una ejecución ni se cierra T-13. Total **9/20**.

### Resultado terminal del diagnóstico ampliado

El propietario confirma el final del mismo proceso, sin reiniciarlo:
`worker-expanded-20261023T134358019318Z`, exit 1, **16 passed, 1 failed
(357 assertions)**, 542,98 s. Root verifica los hashes del recibo, ambos streams
y las **182 entradas** del ZIP; manifiesto SHA256
`7df22374557d7eee1b7cc80dcc1f6d628c3bcdaa9f1893755d57170e875fe51a`.
Los streams no producen etiquetas del comprobador de datos privados. El fallo
se localiza en la comparación de fecha del test de sesión SQL no UTC: la fecha
persistida incluye precisión de microsegundos. Se conserva el rojo y se corregirá
la expectativa antes de repetir; este diagnóstico no es el gate final.

El owner informa de teardown de sus workers/relay/proxy y colas propios, pero
mantiene la exclusividad de la base y fuentes para completar los casos pendientes.
La inspección del ZIP también detecta que `fn () => $stop` captura el valor inicial
en el comando del worker e impide observar el cambio del handler de SIGTERM.
Se requiere cierre con estado actualizado y prueba Linux de parada real. Quedan
la revalidación de la ventana tras locks y los límites SMTP/lease; no se toma el
verde de los otros escenarios como aprobación global. T-13 sigue en progreso.

### Diagnóstico CLI y reloj en verde; congelación final pendiente

`cli-clock-diagnostic-20261023T135747336425Z` termina exit 0: **5 passed
(138 assertions)**, 251,37 s. Root verifica recibo, streams y **183 entradas**
del ZIP, manifiesto SHA256
`285f3e15c77b2e2e3f9a286d6d3a8a4757a5a1da791ce60686cd6bed9f4d0a69`.
Sin etiquetas del comprobador de datos privados. El owner confirma procesos
propios terminales y limpieza de sus colas/relay/locker; conserva la base para
los gates finales y todavía no libera fuentes.

Los casos incorporan consumo continuo con dos workers y prefetch 1, CLI Linux
con SIGTERM, límite de timeout SMTP, sesión SQL +05:30 y un proceso que mantiene
un lock de pool hasta cruzar el minuto UTC. La cuota revalida la época tras los
locks y no carga la ventana obsoleta. El arranque SQL añade CAS de token,
generación, estado y lease en el propio UPDATE: si la espera venció la reserva,
revierte cuota, capability y mensaje antes de reconciliar. Estos cambios todavía
requieren el conjunto completo con las fuentes finales.

El alcance de T-13 explicita `.env.example` para reproducir la configuración
pública y `docs/aplicacion/**`, la ubicación real de las guías. Root añade
[entrega y recuperación](../../aplicacion/entrega-y-recuperacion.md), marcada como
implementación en validación: outbox, estados, ack, reintentos, cuotas y límites
de SMTP. Sus cuatro enlaces relativos existen y no aparecen etiquetas privadas;
el índice de docs tiene 24 enlaces relativos válidos. La revisión formal incluirá
esta documentación. No se promociona ADR-003 ni se cierra QA de fase 3. Total 9/20.

### Inicio del gate completo del consumidor

El formatter acotado `scoped-format-20261023T140332428447Z` termina exit 0.
Root comprueba recibo/streams y sus 184 entradas de snapshot; no se atribuye a
todo el repositorio un formato global limpio. El owner congela runtime/config/
tests e inicia `worker-final-frozen-20261023T140435704223Z` con el comando exacto
`php artisan test --filter=WorkerRecoveryTest`. Todavía no hay resultado terminal.

Durante esa ejecución, la lectura estática identifica tres clases legacy que
usan `RefreshDatabase` alrededor de SMTP real: MailTransportTest, TemplateTest y
SuppressionTest. Su transacción exterior es incompatible con el nuevo guard.
Tras el terminal se adaptará únicamente su aislamiento a `DatabaseMigrations`,
manteniendo el guard de `emailing_testing`; no se debilitará la barrera de commit.
La suite completa posterior usará el nuevo snapshot y repetirá el consumidor.
No se atribuirá retrospectivamente el recibo actual a un manifiesto posterior.

Las dos subtareas técnicas de T-12 se marcan comprobadas conforme a su revisión
12 y checkpoint ya publicado: estado/dispatcher y cortes/confirms/routing.
Sus criterios integrales siguen abiertos hasta reconciliar la evidencia del
consumidor. Las integraciones restantes de T-10/T-11/T-12 se evaluarán por su
texto literal, sin atribuirles un E2E o aceptación humana todavía no ejecutados.
T-13 continúa en progreso; revisión 13 todavía no iniciada, medidor abierto.

### Gate exacto del consumidor: verde, suite global en curso

`worker-final-causal-frozen-20261023T144915041057Z` termina exit 0 con el
comando exacto `php artisan test --filter=WorkerRecoveryTest`: **20 passed
(473 assertions)** en 1254,12 s. Root verifica el recibo terminal, los hashes
de stdout/stderr y las 185 entradas del ZIP contra el manifiesto SHA256
`357d94d511c161e7c71bacd11563a1b370abaf84a24a523e46c5d6b1de794a6e`.
Stderr vacío y cero etiquetas del comprobador de privacidad en ambos streams.
Este recibo acredita esa ejecución y ese snapshot; conserva los rojos anteriores
como diagnósticos y no los transforma en resultados aprobados.

El owner inicia después `full-final-frozen-20261023T151051959342Z` con
`php artisan test`, de forma secuencial y sin modificar las fuentes. Ambos
requests declaran el mismo manifiesto. Al escribir este checkpoint el owner
confirma mediante polling que la suite sigue activa; todavía no hay resultado
terminal global. La base de pruebas y los procesos continúan bajo su propiedad
exclusiva hasta la comprobación nativa, el censo de recursos y su liberación
explícita. Root no ejecuta SQL ni otra suite en paralelo.

La revisión independiente 13, el QA de fase 3 y la publicación de T-13 siguen
pendientes. Los criterios integrales de T-10/T-11/T-12 se reconciliarán con su
texto literal durante esa revisión. No cambia el total formal: **9/20**.

### Suite global de T-13: cuatro rojos conservados para diagnóstico causal

`full-final-frozen-20261023T151051959342Z` termina con recibo exit 2:
**177 passed, 4 failed (2250 assertions)** en 3200,07 s. Root comprueba el recibo,
ambos streams y las 185 entradas del snapshot contra el mismo manifiesto
`357d94d511c161e7c71bacd11563a1b370abaf84a24a523e46c5d6b1de794a6e`.
Stderr vacío y cero etiquetas del comprobador de privacidad. Este resultado
contradice un verde global; el gate exacto anterior no lo sustituye.

Los cuatro síntomas observados son:

- `OutboxIntegrationTest::test_incremental_migration_down_up_is_repeatable_and_keeps_campaign_snapshots`:
  MySQL 1091 al eliminar el índice `delivery_recovery`.
- `WorkerRecoveryTest::test_legacy_test_send_and_worker_share_atomic_account_and_pool_quota`:
  diagnóstico `null` donde se esperaba `quota_wait`.
- `WorkerRecoveryTest::test_quota_windows_and_wait_are_utc_even_with_non_utc_mysql_session`:
  el identificador de ventana observado difiere del esperado en un minuto.
- `WorkerRecoveryTest::test_real_pool_lock_crossing_utc_minute_defers_without_charging_stale_windows`:
  la barrera del locker no proporciona bytes válidos antes de finalizar la espera.

Se aplica `custom-agents:debug-root-cause` fuera del gancho de tres intentos QA:
reproducción mínima, aislamiento con descartes, hipótesis probada y corrección
con regresión. Todavía no se declaran probadas las causas de esos síntomas.
Antes de cualquier ejecución o cambio se censan y resuelven los hijos y recursos
atribuibles al rojo. No se amplían umbrales ni se reinicia la suite a ciegas.
Tras las correcciones verificadas se ejecutará otra suite completa sobre sus
fuentes finales. La revisión 13 sigue sin comenzar; T-13 continúa en progreso.

### Diagnóstico causal: enrutamiento reproducido y orden de migraciones aislado

La observación de catálogo posterior al rollback
`diagnostic-state-after-red-20261023T161526056580Z` termina exit 0 y acredita,
en ese momento, sólo la tabla `migrations`, sin migraciones registradas ni
tablas de negocio. La consulta del processlist devuelve únicamente la propia
consulta del diagnóstico; no se extrapola esa selección a otros usuarios.
Es una observación histórica de la base de pruebas, no un gate de producto.

`routing-collision-baseline-20261023T171136264002Z` termina exit 0 y reproduce
el candidato con broker real y topología propia. Un binding durable de fallos
ya existente recibe un payload `delivery.ready` cuando la clave normal se
configura igual que la de fallos. Se observan ack y confirm, outbox `published`,
cola normal vacía y entrega todavía `pending`, generación 1, cero intentos y
cero `MailMessage`. No se ejecuta transporte. La hipótesis queda probada:
mandatory/confirm acredita enrutamiento a alguna cola, sin distinguir su rol.
La corrección propuesta rechaza la colisión de ruta o binding normal con la
clave de fallos antes del IO; la publicación debe conservar pendiente/backoff
con un diagnóstico tipado y el consumidor debe fallar antes de bind. Aún faltan
su regresión y los gates sobre el código corregido.

`migration-minimal-red-20261023T171308956852Z` ejecuta únicamente la prueba
incremental de OutboxIntegrationTest y termina exit 2: **1 failed
(9 assertions)**, 29,74 s, con el mismo MySQL 1091. Su secuencia baja/recrea
la migración 000009 directamente mientras 000010 continúa registrada; al
restaurar sólo la tabla de la migración anterior faltan las columnas e índice
que la migración dependiente espera retirar en el teardown. Se debe respetar
el orden de dependencias de la prueba, sin debilitar la migración de producto.

Root comprueba recibos, streams y snapshots de las tres ejecuciones:
185 entradas, manifiesto
`357d94d511c161e7c71bacd11563a1b370abaf84a24a523e46c5d6b1de794a6e`,
stderr vacío y cero etiquetas del comprobador de privacidad. Se conservan las
observaciones y el rojo mínimo; no se les atribuye un resultado de regresión
verde. En ese punto los casos temporales continuaban en diagnóstico. Total
formal: **9/20**.

### Hipótesis temporales probadas; regresiones corregidas pendientes de verde

`boundary-causal-diagnosis-20261023T171809481664Z` termina exit 0 sobre el
snapshot anterior de 185 fuentes. Es diagnóstico controlado, no gate de
producto. Su protocolo confirma que reconstruir 000009 mientras 000010 sigue
registrada elimina el índice dependiente y reproduce MySQL 1091. Con sesión
MySQL `+05:30`, una llamada posterior al cambio real de minuto utiliza la
ventana UTC comprendida entre sus consultas de inicio y fin; la expectativa
tomada antes de la frontera pertenece al minuto anterior. Dos envíos log en
minutos consecutivos consumen una unidad cada uno en ventanas distintas:
el segundo envío puede continuar legítimamente. Root verifica la coherencia
de esos valores, el hash del helper capturado y la referencia al protocolo en
el stdout cuyo hash consta en el recibo padre. El recibo auxiliar carece de timestamp
independiente de fin y hashes originales de sus observaciones; se conserva
esa limitación y su snapshot, sin atribuirle un verde de regresión.

`routing-regression-red-20261023T172253884378Z` incorpora primero la prueba
de colisión y termina exit 1: **1 failed (3 assertions)**, 19,05 s. Antes de
la corrección intenta conexión y devuelve `publish_uncertain`, en lugar de
rechazar la configuración con `routing_key_collision`. Se conserva su
manifiesto de 185 fuentes
`997fcc6c324984f62f47efbdfde577d27b596a6d7074a4663c2913a983c1907b`;
no se atribuye este rojo a las fuentes anteriores ni a las corregidas.

El implementer comunica aplicados el guard previo al IO del publisher y
consumidor, el orden dependiente correcto en la prueba de migraciones y
las correcciones de sincronización temporal. No cambia la migración de
producto ni DeliveryQuota. Faltan los focales verdes, el gate exacto, la
suite global final, el smoke nativo y la liberación explícita de recursos
antes de la revisión independiente. T-13 sigue en progreso; **9/20**.

### Cinco focales causales verdes sobre una nueva foto de fuentes

`causal-five-focal-20261023T173841307602Z` termina exit 0: **5 passed,
116 assertions**, 149,09 s. Root verifica recibo, hashes de ambos streams y
185 entradas del ZIP contra el manifiesto
`595805bea9db7398973b45528a11cc57f58bd86026e5f24a49becc9ab98f9029`;
stderr vacío y cero etiquetas del comprobador de privacidad. Incluye las
regresiones de migraciones, cuota compartida, sesión MySQL no UTC, colisión
de claves y lock real que atraviesa la frontera de minuto.

La prueba de colisión comprueba ruta y binding por separado, cero conexiones
al listener y ningún paso de publicación. El outbox permanece pendiente con
backoff; al restaurar claves válidas publica y produce un único intento log.
En el caso de frontera real, los registros capturados muestran renovaciones
de la misma reserva y una caducidad posterior al desbloqueo: la espera no se
confunde con una reserva vencida. Antes de continuar se observa `quota_wait`,
cero intentos y cero cuota cargada; el siguiente evento puede continuar.
Root inspecciona esos métodos desde el ZIP de la ejecución y las observaciones
temporales, sin consultar SQL ni tocar procesos del implementer.

Son focales sobre fuentes nuevas, no un verde global. El comprobador Pint
acotado anterior pasa, y se preparan las suites OutboxIntegrationTest y
WorkerRecoveryTest completas, otra suite global y la comprobación nativa
final antes del handoff. Revisión 13 aún no iniciada; total **9/20**.

### Outbox y worker completos verdes; nueva suite global todavía en ejecución

`outbox-regression-causal-final-20261023T174237942409Z` termina exit 0:
**20 passed, 299 assertions**, 344,99 s.
`worker-exact-causal-final-20261023T174853241748Z` ejecuta el comando literal
`php artisan test --filter=WorkerRecoveryTest` y termina exit 0:
**21 passed, 429 assertions**, 611,58 s. Root verifica ambos recibos, streams
y 185 entradas de cada ZIP contra el mismo manifiesto
`595805bea9db7398973b45528a11cc57f58bd86026e5f24a49becc9ab98f9029`;
stderr vacío y cero etiquetas del comprobador de privacidad. La comparación
estática de métodos conserva los 20 casos anteriores y añade el de colisión;
esto no se presenta como una medida de cobertura de aserciones.

El owner inicia `full-causal-final-frozen-20261023T175935140900Z` sobre esas
fuentes. Un poll autoritativo informa de FAIL parcial en
`ConcurrentCampaignTest::test_authorization_waiting_on_mysql_account_mutex_observes_committed_scope_revocation`.
La suite sigue activa y todavía no tiene detalle final ni recibo terminal.
Se conserva la ejecución y no se atribuye una causa a la duración del caso.
Después del resultado terminal se resolverán recursos propios y se aislará
el fallo antes de corregir o repetir. Estos gates focales no sustituyen un
verde global; revisión 13, cierre de T-13 y publicación siguen pendientes.

Resultado posterior de esa misma ejecución: terminal con **181 pruebas correctas,
1 fallida y 2221 aserciones**, en 2606,89 s, sobre las mismas 185 fuentes.
Root verificó el recibo, los hashes de stdout/stderr y el ZIP del snapshot.
Los 21 casos de recuperación del consumidor pasan. La excepción final es
`Directory not empty` al ejecutar `rmdir` en el `finally` de la prueba de
revocación concurrente; esa excepción podría ocultar una anterior y no demuestra
que sus aserciones funcionales llegaran a ejecutarse. La reproducción aislada
sin cambios pasa con 1 prueba y 6 aserciones; no reemplaza el resultado global.

El aislamiento con archivos propios en Windows reproduce `unlink` correcto
seguido de `rmdir` fallido mientras permanece abierto un handle que permite
eliminar el archivo; tras cerrar ese mismo handle, el directorio se elimina.
Esto demuestra una fragilidad de la limpieza, sin identificar al actor del
fallo histórico. Se prepara una regresión que preserve por separado el error
primario y el de limpieza. Antes de esa nueva reproducción, el censo registró
296 identidades propias sin contenedores presentes ni helpers nativos
coincidentes; es una observación histórica, no una liberación del entorno.
T-13 continúa abierta y requiere corrección causal, suite global final, smoke,
liberación explícita y revisión independiente antes de publicar.

La adaptación posterior modifica únicamente `ConcurrentCampaignTest.php` y su
helper `campaign-action.php`: conserva los plazos anteriores, observa el hilo
MySQL propio ejecutando `accounts ... FOR UPDATE` mientras el padre mantiene el
mutex, y registra por separado error primario y errores de asentamiento. Las
barreras del hijo se publican mediante rename y la limpieza usa el retry acotado
demostrado por la regresión Windows. El focal completo termina con **5 pruebas
y 34 aserciones correctas**; se verifica la secuencia espera SQL → commit de la
revocación → rechazo 403 sin autorización ni auditoría → limpieza sin errores.

Las 185 fuentes finales tienen manifiesto
`32f798edf22b2e2385fa4846953c25814cdf79160aaa4c31d7cece8bdcd3996f`.
El formatter acotado a 26 PHP pasa en modo comprobación. Root confirma que
runtime, configuración, esquema y pruebas Worker/Outbox conservan sus bytes;
los recibos anteriores de esos componentes siguen siendo históricos.
Se inicia `full-campaign-final-frozen-20261023T190743204457Z` con
`php artisan test`, que repetirá también esos componentes sobre las fuentes
finales. Todavía no se declara verde global ni se inicia revisión 13.

Resultado terminal posterior: **183 pruebas y 2227 aserciones correctas**, en
1403,46 s, con stderr vacío y el mismo manifiesto `32f798…3996f`. Root verifica
los 185 miembros del ZIP y los streams; el resultado contiene los 21 casos
WorkerRecovery, 20 OutboxIntegration y 5 ConcurrentCampaign.

El inventario incluye 184 fuentes/contextos del proyecto y el runner privado
que produce los snapshots. Ese productor se conserva como evidencia y no se
incorpora al diff público de la aplicación; los hashes originales no cambian.

La prueba de
revocación registra espera SQL, commit, aserciones completas y asentamiento sin
error primario ni secundario. No se sustituyen los recibos históricos por otros
de fuentes posteriores.

El smoke nativo de Windows completa ocho comandos: dos ejecuciones reales de
`deliveries:work --once` confirman y consumen el original y su replay, conservando
una entrega logged, un intento, generación 1, el mismo MailMessage y el mismo
hash del artefacto; el contador del listener SMTP es cero. Se eliminan su
topología y artefacto propios y quedan diez contadores de negocio y reservas
en cero dentro de `emailing_testing`. Root verifica comandos, recibos, helpers,
ZIP y observaciones; no ejecuta otra tanda SQL para reinterpretar el resultado.

El censo final verifica 342 identidades de contenedores propias ausentes y cero
helpers nativos coincidentes, con 30 ejecuciones atribuidas y tres orígenes TLS.
Es una observación fechada; el proceso histórico excluido no se inspecciona.
El implementer libera explícitamente fuentes y base de pruebas, con todos sus
handles terminales. T-13 sigue abierta hasta revisión independiente. La memoria
de la eliminación pendiente de Windows se registra como GOT-004 propuesta,
complementaria a GOT-002/003; su promoción requiere esa revisión. La retro final
revisará el umbral de memoria porque la iniciativa acumula cuatro gotchas.

El medidor de implementación T-13 queda cerrado antes de la revisión: fuente
estimada, sin tokens, euros ni horas IA reales disponibles. Su intervalo de
reloj no se presenta como esfuerzo IA medido; tampoco permite separar con
exactitud la preparación aislada de T-14/T-15. La revisión tendrá su propia
ventana. La comprobación de documentación cubre también GOT-004 y su índice.

### Documentación de perfiles: separar ambas familias de colas

La inspección del Compose congelado de T-13 muestra cuatro servicios:
`emailing-php`, `emailing-web`, `emailing-worker` (dominio) y `emailing-jobs`
(Laravel). La receta QA anterior solo cambiaba `RABBITMQ_QUEUE`; aplicada al
consumidor nuevo habría conservado referencias de dominio operativas. Root
actualiza la guía de desarrollo y la integración de infraestructura: web y ambos
consumidores reciben la cola de jobs QA, exchange/cola/fallos de entregas QA y
pool QA. El nombre del proyecto Compose por sí solo no separa colas.

La receta YAML se extrae a un fixture privado y se comprueba mediante
`docker compose ... config --format json`, exit 0. La configuración se analiza
en memoria: los cuatro servicios son los esperados, los tres servicios PHP usan
`emailing_testing` y las referencias de colas/pool coinciden con los valores QA.
No se arrancan servicios ni se consulta SQL; la salida completa con credenciales
no se escribe ni publica. Se conserva un reporte privado depurado con hashes.
Esta comprobación de configuración no acredita build, arranque, salud ni E2E del
Compose nuevo. Los recibos de fases anteriores siguen describiendo su versión.

### Gate completo del consumidor: un límite temporal por diagnosticar

El mismo `worker-final-frozen-20261023T140435704223Z` termina exit 1:
**19 passed, 1 failed (477 assertions)**, 1.118,65 s. Root verifica las 184
entradas, recibo y streams contra el manifiesto `4a28f1401efd59c80556dad7be2a91a453e167dbdf88ea3e21ac5a2eccd3b168`;
sin etiquetas del comprobador de privacidad. El owner confirma limpieza de sus
recursos y mantiene propiedad de la base/fuentes para completar el diagnóstico.

La única prueba roja mide desde la señal `sending` hasta el final del proceso:
15,5865 s frente a una expectativa de 12 s. Esa medición incluye SMTP, limpieza,
persistencia y arnés; por sí sola no demuestra que el timeout de operación de
5 s falle. Se requieren tiempos causales por etapa antes de corregir código o
expectativa. Las aserciones posteriores de estado ambiguo, aceptación observada
y liberación del relay no se ejecutaron en ese caso y no se cuentan aprobadas.
Se conserva el rojo, sin aumentar el umbral a ciegas ni debilitar el guard SQL.
La suite global y la revisión independiente siguen pendientes. Total 9/20.

### Diagnóstico por etapas: timeout comprobado, dos regresiones pendientes

`causal-and-legacy-20261023T142913651950Z` termina exit 2: **14 passed,
2 failed (208 assertions)**, 534,20 s. Root verifica recibo/streams y las
185 entradas, manifiesto SHA256
`3513f0112ae7e4a11d26633fc8bc9e3e3b72cd113fa59b0d2e437b9d6f4d8795`;
sin etiquetas del comprobador de privacidad. La instrumentación SMTP nueva es
inerte por defecto; sus llamadas aportan etapas conocidas, códigos numéricos,
flags, timeout y tiempos monotónicos. No incluyen comandos, respuestas, AUTH ni
cuerpos.

La prueba causal del timeout pasa: configuración efectiva de 5 s, espera de
DATA final de 5,004756108 s y limpieza de 5,003676919 s. Comprueba además el
resultado ambiguo y las invariantes de lease/estado antes de liberar el relay.
No se aumenta el umbral total de 12 s: se sustituye aquella medición conjunta
por observaciones de las fases que realmente se pretendía verificar.

El primer rojo confirma un cambio de contrato legacy: el remitente ajeno produce
NotFoundHttpException donde se esperaba ModelNotFoundException. Se restaurará
`findOrFail` en esa rama, conservando el tratamiento de bloqueo del consumidor.
El segundo rojo es una espera de readiness de SuppressionStartTest: el marcador
aparece después del plazo y su factory puede lanzar antes de registrar el handle
en el bloque de limpieza. Se deben atribuir y resolver los hijos afectados,
preservar los recibos ausentes como limitación y corregir el arnés con evidencia
antes de otra ejecución. No se debilitan guards ni se reescriben helpers sellados.

La observación auxiliar SQL del owner se verifica por hashes del helper y de
ambos streams; su esquema PowerShell usa hashes en mayúsculas. El freezer privado
acepta ese esquema explícito normalizando sólo la representación hexadecimal y
lo clasifica como observación de sólo lectura, nunca como un gate de producto.
Los primeros intentos de root con comparación de casing literal no generaron
un informe; la verificación corregida no ejecutó SQL ni imprimió su salida cruda.
La base y las fuentes todavía no están liberadas. Total 9/20.

### Inicio del gate completo del consumidor

El formatter acotado `scoped-format-20261023T140332428447Z` termina exit 0.
Root comprueba recibo/streams y sus 184 entradas de snapshot; no se atribuye a
todo el repositorio un formato global limpio. El owner congela runtime/config/
tests e inicia `worker-final-frozen-20261023T140435704223Z` con el comando exacto
`php artisan test --filter=WorkerRecoveryTest`. Todavía no hay resultado terminal.

Durante esa ejecución, la lectura estática identifica tres clases legacy que
usan `RefreshDatabase` alrededor de SMTP real: MailTransportTest, TemplateTest y
SuppressionTest. Su transacción exterior es incompatible con el nuevo guard.
Tras el terminal se adaptará únicamente su aislamiento a `DatabaseMigrations`,
manteniendo el guard de `emailing_testing`; no se debilitará la barrera de commit.
La suite completa posterior usará el nuevo snapshot y repetirá el consumidor.
No se atribuirá retrospectivamente el recibo actual a un manifiesto posterior.

Las dos subtareas técnicas de T-12 se marcan comprobadas conforme a su revisión
12 y checkpoint ya publicado: estado/dispatcher y cortes/confirms/routing.
Sus criterios integrales siguen abiertos hasta reconciliar la evidencia del
consumidor. Las integraciones restantes de T-10/T-11/T-12 se evaluarán por su
texto literal, sin atribuirles un E2E o aceptación humana todavía no ejecutados.
T-13 continúa en progreso; revisión 13 todavía no iniciada, medidor abierto.


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

## Revisión de dos lentes — intento 14: Fase 3 (T-14) — una corrección requerida

Primera pasada acotada **1/3** para el incremento T-14. Lentes frescas **A+B+D**
en paralelo, mediante fallback genérico y runtime heredado. El tier declarado
por frontmatter es opus/high; no se afirma ejecución de Claude/Opus. Scope previo:
21/21 ficheros, sin fuera de alcance, avisos ni exclusiones efectivas. Selector
automático: C=false, D=true por la migración, sin forzar configuración.

La cápsula `storage/phase3-t14-review1` conserva 2766 entradas, 782 fuentes de
contexto y 1958 evidencias; patch
`238dd30a77e00290d55df57abfaaca143d377a23191cedde3e599c46d60716e9`,
manifiesto `f430fbc70590076bca6d86b885e9d65126430cd8746f59bc22231d1c8e63b460`.
Se contrasta su integridad también al recibir los informes, sin cambios. Los
hashes no equivalen a lectura semántica de todo el contexto: cada informe
declara el diff completo leído y su evidencia/contexto pertinente.

| Área/criterio | A | B | D | Fusión raíz |
|---|---|---|---|---|
| Tres criterios literales de aceptación T-14 | ✓ | ✓ | rendimiento pertinente ✓ | Cumplen los escenarios contrastados |
| Cuenta/scopes, filtros, CSV, descarga, procedencia nueva, migración y métricas | ✓ salvo A-01 | Sin defecto confirmado | Sin defecto de rendimiento confirmado | Se conservan aprobaciones con sus límites |
| Explicación de intentos sin asociación histórica recuperable | ✗ A-01 | Sin hallazgo | No es hallazgo de rendimiento | ✗ Important, confirmado por raíz |
| Navegador, QA de fase, Cronicle, API y carga posteriores | Pendientes | Pendientes | Pendientes | No se cierran T-15–T-20 ni ADR-003 |

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| A-01 | Important | El vacío de intentos afirma que el transporte no comenzó, aunque una entrega pueda conservar inicios/aceptación sin asociaciones reconstruibles | T-14 | Pendiente: presentar solo la ausencia de registros de la selección y añadir una regresión con comienzos registrados y cero asociaciones | `activity.blade.php:83`, `ActivityService.php:154–162`, esquema nullable de `000010`; [informe A](testing/fase-3/review-14-A.md) |

**Veredicto fusionado: 0 Critical, 1 Important, 0 Minor; T-14 no aprobada.**
Root confirma el defecto mediante la rama incondicional del `@empty`, la consulta
por `delivery_id` y el esquema que permite el histórico sin puntero válido.
No hay nuevo reenvío automático ni fuga; no se eleva a Critical. Los seis gates
verdes anteriores quedan como evidencia de la versión revisada, sin cubrir el
caso omitido. El defecto no se descarta por aparecer solo en un contrato de apoyo:
la spec exige presentar hechos demostrables, también en el detalle histórico.

Matrices completas y límites: [A](testing/fase-3/review-14-A.md),
[B](testing/fase-3/review-14-B.md), [D](testing/fase-3/review-14-D.md).
Las copias mantienen bytes originales y pasan los patrones de datos públicos y
valores locales configurados; no se afirma detección completa de PII. Jira está
desactivado, sin escritura externa. No se promocionan entradas de conocimiento.

El marcador de revisión se cierra a `2026-10-23T23:20:52Z`: **CLOCK 18m**,
tokens/horas IA/euros reales desconocidos/null por falta de transcripciones.
Se conserva la primera implementación y esta revisión; la corrección usará una
ventana independiente `T-14-fix1` y después una nueva pasada **2/3** con todos los
veredictos anteriores. Contador formal **13/20**, T-14 sigue en-progreso.

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

El comando literal `php artisan test --filter=ActivityTest` termina con hijo 0:
`fix1-activity-exact-20261023T233258528346Z`, **20 pruebas/237 aserciones**,
280.67 s del runner. Root contrasta recibo, hashes de streams/ZIP, manifiesto y
recuento del stdout; las 196 fuentes no cambiaron durante el proceso.

La migración incremental se repite con su filtro exacto de OutboxIntegrationTest:
`fix1-migration-final-20261023T233853373319Z`, hijo 0, **1 prueba/15
aserciones**, 26.54 s del runner. Root verifica la misma integridad y el recuento
literal. Ambos gates, Pint y build conservan manifiesto
`30d3025450edd434386fb0a045f45cf5cd95a92e1f010e5fd033fbd86f8e594f`.

El propietario arranca después `fix1-full-final-20261023T233955193754Z`, comando
literal `php artisan test`, y confirma handle propio vivo 62398. Solo él lo
observa; el coordinador no ejecuta aplicación/SQL/SMTP ni reinicia la suite por
una espera de observación. Esta anotación conserva una observación, no prueba
la liveness futura ni anticipa el resultado del full.

Se prepara un auditor de censo específico de fix1, solo de lectura sobre
evidencia archivada, con sintaxis validada pero todavía sin ejecutar. Ese censo
deberá declarar su alcance sobre los runs fix1 y excluir de CIM los tres PIDs
no inspeccionables; no se confundirá con una comprobación de todos los recursos
del host ni con el censo histórico de la primera implementación. Permanecen
pendientes full, censo, handoff/liberación, cierre del medidor, revisión15 y
publicación. Contador formal **13/20**; T-14 sigue **en-progreso**.

#### T-14-fix1 — full verde, entrega y liberación verificadas

El full literal `php artisan test` del run
`fix1-full-final-20261023T233955193754Z` terminó con exit0:
**203 pruebas, 2.466 aserciones, 2.710,15 s** de runner, en 18 clases.
Root contrastó recibo, comando, streams y ZIP de las 196 fuentes finales
con SHA `30d3025450edd434386fb0a045f45cf5cd95a92e1f010e5fd033fbd86f8e594f`,
sin cambios durante la ejecución; verificó 494 copias atribuidas y 236
orígenes sin copia. El conteo de casos coincide con el total de consola.
Esto completa los gates de código de fix1, sin sustituir revisión ni QA.

El censo `fix1-owned-settlement-20261024T002545020815Z` y el writer
`fix1-handoff-final-20261024T002630577672Z` finalizaron con exit0.
El alcance del censo son cinco runs de pruebas de fix1: 77 PIDs históricos
atribuidos, ninguno presente; 46 nombres propios de contenedores ausentes
y 84 consultas pasivas de las 42 entidades propias de broker ausentes.
Se excluyeron de la consulta CIM los tres PIDs ajenos documentados.
La identidad SQL comprobada fue la de pruebas, con solo `migrations: 0`.
Root auditó 656 archivos de procedencia y los comandos archivados sin
ejecutar consultas de aplicación, SQL, procesos ni broker. No se afirma
un inventario global del host ni una garantía posterior de liveness.

El implementador liberó explícitamente las 196 fuentes y `emailing_testing`
después del writer terminal. Root contrastó el handoff fix1 SHA
`3697e18af93ed33bed9830d167f80592ca696c12e78ab4070888fa3e2f6df3e4`,
las 30 entradas nuevas de fuentes privadas, las 41 originales intactas,
el handoff original intacto y las fuentes actuales contra el recibo final.
La evidencia privada queda en los informes root de full, conteo, censo y
liberación dentro de `storage/phase3-t14/`; no se publica ese directorio.
Algunos JSON de stdout root conservan BOM UTF-16LE de PowerShell: se
leyeron con detección de encoding sin modificar sus bytes originales.

Medidor `T-14-fix1` cerrado: 2026-10-23 23:26:36Z →
2026-10-24 00:32:06Z, **CLOCK 1h 6m**. Sin transcripciones disponibles,
tokens reales, coste y horas de IA siguen desconocidos; CLOCK no los estima.
Pendiente congelar la cápsula y ejecutar revisión15, segundo intento
acotado de T14, con los veredictos completos previos. El único gap previo
es A-01, todavía pendiente de esa revisión. No hay cierre ni publicación
de T14: contador formal **13/20**. T15–T20 permanecen pendientes.

#### Revisión15 — despacho, segundo intento acotado de T14

Tras la liberación explícita y el cierre de implementación, la congelación
terminó con exit0: 24 archivos cambiados, 785 fuentes de contexto y 3.607
archivos de evidencia. Cápsula privada `storage/phase3-t14-review2`, sello
SHA `a0889303a1d7ba363bc473eecaea7e05f26a30c0dc8ea52f6863eda760a39da4`,
patch SHA `0d9e938a4d7934fecee6c1b5033b6cf410bf83f284568f2c0c273fa5affffa29`.
La preparación inicial detectó que root exigía el nombre de la base en el
resumen Markdown: se conservó el diagnóstico y se verificó la identidad
explícita en el handoff JSON al que remite ese resumen, sin modificar los
handoffs ni código probado. No fue un intento de revisión ni fallo de app.

Scope exit0 sin exclusiones ni avisos. Selector oficial: A+B+D; C no
activada. Se lanzaron tres revisores genéricos independientes con contexto
fresco y prompts literales, solo lectura, sin override de modelo y con
fallback declarado. Reciben los veredictos previos completos y el diff de
corrección; no deben reabrir criterios aprobados sin evidencia nueva.
Medidor `review-15` iniciado el **2026-10-24 00:34:32Z** después de cerrar
`T-14-fix1`. Resultados y fusión pendientes; no hay aprobación anticipada
ni commit de T14. Contador formal **13/20**.

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
