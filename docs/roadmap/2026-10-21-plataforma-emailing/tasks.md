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
| Fase 1 — Runtime, cuentas y Teammates | 0 | 4 | 0% | — / 104h | — / 3.200000h | — / 0.800000h | — / 960000 |
| Fase 2 — Contactos, plantillas y SMTP | 0 | 5 | 0% | — / 92h | — / 3.016667h | — / 0.754167h | — / 905000 |
| Fase 3 — Campañas, entrega y actividad | 0 | 7 | 0% | — / 120h | — / 3.783333h | — / 0.945833h | — / 1135000 |
| Fase 4 — API y validación global | 0 | 4 | 0% | — / 60h | — / 2.400000h | — / 0.600000h | — / 720000 |
| **TOTAL** | **0** | **20** | **0%** | **— / 376h** | **— / 12,4h** | **— / 3,1h** | **— / 3720000** |

Reales desconocidos: el guion no representa consumo cero medido. Cifras de tarea base sin margen; total con reserva 451,2 h / 22.560 € equivalentes + tokens. Horas IA por tokens/300.000; redondeo decimal solo de presentación. Supervisión 25 %.

## Fase 1 — Runtime, cuentas y Teammates

**Estado**: en-progreso · **Estimado**: 104h · **Real**: — · **Coste est.**: 5200 € equivalentes + tokens · **Tokens est.**: 960000

### T-01 — Runtime Laravel, Laragon y Compose propio

- **Descripción**: Sustituir scaffold por Laravel 13/PHP 8.4 y Livewire, con Fortify/Sanctum y versiones estables bloqueadas. Crear Compose propio Nginx/PHP-FPM/worker sin otros servicios de infra, perfiles Laragon/Docker y provisión idempotente de vhost/usuario RabbitMQ no administrador y base exclusiva de pruebas. Verificar un job real con el adaptador.
- **Estado**: en-progreso
- **Tiempo humano**: est. 24h · real —
- **Tiempo IA (ejec.)**: est. 0.600000h · real —
- **Supervisión**: est. 0.150000h · real —
- **Previsión IA**: 145000 in / 35000 out tok · coste IA por verificar: 0.145 p_in + 0.035 p_out €; humano base 1200 € equivalente
- **Dependencias**: ninguna
- **Tipo**: devops
- **Archivos**: `.dockerignore`, `.editorconfig`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `artisan`, `app/**`, `bootstrap/**`, `config/**`, `routes/**`, `database/**`, `resources/**`, `public/**`, `storage/**`, `tests/**`, `phpunit.xml`, `vite.config.*`, `.env.example`, `.gitignore`, `.htaccess`, `compose.yaml`, `docker/**`, `scripts/**`, `README.md`, `docs/**`, `infra/README.md`
- **Cubre (tests)**: E2E-01 / M-01
- **Criterios spec**: CA-12/21/25
- **Verificación**: `php artisan about` → Laravel 13 y MySQL configurados · `npm run build` → assets compilados · `docker compose config --services` → solo servicios de aplicación · `php artisan test --filter=RuntimeIntegrationTest` → job real/ACL y guard de base pasan

**Criterios de aceptación**

- [ ] MySQL de integración usa emailing_testing y rechaza migrar/refrescar cualquier otra base; lab_smoke permanece intacta.
- [ ] Web Laragon y Docker muestran acceso válido; RabbitMQ runtime no usa administrador ni topología ajena.
- [ ] Dependencias y lock incluyen adaptador estable; prueba publish/consume/reentrega real, no solo Composer.

**Subtareas**

- [ ] Copiar selectivamente skeleton oficial y retirar scaffold provisional preservando infra/docs/secretos.
- [ ] Configurar sockets, dependencias, build y provisión local sin exponer claves.
- [ ] Arrancar servicios propios y ejecutar smoke de runtime, ACL y perfiles.


**Evidencia T-01 (implementer)**: Laravel 13.38.0/PHP 8.4.18, MySQL/rabbitmq/SMTP configurados; RuntimeIntegrationTest 3 passed (5 assertions), npm build PASS. Compose config: emailing-php, emailing-web, emailing-worker. Docker build lanzado por implementer e imagenes construidas; arranque y health de perfiles a cargo del coordinador al parar la jornada; criterio de perfiles sin cerrar. DB de producto solo migraciones incrementales.


**Medicion T-01**: ventana 2026-10-21T16:35:48Z a 16:41:50Z cerrada; fuente estimado, tokens_reales/horas_ia/eur null por falta de transcripciones compatibles. La duracion de reloj no se imputa como horas IA.

### T-02 — Identidades, cuentas y selector multi-cuenta

- **Descripción**: Crear registro interno configurable, login/logout y recuperación Fortify; alta transaccional de cuenta y membresía owner/Admin. Resolver cuenta activa desde membresía vigente y proteger cada acción Livewire/HTTP.
- **Estado**: en-progreso
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

- [ ] Registro autorizado crea identidad/cuenta/owner atómicos; deshabilitado no concede alta operativa.
- [ ] Solicitudes sin auth/CSRF o IDs ajenos se deniegan; selector no arrastra permisos de otra cuenta.
- [ ] Reset por Mailpit permite recuperar acceso con token válido y único.

**Subtareas**

- [ ] Crear migraciones/constraints y contexto de cuenta servidor.
- [ ] Implementar Fortify, formularios y selector en español.
- [ ] Probar dos cuentas y permisos distintos con MySQL y Mailpit.


**Pausa solicitada por el usuario (2026-10-21)**: checkpoint parcial, NO terminado. Creados modelos Account/Membership/AuditEvent, migracion incremental, AccountContext, acciones Fortify y vistas base/selector. Falta ejecutar/verificar AccountAuthenticationTest (aun no existe), CSRF real, reset via Mailpit y permisos efectivos; no hay QA ni revision. T-03 invitaciones/roles/Livewire aun sin iniciar.

**Medicion T-02**: ventana 2026-10-21T16:41:50Z a 16:45:50Z cerrada; fuente estimado; tokens_reales/horas_ia/eur null. Sin consumo real inventado.

**Retomar**: `powershell -ExecutionPolicy Bypass -File scripts/provision-local.ps1`; `powershell -ExecutionPolicy Bypass -File scripts/php.ps1 artisan migrate --force` (incremental, nunca fresh en emailing); con Node 22 `npm ci` y `npm run build`; despues implementar pruebas AccountAuthenticationTest contra emailing_testing, completar T-02, iniciar T-03 y entregar revision/QA T-04. Rebuild Compose pendiente tras este checkpoint porque imagen actual precede los ultimos archivos T-02.

### T-03 — Teammates, invitaciones, roles y auditoría

- **Descripción**: Implementar invitaciones por email con token hash, siete días, reenvío/revocación y aceptación transaccional email-bound. Aplicar Admin/Read-only/Restricted, scopes y presets del catálogo servidor; proteger owner y auditar actor/cuenta/acción.
- **Estado**: borrador
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

- [ ] Token vencido/usado/revocado/sustituido o identidad incorrecta no crea membresía; aceptación concurrente crea una.
- [ ] Read-only/Restricted no elevan scopes ni administran miembros; último owner/Admin protegido.
- [ ] Revocación y cambios de permisos se aplican en siguiente acción, conservan recursos y registran auditoría.

**Subtareas**

- [ ] Crear políticas/scopes y UI de Teammates con estados observables.
- [ ] Enviar invitaciones por Mailpit e implementar aceptación/reenvío/revocación.
- [ ] Verificar matriz de miembros, transferencias protegidas y eventos Livewire manipulados.

### T-04 — Revisión y QA de la primera fase

- **Descripción**: Consolidar plan y ejecutar revisión adversarial de alcance/corrección y lentes condicionales, correcciones y QA de C-01. Incluye 8 h equivalentes de proceso y 24 h de revisión/QA, sin declarar medición real. Publicar documentación de uso y evidencia; C-02 a C-07 permanecen pendientes.
- **Estado**: borrador
- **Tiempo humano**: est. 32h · real —
- **Tiempo IA (ejec.)**: est. 1.533333h · real —
- **Supervisión**: est. 0.383333h · real —
- **Previsión IA**: 380000 in / 80000 out tok · coste IA por verificar: 0.38 p_in + 0.08 p_out €; humano base 1600 € equivalente
- **Dependencias**: T-01, T-02, T-03
- **Tipo**: test
- **Archivos**: `app/**`, `bootstrap/**`, `config/**`, `routes/**`, `database/**`, `resources/**`, `public/**`, `tests/**`, `scripts/**`, `docker/**`, `compose.yaml`, `composer.*`, `package*.json`, `phpunit.xml`, `vite.config.*`, `.env.example`, `.gitignore`, `.htaccess`, `storage/**`, `README.md`, `infra/README.md`, `docs/**`, `CHANGELOG*`
- **Cubre (tests)**: E2E-01 / E2E-02 / E2E-03 / E2E-04 / M-01
- **Criterios spec**: CA-01/12/13/15/16/17/18/20/21/25
- **Verificación**: `php artisan test --testsuite=Feature` → fase 1 verde con base exclusiva · `python C:/Users/46066917X/.codex/plugins/cache/daycry/custom-agents/1.22.0/agent-kits/shared/ledger-lint.py docs\roadmap\2026-10-21-plataforma-emailing/tasks.md` → exit 0 · lectura: informe Playwright E2E-01–04 y qa-gate con cero fallos/flaky

**Criterios de aceptación**

- [ ] No hallazgos Critical/Important abiertos; ambos perfiles verificados con evidencia.
- [ ] E2E de registro/reset/cuentas/invitaciones/revocación/roles/aislamiento pasan sin flaky.
- [ ] Documentación detallada de infra enlazada; secretos/volúmenes/datos ajenos preservados.

**Subtareas**

- [ ] Registrar revisión y corregir hallazgos con evidencia.
- [ ] QA implementa y ejecuta E2E-01 a E2E-04; revisar visual responsive.
- [ ] Actualizar ledger/reportes, documentación y relevo de fase.

## Fase 2 — Contactos, plantillas y SMTP

**Estado**: borrador · **Estimado**: 92h · **Real**: — · **Coste est.**: 4600 € equivalentes + tokens · **Tokens est.**: 905000

### T-05 — Contactos y listas compartidos por cuenta

- **Descripción**: Crear CRUD de contactos/listas con pertenencia a cuenta, paginación, permisos y trazabilidad de origen/fecha de consentimiento.
- **Estado**: borrador
- **Tiempo humano**: est. 14h · real —
- **Tiempo IA (ejec.)**: est. 0.366667h · real —
- **Supervisión**: est. 0.091667h · real —
- **Previsión IA**: 90000 in / 20000 out tok · coste IA por verificar: 0.09 p_in + 0.02 p_out €; humano base 700 € equivalente
- **Dependencias**: T-04
- **Tipo**: backend
- **Archivos**: `app/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/desarrollo/**`
- **Cubre (tests)**: E2E-05 / M-01
- **Criterios spec**: CA-03/13
- **Verificación**: `php artisan test --filter=ContactListTest` → CRUD y acceso negativo pasan

**Criterios de aceptación**

- [ ] Un teammate autorizado utiliza recursos creados por otro en su cuenta.
- [ ] Cuenta ajena o scope insuficiente no lee/escribe/lista/exporta/agrega.

**Subtareas**

- [ ] Crear modelos/políticas y componentes.
- [ ] Probar consentimiento y matriz por cuenta.

### T-06 — Importación CSV validada y deduplicada

- **Descripción**: Importar CSV con resumen por fila, validación de email/consentimiento y deduplicación por cuenta; límite inicial configurable de 5 MB y 10.000 filas.
- **Estado**: borrador
- **Tiempo humano**: est. 10h · real —
- **Tiempo IA (ejec.)**: est. 0.283333h · real —
- **Supervisión**: est. 0.070833h · real —
- **Previsión IA**: 70000 in / 15000 out tok · coste IA por verificar: 0.07 p_in + 0.015 p_out €; humano base 500 € equivalente
- **Dependencias**: T-05
- **Tipo**: backend
- **Archivos**: `app/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/desarrollo/**`
- **Cubre (tests)**: E2E-05
- **Criterios spec**: CA-02/03/13
- **Verificación**: `php artisan test --filter=ContactImportTest` → inválidos, duplicados y límites pasan

**Criterios de aceptación**

- [ ] CSV inválido produce resumen consistente y no contamina otra cuenta.
- [ ] No se infiere consentimiento de un email; origen/fecha obligatorios según política documentada.

**Subtareas**

- [ ] Validar formato/tamaño y normalización.
- [ ] Probar rollback parcial definido y archivos privados.

### T-07 — Plantillas dinámicas y preview aislada

- **Descripción**: Implementar editor HTML sencillo y variables acotadas, preview sandbox y prueba con permiso de envío. Sin editor por bloques ni dependencias de pago.
- **Estado**: borrador
- **Tiempo humano**: est. 20h · real —
- **Tiempo IA (ejec.)**: est. 0.500000h · real —
- **Supervisión**: est. 0.125000h · real —
- **Previsión IA**: 120000 in / 30000 out tok · coste IA por verificar: 0.12 p_in + 0.03 p_out €; humano base 1000 € equivalente
- **Dependencias**: T-05, T-08
- **Tipo**: frontend
- **Archivos**: `app/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/desarrollo/**`
- **Cubre (tests)**: E2E-06 / M-01
- **Criterios spec**: CA-04/12/13
- **Verificación**: `php artisan test --filter=TemplateTest` → variables, sandbox y permisos pasan

**Criterios de aceptación**

- [ ] Scripts no se ejecutan en el panel ni escapan sandbox.
- [ ] Variables se resuelven/escapan según contrato; preview/prueba requieren scopes y cuenta válidos.

**Subtareas**

- [ ] Crear renderer de variables limitado y UI.
- [ ] Probar HTML hostil, datos ausentes y acceso cruzado.

### T-08 — SMTP, remitentes y log privado

- **Descripción**: Guardar configuración SMTP/TLS y remitentes por cuenta con secretos cifrados. Modo log seguro por defecto, prueba Mailpit y diagnóstico depurado; no correo externo por defecto.
- **Estado**: borrador
- **Tiempo humano**: est. 24h · real —
- **Tiempo IA (ejec.)**: est. 0.666667h · real —
- **Supervisión**: est. 0.166667h · real —
- **Previsión IA**: 165000 in / 35000 out tok · coste IA por verificar: 0.165 p_in + 0.035 p_out €; humano base 1200 € equivalente
- **Dependencias**: T-04
- **Tipo**: backend
- **Archivos**: `app/**`, `config/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/desarrollo/**`
- **Cubre (tests)**: E2E-07
- **Criterios spec**: CA-07/08/12/14
- **Verificación**: `php artisan test --filter=MailTransportTest` → log sin socket SMTP, Mailpit y aislamiento pasan

**Criterios de aceptación**

- [ ] Log genera eml privado y no conecta SMTP; descarga exige cuenta/scope.
- [ ] Secretos cifrados no aparecen en HTML/logs/errores/payloads; TLS inválido se rechaza.
- [ ] Aceptación Mailpit se etiqueta SMTP aceptado, nunca entrega.
- [ ] Correo externo exige dominio/remitentes autorizados y evidencia SPF/DKIM/DMARC; se concreta relay y mecanismo de feedback antes de habilitarlo. Mailpit/log siguen permitidos para laboratorio.

**Subtareas**

- [ ] Crear transportes por cuenta/remitentes y cifrado.
- [ ] Pruebas negativas de secretos/TLS y transporte por cuenta.

### T-09 — Revisión y QA de recursos y transportes

- **Descripción**: Revisar diff y validar recursos compartidos, importación, preview y SMTP antes de campañas.
- **Estado**: borrador
- **Tiempo humano**: est. 24h · real —
- **Tiempo IA (ejec.)**: est. 1.200000h · real —
- **Supervisión**: est. 0.300000h · real —
- **Previsión IA**: 300000 in / 60000 out tok · coste IA por verificar: 0.3 p_in + 0.06 p_out €; humano base 1200 € equivalente
- **Dependencias**: T-05, T-06, T-07, T-08
- **Tipo**: test
- **Archivos**: `app/**`, `config/**`, `routes/**`, `database/**`, `resources/**`, `tests/**`, `docs/**`, `README.md`, `CHANGELOG*`
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
- **Verificación**: `python C:/Users/46066917X/.codex/plugins/cache/daycry/custom-agents/1.22.0/skills/api-contract/scripts/openapi-lint.py docs/api/openapi.yaml` → exit 0

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
- **Verificación**: `php artisan test` → suite completa verde en base exclusiva · `npm run build` → éxito · `python C:/Users/46066917X/.codex/plugins/cache/daycry/custom-agents/1.22.0/agent-kits/shared/ledger-lint.py docs\roadmap\2026-10-21-plataforma-emailing/tasks.md` → exit 0 · lectura: revisión final y qa-gate sin fallos/flaky

**Criterios de aceptación**

- [ ] CA-01 a CA-25 tienen evidencia y limitaciones explicitadas.
- [ ] No Critical/Important abiertos; manuales no ejecutados no se marcan aprobados.
- [ ] Documentación consistente con versión final y secretos ausentes de Git.

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
