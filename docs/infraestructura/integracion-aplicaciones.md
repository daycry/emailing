# Cómo conectar varias aplicaciones a los servicios comunes

**Última actualización: 2026-10-24.** Infraestructura y runtime de emailing implementados. Campañas, outbox, workers y actividad tienen revisión de sus componentes. La integración con Cronicle T-15 está en validación; el QA conjunto T-16 y la puesta en operación T-20 siguen pendientes. El laboratorio utiliza log privado y Mailpit.

## Qué declara cada proyecto Compose

`infra/compose.yaml` declara RabbitMQ, Cronicle, MySQL y Mailpit. Un proyecto de aplicación declara únicamente sus propios componentes y utiliza la red externa existente.

El [Compose de emailing](../../compose.yaml) declara `emailing-php`,
`emailing-web`, `emailing-worker` y `emailing-jobs`. La separación de consumidores
está revisada como componente T-13; el QA integral y el despliegue operativo
siguen pendientes. Reutiliza MySQL, RabbitMQ y Mailpit del laboratorio. No añade
Supervisor ni un segundo broker.

Ejemplo de conexión, todavía incompleto para ejecutar por sí solo:

```yaml
services:
  web:
    image: mi-aplicacion:version
    networks: [infra]
  worker:
    image: mi-aplicacion:version
    # Añadir aquí el comando real de consumo de esta aplicación.
    restart: unless-stopped
    networks: [infra]

networks:
  infra:
    external: true
    name: emailing-lab
```

Arranca la infraestructura antes del Compose de aplicación. No declares de nuevo `rabbitmq`, `cronicle`, `mysql` o `mailpit` para acceder a los servicios existentes. No necesitas publicar el puerto del broker en cada aplicación.

`external: true` indica que la red ya pertenece a otro proyecto. Parar la aplicación no debe borrar esa red. `depends_on` de Compose no expresa dependencias sobre servicios de un proyecto distinto: implementa reconexión y reintentos al conectar.

## Direcciones de conexión

| Dependencia | Aplicación ejecutada en Laragon/Windows | Aplicación ejecutada en Docker |
|---|---|---|
| MySQL | host `127.0.0.1`, puerto `3308` | host `mysql`, puerto `3306` |
| RabbitMQ | host `127.0.0.1`, puerto `5672` | host `rabbitmq`, puerto `5672` |
| SMTP de pruebas | host `127.0.0.1`, puerto `11025` | host `mailpit`, puerto `1025` |
| Cronicle API | `http://127.0.0.1:3012` | `http://cronicle:3012` |

El código de negocio puede ser el mismo en ambos perfiles. Lo que cambia es la configuración del entorno y la forma de iniciar sus procesos.

## Cómo aislar las colas

Asigna un vhost distinto por aplicación y entorno. Crea un usuario propio sin privilegios de administración global y otórgale los permisos necesarios dentro de ese vhost.

| Recurso | Laboratorio | Emailing implementado |
|---|---|---|
| Vhost | `emailing-lab`, utilizado por smoke | `emailing`, aprovisionado por `scripts/provision-local.ps1`. |
| Usuario | Administrador inicial de infraestructura | `emailing-app`, sin etiqueta administrativa. |
| Colas | `lab.smoke.<marcador>` | Jobs mediante `RABBITMQ_QUEUE`; entregas JSON mediante `DELIVERY_QUEUE`, con exchange y cola de fallos propios. Cada perfil QA debe configurar ambos grupos por separado. |
| Consumidor | Prueba de protocolo | Worker Laravel para jobs y consumidor `deliveries:work` para entregas; componente T-13 revisado, QA conjunto pendiente T-16. |

El [aprovisionamiento](../../scripts/provision-local.ps1) genera o conserva secretos locales y limita esa identidad al vhost `emailing`. [RuntimeIntegrationTest](../../tests/Feature/RuntimeIntegrationTest.php) comprueba el adaptador de aplicación. Una aplicación de otro lenguaje puede usar otro vhost y su propio formato. Compartir broker no hace compatibles los trabajos serializados de dos frameworks.

No entregues el usuario administrativo del laboratorio a todos los servicios. Define también qué ocurre con mensajes fallidos, reintentos y backlog antes de habilitar cargas persistentes.

## Quién ejecuta los workers

Los workers pertenecen a la aplicación porque cargan su código, configuración y dependencias. Una misma imagen de aplicación puede arrancar el servidor web o un consumidor mediante comandos distintos.

En Docker, ejecuta el consumidor como proceso principal en primer plano. Docker aplica la política de reinicio del contenedor; no hace falta añadir Supervisor por ese motivo.

El Compose de T-13 separa `emailing-worker`, que ejecuta `php artisan deliveries:work`, y `emailing-jobs`, que ejecuta `php artisan queue:work rabbitmq --sleep=2 --tries=3 --timeout=60 --max-time=3600`. El segundo no fija `--queue`: utiliza la cola de `config/queue.php`. Ambos tienen 75 segundos de parada. Esta definición todavía no acredita su despliegue operativo ni el QA integral de fase 3. Los recibos de fases 1/2 documentan un único worker Laravel; los componentes nuevos tienen sus propias pruebas y revisión T-13 en el ledger, sin sustituir el QA integral.

El consumidor JSON utiliza reservas SQL y ack manual; el dispatcher publica el outbox con confirms. Sus garantías y límites se describen en [entrega y recuperación](../aplicacion/entrega-y-recuperacion.md) y [ADR-003](../knowledge/adr/ADR-003-publicacion-confirmada-y-estado-smtp.md), todavía propuesta. Separar colas es imprescindible: un worker Laravel no debe deserializar los eventos mínimos del dominio y el consumidor de entregas no debe procesar jobs ordinarios.

Escalar consumidores significa iniciar más procesos del worker de esa aplicación. La cifra total prevista de 5–10 procesos no obliga a instalar 5–10 brokers ni 5–10 Cronicle.

Un reinicio puede interrumpir un trabajo después de producir un efecto externo y antes de confirmar su consumo. Por eso la aplicación necesita idempotencia y recuperación. La persistencia de RabbitMQ no garantiza por sí sola un único envío SMTP.

## Cómo utilizar Cronicle sin mezclar aplicaciones

Cronicle es el programador común de acciones finitas. Cada aplicación valida su propia llamada HTTP interna autenticada y ejecuta una acción acotada. Los consumidores permanentes pertenecen a la aplicación y se ejecutan en sus servicios de workers.

El servicio web registra `${COMPOSE_PROJECT_NAME:-emailing-app}-web` en la red compartida, único por proyecto. Evita depender de un alias genérico `web` si distintos Compose lo usan. La [guía de programación de emailing](../aplicacion/programacion-con-cronicle.md) describe el endpoint `POST /internal/cronicle/trigger`, las acciones `materialize` y `dispatch`, la credencial propia y los límites de cada llamada. Su aceptación final corresponde a T-15.

Para emailing en Docker, el ejemplo de la guía usa `http://emailing-app-web/internal/cronicle/trigger`; adapta el alias al nombre real de su proyecto. Para Laragon se configura un listener nativo alcanzable desde Cronicle y se comprueba esa ruta completa: un puerto local que responde desde Windows no garantiza que el contenedor pueda utilizarlo.

El [override opcional](../../infra/compose.emailing-cronicle.yaml) se combina con `infra/compose.yaml` dentro del mismo proyecto de infraestructura. Añade dos montajes de solo lectura al Cronicle existente: el cliente y su configuración privada. No se incluye en el Compose de aplicación ni crea otro programador. Prepara primero el archivo privado como explica la guía; el montaje exige que exista y que el usuario del servicio pueda leerlo. El token no se escribe en la definición del evento ni en sus logs.

Conserva los dos archivos Compose al recrear el servicio, también durante una actualización. Si utilizas solamente el Compose base, Docker puede retirar esos montajes y los eventos configurados dejarán de encontrar el cliente o su credencial. Otra aplicación debe aportar su integración y su credencial propias al mismo Cronicle, además de sus colas y permisos independientes en RabbitMQ.

Cada tarea debe tener un único propietario de su calendario. No programes la misma campaña simultáneamente en Cronicle y en otro scheduler sin un mecanismo explícito de coordinación.

Cronicle no necesita el socket Docker, un SSH administrativo ni los repositorios de todas las aplicaciones para llamar a un endpoint. Los jobs shell de su propia instalación siguen ejecutándose dentro de su contenedor.

## Cómo compartir MySQL sin mezclar datos

La instancia MySQL se puede compartir; cada aplicación adicional debe tener su base y usuario con permisos delimitados. El usuario actual `emailing` se creó para su base `emailing`.

No reutilices esa base como contenedor de datos ajenos. Las pruebas ya usan base y usuario exclusivos `emailing_testing`. [TestingDatabaseGuard](../../app/Support/TestingDatabaseGuard.php) comprueba esa identidad antes de limpiar tablas. Ejecutar `migrate:fresh` o la suite sobre la base operativa borraría información.

El smoke conserva cinco filas en `emailing.lab_smoke` y constan 13 tablas en `emailing`; no se ha acreditado el estado de migraciones. El Compose base está detenido, con volúmenes intactos. T-20 contempla inspección del esquema, copia/plan antes de migrar y ensayo de recuperación. El aprovisionamiento actual no realiza esa migración.

## Qué aislamiento tiene PHP y qué URL utiliza

PHP-FPM corre como `www-data` y solo escucha en un socket Unix. El volumen privado se monta únicamente en PHP y Nginx; Nginx lo monta en lectura. No se expone FastCGI TCP 9000. El worker conserva conectividad saliente sin montar ese socket.

La aplicación copia código a sus imágenes: los cambios PHP requieren reconstrucción. El Compose base fija `APP_URL=http://127.0.0.1:8081`, aunque `.env.example` declare 8080. Editar `.env` no sustituye el valor YAML. Otro origen necesita un override de entorno y publicación web.

Los perfiles validados son nativo 8082 y Docker 8083, con datos de pruebas. Docker QA utiliza un override local ignorado. Consulta [desarrollo y pruebas](../aplicacion/desarrollo.md) para su configuración y límites de portabilidad.

Invitaciones y recuperación usan Mailpit: SMTP Windows 11025 o Docker `mailpit:1025`; web 18025. Las pruebas de mensajes por cuenta eligen log privado o ese Mailpit. Su configuración no cambia el correo de acceso. SMTP externo sigue deshabilitado; guardar credenciales no conecta a ese host. Véase [plantillas y transporte](../aplicacion/plantillas-y-transporte.md).

## Cómo comprobar una integración nueva

Comprueba resolución DNS desde el contenedor de aplicación y apertura del puerto interno. Después prueba autenticación y una operación real con las credenciales de esa aplicación.

En RabbitMQ valida publicación, routing y consumo en su propio vhost. En MySQL valida acceso limitado a su base. En Mailpit envía un mensaje de prueba y búscalo por un marcador único.

Finalmente prueba recuperación tras reconexión. El QA de infraestructura demuestra servicios y red; fase 1 añade adaptador y worker de emailing. [QA3 fase 2](../roadmap/2026-10-21-plataforma-emailing/testing/fase-2/report-attempt-3.md) añade contactos/CSV, plantillas y transporte local en ambos perfiles. Ninguno acredita campañas o entrega fiable, ni sustituye validar otra aplicación. Coordina pruebas que escriban o reinicien infraestructura compartida con todos sus consumidores.

## Fuentes y siguiente lectura

La separación se establece en [Compose](../../infra/compose.yaml), la [ADR del broker](../knowledge/adr/ADR-001-rabbitmq-centralizado.md) y el [diseño de aplicación](../roadmap/2026-10-21-plataforma-emailing/design.md). Consulta [operación](operacion.md) antes de recrear infraestructura utilizada por otras aplicaciones.
