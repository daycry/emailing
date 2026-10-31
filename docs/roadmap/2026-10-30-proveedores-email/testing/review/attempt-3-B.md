# Lente B — intento 3 de 3

**Veredicto: sin defectos nuevos de corrección en el delta posterior al intento 2.** Critical: 0; Important: 0; Minor: 0.

Revisión independiente de contexto fresco, mediante rol genérico heredado real; reviewer nativo indisponible. Aplicados el prompt literal B recibido y la persona backend compartida. Alcance: `review-3-production.patch`, sus llamantes, dos nuevos tests de feedback, correcciones E2E frente a la copia QA1 y esperado de ActivityTest. Se conserva el juicio anterior de la fusión de `tasks.md` y los informes A2/B2/C2/D2; no hay evidencia nueva para reabrir C1-01/B1-01/A1-03. Scope3 recibido: exit0, 174 cambios, cero fuera de alcance/criterios alterados/avisos; no ejecutado por esta lente.

| Comprobación | Resultado y evidencia |
|---|---|
| Callback entre requeue y nuevo inicio | `DeliveryLifecycle.php:81` consulta feedback de cualquier intento de esa entrega y persiste `ambiguous/provider_feedback_conflict` antes de retornar null. `AccountMailTransport.php:76` toma primero el mutex de cuenta y `:85` llama antes de cuota, creación del mensaje o IO. `ProviderWebhookService.php:22` comparte ese mutex. No se identifica una ventana que permita ignorar un callback ya persistido antes del inicio durable. |
| Callback del intento anterior durante IO actual | `DeliveryLifecycle.php:187` comprueba por cuenta+delivery, no sólo el mensaje actual. El resultado retryable se convierte en un resultado incierto distinguible y terminal; no genera otro requeue. El callback posterior al inicio no cancela IO ya iniciado, límite declarado y conservado. |
| Finalización del consumidor cuando no se inicia | Seguido `DeliveryConsumer.php:25` → `DeliveryLifecycle.php:286`: `readyToSettle` reconoce el terminal ambiguo, de modo que el retorno null no se confunde con falta de resultado durable ni induce reenvío del consumidor. |
| Nuevas regresiones | `MailProviderDeliveryTest.php:177` exige cero segundo IO, attempts1, diagnóstico y ausencia de claim posterior. `:200` inyecta feedback del primer mensaje durante el segundo IO SMTP y exige estado incierto/ambiguo, attempts2 y ningún nuevo claim. No sustituyen ni eliminan el caso de feedback anticipado o el rate limit normal. |
| Ajustes de oráculos E2E | Comparación directa con `testing/qa-1/sources/mail-providers.spec.mjs`: conserva escenarios y asserts. Rol checkbox mantiene la acción/garantía; espera de dashboard sincroniza el cambio de cuenta; selector de ambigüedad exige el texto exacto visible dentro de la lista; feedback exige etiqueta y fecha UTC en el párrafo real. No se eliminan verificaciones ni aumentan timeout/retries. |
| ActivityTest | `ActivityTest.php:159` pasa de9 a10 porque el sembrado recorre los10 estados, incluido api_accepted. Añade además una exigencia explícita de una fila api_accepted; mantiene total de campaña, unknown0, etiquetas y aislamiento de cuenta. No se identifica un fallo antes detectable que esta corrección deje de detectar. |

Evidencia existente inspeccionada, exclusivamente los tres `test-summary.json` seguros indicados:

| Tanda | Resultado del recibo |
|---|---|
| late-feedback-red-20261031T000919836240Z | exit1; 1 caso/6 aserciones/1 fallo; rojo conservado. |
| late-feedback-green-20261031T001013302259Z | exit0; 3 casos/38 aserciones; cero fallos/errores/omitidos; incluye feedback tardío, temprano y rate limit normal. |
| prior-feedback-green-20261031T001220176603Z | exit0; 1 caso/11 aserciones; cero fallos/errores/omitidos. |

No son ejecuciones propias ni se suman tandas solapadas. QA1 sigue rojo2/6; QA2 y pipeline SQL del coordinador pendientes al recibir el encargo. Esta revisión estática no acredita QA global ni proveedor real. No se ejecutaron pruebas, SQL/E2E, probes, inventarios de procesos o acciones externas; no se leyeron/hasharon secretos, entornos privados ni las rutas prohibidas. Única escritura: este informe.

```json
{"lens":"B","attempt":3,"status":"complete","verdict":"sin defectos","gaps":[],"counts":{"Critical":0,"Important":0,"Minor":0},"scope":"delta posterior al intento2","own_tests_executed":[],"global_qa_green":false,"report":"storage/providers-email-20261030/review-B3.md"}
```
