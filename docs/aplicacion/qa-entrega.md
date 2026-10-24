# Pruebas de entrega y perfiles de QA

Esta guía describe las herramientas de desarrollo de T-16. Una comprobación
automática no sustituye la revisión independiente, el QA de navegador E2E-08/09
ni el juicio humano M-01. Mailpit y log son los transportes del laboratorio.
Una aceptación SMTP no acredita entrega al buzón ni exactly-once SMTP.

## Grupo de integración y evidencia reproducible

Con la infraestructura preparada y el perfil exclusivo `emailing_testing`, ejecutar
primero el harness de perfiles de runtime de esta guía y seleccionar su imagen PHP
identificada en `ownership.json`. Sustituir `<UTC>` por el directorio que imprimió
el productor; el harness conserva esa imagen tras asentar los servicios:

```powershell
$deliveryProfilePath = "storage/qa-delivery/profiles-<UTC>/ownership.json"
$env:T16_RUNTIME_IMAGE = (Get-Content -Raw $deliveryProfilePath | ConvertFrom-Json).images[0]
python scripts/delivery-evidence.py discovery -- php artisan test --group=delivery --list-tests
python scripts/delivery-evidence.py delivery -- php artisan test --group=delivery
python scripts/delivery-evidence.py inventory -- python -m unittest discover -s tests/support -p "test_runtime_*.py" -v
```

El grupo incluye Activity, Campaign, ConcurrentCampaign, MailTransport, Outbox,
SchedulerTrigger, SuppressionStart, Suppression y WorkerRecovery. El descubrimiento
debe contener identificadores reales; un recuento estático no acredita ejecución.
El segundo comando ejecuta literalmente `php artisan test --group=delivery`.

El productor guarda en `storage/qa-delivery/<ejecución>/` la petición, PID, estados
terminales, stdout/stderr y sus hashes, manifiesto y ZIP de fuentes. Comprueba
altas, bajas y cambios de fuentes durante la ejecución. No registra variables de
entorno y excluye archivos privados de configuración. Los directorios de evidencia
son privados; no se publican sus credenciales, enlaces de baja ni cuerpos de correo.
Los fallos y sus recibos se conservan, aunque una corrección posterior pase.

El guard comprueba tanto la configuración como `DATABASE()` y `CURRENT_USER()`
antes de las migraciones o fixtures. Las pruebas de backend deben terminar antes
de utilizar esa misma base desde el navegador. No se usan para migrar `emailing`.

## Fuentes, build y dependencias visibles

`tests/support/runtime-source-inventory.php` sólo funciona por CLI, sin arrancar
Laravel ni leer env o SQL. Inventaría fuentes, migraciones, vistas, entrada web y
CLI, traducciones `lang/`, manifiestos, assets compilados, bootstrap y todos los archivos de vendor.
Rechaza enlaces, junctions, archivos ilegibles, árboles requeridos vacíos y nombres
de entorno con un diagnóstico que no muestra rutas ni contenido.

El productor esperado `runtime_expected_manifest.py` recibe tres ZIP independientes:
fuente congelada, build aceptado y dependencias del perfil. No admite una observación
como entrada. El comparador valida la forma, rutas, hashes y metadatos, y conserva
los hashes de ambos documentos. Los perfiles Windows con dependencias de desarrollo
y Linux con dependencias de producción tienen artefactos distintos. Coincidir en
composer.lock no basta para identificar todo vendor.

El caso Windows de junction usa un enlace real y retira el enlace sin recorrer su
destino. Los casos Linux de permisos y symlinks requieren un usuario no root:

```powershell
docker run --rm --network none --user www-data --mount "type=bind,source=$((Get-Location).Path)/tests/support,target=/qa,readonly" emailing-app/php:local php -n /qa/runtime-inventory-fixtures.php
```

El servidor Nginx se comprueba por separado: `web_asset_inventory.py` contrasta el
build archivado con archivos del contenedor y con los bytes servidos por HTTP.
Un inventario ejecutado en PHP no acredita los assets servidos por Nginx.

## Runtime de revisión y navegador

En Windows con Laragon, Docker Desktop Linux, PHP 8.4 y Apache disponibles:

```powershell
python scripts/delivery-evidence.py profiles -- python tests/support/delivery_qa_runtime.py
```

El harness construye imágenes nuevas con nombres UUID y extrae build/dependencias
de un contenedor de artefactos detenido. Despliega web, PHP-FPM, consumidor de dominio
y consumidor de trabajos Laravel en un proyecto Compose propio. Los tres procesos
PHP usan la misma imagen identificada, sin montajes parciales de fuentes. El perfil
nativo usa Apache con el módulo PHP y consumidores PHP nativos finitos; no se presenta
un listener PHP CLI como prueba de Apache.

Cada perfil usa cuentas ficticias, colas de dominio/trabajos/fallos y pool propios.
Publica dos eventos para el mismo email en cuentas distintas, observa dos mensajes
listos y cero consumidores, confirma la baja pública con formulario y CSRF, repite y
altera el token. Después inicia el consumidor: A queda suprimida sin intento y B se
acepta una vez en Mailpit. También verifica un trabajo Laravel real. Este recorrido
de integración complementa los casos de concurrencia, crash, backoff, fencing,
confirm perdido, persistencia previa al ACK, fallo SQL y ambigüedad del grupo delivery.

Al terminar retira únicamente procesos, contenedores, volúmenes, topología, mensajes
Mailpit y filas de fixture atribuidos al UUID. Conserva imágenes identificadas y ZIP
para revisar los artefactos ejecutados. La base queda con sus migraciones; no elimina
datos operativos ni restaura un respaldo. Los ensayos completos de restauración y
carga cruzada siguen perteneciendo a T-19/T-20.

Para conservar temporalmente las superficies mientras se ejecuta QA:

```powershell
python scripts/delivery-evidence.py browser -- python tests/support/delivery_qa_runtime.py --hold
```

`ready-for-review.json` señala cuándo están listas. `review-surfaces.json` contiene
las URL concretas, rutas y roles de esa ejecución. Los archivos de acceso privados
indicados allí contienen las credenciales sintéticas; no se copian al informe público.
Crear `stop-owned-runtime` dentro de esa ejecución solicita su limpieza normal.
No se inicia otra suite que refresque `emailing_testing` mientras esté abierto.

## Recorrido de E2E-08/09 y M-01

El [ciclo 2, intento 1](../roadmap/2026-10-21-plataforma-emailing/testing/fase-3/t16-attempt-2/report.md)
acredita diez escenarios a la primera, sin fallos ni flaky; el grupo complementario
delivery tiene 139 pruebas y 2.009 aserciones. La revisión 19 no dejó gaps.
M-01 sigue pendiente de una persona. Los pasos siguientes permiten reproducir el
recorrido en un laboratorio nuevo; no autorizan reutilizar credenciales de informes
anteriores ni modificar la base operativa.

Usar las URL de la ejecución activa y comprobar estos flujos desde la UI ordinaria:

1. Entrar como owner A; distinguir cuenta A de B, donde esa identidad tiene sólo
   lectura. Contrastar reader, restricted y foreign en rutas y acciones directas.
2. Abrir Campañas, revisar un borrador y programar una fecha próxima real. Observar
   el procesamiento antes y después de la fecha sin adelantar timestamps mediante SQL.
   Las campañas llamadas `future` se crean como borradores para hacer este recorrido.
3. Comprobar la baja pública y su repetición, el rechazo del enlace alterado y la
   separación del contacto común entre A y B. La ausencia de feedback de proveedor
   debe mostrarse como limitación y mantener bloqueado el transporte externo.
4. Consultar Actividad y detalle: distinguir entregas lógicas de intentos, aceptación,
   fallo, supresión y ambigüedad. Ensayar los estados reales de recuperación del grupo,
   filtros UTC, muestras pequeñas, origen, errores y reintento. Descargar y revisar
   el CSV y los archivos privados, incluida la protección contra fórmulas y cuentas ajenas.
5. Conservar el componente montado, reducir o revocar permisos y cambiar de cuenta;
   repetir acciones y descargas. `delivery-qa.php` permite revocar al reader atribuido.
6. Verificar el disparo finito autenticado mediante Cronicle conforme a E2E-09, sin
   duplicar el scheduler. Documentar la continuidad de fuentes al reutilizar evidencia.
7. Revisar móvil, escritorio y teclado, con estados vacíos, error y éxito. La persona
   debe distinguir cuenta activa, permisos y estados sin explicación técnica ni
   métricas ficticias. Registrar su valoración M-01; un agente no puede aprobarla.

El plan canónico conserva el detalle completo y la trazabilidad de CA-05/06/08/09/10/
11/14/22/23/25 en [E2E-08, E2E-09 y M-01](../roadmap/2026-10-21-plataforma-emailing/test-plan.md).
