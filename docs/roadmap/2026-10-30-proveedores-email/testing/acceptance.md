# Aceptacion tecnica local de proveedores

La implementacion incorpora Brevo y SendGrid por SMTP y API al pipeline comun de pruebas, campanas, REST y MCP. La aceptacion del transporte y el feedback posterior son datos distintos. Credenciales, remitentes y permisos pertenecen a la cuenta compartida por sus Teammates.

## Evidencia original de backend

Los [indices originales](backend/index.json) enlazan tandas con sus JUnit, resumen, recibo y snapshot de fuentes. Se conservaron los hashes originales; las tandas se solapan y no se suman como tests nuevos. Todas usan datos sinteticos; SQL solo contra database y usuario `emailing_testing`, URL alternativa vacia y guardia de exclusividad antes de DDL.

| Tanda | Casos/aserciones | Resultado | Alcance |
|---|---:|---|---|
| t01-config-green2 | 20/265 | Verde | Configuracion, aislamiento, cifrado y regresion existente |
| t02-mysql-schema-green | 1/13 | Verde | Esquema, indices y fecha con microsegundos |
| t05-webhooks-https | 5/79 | Verde | Firma, Bearer, ventana, rotacion, cuenta y lote de1000eventos |
| t06-ui-green | 3/38 | Verde | Secretos de escritura, limpieza, permisos y remitentes |
| t06-pipeline-final | 6/213 | Verde anterior | Matriz por origen y replay concurrente de webhook; anterior al ultimo guard |
| fix1-regression-final | 16/277 | Rojo conservado | Los11MailTransport y4regresiones pasan; Activity esperaba9estados en vez de10 |
| final-focused-regression | 4/73 | Verde | Activity exige10yapi_accepted, replay REST y2guardas legadas de supresion |
| late-feedback-red | 1/6 | Rojo conservado | Callback delivered despues de429 permitia otro inicio durable |
| late-feedback-green | 3/38 | Verde | Callback entre intentos, anticipado y requeue seguro normal |
| prior-feedback-green | 1/11 | Verde | Callback del intento anterior duranteIOactual impide otro requeue |
| final-provider-pipeline | 8/241 | Verde final | Cuatro canales por REST/MCP/campanas, cuotas, fencing, config y feedback |
| final-provider-units | 90/1045 | Verde final | Mapeos API/SMTP y sesiones locales HTTPS/SMTP, TLS y negativos |

Los dos ultimos recibos acreditan506fuentes estables,0fallos/errores/omitidos. Las pruebas de cable mantienen certificados y credenciales sinteticas dentro del harness. TLS no confiable se comprueba desde el cliente (curl60), con cero HTTP o credenciales; STARTTLS valida tanto la ruta correcta como el rechazo de downgrade antes de AUTH/sobre/DATA. No se exportan configuraciones privadas, secretos ni stdout crudo de esos procesos.

`python scripts/check-mcp-contract.py`:37operaciones,76esquemas,599casos de herramientas,26errores,48OAuth y34cursores;0fallos. Packaging:3tests verdes con indice Git aislado sobre12entradas publicas; comprueba identidades en objetos y checkoutsLF/CRLF/input. El primer intento del arnes omitia REST en la seleccion temporal: se corrigio esa seleccion y se preserva el rojo privado; no se cambio producto, pins o assertions para pasar.

## Revision y QA

Revision3 A+B+C+D sin Critical/Important/Minor nuevos o reabiertos. Los [originales de las tres revisiones](review/attempt-3-A.md) mantienen la historia y los gapsTLS/AUTH corregidos. Revision estatica no equivale a QA.

[QA1](qa-1/report.md) conserva2/6verdes y4interrupciones por selectores/sincronizacion del recorrido. No se borraron escenarios, no subio el timeout y no se habilitaron retries. [QA2](qa-2/report.md) conserva5/6verdes; un espacio final trasUTC hacia fallar el selector del feedback. El ajuste final solo admite whitespace al final de la misma etiqueta/fecha.

[QA3](qa-3/report.md) acredita6/6originales verdes, gate0 y0fallos/flaky/omitidos/interrumpidos; una ejecucion por escenario, sin retries. Confirma aislamiento, cuatro canales, requeue/negativos, firmas/supresiones, envio posterior bloqueado, teclado y375px sin overflow. SQL/PHP/puertos propios limpios.507fuentes y el manifiestoCSS/JS permanecieron identicos antes/despues. NativeSHA `0e02e104b953162e70f52a4d45d32c369ac89ee2151970ad50f0848d016fe303`; gateSHA `a82153ea1900f2699f478a32eede7d9a52cd9d84f99b2c583c98efc31874d68f`.

Build final Vite exit0 antesQA3; manifesto `5360413f27210f025d350c599fe8f951ae85084637b81cc549a801a2ba39facd`. Pint--test y PHP--lint sobre71rutas explicitas, incluidos archivos nuevos:0fallos. La regla .gitattributes para testing conserva los bytes nativos y sus hashes al clonar; no cambia linters o umbrales de producto.

## Limites de la aceptacion

- M-01: configuracion privada y envio controlado real por cada canal; DNS, plan/permisos, conectividad, respuestas y webhooks del proveedor no acreditados por fixtures.
- M-02: aceptacion y retrospectiva humanas pendientes; no hay horas/tokens/coste reales medidos ni porcentaje de cobertura configurado.
- Los5-10workers compartidos y1000referencias distintas no tienen benchmark; el lote1000ensayado usa una referencia y comprueba un maximo de16consultas.
- Un callback conocido antes del inicio durable bloquea el retry. Un callback posterior no cancelaIOya iniciado; no se promete exactly-once externo ni entrega en bandeja.
- La ausencia de eventos no demuestra ausencia de rebotes/quejas ni buena reputacion deIP. Principal18/20 y MCP11/14 conservan sus pendientes.


## Manual y documentacion completa

La ampliacion autorizada T07 entrega [Markdown](../../../manual/guia-completa.md) y [PDF](../../../manual/guia-completa.pdf) desde una misma fuente:57paginas,24capitulos y8Mermaid; incluye instalacion de cero Docker/Laragon, uso, administracion y diagnostico. La [matriz](../../../manual/cobertura-documentacion.md) relaciona las opciones actuales con su documento dueno y capitulo.

[Revision documental2](manual/review/attempt-2-A.md), cuatro lentes sin gaps pendientes; [QA independiente](manual/qa-1/report.md) VERDE despues de inspeccionar todas las57paginas individualmente. 919/919bloques,37herramientas,71variables y1015enlaces verificados. La documentacion no convierte procedimientos de instalacion o cualificacion de proveedor en pruebas ejecutadas. Los limites anteriores siguen vigentes.
