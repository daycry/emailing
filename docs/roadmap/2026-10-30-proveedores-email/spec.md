---
spec: proveedores-email
descripcion: Envío por Brevo y SendGrid mediante SMTP y API con aislamiento por cuenta y resultados durables.
estado: aprobada
creado: 2026-10-30
actualizado: 2026-10-30
evaluacion: evaluation.md
design: n/a
plan: improvement-plan.md
generacion:
  inicio: "2026-10-30T20:40:53Z"
  fin: "2026-10-30T20:47:13Z"
  fuente: estimado
  tokens_reales: null
  eur: null
  horas_ia: null
  duracion: null
  ratio_usado: 300000
  avisos: ["Spec creada dentro de la evaluación; sin telemetría separada fiable, no se duplica su consumo."]
---

# Proveedores de correo: Brevo y SendGrid por SMTP y API

> **Evaluación:** [`evaluation.md`](evaluation.md).
> **Plan de implementación:** [improvement-plan.md](improvement-plan.md), [ledger canónico](tasks.md) y [plan de pruebas](test-plan.md).
> **Terminología:** aceptación significa que el proveedor ha recibido la solicitud; entrega significa un evento posterior autenticado. Un resultado incierto no demuestra ni rechazo ni entrega.

## Contexto y objetivo

El usuario autoriza «adelante con los proveedores, tanto por smtp como por api los 2 proveedores» y exige «código limpio y robusto». Los proveedores son Brevo y SendGrid, identificados en sus dos mensajes anteriores. La aplicación interna tiene cuentas y Teammates; contactos, permisos, campañas, API y MCP comparten servicios y trabajo durable. Esta ampliación añade cuatro opciones de envío sin crear otro broker, otro sistema de cuotas ni un segundo camino de campañas.

Actualmente `MailSettingsService` sólo admite `log` y `mailpit`; `AccountMailTransport` y las dos ramas de `DeliveryLifecycle::beforeStart` también los restringen. Las credenciales SMTP de preparación se guardan cifradas, pero no habilitan correo externo. El trabajo parte de ese estado y conserva ambos modos de laboratorio.

El encargo es una **capacidad nueva**. Su primer oráculo será un test nuevo que falle por ausencia de la capacidad antes de implementarla, seguido por pruebas de contrato, integración y regresión. Esto no activa un modo TDD global que el proyecto no tiene configurado.

## Decisiones de diseño

| Decisión | Elección | Motivo |
|---|---|---|
| Canal de envío | Brevo SMTP, Brevo API, SendGrid SMTP y SendGrid API | Las cuatro combinaciones forman parte del encargo explícito. |
| Responsabilidad de proveedores | Adaptadores pequeños tras el transporte común | Reutilizar cuenta, reserva, cuotas, outbox, permisos, bajas y trazabilidad existentes. |
| Credenciales | Configuración cifrada y oculta por cuenta/proveedor; secretos de escritura sin devolución al navegador | Cambiar de proveedor no reutiliza por accidente la clave de otro ni expone secretos en snapshots Livewire. |
| Salida de red | Hosts SMTP de proveedor y endpoints HTTPS fijados; verificación TLS activa | La selección del proveedor no permite convertir el servidor en cliente de destinos arbitrarios. |
| Activación | Habilitación explícita del despliegue, modo de cuenta y remitente autorizado para salida externa | El default sigue siendo laboratorio; autorizar un remitente local no constituye verificación DNS del proveedor. |
| API | Cliente HTTP instalado, sin reintentos automáticos de solicitud ambigua | Las colas gobiernan el trabajo; un timeout tras enviar puede haber generado ya el correo. |
| Eventos | SendGrid con firma verificable del cuerpo original; Brevo con autenticación Bearer y TLS | Usar mecanismos publicados por cada proveedor y deduplicar tras autenticar. |
| Identidad de entrega | Correlación local opaca por intento, ligada a cuenta y proveedor | No depender de un identificador de proveedor que pueda faltar, repetirse o variar entre SMTP y API. |
| Estado durable | Diferenciar aceptación SMTP, aceptación API y feedback posterior | Los contadores y la UI deben describir lo observado sin llamar entrega a una aceptación. |

Estas elecciones desarrollan el encargo autorizado y el reconocimiento del repositorio; no se presentan como preferencias adicionales expresadas por el usuario. Planner concreta las rutas y contratos, conservando estos límites.

## Configuración / parámetros

| Parámetro | Mecanismo | Default | Valor objetivo |
|---|---|---|---|
| Salida externa del despliegue | Flag explícito documentado | Deshabilitada | Habilitable privadamente sin editar código. |
| Proveedor y transporte de cuenta | Modo validado y persistente | `log` | Una de las cuatro combinaciones externas o un modo de laboratorio. |
| SMTP | Perfil fijo del proveedor, usuario/clave privados y TLS obligatorio | Sin credenciales externas | Diferenciar clave SMTP de Brevo y autenticación SMTP de SendGrid según sus contratos. |
| API | Clave privada por proveedor y HTTPS fijo | Sin clave | Autenticación definida por el proveedor; nunca en URL o logs. |
| Webhook | Clave pública de firma SendGrid o secreto Bearer Brevo por cuenta/proveedor | Sin receptor habilitado | Operador configura endpoint y autenticación privadamente. |
| Límites | Timeout de conexión/total, tamaño y número de eventos acotados | Heredar presupuestos seguros del worker | Ninguna operación de red puede agotar silenciosamente la reserva. |

Los nombres finales de nuevas claves de configuración y columnas pertenecen al plan; no se inventan valores de credenciales ni callbacks reales.

## Arquitectura y componentes

Se reutilizan `AccountMailTransport`, `SmtpConnector`, `ObservedSmtpTransport`, `MailSettingsService`, `DeliveryLifecycle`, `DeliveryQuota`, `SuppressionService` y la procedencia API/MCP ya existente. Las operaciones de red continúan fuera de las transacciones que mantienen locks de cuenta. Los adaptadores producen resultados normalizados y diagnósticos cerrados; no devuelven excepciones crudas con mensajes, cabeceras o secretos.

Se añade configuración por cuenta/proveedor y una frontera pequeña para transporte API, perfiles SMTP y feedback. El proveedor seleccionado y la correlación quedan fijados para el intento al cruzar el inicio durable. El webhook no cambia el proveedor elegido por un trabajo, no usa el selector de cuenta de una sesión y no confía en un `account_id` del payload para autorizar recursos.

El feedback conserva su propia identidad y cronología. La aceptación original y la procedencia del mensaje sobreviven a eventos repetidos, fuera de orden, eliminación de actores y cambios de configuración. Una queja o un rebote permanente genera la supresión en la cuenta correspondiente mediante el servicio común. Un evento benigno tardío no levanta una supresión ni degrada un evento final más fuerte.

## Flujo (paso a paso)

1. Una persona con permisos de ajustes selecciona proveedor/canal y suministra sus credenciales mediante el formulario privado; guardar ajustes no envía mensajes ni comprueba claves llamando al proveedor.
2. La aplicación valida la habilitación del despliegue, remitente, cuenta, permisos y configuración. El trabajo se admite por el mismo servicio de campañas, API, MCP o prueba explícita.
3. Bajo el mutex de cuenta se revalidan supresiones, reserva, configuración y cuota. Se registra el único inicio durable, proveedor/canal y correlación del intento.
4. El adaptador ejecuta SMTP o HTTPS con TLS y presupuestos acotados fuera de esos locks. Registra aceptación, rechazo demostrado o incertidumbre, sin retry HTTP implícito ni cambio automático de proveedor.
5. La finalización conserva el fencing del worker. Sólo un rechazo demostrado transitorio puede programar otro intento dentro del presupuesto; un resultado incierto queda reconciliable sin reenvío automático.
6. El receptor autentica el cuerpo original, valida formato/límites y correlaciona el intento de la cuenta. Deduplica antes de actualizar feedback/supresión en una transacción.
7. Actividad y ajustes muestran proveedor, canal, resultado normalizado y estado posterior sin secretos. La operación privada del proveedor se comprueba separadamente cuando el operador dispone de cuenta, dominio y destinatario autorizado.

## Alcance

- **Dentro de esta iteración:** configuración y permisos por cuenta; cuatro transportes; integración con campañas, API, MCP y pruebas; autenticación y deduplicación de eventos de entrega, rebote, queja y baja; UI, contratos aditivos pertinentes, pruebas locales y documentación.
- **Fuera:** compras o creación de cuentas de proveedor, envío externo sin destinatario autorizado, provisión DNS automática, IP dedicada/warm-up automático, seguimiento de apertura/clic, campañas gestionadas desde la API de marketing del proveedor, HA y nueva infraestructura.
- La validación privada real requiere cuentas, claves, remitente/dominio y destinatario aportados privadamente por el operador. Su ausencia no impide implementar ni aceptar la QA local, y no se sustituye por una afirmación de compatibilidad operativa ya probada.
- Esta iniciativa conserva abiertos T12–T14 de MCP y T19/T20 de la plataforma principal. Sus aceptaciones anteriores no se reescriben.

## Manejo de errores

| Caso | Comportamiento |
|---|---|
| Flag deshabilitado, remitente no autorizado o configuración incompleta | Bloqueo antes de la conexión, diagnóstico seguro y sin consumir un envío externo. |
| SMTP devuelve un negativo observado | Clasificar código/etapa; sólo transitorios demostrados pueden reprogramarse. |
| SMTP pierde confirmación tras posible aceptación | Estado incierto; conservar recibo y no reenviar automáticamente. |
| API acepta la solicitud | Estado de aceptación API y referencia normalizada; no afirmar entrega. |
| API rechaza credenciales o payload | Fallo permanente seguro; sin bucle de retry. |
| API informa límite temporal inequívoco | Espera acotada, respetando el presupuesto y `Retry-After` válido; no saturar proveedor ni retener locks. |
| Timeout, conexión interrumpida o respuesta que no prueba rechazo | Conservar incertidumbre; sin retry ciego, failover ni duplicación entre API/SMTP. |
| Webhook sin autenticación, firma inválida o cuerpo alterado | Rechazo sin escribir feedback ni supresiones. |
| Evento válido repetido, ajeno o desconocido | No duplica efectos ni afecta otra cuenta; respuesta y tratamiento documentados. |
| Eventos fuera de orden o pérdida de reserva | Conservar estado durable y fencing; no enviar de nuevo por un evento tardío. |

## Criterios de aceptación

- [ ] [GWT] CA-01 — Dada una cuenta con permiso de ajustes, cuando guarda cada proveedor/canal, entonces la selección persiste por cuenta y los secretos se cifran y permanecen ausentes de HTML, snapshots, serialización y auditoría.
- [ ] [GWT] CA-02 — Dadas dos cuentas y membresías con permisos diferentes, cuando se cambian credenciales, remitentes o proveedor, entonces se revalidan permisos y ninguna operación lee o modifica la configuración de otra cuenta.
- [ ] [GWT] CA-03 — Dado el default o una habilitación/remitente incompletos, cuando se solicita salida externa, entonces se bloquea antes de red; log y Mailpit conservan su comportamiento.
- [ ] [GWT] CA-04 — Dada configuración SMTP válida de cada proveedor, cuando el adaptador envía a una fixture SMTP controlada, entonces aplica autenticación/perfil esperado, TLS verificado, timeout y aceptación/rechazo observado.
- [ ] [GWT] CA-05 — Dado un intento SMTP que pierde la respuesta después del terminador o un negativo transitorio/permanente, cuando se finaliza, entonces se conserva respectivamente incertidumbre o clasificación segura y sólo se reintenta el rechazo transitorio demostrado.
- [ ] [GWT] CA-06 — Dada cada API de proveedor, cuando se envía mediante una fixture HTTP controlada, entonces el endpoint, autenticación, contenido y correlación respetan el contrato y la aceptación se registra como API sin afirmar entrega SMTP.
- [ ] [GWT] CA-07 — Dados errores de credenciales/payload, rate limit, respuesta malformada, timeout o aceptación incierta, cuando termina la llamada API, entonces no hay retry implícito ni failover, el diagnóstico no contiene secretos y sólo una negativa transitoria inequívoca permite reprogramar.
- [ ] [GWT] CA-08 — Dado un intento en curso, cuando cambian ajustes o el worker pierde la reserva, entonces no se redirige el intento a otro proveedor/cuenta y una finalización obsoleta no sobrescribe el resultado vigente.
- [ ] [GWT] CA-09 — Dado un webhook SendGrid firmado, cuando se verifica el cuerpo original y timestamp, entonces sólo la firma válida dentro de la política temporal permite persistir; modificar un byte o usar otra clave impide efectos.
- [ ] [GWT] CA-10 — Dado un webhook Brevo, cuando se presenta el Bearer correcto/incorrecto con payload acotado, entonces sólo la autenticación correcta permite procesarlo y el secreto no aparece en recibos ni logs.
- [ ] [GWT] CA-11 — Dado un evento autenticado repetido, reordenado o que contiene referencias de otra cuenta/proveedor, cuando se procesa, entonces no duplica efectos, no cruza cuentas y conserva la aceptación y procedencia originales.
- [ ] [GWT] CA-12 — Dada una queja, baja o rebote permanente correlacionado, cuando se procesa, entonces se aplica la supresión de esa cuenta y un evento de entrega tardío no la revierte; un rebote temporal no genera una supresión permanente indebida.
- [ ] [GWT] CA-13 — Dadas campañas, API y MCP con trabajo durable, cuando usan cualquiera de los cuatro transportes, entonces conservan cuota, outbox, autorización vigente, supresiones, recibos idempotentes y procedencia sin enviar dos veces por redelivery.
- [ ] [GWT] CA-14 — Dadas las vistas de ajustes y actividad, cuando se selecciona proveedor/canal, se actualizan o eliminan credenciales y se consulta el resultado, entonces hay estados y errores claros, navegación por teclado y distinción entre aceptación y feedback posterior.
- [ ] [GWT] CA-15 — Dados los contratos y clientes actuales, cuando consumen estados/contadores tras esta ampliación, entonces los cambios documentados son compatibles o aditivos y los valores históricos SMTP conservan su significado; la aceptación API tiene representación propia.
- [ ] [GWT] CA-16 — Dado un hostname, redirect, certificado o respuesta fuera de política, cuando el transporte intenta utilizarlo, entonces se rechaza sin degradar TLS, exponer secretos ni continuar hacia un destino no permitido.
- [ ] CA-17 — Los adaptadores tienen responsabilidades acotadas, reutilizan clientes instalados salvo justificación documentada y pasan revisión independiente y QA local con evidencias; no se declara cobertura porcentual sin herramienta/umbral configurados.
- [ ] CA-18 — La documentación distingue las cuatro recetas, claves SMTP/API y autenticación de webhooks, activación/revocación/rotación, límites de retry y validación local frente a prueba privada real; no publica credenciales, callbacks personales ni cuerpos con datos reales.

## Pruebas

Planner trasladará CA-01–CA-16 a escenarios Given/When/Then trazables en `test-plan.md`; las pruebas backend podrán agruparse sin reducir los criterios. La UI nueva necesita QA de navegador independiente. Los negativos incluyen aislamiento, revocación de permisos, secretos en errores, TLS, límites, retry, timeouts, reserva perdida, eventos duplicados y fuera de orden.

Los tests SQL comprobarán base y usuario exclusivos de testing antes de DDL, mantendrán `DB_URL` vacío y no ejecutarán migraciones destructivas sobre la base operativa. Los servidores simulados se limitan al loopback y recursos propios. Se preservan los fallos originales, comandos, fuentes y resultados; no se suman suites solapadas ni se reutiliza el verde histórico de otra versión como ejecución actual.

## Referencias

- Encargo del usuario de 2026-10-30, citado en «Contexto y objetivo»; autorización del flujo completo conservada por el orquestador.
- [`AccountMailTransport.php`](../../../app/Services/AccountMailTransport.php), `execute` y `perform`: inicio durable y separación de I/O.
- [`DeliveryLifecycle.php`](../../../app/Services/DeliveryLifecycle.php), `beforeStart`, `apiBeforeStart` y `finish`: modos vigentes, fencing y resultado ambiguo.
- [`MailSettingsService.php`](../../../app/Services/MailSettingsService.php), `saveSettings`, y [`MailSetting.php`](../../../app/Models/MailSetting.php): permisos, cifrado y modos actuales.
- [`SmtpConnector.php`](../../../app/Services/SmtpConnector.php) y [`ObservedSmtpTransport.php`](../../../app/Services/ObservedSmtpTransport.php): TLS, tiempos, aceptación posible y error seguro.
- [`MailTransportTest.php`](../../../tests/Feature/MailTransportTest.php): regresión de aislamiento, secretos y Mailpit.
- Contratos oficiales de [SMTP Brevo](https://developers.brevo.com/docs/smtp-integration), [API Brevo](https://developers.brevo.com/reference/send-transac-email), [SMTP SendGrid](https://www.twilio.com/docs/sendgrid/for-developers/sending-email/integrating-with-the-smtp-api) y [API SendGrid](https://www.twilio.com/docs/sendgrid/api-reference/mail-send/mail-send), consultados en la investigación del encargo.
- [Autenticación de webhooks Brevo](https://developers.brevo.com/docs/secured-webhooks), [eventos Brevo](https://developers.brevo.com/docs/transactional-webhooks) y [eventos SendGrid](https://www.twilio.com/docs/sendgrid/for-developers/tracking-events/event), contrastados el 2026-10-30. En Brevo, `id` identifica el webhook, no un evento único: no sirve por sí solo para deduplicar.

## Decisiones confirmadas (autorización del usuario · 2026-10-30)

1. Implementar Brevo y SendGrid tanto por SMTP como por API. **Confirmado por el mensaje de encargo.**
2. Código limpio y robusto. **Confirmado por el mensaje de encargo.**
3. Continuar el workflow completo de custom-agents ya elegido. **Autorización conservada por el orquestador; no se solicita otra confirmación para la misma implementación.**

## Supuestos

- **Hecho técnico:** Laravel HTTP y Symfony SMTP ya están disponibles; no se presupone un nuevo SDK ni un cambio de framework — `composer.json`, `SmtpConnector.php`.
- **Hecho técnico:** los guardas actuales de transporte sólo habilitan log/Mailpit — `MailSettingsService.php`, `AccountMailTransport.php`, `DeliveryLifecycle.php`.
- **Hecho técnico:** perfil efectivo2.3 normal; TDD, worktree y subagentes de implementación desactivados; revisión fresca y QA obligatorias; capacidades `api-contract`, `backend-practices`, `delivery-practices`, `frontend-quality`, `stack-practices`, `unit-tests` sin avisos. La sesión ejecuta roles genéricos y no acredita modelos nativos del plugin.
- **Supuesto operativo:** el operador configurará privadamente dominios, cuentas, secretos y endpoints del proveedor antes de una prueba real. Su disponibilidad no se ha acreditado ni se solicitarán claves por chat.
- **Supuesto de alcance:** se envía mediante las APIs transaccionales de los proveedores; sus herramientas de marketing, contactos y facturación no se replican.
- **Estimación:** ninguna cifra de la evaluación representa consumo real o un compromiso de fecha. No hay tarifa personal, presupuesto máximo ni deadline aportados.
