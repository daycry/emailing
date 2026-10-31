# Cómo localizar los fallos habituales

**Última actualización: 2026-10-31.** Empieza por `docker compose --env-file infra/.env -f infra/compose.yaml ps` y consulta logs del servicio afectado. Conserva los volúmenes. El [manual de diagnóstico](../manual/guia-completa.md#diagnóstico-y-preguntas-frecuentes) añade errores de instalación, permisos, CSV, campañas, REST, MCP y proveedores.

## Mailpit no carga al abrir el puerto de Docker

**La web está en <http://127.0.0.1:18025>.** El mapeo `11025:1025` corresponde a SMTP. Un navegador enviado a 11025 habla HTTP contra un receptor SMTP; puede generar errores de comandos desconocidos.

| Necesidad | Dirección correcta |
|---|---|
| Consultar mensajes en navegador | `http://127.0.0.1:18025` |
| Enviar SMTP desde Windows | `127.0.0.1:11025` |
| Enviar SMTP desde una aplicación Docker | `mailpit:1025` |

La incidencia observada en este laboratorio fue confundir esos dos protocolos. Verifica la URL antes de cambiar Compose o credenciales.

## Un puerto ya está ocupado

El laboratorio utiliza MySQL 3308 y Mailpit 11025/18025 para evitar servicios existentes. No detengas otro MySQL o Mailpit sin identificar antes a qué aplicación pertenece.

Consulta los listeners de Windows:

```powershell
Get-NetTCPConnection -State Listen |
    Where-Object { $_.LocalPort -in @(5672, 15672, 3012, 3308, 11025, 18025) } |
    Select-Object LocalAddress, LocalPort, OwningProcess
docker ps --format 'table {{.Names}}\t{{.Ports}}'
```

Si cambias un puerto de host, actualiza clientes y el smoke. Consulta [configuración](configuracion.md) para distinguir puertos de host y contenedor.

## Un contenedor no alcanza MySQL o RabbitMQ

Comprueba que pertenece a `emailing-lab` y que utiliza `mysql:3306` o `rabbitmq:5672`. Un contenedor que use `127.0.0.1:3308` intentará conectar consigo mismo.

```powershell
docker network inspect --format '{{range .Containers}}{{.Name}} {{.IPv4Address}}{{println}}{{end}}' emailing-lab
```

Una conexión TCP abierta no confirma acceso a un vhost ni permisos SQL. Valida después usuario, contraseña, base o vhost de esa aplicación. No sustituyas su credencial por root o por el administrador del broker para dejarla funcionando permanentemente.

Si Compose indica que la red externa no existe, arranca primero infraestructura. El nombre debe ser exactamente `emailing-lab`.

## Una contraseña nueva en el archivo no funciona

Con datos ya inicializados, cambiar `infra/.env` no cambia automáticamente cuentas existentes. Comprueba qué credencial tiene actualmente el servicio y realiza la rotación mediante su interfaz administrativa.

En Cronicle, una contraseña modificada desde la UI prevalece sobre la inicialización. Conserva el marcador `.lab-initialized` y sincroniza `CRONICLE_ADMIN_PASSWORD` si quieres que el smoke use la contraseña actual.

No borres volúmenes ni el marcador para recuperar acceso como primer recurso. Esa operación puede eliminar usuarios y datos o reaplicar la inicialización sobre un estado que pretendías conservar.

## Cronicle responde HTTP pero está unhealthy

El healthcheck exige que la configuración identifique como master al hostname actual. Un HTTP 200 sin master correcto no es suficiente.

```powershell
docker compose --env-file infra/.env -f infra/compose.yaml logs --tail 100 cronicle
docker compose --env-file infra/.env -f infra/compose.yaml exec -T cronicle node /opt/cronicle/lab-health.js
```

Comprueba hostname `lab-cronicle`, almacenamiento conservado y bootstrap sin errores. La configuración de laboratorio fija `master: true`; no utiliza una espera de elección de failover.

Si aparece un error de permisos de storage tras restaurar, revisa propietarios Linux conservados por el archivo. No apliques permisos abiertos a todos los datos como remedio general.

## Un job Cronicle no termina

Abre <http://127.0.0.1:3012>, entra en el detalle del job y revisa código de salida, log, timeout y categoría. Los jobs shell se ejecutan dentro del contenedor Cronicle, no dentro del contenedor de la aplicación.

Para la integración HTTP de Emailing, comprueba DNS único, puerto interno,
autenticación y timeout. El trigger ejecuta acciones finitas; los envíos
pertenecen a sus workers. Consulta [programación](../aplicacion/programacion-con-cronicle.md).

El smoke espera hasta 90 segundos por su job y falla si no finaliza con código 0. `max_jobs=10` limita jobs Cronicle simultáneos; no equivale al número de workers RabbitMQ.

## El smoke falla después de una recreación

| Síntoma | Comprobación |
|---|---|
| `No previous smoke markers exist` | Falta `infra/.runtime/smoke-state.json`; ejecuta antes una prueba normal para establecer marcadores. |
| Falla autenticación | Credencial actual del servicio frente al valor de `infra/.env`. |
| Falta email anterior | Retención de Mailpit: máximo de 1000 mensajes, eliminación manual o volumen distinto. |
| Falta job anterior | Retención de Cronicle: 30 días, eliminación manual o volumen distinto. |
| Cola o fila inexistente | Nombre de proyecto/volumen, conexión al servicio correcto y acciones de limpieza previas. |
| Job no finaliza | UI y logs de Cronicle; salud del master y capacidad para ejecutar el plugin. |

Una prueba normal exitosa crea datos nuevos, pero no demuestra que se conservaron los anteriores. Para esa comprobación existe `-VerifyPersistence`.

## Los datos parecen haber desaparecido

Consulta los seis volúmenes y el nombre de proyecto:

```powershell
docker volume ls --filter label=com.docker.compose.project=emailing-infra
docker compose --env-file infra/.env -f infra/compose.yaml ps --all
```

Cambiar el proyecto Compose puede crear otros volúmenes vacíos. Un volumen antiguo puede seguir existiendo aunque el contenedor nuevo no lo monte. Identifica los montajes antes de inicializar o restaurar datos.

Los archivos persisten en Docker Desktop, no dentro del repositorio. Quitar una carpeta local de código no equivale a respaldar los volúmenes. Consulta [respaldos y traslado](backup-migracion.md).

## El contenedor está running pero la aplicación falla

`running` indica proceso activo. `healthy` depende del healthcheck; aun así, una aplicación puede tener permisos, DNS o configuración incorrectos.

En RabbitMQ observa conexiones, canales, consumidores y colas de su vhost. En MySQL comprueba acceso con el usuario de aplicación. En SMTP verifica la dirección y el protocolo, y busca el marcador en Mailpit.

No hay medición de capacidad máxima que permita atribuir un fallo a falta de recursos sin observarlo. Revisa memoria, CPU, disco y backlog antes de aumentar límites.

## El perfil de aplicación no coincide con el puerto esperado

`.env.example` indica 8080; el Compose base fija 8081. QA nativo utiliza 8082 y Docker QA, 8083 con override privado. Editar únicamente `APP_URL` en `.env` no cambia el valor explícito del Compose. Revisa [perfiles de desarrollo](../aplicacion/desarrollo.md).

Si un cambio PHP no aparece en Docker, comprueba que reconstruiste la imagen y recreaste el servicio. El código no se monta desde el host. No borres volúmenes para actualizar fuentes.

## Las pruebas rechazan la base o el panel pierde datos

El guard de pruebas exige base y usuario `emailing_testing`, MySQL y ausencia de `DB_URL`. Corrige esa configuración; no retires el guard. `emailing` conserva cinco filas de `lab_smoke` y constan 13 tablas; no se conoce su estado de migraciones. T-20 requiere inspección y copia/plan antes de migrar.

Las suites con `RefreshDatabase` pueden limpiar datos del panel QA, porque comparten la base de pruebas. Coordina su ejecución. No uses ese panel para datos operativos.

## Una sesión antigua conserva una cuenta retirada

Los permisos se consultan de nuevo en la siguiente acción. Si recibes un 403 al volver a la cuenta retirada, abre **Mis cuentas** y selecciona otra membresía disponible. Tras un reset de contraseña vuelve a iniciar sesión. Véase [guía de usuario](../aplicacion/cuentas-y-equipo.md).

## El correo de acceso no aparece fuera del laboratorio

Invitaciones y recuperación se capturan en Mailpit y no salen a Internet. Comprueba la UI 18025 y SMTP 11025 desde Windows o `mailpit:1025` desde Docker. El transporte de pruebas por cuenta ya permite log privado o Mailpit. En log no habrá captura SMTP; busca el estado `logged` y el archivo `.eml` privado. En Mailpit, `smtp_accepted` significa aceptación local, nunca entrega al destinatario. Guardar configuración SMTP externa no habilita un relay.

## El CSV falla o conserva avisos al buscar listas

Comprueba UTF-8, extensión `.csv`, separador coma, encabezados exactos, comillas y límite 5 MiB/10.000 registros. Un error estructural rechaza el archivo completo; una fila inválida aparece en el informe. Duplicados se omiten sin actualizar nombre, consentimiento ni listas.

La búsqueda de listas conserva el aviso de archivo para que puedas corregirlo. Tras una importación válida desaparece el error anterior. No vacíes todos los errores en `render()`; [GOT-001](../knowledge/gotchas/GOT-001-errores-livewire-tras-reintento.md) explica esa regresión. Revisa [contactos e importación](../aplicacion/contactos-e-importacion.md) para formatos y límites PHP/Nginx.

Si una subida temporal caducó o pertenece a otra sesión/cuenta, vuelve a seleccionar el archivo con acceso vigente. No copies handles entre cuentas ni borres directorios completos para limpiar QA. La [limpieza atribuida](../knowledge/gotchas/GOT-002-limpieza-de-uploads-de-qa.md) conserva históricos no comprobados.

## La preview no muestra imágenes o la prueba usa otro contenido

La preview elimina imágenes, destinos de enlaces y recursos activos por diseño. Sólo muestra una versión aislada del formulario. **Crear prueba** utiliza la plantilla guardada; guarda los cambios antes de probarlos. Consulta [plantillas y transporte](../aplicacion/plantillas-y-transporte.md).

`smtp_unconfirmed` conserva una aceptación incierta; no reenvíes automáticamente. Un HTTP 200 aislado tampoco demuestra éxito: comprueba el aviso de la operación y su estado persistido. La observación nativa intermedia de QA2 no tiene causa demostrada y no se presenta como un fallo explicado.

## Dónde consultar las evidencias

El [informe QA](../roadmap/2026-10-21-infra-laboratorio/testing/report.md) contiene resultados y capturas de RabbitMQ, Cronicle y Mailpit. El [ledger de infraestructura](../roadmap/2026-10-21-infra-laboratorio/tasks.md) registra implementación, revisiones y verificación de persistencia.

Los valores reales de contraseñas, sesiones y secretos no forman parte de esta documentación. Comparte únicamente las salidas necesarias para diagnosticar el fallo.
