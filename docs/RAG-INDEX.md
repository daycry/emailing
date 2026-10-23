# Emailing — índice de conocimiento para agentes

**Última actualización: 2026-10-23.** Este índice resume documentación técnica y enlaza fuentes; no sustituye el código ni los resultados de pruebas.

## Estado real y navegación

Fuente: [README de documentación](README.md), [roadmap](roadmap/README.md) e [informe QA de infraestructura](roadmap/2026-10-21-infra-laboratorio/testing/report.md).

La infraestructura está implementada y probada: cuatro servicios healthy, protocolos, persistencia tras recreación, conectividad desde otro contenedor y tres E2E aprobados. La aplicación tiene fases 1/2: auth, cuentas/Teammates, contactos/listas, CSV, plantillas y transporte por cuenta log/Mailpit. QA3 de fase 2 acredita 20/20, diez por perfil, sin failed/flaky/skipped/interrupted; M-01 humana pendiente. Campañas, API, outbox, bajas, supresiones, cuotas y entrega externa son futuros. De `emailing` sólo constan 13 tablas y `lab_smoke` con cinco filas; el estado de migraciones requiere inspección en T-20.

## Topología y aislamiento

Fuente: [arquitectura](infraestructura/arquitectura.md), [Compose](../infra/compose.yaml) y [ADR-001](knowledge/adr/ADR-001-rabbitmq-centralizado.md).

Proyecto `emailing-infra`; bridge local `emailing-lab`; hostnames `lab-rabbitmq` y `lab-cronicle`. Un único host, sin HA ni Supervisor. El [Compose de aplicación](../compose.yaml) ya declara PHP, Nginx y worker; usa red externa y credenciales propias. Cronicle no monta Docker socket. Vhosts aíslan permisos/topología, no recursos físicos.

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

Laravel 13.38/PHP 8.4/Livewire 4 y MySQL 8.4. Compose de tres servicios sin duplicar infraestructura. Usuario `emailing-app` sin administración global, vhost `emailing`, aprovisionados por `scripts/provision-local.ps1`. Worker en primer plano con cola efectiva de configuración. FastCGI solo por socket Unix de volumen privado PHP/Nginx; PHP non-root, Nginx monta lectura, sin TCP 9000. Alias `${COMPOSE_PROJECT_NAME:-emailing-app}-web`. Cronicle HTTP autenticado sigue pendiente T-15; un propietario por calendario.

## Cuentas, Teammates y acceso

Fuente: [arquitectura de aplicación](aplicacion/arquitectura.md), [guía de usuario](aplicacion/cuentas-y-equipo.md), [AccountContext](../app/Services/AccountContext.php), [TeamService](../app/Services/TeamService.php) y [Permissions](../app/Services/Permissions.php).

Identidad multi-cuenta mediante membresía; recursos compartidos por cuenta. Registro configurable crea propietario Admin atómicamente. Admin/Read-only/Restricted y presets Developer/Marketer/Accountant; los scopes de módulos futuros no los implementan. Invitación siete días, hash SHA-256, email destinatario, un solo uso, expiración/revocación/reenvío con rotación y bloqueo concurrente. Transferencia reautentica contraseña, promueve nuevo owner Admin y conserva Admin anterior. Protegidos propietario y último Admin. Revocación/reducción aplican en siguiente acción y en lecturas de módulos. Reset invalida sesiones previas; enlaces secretos usan APP_URL confiable. SMTP de auth/invitaciones captura en Mailpit y mantiene configuración separada del transporte de pruebas por cuenta.

## Contactos, listas y CSV

Fuente: [guía funcional y técnica](aplicacion/contactos-e-importacion.md), [ContactService](../app/Services/ContactService.php), [ContactCsv](../app/Support/ContactCsv.php) y [ContactImportService](../app/Services/ContactImportService.php).

Cuenta delimita consultas/acciones; panel exige contacts.read y escritura añade contacts.write. Email trim/minúsculas, único activo por cuenta; email inmutable al editar. Consentimiento unknown por defecto; granted exige fuente/fecha UTC no futura; revoked no se reactiva por CRUD. Borrado lógico y retirada de asociaciones. CSV UTF-8/BOM, comas/comillas/LF/CRLF, cinco encabezados admitidos con email obligatorio; 5 MiB/10.000 registros, validación estructural completa antes de persistir, lotes 500 en transacción. Duplicados activos y repetidos válidos se omiten sin actualizar datos/consentimiento/listas. Sólo nuevos van al destino. Informes 25 filas/página e historial 10; descarga privada formula-safe, sin exportación general de contactos. Upload ligado a sesión/actor/cuenta una hora, disco privado contact-imports/temporary; PHP 6M/post8M y Nginx8m. QA limpia referencias propias comprobadas, conserva históricos sin atribución.

## Plantillas y transporte de laboratorio

Fuente: [guía funcional y técnica](aplicacion/plantillas-y-transporte.md), [TemplateRenderer](../app/Support/TemplateRenderer.php), [TemplatePreview](../app/Support/TemplatePreview.php), [AccountMailTransport](../app/Services/AccountMailTransport.php) y [MailSettingsService](../app/Services/MailSettingsService.php).

Tres variables: contact.name/contact.email/account.name; una pasada, HTML escapado y variables HTML sólo en texto del cuerpo. Asunto200 caracteres antes/después sin CR/LF; fuentes100.000 bytes/200 variables, resultado200.000 bytes. HTML/texto alternativos, ambos vacíos rechazados; texto derivado si falta. Preview de formulario con allowlist+sandbox+CSP sin recursos activos; email conserva HTML y enlaces. Prueba usa versión guardada, templates.read+mail.send sin contacts.read/settings.read. Transporte recibe cuenta explícita sin sesión; fachadas autorizan. Modo log por defecto, .eml privado sin SMTP; Mailpit captura local. Estados preparing/logged/smtp_accepted/smtp_unconfirmed/local_failed/blocked no equivalen a entrega. Credenciales cifradas/hidden, sin propiedades públicas Livewire, campos vacíos después de respuesta; guardarlas no habilita externo. TLS verifica certificado/nombre, sin autofirmados; plain sólo Mailpit local. Remitente lab_authorized no acredita dominio externo ni reputación.

## Desarrollo, perfiles y evidencia de aplicación

Fuente: [desarrollo y pruebas](aplicacion/desarrollo.md), [informe QA fase 1](roadmap/2026-10-21-plataforma-emailing/testing/report.md), [guard SQL](../app/Support/TestingDatabaseGuard.php) y [scripts](../scripts).

Base/usuario exclusivos `emailing_testing`; guard antes de RefreshDatabase y sin DB_URL. `serve-testing.ps1 -Port 8082` genera `.env.qa` desde `.env.testing`, APP_ENV local para CSRF real y sesión/caché file; no ejecutar copias en paralelo. Docker QA 8083 usa override privado `tests/.runtime/compose.qa.yaml`, DB testing y cola `emailing.qa.jobs`. Coordinar suites/harness: comparten SQL y pueden limpiarlo. Compose base detenido, volúmenes intactos; `emailing.lab_smoke` conserva cinco filas; estado de migraciones desconocido. T-20 contempla inspección de esquema, copia/plan, migración segura/restore y portabilidad final. URLs de storage genérico sin firma dan403 fuera de producción; recursos de otra cuenta mantienen404.

`.env.example` declara APP_URL 8080; Compose base fija APP_URL/puerto 8081. Editar `.env` no sustituye el valor YAML: otro origen exige override explícito. Código en imagen, reconstruir tras cambios PHP; assets reutilizan caché si no cambian entradas. Secretos/env/overrides privados ignorados. Fase1 conserva8/8; [QA3 fase2](roadmap/2026-10-21-plataforma-emailing/testing/fase-2/report-attempt-3.md) sobre c24d227 acredita20/20 con worker1/retries0/timeout360s, una entrada por caso y gates original/público idénticos exit0. Feature previa90 pruebas/977 aserciones no repetida en QA3. QA1 fase2 conserva8/12, QA2 fase2 conserva13/7 y focales mantienen sus rojos. M-01 humana y E2E-08–11/M-02 pendientes; no hay cobertura porcentual acreditada. [Handoff final](roadmap/2026-10-21-plataforma-emailing/testing/fase-2/raw/handoff-attempt-3.json): integridad37QA+2producto sin diferencias,20UUID verificados y cero restos propios registrados, servicios8082/8083 saludables. PDF10p/20colocaciones; fallo inicial de conteo de recursos conservado, misma exportación verificada sin alterar exit1 histórico.

## Memoria técnica vigente

Fuente: [índice de decisiones](knowledge/README.md), [GOT-001](knowledge/gotchas/GOT-001-errores-livewire-tras-reintento.md), [GOT-002](knowledge/gotchas/GOT-002-limpieza-de-uploads-de-qa.md) y [ADR-003](knowledge/adr/ADR-003-publicacion-confirmada-y-estado-smtp.md).

ADR-001/002/004 aceptadas: broker compartido, stack y FastCGI/origen confiable. GOT-001/002 aceptados tras revisión6: errores visibles conservados al validar campos auxiliares y limpieza con raíz efectiva/atribución exacta/continuidad ante fallos. ADR-003 sigue propuesta para T-10–T-16: publisher/consumer JSON de dominio, confirms, outbox, fencing y ambigüedad SMTP. El queue:work ordinario no acredita ese protocolo; no hay servicios de entrega de dominio implementados.

## Persistencia y recuperación

Fuente: [respaldos y traslado](infraestructura/backup-migracion.md).

Seis volúmenes `emailing-infra_{rabbitmq-data,mysql-data,cronicle-data,cronicle-logs,cronicle-queue,mailpit-data}`. Datos Linux gestionados por Docker Desktop, no carpetas del repositorio. Recetas de respaldo/restauración propuestas, aún no ensayadas. Copia fría requiere parar productores/consumidores de todas las aplicaciones y los servicios; no tar de volúmenes vivos. Definitions de RabbitMQ no incluyen mensajes. Conservar secretos, identidades, imagen Cronicle y APP_KEY de aplicación. No sobrescribir volúmenes operativos durante un ensayo.

## Incidencias conocidas

Fuente: [troubleshooting](infraestructura/troubleshooting.md).

Mailpit web usa 18025; 11025 es SMTP y falla si se abre como HTTP. `localhost` de un contenedor es el propio contenedor. Network external inexistente requiere arrancar infra. Contraseña UI Cronicle puede diferir del valor inicial. Un nombre nuevo de proyecto puede crear volúmenes vacíos. Healthy no valida permisos de aplicación ni entrega a Internet.

## Entregabilidad y riesgo de listas de bloqueo

Fuente: [entregabilidad](infraestructura/entregabilidad.md). Controles previstos, todavía no implementados ni probados en C-05/C-06. Mailpit solo captura pruebas; relay externo, IP real y proveedor pendientes. Propuesta inicial: relay gestionado, no MTA propio. En ese patrón el último salto hacia el MX sale de la IP del relay, no necesariamente de la IP de la aplicación. Ninguna IP dedicada, consulta DNSBL ni autenticación garantiza ausencia de bloqueo o llegada a inbox.

Diseño previsto: dominio/remitente autorizados, SPF/DKIM/DMARC y alineación; TLS y DNS/PTR del relay; consentimiento, bajas, supresiones de quejas/rebotes confirmados, cuotas por cuenta y agregadas por transporte, alertas/pausas y reintentos acotados. SMTP 250 es aceptación del relay. Feedback requiere mecanismo real del proveedor, autenticación y correlación/deduplicación; SMTP genérico puede no ofrecerlo. Mostrar cobertura y datos desconocidos sin inventar tasas 0 %. IP compartida implica reputación de otros emisores; dedicada exige mantenimiento y calentamiento, no mejora universal. No rotar IPs para eludir bloqueos.
