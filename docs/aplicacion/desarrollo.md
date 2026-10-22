# Cómo preparar y comprobar la aplicación

**Última actualización: 2026-10-22.** Esta guía cubre el runtime y la fase 1: acceso, cuentas y Teammates. Los perfiles QA funcionan; la base operativa todavía no tiene las migraciones de aplicación.

## Qué está disponible

| Pieza | Estado y fuente |
|---|---|
| PHP / Laravel / Livewire | Runtime PHP 8.4; Laravel resuelto 13.38 y Livewire 4. Dependencias declaradas en [composer.json](../../composer.json). |
| Acceso y sesiones | Fortify, formularios en español y comprobación de sesiones tras reset. Véase [arquitectura](arquitectura.md). |
| Cuentas y Teammates | Membresías múltiples, invitaciones, roles, propiedad y auditoría. |
| MySQL / RabbitMQ / Mailpit | Servicios compartidos del [laboratorio](../infraestructura/servicios.md). |
| Sanctum | Dependencia instalada para el desarrollo posterior de la API; no hay API de negocio terminada. |
| Contactos, plantillas, campañas, SMTP por cuenta y entrega fiable | Pendientes del [roadmap](../roadmap/2026-10-21-plataforma-emailing/tasks.md). |

## Cómo preparar Windows sin tocar la base operativa

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

La base operativa `emailing` conserva cinco filas de `lab_smoke` y no tiene las migraciones de aplicación aplicadas en este corte. No ejecutes allí `migrate:fresh`, `db:wipe` ni la suite. T-20 contempla la migración operativa con preservación de datos y ensayo de recuperación.

## Cómo ejecutar las comprobaciones backend

Los comandos siguientes realizan escrituras en la base de pruebas y, para integración, en RabbitMQ. Comprueba antes la configuración local; no son consultas de estado.

```powershell
powershell -NoProfile -File scripts/php.ps1 artisan config:clear
powershell -NoProfile -File scripts/php.ps1 artisan test
powershell -NoProfile -File scripts/php.ps1 artisan test --filter=TeammateTest
powershell -NoProfile -File scripts/php.ps1 artisan app:runtime-smoke --env=testing
```

`app:runtime-smoke` usa la identidad de aplicación. Publica una sonda en una cola temporal, libera el trabajo, reconecta, consume y comprueba el ack. Limpia su archivo y cola al terminar correctamente. No envía SMTP ni acredita las garantías futuras de outbox o campañas.

[RuntimeIntegrationTest](../../tests/Feature/RuntimeIntegrationTest.php) prueba además aislamiento SQL, rechazo de credenciales AMQP en el vhost ajeno y consumo real. [TeammateConcurrencyTest](../../tests/Feature/TeammateConcurrencyTest.php) cubre operaciones concurrentes. Las suites de acceso verifican formularios, enlaces y sesiones anteriores a un reset.

## Cómo abrir el perfil HTTP nativo de QA

Prepara la base mediante las pruebas antes de abrir el panel. El servidor utiliza esa misma base y sus datos pueden desaparecer al repetir una suite con `RefreshDatabase`.

```powershell
powershell -NoProfile -File scripts/serve-testing.ps1 -Port 8082
python tests/support/http-profile-smoke.py http://127.0.0.1:8082
```

[serve-testing.ps1](../../scripts/serve-testing.ps1) genera `.env.qa` desde `.env.testing` y arranca PHP en segundo plano. Publica el PID y guarda logs en `storage/logs/qa-server.*.log`.

El perfil mantiene `APP_ENV=local` para comprobar CSRF real, pero exige la base y usuario de pruebas. Utiliza sesión y caché en archivos, cookie `emailing-qa-session` y SMTP a Mailpit. No inicies otra instancia del script en paralelo: ambas escribirían `.env.qa`, aunque elijas otro puerto.

El [smoke HTTP](../../tests/support/http-profile-smoke.py) acepta una URL posicional y solo permite los perfiles 8082 y 8083. Comprueba salud, login, rechazo CSRF y tipos de entrada inválidos. Necesita un servidor ya iniciado; no lo crea.

## Cómo se compone Docker

[compose.yaml](../../compose.yaml) declara solo `emailing-php`, `emailing-web` y `emailing-worker`. Reutiliza la red externa `emailing-lab`. PHP y worker comparten imagen y almacenamiento; Nginx sirve el contenido público.

PHP-FPM corre como `www-data` y escucha en `/run/emailing-fpm/php.sock`. El volumen del socket solo se monta en PHP y Nginx; Nginx lo monta en lectura. No hay listener FastCGI TCP 9000. El worker consume la cola efectiva de `config/queue.php`, sin fijar otra mediante `--queue`.

| Perfil | URL | Datos y límites |
|---|---|---|
| Ejemplo raíz | `http://127.0.0.1:8080` | Valor de `.env.example`; no inicia un servidor. |
| Compose base `emailing-app` | `http://127.0.0.1:8081` | Fija `APP_URL` y puerto en YAML; actualmente detenido, con volúmenes conservados. No es una instalación operativa migrada. |
| QA nativo | `http://127.0.0.1:8082` | `.env.qa`, base `emailing_testing`, sesiones en archivos. |
| QA Docker | `http://127.0.0.1:8083` | Override privado `tests/.runtime/compose.qa.yaml`, base de pruebas y cola `emailing.qa.jobs`. |

Cambiar `APP_URL` en `.env` no cambia el valor explícito del Compose base. Para otro origen necesitas un override explícito tanto de entorno como del puerto web. T-20 conserva pendiente la portabilidad final y el procedimiento de puesta en operación.

El override usado por QA es local e ignorado. Después de generar `.env.qa` con el script nativo, crea `tests/.runtime/compose.qa.yaml` con esta estructura, que no contiene credenciales:

```yaml
services:
  emailing-php:
    env_file: .env.qa
    environment:
      APP_URL: http://127.0.0.1:8083
      SESSION_COOKIE: emailing-dockerqa-session
      RABBITMQ_QUEUE: emailing.qa.jobs
  emailing-worker:
    env_file: .env.qa
    environment:
      APP_URL: http://127.0.0.1:8083
      SESSION_COOKIE: emailing-dockerqa-session
      RABBITMQ_QUEUE: emailing.qa.jobs
  emailing-web:
    ports: !override ["127.0.0.1:8083:80"]
```

Las rutas del override se resuelven respecto al primer Compose, ubicado en la raíz. `!override` sustituye la publicación base, evitando publicar también 8081. Utiliza un Docker Compose que reconozca esa etiqueta.

Mantén el nombre de proyecto `emailing-qa` en todos los comandos. Así seleccionas sus contenedores, volúmenes y alias, separados de `emailing-app`:

```powershell
docker compose -p emailing-qa --env-file .env -f compose.yaml -f tests/.runtime/compose.qa.yaml config --quiet
docker compose -p emailing-qa --env-file .env -f compose.yaml -f tests/.runtime/compose.qa.yaml up -d --build --wait --wait-timeout 240
docker compose -p emailing-qa --env-file .env -f compose.yaml -f tests/.runtime/compose.qa.yaml ps
python tests/support/http-profile-smoke.py http://127.0.0.1:8083
```

El perfil reutiliza las tablas de `emailing_testing` preparadas por la suite; no aplica migraciones a la base operativa. No publiques `.env`, `.env.testing`, `.env.qa` ni el override privado. Para reproducirlo en otro host, adapta las rutas del [harness QA](../roadmap/2026-10-21-plataforma-emailing/testing/playwright.config.mjs) y prepara las credenciales locales. Esta receta reproduce QA de fase 1; T-20 conserva la puesta en operación.

[docker/Dockerfile](../../docker/Dockerfile) copia el código a la imagen. Los cambios PHP requieren reconstruir y recrear los servicios afectados. La etapa de assets puede reutilizar caché cuando no cambian sus entradas; no hay un montaje de código que refleje automáticamente las ediciones del host.

## Qué acredita el QA actual

El [informe de fase 1](../roadmap/2026-10-21-plataforma-emailing/testing/report.md) registra 8/8 E2E aprobados: cuatro escenarios en cada perfil. No hay flaky, skipped ni interrupted. La duración total registrada es 261,882275 segundos.

La regresión backend aportada registra 33 pruebas y 262 aserciones aprobadas en 53,60 segundos. FB01 comprueba que un reintento válido de transferencia elimina el error anterior del mismo formulario.

Los 25 GWT están trazados, no todos ejecutados. E2E-05–11 y M-02 pertenecen a fases futuras. M-01 sigue pendiente de aceptación humana; las capturas y las medidas responsive no la sustituyen. No hay porcentaje de cobertura de código acreditado.

Para repetir E2E, revisa primero las rutas y dependencias locales de [playwright.config.mjs](../roadmap/2026-10-21-plataforma-emailing/testing/playwright.config.mjs). El informe es la fuente de resultados; esta guía no vuelve a ejecutar suites ni modifica sus artefactos.
