# Revisión adversarial — lente D — intento 2 de 3

**Veredicto: sin hallazgos de rendimiento en las correcciones.** Critical: 0; Important: 0; Minor: 0.

## Contrato y traspaso

Revisor independiente de contexto fresco, subagente genérico real con modelo heredado; el reviewer nativo no está disponible. Se aplicaron `adversarial-review` 2.3.0 y el prompt literal D de `references/lens-prompts.md`: sólo degradación de rendimiento introducida o reabierta, con escenario de carga y orden de magnitud; sin microoptimizaciones ni estilo.

Base recibida `74970e0af4c5e68bd3b8e139a0988a119208493e` contra worktree, incluidos nuevos públicos. Puerta comunicada exit 0, 116 rutas y cero fuera de alcance/criterios alterados. No se ha repetido la puerta ni se presenta como ejecución propia. Activación D heredada del intento 1: migraciones y trabajo de callbacks/worker.

Leídos los cuatro informes completos `docs/roadmap/2026-10-30-proveedores-email/testing/review/attempt-1-{A,B,C,D}.md` y la fusión/evidencia en `tasks.md:322`–342. Traspaso preservado: D1 sin hallazgos; C1-01/A1-01 TLS, B1-01/A1-02 AUTH y A1-03 documental son asuntos de sus lentes, con correcciones remitidas a intento 2. Esta lente no reabre lo aprobado/rebatido ni vuelve a auditar webhooks, índices o consultas productivas sin cambios posteriores comunicados. Se reevalúan el coste y las posibles regresiones de las correcciones SMTP, fecha HTTP, diagnóstico/etiquetas y probes.

Carga de referencia: 100–200 emails/día, crecimiento a 5–10 workers, RabbitMQ compartido y laboratorio sin HA. No se ha supuesto un benchmark de ese entorno.

## Comprobaciones de las correcciones

| Criterio D | Resultado | Evidencia y carga examinada |
|---|---|---|
| Estado por comando SMTP | ✓ Sin hallazgo | `app/Services/ObservedSmtpTransport.php:20`, `:43`, `:59`, `:79` y `:85` mantienen/limpian un único array de negociación, sin historial creciente, SQL ni E/S nueva. El trabajo adicional es constante por comando; no añade comandos ni recorridos sobre mensajes. Con 200 mensajes/día y 5–10 workers no aparece un multiplicador de consultas o de conexiones por esta corrección. El heartbeat de `:47` y el observador de `:89` siguen siendo por comando; `SmtpProtocolObserver.php:8` no persiste ni realiza E/S. |
| ESMTP obligatorio y cierre | ✓ Sin hallazgo | `ObservedSmtpTransport.php:70` aborta EHLO fallido mediante `SmtpPolicyViolation`; `SmtpConnector.php:44` conserva la excepción y `ProviderSmtpTransport.php:31` produce resultado definitivo. No añade negociación, sleep ni bucle; elimina la continuación insegura. El cierre se conserva en `SmtpConnector.php:61`–67, con el timeout de operación ya acotado a 5 s en `:31`. No se identifica una nueva espera por mensaje introducida por la guarda. |
| Reintentos de AUTH/política TLS | ✓ Sin hallazgo | `SmtpConnector.php:47` y `:53` recuperan el negativo observado; `ProviderSmtpTransport.php:33`–35 sólo reintenta 4xx. `DeliveryLifecycle.php:187`–205 termina un rechazo definitivo y difiere los temporales mediante disponibilidad durable. Para 200 entregas con credencial 535 inválida y máximo configurado 20, la corrección evita hasta 4000 comienzos en favor de 200; es un límite estático del escenario, no una medición. No añade retry de transporte ni espera ocupando el worker. |
| Mutex e I/O del camino corregido | ✓ Sin hallazgo | Se siguió el llamante `AccountMailTransport.php:136`, `:158`, `:186`–194 hasta `DeliveryLifecycle.php:172`. La transacción inicial devuelve antes de realizar SMTP; la finalización abre su propia transacción después del resultado. Las nuevas ramas no introducen red dentro del mutex ni mantienen el mutex durante el handshake con 5–10 workers. No reabren el resultado D1 sobre callbacks. |
| Fecha HTTP y rate limit | ✓ Sin hallazgo | `ProviderApiTransport.php:24` reemplaza la constante de formato; `:142`–157 conserva parsing una vez por rechazo, entrada máxima de 128 caracteres y retraso 1–86400 s. `DeliveryLifecycle.php:189` conserva máximo dos intentos de 429 y `:198`–199 difiere sin sleep. Incluso si las 200 entregas iniciales reciben 429, este cambio no aumenta el límite de 400 comienzos totales para ese escenario. |
| Diagnóstico, etiquetas, contratos y pins | ✓ Sin hallazgo | `ActivityService.php:21` añade una entrada al mapa de diagnósticos; `MailResult.php:9` y `:27` resuelven etiquetas mediante match sobre atributos ya cargados. No hay consultas, red ni carga de relaciones por etiqueta. La corrección del aviso no cambia el coste de una página. Cambios de contratos/pins no añaden trabajo al camino productivo SMTP/API. No se reevalúan las consultas de actividad ya juzgadas en D1. |
| Probes y regresiones nuevas | ✓ Sin hallazgo productivo | `tests/support/mail-provider-http-probe.php:32`–39 recoge un único entero de CURL en `on_stats` y conserva el callback anterior. Sólo pertenece al arnés de pruebas. `ProviderSmtpWireTest.php:67`–71 añade cinco sesiones finitas; el test de worker `MailProviderDeliveryTest.php:261`–281 recorre dos rechazos y verifica una generación/un evento ready, sin requeue. No añade carga al worker de producción. |

## Evidencia y límites

Validación propia exclusivamente estática; no se ejecutaron pruebas, SQL, E2E, proveedores reales, inventarios de procesos/contenedores ni operaciones sobre sus PIDs. Única escritura: este informe. No se leyeron ni calcularon hashes de archivos privados/prohibidos, entornos, solicitudes privadas, PEM privados, dumps o ZIP. Se consultaron únicamente resúmenes/recibos públicos permitidos para resultados de ejecución.

- `storage/providers-email-20261030/fix1-unit-final-20261030T232930242625Z/test-summary.json`: exit 0, 90 casos/1045 aserciones, cero fallos/errores/omitidos. El recibo público declara terminal_exit 0 y 506 fuentes estables. Los identificadores y código incluyen 13 sesiones SMTP y 8 HTTPS; no son mediciones de throughput.
- `storage/providers-email-20261030/fix1-lab-and-smtp-worker-20261030T232929000982Z/test-summary.json`: **exit 1**, 12 casos/214 aserciones y un fallo en aviso de incertidumbre. El test de rechazo definitivo SMTP no figura entre los problem_cases. No se presenta la tanda como verde.
- `storage/providers-email-20261030/fix1-legacy-notice-20261030T233512752157Z/test-summary.json`: exit 0, 1 caso/8 aserciones, sin fallos/errores/omitidos. Es una verificación focal posterior; no sustituye la regresión completa.

La regresión final de 16 casos y QA de navegador estaban pendientes/en ejecución al recibir el encargo; no se les atribuye resultado global. La revisión no acredita percentiles, saturación de RabbitMQ, EXPLAIN ni capacidad de miles de mensajes diarios. Persisten los límites D1: sin benchmark de 5–10 workers ni coste medido del lote de 1000 referencias distintas. No hay evidencia nueva que justifique reabrirlos como hallazgo.

```json
{
  "lens": "D",
  "attempt": 2,
  "status": "complete",
  "verdict": "sin hallazgos",
  "gaps": [],
  "counts": {"Critical": 0, "Important": 0, "Minor": 0},
  "base": "74970e0af4c5e68bd3b8e139a0988a119208493e",
  "target": "worktree including public untracked files",
  "scope": "corrections after attempt 1; approved findings preserved",
  "reviewer": "generic inherited fallback; native reviewer unavailable",
  "own_tests_executed": [],
  "benchmark_performed": false,
  "global_qa_approved": false,
  "report": "storage/providers-email-20261030/review-D2.md"
}
```
