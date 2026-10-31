# Revisión B — intento 1 de 3

Base: `74970e0af4c5e68bd3b8e139a0988a119208493e` hasta el worktree congelado, incluidos ficheros públicos nuevos. Lente: exclusivamente corrección. Revisor genérico independiente, modelo real heredado; rol reviewer nativo no disponible. Método: custom-agents 2.3.0 `adversarial-review`, con persona backend. No se ha modificado código ni ejecutado SQL, E2E o llamadas a proveedores.

## Veredicto

**1 Important pendiente; 0 Critical; 0 Minor.** No procede aprobar la lente B mientras persista B1-01.

| ID | Grado | Ruta y línea | Escenario concreto | Estado |
|---|---|---|---|---|
| B1-01 | Important | `app/Services/MailProviders/ProviderSmtpTransport.php:33`–34 | Un servidor SMTP devuelve `535` durante AUTH por credenciales incorrectas. El conector convierte ese rechazo permanente en `SmtpPreAcceptanceFailure`; el adaptador lo marca reintentable y el pipeline vuelve a intentar con la misma configuración hasta agotar el presupuesto. Además muestra `smtp_before_terminator` en lugar de distinguir el rechazo de autenticación. | pendiente |

### B1-01 — El rechazo SMTP definitivo de autenticación se reprograma

La clasificación de códigos de `ProviderSmtpTransport.php:30`–32 sólo funciona si recibe `SmtpRejected`. El cable real no produce esa excepción para AUTH:

1. `ObservedSmtpTransport.php:25` marca `messageEntered=true` antes de llamar a `parent::doSend`.
2. Symfony inicia la sesión dentro de `SmtpTransport::doSend` (`vendor/symfony/mailer/Transport/Smtp/SmtpTransport.php:213`–215). La negociación y AUTH ocurren allí, antes de MAIL FROM. Los autenticadores usan `executeCommand` con respuesta esperada 235; `EsmtpTransport.php:246`–269 agota los autenticadores y conserva el fallo definitivo.
3. `ObservedSmtpTransport.php:37` clasifica AUTH y sus pasos como `session`; las líneas 61–63 sólo fijan `firstFailure` para `mail`, `recipient`, `data` y `data_final`. Que un mecanismo fallido pueda recuperarse con otro no significa que, una vez agotados todos, el rechazo permanente deba perderse.
4. `SmtpConnector.php:46`–47 ve `messageEntered=true`, ninguna aceptación posible y ningún negativo fijado; lanza `SmtpPreAcceptanceFailure`.
5. `ProviderSmtpTransport.php:33`–34 devuelve `smtp_rejected`, `smtp_before_terminator`, `retryable=true`. El llamante `AccountMailTransport.php:192` recibe ese resultado y en su línea 161 lo entrega a `DeliveryLifecycle::finish`; sus líneas 187–200 reprograman el trabajo, sin una guarda superior que recupere el código 535.

Reproducción verificable: con un peer SMTP local y TLS válido que anuncie LOGIN/PLAIN y rechace todos los mecanismos con 535, una entrega durable debe quedar fallida sin nueva generación reintentable. Actualmente la ruta anterior devuelve el resultado reintentable y genera otra oportunidad. No se ha ejecutado esta reproducción en esta revisión por la congelación y la instrucción de lectura; la evidencia aquí es el recorrido estático completo hasta el framework y el llamante.

`tests/Unit/MailProviders/ProviderSmtpTransportTest.php::test_only_observed_transient_negatives_are_retryable` incluye 535, pero lo inyecta como `SmtpRejected('data_final', 535)`: no pasa por AUTH ni por la clasificación del conector. Los casos de cable de `ProviderSmtpWireTest::sessions` verifican negativos al final de DATA, con autenticación exitosa, por lo que tampoco detectan este escenario. No se acusa a esos tests de haber sido debilitados; son nuevos y su límite explica por qué el defecto puede sobrevivir.

## Cobertura de lectura

- Se inventariaron los 110 caminos públicos del alcance recibido usando el diff acumulado y `git ls-files --others --exclude-standard`; se excluyeron `.claude/**` y `tests/.runtime/**` conforme al encargo.
- Se leyó el código productivo nuevo y los cambios de integración: cuatro transportes, snapshots y credenciales, guardas de cuenta/remitente, ambos comienzos durables, finalización/recuperación/fencing, cuotas compartidas, autenticación/correlación/dedupe de callbacks, precedencia de feedback, supresiones, proyecciones REST/MCP, Livewire/vistas y las cuatro migraciones.
- Se siguieron los llamantes y el framework antes de señalar el manejo de error: `AccountMailTransport`, `DeliveryLifecycle`, `SmtpConnector`, `ObservedSmtpTransport` y Symfony SMTP/Auth.
- Se leyeron los nuevos tests Unit/Feature, fixtures SMTP/HTTPS, E2E y scripts de soporte de navegador/cable; sólo lectura. Los cambios de tests existentes conservan sus comprobaciones y ajustan texto esperado por la integración de feedback.
- OpenAPI REST, OAuth y catálogo MCP se compararon además como árboles parseados, para separar cambios semánticos del reformateo. Se compararon completos los fixtures 0.2.0 frente a 0.1.0. En `tools.json`, el caso antiguo 455 pasa de inválido a válido para `lab_authorized=false`; ese cambio está solicitado por el contrato de remitente externo independiente y documentado en el ledger/API. No es una actualización de golden sin criterio. Los otros cambios incorporan versión/pins y dos casos nuevos.
- Se consultaron spec, plan, ledger, test-plan, research y documentación operativa para fijar los escenarios y límites. La auditoría de conformidad completa por criterio corresponde a la lente A.

No se identificaron otros defectos demostrables de corrección dentro de lo leído. Las respuestas seguras de error, bloqueos y resultados ambiguos tienen un estado distinguible para el llamante; no se reportan como fallbacks silenciosos.

## Límites y pruebas

Esta revisión no acredita ejecución propia de tests, cobertura porcentual, QA de navegador ni funcionamiento real de Brevo/SendGrid. No se han leído ni calculado hashes de secretos, entornos privados, cuerpos privados o evidencias expresamente prohibidas.

El orquestador comunicó que la unidad conjunta terminó con 85 casos/823 aserciones y dos fallos en la expectativa del peer `tls=false` con CA no confiable. También comunicó `ConnectException`, ausencia de respuesta HTTP y de request/auth/correlation, sin retry. Un peer TLS 1.3 puede completar su parte antes de recibir la alerta del cliente: ese `tls=true` del servidor no demuestra aceptación del certificado por el cliente. Es un resultado pendiente de precisar por el orquestador, no un verde global ni un defecto TLS confirmado por esta lente. La regresión global y la QA posterior conservan sus puertas propias.

La semántica de revocación aplicada es la vigente: revocar API/MCP impide nuevas llamadas/replays y conserva trabajo ya aceptado; remitente/perfil se revalidan al comienzo. No se presenta esa conservación como defecto.
