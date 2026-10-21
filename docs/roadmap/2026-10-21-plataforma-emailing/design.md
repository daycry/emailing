---
design: plataforma-emailing
titulo: Tecnologías para la plataforma interna de emailing
estado: borrador
creado: 2026-10-21
actualizado: 2026-10-21
spec: spec.md
evaluacion: evaluation.md
plan: pendiente
adr: ../../knowledge/adr/ADR-001-rabbitmq-centralizado.md
opcion_elegida: pendiente
broker_elegido: RabbitMQ
broker_validado_por_usuario: 2026-10-21
despliegue_elegido: pendiente
generacion:
  inicio: 2026-10-21T12:47:21Z
  fin: 2026-10-21T12:51:51Z
  fuente: estimado
  tokens_reales: null
  eur: null
  horas_ia: null
  nota: Sin telemetría compatible; no se atribuyen cifras medidas. Incluye consolidación posterior al cierre.
  avisos: [carpeta de transcripciones no disponible]
---

# Diseño — Tecnologías para la plataforma interna de emailing

> **Spec:** [spec.md](spec.md), borrador · **Evaluación:** [evaluation.md](evaluation.md), en revisión · **Plan:** pendiente · **ADR del broker:** [ADR-001](../../knowledge/adr/ADR-001-rabbitmq-centralizado.md), aceptada por el usuario.

| | |
|---|---|
| **Estado** | Borrador exploratorio; pasada 1 |
| **Opción elegida** | Broker: RabbitMQ; stack y despliegue pendientes |
| **Validada por el usuario** | RabbitMQ confirmado el 2026-10-21 mediante «pues RabbitMQ»; arquitectura completa pendiente |

## 1. Contexto y restricciones

El usuario aclara que es un laboratorio de pruebas, sin alta disponibilidad, y pide replantear desde cero el despliegue sin requerir Supervisor. Docker es viable; RabbitMQ permanece elegido mediante ADR-001. Cada aplicación tendrá colas y consumidores propios. Solicita además valorar Cronicle. Esta exploración sobre spec borrador no aprueba stack, despliegue, alcance ni evaluación.

La plataforma necesita cuentas aisladas, Teammates, permisos por módulo, contactos/CSV, plantillas, campañas, cola, SMTP por cuenta, bajas y API bearer. Se propone un monolito modular: interfaz, API y procesos de cola comparten reglas de dominio y persistencia.

El repositorio contiene `composer.json`, `composer.lock`, `.env.example`, `.htaccess`, `public/router.php` y `storage/.gitkeep`. PHPMailer 7.1.1 está instalado. El router requiere `public/index.php`, que no existe; tampoco existen las clases, pruebas o consola declaradas en Composer. No hay producto funcional que migrar ni constitución. ADR-001 registra la elección posterior de RabbitMQ.

**Restricciones que fijan el espacio de soluciones**

- Laboratorio en un único servidor; alta disponibilidad y orquestación multi-host quedan fuera de alcance. Supervisor deja de ser requisito; se comparan servicios Linux nativos y Compose.
- Laragon y las herramientas locales facilitan desarrollo, pero no deciden el stack. No se ha probado el runtime Docker/WSL ni el servicio MySQL local.
- El transporte es un relay SMTP por cuenta, con modo log privado por defecto. No se construye un MTA. Aceptación SMTP no significa entrega al buzón.
- MySQL/InnoDB es la base preferida propuesta por el usuario, con versión compatible fijada y pruebas reales. PHP/SQLite/PHPMailer del scaffold no fija el stack; MySQL tampoco está desplegado ni validado.
- La cola será infraestructura compartida entre aplicaciones. Se necesitan credenciales, permisos, topología y consumidores separados; también límites para evitar que una aplicación perjudique a las demás.
- «Óptimo» requiere objetivos de latencia, caudal y recuperación. Sin picos, hardware ni mediciones no se puede nombrar al broker o lenguaje más rápido.
- Volumen inicial comunicado: 100–200 emails al día, con crecimiento posible. No se conoce su distribución en picos ni la carga agregada de otras aplicaciones; no se interpreta como mensajes por segundo.
- Concurrencia prevista confirmada: 5–10 procesos workers ejecutando trabajos. El reparto entre aplicaciones/colas es ajustable; no se reservan todos para los 100–200 emails diarios.

## 2. Opciones (3)

### O1 — PHP 8.4, Laravel 13 y Livewire

Interfaz Blade/Livewire y Tailwind con componentes gratuitos o vistas propias. Fortify cubre autenticación; Sanctum aporta tokens bearer con abilities. MySQL/InnoDB propuesto y RabbitMQ mediante `vyuldashev/laravel-queue-rabbitmq`: adaptador externo cuyo manifiesto declara Laravel 13 compatible; ejecución sin probar. RabbitMQ no es un driver incluido por Laravel. Laravel Mail usa Symfony Mailer con SMTP/log privado `.eml`; se retiraría PHPMailer. Web, workers, scheduler y dispatcher de outbox comparten imagen/código con comandos distintos. Symfony 7.4 LTS + Messenger/AMQP oficial es una variante PHP si evitar el adaptador resulta prioritario.

| Criterio | Valoración |
|---|---|
| Complejidad | Media-alta: panel/auth/API integrados; integración RabbitMQ y reglas de cuenta/envío requieren trabajo explícito |
| Riesgo | Medio: compatibilidad y mantenimiento del adaptador RabbitMQ; aislamiento y recuperación no vienen resueltos |
| Coste relativo | M: suite integrada con coste adicional de validar y operar el adaptador; supone experiencia equivalente |
| Reversibilidad | Media-baja: SQL y reglas de negocio son trasladables; cambiar framework exige reescribir interfaz, políticas y trabajos |

### O2 — Python, Django y Celery

Django 5.2 LTS con Python compatible y Celery 5.6; vistas Django/HTMX sin SPA separada. Auth, ORM y migraciones integrados; autorización de cuenta y API bearer/scopes explícitos. MySQL propuesto; Celery mantiene integración con RabbitMQ. Correo Django con SMTP por cuenta y log privado. Soporte extendido LTS hasta abril de 2028; fijar parches vigentes al implementar. Web, Celery, scheduler y outbox como procesos separados. Celery no soporta Windows nativo; requiere Linux o contenedores Linux operativos.

| Criterio | Valoración |
|---|---|
| Complejidad | Media-alta: dominio y ORM integrados; Celery aporta integración RabbitMQ; API y permisos siguen siendo propios |
| Riesgo | Medio: ajustar acknowledgements, reentrega y routing; el admin Django no sustituye al panel de cuentas |
| Coste relativo | M: integración de tareas disponible; experiencia del equipo y operación pesan más que diferencias supuestas de lenguaje |
| Reversibilidad | Media-baja: SQL y contratos son trasladables; cambiar ecosistema exige reescribir aplicación y tareas |

### O3 — TypeScript, NestJS, React y RabbitMQ

Backend NestJS, React y MySQL propuesto con ORM por seleccionar. Auth, permisos y SMTP/log se ensamblan con módulos elegidos. El transporte RabbitMQ permite acknowledgements manuales y prefetch controlado; no se añade BullMQ para duplicar broker. Web, tareas y publicación de outbox se ejecutan por separado bajo D1/D2. React es una elección de interfaz, no un requisito de Node. Compartir TypeScript no elimina validación en ejecución ni autorización.

| Criterio | Valoración |
|---|---|
| Complejidad | Alta: frontend/backend separados; transporte RabbitMQ disponible, pero routing, reintentos y fallos necesitan diseño |
| Riesgo | Medio: desajustes de contratos; RPC/eventos del framework no sustituyen una máquina de estados de trabajos |
| Coste relativo | L: mayor integración inicial con experiencia equivalente; puede bajar con equipo TypeScript y componentes previos |
| Reversibilidad | Media-baja: API y frontend pueden evolucionar por separado; sustituir backend o ORM requiere migración |

**Diseño común propuesto para las tres opciones**

Una base central contiene identidades, cuentas y membresías. Los recursos de cuenta llevan `account_id`, con índices, unicidades y restricciones coherentes. Cada operación resuelve cuenta y scopes desde una membresía vigente o llave autorizada. Los paquetes de autenticación no entregan automáticamente aislamiento, selector multi-cuenta, invitaciones ni los roles Admin/Read-only/Restricted requeridos.

Cada trabajo persiste cuenta, referencias e identificador estable; el worker reconstruye contexto sin sesión. Una transacción SQL registra trabajo y outbox. El dispatcher publica con publisher confirms y detección de mensajes no enrutables (`mandatory`/retornos o mecanismo equivalente probado); marca publicación solo tras confirmación y routing correcto. Si el cliente no ofrece estas garantías, exige validación o sustitución. No existe una transacción SQL/broker asumida: reconciliación y reenvío de publicación pueden duplicar notificaciones; idempotencia y reserva SQL evitan ejecución simultánea del trabajo.

El worker confirma al broker tras persistir un resultado durable. El estado distingue pendiente, reservado, aceptado por SMTP, fallido y ambiguo. Un fallo tras posible aceptación SMTP no activa reenvío automático, aunque el broker reentregue. Publisher confirms, consumer acknowledgements e idempotencia no garantizan entrega SMTP exactamente una vez ni entrega al buzón.

MySQL/InnoDB propuesto mantiene estado y outbox; se probarán reservas/bloqueos en MySQL real. SQLite tiene un escritor simultáneo y no sustituye esa prueba. Cada aplicación puede usar una base y credenciales propias en una instancia MySQL compartida; separar instancias si requiere aislamiento físico o ventanas de mantenimiento independientes. Dentro de emailing, `account_id` y membresías siguen aislando cuentas del producto.

**Comparación del broker compartido**

| Broker | Encaje para este caso | Coste relativo / condición |
|---|---|---|
| RabbitMQ | Task queues, routing, acknowledgements/confirms y vhosts/permisos por aplicación; opción recomendada para trabajos independientes | M: requiere límites, monitorización, estrategia de reintentos y topología durable; no se afirma mayor velocidad universal |
| Kafka | Log durable con replay, particiones y grupos de consumidores; también Share Groups para trabajos con confirmación individual | L para estos jobs: validar clientes, routing, retrasos y recuperación del conjunto; sus transacciones no cubren SMTP |
| Beanstalkd | Tubes, reserva, prioridades y retrasos para una cola de trabajos con protocolo acotado | S/M de broker; validar aislamiento de seguridad, integración por lenguaje, persistencia y recuperación antes de compartirlo |

Kafka también se puede desplegar en Docker. [Kafka Queues / Share Groups es production-ready desde 4.2](https://kafka.apache.org/blog/2026/02/17/apache-kafka-4.2.0-release-announcement/); no se descarta por incapacidad para ejecutar trabajos. Su valor relativo depende de necesitar retención/replay, consumidores independientes e integración de clientes. Una UI como [AKHQ](https://akhq.io/) se desplegaría como componente adicional.

Con RabbitMQ, proponer un vhost y usuarios de mínimo privilegio distintos por aplicación y entorno. Dentro de emailing: colas de SMTP transaccional, campañas, CSV y retry/fallos; grupos de workers ajustables por carga. No se necesita una cola por cuenta: el aislamiento `account_id` continúa dentro de la aplicación. Compartir broker no vuelve interoperables los jobs Celery y Laravel; si aparecen consumidores entre apps, definir contratos JSON versionados explícitos.

Publicar identificadores de trabajo y metadatos mínimos, evitando HTML completo o secretos SMTP. El worker recupera datos privados y valida referencias de cuenta. Para SMTP de duración variable, prefetch 1 es un punto de partida conservador a medir; ajustar concurrencia y prefetch con las pruebas, sin imponer esa cifra a todas las colas.

Los vhosts separan acceso y topología, no CPU, RAM ni disco. Aplicar límites de conexiones/colas, políticas de tamaño/retención, cuotas de publicación/consumo en las apps y alarmas. Dimensionar o separar nodos/instancias cuando se requiera aislamiento físico. Prefetch, concurrencia, límites SMTP y routing deben evitar que un CSV o una campaña monopolicen el servicio.

Para el laboratorio, un broker durable con almacenamiento persistente y recuperación probada. Configurar confirms, acknowledgements manuales y reintentos acotados con espera y cola de fallos. Repartir los 5–10 workers según las pruebas, sin dedicar todos a emailing por defecto.

El usuario elige RabbitMQ el 2026-10-21 tras comparar con Kafka. Docker con UI es la propuesta de despliegue presentada. La [imagen oficial de Docker RabbitMQ](https://hub.docker.com/_/rabbitmq) tiene variante `management` con [UI/API de administración](https://www.rabbitmq.com/docs/management). Se propone versión fijada, volumen persistente en `/var/lib/rabbitmq`, nombre de nodo estable, credenciales por aplicación y UI limitada a operadores en red privada. La UI del broker no sustituye el panel de campañas ni demuestra entrega al buzón. La instalación y la topología definitiva quedan para el plan; no se han creado contenedores.

**Opciones de despliegue independientes del lenguaje**

| Criterio | D1 — Linux, servicios nativos | D2 — Docker Compose por función/aplicación |
|---|---|---|
| Diseño | Nginx, runtime y workers bajo systemd u otro gestor nativo; RabbitMQ/MySQL como servicios | Contenedores por función, infraestructura común y despliegues de aplicaciones separados |
| Complejidad / riesgo | Media; dependencias conviven en host y necesitan paquetes/permisos coherentes | Media; imágenes y redes explícitas, entorno reproducible y recuperación por servicio |
| Coste relativo | S/M; menos plataforma, más operación específica del host | M; construir imágenes y mantener Docker/volúmenes, evitando procesos mezclados |
| Reversibilidad | Media; empaquetar después exige trasladar servicios/configuración | Alta entre hosts Docker; configuración y datos deben exportarse/migrarse |
| Preferir cuando | Equipo opera Linux nativo y portabilidad tiene menor peso | Laboratorio portable, reinicios independientes y varias aplicaciones con dependencias propias |

**D2 propuesta: laboratorio en un host, servicios independientes**

- Infraestructura central: RabbitMQ Management y MySQL persistentes, una sola vez; cada app conserva vhost/ACL, base/credenciales y releases propios. Redes privadas por aplicación y acceso controlado a infraestructura. Exponer solo HTTPS; UIs por VPN/red de administración.
- Una imagen inmutable por aplicación y release para código/runtime: comandos separados para web, workers por cola, scheduler y outbox-dispatcher. Cada app instala sus dependencias y actualiza sus consumidores sin redesplegar las otras. Para O1, PHP-FPM atiende FastCGI en privado y Nginx sirve `public/`/estáticos y delega PHP; PHP-FPM no es un servidor HTTP público. Nginx/proxy usa su imagen/configuración y recursos de esa release.
- Workers como proceso principal en primer plano, con señales propagadas correctamente; Docker aplica restart al salir. No se añade Supervisor dentro del contenedor. Reiniciar/escalar cada réplica por separado; repartir los 5–10 workers totales según carga. Un responsable de programación por tarea: scheduler de la app o Cronicle; exclusión de ejecuciones y límites de recursos comprobados.
- Healthchecks y señales de progreso detectan dependencia caída o worker atascado. Docker standalone no reinicia solo por estado `unhealthy`: restart responde a la salida del proceso. En laboratorio, mostrar fallo y permitir reinicio manual; watchdog externo/alertas son ampliación opcional. `depends_on` no sustituye reconexión/backoff durante ejecución.
- Parada ordenada: dejar de reclamar trabajos, finalizar o registrar el resultado en curso y respetar un plazo ligado al timeout SMTP. Despliegues drenan workers y coordinan migraciones compatibles; no arrancan otra ejecución por tratar una aceptación SMTP incierta como fallo seguro. Un kill forzado conserva el caso ambiguo para reconciliación.
- Un volumen persistente no es un backup. Exportación/respaldo coherente de MySQL, archivos privados y claves fuera del volumen; definiciones RabbitMQ y recuperación de pendientes mediante outbox. Exportar topología no incluye mensajes. Probar borrado/recreación de contenedores y restauración del laboratorio; no se plantea infraestructura de respaldo distribuida.
- UI opcional [Portainer](https://docs.portainer.io/user/docker/containers) para contenedores de todas las apps, logs y estadísticas; acceso de operador privilegiado y red privada. RabbitMQ Management administra colas/broker. Ninguna UI demuestra salud del negocio ni entrega SMTP: observar outbox/cola, progreso, fallos, ambiguos y recursos.

**Desarrollo y portabilidad:** perfil Laragon opcional para web/MySQL nativos, workers Linux en Docker y RabbitMQ de laboratorio; perfil Compose completo reproducible en otro servidor. Misma configuración lógica/migraciones, rutas portables y archivos privados accesibles por web/worker. Generar dependencias por plataforma: no compartir un `vendor` Windows/Linux incompatible. Bind mounts de código para desarrollo; imágenes fijadas para escenarios de prueba reproducibles y volúmenes para datos. Conservar claves al migrar secretos y validar ambos perfiles antes de afirmar soporte.

**Cronicle como opción de programación con UI**

Cronicle puede centralizar tareas programadas o bajo demanda, con logs y estadísticas de sus ejecuciones. Se propone opcional como scheduler/runner común del laboratorio. Su plugin HTTP puede invocar endpoints internos autenticados de cada app para acciones finitas: activar campañas vencidas o reconciliar outbox. Estas acciones registran estado durable o publican en RabbitMQ; workers permanentes de cada aplicación consumen independientemente bajo Docker. No se atribuye a Cronicle consumo RabbitMQ nativo ni gestión de daemons.

Su plugin shell ejecuta subprocesos donde corre Cronicle; no permite por sí solo ejecutar dentro de cualquier contenedor. Preferir HTTP interno o runner acotado antes de montar el socket Docker privilegiado. Si se adopta Cronicle, quitar del scheduler de cada app la misma tarea para evitar doble programación; conservar locks/idempotencia. La UI muestra ejecuciones Cronicle, no todos los workers ni el resultado de cada email. [Cronicle](https://cronicle.net/) y [plugins shell/HTTP](https://raw.githubusercontent.com/jhuckaby/Cronicle/master/docs/Plugins.md).

Fuentes de despliegue: [Compose en un servidor](https://docs.docker.com/compose/how-tos/production/), [servicios y restart](https://docs.docker.com/reference/compose-file/services/), [restart al salir el contenedor](https://docs.docker.com/reference/cli/docker/container/run/), [healthchecks](https://docs.docker.com/reference/dockerfile/#healthcheck), [dependencias de arranque](https://docs.docker.com/compose/how-tos/startup-order/), [Laravel/Nginx/PHP-FPM](https://laravel.com/docs/13.x/deployment). D2 es recomendación pendiente; no se han creado servicios, contenedores ni imágenes.

**Fuentes primarias verificadas (2026-10-21):** [Laravel 13](https://laravel.com/framework/docs/13.x/releases), [starter kits](https://laravel.com/framework/docs/13.x/starter-kits), [Sanctum](https://laravel.com/framework/docs/13.x/sanctum), [colas](https://laravel.com/framework/docs/13.x/queues), [correo](https://laravel.com/docs/13.x/mail), [adaptador RabbitMQ externo](https://github.com/vyuldashev/laravel-queue-rabbitmq/blob/master/composer.json), [Symfony LTS](https://symfony.com/releases/7.4), [Messenger/AMQP](https://symfony.com/doc/current/messenger.html#amqp-transport); [Django/LTS](https://www.djangoproject.com/download/), [auth](https://docs.djangoproject.com/en/5.2/topics/auth/), [correo](https://docs.djangoproject.com/en/6.0/topics/email/), [Celery/plataformas](https://docs.celeryq.dev/en/stable/getting-started/introduction.html), [Django/Celery](https://docs.celeryq.dev/en/stable/django/first-steps-with-django.html), [Celery/RabbitMQ](https://docs.celeryq.dev/en/stable/getting-started/backends-and-brokers/rabbitmq.html); [NestJS/RabbitMQ](https://docs.nestjs.com/microservices/rabbitmq).

Para el broker: [vhosts y separación lógica](https://www.rabbitmq.com/docs/vhosts), [acks/confirms](https://www.rabbitmq.com/docs/confirms), [prefetch](https://www.rabbitmq.com/docs/consumer-prefetch), [límites](https://www.rabbitmq.com/docs/limits); [Kafka y replay](https://kafka.apache.org/43/getting-started/introduction/); [Beanstalkd](https://beanstalkd.github.io/) y [protocolo/tubes](https://raw.githubusercontent.com/beanstalkd/beanstalkd/master/doc/protocol.txt); [SQLite](https://www.sqlite.org/whentouse.html). Las valoraciones son juicio arquitectónico; no resultados de benchmarks.

## 3. Criterios de decisión

1. Priorizar aislamiento comprobable y reglas coherentes entre panel, API y worker: atraviesan C-01 a C-07.
2. Integrar un broker compartido y políglota con control de acceso, recuperación y reparto de recursos entre aplicaciones.
3. Para 100–200 emails/día, priorizar fiabilidad, simplicidad, consumo y mantenimiento. Medir picos y carga compartida; el lenguaje no se elige por caudal supuesto.
4. Reducir integración y mantenimiento de auth, API y workers. Experiencia y hardware son desconocidos; priorizar reproducibilidad del laboratorio frente a infraestructura adicional.

| Criterio decisivo | O1 Laravel | O2 Django/Celery | O3 NestJS |
|---|---|---|---|
| Panel, auth y API bearer | Integración Fortify/Sanctum/Livewire | Auth/ORM integrados; API scoped requiere composición | Módulos de auth, ORM y UI por componer |
| RabbitMQ | Adaptador externo declara Laravel 13; probar | Broker soportado por Celery | Transporte oficial; diseñar semántica de jobs |
| Rendimiento real | Sin medir; lenguaje no decide a 100–200/día | Sin medir; mismas garantías exigidas | Sin medir; mismas garantías exigidas |
| Cuándo preferir | Equipo PHP y adaptador validado | Equipo Python o preferencia Celery | Equipo TypeScript y frontend independiente |

**Cómo validar rendimiento antes de fijar capacidad**

- Acordar carga normal/pico, tamaño de mensajes, latencia p95/p99 de publicación y espera, tiempo de recuperación y disponibilidad requeridos.
- Medir la integración RabbitMQ elegida con datos durables y payloads representativos; variar concurrencia, prefetch y reparto de colas. Observar CPU/RAM/disco y pendientes.
- Simular dos o más aplicaciones: una saturando sus colas y otra con trabajos cortos. Medir degradación, equidad y recuperación tras parar worker, broker o red.
- Separar coste de broker, consulta SQL, render HTML y relay SMTP. Probar con SMTP controlado y sus límites, sin atribuir al lenguaje las esperas de red.
- Registrar pérdidas, duplicados y estados ambiguos; elegir con umbrales acordados. No hay benchmark ejecutado ni resultado de rendimiento en esta exploración.

## 4. Recomendación · opción elegida y por qué

**Broker elegido: RabbitMQ.** El usuario confirma la elección el 2026-10-21 mediante «pues RabbitMQ». Su modelo de task queues, routing y aislamiento por aplicación encaja con las colas compartidas descritas. Kafka y Beanstalkd quedan como alternativas evaluadas y descartadas para este alcance; no se atribuye a RabbitMQ una superioridad universal de rendimiento o robustez. Decisión registrada en [ADR-001](../../knowledge/adr/ADR-001-rabbitmq-centralizado.md).

**Stack recomendado provisionalmente: O1 — Laravel + Livewire, condicionado a validar el adaptador RabbitMQ.** Su integración de panel, autenticación y API bearer reduce ensamblaje para el producto completo. O2 es un candidato comparable: auth/ORM integrados y RabbitMQ soportado por Celery; preferible con equipo Python o si el adaptador O1 falla. O3 gana peso con equipo TypeScript y frontend independiente. No se atribuye a O1 mejor rendimiento ni ventaja por evitar broker; ese argumento anterior queda retirado.

**Despliegue recomendado provisionalmente: D2 — Compose con servicios por función/aplicación.** Encaja con un laboratorio portable entre Laragon/Docker y aplicaciones independientes. D1 sigue viable con experiencia Linux nativa. Mantener un RabbitMQ, un MySQL, servicios propios de cada app y recuperación verificable; Cronicle es opción adicional para programación con UI, Portainer para contenedores y RabbitMQ Management para colas. No necesitan adoptarse todas las UIs para arrancar.

**Aplicación y despliegue pendientes.** RabbitMQ está elegido; O1 y D2 son recomendaciones, MySQL es preferencia propuesta y la topología no está aprobada. El diseño completo sigue en borrador. Evaluator revisará integración, operación, outbox, recuperación y pruebas antes del presupuesto; no se cambian cifras aquí.

## 5. Impacto en módulos y ficheros

| Módulo / fichero (ruta real) | Cambio previsto tras elección y aprobación | Nuevo / modificado |
|---|---|---|
| `composer.json`, `composer.lock` | O1: framework y adaptador RabbitMQ validado; O2/O3: sustituir scaffold PHP tras aprobación | Modificados o sustituidos |
| `.env.example` | Documentar base/broker, claves y defaults seguros; SMTP de cuenta permanece en persistencia privada | Modificado |
| `.htaccess`, `public/.htaccess`, `public/router.php` | Adaptar entrada HTTP al framework y preservar aislamiento de almacenamiento | Modificados o sustituidos según opción |
| `storage/.gitkeep` | Conservar almacenamiento privado; concretar ubicación de `.eml` y archivos por cuenta | Revisado |
| `docs/roadmap/2026-10-21-plataforma-emailing/spec.md` | Reconciliar laboratorio, D1/D2, Cronicle opcional, MySQL/portabilidad y recuperación; Supervisor no requerido | Coordinación root; sin cambios en esta pasada |
| `docs/roadmap/2026-10-21-plataforma-emailing/evaluation.md` | Evaluator revisa integración, operación elegida, imágenes, backups, recuperación y pruebas; presupuesto previo no vigente | Pendiente; sin cambios en esta pasada |

Los módulos de aplicación y pruebas serán nuevos. Sus rutas concretas seguirán las convenciones de la opción elegida; no se presentan directorios futuros como código existente.

## 6. Riesgos y mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|
| Acceso entre cuentas o escalada de scopes | Media | Alto | Autorización vigente en cada entrada; restricciones de persistencia; matriz UI/API/worker con dos cuentas |
| Reenvío duplicado por caída o timeout SMTP | Media | Alto | Máquina de estados e idempotencia; resultado ambiguo sin reintento ciego; relay controlado con fallos inducidos |
| Se pierde publicación o se procesa una reentrega dos veces | Media | Alto | Outbox transaccional SQL, confirms, reconciliación y deduplicación por trabajo; no asumir transacción SQL/broker |
| Una aplicación satura el broker compartido | Media | Alto | Vhosts/usuarios, límites, cuotas, workers separados y pruebas de carga cruzada; aislamiento físico si hace falta |
| Secretos o HTML de plantillas expuestos | Media | Alto | Cifrado, logs depurados, `.eml` privado y preview aislada con variables acotadas |
| Capacidad y operación sin dimensionar | Desconocida | Alto | Acordar volumen; probar el motor de base de datos elegido y la carga; planificar supervisión y límites por cuenta antes de prometer capacidad |
| Entorno local distinto del servidor | Media | Medio | Concretar destino y replicar servicios necesarios; fijar runtimes soportados; Celery requiere entorno compatible, no Windows nativo |
| Proceso vivo pero bloqueado o datos eliminados | Media | Alto | Healthchecks/progreso, reinicio visible y restauración probada; watchdog externo opcional |
| Release o parada corta duplica un envío | Media | Alto | Drenaje, señales y timeouts coordinados; migraciones compatibles; idempotencia y tratamiento durable de estados ambiguos |
| Evaluación genérica interpretada como presupuesto cerrado | Alta | Alto | Evaluator revisa tras elegir stack; no convertir coste relativo en horas ni confirmar el importe actual |

## 7. Preguntas abiertas

- Antes de implementar, concretar cargas agregadas, lenguajes y garantías de las otras apps, y reparto de los 5–10 workers confirmados. Infraestructura compartida no implica intercambio de mensajes.
- Concretar experiencia, servidor/hardware y picos sobre 100–200 emails/día; elegir O1/O2/O3 y D1/D2. MySQL es preferencia propuesta; Cronicle y Portainer son opciones, Supervisor no condiciona la elección.
- Antes de implementar: política de registro interno, matriz owner/miembros/scopes, retención y relay/remitentes; no bloquean esta comparación.
- Tras elegir el stack: revisar spec/evaluación y recoger el go/no-go del conjunto. ADR-001 registra únicamente la elección del broker; el diseño completo y el handoff a planner siguen pendientes.

---

## Changelog

| Fecha | Cambio |
|---|---|
| 2026-10-21 | Comparación anticipada ampliada a broker compartido multiapp, Supervisor, Docker viable y evaluación de rendimiento; sin elección ni aprobación |
| 2026-10-21 | Usuario elige RabbitMQ; registrada ADR-001 aceptada. Lenguaje/framework y aprobación del conjunto pendientes |
| 2026-10-21 | Revisión de despliegue para laboratorio: Supervisor no requerido; D2 recomendado frente a D1, Cronicle opcional; sin elección ni implementación |
