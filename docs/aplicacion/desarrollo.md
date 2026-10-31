# Cómo preparar y comprobar la aplicación

**Última actualización: 2026-10-31.** La aplicación principal tiene 18/20 tareas aceptadas técnicamente: incluye campañas, entrega, actividad y API. Las ampliaciones Google/MCP y proveedores mantienen sus propios ledgers. Los perfiles QA funcionan; las pruebas no acreditan el despliegue operativo y el estado de migraciones de su base necesita inspección antes del traslado.

## Qué está disponible

| Pieza | Estado y fuente |
|---|---|
| PHP / Laravel / Livewire | Laravel resuelto 13.38 y Livewire 4. T11 observó PHP/PHP-CGI 8.5.12 en Windows y PHP 8.4.27 en Docker; [matriz de perfiles](../roadmap/2026-10-24-mcp-google/testing/client-matrix.md). Dependencias declaradas en [composer.json](../../composer.json). |
| Acceso y sesiones | Fortify, formularios en español y comprobación de sesiones tras reset. Véase [arquitectura](arquitectura.md). |
| Cuentas y Teammates | Membresías múltiples, invitaciones, roles, propiedad y auditoría. |
| MySQL / RabbitMQ / Mailpit | Servicios compartidos del [laboratorio](../infraestructura/servicios.md). |
| API y credenciales | API T-18 implementada y aceptada técnicamente; [contrato e implementación](../api/README.md). Credenciales de cuenta y tokens MCP tienen propósitos distintos. |
| Contactos, listas e importación CSV | Implementados con límites, consentimiento e informes privados; [guía](contactos-e-importacion.md). |
| Plantillas y transporte por cuenta | Implementados: tres variables, preview aislada, pruebas en log privado/Mailpit; [guía](plantillas-y-transporte.md). |
| Brevo y SendGrid | Cuatro canales SMTP/API, perfiles cifrados, feedback autenticado y pruebas locales; [recetas y límites](proveedores-email.md). Salida externa deshabilitada por defecto. |
| Campañas, outbox, entrega, bajas, supresiones y cuotas | Implementadas y verificadas en laboratorio; [entrega y recuperación](entrega-y-recuperacion.md). Carga/restauración T-19 y revisión/QA final T-20 siguen pendientes; envío externo deshabilitado. |
| Google y MCP | Identidad, OAuth delegado y las 37 operaciones MCP aceptadas en laboratorio. T11 aceptada: perfiles HTTPS, recuperación y scheduler. Regresión global T12 y Google/clientes reales/M-01–M-04 en T14 pendientes; [ledger MCP](../roadmap/2026-10-24-mcp-google/tasks.md). |

## Cómo preparar Windows sin tocar la base operativa

Para instalar el producto en un destino nuevo y persistente, sigue la
[ruta Docker desde cero](../manual/guia-completa.md#instalación-docker-desde-cero)
o [Laragon nativo](../manual/guia-completa.md#instalación-nativa-en-windows-con-laragon).
Esas recetas incluyen preparación, migración no destructiva, primer registro,
workers y calendarios. Las secciones QA de esta página usan otra base y no son
una instalación personal persistente. El Dockerfile compila PHP/Composer y
Vite dentro de la imagen; PHP/Composer/Node locales pertenecen a la ruta nativa.

El manifiesto raíz declara PHP `^8.3`, pero el lock vigente resuelve Symfony con
requisito `>=8.4.1`. La imagen usa PHP 8.4; PHP 8.5.12 es una observación de QA
nativo. Instala versiones compatibles con el lock, sin rebajar sus comprobaciones.

Para publicar el recurso MCP por HTTPS, usa la [guía de operación MCP](mcp-operacion.md)
y los [ejemplos de clientes](mcp-clientes.md). El perfil HTTP de QA descrito más abajo
no sustituye el origen canónico TLS de OAuth. La calificación de ambos perfiles,
reinicio y recuperación se registra en la [matriz de clientes y perfiles](../roadmap/2026-10-24-mcp-google/testing/client-matrix.md).

Necesitas PowerShell, PHP 8.4, Composer, Node/npm y Docker Desktop con contenedores Linux. Ejecuta los comandos desde la raíz. Arranca primero la [infraestructura](../infraestructura/operacion.md).

```powershell
powershell -NoProfile -File scripts/composer.ps1 install
npm ci
npm run build
powershell -NoProfile -File scripts/provision-local.ps1
```

[provision-local.ps1](../../scripts/provision-local.ps1) genera o completa secretos locales, aprovisiona el vhost `emailing` y el usuario RabbitMQ `emailing-app`, sin etiqueta administrativa. También crea la base y usuario exclusivos `emailing_testing`. Conserva secretos existentes; no aplica migraciones a `emailing`.

Los wrappers [php.ps1](../../scripts/php.ps1) y [composer.ps1](../../scripts/composer.ps1) habilitan `sockets` mediante `storage/php-conf/sockets.ini`. El ajuste pertenece al proyecto; no modifica el `php.ini` global. [prepare-runtime.php](../../scripts/prepare-runtime.php) prepara directorios de caché, sesiones y logs.

El script Composer `setup` instala dependencias y compila assets. No reemplaza el aprovisionamiento de credenciales ni aplica migraciones. Evita interpretar un build correcto como una instalación operativa terminada.

## Qué base puede limpiarse en las pruebas

`emailing_testing` usa su propio usuario SQL. [phpunit.xml](../../phpunit.xml) selecciona esa base, MySQL y transportes de prueba en memoria para correo, sesión y cola por defecto.

[tests/TestCase.php](../../tests/TestCase.php) ejecuta [TestingDatabaseGuard](../../app/Support/TestingDatabaseGuard.php) antes de que `RefreshDatabase` pueda limpiar tablas. El guard exige MySQL, base y usuario `emailing_testing`, y ausencia de una URL de conexión alternativa.

De la base operativa `emailing` constan 13 tablas y cinco filas de `lab_smoke`; no se ha acreditado su estado de migraciones. No ejecutes allí `migrate:fresh`, `db:wipe` ni la suite con `RefreshDatabase`. T-20 contempla inspección del esquema, copia y plan antes de migrar, con preservación de datos y ensayo de recuperación. Un reset no es el procedimiento de despliegue.

## Cómo ejecutar las comprobaciones backend

Los comandos siguientes realizan escrituras en la base de pruebas y, para integración, en RabbitMQ. Comprueba antes la configuración local; no son consultas de estado.

```powershell
powershell -NoProfile -File scripts/php.ps1 artisan config:clear
powershell -NoProfile -File scripts/php.ps1 artisan test
powershell -NoProfile -File scripts/php.ps1 artisan test --filter=TeammateTest
powershell -NoProfile -File scripts/php.ps1 artisan test --filter=ContactImportTest
powershell -NoProfile -File scripts/php.ps1 artisan test --filter=TemplateTest
powershell -NoProfile -File scripts/php.ps1 artisan test --filter=MailTransportTest
powershell -NoProfile -File scripts/php.ps1 artisan app:runtime-smoke --env=testing
```

`app:runtime-smoke` usa la identidad de aplicación. Publica una sonda en una cola temporal, libera el trabajo, reconecta, consume y comprueba el ack. Limpia su archivo y cola al terminar correctamente. No envía SMTP; outbox y campañas tienen sus propias pruebas posteriores y no se acreditan mediante esta sonda.

[RuntimeIntegrationTest](../../tests/Feature/RuntimeIntegrationTest.php) prueba además aislamiento SQL, rechazo de credenciales AMQP en el vhost ajeno y consumo real. [TeammateConcurrencyTest](../../tests/Feature/TeammateConcurrencyTest.php) cubre operaciones concurrentes. Las suites de acceso verifican formularios, enlaces y sesiones anteriores a un reset.

[ContactListTest](../../tests/Feature/ContactListTest.php), [ContactImportTest](../../tests/Feature/ContactImportTest.php), [TemplateTest](../../tests/Feature/TemplateTest.php) y [MailTransportTest](../../tests/Feature/MailTransportTest.php) cubren reglas de fase 2. No las ejecutes durante E2E o uso manual: ambos perfiles HTTP comparten la misma base de pruebas. Coordina una ventana exclusiva antes de preparar fixtures o refrescar tablas.

## Cómo abrir el perfil HTTP nativo de QA

Prepara la base mediante las pruebas antes de abrir el panel. El servidor utiliza esa misma base y sus datos pueden desaparecer al repetir una suite con `RefreshDatabase`.

```powershell
powershell -NoProfile -File scripts/serve-testing.ps1 -Port 8082
python tests/support/http-profile-smoke.py http://127.0.0.1:8082
```

[serve-testing.ps1](../../scripts/serve-testing.ps1) genera `.env.qa` desde `.env.testing` y arranca PHP en segundo plano. Publica el PID y guarda logs en `storage/logs/qa-server.*.log`.

El perfil mantiene `APP_ENV=local` para comprobar CSRF real, pero exige la base y usuario de pruebas. Utiliza sesión y caché en archivos, cookie `emailing-qa-session` y SMTP a Mailpit. No inicies otra instancia del script en paralelo: ambas escribirían `.env.qa`, aunque elijas otro puerto. El kernel de fixtures de QA usa `testing`; ese valor no sustituye el entorno HTTP efectivo `local`.

Las URLs del storage genérico sin firma devuelven 403 fuera de producción. Es una comprobación distinta de los recursos de otra cuenta, que mantienen 404. No atribuyas el comportamiento del servidor PHP nativo a Apache.

Los wrappers y `serve-testing.ps1` copian [emailing-uploads.ini](../../docker/emailing-uploads.ini) al directorio de ini del proyecto: upload 6M, POST 8M, memoria 256M. El límite funcional CSV sigue en 5 MiB/10.000 filas. Nginx admite body de 8m. Subir el límite exige coordinar ambas capas, configuración Livewire y runtime; véase [importación](contactos-e-importacion.md).

El [smoke HTTP](../../tests/support/http-profile-smoke.py) acepta una URL posicional y solo permite los perfiles 8082 y 8083. Comprueba salud, login, rechazo CSRF y tipos de entrada inválidos. Necesita un servidor ya iniciado; no lo crea.

## Cómo se compone Docker

[compose.yaml](../../compose.yaml) declara `emailing-php`, `emailing-web`,
`emailing-worker`, `emailing-jobs` y `emailing-scheduler`, y reutiliza la red externa `emailing-lab`.
PHP y los consumidores comparten imagen y almacenamiento; Nginx sirve el
contenido público. La separación de consumidores está revisada como componente
T-13. Su QA integral T-16 está aceptado en laboratorio; el despliegue operativo T-20 sigue pendiente. Los
recibos históricos de fases 1 y 2 corresponden al Compose anterior.

PHP-FPM corre como `www-data` y escucha en `/run/emailing-fpm/php.sock`. El volumen del socket solo se monta en PHP y Nginx; Nginx lo monta en lectura. No hay listener FastCGI TCP 9000. `emailing-worker` ejecuta `deliveries:work` para los eventos JSON de dominio. `emailing-jobs` ejecuta el worker ordinario Laravel y consume la cola efectiva de `config/queue.php`, sin fijar otra mediante `--queue`. Sus colas deben ser distintas. Consulta [entrega y recuperación](entrega-y-recuperacion.md) para el estado de validación del protocolo de dominio.

`emailing-scheduler` ejecuta `schedule:work`: activa el calendario de Laravel,
incluido `mcp:prune --limit=1000` cada minuto. Comparte la configuración y el
almacenamiento de caché de la aplicación; su recuperación y los locks de
`withoutOverlapping` se explican en [operación MCP](mcp-operacion.md).
Es un servicio de esta aplicación: reutiliza la infraestructura y mantiene el
Cronicle central existente, sin crear otra instancia ni duplicar su calendario.

| Perfil | URL | Datos y límites |
|---|---|---|
| Ejemplo raíz | `http://127.0.0.1:8080` | Valor de `.env.example`; no inicia un servidor. |
| Compose base `emailing-app` | `http://127.0.0.1:8081` | Uso persistente con base `emailing`; la última observación lo dejó detenido. Inspecciona el estado actual antes de migrar/arrancar; receta completa en el manual. |
| QA nativo | `http://127.0.0.1:8082` | `.env.qa`, base `emailing_testing`, sesiones en archivos. |
| QA Docker | `http://127.0.0.1:8083` | Override privado `tests/.runtime/compose.qa.yaml`, base de pruebas y colas QA separadas para jobs y entregas. |

Cambiar `APP_URL` en `.env` no cambia el valor explícito del Compose base. Para otro origen necesitas un override explícito tanto de entorno como del puerto web. T-20 conserva pendiente la portabilidad final y el procedimiento de puesta en operación.

El override QA es local e ignorado. La receta siguiente describe ese perfil
histórico separado; no reemplaza sus recibos ni la instalación persistente.
Después de generar `.env.qa` con el script nativo, crea
`tests/.runtime/compose.qa.yaml` con esta estructura sin credenciales:

```yaml
x-qa-environment: &qa-environment
  APP_URL: http://127.0.0.1:8083
  SESSION_COOKIE: emailing-dockerqa-session
  CACHE_PREFIX: emailing-qa
  RABBITMQ_QUEUE: emailing.qa.jobs
  DELIVERY_EXCHANGE: emailing.qa.deliveries
  DELIVERY_QUEUE: emailing.qa.deliveries
  DELIVERY_FAILURE_QUEUE: emailing.qa.deliveries.failed
  DELIVERY_RELAY_POOL: mailpit-qa
services:
  emailing-php:
    env_file: .env.qa
    environment: *qa-environment
  emailing-worker:
    env_file: .env.qa
    environment: *qa-environment
  emailing-jobs:
    env_file: .env.qa
    environment: *qa-environment
  emailing-scheduler:
    env_file: .env.qa
    environment: *qa-environment
  emailing-web:
    ports: !override ["127.0.0.1:8083:80"]
```

Las rutas del override se resuelven respecto al primer Compose, ubicado en la raíz. `!override` sustituye la publicación base, evitando publicar también 8081. Utiliza un Docker Compose que reconozca esa etiqueta.

El nombre de proyecto separa contenedores y volúmenes, pero no renombra las colas del broker compartido. Por eso web y ambos consumidores reciben las mismas referencias QA y no los valores de entrega operativos. Un override antiguo que solo cambia `RABBITMQ_QUEUE` no aísla el consumidor nuevo de dominio. Para otro perfil nativo aplica la misma separación en su configuración efectiva antes de iniciar sus consumidores.

Mantén el nombre de proyecto `emailing-qa` en todos los comandos. Así seleccionas sus contenedores, volúmenes y alias, separados de `emailing-app`:

```powershell
docker compose -p emailing-qa --env-file .env -f compose.yaml -f tests/.runtime/compose.qa.yaml config --quiet
docker compose -p emailing-qa --env-file .env -f compose.yaml -f tests/.runtime/compose.qa.yaml up -d --build --wait --wait-timeout 240
docker compose -p emailing-qa --env-file .env -f compose.yaml -f tests/.runtime/compose.qa.yaml ps
python tests/support/http-profile-smoke.py http://127.0.0.1:8083
```

El perfil reutiliza las tablas de `emailing_testing` preparadas por la suite; no aplica migraciones a la base operativa. No publiques `.env`, `.env.testing`, `.env.qa` ni el override privado. Para reproducirlo en otro host, adapta las rutas del [harness de fase 2](../roadmap/2026-10-21-plataforma-emailing/testing/fase-2/playwright.config.mjs) y prepara las credenciales locales. Esta receta prepara perfiles de pruebas; T-20 conserva la puesta en operación.

[docker/Dockerfile](../../docker/Dockerfile) copia el código a la imagen. Los cambios PHP requieren reconstruir y recrear los servicios afectados. La etapa de assets puede reutilizar caché cuando no cambian sus entradas; no hay un montaje de código que refleje automáticamente las ediciones del host.

## Qué acredita el QA actual

T11 verificó técnicamente los perfiles HTTPS Laragon y Docker con ambos protocolos
MCP, reinicio, rotación, revocación, restore de fixtures y dos pasadas reales del
scheduler por perfil. La [evidencia de implementación](../roadmap/2026-10-24-mcp-google/testing/fase-5/t11/implementation.md)
conserva ocho pruebas backend/61 aserciones y 135 pruebas Python. El
[QA independiente T11](../roadmap/2026-10-24-mcp-google/testing/fase-5/t11/qa-2026-10-30/report/report.md)
acredita nueve escenarios originales a la primera, gate verde y limpieza propia
admitida. Esta tanda no repite la estabilidad E2E-09 ×3 de T10 ni la regresión
global T12. T11 está aceptada en el ledger; Google/clientes reales y
M-01–M-04 del destino permanecen en T14. T19 conserva el restore global.

El [informe de fase 1](../roadmap/2026-10-21-plataforma-emailing/testing/report.md) registra 8/8 E2E aprobados: cuatro escenarios en cada perfil. No hay flaky, skipped ni interrupted. La duración total registrada es 261,882275 segundos.

La regresión backend aportada registra 33 pruebas y 262 aserciones aprobadas en 53,60 segundos. FB01 comprueba que un reintento válido de transferencia elimina el error anterior del mismo formulario.

El [QA3 de fase 2](../roadmap/2026-10-21-plataforma-emailing/testing/fase-2/report-attempt-3.md), sobre `c24d227adab97a0036e45c1728bd679ac7af7c47`, registra 20/20 casos: 10 nativos y 10 Docker. E2E-01–04 vuelven a comprobar la fase 1; E2E-05 aporta tres casos por perfil, E2E-06 dos y E2E-07 uno. Cada caso tuvo un intento, worker 1, retries 0 y timeout 360 segundos. Ambos gates reales fueron idénticos, exit 0; no hubo flaky, skipped ni interrupted.

La suite Feature previa del incremento pasó 90 pruebas y 977 aserciones; QA3 no volvió a ejecutarla. QA1 de fase 2 conserva 8 aprobados/12 fallidos y QA2, 13/7. Los focales conservan 6/2, 1/1 y E07 final 2/0. El verde completo no convierte los rojos anteriores en verdes ni demuestra una causa para la observación nativa intermedia.

El [handoff terminal](../roadmap/2026-10-21-plataforma-emailing/testing/fase-2/raw/handoff-attempt-3.json) libera la base de pruebas y acredita integridad de 37 fuentes QA y dos de producto/test, sin diferencias. Verificó veinte UUID y cero restos propios registrados, conservando históricos no atribuibles. No implica cero archivos globales; consulta [GOT-002](../knowledge/gotchas/GOT-002-limpieza-de-uploads-de-qa.md).

El [PDF](../roadmap/2026-10-21-plataforma-emailing/testing/fase-2/report-attempt-3.pdf) final tiene diez páginas y veinte colocaciones de capturas. La primera comprobación contó recursos deduplicados y falló; su exit 1 permanece en el recibo. La exportación fue exit 0 y la verificación corregida de la misma exportación también exit 0.

Los GWT están trazados, no todos ejecutados. En la plataforma principal,
E2E-08/09 están acreditados por el [QA de entrega T-16](../roadmap/2026-10-21-plataforma-emailing/testing/fase-3/t16-attempt-2/report.md)
y E2E-10 por el [QA de API T-18](../roadmap/2026-10-21-plataforma-emailing/testing/fase-4/t18-api/report.md).
E2E-11 y M-02 siguen pendientes. M-01 requiere aceptación humana; las capturas
y medidas responsive, incluida la corrección móvil `9109d87`, no la sustituyen.
Los identificadores E2E de MCP pertenecen a su propio test-plan. No hay porcentaje
de cobertura de código acreditado ni benchmark universal de importación.

Para repetir E2E, revisa primero las rutas, dependencias y limpieza atribuida del [harness de fase 2](../roadmap/2026-10-21-plataforma-emailing/testing/fase-2/playwright.config.mjs). Su invocación QA3 fue `python docs/roadmap/2026-10-21-plataforma-emailing/testing/fase-2/run.py 3`, con Node 22 en PATH. Es un recibo histórico: repetirlo puede escribir fixtures, limpiar SQL y sobrescribir artefactos del intento. Prepara una tanda nueva y conserva las evidencias anteriores antes de ejecutarla. El informe es la fuente de resultados.

## Cómo se verifica la ampliación de proveedores

Las pruebas `MailProvider*` usan credenciales sintéticas y la identidad SQL
exclusiva `emailing_testing`. Los fixtures de cable verifican TLS contra peers
propios. El [harness de navegador](../../tests/support/mail-provider-browser-run.py)
contiene sus procesos, usa HTTPS loopback y adaptadores controlados para
proveedores; Mailpit conserva captura real propia. Requiere las dependencias del
lock y Chrome instalado. No uses claves del operador para QA local.

[QA3](../roadmap/2026-10-30-proveedores-email/testing/qa-3/raw/qa-gate.json)
acredita 6/6 escenarios originales, sin flaky, omitidos ni interrumpidos.
La [aceptación backend](../roadmap/2026-10-30-proveedores-email/testing/acceptance.md)
mantiene los resultados originales y sus fuentes, sin sumar tandas solapadas.
La paridad de fuentes y la limpieza propia están enlazadas en la
[guía de proveedores](proveedores-email.md#qué-acredita-el-laboratorio).

Las cuatro migraciones aditivas de proveedores se prueban en la base exclusiva
de QA. La base operativa no se ha migrado con estas pruebas: inspecciona el
destino y prepara copia y recuperación antes de aplicarlas. La cualificación
privada de los cuatro canales y la revisión humana siguen en M-01/M-02 del
[ledger propio](../roadmap/2026-10-30-proveedores-email/tasks.md). La plataforma
mantiene T19/T20 pendientes y MCP conserva 11/14, con T12–T14 pendientes.
