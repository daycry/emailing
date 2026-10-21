---
generacion:
  inicio: 2026-10-21T10:05:00Z
  fin: 2026-10-21T10:12:25Z
  fuente: estimado
  tokens_reales: null
  eur: null
  horas_ia: null
  duracion: null
  duracion_reloj: 7m
  ratio_usado: 300000
  ratio_origen: default no calibrado (300000)
  nota: estima tokens/horas a juicio y márcalo como estimado
  avisos: [carpeta de transcripciones no disponible]
estimacion_generacion:
  tokens: { entrada: 18000, salida: 12000 }
  horas_ia: 0.1
  duracion: 6m
  metodo: Juicio sin telemetría; 30000 tokens divididos por 300000 tok/h. No usa duración de reloj. Excluye la consolidación de spec estimada por separado.
generacion_revision_broker:
  artefacto: docs/roadmap/2026-10-21-plataforma-emailing/revision-broker-compartido
  inicio: 2026-10-21T10:32:37Z
  fin: 2026-10-21T10:35:34Z
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
estimacion_revision_broker:
  alcance: Revisión de spec, evaluación e índice; registrada una vez, sin duplicarla por documento.
  tokens: { entrada: 14000, salida: 7000 }
  horas_ia: 0.07
  duracion: 4m
  metodo: Juicio sin telemetría; 21000 tokens divididos por 300000 tok/h. No usa reloj ni se añade a importes del escenario previo.
---

# 2026-10-21-plataforma-emailing

> Aplicación interna con cuentas, Teammates, permisos, campañas y API sobre colas centralizadas compartidas entre aplicaciones. Evaluación en revisión, sin go.
>
> **Última instrucción del usuario:** laboratorio de pruebas single-host, alta disponibilidad fuera del alcance y despliegue abierto sin asumir Supervisor. [design.md](design.md) compara servicios Linux y Compose por función/aplicación; Cronicle se estudia como scheduler común con UI. Las menciones previas a Supervisor previsto o HA según SLO describen el escenario anterior; dejan de ser requisito o decisión pendiente. MySQL y portabilidad Laragon/Docker se evaluarán con la opción elegida. Las cifras históricas no se convierten en estimación del laboratorio.
>
> **Revisión de alcance 2026-10-21:** los **417,6 h / 20.880 € + tokens** que se conservan abajo corresponden al **escenario previo provisional**, anterior a concretar infraestructura compartida, outbox/reconciliación y pruebas multiaplicación. **No son presupuesto vigente del alcance ampliado ni importe aprobado.** Se reestimará tras elegir stack y validar su integración con RabbitMQ; esta revisión no inventa incrementos numéricos.

| | |
|---|---|
| **Fecha** | 2026-10-21 |
| **Estado** | en-revision |
| **Prioridad global** | Media |
| **Solicitante** | Usuario de esta conversación |
| **Spec** | [spec.md](spec.md) |
| **Diseño** | [design.md](design.md), borrador exploratorio; RabbitMQ elegido, stack pendiente |
| **Plan** | pendiente (handoff a planner después del go) |
| **Características evaluadas** | 7 funciones de emailing; infraestructura compartida transversal añadida, pendiente de recoste |

## Cuadro de mando

| Métrica | Escenario previo provisional; pendiente de reestimar | Confianza |
|---|---|---|
| Esfuerzo humano equivalente | **417,6 h** (348 h base +20 %) | Baja, sin histórico ni stack definitivo |
| Tiempo IA derivado | **13,84 h** (11,53 h base +20 %) + **3,46 h** supervisión | Muy baja, ratio no calibrado; no plazo de entrega |
| Coste humano equivalente | **20.880 € + T**, precio de tokens por verificar | Baja, 50 €/h supuesto; no cargo ni factura |
| Tokens IA | **4.152.000** con reserva; base 3.460.000 (in 2.900.000 / out 560.000) | Baja, juicio por componentes |
| Multiplicador teórico | **×24,14** | Muy baja, cálculo mecánico sin calibrar; no promesa |
| Características | **7 core** | Alcance propuesto para go/no-go |

Estas cifras conservan trazabilidad del escenario previo y no incluyen una valoración cerrada de la ampliación de infraestructura compartida. No son gasto real de IA ni calendario garantizado. Se separan implementación, proceso, revisión y QA; las horas humanas equivalentes no se suman a las horas derivadas de IA. También horas IA/tokens/productividad deberán revisarse tras elegir arquitectura.

## Resumen ejecutivo

El usuario confirma una aplicación interna multiusuario y solicita explícitamente Teammates como SendGrid: cuentas aisladas, miembros invitados y permisos dentro de cada cuenta. La propuesta incluye contactos/listas, plantillas dinámicas, campañas y cola, SMTP por cuenta, actividad/bajas y API de envío con llaves y scopes. El coste de autorización transversal e invitaciones exige arquitectura previa, revisión y QA propios. **Go condicionado a aprobar esta spec y sus supuestos**; la elección del flujo completo no constituye aprobación de alcance, arquitectura o coste, y aún no hay go del usuario.

La aclaración posterior añade **broker central estándar compartido entre varias aplicaciones**, cada una con sus colas, credenciales y workers; no se construye broker propio ni el negocio de otras aplicaciones. El volumen inicial confirmado es 100–200 emails/día para emailing y la concurrencia prevista son 5–10 procesos workers ejecutando trabajos, con reparto por aplicaciones/colas todavía abierto. [design.md](design.md) compara O1 Laravel 13/Livewire, O2 Django 5.2 LTS/Celery y O3 NestJS/React; RabbitMQ ha sido elegido por el usuario frente a Kafka/Beanstalkd ([ADR-001](../../knowledge/adr/ADR-001-rabbitmq-centralizado.md)); el stack sigue pendiente y no hay benchmark. La gestión de procesos vuelve a estar abierta sin exigir Supervisor; Docker es posible y el servidor/SO sigue abierto.

## Requerimientos recibidos

| ID | Característica | Requisito origen | ¿Claro? |
|---|---|---|---|
| C-01 | Identidades, cuentas, Teammates y permisos | Spec, Decisiones y CA-01/13/15/16/17/18/20; «uso interno», «datos separados», «con los teammates» | Modelo confirmado; registro interno/roles exactos a concretar |
| C-02 | Contactos, listas y CSV | Spec, Alcance y CA-02/03/13 | Propuesta equivalente a flujos documentados |
| C-03 | Plantillas dinámicas y preview | Spec, Alcance y CA-04/13 | Editor HTML sencillo propuesto |
| C-04 | Campañas, programación y cola | Spec, CA-05/06/09/14/21 a CA-25 | Broker compartido confirmado; 100–200 emails/día y 5–10 workers repartidos, picos/carga agregada pendientes |
| C-05 | SMTP/remitentes y log por cuenta | Spec, Configuración y CA-07/08/12/14 | Envío propio confirmado; relay/dominio pendientes |
| C-06 | Actividad real, bajas y supresión | Spec, CA-08/10/11/13/14 | Estados acotados; sin métricas inventadas |
| C-07 | API bearer, llaves y permisos de cuenta | Spec, C-07 y CA-19/20 | **Core** de la propuesta; contrato por concretar |
| Transversal | Infraestructura de colas por aplicación y despliegue de emailing | Spec, Arquitectura/Alcance y CA-21 a CA-25; última aclaración del usuario | RabbitMQ elegido; stack y coste de integración pendientes |

**Ambigüedades:** picos sobre 100–200 emails/día, carga agregada de las otras aplicaciones, reparto de 5–10 workers, plazo/presupuesto, política exacta de registro interno/recuperación, matriz final de permisos, SMTP/remitentes y servidor/SO. No se confunden workers con número de aplicaciones o cuentas. La propuesta no exige más descubrimiento antes de presentar esta revisión: las decisiones finales y umbrales se cierran en arquitectura, sin fingir que el importe previo cubre infraestructura todavía no costeada. Teammates comparte recursos de cuenta; no es aislamiento exclusivo por creador ni Subusers/impersonación.

## Datos necesarios para una evaluación completa

- [x] Objetivo de plataforma propia, uso interno y Teammates con aislamiento entre cuentas.
- [x] Contexto inicial disponible: Laragon/PHP y scaffold sin funcionalidad heredada.
- [x] Spec actualizada con CA-01 a CA-25: emailing, aislamiento entre aplicaciones, outbox/ack, durabilidad y carga cruzada.
- [x] Volumen inicial emailing 100–200 emails/día; 5–10 procesos workers ejecutando trabajos; infraestructura centralizada confirmada.
- [ ] Go/no-go del usuario sobre C-01 a C-07 y supuestos; bloquea planificación definitiva.
- [ ] Concretar política de alta interna y scopes en diseño; bloquea implementación final de acceso.
- [x] RabbitMQ elegido por el usuario el 2026-10-21; decisión limitada al broker, registrada en ADR-001.
- [ ] Elegir stack/motor/adaptador para RabbitMQ y validar su integración; **reestimar** infraestructura, integración y pruebas antes de presentar importe definitivo.
- [ ] Reparto de workers, picos/carga agregada, SLO y servidor/SO; condicionan motor, cola y operación, sin impedir diseño exploratorio.
- [ ] SMTP y dominio/remitentes autorizados; necesarios para envío real.
- [ ] Tarifas y precio de tokens si se necesita presupuesto económico definitivo.

## Supuestos económicos (ajustables)

**Coste equivalente = horas humanas × tarifa + tokens parametrizados.** EUR; no es factura del agente.

| Parámetro | Valor | Nota |
|---|---|---|
| Tarifa | 50 €/h | Default del kit no confirmado |
| Modelo IA | Runtime Codex disponible; modelo facturable no verificado | Model-tier propone opus, no disponible aquí; no se afirma cambio ni tarifa Claude |
| Precio input/output | Por verificar | `p_in` y `p_out`, €/1M tokens; sin rates.json fiable |
| Divisa | Sin conversión aplicada | Default 0,92 €/USD no se trata como cambio vigente |
| Contingencia | +20 % | Horas humanas e IA; reserva de tokens explícita y contada una vez |
| Supervisión | 25 % de horas IA | Default no calibrado; distinta de revisión y QA presupuestadas |
| Tokens→hora IA | 300.000 tok/h | Default; no CALIBRATION.md |
| FTE | 160 h/mes | Unidad comparativa |

Se comprobó ausencia de constitución, calibración, memoria técnica y dev.json/rates.json locales o de usuario en ubicaciones conocidas. Se aplican LES-001/007/008/009 del plugin: separar equivalencia humana de IA, presupuestar proceso y reservar revisión. El modelo/facturación real no está verificado; precios quedan parametrizados. `rates-verify` procede solo si se decide usar precios Claude.

Método: descomposición de abajo arriba por módulos nuevos, autorización por cuenta/scopes, transacciones y fallos SMTP; horas de juicio profesional con confianza baja. Desviación plausible de −30 % a +60 % sobre la base ante incógnitas; no es intervalo estadístico. El +20 % es contingencia contable, no techo garantizado. El coste IA medido de documentos no está disponible y no se confunde con este presupuesto.

## Evaluación por característica

Todas las fichas muestran implementación **base**, sin proceso/revisión/QA ni margen, añadidos por separado. Las rutas `src/`, `bin/`, API/vistas son módulos futuros, no existentes. Cada coste agrega `t_i`, tokens al precio todavía desconocido.

### C-01 — Registro, cuentas, Teammates y permisos

- **Requisito origen:** spec, Decisiones confirmadas y CA-01/13/15/16/17/18/20.
- **Descripción:** identidad/cuenta al registrarse, membresías y selector, invitaciones uso único 7 días, roles y scopes, revocación efectiva y auditoría. Los recursos sobreviven a eliminar al creador como teammate; el rol de una cuenta no abre otra.
- **Complejidad:** Alta.
- **Esfuerzo:** **64 h**, confianza Baja: identidad/recuperación 12, cuentas/membresías/selector 12, invitaciones 12, permisos/administración/auditoría 20, panel base 8.
- **Previsión IA:** 260.000 in / 60.000 out; 320.000 tokens; `t_1 = 0,26 p_in + 0,06 p_out` €.
- **Coste:** **3.200 € + t_1** (64 × 50).
- **Impacto / áreas:** futuros autenticación, repositorios y políticas de cuenta, miembros, sesión y panel.
- **Dependencias:** diseño previo de scopes y restricción de alta interna; transporte para invitaciones/recuperación.
- **Riesgos:** acceso entre cuentas, invitaciones reutilizadas, permisos antiguos en sesión, escalar privilegios vía API keys.
- **Incógnitas:** mecanismo exacto de registro interno, política owner/último admin y matriz de scopes; SSO/MFA no incluidos.

### C-02 — Contactos, listas e importación

- **Requisito origen:** spec C-02 y CA-02/03/13.
- **Descripción:** recursos compartidos de cuenta, CSV validado/deduplicado y consentimiento trazable.
- **Complejidad:** Media-Alta.
- **Esfuerzo:** **24 h**, confianza Baja: CRUD/listas 10, importación 10, consentimiento/alcance 4.
- **Previsión IA:** 160.000 in / 35.000 out; 195.000 tokens; `t_2 = 0,16 p_in + 0,035 p_out` €.
- **Coste:** **1.200 € + t_2**.
- **Impacto / áreas:** futuros contactos/listas/importador/vistas.
- **Dependencias:** C-01, formato/límites CSV y política de consentimiento.
- **Riesgos:** scope omitido en exportación/importación, filas masivas, duplicados y fórmulas peligrosas al exportar.
- **Incógnitas:** columnas, tamaño y retención; CRM y segmentación avanzada excluidos.

### C-03 — Plantillas HTML/dinámicas

- **Requisito origen:** spec C-03 y CA-04/13.
- **Descripción:** edición HTML sencilla, variables, preview aislada y prueba, accesible a miembros con permisos.
- **Complejidad:** Media-Alta.
- **Esfuerzo:** **20 h**, confianza Baja: CRUD/editor 8, variables 4, preview 6, prueba 2.
- **Previsión IA:** 120.000 in / 30.000 out; 150.000 tokens; `t_3 = 0,12 p_in + 0,03 p_out` €.
- **Coste:** **1.000 € + t_3**.
- **Impacto / áreas:** futuros renderer/editor/preview.
- **Dependencias:** C-01 y C-05 para pruebas de mensaje.
- **Riesgos:** scripts en panel, escape de variables y plantilla de otra cuenta.
- **Incógnitas:** catálogo de variables/diseños; editor por bloques y verificación visual en todos los clientes excluidos.

### C-04 — Campañas y cola (ficha de coste del escenario previo)

- **Requisito origen:** spec C-04 y CA-05/06/09/14; ampliado posteriormente por CA-21 a CA-25.
- **Descripción:** borrador/revisión/programación, reservas atómicas, límites, reintento controlado y contexto de cuenta persistente sin sesión. La ampliación incorpora broker compartido y outbox/reconciliación; las cifras siguientes son la referencia anterior, no su incremento de coste.
- **Complejidad:** Alta.
- **Esfuerzo:** **40 h**, confianza Baja: campañas/fechas 12, cola/reservas 14, fallo/reintento 10, límites/contexto 4.
- **Previsión IA:** 260.000 in / 55.000 out; 315.000 tokens; `t_4 = 0,26 p_in + 0,055 p_out` €.
- **Coste:** **2.000 € + t_4**.
- **Impacto / áreas:** futuros campañas/tablas trabajos/worker `bin/`; script ya declarado pero no implementado en composer.
- **Dependencias:** C-01/02/03, transporte C-05, supresión C-06, relay controlado; validar cliente RabbitMQ, topología, ACL, outbox y estrategia de confirmación/reentrega.
- **Riesgos:** duplicados, carreras y mezcla de cuentas; atomicidad inexistente SQL-broker, caída antes/después de ack y saturación por otras aplicaciones.
- **Incógnitas:** carga agregada/picos, reparto de workers y SLO. No se promete exactly-once SMTP; 100–200 emails/día no basta para medir capacidad global.

### C-05 — SMTP/remitentes y log por cuenta

- **Requisito origen:** spec C-05 y CA-07/08/12/14.
- **Descripción:** configuración privada de cuenta, TLS, secretos protegidos, modo log y diagnóstico seguro; PHPMailer es dependencia reutilizable.
- **Complejidad:** Alta.
- **Esfuerzo:** **28 h**, confianza Baja: contrato/log 6, SMTP/TLS 8, configuración/secretos 10, diagnóstico 4.
- **Previsión IA:** 180.000 in / 35.000 out; 215.000 tokens; `t_5 = 0,18 p_in + 0,035 p_out` €.
- **Coste:** **1.400 € + t_5**.
- **Impacto / áreas:** `.env.example`, PHPMailer en composer y futuros transporte/configuración/outbox. Variables globales existentes no solucionan cuentas por sí solas.
- **Dependencias:** C-01, clave de cifrado y relay/remitentes autorizados para pruebas reales.
- **Riesgos:** mezcla/fuga de credenciales, TLS relajado, destino SMTP interno no previsto; política de hosts según entorno.
- **Incógnitas:** proveedor, DNS, remitente y host producción. MTA y reputación excluidos.

### C-06 — Actividad, bajas y supresión

- **Requisito origen:** spec C-06 y CA-08/10/11/13/14.
- **Descripción:** estados reales y estadísticas de cuenta por scope, baja firmada/idempotente y supresión antes del transporte.
- **Complejidad:** Alta.
- **Esfuerzo:** **24 h**, confianza Baja: eventos/panel 8, tokens/baja 8, supresión/auditoría 8.
- **Previsión IA:** 170.000 in / 35.000 out; 205.000 tokens; `t_6 = 0,17 p_in + 0,035 p_out` €.
- **Coste:** **1.200 € + t_6**.
- **Impacto / áreas:** futuros eventos/supresiones/estadísticas/baja pública y worker.
- **Dependencias:** C-01/02/04/05, URL HTTPS y alcance de supresión por cuenta.
- **Riesgos:** baja en otra cuenta, tokens adivinables, filtración por agregados y confundir aceptación con entrega.
- **Incógnitas:** retención y eventual integración de rebotes. Aperturas/clics y webhooks por proveedor excluidos.

### C-07 — API bearer y llaves por cuenta

- **Requisito origen:** spec C-07 y CA-19/20; parte core de la propuesta, no opcional.
- **Descripción:** contrato de envío autenticado, llaves revocables/scoped de cuenta, límites e idempotencia. Gestión de llaves no permite eludir permisos de UI; recursos/llaves pertenecen a cuenta y su revocación se administra explícitamente.
- **Complejidad:** Alta.
- **Esfuerzo:** **28 h**, confianza Baja: contrato/endpoint 8, autenticación/scopes 8, idempotencia/límites 8, gestión/documentación 4.
- **Previsión IA:** 170.000 in / 30.000 out; 200.000 tokens; `t_7 = 0,17 p_in + 0,03 p_out` €.
- **Coste:** **1.400 € + t_7**.
- **Impacto / áreas:** futuros contrato API, middleware de tokens, gestión de llaves y adaptador de cola.
- **Dependencias:** C-01/04/05/06 y diseño de scopes; validar contrato antes de implementar endpoints.
- **Riesgos:** API key elige cuenta ajena, elevación de scopes, repetición de envío y llaves expuestas.
- **Incógnitas:** consumidores y endpoints exactos; no se ofrece compatibilidad total con API SendGrid.

## Comparativa

La tabla conserva las estimaciones **del escenario previo**. No suma todavía el impacto transversal detallado a continuación ni aprueba el importe.

| # | Característica | Complejidad | Horas base | Coste humano base | Tokens base | Prioridad | Confianza |
|---|---|---|---:|---:|---:|---|---|
| C-01 | Cuentas y Teammates | Alta | 64 h | 3.200 € | 320.000 | Alta | Baja |
| C-02 | Contactos/listas | Media-Alta | 24 h | 1.200 € | 195.000 | Alta | Baja |
| C-03 | Plantillas | Media-Alta | 20 h | 1.000 € | 150.000 | Media | Baja |
| C-04 | Campañas/cola | Alta | 40 h | 2.000 € | 315.000 | Alta | Baja |
| C-05 | SMTP/log de cuenta | Alta | 28 h | 1.400 € | 215.000 | Alta | Baja |
| C-06 | Actividad/bajas | Alta | 24 h | 1.200 € | 205.000 | Alta | Baja |
| C-07 | API/keys/scopes | Alta | 28 h | 1.400 € | 200.000 | Alta | Baja |
| | **Implementación** | | **228 h** | **11.400 €** | **1.600.000** | | |

La comparativa muestra implementación base. Prioridades son recomendación técnica; los costes de tokens, proceso, revisión, QA y margen se agregan después.

## Impacto pendiente de reestimación: infraestructura compartida

| Cambio confirmado o decisión pendiente | Impacto sobre el trabajo | Tratamiento económico |
|---|---|---|
| RabbitMQ central, aplicaciones con 1+ colas propias | Bootstrap durable, almacenamiento persistente, topología, credenciales/ACL por aplicación/entorno y observabilidad; no construir broker propio | Costear infraestructura/operación al concretar despliegue e integración; sin importe inventado |
| O1 Laravel/Livewire, O2 Django/Celery, O3 NestJS/React | O1 requiere validar adaptador RabbitMQ externo; O2 requiere runtime compatible con Celery; O3 semántica de jobs/ack explícita | Revalidar estimación por stack/cliente; scaffold PHP/PHPMailer no obliga elección |
| Outbox SQL, dispatcher y reconciliación con broker | Persistencia/publicación no atómicas; confirms, ack tras resultado durable, reserva/deduplicación y estado SMTP ambiguo | Revisar C-04 y parte de C-05/C-07; conservar cifras antiguas solo como referencia |
| 5–10 workers distribuidos; emailing 100–200/día | Reparto por cola/aplicación y límites SMTP; no 5–10 aplicaciones ni carga total conocida | Medir picos/carga agregada antes de dimensionar o prometer rendimiento |
| Aislamiento de aplicaciones con lenguajes distintos | Vhost RabbitMQ y usuarios/permisos por aplicación; no requiere mismo formato interno de jobs ni aislamiento físico de recursos | Integrar emailing; otras aplicaciones integran sus consumidores por separado, sin implementar aquí su negocio |
| Gestión de procesos abierta, Docker posible, SO/servidor pendiente | Servicios web/worker/broker, arranque/parada, backups/restauración, límites y un responsable de gestión por proceso | Costear despliegue elegido sin doble gestión; HA solo según SLO acordado |
| CA-21 a CA-25 | ACL con dos aplicaciones de prueba, cortes broker/red, crash publicación/ack, replay, SMTP ambiguo, persistencia y carga cruzada | Reestimar revisión/QA específicas; no ocultarlas en +20 % ni atribuir benchmark inexistente |

RabbitMQ está elegido por el usuario; Kafka/Beanstalkd quedan como alternativas evaluadas. No se ha demostrado mayor velocidad mediante benchmark. Vhosts/ACL separan acceso y topología, no CPU/RAM/disco; límites y eventual separación física dependen de carga/SLO. Compartir infraestructura no implica interoperar los jobs de cada framework; si después se desea integración entre aplicaciones, se definirán contratos versionados explícitos. La base inicial es un broker durable con recuperación probada, sin cluster/HA automático por volumen bajo.

## Presupuesto total (escenario previo provisional)

| Concepto | Horas humanas base | Tokens in / out base | Coste humano base |
|---|---:|---:|---:|
| Implementación C-01 a C-07 | 228 h | 1.320.000 / 280.000 | 11.400 € |
| Proceso: spec, evaluación, arquitectura y plan | 16 h | 180.000 / 40.000 | 800 € |
| Revisión adversarial y correcciones | 56 h | 800.000 / 130.000 | 2.800 € |
| QA: permisos, invitaciones, API, concurrencia, E2E, entrega | 48 h | 600.000 / 110.000 | 2.400 € |
| **Base** | **348 h** | **2.900.000 / 560.000** | **17.400 €** |
| Margen +20 % | 69,6 h | Reserva 580.000 / 112.000 | 3.480 € |
| **Total con margen** | **417,6 h** | **3.480.000 / 672.000** | **20.880 € + T** |

`T_base = 2,9 p_in + 0,56 p_out` €. Con reserva, **`T = 3,48 p_in + 0,672 p_out` €**, precio por verificar. La contingencia de tokens se cuenta una vez. Revisión y QA no están ocultas en el margen y no deben redistribuirse duplicadas al planificar.

El escenario de uso de IA no factura automáticamente 417,6 h humanas: un cálculo ilustrativo de supervisión sería `3,46 h × 50 €/h = 173 € + T`, de confianza muy baja, sin garantizar que esa supervisión baste para producción. Ambas magnitudes son supuestas. Los costes de este documento son generación separada y no se añaden otra vez a la previsión del proceso.

**Advertencia de vigencia:** 417,6 h y 20.880 € + T son el escenario anterior, no valoración cerrada del broker compartido/adaptador/despliegue ampliados. Tampoco IA/tokens/productividad se convierten en pronóstico actual. No se incrementan ni reducen importes sin elección y reestimación.

**Costes externos no tarifados:** hosting/broker, SMTP por volumen, dominios, operación/mantenimiento, reputación y certificaciones; proveedor/capacidad pendientes. El trabajo de bootstrap y despliegue compartido ahora sí pertenece al alcance, pendiente de costear. **Fases orientativas:** infraestructura compartida y diseño de cuenta/permisos → aplicación emailing y sus colas/consumidores → integración y recuperación/carga. Cada otra aplicación integrará después sus consumidores y negocio por separado. No es un recorte aprobado y el planner concretará tareas tras elección y go.

## Productividad IA (humano vs. IA)

Comparativa matemática histórica del escenario previo; pendiente de revisar con la ampliación y sin valor de promesa para el alcance actual.

| KPI | Valor con margen +20 % |
|---|---:|
| Horas humanas equivalentes | 417,6 h (348 h base) |
| Horas IA derivadas | 13,84 h (3.460.000 / 300.000 × 1,2) |
| Supervisión humana | 3,46 h (25 %) |
| **IA + supervisión** | **17,3 h** |
| Horas equivalentes ahorradas teóricas | 400,3 h |
| Ahorro teórico | 95,86 % |
| Multiplicador teórico | ×24,14 |
| FTE equivalente teórico | 2,502 meses |

No es una promesa de ahorro ni plazo. Es un resultado matemático del ratio default no calibrado; no incluye esperas del usuario, DNS o relay ni acredita desempeño de esta máquina. Las herramientas, fallos y revisión pueden cambiar el tiempo real sustancialmente. Las horas equivalentes humanas nunca se han validado en este proyecto.

## Recomendación

- **Veredicto:** propuesta funcional revisada en-revision, sin aprobación. Concretar elección en [diseño exploratorio](design.md), validar adaptador/operación y reestimar antes del go/no-go definitivo; no presentar el importe previo como aprobado ni vigente para infraestructura ampliada.
- **Menor coste y alto valor:** C-02/C-03 con log de C-05 permiten validar la experiencia tras la base de cuenta/permiso.
- **Costosas/delicadas:** C-01, C-04 y permisos de C-07; revisión transversal tiene presupuesto propio.
- **Orden sugerido:** concretar stack y despliegue RabbitMQ → reestimar y go/no-go → plan de infraestructura compartida y emailing → implementación de cuentas/permisos, transportes/API y módulos con sus colas → revisión/recuperación/carga y QA. No es plan detallado ni aprobación.
- **Fuera:** broker propio, negocio de otras aplicaciones, réplica total SendGrid, MTA/SaaS/billing/Subusers/impersonación, HA sin SLO acordado, aperturas/entrega sin eventos, editor de bloques.

## Riesgos transversales

| Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|
| Se confunde creador con cuenta o se mezclan cuentas | Media | Alto | Recursos de cuenta; membresía/scopes validados; matriz owner/teammate/ajeno en UI/API/exportaciones/agregados |
| Revocación no efectiva o permisos UI eludidos por API | Media | Alto | Permisos vigentes, scopes mínimos de keys y gestión autorizada; E2E tras revocación/reducción |
| Invitación reutilizada/registro interno abierto | Media | Alto | Uso único/caducidad, política configurable de alta, auditoría; concretar antes de implementar |
| Worker utiliza cuenta/secretos incorrectos | Media | Alto | Contexto de cuenta persistido, validación de referencias y SMTP/bajas propios; sin sesión de navegador |
| Error SMTP ambiguo provoca duplicados | Media | Alto | Estado ambiguo sin reintento a ciegas; relay controlado con fallos inducidos |
| Motor/cliente o adaptador no cumple recuperación/concurrencia | Desconocida | Alto | RabbitMQ elegido; stack pendiente; validar adaptador, base y semántica de jobs antes de cerrar coste o capacidad |
| Se pierde publicación o ack provoca doble ejecución | Media | Alto | Outbox/reconciliación, confirms/ack tras persistencia, deduplicación y casos de crash; SMTP ambiguo sin retry ciego |
| Otra aplicación satura broker compartido o cruza colas | Media | Alto | ACL/vhost RabbitMQ por app, límites y pruebas cruzadas; separación lógica no es CPU/RAM aisladas |
| Volumen emailing se confunde con carga global o workers con apps | Media | Alto | 100–200 emails/día solo emailing; 5–10 procesos workers repartidos; medir carga agregada/picos |
| Broker único o doble supervisión se confunden con HA | Media | Alto | Persistencia y restauración, un responsable por proceso; SLO antes de réplica/quorum; servidor/SO pendientes |
| Fuga de SMTP/API keys o HTML ejecutable | Media | Alto | Secretos protegidos, logs limpios, políticas de destinos/preview aislada y tests por scope |
| «Igual» se interpreta como backend observado o producto íntegro | Alta | Alto | Spec acotada y fuentes oficiales; REA solo observa web pública |
| Relay/DNS/reputación o tarifas desconocidas | Media | Alto | Relay/dominio autorizados y pruebas controladas; aceptación no significa entrega; costes parametrizados |

Recon muestreó el scaffold relevante; excluyó vendor, lockfiles y captura masiva. No se ejecuta code-health: no hay árbol de producto >200 ficheros que justifique el opt-in. La [spec](spec.md#referencias) enlaza fuentes oficiales de Teammates/permisos y evidencia REA. No es auditoría exhaustiva de seguridad ni evidencia del backend privado.

## Siguiente paso

**RabbitMQ elegido; stack pendiente**: elegir opción de aplicación, validar integración/adaptador y cerrar reestimación del impacto compartido antes del go/no-go económico definitivo. La elección del broker no aprueba el conjunto. Tras el go, spec pasa a `aprobada`, evaluación a `completado` y `planner` crea artefactos enlazados con infraestructura compartida y despliegue de emailing, sin desarrollar negocio de otras aplicaciones ni duplicar proceso. Concretar registro/scopes, reparto de workers, carga/SLO y servidor antes de implementar sus decisiones; SMTP real requiere relay autorizado. No se publica en Confluence sin opt-in.

## Changelog

- 2026-10-21: el usuario elige RabbitMQ mediante «pues RabbitMQ»; ADR-001 aceptada. Stack, despliegue, reestimación y go del conjunto siguen pendientes.

- 2026-10-21: consolidación de la última aclaración: cuentas aisladas, Teammates invitados, recursos compartidos de cuenta y API core. Sustituye propuestas anteriores de una cuenta única, aislamiento exclusivo por usuario y API opcional; no se presentan como alcance aprobado.
- 2026-10-21: revisión acotada por broker central multiaplicación, volumen inicial 100–200 emails/día y 5–10 procesos workers; Supervisor previsto/Docker posible. Añadidos impactos y CA-21 a CA-25, sin cambiar cifras: 417,6 h / 20.880 € quedan etiquetados como escenario previo provisional pendiente de reestimar; stack/broker sin aprobar.
