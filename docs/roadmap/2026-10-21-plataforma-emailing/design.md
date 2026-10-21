---
design: plataforma-emailing
titulo: Laravel y Livewire sobre infraestructura compartida
estado: aprobado
creado: 2026-10-21
actualizado: 2026-10-21
spec: spec.md
evaluacion: evaluation.md
plan: improvement-plan.md
adr: ../../knowledge/adr/ADR-002-laravel-mysql-compose.md
opcion_elegida: O1
despliegue_elegido: D2
broker_elegido: RabbitMQ
broker_validado_por_usuario: 2026-10-21
generacion:
  inicio: 2026-10-21T16:18:17Z
  fin: 2026-10-21T16:20:27Z
  fuente: estimado
  tokens_reales: null
  eur: null
  horas_ia: null
  nota: Sin transcripciones compatibles; sin cifras medidas. Incluye consolidación editorial posterior al cierre del marcador.
  avisos: [carpeta de transcripciones no disponible]
---

# Diseño — Laravel y Livewire sobre infraestructura compartida

> **Spec:** [spec.md](spec.md), aprobada · **Evaluación:** [evaluation.md](evaluation.md), revisión del alcance de aplicación · **Plan:** pendiente · **ADR:** [ADR-002](../../knowledge/adr/ADR-002-laravel-mysql-compose.md), propuesta; [ADR-001](../../knowledge/adr/ADR-001-rabbitmq-centralizado.md), broker aceptado.

| Campo | Decisión |
|---|---|
| Estado | Aprobado, pasada 2 del architect |
| Opción elegida | O1: PHP 8.4 / Laravel 13 / Livewire, con MySQL |
| Despliegue elegido | D2: Compose independiente por aplicación conectado a infraestructura común |
| Validación | 2026-10-21: «vamos a por ello», tras recomendar cerrar Laravel + Livewire y planificar cuentas, Teammates y permisos; orquestador confirma O1 + D2 |

## 1. Contexto y restricciones

La aplicación interna necesita cuentas aisladas, membresías multi-cuenta, Teammates y permisos; después contactos, plantillas, campañas, SMTP por cuenta, bajas y API bearer. Se construye un monolito modular con reglas compartidas entre panel, API y trabajos. La interfaz es propia, en español. Los flujos de referencia se basan en documentación pública; no se ha observado el backend autenticado de SendGrid.

El repositorio contiene scaffold provisional en `composer.json`, `composer.lock`, `.env.example`, `.htaccess`, `public/router.php` y `storage/.gitkeep`, con PHPMailer instalado. No existe aplicación funcional ni datos de aplicación que migrar. Se autoriza sustituir este scaffold por Laravel; no se borran datos ni marcadores de smoke del laboratorio. No hay constitución que imponga otro stack.

La infraestructura existente está en `infra/compose.yaml` y documentada en `infra/README.md`: RabbitMQ 4.4.0 Management, MySQL 8.4.11, Cronicle 0.9.137 y Mailpit v1.27.4. El [informe de QA de infraestructura](../2026-10-21-infra-laboratorio/testing/report.md) registra protocolos, persistencia y tres subcasos de UI pasados. Docker Linux y la red compartida ya están probados; la aplicación todavía no.

- Laboratorio de un único host, sin alta disponibilidad ni requisito de Supervisor.
- RabbitMQ queda elegido por ADR-001; se comparte entre aplicaciones con vhost, credenciales y topología propias, sin asumir aislamiento físico de recursos.
- La aplicación tendrá un Compose separado que reutiliza la red externa `emailing-lab`. No redeclara los servicios comunes.
- MySQL/InnoDB es el motor elegido. La base de emailing y su usuario están separados de root y de las futuras bases de otras aplicaciones.
- Volumen inicial: 100–200 emails/día para emailing. Los 5–10 workers totales se repartirán según las pruebas; no se reservan todos para SMTP ni representan usuarios o aplicaciones.
- SMTP por cuenta inicialmente conectado a Mailpit. No se envía a Internet por defecto; aceptación SMTP no significa entrega al buzón. Mantener log privado como alternativa explícita.
- Configuración y secretos locales en `.env` ignorados; credenciales SMTP persistentes cifradas por cuenta. Nunca poner contraseñas en documentos o payloads de cola.

## 2. Opciones (3)

### O1 — PHP 8.4, Laravel 13 y Livewire

Panel Livewire/Blade con Tailwind y vistas propias, auth Laravel/Fortify y API bearer mediante Sanctum. MySQL persiste datos y estados. El paquete externo `vladimir-yuldashev/laravel-queue-rabbitmq` v15.0.2 declara `illuminate/queue` ^10|^11|^12|^13; se valida en runtime antes de integrar envíos. La resolución Composer fijará versiones compatibles de Livewire 4 y dependencias, sin componentes de pago necesarios. El starter kit estable antiguo basado en Laravel 12 no se usará para forzar un downgrade; si se adopta la plantilla oficial actual, se fija su revisión y se conservan locks de dependencias runtime estables.

| Criterio | Valoración |
|---|---|
| Complejidad | Media-alta: panel, auth y API integrados; aislamiento y semántica de envío explícitos |
| Riesgo | Medio: transporte RabbitMQ externo pendiente de prueba real |
| Coste relativo | M: integración del producto con validación adicional del adaptador |
| Reversibilidad | Media-baja: SQL y contratos trasladables; políticas, UI y jobs ligados al framework |

### O2 — Python, Django y Celery

Django 5.2 LTS, vistas Django/HTMX, MySQL y Celery con RabbitMQ. Auth y ORM integrados; API y permisos por cuenta requieren composición. Celery requiere entorno compatible Linux, no Windows nativo.

| Criterio | Valoración |
|---|---|
| Complejidad | Media-alta: dominio integrado y transporte Celery; panel y autorización propios |
| Riesgo | Medio: configurar reentregas, routing, acknowledgements y entorno de ejecución |
| Coste relativo | M: comparable con experiencia equivalente, sin ventajas de rendimiento medidas |
| Reversibilidad | Media-baja: cambiar ecosistema obliga a reescribir aplicación y tareas |

### O3 — TypeScript, NestJS y React

Backend y frontend separados, MySQL con ORM por resolver y transporte RabbitMQ de NestJS. Auth, SMTP y permisos se ensamblan; no se añade BullMQ/Redis para duplicar el broker.

| Criterio | Valoración |
|---|---|
| Complejidad | Alta: frontend separado y mayor composición de contratos y módulos |
| Riesgo | Medio: desajustes de contratos y semántica de jobs explícita |
| Coste relativo | L: mayor integración inicial sin requisito de SPA |
| Reversibilidad | Media-baja: frontend independiente; sustituir backend mantiene costes de migración |

**Opciones de despliegue independientes del lenguaje**

| Criterio | D1: servicios Linux nativos | D2: Compose por función y aplicación |
|---|---|---|
| Complejidad | Media; dependencias y permisos del host | Media; imágenes, redes y volúmenes explícitos |
| Riesgo | Acoplamiento de versiones y despliegue al host | Configuración y recuperación de contenedores |
| Coste relativo | S/M; más operación específica del host | M; construir imágenes y mantener volúmenes |
| Reversibilidad | Media; empaquetado posterior requiere traslado | Alta entre hosts Docker, trasladando también datos y claves |

Fuentes primarias de la comparación: [Laravel 13](https://laravel.com/framework/docs/13.x/releases), [starter kits](https://laravel.com/framework/docs/13.x/starter-kits), [Sanctum](https://laravel.com/framework/docs/13.x/sanctum), [colas](https://laravel.com/framework/docs/13.x/queues), [adaptador RabbitMQ](https://github.com/vyuldashev/laravel-queue-rabbitmq/blob/master/composer.json), [Django](https://www.djangoproject.com/download/), [Celery/RabbitMQ](https://docs.celeryq.dev/en/stable/getting-started/backends-and-brokers/rabbitmq.html), [NestJS/RabbitMQ](https://docs.nestjs.com/microservices/rabbitmq) y [Compose](https://docs.docker.com/compose/how-tos/production/). Las valoraciones son juicio arquitectónico; no benchmarks.

## 3. Criterios de decisión

1. Aislamiento de cuentas y autorización comprobable en UI, API y worker, con revocación efectiva.
2. Integración mantenible de panel, autenticación y API, sin frontend separado innecesario.
3. Reutilización de la infraestructura ya probada, con aplicaciones y despliegues independientes.
4. Portabilidad Laragon/Docker con la misma lógica y migraciones; secretos y datos se trasladan explícitamente.
5. Recuperación correcta frente a fallos de SQL, broker, worker y SMTP; medir capacidad antes de prometer rendimiento.

## 4. Recomendación · opción elegida y por qué

**O1 + D2 están elegidas.** El usuario confirma continuar con Laravel + Livewire, MySQL y el plan de implementación el 2026-10-21. Se integra un monolito modular PHP 8.4/Laravel 13 con UI española. El adaptador RabbitMQ debe superar una prueba de publicación, consumo, reentrega y recuperación; la compatibilidad del manifiesto no se presenta como ejecución probada. Una incompatibilidad sustantiva se comunica con evidencia antes de cambiar la arquitectura.

O2 queda descartada para este alcance porque no compensa cambiar el ecosistema PHP elegido; O3 añade ensamblaje y separación sin requisito de SPA. D1 es viable, pero D2 proporciona el laboratorio reproducible solicitado. PostgreSQL no se añade: MySQL cubre las necesidades y ya está desplegado y probado. Symfony/Messenger es una contingencia PHP si falla el transporte, no un segundo backend paralelo. No se atribuye superioridad universal de rendimiento a PHP ni a RabbitMQ.

**Ejecución y conexión**

| Función | Organización |
|---|---|
| Infraestructura | El Compose existente mantiene RabbitMQ, MySQL, Cronicle y Mailpit una sola vez |
| Web Docker | Nginx y PHP-FPM; FastCGI no se expone como HTTP público |
| Workers | Misma versión de código que la web, procesos principales en primer plano, política Docker de reinicio y parada ordenada; sin Supervisor |
| Programación | Cronicle dispara acciones finitas HTTP autenticadas; no recibe socket Docker ni consume RabbitMQ directamente |
| Perfil Laragon | Web nativa; MySQL `127.0.0.1:3308`, RabbitMQ `127.0.0.1:5672`, Mailpit SMTP `127.0.0.1:11025`, UI `127.0.0.1:18025` |
| Perfil Compose | Red externa `emailing-lab`; MySQL `mysql:3306`, RabbitMQ `rabbitmq:5672`, Mailpit `mailpit:1025` |

Cada aplicación usa vhost/usuario RabbitMQ de mínimo privilegio y base/usuario MySQL propios. El usuario administrativo RabbitMQ del laboratorio no es credencial runtime de emailing. La infraestructura se arranca primero, pero cada proceso conserva reconexión/backoff: `depends_on` no resuelve caídas posteriores. Docker reinicia ante salida; estado `unhealthy` solo no reinicia automáticamente. No se comparte un `vendor` generado para Windows y Linux si contiene dependencias incompatibles.

**Dominio y permisos**

Identidades participan en cuentas mediante membresías, con owner protegido y roles Admin/Read-only/Restricted y scopes por módulo. Todos los recursos de negocio llevan `account_id`; quien creó el recurso es actor auditado, no propietario exclusivo. El contexto de cuenta se deriva de membresía vigente o API key autorizada; no se acepta una cuenta o un rol arbitrario enviados por el cliente. Aplicar las mismas políticas a vistas Livewire, endpoints, descargas, listados, agregados y trabajos.

Primera fase ejecutable: C-01, autenticación, cuentas, selector, Teammates y permisos. Registro habilitado para laboratorio, creando cuenta propia. Invitaciones de uso único, siete días, reenviables/revocables; aceptación solo por identidad de email coincidente. Recuperación de acceso e invitaciones se prueban con Mailpit. C-02 a C-07 permanecen en el plan completo; la API bearer es core, no se elimina del alcance.

**Trabajos y recuperación**

SQL conserva trabajo y outbox transaccional. El dispatcher publica con confirms y detección probada de mensajes no enrutables; no se presupone transacción SQL/broker. Mensajes con identificador estable y referencias mínimas, sin secretos ni HTML completo. El worker reconstruye cuenta y permisos, reserva/deduplica en MySQL, comprueba supresión y confirma al broker solo tras persistir resultado durable.

Estados distinguen pendiente, reservado, aceptado por SMTP, fallido y ambiguo. Una caída tras posible aceptación SMTP no activa un reenvío ciego al reentregar el broker. Confirms y acknowledgements no proporcionan SMTP exactamente una vez. Reintentos acotados y cola de fallos requieren pruebas; prefetch y reparto de workers se ajustan por carga, no por suposiciones. Cada tarea tiene un único programador; Cronicle y Laravel no duplican el mismo disparo.

Volúmenes no sustituyen respaldos: exportar datos coherentes, archivos privados y claves. Las definiciones RabbitMQ no contienen sus mensajes. El laboratorio no ofrece HA; su robustez se verifica mediante reinicios, reentregas, persistencia, restauración y estados visibles. No se fija gasto real, capacidad máxima ni SLA. [ADR-002](../../knowledge/adr/ADR-002-laravel-mysql-compose.md) conserva la decisión para revisión.

### Reputación y habilitación de correo externo

La precisión posterior del usuario sobre listas de bloqueo se incorpora como puerta de C-05/C-06, pendiente de implementar. El relay realiza la entrega final y determina su IP de salida; la aplicación conserva responsabilidad sobre remitentes autorizados, consentimiento, frecuencia y supresiones. Docker, RabbitMQ y la separación de cuentas no protegen por sí solos la reputación de IP o dominio.

El paso de Mailpit/log a correo externo exige validar dominio y autenticación SPF/DKIM/DMARC, elegir un relay y conocer qué feedback proporciona. Los eventos autenticados o DSN soportados deben permitir tratar rebotes definitivos y bajas; para quejas se necesita un mecanismo disponible y documentado. No asumir que cualquier SMTP entrega esa información. Las cuentas comparten límites y riesgo cuando usan una misma credencial o pool, por lo que los límites agregados y la pausa de campañas complementan los límites por cuenta.

La [guía de entregabilidad](../../infraestructura/entregabilidad.md) documenta los controles, su estado pendiente y las fuentes. Seleccionar el conector concreto y ajustar su esfuerzo corresponde a la preparación de C-05/C-06; no cambia C-01 ni autoriza crear un MTA propio. Ninguna opción proporciona garantía absoluta de no aparecer en listas de bloqueo.

## 5. Impacto en módulos y ficheros

| Ruta real existente | Cambio con O1 + D2 | Nuevo / modificado |
|---|---|---|
| `composer.json`, `composer.lock` | Sustituir scaffold por Laravel, Livewire y dependencias compatibles; retirar PHPMailer | Modificados |
| `.env.example` | Defaults de app y perfiles de conexión, sin contraseñas; SMTP privado por cuenta | Modificado |
| `.htaccess`, `public/.htaccess`, `public/router.php` | Entrada Laravel compatible con Laragon y despliegue Docker | Modificados o sustituidos |
| `storage/.gitkeep` | Adaptar estructura privada Laravel; web y workers acceden a los mismos archivos autorizados | Revisado |
| `infra/compose.yaml`, `infra/README.md` | Reutilizar red, servicios y volúmenes; provisionar credenciales de app de forma acotada | Preservados; sin duplicación de servicios |
| `docs/roadmap/2026-10-21-plataforma-emailing/spec.md` | Consolidar decisiones elegidas e infraestructura ya terminada | Coordinación root |
| `docs/roadmap/2026-10-21-plataforma-emailing/evaluation.md` | Revisar alcance sobre infraestructura existente, sin presentar cifras históricas como vigentes | Evaluator |

Las rutas futuras siguen Laravel: `app/Models/`, `app/Policies/`, `app/Livewire/`, `resources/views/`, `database/migrations/`, `app/Jobs/`, `routes/web.php`, `routes/api.php`, `routes/console.php` y `tests/Feature/`. No se presentan como código existente. El Compose y Dockerfiles de aplicación serán nuevos e independientes de `infra/compose.yaml`.

## 6. Riesgos y mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|
| Acceso entre cuentas o escalada de permisos | Media | Alto | Políticas centrales, constraints y matriz UI/API/worker con dos cuentas y membresías revocadas |
| Adaptador RabbitMQ incompatible | Media | Alto | Probar versión estable con Laravel 13; publicación, ack, requeue, reconexión y rutas no válidas antes de envíos |
| Envíos repetidos tras caída SMTP | Media | Alto | Estados durables, reserva/idempotencia y aceptación ambigua sin reintento ciego |
| Publicación perdida o repetida | Media | Alto | Outbox, confirms, detección de no enrutables y reconciliación; deduplicación SQL |
| Aplicación monopoliza broker compartido | Media | Medio | ACL, límites y reparto de workers; pruebas con una segunda cola/app de laboratorio |
| Secretos o datos privados expuestos | Media | Alto | Cifrado por cuenta, logs depurados, archivos privados, preview aislada y `.env` ignorado |
| Diferencias Laragon/Linux | Media | Medio | Probar ambos perfiles; dependencias por plataforma y mismos contratos/migraciones |
| Worker vivo pero bloqueado | Media | Medio | Healthcheck/progreso, timeouts y reinicio visible; no afirmar autoreinicio por `unhealthy` |
| Migración o recreación destruye datos | Baja | Alto | No borrar volúmenes, migraciones acotadas, preservar smoke/datos ajenos y probar respaldo/restauración |
| Estimación histórica interpretada como presupuesto | Alta | Medio | Evaluator/planner revisan alcance elegido; no cambiar importes desde arquitectura |

## 7. Preguntas abiertas

- El implementer resolverá y bloqueará versiones compatibles; validará transporte RabbitMQ y ambos perfiles de aplicación. No requiere otra elección salvo incompatibilidad demostrada que cambie O1.
- El planner detallará owner protegido, matriz de scopes, cuenta activa y aceptación de invitaciones en C-01 antes de implementación.
- Dimensionar progresivamente los 5–10 workers compartidos y medir carga de otras aplicaciones; no bloquea el inicio de C-01 ni implica HA.
- Retención, remitentes reales y límites SMTP externos se concretan en C-05/C-06. Mailpit es el destino inicial del laboratorio.
- Evaluación y plan se ajustan a infraestructura completada; los importes históricos no se convierten en presupuesto ni gasto aprobado.

---

## Changelog

| Fecha | Cambio |
|---|---|
| 2026-10-21 | Comparación anticipada Laravel/Django/NestJS, broker compartido y alternativas de despliegue |
| 2026-10-21 | Usuario elige RabbitMQ, registrado en ADR-001 |
| 2026-10-21 | Infraestructura construida y probada en iniciativa independiente |
| 2026-10-21 | Usuario autoriza continuar; O1 + D2 elegidas, diseño aprobado y ADR-002 propuesta; handoff a planner para C-01 y alcance completo |
