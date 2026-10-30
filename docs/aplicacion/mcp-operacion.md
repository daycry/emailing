# Operar MCP en Laragon y Docker

**Última actualización: 2026-10-30.** T11 está aceptada técnicamente en el
[ledger](../roadmap/2026-10-24-mcp-google/tasks.md), con revisión2 y QA9/9 verdes.

Esta guía prepara el laboratorio con un origen canónico `https://localhost:18443`.
Laragon y Docker se ejecutan por turnos en ese puerto y reutilizan MySQL, RabbitMQ,
Cronicle y Mailpit de la [infraestructura compartida](../infraestructura/arquitectura.md).
El correo permanece en Mailpit/log. Google real y los clientes nativos se acreditan
en T14; una prueba sintética o un JSON de configuración no demuestra esa conexión.

La [matriz de perfiles](../roadmap/2026-10-24-mcp-google/testing/client-matrix.md)
registra el ciclo técnico real: PHP/PHP-CGI 8.5.12 y Nginx 1.28.2 en Windows;
PHP 8.4.27, Nginx 1.28.3 y Compose 5.6.1 en Docker. Ambos perfiles probaron los
protocolos MCP `2025-11-25` y `2026-07-28`, reinicio, rotación, revocación y
restauración de fixtures MCP. La [evidencia de implementación](../roadmap/2026-10-24-mcp-google/testing/fase-5/t11/implementation.md)
conserva ocho pruebas backend/61 aserciones, 135 pruebas Python y los ensayos
separados de perfiles; cada scheduler completó dos pasadas periódicas reales.
El [QA independiente](../roadmap/2026-10-24-mcp-google/testing/fase-5/t11/qa-2026-10-30/report/report.md)
pasó los nueve escenarios originales sin fallos, flaky, omitidos ni interrumpidos,
con limpieza propia admitida. No sustituye la regresión global T12 ni la
validación del servidor de destino de T14; tampoco acredita producción operativa.

## Configuración persistente

Los dos perfiles usan el mismo código, contratos y migraciones. Conserva fuera de
Git `APP_KEY`, las credenciales SQL, configuración Google y claves TLS. No ejecutes
`key:generate` al reiniciar ni al recrear un contenedor. El volumen `app-storage`
conserva almacenamiento privado, sesiones y logs; MySQL conserva usuarios,
identidades, membresías, clientes OAuth, grants, códigos, tokens y revocaciones.
Las claves TLS y el archivo de entorno se montan desde archivos privados del host.
El socket `app-fpm` es comunicación efímera entre PHP y Nginx, no una copia de datos.

| Variable | Valor del laboratorio |
| --- | --- |
| `APP_URL`, `MCP_ISSUER` | `https://localhost:18443` |
| `MCP_RESOURCE` | `https://localhost:18443/mcp` |
| `MCP_ENABLED` | `true` sólo al abrir el recurso |
| `MCP_DYNAMIC_REGISTRATION` | `false`; prerregistrar los clientes |
| `SESSION_SECURE_COOKIE` | `true` |
| `GOOGLE_REDIRECT_URI` | `https://localhost:18443/auth/google/callback`, si Google está configurado |

Un cliente remoto necesita un nombre y certificado accesibles desde su equipo:
su `localhost` no es el del servidor. El perfil incluido sólo escucha en loopback.
Para trasladarlo a un servidor, adapta conjuntamente DNS/certificado, autoridad
Nginx, `APP_URL`, emisor/recurso y callback Google. Los callbacks OAuth de cada
cliente siguen siendo los que éste escucha, con coincidencia exacta en el registro.
No sustituyas el emisor por `Host` ni confíes indiscriminadamente en `Forwarded`.

## Certificados del laboratorio

Guarda los archivos en `storage/app/private/tls/`: `localhost-chain.pem`,
`localhost-key.pem` y, si usas una CA propia, `ca.pem`. El certificado debe incluir
`DNS:localhost` en SAN y su cadena debe ser verificable. Una CA privada se confía
explícitamente en cada cliente de pruebas; el runner acepta `--ca-file`. La prueba
no instala una CA global ni desactiva la verificación de nombre/cadena.

Ejemplo con OpenSSL, desde PowerShell y en una carpeta privada nueva:

```powershell
@'
[req]
prompt=no
distinguished_name=dn
x509_extensions=ca_ext
[dn]
CN=Emailing laboratory CA
[ca_ext]
basicConstraints=critical,CA:TRUE
keyUsage=critical,keyCertSign,cRLSign
subjectKeyIdentifier=hash
authorityKeyIdentifier=keyid:always
'@ | Set-Content -Encoding ASCII ca.conf
openssl req -x509 -newkey rsa:2048 -nodes -days 2 -sha256 -config ca.conf -keyout ca-key.pem -out ca.pem
openssl req -new -newkey rsa:2048 -nodes -subj "/CN=localhost" -keyout localhost-key.pem -out localhost.csr
@'
subjectAltName=DNS:localhost
basicConstraints=critical,CA:FALSE
keyUsage=critical,digitalSignature,keyEncipherment
extendedKeyUsage=serverAuth
subjectKeyIdentifier=hash
authorityKeyIdentifier=keyid:always
'@ | Set-Content -Encoding ASCII extensions.conf
openssl x509 -req -in localhost.csr -CA ca.pem -CAkey ca-key.pem -CAcreateserial -days 2 -sha256 -extfile extensions.conf -out localhost-chain.pem
openssl verify -x509_strict -CAfile ca.pem localhost-chain.pem
```

Son certificados de laboratorio de dos días. Renueva antes de caducar y protege
la clave de la CA. No publiques claves, copias SQL, archivos de entorno ni tokens.

## Docker

Prepara privadamente `storage/app/private/runtime/application.env` con las variables
de Laravel, una `APP_KEY` persistente y las credenciales de esta aplicación. Dentro
de Docker los hosts son `mysql`, `rabbitmq` y `mailpit`; se usa su puerto interno,
no el publicado para Windows. No copies una configuración de pruebas a la base
operativa sin revisar sus nombres y permisos.

```powershell
docker compose -f compose.yaml -f compose.tls.yaml config --quiet
docker compose -f compose.yaml -f compose.tls.yaml build
docker compose -f compose.yaml -f compose.tls.yaml up -d --wait emailing-web
```

El [override HTTPS](../../compose.tls.yaml) sustituye el puerto HTTP publicado por
`127.0.0.1:18443:443`, elimina el `env_file` de los procesos PHP y monta el entorno
como secreto de fichero. El [Nginx TLS](../../docker/nginx-tls.conf) rechaza Host/SNI
ajenos y peticiones en formato de proxy; FastCGI fija HTTPS explícitamente. Su HTTP
interno sólo sirve salud y el trigger autenticado de Cronicle. No se publican SQL,
AMQP ni servicios nuevos desde este Compose.

Tras revisar migraciones y copia del entorno de destino, aplícalas con `migrate
--force`; nunca uses `migrate:fresh` sobre la base operativa. La [guía de
desarrollo](desarrollo.md) distingue `emailing_testing` de esa base. Sólo después
de verificar el arranque abre los workers con el mismo par de archivos Compose.
`emailing-worker` ejecuta `deliveries:work`; `emailing-jobs` ejecuta los trabajos
Laravel. Conserva el mismo proyecto Compose al reiniciar para reutilizar volúmenes.
No uses `down --volumes` en un entorno cuyos datos quieras conservar.

## Laragon

Utiliza PHP/PHP-CGI y Nginx instalados en Laragon con las dependencias del lock y
assets compilados. [La plantilla nativa](../../docker/laragon-nginx-tls.conf.example)
usa la misma autoridad TLS y FastCGI en `127.0.0.1:18112`. Copia la plantilla a una
carpeta privada y sustituye `__PROJECT_ROOT__`, `__NGINX_ROOT__` y
`__PRIVATE_TLS_ROOT__` por rutas absolutas con `/`. Crea `logs/` y `temp/` dentro
del prefijo Nginx; no reemplaces la configuración global de Laragon.

Configura el entorno privado de la aplicación con la tabla anterior y los hosts y
puertos publicados de la infraestructura para Windows. Detén primero el perfil
Docker si ocupa `18443`. Abre PHP-CGI y Nginx con el prefijo privado:

```text
php-cgi -d opcache.cache_id=emailing-lab-tls -b 127.0.0.1:18112
nginx -p <prefijo-privado>/ -c nginx.conf -t
nginx -p <prefijo-privado>/ -c nginx.conf -g "daemon off;"
```

Los procesos permanecen bajo el control del operador. El ensayo automatizado de
T11 utiliza procesos propios y un entorno/almacenamiento privados para aislarse
de los servicios globales de Laragon. No acredita Apache ni otros vhosts.
En Windows usa un `opcache.cache_id` propio de la aplicación y de su configuración
PHP. Procesos del mismo SAPI y usuario con el mismo ID comparten OPcache; el
ensayo detectó un fallo de bootstrap al reutilizar una caché ajena y verificó que
un ID aislado lo evita, manteniendo OPcache habilitado. No es necesario limpiar
la caché global ni reiniciar otras aplicaciones. [Referencia PHP](https://www.php.net/manual/en/opcache.configuration.php#ini.opcache.cache-id).

## Scheduler de la aplicación

`routes/console.php` programa `mcp:prune --limit=1000` cada minuto con
`withoutOverlapping`. Declarar ese calendario no arranca el proceso que lo ejecuta.
En Docker activa `emailing-scheduler`, que comparte imagen, entorno privado,
almacenamiento y caché con la aplicación:

```powershell
docker compose -f compose.yaml -f compose.tls.yaml up -d --wait emailing-scheduler
docker compose -f compose.yaml -f compose.tls.yaml logs --tail 30 emailing-scheduler
```

Docker reinicia ese proceso si termina; `init` recoge descendientes y una parada
graciosa permite que Laravel termine la pasada activa. No se crea otro Cronicle:
el Cronicle central conserva sus triggers de negocio y este proceso ejecuta el
calendario de mantenimiento Laravel. No programes además la misma pasada en
Cronicle o cron. Usa el mismo `CACHE_STORE` y `CACHE_PREFIX` en todos los procesos
de esta aplicación, con un prefijo distinto para las demás aplicaciones.

En Laragon abre otro proceso con el entorno privado de esta aplicación:

```powershell
powershell -NoProfile -File scripts/php.ps1 artisan schedule:work
```

Mantén ese proceso bajo control del operador o del gestor de procesos del entorno.
Tras un reinicio debe volver a arrancar: la cola de trabajos no sustituye al
scheduler. Comprueba que una pasada real de `mcp:prune` termina, además del estado
del proceso. El borrado es finito y conserva las referencias protegidas por SQL.

Una parada forzada puede dejar el mutex de `withoutOverlapping`. Detén el scheduler
y confirma que no quedan pasadas activas antes de recuperar **los locks de esta
aplicación** con `php artisan schedule:clear-cache`. Vuelve a arrancarlo y verifica
la siguiente pasada. No ejecutes `cache:clear` global ni borres locks mientras
otro proceso pueda estar usando el mismo calendario. Detén también este servicio
antes de restaurar SQL; se reabre después de reconciliar la autoridad MCP.

## Registro y comprobación de clientes

Usa [los ejemplos ficticios](mcp-clientes.md), registra cada callback exacto y
conserva privadamente el ID real devuelto. La autorización sigue el navegador,
selección de cuenta y consentimiento existentes; no hay un segundo login en el
runner. Comienza con `contacts.read` y una cuenta de laboratorio propia.

```powershell
python scripts/mcp-profile-evidence.py --profile laragon
python scripts/mcp-profile-evidence.py --profile docker
```

Esas dos invocaciones son **dry-run**: no cargan credenciales ni hacen red. Para
ejecutar el smoke, la configuración privada `storage/app/private/mcp-profile-input/<uuid>.json`
debe contener exclusivamente `schema_version: 1`, `token`, `account_id`, `user_id`,
`grant_public_id`, `contact_id` y `denied_template_id`. El token corresponde a un
grant propio con `contacts.read`; el contacto pertenece a su cuenta y la plantilla
sirve para comprobar una denegación. Los IDs son los de fixtures creadas por el
operador. No incluyas este JSON en Git, mensajes, capturas ni argumentos CLI.

```text
python scripts/mcp-profile-evidence.py --profile laragon --protocol 2025-11-25 --ca-file storage/app/private/tls/ca.pem --inputs storage/app/private/mcp-profile-input/<uuid>.json --execute
python scripts/mcp-profile-evidence.py --profile docker --protocol 2026-07-28 --ca-file storage/app/private/tls/ca.pem --inputs storage/app/private/mcp-profile-input/<uuid>.json --execute
```

Ejecuta ambos protocolos en ambos perfiles. El runner verifica salud, metadatos,
handshake, identidad/cuenta, catálogo permitido, lectura útil y denegación. Emite
hashes del contrato y sus fuentes, junto con referencias y hashes de recibos bajo
`storage/app/private/mcp-profile-evidence/`. No conserva headers ni cuerpos HTTP.
El límite de pared es 180 s y el padre contiene/recoge su árbol de procesos.
`profile_qualified: false` es intencionado: el smoke aislado no prueba reinicio,
restore, Google ni cliente nativo. La aceptación completa se apoya en esos ensayos
separados y en la [matriz](../roadmap/2026-10-24-mcp-google/testing/client-matrix.md).

## Reinicio, rotación y revocación

Conserva un grant activo y otro revocado. Comprueba identidad y lectura útil,
detén/reinicia PHP y web conservando SQL, almacenamiento y clave, y repite la
llamada. El grant activo debe seguir funcionando y el revocado debe recibir 401.
Revoca el activo desde **Conexiones MCP** y verifica también un rechazo real con
su credencial anterior después de otro reinicio; un acuse de revocación no basta.

Para rotar `APP_KEY`, conserva temporalmente la anterior en `APP_PREVIOUS_KEYS`,
reinicia los procesos y verifica los datos cifrados existentes. Laravel cifra con
la nueva clave y puede descifrar con las anteriores. La rotación de clave no
revoca grants ni tokens: esas revocaciones se conservan en SQL. Retirar claves
anteriores exige haber recifrado o retirado los datos que aún las necesitan;
documenta su custodia con la copia correspondiente.

## Backup y restauración

Guarda de forma coherente SQL, `APP_KEY` y claves anteriores necesarias, archivos
privados y configuración del perfil. El SQL incluye identidades Google, usuarios,
membresías, clientes, grants, tokens, códigos, solicitudes, sesiones y auditoría,
además de los datos de negocio. Las claves Google pertenecen a la configuración
privada del operador; no se guardan como tokens de Google en la aplicación.

Primero cierra productores y admisión, pausa/drena entregas y detén todos los
workers y procesos web que usen esa base. El modo mantenimiento por sí solo no
drena un worker. Usa una copia consistente del motor y restaura primero en un
destino aislado. No pruebes un restore contra `emailing`. T11 comprueba copias de
datos de sus fixtures MCP; el simulacro global con volúmenes/broker es T19.

Una copia antigua puede preceder a una revocación y reactivar credenciales. Antes
de volver a abrir el servicio restaurado:

```powershell
$ErrorActionPreference = 'Stop'
php artisan down
if ($LASTEXITCODE -ne 0) { throw 'No se pudo activar mantenimiento; detener la recuperacion.' }
php artisan mcp:invalidate-restored-access
if ($LASTEXITCODE -ne 0) { throw 'Fallo la previsualizacion; mantener el servicio cerrado.' }
php artisan mcp:invalidate-restored-access --apply
if ($LASTEXITCODE -ne 0) { throw 'Fallo la invalidacion; mantener mantenimiento y acceso cerrado.' }
```

El primer comando de invalidación es sólo una previsualización. `--apply` exige
mantenimiento real y funciona con MCP deshabilitado. Una transacción revoca
clientes/grants/tokens, consume códigos y solicitudes pendientes y elimina las
sesiones MCP. Mantiene las filas referenciadas por recibos de correo y auditoría,
las identidades, las cuentas y las llaves API de cuenta. Repetirlo conserva las
fechas de revocación ya registradas. Si falla una escritura, se revierte el conjunto.
Si cualquier paso falla, conserva mantenimiento, tráfico cerrado y workers/scheduler
detenidos; no ejecutes `up`. Después de una invalidación correcta, mantén el acceso
público bloqueado y levanta mantenimiento sólo en el circuito privado de prueba:

```powershell
php artisan up
if ($LASTEXITCODE -ne 0) { throw 'No se pudo preparar la comprobacion privada; no abrir trafico.' }
```

Comprueba401 con las credenciales antiguas desde ese circuito privado. Si el
resultado diverge, vuelve a mantenimiento e investiga con el acceso público aún
cerrado. Sólo tras verificar el rechazo restablece tráfico, scheduler y workers,
registra de nuevo los clientes y vuelve a autorizar. Las comprobaciones locales
de T11 siguen ese orden con la admisión detenida durante restore/invalidation.

Las llaves API de cuenta y los trabajos de entrega requieren su propia
reconciliación/rotación: este comando sólo invalida autoridad MCP. Una copia SQL
antigua tampoco demuestra si un SMTP externo aceptó un mensaje después de la copia.
No reproduzcas automáticamente entregas inciertas. Consulta [entrega y
recuperación](entrega-y-recuperacion.md) y conserva Mailpit/log en este laboratorio.

## Diagnóstico

Comprueba `/up`, cadena/SAN/fecha TLS, autoridad y puertos antes de OAuth. Un 404 de
metadatos puede indicar MCP deshabilitado o emisor/recurso incoherentes; un 401 puede
indicar revocación/caducidad; un rechazo del callback exige comparar su URI completa
con el registro. No soluciones TLS con `verify=false` ni amplíes redirects por comodidad.
El perfil HTTPS desactiva access logs para que las queries de callbacks no se
persistan. Mantén también fuera de diagnósticos códigos, tokens y contenidos de correo.
