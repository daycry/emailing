# Aceptación técnica de T-10 — catálogo MCP completo

**T-10 aceptada técnicamente en laboratorio el 30 de octubre de 2026.** El catálogo implementado alcanza37 operaciones. La ampliación queda en10/14, con la fase de catálogo3/3; T-11–T-14 y el cierre global conservan todo su alcance. Root completa las puertas de revisión, QA y documentación descritas aquí antes de esta aceptación.

## Evidencia admitida

| Capa | Resultado propio | Procedencia |
| --- | --- | --- |
| Backend completo sin filtro | 485 IDs únicos exactos,13.537 aserciones; exit0; sin fallos, errores, skips ni avisos registrados | [Proyección del backend](backend-2026-10-30.json), una única tanda; PHP8.5.12 nativo |
| Navegador independiente | Nueve escenarios originales9/9, sin retries/flaky/omitidos | [Informe QA original](qa-2026-10-30/report/report.md), [PDF revisado](qa-2026-10-30/report/report.pdf), [gate original](qa-2026-10-30/runtime/original9/qa-gate.stdout.json) |
| Estabilidad independiente | E2E-09×3 separado, cada índice nativo0/1/2 pasó una vez; exit0 | [Gate original](qa-2026-10-30/runtime/stability09/qa-gate.stdout.json) y reporter nativo conservado |
| Empaquetado Git | Tres tests sobre el commit6ff7858 pasan; ocho hashes originales conservados | [Causa y regresión](packaging/report.md), [evidencia HEAD](packaging/evidence.json) |
| Revisión independiente | R1 detecta B-01; R2 lo corrige sin gaps; R3 A+B+C no encuentra defectos nuevos en empaquetado | [R1](review-1/report.md), [R2](review-2/report.md), [R3](review-3/report.md) |
| Documentación | Guía MCP e índice actualizados;74 enlaces locales válidos, sin ejecutar runtime | [Contrato y uso](../../../../../aplicacion/mcp-contrato.md), [índice](../../../../../README.md) |

El backend captura415 fuentes. Las dos tandas E2E conservan esos415 hashes y una única fuente añadida: el test Python de empaquetado, para416. No se cambió el runtime PHP de aplicación para arreglar Git y no se agregan tandas parciales para fabricar el resultado completo. Las reproducciones previas y las interrupciones mantienen sus resultados y fechas.

## Auditoría de los criterios aprobados

| Criterio de T-10 | Resultado y evidencia que lo prueba |
| --- | --- |
| 37 operaciones reales | `McpSendTest::test_all_thirty_seven_operations_publish_the_pinned_catalogue` y el catálogo registrado coinciden; comprobador estático37/76, sin fallos. `McpConnectionInfoTest` contiene3 casos/98 aserciones de información ligada a cuenta/grant y permisos vigentes. |
| Modalidades y destinatarios comunes; templates.read condicional | `SendingApiService::accept` y `acceptDelegated` convergen en `acceptSource`; `SendingPrincipal` exige templates.read según contenido. `McpSendTest` verifica defaults/plantilla/fingerprints, lectura actual del scope y lotes de100 destinatarios ordenados; `SendingApiTest` conserva31 casos/791 aserciones en la suite completa. |
| Un trabajo por cuenta/clave/payload entre API y MCP | `McpApiIdempotencyTest`:8 casos/4310 aserciones con procesos, barreras y conteos; prueba ganador API/MCP, conflicto, revocación que gana o espera y aislamiento de otra cuenta. E2E-09 verifica recibo y delivery_ids compartidos sin reatribuir origen. |
| Consentimiento, cuota, supresión y autorización antes del SMTP | `McpSendTest` verifica supresión tardía, retirada del consentimiento, cambio de remitente, permisos/credenciales vigentes y aislamiento. `McpPipelineTest`:2 casos/172 aserciones con broker real, cuota, supresión, retry, incertidumbre y Mailpit; no se toma mail.send por consentimiento del destinatario. |
| Estados distintos; transportes limitados a laboratorio | Backend de pipeline, `WorkerRecoveryTest`24/710 y `OutboxIntegrationTest`23/369 mantienen estados durables, ambigüedad y conteos atribuidos. E2E-09 muestra aceptación pendiente con cero intentos, sin afirmar publicación o entrega SMTP. Mailpit/log son los destinos habilitados. |
| Reutilizar T-18 sin recursos ficticios ni otro SMTP | El principal delegado usa el mismo servicio de aceptación, request/recipients, delivery y outbox; `McpPipelineTest` y las regresiones API prueban ese camino común. No se crea una API key, contacto o campaña como sustituto de autoridad MCP. |
| Correlación sin exponer bearer | `McpSendTest`24/564 verifica procedencia, tombstones, SQL y actividad/CSV. E2E-09 comprueba actor, cliente, grant, request y una entrega lógica en MCP, REST, web y CSV; capturas sólo de fixtures ficticios. |
| Concurrencia real y mensajes atribuidos | Los8 casos API/MCP y los2 del pipeline exigen cardinalidad de solicitudes/trabajo y Mailpit propio; la recuperación conserva un solo resultado durable. Las tres repeticiones de navegador complementan estas pruebas, sin sustituir las barreras SQL o el broker. |

Las cifras de cada clase son subconjuntos de la misma tanda485/13.537, no ejecuciones nuevas sumadas al total. Root conserva el juicio previo sobre las partes no modificadas y verifica las correcciones con las pruebas y fuentes admitidas.

## Revisión, limpieza y publicación

B-01 conserva su grado histórico Important: guardar el resultado tras retirar al actor mantiene el tombstone SQL, mientras cambiar de actor explícitamente sigue rechazado. PKG-01 también conserva grado Important: Git normalizaba siete JSON y rompía sus identidades; los atributos preservan los ocho inputs publicados sin relajar el guard. Ambos están corregidos y comprobados. R3 no activa D para el nuevo delta; el juicio de rendimiento de las rondas anteriores se conserva.

QA admite terminal, reporter y limpieza después de cada tanda, antes de iniciar la siguiente: callbacks cerrados, recibo de teardown correcto, conteos SQL propios y del pipeline a cero, identidad de pruebas, otras conexiones0/transacción0 y los tres puertos liberados con bind exclusivo. Root verifica de nuevo esos metadatos y los resultados nativos. No se leen ni publican raw de teardown, settlements o configuración privada para justificar la limpieza.

El [manifiesto de publicación QA](qa-2026-10-30/publication-manifest.json) conserva52 originales byte a byte, incluido el Markdown/PDF y25 PNGs. Un resumen de controles omite rutas locales de ejecutables que aparecían en el original privado; no cambia resultados ni hashes de sus streams. La revisión genérica de patrones no detectaba esas rutas, por lo que root aplica esa exclusión antes del commit. La inspección visual de QA y root comprende las capturas y las17 páginas del PDF. No equivale a aceptación humana de M-02.

El primer render del PDF agotó la espera de inactividad de red. El intento final sólo adapta la espera de render a DOM listo, imágenes decodificadas y fuentes listas, sin aumentar tiempos ni alterar E2E. Los dos intentos permanecen privados y atribuibles. Las referencias y estados pendientes de los informes originales describen el momento en que se emitieron; esta aceptación posterior y el [ledger canónico](../../../tasks.md) indican el estado actual.

## Límites y siguiente trabajo

Se acredita el laboratorio nativo con Google simulado, aplicación/SQL/navegador reales y cliente MCP de prueba. Las sondas Linux de clase PHP8.4.27 son distintas de la cualificación de una aplicación Docker completa. T-11 debe instalar y verificar perfiles, configuración persistente, reinicio, rotación y restauración; T-12 la regresión cruzada; T-13 la revisión consolidada; T-14 Google y clientes reales con M-01–M-04. No se solicitan secretos en chat ni se confunde ayuda CLI o configuración documental con una conexión ejecutada.

La curación de conocimiento se registra por separado con sus recibos o pendientes explícitos; QA y esta aceptación no aprueban doctrina. La retro y el cierre de la iniciativa siguen pendientes. Las comprobaciones del contenido público actual no certifican el historial Git, cuya incidencia conocida conserva su seguimiento sin reescritura no autorizada. T-19/T-20 de la plataforma principal siguen abiertos y el objetivo completo permanece activo.

Tokens, coste y horas IA reales son desconocidos. No se convierten duraciones de tests o reloj en consumo medido.
