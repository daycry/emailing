# Cómo configurar puertos, credenciales y persistencia

**Última actualización: 2026-10-21.** Fuentes: [compose.yaml](../../infra/compose.yaml), [.env.example](../../infra/.env.example), [init.ps1](../../infra/init.ps1) y [bootstrap Cronicle](../../infra/cronicle/bootstrap.js).

## Qué contiene cada archivo

| Archivo | Función | Versionado |
|---|---|---|
| `infra/compose.yaml` | Imágenes, servicios, red, volúmenes, puertos y límites. | Sí. |
| `infra/.env.example` | Nombres de variables y marcadores, sin secretos reales. | Sí. |
| `infra/.env` | Credenciales reales y clave de Cronicle. | Ignorado por Git. |
| `infra/init.ps1` | Generación inicial de secretos. | Sí. |
| `infra/.runtime/smoke-state.json` | Identificadores de la última prueba. | Ignorado por Git. |

El `.env` permite mantener secretos fuera de YAML y de Git. No cifra las credenciales: quien tenga acceso al archivo o administración de Docker puede acceder a ellas.

Los comandos de estas guías pasan `--env-file infra/.env` explícitamente. Así no dependen de un futuro `.env` de aplicación en la raíz.

## Cómo se generan los secretos

```powershell
powershell -NoProfile -File infra/init.ps1
```

El script genera 32 bytes aleatorios por secreto mediante `RandomNumberGenerator` y los representa como 64 caracteres hexadecimales. Escribe UTF-8 sin BOM. Si el archivo ya existe, termina conservándolo completo.

| Variable | Consumidor | Uso |
|---|---|---|
| `RABBITMQ_ADMIN_USER` | RabbitMQ | Usuario administrativo inicial; valor propuesto `labadmin`. |
| `RABBITMQ_ADMIN_PASSWORD` | RabbitMQ | Contraseña administrativa inicial. |
| `MYSQL_ROOT_PASSWORD` | MySQL | Contraseña inicial del administrador SQL. |
| `MYSQL_EMAILING_PASSWORD` | MySQL | Contraseña inicial del usuario `emailing`. |
| `CRONICLE_SECRET_KEY` | Cronicle | Clave persistente del servicio. |
| `CRONICLE_ADMIN_PASSWORD` | Cronicle | Contraseña inicial de `admin`. |

Consulta el archivo con tu editor local para recuperar credenciales. No se reproducen aquí sus valores. La salida completa de `docker compose config` puede expandirlos; para validar usa `config --quiet`.

## Por qué cambiar el archivo no cambia la cuenta

Las variables iniciales de RabbitMQ y MySQL actúan sobre almacenamiento nuevo. Con volúmenes ya inicializados, editar el archivo no actualiza automáticamente los usuarios existentes.

Cronicle protege los cambios posteriores mediante `data/.lab-initialized`. Cuando el marcador existe, conserva el usuario almacenado y no reaplica `CRONICLE_ADMIN_PASSWORD`.

Para rotar una contraseña, cambia la cuenta mediante la UI o herramienta administrativa del servicio. Actualiza después los clientes y el archivo de configuración correspondiente. Comprueba una conexión nueva antes de retirar la credencial anterior.

`smoke.ps1` inicia sesión con los valores de `infra/.env`. Si cambias `admin` desde Cronicle, actualiza también `CRONICLE_ADMIN_PASSWORD` en ese archivo para que las pruebas puedan autenticar. Esto sincroniza el cliente de pruebas; no provoca el cambio de servidor.

Mantén `CRONICLE_SECRET_KEY` con los datos de Cronicle durante una recreación o traslado. No la regeneres como si fuera una contraseña de usuario.

## Qué dirección corresponde a cada cliente

| Servicio | Host Windows/Laragon | Contenedor en `emailing-lab` |
|---|---|---|
| RabbitMQ | `127.0.0.1:5672` | `rabbitmq:5672` |
| RabbitMQ Management | `127.0.0.1:15672` | `rabbitmq:15672` |
| Cronicle HTTP | `127.0.0.1:3012` | `cronicle:3012` |
| MySQL | `127.0.0.1:3308` | `mysql:3306` |
| Mailpit SMTP | `127.0.0.1:11025` | `mailpit:1025` |
| Mailpit HTTP | `127.0.0.1:18025` | `mailpit:8025` |

`localhost` dentro de un contenedor apunta a ese contenedor. No apunta al host ni al MySQL de infraestructura. Los DNS `mysql`, `rabbitmq`, `cronicle` y `mailpit` requieren estar conectado a la red compartida.

Todos los puertos del host están ligados a `127.0.0.1`. Para administrar desde otro equipo, planifica un túnel o un acceso autenticado; la configuración actual no publica esas interfaces en la LAN.

## Cómo cambiar un puerto sin perder datos

Cambia únicamente la parte de host en el mapeo de `infra/compose.yaml`. Por ejemplo, para cambiar MySQL de 3308 a 3310, conserva el destino interno `3306` y el volumen.

Actualiza los clientes Windows y los scripts que utilicen el puerto anterior. `smoke.ps1` contiene URLs y el puerto SMTP del laboratorio, por lo que cambiar puertos exige adaptar y volver a validar ese script. Los clientes Docker continúan usando el puerto interno.

Cronicle además fija `base_app_url` en su bootstrap. Cambiar su publicación HTTP requiere revisar esa URL y reconstruir/recrear el servicio.

## Qué cambios necesitan reconstrucción

| Cambio | Acción necesaria |
|---|---|
| Puertos, límites o variables de Compose | Recrear el servicio con `up -d`; un simple `restart` no aplica toda la configuración. |
| `infra/cronicle/Dockerfile`, `bootstrap.js` o `health.js` | Construir imagen y recrear Cronicle. |
| Credenciales de cuentas ya creadas | Rotar en el servicio y sincronizar clientes. |
| Etiqueta de imagen | Respaldar, revisar compatibilidad y recrear con la nueva imagen. |
| Nombre de proyecto, hostname o volumen | Tratar como cambio de identidad/almacenamiento, no como ajuste cosmético. |

La receta de [operación](operacion.md) conserva la identidad y los volúmenes. La de [traslado](backup-migracion.md) explica qué registrar antes de cambiar de host.
