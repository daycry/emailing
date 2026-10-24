---
generacion:
  inicio: 2026-10-24T07:44:59Z
  fin: 2026-10-24T07:52:25Z
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
  tokens: { entrada: 24000, salida: 10000 }
  horas_ia: 0.113333
  duracion: 7m
  metodo: Juicio exclusivo para este documento, 34000 tokens divididos por 300000 tok/h; no es consumo medido ni deriva del reloj. No incluye T-16 ni el trabajo del orquestador.
---

# 2026-10-24-mcp-google

> Evaluación de MCP y autenticación Google para ampliar la plataforma interna con acceso delegado desde agentes.

| | |
|---|---|
| **Fecha** | 2026-10-24 |
| **Estado** | completado |
| **Prioridad global** | Alta |
| **Solicitante** | Usuario del proyecto |
| **Spec** | [`spec.md`](spec.md) |
| **Plan** | [`improvement-plan.md`](improvement-plan.md), borrador; [14 tareas](tasks.md) y [pruebas](test-plan.md) |
| **Características evaluadas** | 6 |

## Cuadro de mando

| Métrica | Total estimado | Confianza |
|---|---|---|
| Esfuerzo humano equivalente | **201,6 h** (168 h base +20 %); rango orientativo con margen **156–297,6 h** | Media-baja; juicio, sin calibración local |
| Tiempo IA derivado de tokens | **5,76 h** (4,8 h base +20 %) + **1,44 h** de supervisión | Baja; ratio no calibrado, no plazo de reloj |
| Coste humano equivalente | **10.080 € + coste de tokens sin verificar**; rango **7.800–14.880 € + tokens** | Media-baja para equivalente humano; coste IA desconocido |
| Tokens previstos con margen | **1.728.000** (1.440.000 entrada / 288.000 salida); base 1.440.000 | Baja; previsión, no consumo |
| Multiplicador de productividad | **×28**, resultado mecánico del ratio supuesto | Baja; no compromiso de rendimiento |
| Características | **6**, más proceso y revisión presupuestados aparte | Media |

## Resumen ejecutivo

La ampliación es viable sobre Laravel y el modelo de cuentas/Teammates existente. La referencia solicitada, social-scheduler, aporta un patrón de login Socialite y MCP con Sanctum y OAuth propio que merece evaluarse antes de instalar Passport. La mayor dificultad está en adaptar la autorización a la cuenta y membresía vigente, asegurar el flujo OAuth y comprobar clientes reales; no en añadir un endpoint o un botón Google. Se recomienda incorporar las seis características al plan completo, conservando los requisitos originales y el envío en Mailpit/log.

Esta evaluación es una propuesta revisable. No instala dependencias, no modifica servicios ni prueba Google/MCP, y no supone que leer configuraciones de clientes demuestre interoperabilidad.

## Requerimientos recibidos

| ID | Característica | Requisito origen (ref.) | ¿Claro? |
|---|---|---|---|
| C-01 | Google web y vínculos de identidad | Spec: Contexto, Configuración y CA-01–04 | Función clara; política Google y autoenlace por confirmar |
| C-02 | Servidor de autorización OAuth y transporte MCP | Spec: Decisiones, Flujo y CA-05/06/08 | Claro a nivel funcional; paquetes, registro y renovación pendientes de diseño |
| C-03 | Cuenta, Teammates, consentimiento y auditoría | Spec: Arquitectura y CA-07–09 | Claro; requiere adaptar contexto de sesión actual |
| C-04 | Herramientas de negocio compartidas | Spec: Catálogo y CA-09–11 | Módulos definidos; esquemas finales dependen de T-17/T-18 |
| C-05 | Clientes y despliegue portable | Spec: Configuración y CA-12–15 | Familias claras; producto Claude, versiones y red de destino por fijar |
| C-06 | QA de identidad/MCP y regresión transversal | Spec: Pruebas y CA-01–16 | Casos definidos; recorrido real requiere credenciales y sesiones locales |

**Ambigüedades:** se ha preguntado opcionalmente si Google se limita a un dominio Workspace. Sin respuesta se propone Gmail/Workspace para personas autorizadas, con filtro opcional; no se bloquea el diseño. Deben fijarse duraciones de tokens, renovación, mecanismo de registro de cliente, versiones y productos Claude concretos. La referencia admite autoenlace Google verificado; la propuesta inicial de Emailing exige vinculación explícita y deja cualquier cambio de política para revisión documentada.

## Datos necesarios para una evaluación completa

- [x] Requisitos funcionales y límites de laboratorio registrados en la spec.
- [x] Seis características, criterios observables y dependencias identificados.
- [x] Contexto técnico muestreado: Laravel 13, Fortify, Livewire, Sanctum, MySQL y servicios de negocio existentes.
- [x] Referencia pública solicitada inspeccionada en una instantánea fija.
- [ ] Política definitiva Google y autoenlace: pendiente, no bloquea una propuesta configurable.
- [ ] Versiones/productos cliente y accesibilidad de red: necesarias para cerrar la matriz de interoperabilidad.
- [ ] Credenciales Google/callbacks por entorno y sesiones de clientes reales: necesarias para E2E externo; sólo configuración privada del operador.
- [ ] Tarifa real, precio IA y calibración local: desconocidos; presupuesto equivalente y parametrizado, sin bloquear planificación.

## Supuestos económicos (ajustables)

**Coste equivalente humano = horas humanas × tarifa. Coste IA = millones de tokens de entrada × `p_in` + millones de salida × `p_out`.** Los escenarios se muestran por separado para no sumar desarrollo humano completo y supervisión de IA como si fueran el mismo gasto.

| Parámetro | Valor | Nota |
|---|---|---|
| Tarifa | 50 €/h | Default del kit; no confirmado ni facturación real |
| Modelo IA | Heredado del entorno de ejecución | No se asume un modelo Claude por el frontmatter del agente |
| Precio input/output | `p_in` / `p_out` €/millón; **verificar** | Sin tarifa vigente confirmada; coste numérico de tokens desconocido |
| Cambio USD/EUR | No aplicado | No se convierte moneda sin precio verificado |
| Supervisión | 25 % de horas IA derivadas | Default supuesto; no trabajo medido |
| Margen | 20 % sobre horas y previsión de tokens base | Aplicado una sola vez |
| Ratio IA | 300.000 tokens/h, no calibrado | Default compartido; no existe CALIBRATION.md local |
| FTE | 160 h/mes | Sólo referencia matemática |

Método: descomposición funcional por integración, controles y recorridos de aceptación; rangos por incertidumbre de paquetes, OAuth y clientes. La estimación central de cada característica es juicio técnico, no el punto medio obligatorio de su rango. No existe histórico local de estimación disponible ni tarifa configurada. Se aplican las lecciones del kit LES-007/008/009: distinguir magnitudes, presupuestar proceso y dar partida propia a revisión. El trabajo equivalente de QA no sustituye la revisión independiente.

## Evaluación por característica

### C-01 — Google web y vinculación segura

- **Requisito origen:** spec, CA-01–04.
- **Descripción:** acceso Google, identidad estable, vínculo/desvínculo explícito, errores seguros y preservación de registro/invitaciones existentes.
- **Complejidad:** Alta.
- **Esfuerzo:** 20 h base; rango 16–28 h; confianza media.
- **Previsión IA:** 120.000 in / 20.000 out; 0,466667 h derivadas; coste `0,12 p_in + 0,02 p_out` €.
- **Coste:** 20 × 50 = 1.000 € equivalentes base; 1.200 € con margen, más tokens.
- **Impacto:** `User`, Fortify/CreateNewUser, rutas y vistas de autenticación, nuevas identidades externas y configuración de servicios.
- **Dependencias:** proyecto Google y callbacks privados para E2E; política de admisión existente.
- **Riesgos:** secuestro por autoenlace, collation de email, conflictos de identidad y desvínculo que bloquee recuperación.
- **Incógnitas:** filtro Workspace y autoenlace; patrón de social-scheduler aprovechable, sin imponer su política de sólo usuarios existentes.

### C-02 — OAuth y transporte MCP

- **Requisito origen:** spec, CA-05/06/08.
- **Descripción:** descubrir, registrar/identificar cliente, consentir y canjear códigos con PKCE; tokens del recurso, caducidad, revocación y reautorización.
- **Complejidad:** Muy alta.
- **Esfuerzo:** 32 h base; rango 24–48 h; confianza media-baja.
- **Previsión IA:** 240.000 in / 40.000 out; 0,933333 h derivadas; coste `0,24 p_in + 0,04 p_out` €.
- **Coste:** 1.600 € base; 1.920 € con margen, más tokens.
- **Impacto:** nuevas rutas de metadatos/OAuth/MCP, persistencia de clientes/códigos/grants, middleware y gestión de conexiones.
- **Dependencias:** decisión de diseño tras contrastar Laravel MCP + Sanctum/OAuth propio de la referencia frente a Passport; validación de Composer antes de instalar.
- **Riesgos:** redirects, replay/concurrencia, audiencia insuficientemente ligada al token, confusión entre bearer y sesión; código OAuth propio amplía obligación de revisión.
- **Incógnitas:** refresh necesario o reautorización, preregistro/metadatos de cliente/DCR, compatibilidad de versiones de protocolo. El wrapper Passport `mcp:use` no resuelve por sí solo permisos granulares.

### C-03 — Contexto delegado por cuenta y permisos

- **Requisito origen:** spec, CA-07–09.
- **Descripción:** contexto independiente de sesión, intersección de grant y membresía, revocación efectiva y auditoría con cliente/actor/cuenta.
- **Complejidad:** Alta.
- **Esfuerzo:** 16 h base; rango 12–24 h; confianza media.
- **Previsión IA:** 100.000 in / 20.000 out; 0,4 h derivadas; coste `0,10 p_in + 0,02 p_out` €.
- **Coste:** 800 € base; 960 € con margen, más tokens.
- **Impacto:** `AccountContext`, `Permissions`, `TeamService`, servicios que consumen contexto y nuevos grants delegados.
- **Dependencias:** contrato de autenticación T-17/T-18 y modelo de ejecución delegado.
- **Riesgos:** aislamiento por usuario copiado de la referencia sería insuficiente; permisos emitidos antiguos y carrera entre revocación y mutación.
- **Incógnitas:** estrategia concreta de contexto y bloqueo; distinguir ciclo de vida del grant personal del de API keys de cuenta.

### C-04 — Catálogo MCP de negocio

- **Requisito origen:** spec, Catálogo y CA-09–11.
- **Descripción:** herramientas útiles en los siete módulos propuestos, con esquemas, paginación, errores y ejecución mediante servicios existentes.
- **Complejidad:** Alta.
- **Esfuerzo:** 32 h base; rango 24–44 h; confianza media-baja.
- **Previsión IA:** 200.000 in / 40.000 out; 0,8 h derivadas; coste `0,20 p_in + 0,04 p_out` €.
- **Coste:** 1.600 € base; 1.920 € con margen, más tokens.
- **Impacto:** nuevas herramientas MCP y adaptadores de servicios de contactos, plantillas, campañas, actividad, supresiones y envío API/outbox.
- **Dependencias:** C-02/C-03 y T-17/T-18; decisiones de consentimiento de destinatarios permanecen en el contrato común.
- **Riesgos:** duplicar lógica, saltar revisión de campañas, exponer secretos, dar por consentido un destinatario por tener `mail.send` y confundir aceptación SMTP con entrega.
- **Incógnitas:** número final de herramientas y división por operación; el rango contempla contrato inicial de todos los módulos, no sólo una herramienta de ejemplo.

### C-05 — Clientes y operación portable

- **Requisito origen:** spec, CA-12–15.
- **Descripción:** perfiles verificables para Codex, Claude y OpenCode; configuración, red, callbacks, claves y recuperación en Laragon/Docker.
- **Complejidad:** Media-alta.
- **Esfuerzo:** 16 h base; rango 12–24 h; confianza baja.
- **Previsión IA:** 100.000 in / 20.000 out; 0,4 h derivadas; coste `0,10 p_in + 0,02 p_out` €.
- **Coste:** 800 € base; 960 € con margen, más tokens.
- **Impacto:** documentación y ejemplos por cliente, configuración de servidor y persistencia/backup de identidad OAuth.
- **Dependencias:** C-01–04, versiones instaladas y red que permita acceder al recurso desde cada producto cliente.
- **Riesgos:** confundir variantes/versiones de OpenCode o productos Claude; un cliente alojado no tiene la misma conectividad que una CLI local.
- **Incógnitas:** productos y versiones finales; cada compatibilidad se acreditará por recorrido real, no por documentación ajena.

### C-06 — QA y regresión de identidad/MCP

- **Requisito origen:** spec, Pruebas y CA-01–16.
- **Descripción:** negativos protocolarios, aislamiento de cuentas, revocación concurrente, catálogo, idempotencia cruzada y recorridos reales por cliente/entorno.
- **Complejidad:** Alta.
- **Esfuerzo:** 24 h base; rango 20–36 h; confianza media-baja.
- **Previsión IA:** 160.000 in / 40.000 out; 0,666667 h derivadas; coste `0,16 p_in + 0,04 p_out` €.
- **Coste:** 1.200 € base; 1.440 € con margen, más tokens.
- **Impacto:** pruebas, fixtures ficticios, test-plan y evidencias de QA; dependencias de cierre T-19/T-20.
- **Dependencias:** C-01–05 y credenciales/sesiones privadas para integración real.
- **Riesgos:** marcar simulación como E2E real, usar datos personales en evidencias, regresión de web/API existente.
- **Incógnitas:** disponibilidad de Google y clientes reales; ausencia se deja pendiente expresamente y no se sustituye con un aprobado automático.

## Comparativa

Horas y tokens de esta tabla son base; el coste humano no incluye tokens sin precio verificado.

| ID | Característica | Complejidad | Horas | Coste humano | Tokens in / out | Prioridad | Confianza |
|---|---|---|---|---|---|---|---|
| C-01 | Google e identidades | Alta | 20 | 1.000 € | 120k / 20k | Alta | Media |
| C-02 | OAuth y MCP | Muy alta | 32 | 1.600 € | 240k / 40k | Alta | Media-baja |
| C-03 | Cuenta y permisos | Alta | 16 | 800 € | 100k / 20k | Alta | Media |
| C-04 | Herramientas de negocio | Alta | 32 | 1.600 € | 200k / 40k | Alta | Media-baja |
| C-05 | Clientes y despliegue | Media-alta | 16 | 800 € | 100k / 20k | Alta | Baja |
| C-06 | QA transversal | Alta | 24 | 1.200 € | 160k / 40k | Alta | Media-baja |
| | **Características** | | **140** | **7.000 €** | **920k / 180k** | | |
| P | Proceso: spec, evaluación, diseño y plan | Media | 8 | 400 € | 80k / 20k | Alta | Baja |
| R | Revisión adversarial, seguridad y correcciones | Alta | 20 | 1.000 € | 200k / 40k | Alta | Baja |
| | **Total base** | | **168** | **8.400 €** | **1.200k / 240k** | | |

La referencia reduce incertidumbre de estructura, pero no se descuenta un ahorro numérico no medido: cambia de usuario propietario a cuentas/Teammates y sus garantías deben verificarse. Las partidas P/R son de esta ampliación; no repiten el presupuesto completo original de la plataforma. Rangos base agregados: características 108–204 h, proceso 6–12 h y revisión 16–32 h, total 130–248 h. No existe calendario ni deadline comprometido.

## Presupuesto total

| Concepto | Cálculo | Importe |
|---|---|---|
| Equivalente humano base | 168 h × 50 €/h | 8.400 € |
| Contingencia humana | 33,6 h × 50 €/h | 1.680 € |
| Tokens base | 1,2 `p_in` + 0,24 `p_out` | Sin precio confirmado |
| Tokens con margen | 1,44 `p_in` + 0,288 `p_out` | Sin precio confirmado |
| **Escenario humano equivalente con margen** | 201,6 h × 50 + tokens con margen | **10.080 € + 1,44 `p_in` + 0,288 `p_out`** |
| **Escenario IA + supervisión** | 1,44 h de supervisión × 50 + tokens con margen | **72 € + 1,44 `p_in` + 0,288 `p_out`**, bajo defaults no calibrados |

El escenario IA no representa facturación ni trabajo real, no monetiza horas de ejecución del agente como horas humanas y no garantiza el tiempo de validación manual. No se suman ambos escenarios. Rango humano con margen: 156–297,6 h y 7.800–14.880 €, más tokens. Como sensibilidad de tokens se estima 0,8–2,2 millones base, 0,96–2,64 millones con margen; no se presenta como intervalo estadístico.

## Productividad IA (humano vs. IA)

| KPI | Valor supuesto con margen |
|---|---|
| Horas humanas equivalentes | 201,6 h (168 base) |
| Horas IA derivadas | 5,76 h (1.728.000 / 300.000) |
| Supervisión humana | 1,44 h (25 % de 5,76) |
| **IA + supervisión** | **7,2 h** |
| Diferencia matemática | 194,4 h |
| Ahorro teórico | 96,43 % |
| Multiplicador teórico | ×28 |
| FTE teórico | 1,215 meses equivalentes |

Estas métricas proceden del default del kit, no de una medición de este proyecto. La velocidad de tokens no permite predecir esperas de proveedor, ejecución de pruebas, revisiones ni sesiones humanas; las cifras de productividad tienen confianza baja y no son un plazo de entrega. La previsión base incluye proceso y revisión separados. Tiempo de reloj y consumo real de producir esta evaluación se registran, si están disponibles, únicamente en su bloque de generación.

## Recomendación

- **Veredicto:** go técnico condicionado al diseño de autorización y validación de clientes; las seis características están propuestas para planificación, sin implementación aprobada por este documento.
- **Primer avance de valor:** C-01 reutiliza un patrón conocido de identidad, y C-03 fija el contexto común que necesita el resto.
- **Partidas de mayor incertidumbre:** C-02, C-04 y C-05; no reducirlas a un endpoint con token estático para aparentar compatibilidad.
- **Orden sugerido por dependencias:** diseño → C-01/C-03 → C-02 → C-04 → C-05/C-06, con pruebas desde cada cambio. El planner decidirá tareas y secuencia concreta.
- **Integración global:** concluir T-16, alinear contratos T-17/T-18 y ejecutar esta ampliación antes del cierre T-19/T-20. No cambiar el alcance original ni dar el objetivo por terminado al alcanzar 20/20.
- **Fuera recomendado:** SMTP externo, Gmail/Drive y administración de credenciales/Teammates vía MCP sin contrato explícito.

## Riesgos transversales

| Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|
| Copiar aislamiento por usuario de la referencia | Alta | Alto | Grant por cuenta, membresía actual y pruebas cruzadas |
| Elegir paquetes antes de verificar OAuth requerido | Media | Alto | Comparar referencia/Passport y validar metadatos, audiencia, registro y versiones |
| Sesión web aceptada indebidamente en MCP | Media | Alto | Guard de bearer específico y negativos con cookies/sin token |
| Vínculos de identidad inseguros | Media | Alto | `sub` estable, control explícito de vinculación, política auditada y negativos de collation |
| Permisos antiguos sobreviven a revocación | Media | Alto | Intersección actual por llamada y punto de mutación, incluidos refresh/reautorización |
| El cliente agente salta controles de envío | Media | Alto | Servicios comunes, cuotas, revisión, consentimiento e idempotencia compartidos |
| Falsa compatibilidad por documentación | Alta | Medio | Matriz por producto/versión y evidencia ejecutada separada de consulta |
| Datos privados en el repo público | Media | Alto | Configuración local, fixtures ficticios y control de publicación de evidencias |
| Solapamiento con T-16 en curso | Baja | Alto | Esta evaluación sólo toca sus documentos e índice; sin aplicación, DB, Docker ni tests |

## Siguiente paso

Revisión técnica del orquestador completada el 24-10-2026. El alcance y el plan se
aceptan para ejecución dentro del objetivo ya autorizado de implementar todo el
roadmap. No se afirma una nueva aprobación humana de presupuesto, arquitectura
o pruebas. La implementación continúa pendiente de las dependencias T-16/T-17/T-18
según cada tarea y conserva las comprobaciones externas de Google y clientes.

El orquestador revisará esta propuesta y hará el handoff al diseño/planner del workflow completo. Se proponen **C-01–C-06** y partidas P/R; no hay autorización de proveedor externo ni de publicación de credenciales. El planner conservará estos supuestos y presupuestos o explicará cualquier ajuste con evidencia, enlazará las dependencias con T-17/T-18 y el cierre T-19/T-20, y rellenará los backlinks al crear el plan.

La consulta opcional sobre dominio Google permanece abierta. Se puede planificar la política configurable sin detener trabajo; su valor definitivo y el autoenlace se registrarán como decisiones explícitas. Las credenciales futuras se configurarán privadamente en el entorno, no se solicitarán como texto público ni se escribirán en Git.

## Evidencia y límites de esta evaluación

- Recon de muestra de `AccountContext`, `Permissions`, `Membership`, `CreateNewUser` y firmas de servicios: contexto de sesión actual, permisos existentes y registro explícito que crea una cuenta propia.
- Stack confirmado por el orquestador: Laravel 13.38.0 / Sanctum 4.3.3; `composer.json` leído. Passport, Socialite y Laravel MCP no se instalan en esta evaluación.
- Referencia `daycry/social-scheduler` fijada a `7f542a548613eb535e767fc10081fc21dc5b792a`: lectura completa de login/resolver, LoginFlow, servidor OAuth/PKCE, redirects, guard, abilities, herramienta base y rutas MCP; muestra de conexión de redes. No es auditoría exhaustiva ni ejecución.
- [Análisis de referencia del orquestador](reference-analysis.md): muestra de 40 archivos verificados contra blobs Git. Su documentación deja pendientes los smokes manuales de clientes; no se hereda compatibilidad probada ni su aprobación de arquitectura como aprobación para Emailing.
- Fuentes primarias y rutas figuran en [Referencias de la spec](spec.md#referencias); consulta de documentación realizada el 2026-10-24, con compatibilidad runtime pendiente.
- No hay constitución, rates.json ni CALIBRATION.md local detectados. Consulta de lecciones de estimación del proyecto sin aciertos; doctrina del plugin leída selectivamente. Se enumeraron 116 fuentes PHP de aplicación/configuración/rutas/migraciones en el recon acotado; no se ha ejecutado informe code-health.
- No se ejecutaron aplicación, SQL, HTTP local, SMTP, Docker ni pruebas. Ninguna cifra se atribuye a T-16 ni a ventanas de otros agentes. Sin sincronización externa Jira/Confluence.
- Medidor de esta evaluación cerrado con estado privado independiente; salida literal conservada en `storage/mcp-google-evaluation/meter-close.stdout.json`. Degradación a estimado por ausencia de transcripciones; tokens y coste reales desconocidos. Los 7m de reloj se mantienen separados de las horas IA por tokens.

## Changelog

- 2026-10-24: spec y evaluación iniciales; incorporación de la referencia solicitada social-scheduler, separación entre login/conexión social/OAuth MCP y adaptación pendiente a cuentas/Teammates. Estado de evaluación: en revisión.
- 2026-10-24: planner enlaza plan/ledger/test-plan en borrador y conserva las seis características, 168 h y 1.440.000 tokens base. Propuesta técnica revisable; no cambia el estado de spec/evaluación ni afirma aprobación humana nueva.
