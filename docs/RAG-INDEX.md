# Emailing — índice de conocimiento para agentes

**Última actualización: 2026-10-31.** Este índice resume documentación técnica y enlaza fuentes; no sustituye el código ni los resultados de pruebas.

## Manual y cobertura completa

El [PDF del manual](manual/guia-completa.pdf) conserva el contenido de la fuente
Markdown, con índice paginado y ocho diagramas. La [guía de mantenimiento](manual/README.md)
describe la exportación y las comprobaciones de contenido y presentación.

Fuente: [manual de uso e instalación](manual/guia-completa.md) y
[matriz documental](manual/cobertura-documentacion.md). El manual explica
instalación limpia persistente Docker/Laragon, Linux/TLS, primer registro,
funciones, ejemplos y ocho Mermaid, API REST, 37 herramientas MCP, variables,
operación y recuperación. Docker compila PHP/Composer/Vite dentro de la imagen;
esa ruta no exige esos programas en el host. Los scripts de aprovisionamiento
son PowerShell; Linux necesita pwsh o procedimiento equivalente administrado.
La instalación persistente usa `emailing`/8081; QA 8082/8083 puede limpiar su base.
Migrate aplica pendientes tras inspección/copia; nunca reset de datos operativos.

La matriz cubre pantallas/tareas, interfaces, las 37 operaciones MCP y 71 variables
de entorno públicas más opciones propias adicionales. Documentar los pasos no
acredita instalación nueva, restore global, proveedor real ni aceptación humana.
Principal conserva 18/20, MCP 11/14 y sus puertas pendientes.

## Estado real y navegación

Fuente: [README de documentación](README.md), [roadmap](roadmap/README.md) e [informe QA de infraestructura](roadmap/2026-10-21-infra-laboratorio/testing/report.md).

La infraestructura está implementada y probada: cuatro servicios healthy,
protocolos, persistencia y tres E2E aprobados. Las fases 1/2 de aplicación cubren
auth, cuentas/Teammates, contactos/listas, CSV, plantillas y transporte log/Mailpit;
QA3 de fase 2 acredita 20/20 casos, diez por perfil. La revisión 13 cierra los
componentes T-10–T-13: campañas, bajas/supresiones, outbox y workers. El total
formal es **18/20**. Actividad T-14 y Cronicle T-15 están revisados; T-16 acredita
grupo delivery 139/2009 y navegador 10/10 r0, con revisión 19 sin gaps.
Los endpoints API T-18 están aceptados tras revisión 23 y QA nativo/Docker 2/2.
La ampliación Google/MCP está en **11/14** aceptadas: identidad, acceso, OAuth
delegado y catálogo de 37 operaciones probados en laboratorio. T11 ha superado
sus comprobaciones técnicas de perfiles HTTPS, persistencia y recuperación, con
revisión 2 A+B+C+D sin defectos pendientes y QA independiente 9/9; su
[aceptación](roadmap/2026-10-24-mcp-google/testing/fase-5/t11/acceptance.md) está registrada. La regresión global T12, Google/clientes nativos reales y los
manuales M-01–M-04 del destino privado en T14 siguen pendientes. Carga y
restauración T-19 y cierre T-20 permanecen abiertos. M-01 humana y M-02 siguen pendientes. El esquema operativo `emailing`
requiere inspección antes del despliegue; las pruebas usan `emailing_testing`.

T-17 cierra el [contrato OpenAPI](api/README.md), con [revisión 21](roadmap/2026-10-21-plataforma-emailing/testing/fase-4/review/attempt-21.md) sin gaps, 107/107 ejemplos de esquema y corrección causal de las dos variantes. Son pruebas documentales. La implementación T-18 y su [QA E2E-10](roadmap/2026-10-21-plataforma-emailing/testing/fase-4/t18-api/report.md) están aceptados; ese resultado no acredita despliegue operativo ni el MCP futuro.

## Topología y aislamiento

Fuente: [arquitectura](infraestructura/arquitectura.md), [Compose](../infra/compose.yaml) y [ADR-001](knowledge/adr/ADR-001-rabbitmq-centralizado.md).

Proyecto `emailing-infra`; bridge local `emailing-lab`; hostnames `lab-rabbitmq`
y `lab-cronicle`. Un único host, sin HA ni Supervisor. El [Compose de aplicación](../compose.yaml)
declara PHP, Nginx, consumidor de dominio, worker Laravel y scheduler, con red
externa y credenciales propias. Cronicle no monta Docker socket. Los vhosts
aíslan permisos y topología, no recursos físicos.

## Versiones, salud y límites

Fuente: [servicios](infraestructura/servicios.md), [bootstrap](../infra/cronicle/bootstrap.js) y [healthcheck Cronicle](../infra/cronicle/health.js).

RabbitMQ `4.4.0-management`, límite efectivo de body consultado `16777216` bytes = 16 MiB. Cronicle local `0.9.137` sobre Node 22 fijado por digest; master único, foreground, usuario node, init mínimo. MySQL `8.4.11`; Mailpit `v1.27.4`. Cronicle conserva historial 30 días, logs de job de hasta 10 MiB y máximo de 10 jobs simultáneos. Mailpit conserva hasta 1000 mensajes. Logs Docker `json-file`: tres archivos de 10 MB. Límites CPU/memoria son configuración inicial, no benchmark.

## Puertos y credenciales

Fuente: [configuración](infraestructura/configuracion.md), [.env.example](../infra/.env.example) e [init.ps1](../infra/init.ps1).

| Servicio | Windows/Laragon | Red Docker |
|---|---|---|
| RabbitMQ AMQP | `127.0.0.1:5672` | `rabbitmq:5672` |
| RabbitMQ UI | `127.0.0.1:15672` | `rabbitmq:15672` |
| Cronicle | `127.0.0.1:3012` | `cronicle:3012` |
| MySQL | `127.0.0.1:3308` | `mysql:3306` |
| SMTP Mailpit | `127.0.0.1:11025` | `mailpit:1025` |
| Web Mailpit | `127.0.0.1:18025` | `mailpit:8025` |

Secretos en `infra/.env` ignorado, generados aleatoriamente y conservados si ya existe. No leer, imprimir ni copiar sus valores a documentación. RabbitMQ cuenta administrativa inicial por variables; MySQL root y usuario `emailing` separados; Cronicle `admin` inicial y marcador `.lab-initialized`. Editar `.env` no rota cuentas existentes. Conservar `CRONICLE_SECRET_KEY` al trasladar datos.

## Operación y pruebas

Fuente: [operación](infraestructura/operacion.md) y [smoke.ps1](../infra/smoke.ps1).

Arranque: init, `config --quiet`, `up -d --build --wait --wait-timeout 240`, smoke. Siempre pasar `--env-file infra/.env -f infra/compose.yaml`. Smoke normal crea marcadores y escrituras nuevos; RabbitMQ se prueba mediante Management HTTP, no mediante el adaptador AMQP de aplicación. `-VerifyPersistence` usa estado previo, comprueba datos anteriores y ejecuta otro job Cronicle. Los fixtures permanecen; la prueba no borra datos. Recreación sin borrar volúmenes probada. No repetir escrituras o recreaciones solo para leer documentación.

## Integración actual y programación futura

Fuente: [integración](infraestructura/integracion-aplicaciones.md), [ADR-002](knowledge/adr/ADR-002-laravel-mysql-compose.md) y [diseño](roadmap/2026-10-21-plataforma-emailing/design.md).

Laravel 13.38/Livewire 4 y MySQL 8.4; perfiles PHP observados en [operación MCP](aplicacion/mcp-operacion.md). Compose de cinco servicios sin duplicar infraestructura: PHP, web, consumidor de dominio, jobs Laravel y scheduler; separación de T-13 y QA técnico T-16 verificados. Usuario `emailing-app` sin administración global, vhost `emailing`, aprovisionados por `scripts/provision-local.ps1`. Workers en primer plano con colas distintas. FastCGI solo por socket Unix de volumen privado PHP/Nginx; PHP non-root, Nginx monta lectura, sin TCP 9000. Alias `${COMPOSE_PROJECT_NAME:-emailing-app}-web`. Cronicle central dispara acciones HTTP autenticadas y finitas. El scheduler de aplicación ejecuta `schedule:work` y el mantenimiento `mcp:prune --limit=1000`; no dupliques ese calendario en Cronicle. Véase [operación MCP](aplicacion/mcp-operacion.md#scheduler-de-la-aplicación).

## Cuentas, Teammates y acceso

Fuente: [arquitectura de aplicación](aplicacion/arquitectura.md), [guía de usuario](aplicacion/cuentas-y-equipo.md), [AccountContext](../app/Services/AccountContext.php), [TeamService](../app/Services/TeamService.php) y [Permissions](../app/Services/Permissions.php).

Identidad multi-cuenta mediante membresía; recursos compartidos por cuenta. Registro configurable crea propietario Admin atómicamente. Admin/Read-only/Restricted y presets Developer/Marketer/Accountant; los scopes de módulos futuros no los implementan. Invitación siete días, hash SHA-256, email destinatario, un solo uso, expiración/revocación/reenvío con rotación y bloqueo concurrente. Transferencia reautentica contraseña, promueve nuevo owner Admin y conserva Admin anterior. Protegidos propietario y último Admin. Revocación/reducción aplican en siguiente acción y en lecturas de módulos. Reset invalida sesiones previas; enlaces secretos usan APP_URL confiable. SMTP de auth/invitaciones captura en Mailpit y mantiene configuración separada del transporte de pruebas por cuenta.

## Identidad y acceso con Google

Fuente: [guía Google](aplicacion/acceso-google.md), [contrato MCP](aplicacion/mcp-contrato.md), [ledger](roadmap/2026-10-24-mcp-google/tasks.md) y [QA de identidad](roadmap/2026-10-24-mcp-google/testing/fase-2/google/report.md).

Proveedor/emisor/sub exactos identifican al usuario; coincidir en email no fusiona
cuentas. El vínculo exige control local y el desvínculo conserva recuperación.
El servidor verifica firma JWT, claims, state y código de un uso; no guarda tokens
Google. Registro explícito con CSRF mantiene admisión e invitaciones. Los errores
corregibles conservan pendiente y TTL, nunca contraseñas. QA independiente acredita
20 pruebas/510 aserciones y 3/3 recorridos en Chromium y PHP cli-server nativo,
con proveedor HTTP simulado. El retorno opaco no acredita consentimiento OAuth
completo. T11 verificó técnicamente ambos perfiles HTTPS con identidades sintéticas;
Google y clientes reales conservan T14, y la regresión global sigue en T12.

## Conexiones, clientes y operación MCP

Fuente: [Conexiones MCP](aplicacion/conexiones-mcp.md), [clientes](aplicacion/mcp-clientes.md),
[operación](aplicacion/mcp-operacion.md), [contrato y catálogo](aplicacion/mcp-contrato.md),
[matriz de perfiles/clientes](roadmap/2026-10-24-mcp-google/testing/client-matrix.md) y
[QA T11](roadmap/2026-10-24-mcp-google/testing/fase-5/t11/qa-2026-10-30/report/report.md).

El consentimiento une usuario, cliente, cuenta, recurso y techo de scopes vigente.
El grant caduca como máximo en una hora; sin refresh tokens, se vuelve a autorizar.
Revocar corta tokens y sesiones de esa conexión; retirar membresía revoca sus grants.
Las 37 operaciones comparten servicios, outbox, permisos e idempotencia con web/API.
La actividad conserva el origen delegado y no muestra códigos, tokens o contactos.

Los ejemplos de Codex CLI, Claude Code y OpenCode V2 son ficticios y documentales.
La guía conserva versiones observadas y procedencia, separadas de clientes conectados.
Claude Desktop y clientes alojados requieren pruebas propias. El perfil técnico
`https://localhost:18443` se verificó por turnos en Laragon/Nginx y Docker, con
certificado/SAN confiables, cookie Secure y ambos protocolos MCP. Reinicio, rotación,
rechazo de credenciales revocadas y restore MCP están probados; cada scheduler
completó dos pasadas reales antes/después de recuperar su mutex. T11 conserva
ocho casos backend/61 aserciones, 135 Python y QA independiente 9/9 con limpieza
propia admitida. Estas pruebas no acreditan Google ni un cliente nativo conectado.

La recuperación de una copia antigua mantiene acceso público, workers y scheduler
cerrados. `mcp:invalidate-restored-access` previsualiza; `--apply` exige mantenimiento,
funciona con MCP deshabilitado y revierte toda la transacción si falla. Cualquier
fallo detiene la receta. Sólo tras invalidar se retira mantenimiento en un circuito
privado y se comprueba 401 con credenciales antiguas antes de reabrir tráfico.
Se conservan recibos de correo, auditoría y llaves API de cuenta, que requieren
reconciliación propia. El restore MCP no sustituye el simulacro global T19.

## Contactos, listas y CSV

Fuente: [guía funcional y técnica](aplicacion/contactos-e-importacion.md), [ContactService](../app/Services/ContactService.php), [ContactCsv](../app/Support/ContactCsv.php) y [ContactImportService](../app/Services/ContactImportService.php).

Cuenta delimita consultas/acciones; panel exige contacts.read y escritura añade contacts.write. Email trim/minúsculas, único activo por cuenta; email inmutable al editar. Consentimiento unknown por defecto; granted exige fuente/fecha UTC no futura; revoked no se reactiva por CRUD. Borrado lógico y retirada de asociaciones. CSV UTF-8/BOM, comas/comillas/LF/CRLF, cinco encabezados admitidos con email obligatorio; 5 MiB/10.000 registros, validación estructural completa antes de persistir, lotes 500 en transacción. Duplicados activos y repetidos válidos se omiten sin actualizar datos/consentimiento/listas. Sólo nuevos van al destino. Informes 25 filas/página e historial 10; descarga privada formula-safe, sin exportación general de contactos. Upload ligado a sesión/actor/cuenta una hora, disco privado contact-imports/temporary; PHP 6M/post8M y Nginx8m. QA limpia referencias propias comprobadas, conserva históricos sin atribución.

## Plantillas y transporte por cuenta

Fuente: [guía funcional y técnica](aplicacion/plantillas-y-transporte.md), [TemplateRenderer](../app/Support/TemplateRenderer.php), [TemplatePreview](../app/Support/TemplatePreview.php), [AccountMailTransport](../app/Services/AccountMailTransport.php) y [MailSettingsService](../app/Services/MailSettingsService.php).

Tres variables: contact.name/contact.email/account.name; una pasada, HTML escapado y variables HTML sólo en texto del cuerpo. Asunto200 caracteres antes/después sin CR/LF; fuentes100.000 bytes/200 variables, resultado200.000 bytes. HTML/texto alternativos, ambos vacíos rechazados; texto derivado si falta. Preview de formulario con allowlist+sandbox+CSP sin recursos activos; email conserva HTML y enlaces. Prueba usa versión guardada, templates.read+mail.send sin contacts.read/settings.read. Transporte recibe cuenta explícita sin sesión; fachadas autorizan. Modo log por defecto, .eml privado sin SMTP; Mailpit captura local. Los estados de transporte SMTP/API no equivalen a entrega; el feedback posterior permanece separado. Credenciales cifradas/hidden, sin propiedades públicas Livewire, campos vacíos después de respuesta; guardarlas no habilita externo. TLS verifica certificado/nombre, sin autofirmados; plain sólo Mailpit local. Remitente lab_authorized no acredita dominio externo ni reputación; external_authorized es una autorización interna independiente.

## Actividad y métricas — T-14 revisada

Fuente: [guía de actividad](aplicacion/actividad-y-metricas.md),
[ActivityService](../app/Services/ActivityService.php),
[componente](../app/Livewire/Activity.php) y
[ledger](roadmap/2026-10-21-plataforma-emailing/tasks.md).

`activity.read` permite consultas, detalle, CSV y archivos privados sin exigir
`settings.read`. Las siguientes acciones y callbacks revalidan cuenta y permiso.
La cohorte se define por fecha de creación en días UTC [inicio, fin+1día);
los filtros también afectan a numerador y denominador. Entregas lógicas de
campañas/API/MCP, pruebas explícitas e históricos desconocidos conservan sus
cinco poblaciones separadas.
Los reintentos no crean otra entrega lógica y un registro histórico no prueba
una conexión SMTP ni una generación recuperable. Detalle incluye todos los
intentos vinculados y el actor disponible; no usa al viewer como autor.

CSV en lotes200, con reautorización y protección de fórmulas, ventana y fuente;
no es snapshot global y puede interrumpirse al cambiar permisos. Aceptación SMTP/API
no acredita entrega/apertura. El feedback de proveedor se muestra por separado;
sin eventos sigue desconocido. Listas de bloqueo, aperturas y clics no se miden;
sin muestra no equivale a 0% de incidencias. T-14 tiene gates finales verdes y [revisión 15 aprobada](roadmap/2026-10-21-plataforma-emailing/testing/fase-3/review-15.md):
20 pruebas/237 aserciones de Activity y 203 pruebas/2.466 aserciones del full.
El QA de navegador nativo/Docker de T-16 pasó 10/10 a la primera; M-01 sigue pendiente.

## Desarrollo, perfiles y evidencia de aplicación

Fuente: [desarrollo y pruebas](aplicacion/desarrollo.md), [informe QA fase 1](roadmap/2026-10-21-plataforma-emailing/testing/report.md), [guard SQL](../app/Support/TestingDatabaseGuard.php) y [scripts](../scripts).

Base/usuario exclusivos `emailing_testing`; guard antes de RefreshDatabase y sin DB_URL. `serve-testing.ps1 -Port 8082` genera `.env.qa` desde `.env.testing`, APP_ENV local para CSRF real y sesión/caché file; no ejecutar copias en paralelo. Docker QA 8083 usa override privado `tests/.runtime/compose.qa.yaml`, DB testing y cola `emailing.qa.jobs`. Coordinar suites/harness: comparten SQL y pueden limpiarlo. La última verificación operativa registrada dejó Compose base detenido, volúmenes intactos y cinco filas en `emailing.lab_smoke`; no es una observación continua. T-20 exige inspección actual de esquema, copia/plan, migración segura/restore y portabilidad final. URLs de storage genérico sin firma dan403 fuera de producción; recursos de otra cuenta mantienen404.

`.env.example` declara APP_URL 8080; Compose base fija APP_URL/puerto 8081. Editar `.env` no sustituye el valor YAML: otro origen exige override explícito. Código en imagen, reconstruir tras cambios PHP; assets reutilizan caché si no cambian entradas. Secretos/env/overrides privados ignorados. Fase1 conserva8/8; [QA3 fase2](roadmap/2026-10-21-plataforma-emailing/testing/fase-2/report-attempt-3.md) sobre c24d227 acredita20/20 con worker1/retries0/timeout360s, una entrada por caso y gates original/público idénticos exit0. Feature previa90 pruebas/977 aserciones no repetida en QA3. QA1 fase2 conserva8/12, QA2 fase2 conserva13/7 y focales mantienen sus rojos. En aquel QA, E2E-10/11 y los manuales seguían pendientes; E2E-10 está acreditado ahora por T-18, mientras E2E-11 y M-01/M-02 continúan pendientes. No hay cobertura porcentual acreditada. [Handoff de fase2](roadmap/2026-10-21-plataforma-emailing/testing/fase-2/raw/handoff-attempt-3.json): integridad37QA+2producto sin diferencias,20UUID verificados y cero restos propios registrados; sus observaciones de servicios son históricas. PDF10p/20colocaciones; fallo inicial de conteo de recursos conservado, misma exportación verificada sin alterar exit1 histórico.

[QA T-16, ciclo2/intento1](roadmap/2026-10-21-plataforma-emailing/testing/fase-3/t16-attempt-2/report.md): 10/10 r0, original/proyección equivalentes, siete helpers y 235 fuentes estables. Grupo complementario139/2009,17ventanas mapeadas; tres jobs nuevos Cronicle con auth ausente401 y válida/repetida created0. Seis namespaces limpios, cinco contenedores ausentes y dos tags propios retirados; otros dos tags de fix1 retenidos como artefactos. PDF11p/8imágenes validado, con errores de conversión conservados y perfiles retirados. 33capturas y checklist M-01 sin aprobación humana.

## Memoria técnica vigente

Fuente: [índice de decisiones](knowledge/README.md), [GOT-001](knowledge/gotchas/GOT-001-errores-livewire-tras-reintento.md), [GOT-002](knowledge/gotchas/GOT-002-limpieza-de-uploads-de-qa.md), [GOT-003](knowledge/gotchas/GOT-003-barreras-de-prueba-atomicas.md), [GOT-004](knowledge/gotchas/GOT-004-limpieza-delete-pending-en-windows.md) y [ADR-003](knowledge/adr/ADR-003-publicacion-confirmada-y-estado-smtp.md).

ADR-001/002/004 aceptadas: broker compartido, stack y FastCGI/origen confiable. GOT-001/002 aceptados tras revisión6: errores visibles conservados al validar campos auxiliares y limpieza con raíz efectiva/atribución exacta/continuidad ante fallos. GOT-003 aceptado tras revisión12 para barreras atómicas; GOT-004 tras revisión13 para eliminación pendiente en Windows y conservación del error primario. GOT-006 y GOT-007 aceptados tras revisiones18/19: presupuestos de bootstrap y confirmación explícita en batches Livewire. ADR-003 conserva su estado formal de propuesta; sus componentes y QA técnico T-10–T-16 están verificados. [ADR-006](knowledge/adr/ADR-006-identidad-google-y-oauth-mcp-delegado.md) está aceptada técnicamente tras la revisión 1 de contratos; identidad, acceso y catálogo MCP tienen QA de laboratorio. La operación de perfiles se documenta en [operación MCP](aplicacion/mcp-operacion.md), con Google y clientes reales pendientes en T14. [GOT-012](knowledge/gotchas/GOT-012-proxy-y-redirecciones-playwright.md) está aceptado tras revisión 2 para redirecciones y aislamiento de Playwright. Esto no constituye aprobación humana de una política Google nueva. El worker Laravel ordinario permanece separado; la ampliacion de proveedores incorpora feedback autenticado, con cualificacion privada real pendiente. Consulta el índice de conocimiento y el ledger para distinguir decisión formal, implementación, pruebas y aprobación humana.

## Persistencia y recuperación

Fuente: [respaldos y traslado](infraestructura/backup-migracion.md).

Seis volúmenes `emailing-infra_{rabbitmq-data,mysql-data,cronicle-data,cronicle-logs,cronicle-queue,mailpit-data}`. Datos Linux gestionados por Docker Desktop, no carpetas del repositorio. Recetas de respaldo/restauración propuestas, aún no ensayadas. Copia fría requiere parar productores/consumidores de todas las aplicaciones y los servicios; no tar de volúmenes vivos. Definitions de RabbitMQ no incluyen mensajes. Conservar secretos, identidades, imagen Cronicle y APP_KEY de aplicación. No sobrescribir volúmenes operativos durante un ensayo.

## Incidencias conocidas

Fuente: [troubleshooting](infraestructura/troubleshooting.md).

Mailpit web usa 18025; 11025 es SMTP y falla si se abre como HTTP. `localhost` de un contenedor es el propio contenedor. Network external inexistente requiere arrancar infra. Contraseña UI Cronicle puede diferir del valor inicial. Un nombre nuevo de proyecto puede crear volúmenes vacíos. Healthy no valida permisos de aplicación ni entrega a Internet.

## Entregabilidad y riesgo de listas de bloqueo

Fuente: [entregabilidad](infraestructura/entregabilidad.md),
[entrega y recuperación](aplicacion/entrega-y-recuperacion.md) y
[actividad](aplicacion/actividad-y-metricas.md). Consentimiento, bajas/supresiones,
cuotas, pausas y reintentos tienen implementación y pruebas de componentes
T-10–T-13; su integración técnica está acreditada por T-16. Faltan las pruebas
cruzadas, carga y restauración de T-19 y los controles vinculados a un proveedor real.
Mailpit captura pruebas. [Brevo/SendGrid](aplicacion/proveedores-email.md) incorporan SMTP/API y feedback autenticado, con salida deshabilitada por defecto y cualificacion privada real pendiente.
La aplicación no incorpora un MTA propio. Con un relay, el último salto al MX
sale de la IP de ese relay, que puede diferir de la IP del host de la aplicación.
Ninguna IP dedicada, consulta DNSBL ni autenticación garantiza ausencia de
bloqueo o llegada a inbox.

Para el envío externo siguen pendientes la autorización del dominio/remitente,
SPF/DKIM/DMARC y alineación, TLS y DNS/PTR del relay, y un mecanismo real de
cualificacion del feedback autenticado y correlacionado con la cuenta real. SMTP250 acredita aceptación del relay;
SMTP genérico puede no ofrecer quejas ni todos los eventos. Las métricas de
actividad deben conservar fuente, ventana y denominador, y mostrar datos
externos desconocidos. Una IP compartida participa en la reputación de otros
emisores; una dedicada requiere mantenimiento y calentamiento, sin mejora
universal garantizada. No rotar IPs para eludir bloqueos.

## Proveedores de correo

Fuente: [guía de operación](aplicacion/proveedores-email.md),
[contratos](api/README.md), [aceptación backend](roadmap/2026-10-30-proveedores-email/testing/acceptance.md)
y [QA3](roadmap/2026-10-30-proveedores-email/testing/qa-3/raw/qa-gate.json).

Cuatro recetas Brevo/SendGrid SMTP/API. Perfiles cifrados por cuenta, secretos
SMTP/API separados, destinos fijos, TLS verificado y sin SDK adicional.
`EMAIL_EXTERNAL_ENABLED` es false por defecto; exige perfil y remitente externo
autorizados antes de IO. REST 1.1.0/MCP 0.2.0 separan `api_accepted`,
`smtp_accepted` y feedback; conservan las 37 operaciones MCP y fixtures 0.1.0.

SendGrid verifica firma ECDSA P-256 sobre timestamp y cuerpo crudo, ventana 24h
y tolerancia futura 300s; Brevo compara Bearer privado de al menos 32 caracteres.
HTTPS, cuerpo 1MiB, lote SendGrid 1000, correlación opaca, dedupe y mutex de cuenta.
Rebote permanente/queja/baja aplican supresiones que un evento benigno no revierte.

API `429` permite como máximo un requeue seguro con `Retry-After` acotado.
Timeout/5xx/recibo inválido quedan inciertos, sin failover. Feedback de cualquier
intento impide otra reprogramación o un siguiente inicio durable. El mutex ordena
callback e inicio; no cancela IO empezado ni promete exactly-once externo.

Revisión 3 sin gaps y QA3 6/6 originales, cero flaky/omitidos/interrumpidos,
507 fuentes estables y limpieza propia acreditada. Servicios simulados y datos
sintéticos; M-01 real y M-02 humana siguen pendientes. Las cuatro migraciones
aditivas requieren inspección y aplicación en el destino; QA no migró la base
operativa. Principal conserva 18/20 y T19/T20 pendientes; MCP conserva 11/14 y
T12–T14 pendientes.
