# Cómo respaldar y trasladar el laboratorio

**Última actualización: 2026-10-23.** Las recetas de copia y restauración de esta página están propuestas a partir de la configuración real. **No se han ejecutado ni validado mediante un ensayo de restauración.**

El [QA existente](../roadmap/2026-10-21-infra-laboratorio/testing/report.md) comprobó persistencia tras recrear contenedores conservando sus volúmenes. Eso no demuestra que un respaldo pueda recuperarse en otra máquina.

## Qué necesitas conservar

| Elemento | Por qué se necesita |
|---|---|
| Código de `infra/` y revisión Git | Reconstruye configuración, bootstrap y healthchecks. |
| `infra/.env` protegido fuera de Git | Conserva credenciales y, especialmente, la clave de Cronicle. |
| Los seis volúmenes | Contienen datos de cada servicio, historial y mensajes. |
| Imágenes construidas y versiones | Evita depender de resolver otra vez las dependencias de Cronicle. |
| Nombres de proyecto, red y hostnames | Conserva identidad y localización del almacenamiento. |
| Configuración y secretos de cada aplicación | Permite volver a autenticar y descifrar sus propios datos. |
| Registro de fecha, versiones y resultado del ensayo | Permite identificar qué copia se recuperó. |

La aplicación Laravel ya tiene `APP_KEY` local. Consérvala junto a sus secretos: una clave nueva no descifra los datos protegidos con la anterior. Sus volúmenes `app-storage` y `app-fpm` pertenecen al Compose de aplicación. El primero contiene almacenamiento persistente; el segundo contiene un socket de ejecución que se regenera, no datos de negocio.

El Compose base de aplicación está detenido, con volúmenes conservados. De la base operativa `emailing` constan 13 tablas y cinco filas de `lab_smoke`; el estado de migraciones no está acreditado. Sólo los perfiles QA están preparados para esta fase. T-20 mantiene pendiente inspección de esquema, copia y plan antes de migrar, portabilidad y ensayo de restauración; no importes datos de QA sobre `emailing`.

La fase 2 añade originales CSV, temporales y archivos `.eml` privados en `app-storage`, además de credenciales SMTP cifradas en SQL. Conserva almacenamiento y `APP_KEY` coherentes con la copia de datos; una base recuperada sin su clave no permite descifrar esos secretos. La limpieza atribuida de fixtures QA no sustituye una política de respaldo o retención operativa.

`infra/.runtime/smoke-state.json` es opcional: ayuda a verificar marcadores de laboratorio, pero no contiene los datos de los servicios. Tampoco sustituye un respaldo.

## Dónde están los datos

| Volumen real | Montaje del contenedor |
|---|---|
| `emailing-infra_rabbitmq-data` | RabbitMQ: `/var/lib/rabbitmq` |
| `emailing-infra_mysql-data` | MySQL: `/var/lib/mysql` |
| `emailing-infra_cronicle-data` | Cronicle: `/opt/cronicle/data` |
| `emailing-infra_cronicle-logs` | Cronicle: `/opt/cronicle/logs` |
| `emailing-infra_cronicle-queue` | Cronicle: `/opt/cronicle/queue` |
| `emailing-infra_mailpit-data` | Mailpit: `/data` |

Consulta metadatos, sin imprimir variables de entorno:

```powershell
docker volume ls --filter label=com.docker.compose.project=emailing-infra
docker volume inspect --format '{{.Name}} {{.Mountpoint}}' emailing-infra_mysql-data
docker network inspect --format '{{.Name}} {{.Driver}} {{.Scope}}' emailing-lab
```

El montaje vive en el entorno Linux de Docker Desktop, habitualmente bajo `/var/lib/docker/volumes/`. No es una carpeta del repositorio accesible directamente como `C:\...`. Las recetas utilizan Docker para copiarlo.

## Elige copia lógica o copia en frío

Un dump lógico de MySQL permite recuperar SQL. No contiene las colas de RabbitMQ, la base de Cronicle ni mensajes de Mailpit.

Una exportación de definitions de RabbitMQ conserva topología y configuración, pero **no contiene los mensajes encolados**. Puede incluir credenciales derivadas y debe tratarse como información sensible. La [guía oficial de respaldo de RabbitMQ](https://www.rabbitmq.com/docs/backup) distingue ambos tipos de datos y exige conservar exactamente el nombre del nodo para recuperar datos en disco.

No copies con `tar` el volumen de un broker, MySQL o SQLite mientras escriben y lo presentes como copia consistente. La receta siguiente usa una parada coordinada de todos los productores y consumidores, incluidos los de otras aplicaciones conectadas. El patrón de contenedor auxiliar para copiar volúmenes se apoya en la [guía de Docker](https://docs.docker.com/engine/storage/volumes/#back-up-restore-or-migrate-data-volumes); su aplicación a este conjunto de servicios necesita el ensayo indicado.

## Receta propuesta: copia en frío de los seis volúmenes

### 1. Prepara una ventana de mantenimiento

Identifica todas las aplicaciones que utilizan esta infraestructura. Detén las nuevas entradas y tareas programadas. Detén Cronicle y permite que los workers finalicen sus trabajos antes de parar los procesos de aplicación.

No basta con parar la web de emailing si otros servicios siguen publicando, consumiendo o escribiendo. Los efectos SMTP ya iniciados requieren reconciliación de aplicación; una copia de volumen no resuelve un resultado externo ambiguo.

Cuando no queden trabajos en ejecución ni clientes escribiendo, detén la infraestructura:

```powershell
docker compose --env-file infra/.env -f infra/compose.yaml stop
docker compose --env-file infra/.env -f infra/compose.yaml ps --all
```

Confirma que los cuatro servicios están parados. Conserva los contenedores para poder consultar su configuración si hace falta.

### 2. Crea una carpeta externa al repositorio

El siguiente ejemplo usa una carpeta nueva por copia. Revisa la ubicación y protege su acceso, porque incluirá secretos y datos.

```powershell
$backupDir = 'C:\lab-backups\emailing-infra\2026-10-21-cold'
if (Test-Path -LiteralPath $backupDir) { throw 'Elige una carpeta nueva para esta copia.' }
New-Item -ItemType Directory -Path $backupDir | Out-Null
$backupDir = (Resolve-Path -LiteralPath $backupDir).Path
Copy-Item -LiteralPath infra/.env -Destination (Join-Path $backupDir 'infra.env')
Copy-Item -LiteralPath infra/compose.yaml -Destination (Join-Path $backupDir 'compose.yaml')
```

No subas esa carpeta a Git. Almacena una copia protegida fuera del host para cubrir pérdida de disco. El ejemplo no incorpora cifrado automático.

### 3. Archiva los volúmenes parados

Esta receta utiliza un contenedor auxiliar `alpine:3.21`. Su disponibilidad y funcionamiento en el host de destino todavía deben verificarse. Monta cada origen como solo lectura.

```powershell
$volumeNames = @(
    'emailing-infra_rabbitmq-data',
    'emailing-infra_mysql-data',
    'emailing-infra_cronicle-data',
    'emailing-infra_cronicle-logs',
    'emailing-infra_cronicle-queue',
    'emailing-infra_mailpit-data'
)
foreach ($volumeName in $volumeNames) {
    docker volume inspect $volumeName | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "No existe el volumen $volumeName" }
    docker run --rm `
        --mount "type=volume,source=$volumeName,target=/source,readonly" `
        --mount "type=bind,source=$backupDir,target=/backup" `
        alpine:3.21 tar -czpf "/backup/$volumeName.tar.gz" -C /source .
    if ($LASTEXITCODE -ne 0) { throw "Error copiando $volumeName" }
}
Get-ChildItem -LiteralPath $backupDir -Filter '*.tar.gz' | Get-FileHash -Algorithm SHA256
```

`docker volume inspect` evita crear por error un volumen de origen inexistente con nombre equivocado. El archivo `tar` incluye permisos y propietarios Linux; no extraigas estos datos con el explorador de Windows para volver a copiarlos.

Guarda los hashes y un registro de la revisión Git, fecha e imágenes. Esta operación respalda todos los datos de esos volúmenes, incluidas otras bases que pudieran añadirse a la instancia MySQL.

### 4. Conserva las imágenes si necesitas reproducir el mismo entorno

```powershell
docker image save --output (Join-Path $backupDir 'infra-images.tar') `
    emailing-lab/cronicle:0.9.137 `
    rabbitmq:4.4.0-management `
    mysql:8.4.11 `
    axllent/mailpit:v1.27.4
```

Guardar la imagen de Cronicle conserva el resultado de su build. Guardar solo el Dockerfile no fija las dependencias npm transitivas resueltas durante aquella construcción. El archivo de imágenes **no contiene los datos de los volúmenes**, que deben copiarse por separado, como explica la [guía de recuperación de Docker Desktop](https://docs.docker.com/desktop/settings-and-maintenance/backup-and-restore/).

### 5. Reanuda y registra el resultado

Arranca los servicios de infraestructura y verifica salud. Reanuda aplicaciones y calendarios según el orden de dependencias. Evita abrir productores antes de que los consumidores y bases estén preparados.

```powershell
docker compose --env-file infra/.env -f infra/compose.yaml up -d --wait --wait-timeout 240
docker compose --env-file infra/.env -f infra/compose.yaml ps
```

La verificación de archivos o hashes detecta problemas de copia, pero no prueba una recuperación funcional. Completa el ensayo siguiente antes de considerar este procedimiento validado.

## Receta propuesta: recuperación en un host limpio

Usa otro host Docker o un entorno aislado sin volúmenes de ese nombre. No pruebes la restauración sobre el almacenamiento operativo.

1. Recupera la misma revisión del repositorio y las imágenes. Si guardaste el archivo, utiliza `docker image load --input <ruta-del-archivo>`.
2. Restaura el archivo secreto como `infra/.env`. No ejecutes una regeneración que sustituya la clave de Cronicle.
3. Comprueba que los seis nombres de volumen no existen en el destino. Si existen, detén el procedimiento y decide cómo conservarlos antes de continuar.
4. Crea los volúmenes vacíos y extrae cada archivo correspondiente, como muestra el fragmento siguiente.
5. Arranca primero RabbitMQ, MySQL y Mailpit. Comprueba salud, usuarios y datos antes de conectar aplicaciones.
6. Revisa qué calendarios de Cronicle estaban habilitados. Arráncalo dentro de la ventana de mantenimiento, con endpoints de aplicaciones todavía controlados para evitar ejecuciones imprevistas.
7. Comprueba los datos históricos, mensajes y permisos de aplicación. Después habilita consumidores, productores y calendarios en el orden acordado.

Ejemplo de extracción para **un volumen nuevo y vacío** en ese host aislado:

```powershell
$backupDir = (Resolve-Path -LiteralPath 'C:\lab-backups\emailing-infra\2026-10-21-cold').Path
$volumeName = 'emailing-infra_mysql-data'
docker volume inspect $volumeName 2>$null | Out-Null
if ($LASTEXITCODE -eq 0) { throw 'El volumen ya existe; no se sobrescribe.' }
docker volume create $volumeName
if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear el volumen.' }
docker run --rm `
    --mount "type=volume,source=$volumeName,target=/target" `
    --mount "type=bind,source=$backupDir,target=/backup,readonly" `
    alpine:3.21 tar -xzpf "/backup/$volumeName.tar.gz" -C /target
if ($LASTEXITCODE -ne 0) { throw 'Extraccion incompleta; no iniciar el servicio.' }
```

Repite únicamente con los seis nombres y sus archivos verificados. Conserva proyecto `emailing-infra`, hostnames `lab-rabbitmq` y `lab-cronicle`, y las mismas versiones para el primer ensayo. La identidad del nodo RabbitMQ debe seguir siendo exactamente `rabbit@lab-rabbitmq`; una copia de sus datos en disco no permite renombrarlo libremente.

El marcador `.lab-initialized` pertenece al volumen de datos Cronicle y debe recuperarse con él. La contraseña recuperada será la almacenada en la copia; puede diferir de la contraseña inicial si se cambió en la UI.

Una prueba satisfactoria debe verificar datos previos, no solo crear datos nuevos. `-VerifyPersistence` puede ayudar si conservaste su estado y los marcadores no caducaron. Añade un smoke normal para confirmar escrituras nuevas después de comprobar los datos recuperados.

## Receta propuesta: dump lógico de la base emailing

El ejemplo genera el archivo dentro del contenedor y lo copia con Docker. Evita el redireccionamiento binario desde PowerShell y no imprime contraseñas.

```powershell
docker compose --env-file infra/.env -f infra/compose.yaml exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --single-transaction --routines --events --triggers --databases emailing > /tmp/emailing-backup.sql'
if ($LASTEXITCODE -ne 0) { throw 'Fallo el dump; no usar como copia valida.' }
docker compose --env-file infra/.env -f infra/compose.yaml cp mysql:/tmp/emailing-backup.sql (Join-Path $backupDir 'emailing.sql')
```

La consistencia de `--single-transaction` presupone tablas transaccionales y ausencia de cambios DDL concurrentes. Este dump solo incluye la base `emailing`; no incluye usuarios globales ni otras bases. El archivo SQL contiene datos sensibles. El [manual de respaldo y recuperación de MySQL 8.4](https://dev.mysql.com/doc/refman/8.4/en/backup-and-recovery.html) desarrolla las alternativas lógicas y físicas.

La importación necesita una base de destino elegida y permisos adecuados. Debe ensayarse antes de establecer esta vía como recuperación oficial. No ejecutes una importación sobre la base operativa para comprobar que el archivo existe.

## Qué cambia al pasar a un servidor

Instala Docker/Compose compatibles y traslada datos mediante un respaldo ensayado. Ajusta recursos, disco y puertos según el destino; conserva los puertos internos entre contenedores.

La configuración actual publica las UIs solo en localhost. Una administración remota requiere un acceso deliberado. Cronicle fija su URL base en el bootstrap; revísala si cambia el modo de acceso.

Un traslado no añade alta disponibilidad. Conserva respaldos externos y registra cuánto tarda una recuperación real antes de fijar objetivos de tiempo de recuperación.
