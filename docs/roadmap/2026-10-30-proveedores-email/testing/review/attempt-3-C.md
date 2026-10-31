# Revisión adversarial — lente C, intento 3 de 3

**Veredicto: sin hallazgos de seguridad nuevos o reabiertos.** Critical 0; Important 0; Minor 0 pendientes de esta lente. C1-01 conserva su estado corregido y verificado en el intento 2: no hay evidencia nueva que lo reabra.

Revisor de contexto fresco por subagente genérico, modelo real heredado; reviewer nativo no disponible. Se aplicaron `adversarial-review/SKILL.md` y el prompt literal C de custom-agents 2.3.0. Activación heredada: `.env.example`, OAuth y permisos. No es una auditoría completa ni un resultado de QA.

## Alcance y traspaso

Base heredada `74970e0af4c5e68bd3b8e139a0988a119208493e`, worktree actual. Se consultaron los informes `testing/review/attempt-2-{A,B,C,D}.md` y la fusión del intento 2 de `docs/roadmap/2026-10-30-proveedores-email/tasks.md`. Sólo se reevalúa lo corregido después de ese juicio y sus llamantes. Scope3 comunicado por el orquestador: exit 0, cero rutas fuera de alcance y cero criterios alterados; no se ejecutó de nuevo por esta lente.

Se leyó completo el delta productivo suministrado `storage/providers-email-20261030/review-3-production.patch`: únicamente cambia `DeliveryLifecycle`. También se leyeron las regresiones de feedback de `MailProviderDeliveryTest`, el diff de `ActivityTest` y el diff E2E contra la copia original de QA1. Las superficies previamente aprobadas de credenciales, canales y políticas no se reabren.

## Comprobaciones de seguridad del delta

| Comprobación | Resultado y evidencia |
|---|---|
| Feedback de otra cuenta no bloquea esta entrega | ✓ `app/Services/DeliveryLifecycle.php:221` exige simultáneamente `account_id` y `delivery_id` al buscar cualquier intento con feedback. La ampliación desde el mensaje actual a todos los intentos conserva el aislamiento de cuenta y no acepta identificadores provenientes del callback en esta consulta. |
| Un callback no autenticado no puede activar la nueva guarda | ✓ Se siguió el escritor de feedback: `app/Services/MailProviders/ProviderWebhookService.php:22` toma el mutex de cuenta, `:23` carga la conexión de esa cuenta/proveedor y `:24` revalida la autenticación antes de aplicar eventos. `:61`–`:71` exige cuenta, modo del proveedor, referencia de un mensaje iniciado y coincidencia del destinatario. La nueva guarda no abre otra entrada ni omite esa autenticación. |
| El bloqueo de un nuevo inicio es serializable con el callback | ✓ `app/Services/AccountMailTransport.php:75`–`:85` llama `beforeStart` dentro de la transacción que ya tomó el mismo mutex de cuenta. `DeliveryLifecycle.php:81` convierte la contradicción en resultado ambiguo antes del inicio durable, cuota y nuevo mensaje; no concede permisos ni alcanza el transporte por esa rama. `finish` toma el mutex en `:182` y consulta todos los intentos en `:187`, cubriendo feedback anterior recibido durante el I/O de un intento posterior. |
| El cambio no introduce un nuevo sink de inyección, secretos o red | ✓ `DeliveryLifecycle.php:219`–`:221` usa el constructor de consultas con valores enlazados. Los resultados y el diagnóstico de `:82`/`:189` son literales fijos. El delta no añade URL, ruta de archivo, deserialización, ejecución de comandos, secretos ni cambios de autenticación/sesión/autorización. No se identifica escenario explotable ni CWE nuevo que reportar. |

Los cambios E2E conservan el 403 de permisos revocados y las comprobaciones de aislamiento y ausencia de I/O adicional; ajustan selectores de checkbox/texto y esperan la navegación de cambio de cuenta. `ActivityTest` actualiza a diez estados y exige explícitamente una fila `api_accepted`. No se identifica una nueva vía de explotación derivada de esos cambios de pruebas.

## Evidencia consultada y límites

Se leyeron únicamente los resúmenes seguros indicados; son ejecuciones ajenas, no pruebas propias de esta lente:

| Resumen bajo `storage/providers-email-20261030/` | Resultado |
|---|---|
| `late-feedback-red-20261031T000919836240Z/test-summary.json` | exit 1, 1 caso/6 aserciones, 1 fallo: feedback después de requeue antes del siguiente inicio. |
| `late-feedback-green-20261031T001013302259Z/test-summary.json` | exit 0, 3 casos/38 aserciones, sin fallos/errores/omitidos: rate limit, feedback tardío y feedback temprano. |
| `prior-feedback-green-20261031T001220176603Z/test-summary.json` | exit 0, 1 caso/11 aserciones, sin fallos/errores/omitidos: callback del primer intento durante el I/O del segundo. |

La lectura de `tests/Feature/MailProviderDeliveryTest.php:177`, `:200` y `:228` confirma que se exige no iniciar una segunda llamada tras feedback tardío y no reprogramar tras feedback de un intento anterior durante el segundo. Los resúmenes focales no se presentan como QA global. QA1 fue comunicado rojo (2/6); QA2 y cualificación real/humana siguen pendientes.

Validación propia estática: no se ejecutaron SQL, E2E, sondas de red ni proveedores reales. No se inspeccionaron procesos ni PIDs, archivos privados/prohibidos, entornos, PEM, dumps o ZIP; no se calcularon sus hashes. Única escritura: este informe. No se modifican fuentes, ledger, knowledge ni sistemas externos.

```json
{"lens":"C","attempt":3,"status":"complete","verdict":"sin hallazgos","gaps":[],"open_counts":{"Critical":0,"Important":0,"Minor":0},"previous_gap":{"id":"C1-01","state":"corregido y verificado en intento 2; no reabierto"},"scope":"delta posterior a intento 2 y llamantes","own_tests_executed":[],"sql":false,"e2e":false,"network":false,"qa_global_approved":false,"report":"storage/providers-email-20261030/review-C3.md"}
```
