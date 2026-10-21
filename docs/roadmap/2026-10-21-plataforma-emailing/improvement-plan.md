---
estado: en-progreso
design: design.md
test-plan: test-plan.md
generacion:
  inicio: 2026-10-21T16:28:21Z
  fin: 2026-10-21T16:34:48Z
  fuente: estimado
  tokens_reales: null
  eur: null
  horas_ia: null
  duracion: null
  duracion_reloj: 6m
  ratio_usado: 300000
  ratio_origen: default no calibrado (300000)
  nota: Sin telemetría compatible; no cifras reales de consumo.
  avisos: [carpeta de transcripciones no disponible]
estimacion_generacion:
  tokens: { entrada: 22000, salida: 15000 }
  horas_ia: 0.123333
  duracion: 7m
  metodo: Juicio sin telemetría; 37000 / 300000 tok/h, no duración de reloj.
---

# 2026-10-21-plataforma-emailing

> Plataforma interna de email: empezar por cuentas, Teammates y permisos, conservando el alcance completo C-01 a C-07.

| Campo | Valor |
|---|---|
| Fecha | 2026-10-21 |
| Estado | en-progreso; primera fase autorizada |
| Tipo | Nueva funcionalidad |
| Prioridad | Alta |
| Solicitante | Usuario de esta conversación |
| Responsable | Implementer, con revisión y QA independientes |
| Spec | [spec.md](spec.md), aprobada |
| Evaluación | [evaluation.md](evaluation.md), completada |
| Diseño | [design.md](design.md), O1 + D2 aprobadas |
| Ledger | [tasks.md](tasks.md), fuente canónica de progreso |

## Cuadro de mando

| Métrica | Estimado | Real | Confianza |
|---|---|---|---|
| Tiempo humano equivalente | **451,2 h**: 376 h base +20 % | Sin medición | Baja |
| Tiempo IA | **14,88 h** más **3,72 h** de supervisión | Sin medición | Muy baja |
| Coste equivalente | **22.560 € + T** | Desconocido | Baja; no presupuesto contratado |
| Tokens | **4.464.000** con reserva; 3.720.000 base | Sin telemetría | Baja |
| Multiplicador teórico | ×24,26 | Sin medir | Muy baja |
| Tareas | **20** en cuatro fases | 0 completadas | — |

La autorización «vamos a por ello» permite planificar e implementar empezando por C-01. No acredita contratación de las equivalencias económicas. La infraestructura terminada queda excluida; solo se estima integración y despliegue de la aplicación. La documentación detallada de infraestructura solicitada adicionalmente se registra como alcance documental y no añade costes ficticios a esta evaluación.

## Estimación por fase

Horas y tokens **base**, sin margen. Cada fila hereda las partidas de la evaluación, repartidas por dependencias; no son nuevas estimaciones.

| Fase | Tareas | Horas base | Entrada / salida | Coste humano base |
|---|---|---:|---:|---:|
| 1 — Runtime, cuentas y Teammates | T-01–T-04 | 104 | 785.000 / 175.000 | 5.200 € |
| 2 — Contactos, plantillas y SMTP | T-05–T-09 | 92 | 745.000 / 160.000 | 4.600 € |
| 3 — Campañas, entrega y actividad | T-10–T-16 | 120 | 940.000 / 195.000 | 6.000 € |
| 4 — API y validación global | T-17–T-20 | 60 | 610.000 / 110.000 | 3.000 € |
| **Total base** | **20** | **376** | **3.080.000 / 640.000** | **18.800 €** |
| Reserva +20 % | — | 75,2 | 616.000 / 128.000 | 3.760 € |
| **Total con margen** | — | **451,2** | **3.696.000 / 768.000** | **22.560 € + T** |

T-01 contiene las 24 h transversales; T-02/T-03 las 48 h de C-01. T-04 incluye las 8 h de consolidación/plan y 24 h de revisión/QA. Las otras puertas reparten las 88 h restantes de revisión/QA: 24 +32 +16 +16. Total de revisión/QA: 112 h; implementación: 256 h; proceso: 8 h. La elaboración de este plan no demuestra haber consumido esas 8 h.

## Presupuesto económico

### Supuestos

| Parámetro | Valor | Nota |
|---|---|---|
| Tarifa equivalente | 50 €/h | Default heredado, no precio contratado |
| Modelo | Runtime Codex disponible | No se afirma ejecución Claude por el tier del plugin |
| Tokens entrada/salida | `p_in`, `p_out` €/millón | Por verificar; no convertir a euros sin tarifa fiable |
| Ratio tokens/hora IA | 300.000 | Default sin calibrar, no productividad medida |
| Supervisión | 25 % del tiempo IA | Comparación ilustrativa |
| Contingencia | 20 % una sola vez | Sobre base humana, IA y reserva de tokens |
| FTE | 160 h/mes | Unidad comparativa |

### Desglose

| Concepto | Cálculo | Importe |
|---|---|---:|
| Equivalencia humana base | 376 × 50 | 18.800 € |
| Contingencia humana | 75,2 × 50 | 3.760 € |
| Tokens con reserva | `T = 3,696 p_in + 0,768 p_out` | Desconocido |
| Total equivalente con margen | Sin hosting, relay, dominios o mantenimiento | **22.560 € + T** |

## Previsión de tokens por fase

| Fase | Entrada | Salida | Total base | Coste IA base |
|---|---:|---:|---:|---|
| 1 | 785.000 | 175.000 | 960.000 | `0,785 p_in + 0,175 p_out` |
| 2 | 745.000 | 160.000 | 905.000 | `0,745 p_in + 0,160 p_out` |
| 3 | 940.000 | 195.000 | 1.135.000 | `0,940 p_in + 0,195 p_out` |
| 4 | 610.000 | 110.000 | 720.000 | `0,610 p_in + 0,110 p_out` |
| **Total** | **3.080.000** | **640.000** | **3.720.000** | `3,08 p_in + 0,64 p_out` |

Método: herencia del desglose por componente de evaluación; distribución entre tareas por lectura/generación y pruebas previstas, sin telemetría. La reserva no se vuelve a sumar dentro de cada tarea.

## Productividad IA (humano vs. IA)

| Indicador teórico | Con margen |
|---|---:|
| Horas humanas equivalentes | 451,2 h |
| IA aproximada | 14,88 h, base 12,4 h |
| Supervisión | 3,72 h, base 3,1 h |
| IA + supervisión | 18,6 h |
| Diferencia equivalente | 432,6 h |
| Ahorro teórico | 95,88 % |
| Multiplicador | ×24,26 |
| FTE equivalente | 2,704 meses |

No es plazo comprometido ni ahorro acreditado. Interrupciones, herramientas y revisión pueden cambiar el tiempo real. No sumar equivalencia humana y ejecución IA como gasto real.

## Resumen ejecutivo

Sustituir el scaffold provisional por Laravel 13/PHP 8.4 y Livewire 4 con UI propia en español. Mantener un producto interno multiusuario con cuentas aisladas y Teammates que comparten recursos de su cuenta. La primera entrega verificable permite registro, acceso, cambio de cuenta e invitaciones con permisos efectivos. Después se añaden contactos, plantillas, SMTP, campañas, actividad y API.

### Objetivos

- Aplicar autorización por cuenta en todas las vías de acceso, incluida revocación inmediata en acciones siguientes.
- Ejecutar el mismo producto mediante Laragon y su Compose, reutilizando los cuatro servicios existentes.
- Registrar trabajo durable y resultados demostrables sin reenviar automáticamente tras aceptación SMTP incierta.

## Datos necesarios para un informe completo

- [x] Requisitos y alcance C-01 a C-07 confirmados en spec.
- [x] Criterios CA-01 a CA-25 disponibles y trazados en test-plan.
- [x] Autorización para iniciar C-01 y continuar el workflow.
- [x] Infraestructura local y acceso al repositorio disponibles; secretos conservados fuera de documentos.
- [x] Laravel/Livewire/MySQL y Compose independiente elegidos.
- [x] Dependencias y riesgos del adaptador identificados; prueba runtime en T-01.
- [x] Laboratorio sin HA, Supervisor ni correo externo por defecto.
- [ ] Relay/remitentes reales: se requieren antes de activar salida externa, no para Mailpit.
- [ ] Tarifas verificadas: necesarias solo para presupuesto contractual, no bloquean desarrollo.

## Análisis de impacto

- `composer.json`, `composer.lock`, entrada `public/` y almacenamiento: reemplazar scaffold sin datos de producto que migrar; conservar `infra/`, `docs/` y exclusiones de secretos.
- Nuevos `app/`, `bootstrap/`, `config/`, `database/`, `resources/`, `routes/` y `tests/`: módulos Laravel con cuentas, políticas y pruebas.
- Nuevo `compose.yaml` y `docker/`: solo web, PHP y workers propios; red externa `emailing-lab`.
- `docs/infraestructura/` y enlaces documentales: documentación detallada de arquitectura, servicios, configuración, operación, persistencia, respaldos, aplicaciones y resolución de problemas, basada en archivos reales.

## Cambios arquitectónicos

Se aplica [diseño O1 + D2](design.md), [ADR-001](../../knowledge/adr/ADR-001-rabbitmq-centralizado.md) y [ADR-002](../../knowledge/adr/ADR-002-laravel-mysql-compose.md). No se rediseña infraestructura ni se añaden otras instancias RabbitMQ/Cronicle/MySQL/Mailpit.

La cuenta posee recursos; el usuario es identidad y actor auditado. Admin puede administrar recursos y Teammates de su cuenta; Read-only solo lee los módulos autorizados; Restricted recibe scopes explícitos de un catálogo servidor. Presets developer/marketer/accountant son conjuntos de scopes, sin privilegios ocultos. Owner es una membresía Admin protegida: nadie puede eliminarla o degradarla sin transferencia atómica a otro Admin activo y reautenticación; no se permite dejar una cuenta sin administrador. Los no administradores no gestionan miembros ni elevan scopes. El contexto activo se comprueba contra membresía vigente en cada acción, incluidos eventos Livewire. Registro habilitado en laboratorio y configurable para deshabilitar altas; aceptación de invitaciones no habilita un registro público desactivado. Tokens aleatorios de invitación se guardan hash, duran siete días, solo aceptables por el email previsto y una vez bajo transacción/bloqueo.

MySQL de producto `emailing` conserva la tabla `lab_smoke`. Toda prueba destructiva usa **exclusivamente `emailing_testing`** y un guard que rechaza otros nombres; prohibidos `migrate:fresh`, `RefreshDatabase` o truncados contra la base compartida. Pruebas críticas usan MySQL real; SQLite no sustituye validación de constraints, locks o aislamiento.

Credenciales/vhost RabbitMQ propios de aplicación, sin rol administrador; nunca usar el administrador del laboratorio en runtime. Web y worker usan imagen/código compatibles, worker en primer plano con reinicio Docker; no Supervisor. Cronicle dispara acciones finitas HTTP autenticadas sin socket Docker. Outbox, confirms, ack después de persistencia, deduplicación y estados SMTP ambiguos se verifican en fase 3.

## Archivos a crear/modificar

| Ruta | Acción | Propósito |
|---|---|---|
| `composer.json`, `composer.lock`, `package.json`, `package-lock.json` | Sustituir/crear | Dependencias runtime estables y locks |
| `artisan`, `app/**`, `bootstrap/**`, `config/**`, `routes/**` | Crear | Aplicación modular y autorización |
| `database/**`, `tests/**`, `phpunit.xml` | Crear | Migraciones, fixtures y pruebas con guard de base |
| `resources/**`, `public/**`, `vite.config.*` | Crear/adaptar | UI española y assets generados |
| `.env.example`, `.gitignore`, `.htaccess`, `storage/**` | Adaptar | Perfiles y almacenamiento privado; no versionar secretos/bootstrap temporal |
| `compose.yaml`, `docker/**`, `scripts/**` | Crear | Empaquetado, provisión local y operación propia |
| `docs/api/**`, `docs/infraestructura/**`, `docs/desarrollo/**` | Crear | Contrato y operación |
| `README.md`, `docs/README.md`, `docs/RAG_INDEX.md`, `infra/README.md`, `docs/roadmap/**`, `docs/knowledge/**`, `CHANGELOG*` | Actualizar | Enlaces, decisiones, trazabilidad y evidencias |

Los patrones cubren scaffold y archivos generados necesarios; no autorizan incorporar `vendor/`, `node_modules/`, secretos, cachés o datos locales a Git.

## Dependencias y prerequisitos

- Infra existente healthy; red externa y volúmenes preservados. Ningún `down -v`.
- PHP 8.4 con MySQL, sodium y sockets para adaptador; Node 22 explícito `C:/laragon/bin/nodejs/node-v22`.
- Composer fija Laravel/Fortify/Livewire/Sanctum y adaptador compatible. No se añaden Flux Pro, passkeys u otros extras sin necesidad.
- Orden: C-01 → contactos/plantillas/SMTP → supresión/campañas/envío/actividad → API. Contrato antes de endpoints C-07.
- Implementación secuencial por fases; revisión adversarial y QA como puertas separadas. No configuración TDD ni implementación paralela opt-in detectada.

## Criterios de aceptación globales

- [ ] CA-01 a CA-25 pasan con evidencia; matriz completa en test-plan.
- [ ] Fase 1 supera auth, invitaciones, revocación, roles y aislamiento MySQL/UI.
- [ ] Laragon y Compose funcionan sin duplicar servicios ni destruir `lab_smoke`.
- [ ] Concurrencia, reentrega y fallos SQL/broker/SMTP tienen resultados durables verificables.
- [ ] API conserva scopes/cuenta, revocación e idempotencia, sin privilegios adicionales.
- [ ] Revisión adversarial sin hallazgos Critical/Important abiertos; QA sin fallos ni flaky pendientes.

## Riesgos y mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|
| Cruce de cuentas/permisos obsoletos | Media | Alto | Matriz negativa UI/API/worker y contexto vigente servidor |
| Adaptador solo compatible en manifiesto | Desconocida | Alto | Job real, redelivery y ACL antes de campañas |
| Doble envío o publicación perdida | Media | Alto | Outbox, confirms y estado ambiguo sin retry ciego |
| Prueba destruye datos compartidos | Baja | Alto | Base testing exclusiva y guard antes de migrar |
| Worker vivo bloqueado | Media | Medio | Timeouts/progreso y señales; unhealthy no implica reinicio automático |
| Diferencia Windows/Linux | Media | Medio | Verificar Laragon y Compose; sockets y workers Linux |

## Métricas de éxito

- Cero acceso cruzado en casos negativos por cuenta y rol.
- Un único trabajo por clave idempotente; ninguna segunda ejecución concurrente del mismo envío.
- Estados y métricas sustentados en eventos observados; aceptación SMTP nunca etiquetada entrega.
- Carga cruzada inicial: repartir cinco procesos 2 emailing/3 segunda aplicación; repetir diez procesos 4/6. Fijar y registrar volumen/picos y umbrales antes de medir, sin SLA inventado.
- Registro de avance por tarea y evidencia reproducible en `testing/`.

## Precisión de entregabilidad antes de correo externo

La consulta posterior del usuario sobre listas de bloqueo amplía el detalle de C-05/C-06. T-08 deberá validar dominio/remitentes y documentar SPF/DKIM/DMARC y relay; T-10 incorporará feedback a las supresiones; T-13 aplicará límites por cuenta y agregados por relay, con pausa; T-14 distinguirá resultados observados, ausencia de feedback y alertas. Las supresiones incorporarán rebotes definitivos y quejas mediante un mecanismo autenticado realmente soportado por el proveedor. SMTP por sí solo no ofrece ese feedback completo.

Estos controles todavía están pendientes. El laboratorio continúa con Mailpit y no se habilita entrega a Internet como parte de C-01. La [spec](spec.md) y la [guía de entregabilidad](../../infraestructura/entregabilidad.md) describen responsabilidades y límites; no existe garantía de ausencia de bloqueos. Antes de la fase de transportes, elegir proveedor/feedback, concretar pruebas de DNS y eventos y revisar el esfuerzo del conector: las cifras heredadas no son un presupuesto cerrado para integraciones todavía sin definir.

## Changelog del plan

| Fecha | Cambio | Autor |
|---|---|---|
| 2026-10-21 | Plan completo de 20 tareas; primera fase C-01 autorizada; infra ya terminada excluida | planner |
| 2026-10-21 | Se explicitan controles pendientes de reputación y entregabilidad en C-05/C-06; concretar proveedor, pruebas e impacto antes de correo externo | coordinador, precisión del usuario |

## Siguiente paso

Implementer inicia T-01 a T-04 sobre la rama `feature/plataforma-emailing`, usando `tasks.md`. La autorización de continuar ya existe; no se repite la pregunta de aprobación ni se presenta la estimación como contratación. Tras la primera fase se mantiene el resto pendiente, sin declarar C-02 a C-07 completadas. Jira y Confluence son opcionales sin opt-in y no se publican.
