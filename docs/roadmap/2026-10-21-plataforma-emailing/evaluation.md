---
estado: completado
actualizado: 2026-10-21
generacion:
  artefacto: docs/roadmap/2026-10-21-plataforma-emailing/evaluation.md
  inicio: 2026-10-21T16:23:43Z
  fin: 2026-10-21T16:27:18Z
  fuente: estimado
  tokens_reales: null
  eur: null
  horas_ia: null
  duracion: null
  duracion_reloj: 0h4m
  ratio_usado: 300000
  ratio_origen: default no calibrado (300000)
  nota: estima tokens/horas a juicio y márcalo como estimado
  avisos: [carpeta de transcripciones no disponible]
estimacion_generacion:
  tokens: { entrada: 16000, salida: 6000 }
  horas_ia: 0.0733
  duracion: 0h4m
  metodo: Juicio sin telemetría; 22000 / 300000 tok/h, no duración de reloj.
---

# Evaluación — Plataforma interna de emailing

> **Autorización:** «vamos a por ello» permite continuar con Laravel + Livewire y MySQL, comenzando por cuentas, Teammates y permisos. Cifras equivalentes supuestas, no cargos, facturas ni plazo comprometido.
>
> **Infraestructura excluida:** [infra-laboratorio](../2026-10-21-infra-laboratorio/tasks.md) ya completada 3/3: RabbitMQ, MySQL, Cronicle y Mailpit verificados, persistencia y tres escenarios UI. No se presupuesta de nuevo su construcción.

| Campo | Valor |
|---|---|
| Fecha | 2026-10-21 |
| Estado | completado; autorización funcional para planificar e implementar |
| Prioridad global | Alta |
| Solicitante | Usuario de esta conversación |
| Spec | [spec.md](spec.md), aprobada |
| Diseño | [design.md](design.md), O1 Laravel/Livewire + MySQL, D2 Compose independiente |
| Plan | [improvement-plan.md](improvement-plan.md), [ledger](tasks.md) y [test-plan](test-plan.md); 20 tareas, comenzar C-01 |
| Características evaluadas | C-01 a C-07 y despliegue/integración de aplicación |

## Cuadro de mando

| Métrica | Previsión actual del trabajo pendiente | Confianza |
|---|---|---|
| Esfuerzo humano equivalente | **451,2 h**: 376 h base +20 % | Baja; sin calibración |
| Coste humano equivalente | **22.560 € + T**: 451,2 × 50 €/h supuestos | Baja; no precio contratado |
| Tokens previstos | **4.464.000** con reserva; 3.720.000 base | Baja; juicio por componentes |
| Tiempo IA derivado | **14,88 h** + **3,72 h** supervisión | Muy baja; ratio no calibrado |
| Multiplicador teórico | **×24,26** | Muy baja; no rendimiento medido |
| Características | **7 core** y bloque transversal | Alcance autorizado |

## Resumen ejecutivo

Monolito Laravel 13/PHP 8.4, Livewire 4 y MySQL. Auth Laravel/Fortify y llaves Sanctum apoyan el desarrollo, sin resolver automáticamente aislamiento, invitaciones ni permisos de Teammates. Se sustituye el scaffold PHP/SQLite/PHPMailer provisional, sin producto ni usuarios que migrar. Se preservan `infra/`, sus secretos y volúmenes.

Mayor riesgo: autorización por cuenta y semántica de envío: outbox SQL, publicación durable, reentregas, crash antes/después del ack y SMTP ambiguo. El adaptador RabbitMQ estable 15.0.2 declara compatibilidad Laravel 13, todavía sin validación runtime. Comprobarlo pertenece al trabajo nuevo de aplicación, junto a su acceso/vhost y Compose; no a repetir infraestructura.

## Requerimientos recibidos

| ID | Característica | Origen | Claridad |
|---|---|---|---|
| C-01 | Identidades, cuentas, Teammates y permisos | Spec CA-01/13/15/16/17/18/20; diseño | Aprobado; aplicar política de registro y matriz del diseño |
| C-02 | Contactos/listas y CSV | CA-02/03/13 | Aprobado; límites en plan |
| C-03 | Plantillas/preview | CA-04/13 | HTML sencillo, sin editor por bloques |
| C-04 | Campañas/programación/cola | CA-05/06/09/14/21–25 | Outbox/recuperación, sin exactly-once SMTP |
| C-05 | SMTP y remitentes de cuenta | CA-07/08/12/14 | Mailpit de laboratorio y alternativa log privado |
| C-06 | Actividad/bajas/supresión | CA-08/10/11/13/14 | Sin inventar entrega/aperturas/clics |
| C-07 | API bearer y scopes | CA-19/20 | Core; contrato antes de endpoints |
| Transversal | Runtime, Laragon/Compose y conexión al laboratorio | CA-21–25 y diseño D2 | Infra existente, aplicación pendiente |

100–200 emails/día solo para emailing. Los 5–10 workers son procesos repartidos entre aplicaciones, no cantidad de usuarios ni carga global conocida. Teammates comparte recursos de cuenta; no Subusers ni aislamiento exclusivo por creador. Sin SaaS público, MTA propio o negocio de otras aplicaciones.

## Datos necesarios para una evaluación completa

- [x] C-01 a C-07 y autorización de continuar workflow completo.
- [x] Laravel/Livewire + MySQL y Compose separado; laboratorio sin HA ni Supervisor obligatorio.
- [x] Infra probada, sin reconstruirla ni imputarla otra vez.
- [ ] Validar adaptador con job real, reentrega y ACL: integración inicial, no bloqueo de planificación.
- [ ] Medir picos/reparto y límites durante QA; sin benchmark previo.
- [ ] Relay/remitentes autorizados antes de salir de Mailpit.
- [ ] Tarifas reales de tokens/desarrollo si se solicita presupuesto contractual.

## Supuestos económicos (ajustables)

| Parámetro | Valor | Método |
|---|---|---|
| Tarifa | 50 €/h | Default del kit; equivalencia, no cobro |
| Modelo IA | Runtime Codex disponible | Tier sugerido opus no implica ejecución Claude |
| Tokens input/output | Por verificar | `p_in`, `p_out` en €/millón, sin inventar tarifa |
| Contingencia | +20 % | Horas/reserva de tokens una vez |
| Supervisión | 25 % del tiempo IA | Default, separada de revisión/QA |
| Ratio tokens→hora | 300.000 tok/h | Default sin CALIBRATION.md |
| FTE | 160 h/mes | Unidad comparativa |

Método: descomposición del trabajo pendiente con stack elegido. Reducciones por auth/UI estándar e incremento de integración/recuperación por componente; no resta arbitraria del presupuesto antiguo. Sin rates.json/histórico verificable para sustituir defaults. LES-001/007/008/009: separar equivalencia humana de gasto real, proceso de implementación y revisión de contingencia. Confianza baja; desviación plausible −30 %/+60 %, no intervalo estadístico ni techo. No se suma la generación documental como gasto medido.

## Evaluación por característica

Implementación base; revisión/QA/margen separados después. Cada coste IA: `(entrada/1M × p_in) + (salida/1M × p_out)`, precio desconocido. Confianza de horas baja en todas las filas.

| ID | Complejidad | Desglose de horas base | Tokens entrada / salida | Coste humano base | Áreas/dependencias | Riesgos/incógnitas |
|---|---|---|---|---|---|---|
| C-01 | Alta | **48 h**: bootstrap/auth 12, cuentas/selector 10, invitaciones 10, permisos/auditoría 16 | 260.000 / 60.000 | 2.400 € | `app/`, rutas, Livewire; Fortify/MySQL/Mailpit | Cruce de cuentas, último admin, reutilización de invitaciones y scopes; QA por rol |
| C-02 | Media-alta | **24 h**: CRUD/listas 10, CSV 10, consentimiento 4 | 160.000 / 35.000 | 1.200 € | Contactos/importador; C-01 | CSV inválido/duplicados, exportación sin scope; límites por fijar |
| C-03 | Media-alta | **20 h**: editor 8, variables 4, preview 6, prueba 2 | 120.000 / 30.000 | 1.000 € | Renderer/vistas; C-01/05 | HTML activo/escape; preview aislada; sin editor visual avanzado |
| C-04 | Alta | **64 h**: campañas 12, outbox/dispatcher 20, consumo/reservas 16, reconciliación/reintentos 12, Cronicle 4 | 370.000 / 80.000 | 3.200 € | Jobs/estado/comandos; C-01/02/03/05/06, RabbitMQ | SQL-broker no atómicos, crash/ack, SMTP ambiguo sin retry ciego |
| C-05 | Alta | **24 h**: transporte/log 6, SMTP/TLS 6, secretos 8, diagnóstico 4 | 165.000 / 35.000 | 1.200 € | Mail Laravel/Symfony, config cifrada; C-01 | Secretos, destinos SMTP; proveedor real pendiente |
| C-06 | Alta | **24 h**: actividad 8, bajas/tokens 8, supresión 8 | 170.000 / 35.000 | 1.200 € | Eventos/baja pública; C-01/02/04/05 | Tokens, agregados entre cuentas, falsa entrega; retención por fijar |
| C-07 | Alta | **28 h**: contrato 8, scopes/auth 8, idempotencia/límites 8, gestión/doc 4 | 170.000 / 30.000 | 1.400 € | Sanctum/middleware/OpenAPI; C-01/04/05/06 | Llaves elevan scopes o eligen cuenta ajena; sin compatibilidad SendGrid total |
| Transversal | Media-alta | **24 h**: Compose/Laragon 10, ACL/topología 6, adaptador/operación 8 | 145.000 / 35.000 | 1.200 € | Compose propio, perfiles y docs; red externa existente | Runtime pendiente y diferencias Windows/Linux; preservar secretos/volúmenes |

## Comparativa

| Bloque | Horas base | Coste humano base | Tokens base | Orden recomendado |
|---|---:|---:|---:|---|
| C-01 | 48 h | 2.400 € | 320.000 | Primero, aislamiento y Teammates |
| Transversal | 24 h | 1.200 € | 180.000 | Bootstrap y prueba real de integración |
| C-02 | 24 h | 1.200 € | 195.000 | Tras cuentas |
| C-03 | 20 h | 1.000 € | 150.000 | Tras cuentas; pruebas necesitan SMTP |
| C-05 | 24 h | 1.200 € | 200.000 | Antes de campañas |
| C-04 | 64 h | 3.200 € | 450.000 | Con supresión lista antes de enviar |
| C-06 | 24 h | 1.200 € | 205.000 | Junto al envío |
| C-07 | 28 h | 1.400 € | 200.000 | Contrato y servicios comunes primero |
| **Implementación** | **256 h** | **12.800 €** | **1.900.000** | Planner concreta dependencias |

## Presupuesto total

| Partida pendiente | Horas base | Tokens entrada / salida | Coste humano base |
|---|---:|---:|---:|
| Implementación C-01 a C-07 + transversal | 256 h | 1.560.000 / 340.000 | 12.800 € |
| Proceso restante: consolidación y plan | 8 h | 100.000 / 20.000 | 400 € |
| Revisión adversarial y correcciones | 56 h | 760.000 / 140.000 | 2.800 € |
| QA: permisos, integración, fallos y E2E | 56 h | 660.000 / 140.000 | 2.800 € |
| **Base** | **376 h** | **3.080.000 / 640.000** | **18.800 €** |
| Reserva +20 % | 75,2 h | 616.000 / 128.000 | 3.760 € |
| **Total** | **451,2 h** | **3.696.000 / 768.000** | **22.560 € + T** |

`T_base = 3,08 p_in + 0,64 p_out`; `T = 3,696 p_in + 0,768 p_out`, precios por verificar. Sin hosting, SMTP externo, dominios o mantenimiento. Sin reconstruir infraestructura completada; sí incluye conectar/probar aplicación, workers, ACL y despliegue propios.

**Histórico:** **417,6 h / 20.880 € + tokens** era escenario provisional con stack abierto e integración ampliada sin estimar. Solo trazabilidad; no se suma al actual ni acredita desviación real o gasto aprobado/medido.

## Productividad IA (humano vs. IA)

| Indicador teórico | Con margen |
|---|---:|
| Equivalencia humana | 451,2 h |
| Tiempo IA | 14,88 h: 3.720.000 / 300.000 × 1,2 |
| Supervisión | 3,72 h: 25 % |
| IA + supervisión | 18,6 h |
| Diferencia equivalente | 432,6 h; 95,88 % |
| Multiplicador | ×24,26 |
| FTE equivalente | 2,704 meses |

No es plazo ni ahorro acreditado: ratio sin calibrar y fallos/herramientas alteran tiempo real. Equivalencia humana no se suma a horas IA. Supervisión ilustrativa: 186 € a 50 €/h más tokens desconocidos, sin acreditar suficiencia para producción.

## Recomendación

- **Veredicto:** continuar con planner y ejecución autorizada. C-01 y conexión/despliegue propio primero; job RabbitMQ real antes de depender del adaptador para campañas.
- **Valor temprano:** login, aislamiento e invitaciones con UI española verificable; después contactos y plantillas.
- **Mayor riesgo:** C-01/04/07; revisión/QA por fase, sin esperar al final para comprobar aislamiento.

## Riesgos transversales

| Riesgo | Probabilidad / impacto | Mitigación/evidencia |
|---|---|---|
| Acceso cruzado/escalada | Media / alto | Políticas/contexto servidor y pruebas negativas por rol/UI/API |
| Adaptador y recuperación | Desconocida / alto | Job real/fallos, no inferir runtime de Composer |
| Doble envío/publicación perdida | Media / alto | Outbox/reconciliación/idempotencia/SMTP ambiguo y crash tests |
| Cruce/saturación por otra app | Media / alto | Vhost/ACL y carga cruzada; separación lógica no física |
| Laragon/Compose difieren | Media / medio | Perfiles y MySQL; verificar ambos; workers Linux |
| Secretos/HTML ejecutable | Media / alto | Cifrado, logs limpios y preview aislada |
| Correo real/métricas falsas | Media / alto | Mailpit por defecto; aceptación no es entrega |
| Se espera SendGrid íntegro | Media / alto | CA acotados y fuentes públicas; backend privado no observado |

Recon muestreado del scaffold y evidencia infra; no code-health necesario para árbol pequeño. Sin HA/clúster/Supervisor como requisito. Sin publicación externa.

## Siguiente paso

Handoff a **planner** para C-01 a C-07 + transversal, con plan, ledger y test-plan enlazados. Ejecutar primero Laravel, cuentas, Teammates/permisos y verificación runtime. Mantener laboratorio y distinguir éxito SMTP de entrega. No repetir aprobación de continuar ni presentar cifras como contratación.

## Changelog

- 2026-10-21: reestimación Laravel/Livewire + MySQL, D2 autorizado; infraestructura completada excluida. Handoff a planner con equivalencias supuestas, sin telemetría.
- 2026-10-21: histórico RabbitMQ elegido y ampliación outbox/multiaplicación; 417,6 h / 20.880 € anterior no vigente.
