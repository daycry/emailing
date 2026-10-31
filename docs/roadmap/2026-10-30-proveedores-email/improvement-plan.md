---
design: n/a
test-plan: test-plan.md
generacion:
  inicio: "2026-10-30T20:49:26Z"
  fin: "2026-10-30T20:55:57Z"
  fuente: estimado
  tokens_reales: null
  eur: null
  horas_ia: null
  duracion: null
  duracion_reloj: "7m"
  ratio_usado: 300000
  ratio_origen: "default no calibrado (300000)"
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
  tokens: { entrada: 40000, salida: 18000 }
  horas_ia: 0.193333
  metodo: "Juicio para plan, ledger y test-plan en una ventana;58000 tokens divididos por ratio no calibrado. No es consumo observado ni tiempo IA derivado del reloj."
---

# 2026-10-30-proveedores-email

> Integrar Brevo y SendGrid por SMTP y API en el flujo durable de la aplicación.

| | |
|---|---|
| **Fecha** | 2026-10-30 |
| **Estado** | en-progreso |
| **Tipo** | Nueva funcionalidad |
| **Prioridad** | Alta |
| **Solicitante** | Usuario del proyecto |
| **Responsable** | Orquestador e implementer; revisión y QA independientes |
| **Spec** | [spec.md](spec.md), aprobada |
| **Evaluación** | [evaluation.md](evaluation.md), completada; go C-01–C-06 |
| **Diseño** | n/a; decisiones de la spec y encargo autorizado, sin paso architect adicional |

## Cuadro de mando

| Métrica | Estimado | Real | Confianza |
|---|---|---|---|
| Tiempo humano equivalente | **139,2h**;116h base +20 %; incluye C-07 | Desconocido | Media-baja |
| Tiempo IA por ratio | **4,096h**, más1,024h de supervisión | Desconocido | Baja; ratio no calibrado |
| Coste humano equivalente | **6960 €**, más tokens por verificar | Desconocido | Tarifa supuesta |
| Tokens IA | **1228800**;1024000 base; incluye C-07 | Desconocido | Baja; juicio técnico |
| Multiplicador aritmético | ×27,19 frente a IA+supervisión estimadas | No medido | No predice duración ni ahorro real |
| Tareas | **7** | **5** hechas | Alcance explícito |

La autorización «adelante con los proveedores» conserva el flujo completo ya
elegido. No hace falta otra pregunta de go/OK para empezar. La implementación y las pruebas locales se acreditan en el ledger y sus originales. La conexión real a proveedores conserva M-01 pendiente; las tablas económicas siguientes conservan la línea base C-01–C-06 y la ampliación C-07 se detalla al final.

## Estimación por fase

Todas las filas son base. El margen se aplica una sola vez al total.

| Fase | Partidas heredadas | Horas | Tokens in/out | Coste humano supuesto |
|---|---|---|---|---|
| 1 — Configuración y contrato común | C-01 y primera parte C-05 | 16h | 113333/22667 | 800 € |
| 2 — Transportes y feedback | C-02, C-03 y C-04 | 60h | 400000/80000 | 3000 € |
| 3 — Integración, UI y validación | Resto C-05 y C-06 | 28h | 206667/41333 | 1400 € |
| **Total base** | C-01–C-06 | **104h** | **720000/144000** | **5200 €** |
| **Total con margen20 %** | Una sola aplicación | **124,8h** | **864000/172800** | **6240 €** |

C-05 se divide sin reestimar: T-02 prepara contrato/frontera común con4h y40000
tokens; T-06 integra y prueba el conjunto con10h y80000. T-03/T-04 dependen del
contrato común, no de una integración final aún inexistente. Así se evita una
dependencia circular conservando exactamente14h y120000 tokens de C-05.

## Presupuesto económico

### Supuestos

| Parámetro | Valor | Procedencia/límite |
|---|---|---|
| Tarifa | 50 €/h | Fallback heredado de la evaluación, no tarifa personal confirmada |
| Modelo | Heredado por la sesión real | El rol genérico no acredita Sonnet/Opus nativos |
| Precio input/output | `p_in`/`p_out` €/millón; verificar | No hay precio fiable confirmado |
| Cambio USD/EUR | No aplicado | No se convierte una tarifa desconocida |
| Contingencia | 20 % | Horas y tokens base una vez |
| Ratio tokens/hora IA | 300000 | Default sin calibración de este proyecto |
| Supervisión | 25 % de horas IA | Supuesto, no trabajo observado |

### Desglose

| Concepto | Cálculo | Importe |
|---|---|---|
| Equivalente humano base | 104h×50 €/h | 5200 € |
| Contingencia | 20 % de5200 € | 1040 € |
| Tokens con margen | 864000in/172800out | `0,864p_in +0,1728p_out` € |
| **Total supuesto** | 124,8h y1036800 tokens | **6240 € + tokens por verificar** |

No incluye compras, cargos de envío o planes comerciales de proveedores.
Los8h base de revisión/corrección incluidos en C-06 son partida explícita; no
se sustituyen por el margen ni se añaden de nuevo fuera de los104h.

## Previsión de tokens

| Fase | Input | Output | Total base |
|---|---|---|---|
| 1 | 113333 | 22667 | 136000 |
| 2 | 400000 | 80000 | 480000 |
| 3 | 206667 | 41333 | 248000 |
| **Total** | **720000** | **144000** | **864000** |

El método hereda el juicio técnico por característica de la evaluación. El
reparto entero de C-05 conserva los totales in/out, con redondeo entre tareas.
La medición de generación de estos documentos es una ventana compartida y no
forma parte de esa previsión de implementación.

## Productividad IA

| KPI | Supuesto con margen |
|---|---|
| Equivalente humano | 124,8h |
| IA | 3,456h |
| Supervisión | 0,864h |
| IA+supervisión | 4,32h |
| Diferencia aritmética | 120,48h;96,54 % |
| Multiplicador | ×28,89 |

Son cocientes del presupuesto por un ratio no calibrado. No predicen el reloj de
la sesión, productividad observada, ahorro económico ni gasto real.

## Resumen ejecutivo

Una cuenta podrá elegir Brevo o SendGrid por SMTP o API, manteniendo log y
Mailpit. Los cuatro modos usarán el mismo inicio durable, cuotas, supresiones,
outbox y fencing. El feedback autenticado actualizará su propio estado sin
convertir una aceptación del proveedor en una entrega confirmada ni repetir un
envío de resultado incierto.

### Objetivos

- Ofrecer las cuatro combinaciones y credenciales cifradas independientes por cuenta/proveedor.
- Bloquear salida no habilitada antes de red y preservar los modos de laboratorio.
- Correlacionar y deduplicar entregas/rebotes/quejas sin cruzar cuentas ni perder procedencia.
- Entregar código revisado, QA local reproducible y guías con límites operativos explícitos.

## Datos necesarios

- [x] Encargo explícito, seis características y18 criterios aprobados.
- [x] Stack y conectores actuales identificados; sin necesidad demostrada de otro SDK.
- [x] Perfil de laboratorio portable e infraestructura compartida existentes.
- [x] Política de pruebas locales y SQL exclusiva `emailing_testing` definida.
- [x] Costes y consumo desconocidos separados de las estimaciones.
- [ ] Cuentas/claves, dominio/remitentes y destinatario autorizado para prueba externa real.
- [ ] Validación privada del operador y retro final cuando corresponda.

Las dos entradas pendientes pertenecen a la activación/aceptación operativa;
no bloquean escribir el código, verificar contratos o aceptar QA local. No se
solicitan secretos por chat ni se conectan proveedores al guardar ajustes.

## Análisis de impacto

| Zona | Cambio esperado |
|---|---|
| `MailSettingsService`, modelos y nueva migración | Perfil por cuenta/proveedor; modo validado y autorización externa separada |
| `AccountMailTransport`, `SmtpConnector`, `ObservedSmtpTransport` | Despacho acotado, snapshot de configuración y resultado seguro |
| `DeliveryLifecycle`, `SendingApiService`, `ActivityService`, `Mcp/BusinessDto` | Guardas de ambas ramas, aceptación API y proyecciones compatibles |
| `app/Services/MailProviders/**`, receptor y middleware | Adaptadores, autenticación de eventos, límites, dedupe y supresiones |
| Livewire/vistas | Secretos de escritura, cuatro modos, errores/feedback y teclado |
| `docs/api/**` y verificadores/pins pertinentes | Contratos antes del código, cambios aditivos y procedencia verificable |

## Cambios arquitectónicos

Se adopta el diseño autorizado de la spec. La configuración externa vive por
`(account_id,provider)`, con credenciales cifradas y ocultas. El modo identifica
`brevo_smtp`, `brevo_api`, `sendgrid_smtp` o `sendgrid_api`; `log` y `mailpit`
conservan su comportamiento. Un flag del despliegue cerrado por defecto, la
habilitación por cuenta y la autorización externa del remitente se comprueban
antes de iniciar IO. Una autorización interna no prueba SPF/DKIM/DMARC.

El mutex de cuenta protege selección de proveedor/configuración, cuota e inicio
durable. La referencia opaca del intento existe antes de IO, para admitir un
webhook que llegue antes de finalizar la llamada. SMTP/API se ejecutan fuera de
locks con TLS, hosts/endpoints fijos, presupuestos acotados y sin retry oculto.
Sólo una negativa transitoria inequívoca permite reprogramar con límite y
`Retry-After`; timeout/5xx ambiguo no provoca failover ni reenvío ciego.

Los callbacks SendGrid verifican firma ECDSA del timestamp+cuerpo original.
Brevo usa Bearer privado sobre TLS. El `id` de webhook Brevo no identifica por sí
solo un evento. Dedupe, scoping de intento/proveedor/cuenta y precedencia de
feedback se prueban explícitamente. Una queja, baja o rebote permanente aplica
supresión local; un evento benigno tardío no la levanta.

La configuración/credenciales de un proveedor se conservan al elegir otro, para
no mezclar claves y poder autenticar eventos en vuelo. La rotación/revocación
del verificador requiere política explícita y tests: no se promete aceptar una
clave retirada ni se pierde feedback por un simple cambio de modo.

## Archivos a crear/modificar

Los campos `Archivos` del [ledger](tasks.md) son el alcance canónico por tarea.
Los patrones permiten concretar nombres pequeños durante implementación,
sin incorporar vendor, configuración privada o instrumentación temporal.

| Grupo | Acción | Propósito |
|---|---|---|
| Modelos/servicios de proveedor, soporte y migraciones nuevas | Crear | Configuración, resultados, correlación y eventos |
| Servicios de envío, lifecycle, actividad/API/MCP | Modificar | Integrar los cuatro modos sin otro pipeline |
| Rutas/middleware/controlador de callbacks | Crear/modificar | Límite de cuerpo, autenticación y recepción idempotente |
| Livewire y vistas de ajustes/actividad/plantillas | Modificar | Operación privada y feedback legible |
| Contratos API/MCP, fixtures/pins nuevos pertinentes | Ampliar | Representación API propia y compatibilidad documentada |
| Tests Unit/Feature/Fixtures/E2E/support | Crear/ampliar | Contratos, carreras, permisos, TLS y navegación |
| Guías, índices y evidencia de esta iniciativa | Crear/actualizar | Operación, revisión y QA reproducibles |

## Dependencias y prerequisitos

T-01 configura persistencia/permisos; T-02 fija contrato/resultados comunes.
T-03/T-04 implementan SMTP/API; T-05 recibe sus eventos; T-06 termina la
integración y UI, revisión, QA y documentación. No hay dependencia circular:
la primera parte C-05 define el contrato antes de los adaptadores y la restante
comprueba la integración después. No se añade infraestructura ni se cierran
T12–T14 de MCP o T19/T20 de la plataforma principal.

## Criterios de aceptación global

Los18 criterios de la [spec](spec.md#criterios-de-aceptación) se conservan
íntegros en el mapa del [test-plan](test-plan.md); los checks de cada tarea
declaran sus comportamientos observables. Se exige revisión fresca sin
Critical/Important pendientes, QA local verde y documentación con límites.
El cierre global de la iniciativa conserva la puerta de retro del workflow;
no se inventa validación humana ni ejecución del proveedor real.

## Riesgos y mitigaciones

| Riesgo | Mitigación y primera evidencia |
|---|---|
| Secreto visible o clave ajena reutilizada | Test nuevo rojo de aislamiento/serialización antes de configurar el modelo |
| Cuatro adaptadores que no pasan las guardas antiguas | Regresión de ambas ramas de `beforeStart` y envío por campaña/API/MCP |
| Timeout interpretado como rechazo seguro | Simular aceptación sin confirmación; ninguna segunda llamada automática |
| Callback anticipado o fuera de orden | Correlación persistida antes de IO y pruebas de carrera/precedencia |
| Eventos falsificados o duplicados | Firma/Bearer antes de efectos, límites y unicidad transaccional |
| Contrato fijado alterado accidentalmente | Diff OpenAPI antes del código, pins/fixtures versionados y validación final |
| Callback real/clave del operador en Git | Fixtures ficticias, selección explícita de publicación y controles sin leer secretos |
| Cambios DDL contra datos operativos | Guarda real de base/usuario y ensayo exclusivamente en testing |

## Métricas de éxito

| Métrica | Objetivo verificable |
|---|---|
| Combinaciones implementadas | 4/4 con aceptación/rechazo/incertidumbre probados localmente |
| Escenarios automáticos de navegador | 6/6 originales;sin fallos/flaky/omitidos/interrumpidos |
| CA trazados | 18/18;16 GWT automáticos y2 de calidad/documentación |
| Aislamiento | 0 lecturas/efectos de otra cuenta en pruebas negativas |
| Reenvío tras incertidumbre | 0 automático |
| Efectos de evento repetido | 1 por identidad de evento correlacionada |
| Cobertura de código | Sin porcentaje inventado; no existe umbral configurado |
| Proveedor real | Pendiente de prueba privada propia; no cuenta como QA local |

## Changelog del plan

| Fecha | Cambio | Motivo y efecto |
|---|---|---|
| 2026-10-30 | Plan inicial: seis tareas y tres fases | Encargo autorizado. C-05 se divide4h/10h sin alterar su presupuesto para evitar ciclo de dependencias. |

## Siguiente paso

Root comienza con T-01/fase1/plan en progreso por la autorización existente. Implementa
en el hilo principal con el perfil normal observado; las tareas conservan su
primer test rojo y evidencia. Después despacha revisión fresca, QA y documenter.
La selección técnica compartida es `api-contract`, `backend-practices`,
`delivery-practices`, `frontend-quality`, `stack-practices`, `unit-tests`.
PHP está detectado en manifiestos; las extensiones de proyecto no aportan piezas.
No se ejecutan Jira/Confluence ni cambios de instalación durante esta iniciativa.

La [investigación previa](research.md) registra las dependencias instaladas,
alternativas y fuentes primarias. Se reutilizan Laravel13.38.0/Guzzle8.3.0 y
Symfony Mailer/Mime8.1.7; los adaptadores no requieren instalar otro SDK.


## Ampliacion C07 autorizada por el usuario - 31oct2026

T-07 documenta toda la herramienta y su instalacion desde cero en Markdown/PDF con ejemplos y Mermaid, completa las guias y acredita cobertura y maquetacion. Se ejecuta Documenter, revision documental y QA visual antes de commits/push. No hay nuevo runtime ni repeticion de SQL/E2E sin cambios. T01-T06 conservan sus18CA y contadores de revision/QA.

Estimacion adicional de baja confianza:12h equivalentes y160000tokens. Presupuesto tecnico original104h/864000tokens conservado; nuevo subtotal116h/1024000tokens, margen20%139.2h/1228800tokens y6960EUR equivalentes+tokens por verificar. No son horas/gasto reales ni un cargo. Siete tareas en total; estado canonico en tasks.md.
