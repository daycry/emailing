# Cómo arrancar, verificar y mantener el laboratorio

**Última actualización: 2026-10-23.** Ejecuta los comandos desde la raíz del repositorio. Seleccionan el proyecto `emailing-infra`. Una parada o recreación afecta a las aplicaciones conectadas; coordina esa ventana con sus responsables.

## Primer arranque

```powershell
powershell -NoProfile -File infra/init.ps1
docker compose --env-file infra/.env -f infra/compose.yaml config --quiet
docker compose --env-file infra/.env -f infra/compose.yaml up -d --build --wait --wait-timeout 240
docker compose --env-file infra/.env -f infra/compose.yaml ps
```

`config --quiet` valida la composición sin imprimir variables expandidas. `up` crea la red y volúmenes si no existen. `--wait` espera los healthchecks, con un límite de 240 segundos para este arranque.

El primer build de Cronicle requiere acceso a GitHub, npm y repositorios de paquetes de Debian. Un fallo de descarga no implica que los volúmenes estén dañados.

## Cómo comprobar funcionamiento

```powershell
powershell -NoProfile -File infra/smoke.ps1
```

Una ejecución normal **genera un marcador nuevo y realiza escrituras nuevas**. No reutiliza los datos de una ejecución previa como sustituto de esas operaciones.

| Servicio | Operación de la prueba | Dato conservado |
|---|---|---|
| RabbitMQ | Mediante Management HTTP, crea cola durable, publica mensaje persistente y consume con requeue. | Cola y mensaje `lab.smoke.<marcador>`. |
| MySQL | Crea `lab_smoke` si falta, inserta marcador y lo consulta. | Fila en `emailing.lab_smoke`. |
| Mailpit | Envía SMTP a 11025 y encuentra el asunto por API HTTP. | Email de prueba. |
| Cronicle | Autentica, crea evento deshabilitado y lo ejecuta manualmente hasta éxito. | Evento e historial del job. |

El evento de smoke está deshabilitado para ejecución programada. La prueba lo lanza explícitamente. No añade una tarea periódica activa.

El script guarda los identificadores en `infra/.runtime/smoke-state.json`. Ese archivo no contiene las contraseñas y no está versionado. Las sesiones HTTP se cierran al terminar.

### Qué no cubre el smoke

Comprueba protocolos del laboratorio y una ejecución de trabajo. La prueba RabbitMQ utiliza HTTP administrativo; no prueba publicación y consumo con el adaptador AMQP de Laravel. Tampoco prueba campañas, permisos de aplicación, carga máxima ni entrega real a Internet.

Los mensajes y trabajos de prueba quedan almacenados. Ejecutarlo repetidamente añade datos; no es un sondeo sin efectos. La retención de Mailpit y Cronicle puede caducar evidencias antiguas.

## Cómo comprobar persistencia tras recrear

Primero ejecuta un smoke normal. Después recrea conservando los volúmenes y verifica los marcadores anteriores:

```powershell
powershell -NoProfile -File infra/smoke.ps1
docker compose --env-file infra/.env -f infra/compose.yaml up -d --force-recreate --wait --wait-timeout 240
powershell -NoProfile -File infra/smoke.ps1 -VerifyPersistence
```

`-VerifyPersistence` exige el archivo de estado previo. Comprueba el mensaje RabbitMQ, la fila SQL, el email y el job anterior. También ejecuta de nuevo el evento Cronicle y actualiza el identificador del job.

Si no existe el estado local, el script se detiene con `No previous smoke markers exist`. Una ejecución normal vuelve a establecer una base de comparación, pero no demuestra la supervivencia de datos anteriores ya perdidos.

La recreación interrumpe conexiones; programa la prueba cuando las aplicaciones puedan tolerarlo. Los clientes deberán reconectar por sí mismos. No borres volúmenes para resolver un fallo de salud.

## Parar y volver a iniciar

```powershell
docker compose --env-file infra/.env -f infra/compose.yaml stop
docker compose --env-file infra/.env -f infra/compose.yaml start
docker compose --env-file infra/.env -f infra/compose.yaml ps
```

`stop` conserva contenedores y volúmenes. `start` reutiliza los contenedores existentes. Para aplicar cambios de Compose o recuperar contenedores que ya no existen, utiliza `up -d`.

`down` elimina contenedores y la red gestionada por el proyecto, pero conserva los volúmenes nombrados si no se solicita su borrado. No es necesario para una parada diaria. Puede encontrar la red en uso por aplicaciones conectadas; coordina antes su retirada.

La opción `-v` de `down` solicita borrar volúmenes. No forma parte de ninguna receta de conservación de datos de este proyecto.

## Consultar logs y salud

```powershell
docker compose --env-file infra/.env -f infra/compose.yaml logs --tail 50 rabbitmq
docker compose --env-file infra/.env -f infra/compose.yaml logs --tail 50 cronicle
docker compose --env-file infra/.env -f infra/compose.yaml logs --tail 50 mysql
docker compose --env-file infra/.env -f infra/compose.yaml logs --tail 50 mailpit
docker compose --env-file infra/.env -f infra/compose.yaml exec -T rabbitmq rabbitmq-diagnostics -q check_running
```

Puedes añadir `--follow` a `logs` para seguir la salida; `Ctrl+C` deja de seguirla sin parar el servicio. Revisa los logs antes de compartirlos: los mensajes de aplicaciones o trabajos pueden contener datos.

Los healthchecks tienen periodos iniciales de 30–60 segundos en servicios con inicialización más lenta. Un estado `starting` durante ese periodo no equivale a un fallo definitivo.

## Actualizar imágenes o reconstruir Cronicle

Registra la versión anterior y prepara un respaldo recuperable antes de modificar almacenamiento persistente. Revisa la compatibilidad de los datos con la versión de destino.

Para reconstruir el código local de Cronicle sin cambiar la etiqueta declarada:

```powershell
docker compose --env-file infra/.env -f infra/compose.yaml build cronicle
docker compose --env-file infra/.env -f infra/compose.yaml up -d --no-deps --wait --wait-timeout 240 cronicle
```

Para obtener las imágenes declaradas de los otros servicios, `docker compose ... pull rabbitmq mysql mailpit` descarga esas etiquetas. `up -d` aplica la imagen disponible; `pull` por sí solo no sustituye el contenedor.

El build de Cronicle resuelve dependencias npm transitivas. Una reconstrucción posterior puede resolver dependencias distintas aunque la release principal conserve el mismo número. La guía de [respaldos y traslado](backup-migracion.md) contempla guardar la imagen ya construida.

Después de un cambio ejecuta `ps`, smoke normal y, si procede, UI. Repetir estas comprobaciones no sustituye ensayar una restauración.

## Cómo distinguir operación de infraestructura y aplicación

Esta página opera `emailing-infra`. El [Compose raíz](../../compose.yaml) pertenece a `emailing-app` y declara cuatro servicios: PHP, Nginx, consumidor de entregas y worker de jobs Laravel. Los dos workers tienen comandos y colas diferentes; consulta [entrega y recuperación](../aplicacion/entrega-y-recuperacion.md). Detener la aplicación conserva sus volúmenes y no detiene el broker compartido.

En este corte, el Compose base de aplicación está detenido. Los perfiles QA 8082/8083 validan fases 1/2; utilizan `emailing_testing`. De `emailing` constan 13 tablas y cinco filas de `lab_smoke`, sin acreditar el estado de migraciones. T-20 inspeccionará el esquema y preparará copia/plan antes de migrar. No abras el Compose base como si la instalación del producto ya estuviera terminada.

La declaración de cuatro servicios está revisada como componente T-13. El QA
histórico de fases 1/2 no acredita el arranque de esta nueva composición; el QA
integral T-16 y despliegue T-20 siguen pendientes. Usa el override de
[desarrollo y pruebas](../aplicacion/desarrollo.md), que configura la base y las
colas de QA en los tres procesos PHP; cambiar solo la cola de jobs no aísla las
entregas de dominio.

Consulta [desarrollo y pruebas](../aplicacion/desarrollo.md) para preparar sus directorios, secretos y perfiles. El código vive dentro de las imágenes: después de editar PHP hay que reconstruir y recrear los servicios de aplicación afectados. Las dependencias y assets sin cambios pueden reutilizar caché de build.

`serve-testing.ps1` genera un único `.env.qa`; no ejecutes dos instancias simultáneas. Repetir las suites puede limpiar `emailing_testing`, por lo que debe coordinarse con quienes estén usando el panel QA. El HTTP QA mantiene `APP_ENV=local`, distinto del kernel de fixtures `testing`.

Las pruebas por cuenta ya ofrecen log privado y Mailpit. SMTP del host entra por 11025 hacia 1025 del contenedor; UI del host 18025 hacia 8025. Un mensaje capturado sólo acredita recepción del laboratorio. Revisa [plantillas y transporte](../aplicacion/plantillas-y-transporte.md) para sus estados y descargas privadas.

## Evidencia de infraestructura y aplicación

El [informe de QA](../roadmap/2026-10-21-infra-laboratorio/testing/report.md) registra pruebas de protocolos, dos ejecuciones normales con marcadores diferentes, recreación y verificación de persistencia. También registra conexión desde un contenedor externo a la red y tres E2E de UI sin fallos ni flaky.

Las rutas de la configuración Playwright usan la caché local de aquella máquina. Para repetir el QA completo en otro host, revisa [su configuración](../roadmap/2026-10-21-infra-laboratorio/testing/playwright.config.mjs) y el [plan de pruebas](../roadmap/2026-10-21-infra-laboratorio/test-plan.md).

El [QA de aplicación](../roadmap/2026-10-21-plataforma-emailing/testing/report.md) añade cuatro escenarios en cada perfil, 8/8 aprobados sin flaky, skipped ni interrupted. M-01 humana continúa pendiente. No extiende el resultado a campañas ni a envío externo.

El [QA3 de fase 2](../roadmap/2026-10-21-plataforma-emailing/testing/fase-2/report-attempt-3.md) acredita 20/20, diez casos por perfil, con un intento por caso y ambos gates exit 0. Su limpieza verificó veinte UUID y temporales/mensajes propios por referencias exactas. Conservó históricos no atribuibles y mensajes ajenos, sin borrados globales ni recreación de infraestructura. Los perfiles siguieron HTTP 200 y `lab_smoke` mantuvo cinco filas. Este resultado no valida restauración ni entrega externa.
