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
| Fase 2 — Contactos, plantillas y SMTP | 4 | 5 | 80% | — / 92h | — / 3.016667h | — / 0.754167h | — / 905000 |
| Fase 3 — Campañas, entrega y actividad | 0 | 7 | 0% | — / 120h | — / 3.783333h | — / 0.945833h | — / 1135000 |
| Fase 4 — API y validación global | 0 | 4 | 0% | — / 60h | — / 2.400000h | — / 0.600000h | — / 720000 |
| **TOTAL** | **8** | **20** | **40%** | **— / 376h** | **— / 12,4h** | **— / 3,1h** | **— / 3720000** |

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

**Estado**: en-progreso · **Estimado**: 92h · **Real**: — · **Coste est.**: 4600 € equivalentes + tokens · **Tokens est.**: 905000

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
- **Estado**: en-progreso
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

**Criterios de aceptación**

- [ ] Matriz de dos cuentas, tres identidades y scopes pasa.
- [ ] Revisión no deja hallazgos Critical/Important abiertos.

**Subtareas**

- [ ] Revisión adversarial y correcciones.
- [ ] Ejecutar E2E y actualizar evidencias/ledger.

## Fase 3 — Campañas, entrega y actividad

**Estado**: borrador · **Estimado**: 120h · **Real**: — · **Coste est.**: 6000 € equivalentes + tokens · **Tokens est.**: 1135000

### T-10 — Bajas y supresiones antes de enviar

- **Descripción**: Crear baja pública con token ligado a cuenta/destinatario, idempotente y no enumerable; supresión manual por cuenta y consulta inmediata pretransporte.
- **Estado**: borrador
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

- [ ] Token alterado no revela/cambia datos; repetido es idempotente.
- [ ] Mismo email en otra cuenta permanece sin modificar y pendientes se suprimen antes del envío.
- [ ] El mecanismo de feedback elegido incorpora rebotes definitivos y quejas autenticadas como supresiones idempotentes; eventos duplicados o falsificados no alteran otras cuentas. Sin soporte de feedback se muestra la limitación, sin fingir una tasa saludable.

**Subtareas**

- [ ] Definir tokens/rutas y retención inicial documentada.
- [ ] Probar baja después de encolar y antes del transporte.

### T-11 — Campañas, revisión y programación

- **Descripción**: Crear borrador/revisión/programación con permisos, snapshot/referencias de destinatarios y fecha efectiva.
- **Estado**: borrador
- **Tiempo humano**: est. 12h · real —
- **Tiempo IA (ejec.)**: est. 0.286667h · real —
- **Supervisión**: est. 0.071667h · real —
- **Previsión IA**: 70000 in / 16000 out tok · coste IA por verificar: 0.07 p_in + 0.016 p_out €; humano base 600 € equivalente
- **Dependencias**: T-10
- **Tipo**: frontend
- **Archivos**: `app/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/desarrollo/**`
- **Cubre (tests)**: E2E-08 / M-01
- **Criterios spec**: CA-03/05/13
- **Verificación**: `php artisan test --filter=CampaignTest` → borrador/futura/autorizada y scopes pasan

**Criterios de aceptación**

- [ ] Borradores o campañas futuras no envían prematuramente.
- [ ] Destinatarios/plantillas/remitentes se validan contra cuenta y scopes efectivos.

**Subtareas**

- [ ] UI de composición/resumen y transición de estados.
- [ ] Pruebas de fechas y acciones concurrentes.

### T-12 — Outbox transaccional y publicación reconciliable

- **Descripción**: Persistir trabajo/outbox en MySQL y dispatcher con IDs estables, confirms, rechazo de no enrutables y reconciliación. Mensajes mínimos sin secretos/HTML completo.
- **Estado**: borrador
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

**Criterios de aceptación**

- [ ] Caída SQL/broker entre pasos no pierde pendientes silenciosamente.
- [ ] Publicación repetida converge al mismo trabajo; topología/ACL mantiene otras aplicaciones aisladas.

**Subtareas**

- [ ] Implementar estado transaccional y dispatcher.
- [ ] Inducir cortes y verificar confirms/no-routable/reconciliación.

### T-13 — Workers, reservas, reintentos y SMTP ambiguo

- **Descripción**: Consumir con reserva MySQL, deduplicación, ack después de resultado durable, backoff acotado y cola de fallos. Estado potencialmente aceptado queda ambiguo sin reenvío automático.
- **Estado**: borrador
- **Tiempo humano**: est. 28h · real —
- **Tiempo IA (ejec.)**: est. 0.606667h · real —
- **Supervisión**: est. 0.151667h · real —
- **Previsión IA**: 150000 in / 32000 out tok · coste IA por verificar: 0.15 p_in + 0.032 p_out €; humano base 1400 € equivalente
- **Dependencias**: T-12, T-08, T-10
- **Tipo**: backend
- **Archivos**: `app/**`, `config/**`, `database/**`, `tests/**`, `scripts/**`, `docker/**`, `compose.yaml`, `docs/desarrollo/**`
- **Cubre (tests)**: E2E-09
- **Criterios spec**: CA-06/08/09/14/22/23
- **Verificación**: `php artisan test --filter=WorkerRecoveryTest` → dos consumidores/crash/ack/SMTP ambiguo pasan

**Criterios de aceptación**

- [ ] Dos workers no ejecutan concurrentemente el mismo trabajo; recuperan reservas vencidas con política definida.
- [ ] Crash entre aceptación/persistencia/ack no provoca retry ciego; fallo previo sí aplica límites.
- [ ] Se respetan límites por cuenta y agregados por relay/credencial; una campaña pausada por incidencia no continúa enviando al ser reentregada.

**Subtareas**

- [ ] Implementar reservas/heartbeats y recuperación.
- [ ] Relay SMTP controlado y crash tests en procesos Linux.

### T-14 — Actividad y métricas demostrables

- **Descripción**: Mostrar actividad y agregados por cuenta con estados reales, origen/actor y errores depurados. No inventar entrega/aperturas/clics.
- **Estado**: borrador
- **Tiempo humano**: est. 8h · real —
- **Tiempo IA (ejec.)**: est. 0.236667h · real —
- **Supervisión**: est. 0.059167h · real —
- **Previsión IA**: 60000 in / 11000 out tok · coste IA por verificar: 0.06 p_in + 0.011 p_out €; humano base 400 € equivalente
- **Dependencias**: T-13
- **Tipo**: frontend
- **Archivos**: `app/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/desarrollo/**`
- **Cubre (tests)**: E2E-09 / M-01
- **Criterios spec**: CA-08/12/13/14
- **Verificación**: `php artisan test --filter=ActivityTest` → estados y agregados aislados pasan

**Criterios de aceptación**

- [ ] Aceptado SMTP, fallido, suprimido y ambiguo son distinguibles.
- [ ] Listados/agregados/descargas solo incluyen la cuenta autorizada.
- [ ] Alertas de reputación/bloqueo usan eventos comprobables; tasas conservan ventana, denominador y fuente, y ausencia de datos no aparece como cero incidencias.

**Subtareas**

- [ ] Implementar consultas/índices y UI.
- [ ] Comprobar estados y permisos por módulo.

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
