---
design: n/a
test-plan: test-plan.md
generacion:
  inicio: 2026-10-21T13:22:31Z
  fin: 2026-10-21T13:25:04Z
  fuente: estimado
  tokens_reales: null
  eur: null
  horas_ia: null
  duracion: null
  duracion_reloj: 3m
  ratio_usado: 300000
  ratio_origen: default no calibrado (300000)
  nota: Sin transcripciones; consumo real no disponible
---

# 2026-10-21-infra-laboratorio

> Arrancar y verificar infraestructura compartida sin modificar las aplicaciones existentes.

| Campo | Valor |
|---|---|
| **Fecha** | 2026-10-21 |
| **Estado** | completado |
| **Tipo** | Infra |
| **Prioridad** | Media |
| **Solicitante** | Usuario |
| **Responsable** | implementer / qa |
| **Spec** | [spec.md](spec.md), alcance autorizado por la instrucción actual |
| **Evaluación** | n/a: prueba acotada expresamente autorizada; no hereda presupuesto de la plataforma |
| **Diseño** | n/a: separación infraestructura/aplicaciones acordada en conversación |

## Cuadro de mando

| Métrica | Estimado | Real | Confianza |
|---|---|---|---|
| Tiempo humano equivalente | 3,6 h (3 h base +20 %) | No medido | Media |
| Tiempo IA | 1,2 h (1 h base +20 %), supervisión 0,3 h | No medido | Baja |
| Coste equivalente humano | 180 € + tokens sin verificar | No es gasto real ni presupuesto aprobado | Baja |
| Tokens IA | 40.000 (30.000 entrada / 10.000 salida) | Sin telemetría | Baja |
| Multiplicador estimado | ×2,4 | No medido | Baja |
| Tareas | 3 | 3 hechas | — |

## Estimación por fase

| Fase | Horas humanas base | Tokens entrada / salida | Coste equivalente humano |
|---|---|---|---|
| Preparación y Compose (T-01) | 1,5 | 15.000 / 5.000 | 75 € |
| Arranque y bootstrap (T-02) | 0,75 | 7.500 / 2.500 | 37,50 € |
| Verificación y documentación (T-03) | 0,75 | 7.500 / 2.500 | 37,50 € |
| **Total base** | **3** | **30.000 / 10.000** | **150 €** |
| **Total con margen 20 %** | **3,6** | **40.000 totales previstos** | **180 € + tokens sin verificar** |

## Presupuesto económico

### Supuestos (ajustables)

Tarifa de referencia del kit: 50 €/h, no confirmada ni facturada. Margen de horas: 20 %. Los tokens y tiempos son aproximaciones por lectura de configuración, redacción de scripts y comprobación de cuatro servicios. No hay tarifa IA verificada, telemetría ni coste real disponible. La ejecución usa el modelo disponible heredado, sin afirmar uso de Claude.

### Desglose

150 € equivalentes base +30 € de margen =180 € equivalentes humanos. Coste IA parametrizado: `0,03 × precio_entrada_por_millón + 0,01 × precio_salida_por_millón`. No representa un cargo ni un presupuesto aprobado.

## Previsión de tokens (por fase)

La tabla de fases distribuye los 40.000 tokens previstos. Son estimaciones independientes del consumo real de generar este plan; este último permanece nulo si el medidor carece de telemetría.

## Productividad IA (humano vs. IA)

Supuesto con margen: 3,6 h humanas frente a 1,2 h IA +0,3 h de supervisión =1,5 h. Diferencia teórica 2,1 h, ahorro 58,3 %, multiplicador ×2,4. No es rendimiento medido ni compromiso de plazo.

## Resumen ejecutivo

Crear un Compose exclusivo para la infraestructura del laboratorio, ponerlo en marcha y comprobar cada servicio. Las aplicaciones futuras se conectarán a la red compartida, con sus propios Compose.

### Objetivos

- Probar publicación/consumo, SQL, SMTP y ejecución programable.
- Conservar datos al recrear contenedores.
- Dejar instrucciones repetibles para operar y conectar aplicaciones.

## Datos necesarios para un informe completo

- [x] Alcance y prueba autorizados por el usuario.
- [x] Laboratorio local sin requisito de alta disponibilidad.
- [x] Docker Desktop y Compose disponibles; acceso al daemon requiere la elevación permitida del entorno.
- [x] Puertos ocupados identificados; credenciales generadas localmente.
- [x] Criterios verificables definidos en la spec.
- [x] Tarifas no confirmadas identificadas como supuestos, sin bloquear la prueba autorizada.

## Análisis de impacto

`infra/` es nuevo. `.gitignore` incorporará secretos y artefactos locales. `docs/roadmap/` guardará evidencia de revisión y QA. No se modifica código de emailing ni servicios existentes.

## Cambios arquitectónicos

El broker sigue ADR-001. La infraestructura tendrá una única red estable `emailing-lab`, declarada externa por las aplicaciones. Cada futura aplicación tendrá sus propias credenciales y vhost. Este laboratorio no implementa alta disponibilidad.

Cronicle se construye desde su código oficial fijado a `v0.9.137`, con Node compatible (≥22.12). El bootstrap inicializa almacenamiento una sola vez y sustituye la contraseña de administrador por el secreto local. No expone Docker socket. El hostname estable permite recrear Cronicle sin perder su identidad.

## Archivos a crear/modificar

| Archivo | Acción | Propósito |
|---|---|---|
| `infra/compose.yaml` | Crear | Cuatro servicios, red, persistencia y puertos locales |
| `infra/.env.example`, `infra/.env` | Crear | Ejemplos y secretos locales ignorados |
| `infra/cronicle/**` | Crear | Dockerfile, bootstrap y configuración mínimos |
| `infra/init.ps1` | Crear | Preparación repetible sin mostrar secretos |
| `infra/smoke.ps1` | Crear | Pruebas funcionales acotadas de infraestructura |
| `infra/README.md` | Crear | Operación y conexión de aplicaciones |
| `.gitignore` | Modificar | Ignorar secretos y resultados locales |
| `docs/roadmap/2026-10-21-infra-laboratorio/**` | Actualizar | Ledger, revisión y pruebas |
| `docs/roadmap/README.md` | Actualizar | Índice de iniciativa |

## Dependencias y prerequisitos

Docker Desktop operativo y descarga de imágenes/código oficial. Defaults comprobados por el coordinador: `rabbitmq:4.4.0-management`, `mysql:8.4.11`, `axllent/mailpit:v1.27.4`; implementer confirma disponibilidad real antes del build. No hay contrato OpenAPI propio que validar.

## Criterios de aceptación (global)

- [x] Cumplir CA-01 a CA-07 de [spec.md](spec.md).
- [x] `docker compose ... config --quiet` y smoke finalizan con código 0.
- [ ] Revisión y QA registran resultados reales, sin afirmar pruebas no ejecutadas.

## Riesgos y mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|
| Conflictos de puertos | Media | Medio | MySQL 3308, Mailpit 11025/18025; no tocar servicios ajenos |
| Inicialización repetida de Cronicle | Media | Medio | Bootstrap idempotente, hostname y volumen estables |
| Secretos en consola | Media | Alto | `.env` ignorado, no volcar `compose config` completo ni contraseñas |
| Permisos del daemon | Media | Medio | Elevación del comando Docker, sin cambiar permisos del sistema |
| Pérdida de datos en prueba | Baja | Medio | Recrear sin `down -v`, no borrar volúmenes |

## Métricas de éxito

Cuatro servicios en ejecución y funcionales; tres UIs accesibles; un mensaje consumido, una consulta SQL, un email recibido y un job Cronicle correcto. Persistencia demostrada tras recreación.

## Changelog del plan

| Fecha | Cambio | Autor |
|---|---|---|
| 2026-10-21 | Plan acotado a la prueba de infraestructura solicitada | planner |
| 2026-10-21 | Cerrado tras ejecución real, persistencia, revisión y QA verde; 3/3 tareas | qa |

## Siguiente paso

Infraestructura creada, ejecutada y verificada: 3/3 tareas completadas, cuatro servicios Healthy, E2E 3/3 y qa-gate VERDE. Véase [informe QA](testing/report.md). La aprobación no se extiende al producto completo. No se creó rama porque `.git` es de solo lectura y el repositorio no tiene commit inicial; la trazabilidad queda en el ledger.
