# Plan de pruebas — Infraestructura compartida del laboratorio

| Campo | Valor |
|---|---|
| **Estado** | ejecutado ? VERDE |
| **Plan** | [improvement-plan.md](improvement-plan.md) |
| **URL local** | RabbitMQ `http://127.0.0.1:15672`, Cronicle `http://127.0.0.1:3012`, Mailpit `http://127.0.0.1:18025` |

## Automáticos (E2E — QA con navegador disponible)

### E2E-01 — Acceso a las interfaces existentes

- **Objetivo**: Verificar que los servicios ofrecen sus UIs y que Cronicle funciona con la credencial generada.
- **Precondiciones**: Compose arrancado; secretos disponibles solo en `.env` local.
- **Pasos**:
  1. Abrir RabbitMQ e iniciar sesión con el usuario de laboratorio.
  2. Abrir Cronicle, iniciar sesión y localizar el trabajo finito de prueba.
  3. Abrir Mailpit y consultar el mensaje generado por smoke.
- **Aserciones**:
  - [x] RabbitMQ muestra el broker disponible.
  - [x] Cronicle muestra el trabajo con resultado correcto.
  - [x] Mailpit muestra destinatario, asunto y cuerpo de prueba.
- **Capturas esperadas**: paneles sin secretos; no capturar formularios con contraseñas visibles.
- **Cubre tareas**: T-01, T-02, T-03

## API y protocolos — smoke autorizado por la prueba solicitada

### API-01 — Operaciones funcionales y persistencia

- **Método y ruta**: `infra/smoke.ps1`, usando APIs RabbitMQ Management, Mailpit y Cronicle; SQL y SMTP por sus protocolos.
- **Status esperado**: exit 0; respuestas HTTP correctas según operación, job finito finalizado sin error.
- **Aserción sobre el body**: mensaje RabbitMQ recuperado con su identificador; fila SQL consultable; email identificable en Mailpit; ejecución Cronicle con código 0.
- **Persistencia**: guardar marcadores antes de `up -d --force-recreate`; comprobar esos mismos datos después, además de repetir smoke. No usar `down -v`.
- **Cubre tareas**: T-01, T-02, T-03

## Manuales

### M-01 — Operación y conexión de aplicaciones

- **Qué revisar**: README, correspondencia entre URLs y Compose, instrucciones de credenciales y ejemplo de red `external: true`.
- **Por qué no se automatiza**: comprobar claridad y suficiencia para operar el laboratorio.
- **Criterio de aceptación**:
  - [x] Se puede arrancar/parar el proyecto sin afectar otros contenedores; no hay secretos reales en ejemplos.
- **Cubre tareas**: T-01, T-03

## Cobertura (tarea → escenarios)

| Tarea | Cubierta por |
|---|---|
| T-01 | E2E-01, API-01, M-01 |
| T-02 | E2E-01, API-01 |
| T-03 | E2E-01, API-01, M-01 |

Resultados reales y l?mites: [informe QA](testing/report.md).
