# Emailing

Aplicación interna de correo con cuentas compartidas mediante Teammates.
Laravel 13, PHP 8.4, Livewire 4 y MySQL 8.4; infraestructura independiente con
RabbitMQ, Cronicle y Mailpit.

Están implementados el registro configurable, acceso, recuperación de contraseña,
selector de cuentas, invitaciones, roles, auditoría, contactos y listas compartidos,
importación CSV, plantillas con previsualización aislada y transporte por cuenta
mediante log o Mailpit. Campañas, bajas y supresiones, entrega mediante workers y
API siguen el [roadmap](docs/roadmap/README.md).

El laboratorio utiliza **Mailpit** y mantiene el envío externo deshabilitado.
Su interfaz está en <http://127.0.0.1:18025>; el puerto `11025` recibe SMTP.

## Documentación

- [Guías técnicas y de uso](docs/README.md).
- [Contactos, listas e importación CSV](docs/aplicacion/contactos-e-importacion.md).
- [Plantillas y transporte por cuenta](docs/aplicacion/plantillas-y-transporte.md).
- [Infraestructura compartida](infra/README.md).
- [Configuración y credenciales](docs/infraestructura/configuracion.md).
- [Entregabilidad y reputación](docs/infraestructura/entregabilidad.md).
- [Decisiones y problemas conocidos](docs/knowledge/README.md).
- [Informe de pruebas de cuentas y Teammates](docs/roadmap/2026-10-21-plataforma-emailing/testing/report.md).
- [QA de contactos, plantillas y transportes](docs/roadmap/2026-10-21-plataforma-emailing/testing/fase-2/report-attempt-3.md).

## Desarrollo local

Requiere PHP 8.4 con sus extensiones, Composer, Node 22, Docker Desktop con
contenedores Linux y Docker Compose. Ejecuta desde la raíz en PowerShell:

```powershell
powershell -NoProfile -File infra/init.ps1
docker compose --env-file infra/.env -f infra/compose.yaml up -d --build --wait --wait-timeout 240
powershell -NoProfile -File scripts/composer.ps1 install --no-interaction
npm ci
npm run build
powershell -NoProfile -File scripts/provision-local.ps1
powershell -NoProfile -File scripts/php.ps1 artisan test --testsuite=Feature
powershell -NoProfile -File scripts/serve-testing.ps1 -Port 8082
```

Este recorrido prepara un **perfil de pruebas** en
<http://127.0.0.1:8082>, con base y usuario exclusivos `emailing_testing`.
Las pruebas de backend refrescan esa base: ejecútalas antes de iniciar E2E
o de utilizarla manualmente. El perfil mantiene CSRF real.

Los scripts generan los secretos locales fuera de Git y conservan los existentes.
No aplican migraciones a la base operativa `emailing`, que conserva el marcador
del laboratorio. El despliegue operativo y su ensayo de restauración se completan
en la fase final del roadmap.

## Contenedores de aplicación

[compose.yaml](compose.yaml) declara PHP-FPM, Nginx y un worker. Reutiliza la
red externa `emailing-lab`; los servicios compartidos pertenecen únicamente a
[infra/compose.yaml](infra/compose.yaml).

El perfil base publica HTTP en `8081`. El perfil Docker utilizado por QA publica
`8083` mediante un override local ignorado y utiliza la base de pruebas. Consulta
las guías antes de arrancar el perfil base: `APP_URL` y las credenciales deben
corresponder al despliegue. Los cambios de código requieren reconstruir la imagen.

## Comprobaciones

```powershell
powershell -NoProfile -File scripts/php.ps1 vendor/bin/pint --test
powershell -NoProfile -File scripts/composer.ps1 validate --strict
python scripts/check-public-data.py
```

Los resultados y límites de cada fase se registran en el
[ledger](docs/roadmap/2026-10-21-plataforma-emailing/tasks.md). El informe distingue
las pruebas automáticas de la aceptación visual que debe realizar una persona.
