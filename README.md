# Emailing

**Última actualización: 2026-10-31.**

Aplicación interna de correo con cuentas compartidas mediante Teammates.
Laravel 13, PHP 8.4, Livewire 4 y MySQL 8.4; infraestructura independiente con
RabbitMQ, Cronicle y Mailpit.

Están implementados el registro configurable, acceso, recuperación de contraseña,
selector de cuentas, invitaciones, roles, auditoría, contactos y listas compartidos,
importación CSV, plantillas con previsualización aislada y transporte por cuenta
mediante log o Mailpit. Campañas, bajas/supresiones, entrega mediante workers,
actividad y Cronicle tienen verificación técnica; el QA de envío acredita 10/10
recorridos. La [API de envío y gestión de llaves](docs/api/README.md) está
implementada y revisada; su QA acredita dos recorridos completos, uno en Laragon
y otro en Docker. Google y las 37 herramientas MCP tienen pruebas técnicas de
laboratorio; Google/clientes reales, carga/restauración y revisión humana siguen el
[roadmap](docs/roadmap/README.md).

El transporte por defecto es log; **Mailpit** permite capturar SMTP local.
Brevo/SendGrid ofrecen cuatro canales SMTP/API y feedback autenticado, con
salida externa deshabilitada por defecto y cualificación privada pendiente.
Su interfaz está en <http://127.0.0.1:18025>; el puerto `11025` recibe SMTP.

## Documentación

- [Manual completo: instalación desde cero, uso y operación](docs/manual/guia-completa.md).
- [Manual completo en PDF, con índice y diagramas](docs/manual/guia-completa.pdf).
- [Matriz de cobertura de pantallas, configuración, REST y 37 herramientas MCP](docs/manual/cobertura-documentacion.md).
- [Guías técnicas y de uso](docs/README.md).
- [Contactos, listas e importación CSV](docs/aplicacion/contactos-e-importacion.md).
- [Plantillas y transporte por cuenta](docs/aplicacion/plantillas-y-transporte.md).
- [Pruebas de entrega y perfiles de QA](docs/aplicacion/qa-entrega.md).
- [Infraestructura compartida](infra/README.md).
- [Configuración y credenciales](docs/infraestructura/configuracion.md).
- [Entregabilidad y reputación](docs/infraestructura/entregabilidad.md).
- [Decisiones y problemas conocidos](docs/knowledge/README.md).
- [Informe de pruebas de cuentas y Teammates](docs/roadmap/2026-10-21-plataforma-emailing/testing/report.md).
- [QA de contactos, plantillas y transportes](docs/roadmap/2026-10-21-plataforma-emailing/testing/fase-2/report-attempt-3.md).
- [QA de envío y recuperación](docs/roadmap/2026-10-21-plataforma-emailing/testing/fase-3/t16-attempt-2/report.md).

## Instalación persistente desde cero con Docker

Requiere Docker Desktop con contenedores Linux, Compose y PowerShell. El
Dockerfile instala PHP/Composer y compila Vite dentro de las imágenes: esta
ruta no exige PHP, Composer ni Node en el host. Ejecuta desde la raíz solo en
un destino limpio; en uno existente inspecciona esquema y prepara copia antes
de migrar. Procedimiento documentado, sin nuevo ensayo de puesta en operación:

```powershell
powershell -NoProfile -File infra/init.ps1
docker compose --env-file infra/.env -f infra/compose.yaml up -d --build --wait --wait-timeout 240
powershell -NoProfile -File scripts/provision-local.ps1
docker compose -f compose.yaml config --quiet
docker compose -f compose.yaml build
docker compose -f compose.yaml run --rm --no-deps emailing-php php artisan migrate:status
docker compose -f compose.yaml run --rm --no-deps emailing-php php artisan migrate --force
docker compose -f compose.yaml up -d --wait
```

Abre <http://127.0.0.1:8081> y registra la primera identidad si el registro está
habilitado. La base de uso persistente es `emailing`. El aprovisionamiento
también crea `emailing_testing`, reservada para suites que pueden limpiar tablas;
no es el espacio de uso personal. Para campañas completa los eventos Cronicle
del [manual](docs/manual/guia-completa.md#preparar-los-dos-eventos-cronicle).

Los scripts generan los secretos locales fuera de Git y conservan los existentes.
El script de aprovisionamiento no migra la base operativa: el paso explícito
`migrate --force` aplica pendientes al destino elegido. No uses `migrate:fresh`
ni `db:wipe` allí. Conserva APP_KEY y volúmenes. La plataforma mantiene 18/20 y
MCP 11/14; el despliegue final/restore global y validaciones reales conservan sus
puertas pendientes. El manual distingue procedimiento de evidencia ejecutada.

## Desarrollo nativo y QA

Para Laragon necesitas PHP compatible con el lock (actualmente mínimo efectivo
8.4.1), Composer y Node/npm además de la infraestructura. El manifiesto raíz
declara `^8.3`; esa declaración no rebaja los requisitos resueltos del lock.
El [manual](docs/manual/guia-completa.md#instalación-nativa-en-windows-con-laragon)
explica la instalación persistente nativa. [Desarrollo y pruebas](docs/aplicacion/desarrollo.md)
mantiene los perfiles QA 8082/8083 y sus precauciones de aislamiento.

## Contenedores de aplicación

[compose.yaml](compose.yaml) declara cinco servicios: Nginx, PHP-FPM, consumidor
de entregas, worker de jobs Laravel y scheduler de mantenimiento. Reutiliza la
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
