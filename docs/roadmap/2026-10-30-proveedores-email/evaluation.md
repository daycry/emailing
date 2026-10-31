---
generacion:
  artefacto: docs/roadmap/2026-10-30-proveedores-email/evaluation.md
  inicio: "2026-10-30T20:40:53Z"
  fin: "2026-10-30T20:47:13Z"
  fuente: estimado
  tokens_reales: null
  eur: null
  horas_ia: null
  duracion: null
  duracion_reloj: "6m"
  ratio_usado: 300000.0
  ratio_origen: "explícito (300000)"
  nota: "estima tokens/horas a juicio y márcalo como estimado"
  tokens_procedencia: sin_medida
  tokens_cobertura: sin_medida
  transcripts_leidos: null
  transcripts_subagente: null
  transcripts_con_error: null
  coste_procedencia: sin_coste
  coste_cobertura: sin_coste
  coste_sin_precio: []
  tarifa_verificada_el: null
  avisos: ["carpeta de transcripciones no disponible"]
estimacion_generacion:
  tokens: {entrada: 18000, salida: 4500}
  horas_ia: 0.075
  duracion: "5m"
  metodo: Juicio para redactar spec y evaluación; 22500 tokens previstos divididos por el ratio no calibrado de 300000. No es consumo observado, no se duplica como ventana de la spec y no se usa la duración de reloj como horas IA.
---

# 2026-10-30-proveedores-email

> Evaluación de Brevo y SendGrid por SMTP y API sobre el transporte durable existente.

| | |
|---|---|
| **Fecha** | 2026-10-30 |
| **Estado** | completado |
| **Prioridad global** | Alta |
| **Solicitante** | Usuario del proyecto; nombre no requerido |
| **Spec** | [`spec.md`](spec.md) |
| **Plan** | [improvement-plan.md](improvement-plan.md), [tasks.md](tasks.md) y [test-plan.md](test-plan.md) |
| **Características evaluadas** | 6 |

## Cuadro de mando

| Métrica | Total estimado | Confianza |
|---|---|---|
| Esfuerzo humano equivalente | **124,8h** (104h base +20 %) | Media-baja; juicio técnico, sin calibración propia |
| Tiempo IA por ratio de tokens | **3,456h** (+0,864h de supervisión supuesta) | Baja; no promete duración de ejecución |
| Coste humano equivalente | **6240 € + tokens por verificar** | Baja; tarifa supuesta, no gasto comprometido |
| Tokens de implementación, revisión, QA y documentación | **1036800** con margen (864000 in /172800 out) | Baja; previsión, no telemetría |
| Cociente aritmético humano/(IA+supervisión) | **×28,89** | No calibrado; no es productividad observada |
| Características | **6** | Alta para alcance, no para duración |

## Resumen ejecutivo

El encargo comprende cuatro combinaciones de proveedor/canal y código limpio y robusto. Es viable sobre Laravel, Symfony SMTP y el cliente HTTP ya instalados; no necesita otro broker ni exige inicialmente un SDK de proveedor. La parte de mayor riesgo es conservar las garantías de inicio durable, incertidumbre y aislamiento al añadir feedback externo.

El presupuesto incluye configuración privada por cuenta, SMTP, API, webhooks autenticados, integración de estados/contratos y revisión/QA/documentación como partida propia. La autorización «adelante» permite continuar con todas las características sin una nueva pregunta de go. La prueba real con cuentas/dominios del operador se mantiene separada y no se acredita por simulaciones locales.

## Requerimientos recibidos

| ID | Característica | Requisito origen | ¿Claro? |
|---|---|---|---|
| C-01 | Configuración y credenciales por cuenta/proveedor | Spec: «Decisiones», «Configuración», CA-01–CA-03 | Sí; nombres de columnas se concretan en el plan. |
| C-02 | Brevo SMTP y SendGrid SMTP | Mensaje del usuario; spec CA-04/05/16 | Sí; perfiles y TLS se cotejan con documentación oficial. |
| C-03 | Brevo API y SendGrid API | Mensaje del usuario; spec CA-06–CA-08/16 | Sí; clasificación de errores requiere pruebas de contrato. |
| C-04 | Feedback autenticado e idempotente | Robustez pedida, propuesta previa aceptada; spec CA-09–CA-12 | Sí como alcance; formato exacto y rotación de claves se verifican en implementación. |
| C-05 | Integración durable y compatibilidad | «Limpio y robusto», reutilización del producto actual; spec CA-08/13/15 | Sí; incluye ambas ramas de `beforeStart` y representación API propia. |
| C-06 | UI, revisión, QA y operación | Flujo completo elegido; spec CA-14/17/18 | Sí; validación privada real documentada como pendiente del operador. |

No se conocen cuenta de proveedor, dominio, credenciales ni destinatario de prueba real. Son entradas privadas para la activación operativa, no una razón para omitir adaptadores o QA local. No hay deadline, presupuesto máximo ni tarifa personal aportados. El plan debe conservar estas incertidumbres sin pedir secretos por chat.

## Datos necesarios para una evaluación completa

- [x] Requerimientos y cuatro combinaciones explicitados por el usuario.
- [x] Alcance delimitado; API transaccional, sin replicar marketing/contactos/facturación del proveedor.
- [x] Criterios observables CA-01–CA-16 y criterios de calidad/documentación CA-17/18 escritos antes de construir.
- [x] Restricciones: laboratorio portable, infraestructura compartida, sin HA nueva; envío real requiere configuración privada.
- [x] Dependencias externas identificadas; contratos oficiales y cuentas privadas, sin presuponer disponibilidad de estas últimas.
- [x] Contexto técnico reconocido con rutas de transporte, ciclo de vida, ajustes, actividad y regresión.
- [x] Parámetros económicos declarados como supuestos; sin cotización o compromiso de gasto.

La evaluación es suficiente para planificar bajo estos supuestos. La aceptación operativa de proveedores reales tiene entradas pendientes explícitas y no se confunde con el go técnico.

## Supuestos económicos (ajustables)

Los importes son equivalentes de esfuerzo a tarifa supuesta. No son cargos, precios de Brevo/SendGrid, horas reales ni presupuesto contratado.

| Parámetro | Valor usado | Procedencia y límite |
|---|---|---|
| Tarifa de desarrollo | 50 €/h | Fallback de `agent-kits/shared/estimation-defaults.md`; no confirmada como tarifa del usuario. |
| Modelo ejecutado | Heredado por la sesión genérica | El frontmatter del rol propone Opus, pero la herramienta no acredita ese modelo nativo. No se le atribuye su precio. |
| Precio input/output | `p_in`, `p_out` €/1M; ⚠️ verificar | Sin tarifas fiables configuradas; no se inventa coste de tokens. |
| Tipo de cambio | No aplicado | No hay precio USD verificado que convertir. |
| Contingencia | +20 % una vez | Sobre horas y tokens base; las partidas de revisión/QA se presupuestan además de forma explícita. |
| Ratio tokens→hora IA | 300000 tok/h | Default no calibrado; no existe `docs/roadmap/CALIBRATION.md` en el ámbito comprobado. |
| Supervisión | 25 % de horas IA estimadas | Fallback, no trabajo humano observado. |
| FTE de referencia | 160h/mes | Disponible como supuesto; no se usa para dimensionar personal. |

`.claude/rates.json` no existe en el proyecto comprobado. No se leyó configuración global para inventar una tarifa. La consulta local de lecciones de estimación no devolvió entradas; la doctrina del plugin LES-001 y LES-006 aconseja separar revisión del margen y fijar la spec antes de implementar. No se reescribe memoria del proyecto.

## Evaluación por característica

### C-01 — Configuración y credenciales por cuenta/proveedor

- **Requisito origen**: spec, CA-01–CA-03.
- **Descripción**: Conservar credenciales separadas por proveedor y cuenta, ocultas y cifradas; seleccionar uno de los canales sin reutilizar claves ajenas. Habilitación de despliegue y remitente externo diferenciada del permiso de laboratorio.
- **Complejidad**: Media.
- **Esfuerzo base**: **12h**, confianza Media; 4h esquema/modelo y permisos, 4h configuración/validación, 4h regresiones de secretos y aislamiento.
- **Previsión IA base**: 80000 in /16000 out,96000 tokens; coste `0,08p_in +0,016p_out` €.
- **Coste humano base**: 12h ×50 = **600 €**, más tokens sin verificar.
- **Impacto**: `MailSetting`, `MailSettingsService`, configuración de aplicación y nueva persistencia por proveedor; migración compatible.
- **Dependencias**: Ninguna compra o servicio nuevo; contrato de configuración acordado con los adaptadores.
- **Riesgos**: Credenciales en snapshots/errores, clear accidental de otro proveedor o revalidación de permisos incompleta.
- **Incógnitas**: Nombres definitivos de modos, tabla y rotación de secretos. Las resolverá planner/implementer sin cambiar el alcance.

### C-02 — SMTP de ambos proveedores

- **Requisito origen**: encargo del usuario y spec CA-04/05/16.
- **Descripción**: Perfiles SMTP fijos con autenticación privada, TLS verificado y semántica observada de aceptación. Reutilizar `SmtpConnector` y su observador.
- **Complejidad**: Media.
- **Esfuerzo base**: **16h**, confianza Media; 4h perfiles/autenticación, 4h clasificación segura de resultados y 8h simulaciones TLS/negativos/pérdida de confirmación.
- **Previsión IA base**: 100000 in /20000 out,120000 tokens; coste `0,1p_in +0,02p_out` €.
- **Coste humano base**: **800 €**, más tokens sin verificar.
- **Impacto**: `SmtpConnector`, `ObservedSmtpTransport`, selección del transporte y fixtures SMTP.
- **Dependencias**: C-01; los mecanismos SMTP se contrastan con las fuentes del proveedor, diferenciando sus claves SMTP/API.
- **Riesgos**: Relajar TLS para facilitar tests, repetir un envío incierto o convertir un rechazo permanente en retry infinito.
- **Incógnitas**: La aceptación real de la cuenta del operador no se puede inferir del contrato o una fixture local.

### C-03 — API transaccional de ambos proveedores

- **Requisito origen**: encargo del usuario y spec CA-06–CA-08/16.
- **Descripción**: Dos adaptadores HTTP pequeños con endpoints fijos, payload explícito, autenticación privada y resultado normalizado. La aceptación API tendrá representación propia.
- **Complejidad**: Alta.
- **Esfuerzo base**: **20h**, confianza Media-baja; 8h payload/autenticación/adaptadores, 4h clasificación y límites, 8h pruebas de respuestas, timeout, redirects y ausencia de retry implícito.
- **Previsión IA base**: 140000 in /28000 out,168000 tokens; coste `0,14p_in +0,028p_out` €.
- **Coste humano base**: **1000 €**, más tokens sin verificar.
- **Impacto**: Cliente HTTP de Laravel, nuevos adaptadores/frontera de transporte y metadatos de intento.
- **Dependencias**: C-01 y contrato de resultados de C-05; no se presupone añadir SDK.
- **Riesgos**: Tratar timeout/5xx como prueba de no aceptación, seguir redirects con autorización o conservar cuerpos crudos.
- **Incógnitas**: Formas exactas de referencias/respuestas y clasificación de limitación temporal se fijarán contra contrato y pruebas; no se asume idempotencia universal del proveedor.

### C-04 — Webhooks autenticados, feedback y supresiones

- **Requisito origen**: robustez del encargo y spec CA-09–CA-12.
- **Descripción**: Autenticar antes de aplicar eventos, deduplicar y correlacionar un intento de la cuenta/proveedor correctos. Conservar feedback durable y aplicar supresiones por eventos pertinentes.
- **Complejidad**: Alta.
- **Esfuerzo base**: **24h**, confianza Media-baja; 6h autenticación/rotación, 6h identidad/dedupe, 6h orden/supresión y 6h pruebas de firma, replay y aislamiento.
- **Previsión IA base**: 160000 in /32000 out,192000 tokens; coste `0,16p_in +0,032p_out` €.
- **Coste humano base**: **1200 €**, más tokens sin verificar.
- **Impacto**: Receptor HTTP acotado, persistencia de eventos/referencias, servicio común de supresiones y actividad.
- **Dependencias**: C-01 y correlación introducida con C-02/C-03/C-05; endpoint y clave configurados privadamente por operador para prueba real.
- **Riesgos**: Firma verificada sobre JSON reserializado, confianza en `account_id` remoto, pérdida de claves al cambiar modo o dedupe con el `id` repetido del webhook Brevo.
- **Incógnitas**: Política de rotación y conservación de verificadores mientras llegan eventos pendientes; planner debe concretarla manteniendo aislamiento y revocación.

### C-05 — Integración durable y contratos compatibles

- **Requisito origen**: spec CA-08/13/15 y comportamiento existente del producto.
- **Descripción**: Integrar los adaptadores en campañas, API y MCP, revalidando modos en ambas ramas del inicio durable. Mantener cuotas, outbox, fencing, supresiones y procedencia; añadir aceptación API sin alterar el significado histórico SMTP.
- **Complejidad**: Alta por alcance transversal.
- **Esfuerzo base**: **14h**, confianza Media-baja; 6h ciclo de vida/captura de configuración, 4h estado/actividad/contratos y 4h regresión de fuentes y redelivery.
- **Previsión IA base**: 100000 in /20000 out,120000 tokens; coste `0,1p_in +0,02p_out` €.
- **Coste humano base**: **700 €**, más tokens sin verificar.
- **Impacto**: `AccountMailTransport`, `DeliveryLifecycle`, `CampaignService`, `SendingApiService`, `ActivityService` y contratos API/MCP pertinentes.
- **Dependencias**: C-01–C-03; define el resultado/correlación común que consume C-04.
- **Riesgos**: Adaptador correcto pero bloqueado por guardas antiguas; estado aceptado mal contado; sobrescritura tras pérdida de reserva; fallo de un contrato fijado por hash.
- **Incógnitas**: Adiciones exactas de esquemas/contadores; no se declara intocable un contrato si la ampliación lo necesita, pero se exigen compatibilidad, procedencia y pruebas del pin.

### C-06 — UI, revisión independiente, QA y documentación

- **Requisito origen**: workflow completo elegido y spec CA-14/17/18.
- **Descripción**: Formularios de proveedor/canal con secretos de escritura, resultados legibles, accesibilidad y guías de operación. Revisión fresca y QA de navegador/evidencias tras integración.
- **Complejidad**: Media-alta.
- **Esfuerzo base**: **18h**, confianza Media-baja; 8h revisión/correcciones, 6h UI/QA con fixtures y 4h documentación/cierre técnico. La revisión tiene una partida visible, no se oculta en la contingencia.
- **Previsión IA base**: 140000 in /28000 out,168000 tokens; coste `0,14p_in +0,028p_out` €.
- **Coste humano base**: **900 €**, más tokens sin verificar.
- **Impacto**: `MailSettings`, vistas de ajustes/plantillas/actividad, tests UI y docs de transporte/proveedores.
- **Dependencias**: C-01–C-05; revisión/QA sobre una selección congelada y entornos de laboratorio propios.
- **Riesgos**: UI que devuelva claves guardadas, textos que llamen entrega a aceptación, atribuir pruebas simuladas a proveedores reales o ignorar regresión del modo Mailpit/log.
- **Incógnitas**: Disponibilidad del operador para la validación real y retro final; se conservan pendientes sin inventar aceptación humana.

## Comparativa

| ID | Característica | Complejidad | Horas base | Coste humano base | Tokens base | Prioridad | Confianza |
|---|---|---|---:|---:|---:|---|---|
| C-01 | Configuración por cuenta | Media | 12 | 600 € | 96000 | Alta | Media |
| C-02 | Dos transportes SMTP | Media | 16 | 800 € | 120000 | Alta | Media |
| C-03 | Dos transportes API | Alta | 20 | 1000 € | 168000 | Alta | Media-baja |
| C-04 | Webhooks/feedback | Alta | 24 | 1200 € | 192000 | Alta | Media-baja |
| C-05 | Integración durable/contratos | Alta | 14 | 700 € | 120000 | Alta | Media-baja |
| C-06 | UI/revisión/QA/docs | Media-alta | 18 | 900 € | 168000 | Alta | Media-baja |
| | **Total base** | | **104** | **5200 €** | **864000** | | |

Cada cifra es juicio técnico fundamentado en los módulos observados. El margen se aplica únicamente en el total siguiente, no de nuevo por tarea. Como sensibilidad no contractual, una variación del esfuerzo base entre78 y156h daría93,6–187,2h con el mismo margen; no es una medición estadística.

## Presupuesto total

| Concepto | Cálculo | Importe |
|---|---|---|
| Desarrollo/revisión/QA/docs, humano equivalente base | 104h ×50 €/h | 5200 € |
| Contingencia humana | 20 % ×5200 € | 1040 € |
| Tokens input previstos, con margen | 720000 ×1,2 =864000; /1M ×`p_in` | `0,864p_in` €; verificar |
| Tokens output previstos, con margen | 144000 ×1,2 =172800; /1M ×`p_out` | `0,1728p_out` €; verificar |
| **Total estimado con margen** | **124,8h;1036800 tokens** | **6240 € +0,864p_in +0,1728p_out** |

El coste de tokens se presenta como previsión separada. No se suma una segunda vez a una tarifa de agente ni representa coste real ya consumido. Los cargos del proveedor de correo y su plan comercial quedan fuera; no se ha seleccionado ni comprado un plan.

## Productividad IA (humano vs. IA)

| KPI aritmético, no observado | Valor con margen aplicado una vez |
|---|---|
| Horas humanas equivalentes | 124,8h (104h base) |
| Horas IA por ratio no calibrado | 3,456h (2,88h base;1036800/300000) |
| Supervisión humana supuesta | 0,864h (25 %) |
| Horas IA +supervisión supuestas | 4,32h |
| Diferencia aritmética frente al equivalente humano | 120,48h |
| Cociente humano/(IA+supervisión) | ×28,89 |
| Diferencia porcentual aritmética | 96,54 % |

Estos cocientes proceden del default tokens/hora y del presupuesto de tokens a juicio. No están calibrados contra este proyecto, no predicen la duración de la sesión, no incluyen esperas humanas como trabajo IA y no demuestran ahorro económico real. Los costes y tokens observados permanecen null en la medición de generación.

## Recomendación

- **Veredicto:** **go técnico** para C-01–C-06. El usuario ya autorizó implementación; la prueba privada real conserva sus prerrequisitos y evidencia propios.
- **Ganancia inicial:** configuración aislada y perfiles SMTP reutilizando el conector observado; mantener log/Mailpit disponibles durante toda la ampliación.
- **Mayor coste/riesgo:** C-04 y C-05, por autenticación de eventos, orden/supresión e integración transversal de estados.
- **Orden sugerido de dependencias:** C-01 → contrato común de C-05 → C-02/C-03 → C-04 → integración final de C-05 → C-06. Es una prioridad/dependencia, no un plan de ejecución ni sus tareas.
- **Adopción recomendada:** componer clientes instalados y adaptadores propios pequeños. Una dependencia nueva necesita comparación de mantenimiento/licencia/compatibilidad antes de adoptarse; no se aprueba un SDK por popularidad.
- **Fuera de alcance:** alta de cuentas, compras, DNS automático, campañas de marketing del proveedor y supuestos de HA. El trabajo no cierra otras iniciativas.

## Riesgos transversales

| Riesgo | Probabilidad | Impacto | Mitigación evaluada |
|---|---|---|---|
| Duplicar correo por retry o failover tras aceptación incierta | Media | Alto | Resultado ambiguo durable, ningún retry HTTP implícito, misma reserva/correlación y redelivery de trabajo sin doble envío. |
| Filtrar secretos o cruzar cuentas | Media | Alto | Cifrado/hidden, escritura sin devolución de claves, mutex/permisos y pruebas negativas de cuenta y logs. |
| Tratar aceptación como entrega | Alta sin adaptación de estados | Alto | Aceptación API propia, feedback separado y contadores/UI/contratos explícitos. |
| Webhook falso/repetido/ajeno | Media | Alto | Autenticación sobre representación original, correlación local opaca, dedupe transaccional y supresiones monotónicas. |
| Configuración modificada durante un intento | Media | Alto | Fijar proveedor/canal/configuración al inicio durable y conservar referencias/verificadores de eventos pendientes. |
| Romper el contrato MCP fijado por hash o regresiones de entrega | Media | Alto | Extensiones aditivas documentadas, actualizar pins si corresponde y ejecutar pruebas de contratos/orígenes/colas. |
| Prueba real imposible por claves/DNS pendientes | No medida | Medio | QA local completa, guía de activación y recorrido privado pendiente sin falsos verdes externos. |
| Coste estimado muy distinto al observado | Alta | Medio | Registrar consumo real cuando haya fuente, revisión como partida propia y confianza baja de tokens/ratio. |

## Siguiente paso

Handoff a **planner** para C-01–C-06 sobre esta misma carpeta. Debe heredar104h y864000 tokens base,50 €/h supuesto y +20 % global una vez, sin reestimar esas cifras silenciosamente; dividir tareas y registrar dependencias, archivos, capacidades, tipo de cambio y primera comprobación observable. Necesita `improvement-plan.md`, `tasks.md` canónico y `test-plan.md` con CA-01–CA-16 trazados a Given/When/Then y escenarios de UI.

El handoff original de evaluator no creaba plan. Planner añadió después sus [artefactos](improvement-plan.md) y backlinks, heredando las cifras sin reestimarlas. La autorización conversacional permite proseguir sin otra pregunta de go/OK. La spec está aprobada y esta evaluación completada por esa autorización; crear el plan no acredita implementación ni validación externa.

Capacidades heredadas sin avisos: `api-contract`, `backend-practices`, `delivery-practices`, `frontend-quality`, `stack-practices`, `unit-tests`. PHP se detectó en manifiestos; no hay extensiones de proyecto seleccionadas. Perfil normal2.3, TDD/worktree/subagentes de implementación desactivados y revisión fresca/QA obligatorias. El despacho genérico no acredita el tipo/modelo nativo del plugin.

## Changelog de evaluación

- 2026-10-30: spec previa, reconocimiento de los guardas y estados de envío y evaluación de seis características con cifra base/margen, incertidumbres privadas y autorización de ejecución conservados.


## Adenda de alcance documental - 31oct2026

El usuario autoriza manual completo Markdown/PDF no tecnico, instalacion desde cero y cierre de huecos en docs antes de commits/push. C07/T07 anade12h/160000tokens referenciales (baja confianza) a la linea base104h/864000tokens; total116h/1024000tokens, con margen20%139.2h/1228800tokens/6960EUR equivalentes+tokens por verificar. Se reutilizan Markdown/Chrome y el workflow documental existente; no modifica arquitectura, proveedores ni runtime. GO documental por encargo explicito; original18CA intactos. No medidas reales, cambios de tarifa ni gasto observado.
