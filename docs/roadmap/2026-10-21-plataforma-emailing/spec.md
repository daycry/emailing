---
spec: plataforma-emailing
descripcion: Plataforma interna de email con Teammates y API sobre infraestructura de colas centralizada compartida entre aplicaciones.
estado: borrador
creado: 2026-10-21
actualizado: 2026-10-21
evaluacion: evaluation.md
design: design.md
plan: pendiente
generacion:
  inicio: 2026-10-21T10:01:59Z
  fin: 2026-10-21T10:04:41Z
  fuente: estimado
  tokens_reales: null
  eur: null
  horas_ia: null
  duracion: null
  duracion_reloj: 3m
  ratio_usado: 300000
  ratio_origen: default no calibrado (300000)
  nota: estima tokens/horas a juicio y márcalo como estimado
  avisos: [carpeta de transcripciones no disponible]
estimacion_generacion:
  tokens: { entrada: 12000, salida: 6000 }
  horas_ia: 0.06
  duracion: 4m
  metodo: Juicio sin telemetría; 18000 tokens divididos por 300000 tok/h. No usa el reloj. Incluye consolidación posterior a cierre.
---

# Plataforma interna de emailing con Teammates

> **Evaluación:** [evaluation.md](evaluation.md), en revisión; no aprobada todavía.
> **Plan de implementación:** pendiente de go/no-go.
> **Diseño exploratorio:** [design.md](design.md); RabbitMQ elegido por el usuario, stack PHP/Python/TypeScript pendiente.
> **Terminología:** cuenta/workspace contiene los recursos; usuario es una identidad personal; teammate es su membresía autorizada en una cuenta. Aceptación SMTP no demuestra entrega al buzón ni apertura.

## Contexto y objetivo

El usuario solicita una plataforma propia parecida a SendGrid para enviar emails, estudiar la referencia con REA y utilizar el workflow completo de custom-agents antes de desarrollar. Las aclaraciones definitivas son: aplicación de uso interno, varias personas pueden registrarse, datos separados y usuarios «igual que SendGrid», específicamente **con Teammates**. Las cuentas están aisladas entre sí y comparten sus recursos únicamente con miembros invitados y autorizados. La API de envío forma parte del alcance propuesto equivalente a los flujos documentados; no es una mera integración obligatoria con SendGrid.

REA capturó la web pública. No se ha accedido al panel autenticado ni al backend. Los requisitos de Teammates se apoyan en documentación oficial; ninguna decisión de infraestructura interna de SendGrid se presenta como observada.

La aclaración posterior confirma **un sistema de colas centralizado para varias aplicaciones**: emailing utiliza una o varias colas, otra aplicación sus propias colas y consumidores. Se integra infraestructura estándar de broker; la aplicación de email no se convierte en broker ni implementa funciones de las otras aplicaciones. El volumen inicial de emailing es **100–200 emails/día**; se prevén **5–10 procesos workers ejecutando trabajos**, repartidos entre aplicaciones/colas sin distribución fijada. Estas cifras no describen ni el caudal total de las demás aplicaciones ni 5–10 aplicaciones o cuentas.

## Decisiones de diseño

| Decisión | Elección | Motivo |
|---|---|---|
| Modalidad | Aplicación interna con registro de varias personas | Confirmado por usuario |
| Separación | Cada registro crea su cuenta; invitaciones permiten membresías en otras cuentas | Reconciliar datos separados con Teammates |
| Propiedad | Contactos, listas, plantillas, campañas, SMTP, remitentes, actividad, supresiones y API keys pertenecen a la cuenta | El creador es actor auditado, no propietario individual exclusivo |
| Autorización | Cuenta activa y scopes derivados de membresía validada o API key de cuenta | No confiar en `account_id`, `owner_id` o roles enviados en una petición |
| Teammates | Invitación explícita, selector de cuentas, Admin/Read-only/Restricted y permisos por módulo | Referencia documentada; sin inferir Subusers o impersonación |
| Transporte | SMTP configurable por cuenta y `log` seguro por defecto | Proveedor independiente y recorrido comprobable sin enviar correo real |
| Arquitectura | Opciones exploratorias O1 Laravel 13/Livewire, O2 Django 5.2 LTS/Celery y O3 NestJS/React | Sin stack ni motor de datos aprobados; el scaffold PHP/SQLite/PHPMailer no decide producción |
| Colas compartidas | RabbitMQ central, aplicaciones con sus propias colas y workers; lenguajes independientes | Broker elegido por el usuario el 2026-10-21; ver ADR-001 |
| Aislamiento entre aplicaciones | Credenciales y permisos/topología separados; vhost por aplicación/entorno en RabbitMQ | No equivale a aislamiento físico de CPU, RAM o disco; no exige mismo formato interno de jobs |
| Despliegue | Laboratorio de pruebas en un único host; comparar servicios Linux y Docker Compose | Alta disponibilidad fuera del alcance; Supervisor deja de ser condicionante |
| Portabilidad solicitada | Una aplicación ejecutable con Laragon y mediante Docker | MySQL propuesto por el usuario; concretar perfiles y validar ambos entornos antes de dar soporte por comprobado |
| Diseño visual | Interfaz propia en español inspirada en flujos documentados | No se requiere copiar marcas o activos |

## Configuración / parámetros

| Parámetro | Clave / mecanismo | Default | Valor objetivo |
|---|---|---|---|
| Transporte | `MAIL_TRANSPORT` | `log` | Configuración privada por cuenta; SMTP explícito |
| Credenciales/remitentes | Configuración persistente por cuenta | Vacía | Relay y remitentes autorizados; secretos cifrados |
| Variables globales existentes | `SMTP_*`, `MAIL_FROM_*` en `.env.example` | Vacías | No sustituyen configuración ni autorización de cada cuenta |
| URL pública | `APP_URL` | `http://127.0.0.1:8080` | HTTPS para invitaciones y bajas |
| Almacenamiento | `APP_STORAGE` | `storage/` | Datos y `.eml` fuera de `public/`; acceso por cuenta |
| Invitación | Token de uso único y caducidad | 7 días | Reenviar/revocar según permisos, sustituyendo la invitación anterior |
| Registro interno | Restricción configurable | Pendiente | Evitar registro operativo público sin control; mecanismo a concretar en diseño |
| Volumen inicial emailing | Tráfico de email de esta aplicación | 100–200 emails/día confirmados | Picos/latencia y carga agregada de otras aplicaciones pendientes |
| Consumidores | Procesos workers de trabajos | 5–10 confirmados | Reparto por aplicación/cola a dimensionar; no todos dedicados a SMTP |
| Broker | RabbitMQ; endpoint, credenciales y topología por aplicación/entorno | RabbitMQ elegido; Docker Management propuesto | Durabilidad, almacenamiento persistente, límites y recuperación comprobada |
| Gestión de procesos | Despliegue de web, workers y broker | Abierta; Supervisor deja de ser requisito | SO/servidor sin decidir; evitar supervisión doble de un mismo proceso |

## Arquitectura y componentes

Existe solo scaffold: `composer.json`, `.env.example`, `.htaccess`, `public/router.php` y PHPMailer instalado. El router referencia un `public/index.php` todavía inexistente y `composer.json` declara clases `src/`, worker y tests que aún no existen. No se considera aplicación funcional.

Componentes nuevos C-01 a C-07: identidades/cuentas/membresías y permisos; contactos/listas; plantillas; campañas/cola; transportes y remitentes; actividad/bajas; API y llaves. Todos los repositorios, descargas, listados, agregados y trabajos se resuelven con contexto de cuenta validado. Los workers usan el contexto persistido en el trabajo, sin depender de una sesión interactiva. La clave de API almacena cuenta y permisos; la UI no permite saltarse restricciones mediante API.

La [exploración de arquitectura](design.md) compara O1 Laravel 13/Livewire con adaptador RabbitMQ externo pendiente de validación, O2 Django 5.2 LTS/Celery y O3 NestJS/React. El usuario ha elegido RabbitMQ tras comparar con Kafka/Beanstalkd; [ADR-001](../../knowledge/adr/ADR-001-rabbitmq-centralizado.md) registra esa decisión. El stack y la topología siguen pendientes, sin benchmark ejecutado. Compartir broker no requiere compartir lenguaje ni serialización interna; consumidores entre aplicaciones necesitarían contratos explícitos, no incluidos por el mero hecho de compartir infraestructura.

Una outbox transaccional en la base de emailing registra trabajo/publicación; un dispatcher publica y reconcilia pendientes con confirms o garantía equivalente del broker elegido. No se presupone transacción atómica entre SQL y broker. Workers por aplicación validan identidad de cuenta, deduplican/reservan y confirman consumo solo tras resultado durable. Una reentrega no vuelve a enviar automáticamente si SMTP pudo aceptar. Las colas del broker son destino de trabajo; SQL conserva estado/outbox, no un sustituto de la infraestructura compartida.

El plan incluirá bootstrap de infraestructura compartida para un laboratorio de pruebas y despliegue de emailing: broker durable con almacenamiento persistente, credenciales/ACL por aplicación, colas/routing/reintentos/fallos, observabilidad y recuperación tras reinicios/fallos de proceso. La alta disponibilidad queda explícitamente fuera del alcance por aclaración del usuario; no se necesitan clústeres ni múltiples hosts para este laboratorio. Otras aplicaciones integrarán sus consumidores por separado; no se implementa su negocio. Se necesita confirmar arquitectura antes del plan definitivo; el diseño actual sigue exploratorio.

El usuario solicita además portabilidad entre Laragon y Docker y plantea MySQL como base. Tras explorar Supervisor común o por aplicación, pide replantear desde cero sin condicionar el diseño a Supervisor: la UI de Supervisor deja de ser requisito. Se comparan servicios Linux nativos y Compose con contenedores independientes por función/aplicación en un solo host. Misma lógica de aplicación, configuración por entorno y acceso al mismo almacenamiento privado para web y workers; los perfiles Laragon/Docker requieren validación explícita. Compose single-host se recomienda provisionalmente, sin convertirlo en elección aprobada.

Cronicle se incorpora como alternativa de scheduler/runner común con UI, propuesta por el usuario y pendiente de elección. Puede disparar tareas finitas por aplicación; RabbitMQ conserva los trabajos y cada aplicación mantiene sus consumidores. La integración propuesta usa HTTP interno autenticado para registrar/encolar trabajo durable, sin dar acceso general al socket Docker. Un resultado correcto del disparador no demuestra que todos los emails se hayan procesado. Elegir un único scheduler para cada tarea; no duplicar el disparo en Cronicle y en el scheduler de la aplicación.

## Flujo (paso a paso)

1. Una persona se registra conforme a la política interna, inicia sesión y dispone de su propia cuenta.
2. El owner/admin autorizado invita un teammate con rol/scopes; la persona acepta el token válido. Una identidad puede pertenecer a varias cuentas y seleccionar una cuenta activa.
3. Los miembros con permisos gestionan contactos/listas, plantillas y campañas compartidos dentro de esa cuenta.
4. Una campaña se revisa y programa o una API key autoriza un envío con sus scopes; la cuenta determina remitentes, transporte y límites.
5. La transacción registra trabajo/outbox; el dispatcher publica a las colas de emailing del broker compartido y reconcilia publicación pendiente. El worker de emailing reserva/deduplica, comprueba la supresión de esa cuenta y genera un `.eml` o entrega al relay; persiste resultado y actor/origen antes del ack. Un crash/reentrega se resuelve según estado persistido, sin reenvío ciego tras posible aceptación SMTP.
6. El panel muestra solo eventos demostrables y accesibles según cuenta/scopes. Los enlaces de baja se vinculan a su cuenta y destinatario.
7. Revocar una membresía elimina su acceso efectivo; los recursos creados por esa persona permanecen en la cuenta. Las API keys son recursos de cuenta y su revocación se gestiona explícitamente por un administrador autorizado.

## Alcance

**Dentro del MVP propuesto para aprobación:**

- **C-01:** registro/login, cuenta propia, recuperación de acceso básica, selector multi-cuenta, invitación por email expirable en 7 días y uso único, reenvío/revocación, roles Admin/Read-only/Restricted con scopes por módulo y presets equivalentes developer/marketer/accountant. Owner y gestión de miembros con reglas explícitas; auditoría del actor. Un admin no obtiene por ese rol acceso a cuentas ajenas.
- **C-02:** contactos/listas de cuenta, CRUD, importación CSV validada/deduplicada y trazabilidad de consentimiento.
- **C-03:** plantillas HTML/dinámicas de cuenta, variables acotadas, editor sencillo y preview aislada; mensaje de prueba con permiso de envío.
- **C-04:** campañas compartidas por cuenta, borradores, programación, colas en broker central compartido entre aplicaciones, outbox/reconciliación SQL-broker, reserva/deduplicación, límites y reintentos controlados; resultado ambiguo sin reenvío a ciegas.
- **C-05:** SMTP/TLS y remitentes por cuenta, secretos protegidos, modo log por defecto y diagnóstico sin secretos.
- **C-06:** actividad y métricas de estados reales por cuenta, bajas firmadas/idempotentes, listas de supresión y comprobación justo antes de enviar.
- **C-07:** API bearer de envío, llaves de cuenta revocables y permisos acotados, validación, límites, idempotencia y documentación de contrato. Quien no puede gestionar llaves no puede escalar permisos creando otra.
- **Infraestructura transversal:** bootstrap de broker estándar durable compartido y despliegue de emailing; permisos/credenciales/colas por aplicación, workers separados y supervisados, límites, observabilidad y recuperación. Lenguajes independientes y 1+ colas por aplicación. Acordar topología y reparto de 5–10 workers; validar adaptador, motor y broker elegidos. No añadir aquí funciones de otras aplicaciones.

**Fuera:** broker propio, negocio de otras aplicaciones, MTA propio, reputación/IPs dedicadas, SaaS externo/billing, Subusers jerárquicos, impersonación, SSO, alta disponibilidad (laboratorio de pruebas single-host), editor visual por bloques, automatizaciones avanzadas y analítica de aperturas/clics o entrega sin eventos verificables. «Igual que SendGrid» se acota a estos flujos documentados; no afirma réplica íntegra de producto o infraestructura.

## Manejo de errores

| Caso | Comportamiento |
|---|---|
| Cuenta/ID ajenos o scope insuficiente | Denegar lectura, escritura, listados, agregados, descarga y API; no confiar en identificadores o permisos del cliente |
| Teammate revocado o permiso reducido | Aplicar autorización efectiva en siguientes acciones, sin depender de permisos antiguos guardados en sesión |
| Invitación expirada, reutilizada o revocada | Rechazar aceptación; reenvío genera una invitación válida según reglas de uso único |
| Recurso creado por teammate eliminado | Mantenerlo en su cuenta; gestionar llaves de cuenta con revocación explícita, no por borrado accidental del creador |
| CSV inválido/duplicado | Resumen por fila y deduplicación dentro de la cuenta |
| HTML con scripts | Preview aislada; no ejecutar código en panel |
| SMTP sin configurar o TLS inválido | Bloquear salida y mostrar diagnóstico sin credenciales |
| Error previo a aceptación | Reintento controlado, con límite y espera |
| Desconexión tras posible aceptación | Estado ambiguo; no prometer exactly-once ni reenviar automáticamente |
| Dos workers | Una reserva válida por trabajo; recuperación definida para reservas vencidas |
| Caída SQL/broker/red o crash entre publicación y confirmación | Outbox/reconciliación, reentrega y deduplicación recuperan pendientes; no asumir atomicidad SQL-broker |
| Otro productor intenta usar las colas de emailing | ACL/credenciales del broker deniegan publicación/consumo/gestión no autorizados |
| Una aplicación satura sus colas | Límites y observabilidad por aplicación; medir efecto sobre las demás, sin afirmar aislamiento físico por vhost |
| Baja posterior a encolar | Comprobar supresión de la cuenta inmediatamente antes del transporte |
| Token de baja manipulado | Rechazar sin modificar otra cuenta ni revelar datos |

## Criterios de aceptación

- [ ] [GWT] CA-01 — Dada una persona sin sesión o token, cuando solicita datos o envíos, entonces se exige autenticación; escrituras de navegador requieren CSRF válido.
- [ ] [GWT] CA-02 — Dadas dos cuentas A/B y un CSV inválido/repetido en A, cuando se importa, entonces el resumen identifica resultados, deduplica en A y no lee ni modifica B.
- [ ] [GWT] CA-03 — Dado un contacto, cuando se consulta o selecciona para campaña, entonces su origen y fecha de consentimiento son trazables y el envío exige autorización suficiente.
- [ ] [GWT] CA-04 — Dada una plantilla con variables y scripts, cuando un miembro autorizado previsualiza, entonces se resuelven variables admitidas y no se ejecutan scripts en el panel.
- [ ] [GWT] CA-05 — Dada una campaña en borrador o futura, cuando corre el worker, entonces no la envía antes de la autorización y fecha efectiva.
- [ ] [GWT] CA-06 — Dado un trabajo, cuando dos workers lo reclaman, entonces solo una reserva válida llega al transporte con contexto de cuenta persistido.
- [ ] [GWT] CA-07 — Dado modo log, cuando se procesa, entonces genera `.eml` privado para esa cuenta y no abre SMTP.
- [ ] [GWT] CA-08 — Dado relay de prueba que acepta, cuando se procesa, entonces el panel informa aceptación SMTP sin afirmar entrega/apertura.
- [ ] [GWT] CA-09 — Dado fallo tras posible aceptación, cuando se registra resultado, entonces queda ambiguo y no se reenvía automáticamente.
- [ ] [GWT] CA-10 — Dado un enlace de baja válido, cuando se utiliza dos veces, entonces es idempotente, suprime pendientes en esa cuenta y no afecta a otra cuenta con el mismo email.
- [ ] [GWT] CA-11 — Dado token de baja alterado, cuando se solicita, entonces se rechaza sin cambiar datos ni revelarlos.
- [ ] [GWT] CA-12 — Dados secretos, `.eml` y base de datos, cuando se solicitan por HTTP o errores, entonces quedan protegidos y ninguna descarga omite autorización de cuenta.
- [ ] [GWT] CA-13 — Dado recurso creado por A y teammate B autorizado en su cuenta, cuando B actúa con scope permitido, entonces puede usarlo; C ajeno o B sin scope no puede leer/escribir/listar/exportar/agregar ni en UI ni API, incluso alterando IDs de cuenta o recurso.
- [ ] [GWT] CA-14 — Dadas campañas y SMTP de cuentas diferentes, cuando el worker las procesa sin sesión de usuario, entonces usa datos, secretos, supresión y actividad de cada cuenta sin mezclarlos.
- [ ] [GWT] CA-15 — Dada membresía revocada o scopes reducidos, cuando el miembro vuelve a actuar o cambia de cuenta, entonces se aplica el permiso vigente y quedan auditados actor, cuenta y acción; los recursos creados permanecen en su cuenta.
- [ ] [GWT] CA-16 — Dada la política interna configurada, cuando se registra una persona autorizada, entonces obtiene identidad/cuenta propia; un alta que no cumple la restricción no obtiene acceso operativo.
- [ ] [GWT] CA-17 — Dada una invitación de teammate, cuando se acepta antes de 7 días con la identidad prevista, entonces crea una sola membresía; un token vencido, usado, revocado o sustituido no permite otra aceptación.
- [ ] [GWT] CA-18 — Dada una identidad con membresías en dos cuentas, cuando cambia la cuenta activa, entonces solo ve/gestiona recursos según scopes de esa cuenta y no arrastra permisos de la anterior.
- [ ] [GWT] CA-19 — Dada una API key de cuenta con scope de envío, cuando envía con clave de idempotencia repetida, entonces hay un único trabajo; clave inválida/revocada o scope insuficiente se rechaza y no permite elegir una cuenta ajena.
- [ ] [GWT] CA-20 — Dado teammate con permisos restringidos o solo lectura, cuando intenta elevar sus scopes, crear una llave más privilegiada o gestionar miembros, entonces se deniega; al revocar una API key administrativamente sus siguientes usos fallan sin borrar campañas/contactos de cuenta.
- [ ] [GWT] CA-21 — Dadas emailing y otra aplicación de prueba con colas/credenciales separadas en el broker compartido, cuando cada una publica, consume o administra, entonces solo opera su topología autorizada y sus workers no consumen trabajos de la otra.
- [ ] [GWT] CA-22 — Dado trabajo persistido con outbox, cuando se cae el broker o el dispatcher antes/después de publicar y antes de confirmar, entonces la reconciliación recupera pendientes sin pérdida silenciosa y las reentregas no crean una segunda ejecución concurrente del mismo trabajo.
- [ ] [GWT] CA-23 — Dado crash del consumidor entre resultado SMTP, persistencia y ack, cuando el broker reentrega, entonces el estado durable determina la recuperación y un resultado potencialmente aceptado queda ambiguo sin reenvío automático; los errores previos siguen reintentos acotados y cola de fallos.
- [ ] [GWT] CA-24 — Dadas dos aplicaciones de prueba y un reparto explícito de 5–10 procesos workers entre sus colas, cuando una aplicación genera carga mientras emailing procesa su volumen inicial y picos acordados, entonces se registran latencia, pendientes, CPU/RAM/disco y fallos por aplicación y se comprueban límites/umbrales acordados sin prometer capacidad no medida.
- [ ] [GWT] CA-25 — Dado broker durable con almacenamiento persistente y servicios bajo la gestión de procesos elegida, cuando se reinician broker/worker o se restaura un respaldo de prueba, entonces se recuperan topología y trabajos según la política documentada y no arrancan instancias duplicadas por doble gestión.
- [ ] La entrega documenta instalación, migraciones, cola, registro/invitaciones, permisos y API keys, SMTP/log, respaldo y supresión; envío real requiere relay y remitentes autorizados.

## Pruebas

El futuro test-plan enlazará CA-01 a CA-25 a casos verificables con sus IDs. Probar matriz de dos cuentas y tres identidades: owner, teammate y ajeno, para cada recurso/operación/scope, incluyendo exportaciones y agregados. Integración de invitación caducada/uso único, revocación efectiva y cambio de cuenta. API verifica idempotencia, scopes y revocación. Cola se prueba con procesos concurrentes y SMTP controlado que induce fallo ambiguo; no se usa un email externo para demostrar estado de entrega. Añadir dos aplicaciones de prueba sin desarrollar su negocio, ACL del broker, outbox/reconciliación, cortes de red, crash antes/después de publicación y ack, persistencia/restauración, carga cruzada y reparto de los 5–10 workers. Fijar umbrales de picos, latencia y recuperación antes de validar capacidad; 100–200 emails/día solo corresponde a emailing. La revisión y QA son puertas separadas del desarrollo.

## Referencias

- Conversación: plataforma propia, workflow completo, uso interno, registro múltiple, datos separados y última precisión «con los teammates».
- Aclaraciones posteriores: servidor/SO sin elegir, Docker posible, 100–200 emails/día, 5–10 procesos workers y sistema de colas centralizado donde cada aplicación usa sus colas. La última instrucción retira Supervisor como condicionante y solicita replantear la arquitectura. [Diseño exploratorio y fuentes primarias de frameworks/brokers](design.md).
- [SendGrid público](https://www.twilio.com/en-us/sendgrid), [Teammates](https://www.twilio.com/docs/sendgrid/ui/account-and-settings/teammates), [permisos](https://www.twilio.com/docs/sendgrid/ui/account-and-settings/teammate-permissions).
- [Single Sends](https://www.twilio.com/docs/sendgrid/ui/sending-email/single-sends), [contactos](https://www.twilio.com/docs/sendgrid/ui/managing-contacts/create-and-manage-contacts), [grupos de baja](https://www.twilio.com/docs/sendgrid/ui/sending-email/create-and-manage-unsubscribe-groups).
- [PHPMailer](https://github.com/PHPMailer/PHPMailer), dependencia instalada.
- [Captura REA](../../evidence/sendgrid-public.json): `ev_1615846d7d905a0498e6d123257d2be4b6a3530bc274ce7be42326655a1cc411`; `scenario_start`/`public-page` completos, URL final pública, DOM/accesibilidad en Chromium 155.0.8059.85. SHA-256 DOM `9fab4c4a2cd4b2b2b25b68eecf0bc62ccee248b702f2d6bfb80a5962e3a79e95`. Diálogo de cookies presente; navegador propio cerrado al finalizar. Captura completa de esa navegación, sin panel autenticado ni backend.

## Decisiones confirmadas (revisión del usuario · 2026-10-21)

1. Plataforma propia para enviar emails con SendGrid de referencia, estudio con REA y workflow completo. **Confirmado.**
2. Aplicación interna con registro de varias personas, datos separados entre cuentas y Teammates invitados para compartir cuenta con permisos. **Confirmado por aclaraciones sucesivas.**
3. La última precisión «con los teammates» sustituye la interpretación temporal de propiedad exclusiva por `user_id`; los recursos pertenecen a la cuenta y se comparten por membresía autorizada.
4. Infraestructura de colas centralizada compartida por varias aplicaciones con colas/consumidores separados; se permiten lenguajes distintos y no se desarrolla un broker propio. **Confirmado.**
5. Volumen inicial de emailing **100–200 emails/día**, **5–10 procesos workers ejecutando trabajos**; reparto y carga de otras aplicaciones pendientes. Servidor/SO sin elegir y Docker posible; la instrucción posterior retira Supervisor como condicionante.
6. **RabbitMQ elegido** por el usuario el 2026-10-21: «pues RabbitMQ»; registrado en [ADR-001](../../knowledge/adr/ADR-001-rabbitmq-centralizado.md). Stack, adaptador, topología, presupuesto revisado y capacidad efectiva siguen pendientes; la elección del broker no aprueba el conjunto.
7. Revisión de arquitectura desde cero: «no tengas en cuenta lo de supervisor». Mantener el análisis abierto sobre despliegue y gestión de procesos; MySQL y portabilidad Laragon/Docker forman parte de la propuesta actual. El análisis no implica instalar infraestructura.
8. «No entra en juego la alta disponibilidad aquí, es para un laboratorio de pruebas». Alta disponibilidad fuera del alcance; Cronicle se estudia como opción, sin darlo por elegido.

## Supuestos

- Nueva identidad crea cuenta propia; invitaciones dan acceso a otras cuentas, sin jerarquía Subusers ni impersonación. Es la traducción propuesta de los requisitos y la documentación pública.
- Restricción exacta del registro interno, roles iniciales y permisos detallados se concretan en diseño sin inventar acceso público abierto. No hacen falta más preguntas de descubrimiento antes de presentar esta propuesta.
- El scaffold PHP/SQLite/PHPMailer no fija la arquitectura. RabbitMQ está elegido; las opciones de stack de [design.md](design.md) requieren elección y validación de integración; Celery no se presupone ejecutable en Windows nativo.
- Relay, DNS, remitentes, picos/carga agregada, reparto de workers y producción siguen pendientes; se puede validar con log/SMTP controlado mientras tanto.
- Credenciales/vhosts separan acceso/topología, no memoria o CPU físicas. El laboratorio single-host se dimensiona con carga medida; HA queda fuera del alcance.
- No hay constitución, calibración, memoria técnica o `rates.json` fiable en ubicaciones comprobadas. La estimación usa defaults identificados.
