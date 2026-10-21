# Cómo conectar varias aplicaciones a los servicios comunes

**Última actualización: 2026-10-21.** La infraestructura está implementada. Los ejemplos de esta página son patrones de integración: cada aplicación debe aportar su imagen, comandos y credenciales reales.

## Qué declara cada proyecto Compose

`infra/compose.yaml` declara RabbitMQ, Cronicle, MySQL y Mailpit. Un proyecto de aplicación declara únicamente sus propios componentes y utiliza la red externa existente.

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

| Recurso | Laboratorio actual | Aplicación futura de ejemplo |
|---|---|---|
| Vhost | `emailing-lab`, utilizado por smoke | `emailing`, pendiente de aprovisionar. |
| Usuario | Administrador inicial de infraestructura | Usuario exclusivo de emailing, pendiente. |
| Colas | `lab.smoke.<marcador>` | Nombres definidos por el adaptador y el negocio. |
| Consumidor | Prueba de protocolo | Workers del código de emailing. |

Los nombres de la última columna son una propuesta, no recursos ya creados. Una aplicación de otro lenguaje puede usar otro vhost y su propio formato de mensajes. Compartir broker no vuelve compatibles automáticamente los trabajos serializados de dos frameworks.

No entregues el usuario administrativo del laboratorio a todos los servicios. Define también qué ocurre con mensajes fallidos, reintentos y backlog antes de habilitar cargas persistentes.

## Quién ejecuta los workers

Los workers pertenecen a la aplicación porque cargan su código, configuración y dependencias. Una misma imagen de aplicación puede arrancar el servidor web o un consumidor mediante comandos distintos.

En Docker, ejecuta el consumidor como proceso principal en primer plano. Docker aplica la política de reinicio del contenedor; no hace falta añadir Supervisor por ese motivo.

Escalar consumidores significa iniciar más procesos del worker de esa aplicación. La cifra total prevista de 5–10 procesos no obliga a instalar 5–10 brokers ni 5–10 Cronicle.

Un reinicio puede interrumpir un trabajo después de producir un efecto externo y antes de confirmar su consumo. Por eso la aplicación necesita idempotencia y recuperación. La persistencia de RabbitMQ no garantiza por sí sola un único envío SMTP.

## Cómo utilizar Cronicle sin mezclar aplicaciones

Cronicle será el programador común de acciones finitas. Una tarea podrá hacer una llamada HTTP interna autenticada a una aplicación; la aplicación validará la llamada y encolará el trabajo de negocio.

Para esa integración, registra un alias DNS único de aplicación en la red, por ejemplo `emailing-web`. Evita depender de un alias genérico `web` si distintos Compose lo usan en la misma red.

La URL interna puede tener la forma `http://emailing-web:<puerto-interno>/<ruta-interna>`. El puerto, ruta, autenticación, timeout y respuesta deben definirse al implementar el endpoint. Actualmente no existe un endpoint de negocio de emailing documentado como operativo.

Cada tarea debe tener un único propietario de su calendario. No programes la misma campaña simultáneamente en Cronicle y en otro scheduler sin un mecanismo explícito de coordinación.

Cronicle no necesita el socket Docker, un SSH administrativo ni los repositorios de todas las aplicaciones para llamar a un endpoint. Los jobs shell de su propia instalación siguen ejecutándose dentro de su contenedor.

## Cómo compartir MySQL sin mezclar datos

La instancia MySQL se puede compartir; cada aplicación adicional debe tener su base y usuario con permisos delimitados. El usuario actual `emailing` se creó para su base `emailing`.

No reutilices esa base como contenedor de datos ajenos. Separa también una futura base de pruebas destructivas: ejecutar `migrate:fresh` o una suite con limpieza global sobre la base operativa borraría información.

El smoke conserva `emailing.lab_smoke`. Las futuras migraciones de emailing deben añadir sus tablas sin eliminar ese marcador ni datos ya existentes.

## Cómo comprobar una integración nueva

Comprueba resolución DNS desde el contenedor de aplicación y apertura del puerto interno. Después prueba autenticación y una operación real con las credenciales de esa aplicación.

En RabbitMQ valida publicación, routing y consumo en su propio vhost. En MySQL valida acceso limitado a su base. En Mailpit envía un mensaje de prueba y búscalo por un marcador único.

Finalmente prueba recuperación tras reconexión. El QA actual demuestra conectividad de red y servicios; no sustituye las pruebas de cada adaptador o del proceso worker.

## Fuentes y siguiente lectura

La separación se establece en [Compose](../../infra/compose.yaml), la [ADR del broker](../knowledge/adr/ADR-001-rabbitmq-centralizado.md) y el [diseño de aplicación](../roadmap/2026-10-21-plataforma-emailing/design.md). Consulta [operación](operacion.md) antes de recrear infraestructura utilizada por otras aplicaciones.
