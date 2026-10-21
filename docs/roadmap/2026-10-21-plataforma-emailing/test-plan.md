# Plan de pruebas — Plataforma interna de emailing

| Campo | Valor |
|---|---|
| Estado | borrador; ningún resultado implícito |
| Plan | [improvement-plan.md](improvement-plan.md) |
| URL local | `http://127.0.0.1:8080` o puerto publicado del Compose propio; registrar valor efectivo |
| Datos | MySQL **emailing_testing** exclusivamente; guard rechaza otras bases antes de migrar/refrescar |
| Automatización | QA implementa Playwright; pruebas backend/concurrencia y protocolos complementan E2E |

Los casos futuros no se ejecutan ni se declaran superados durante C-01. Fase 1 ejecuta E2E-01 a E2E-04 y sus pruebas Feature. Conservar `lab_smoke` en `emailing`; nunca `RefreshDatabase` contra esa base. Fixture: owner A, teammate compartido A/B, owner B y usuario ajeno; roles y scopes distintos según cuenta. Mailpit recoge correo de prueba, sin correo externo. Secretos y tokens no se incluyen en capturas ni reportes.

## Automáticos (E2E — QA con Playwright)

### E2E-01 — Registro, acceso y recuperación

- **Objetivo**: CA-01/12/16; acceso protegido y registro interno configurable.
- **Precondiciones**: aplicación activa, testing DB aislada y Mailpit disponible.
- **Pasos**:
  1. Abrir panel sin sesión, registrar persona permitida y comprobar cuenta creada.
  2. Cerrar sesión, iniciar con contraseña válida e intentar acceso inválido.
  3. Recuperar acceso mediante Mailpit, consumir token y probar reutilización.
  4. Deshabilitar registro mediante configuración de fixture y probar alta.
- **Aserciones**:
  - [ ] Sin sesión no se exponen recursos; errores no revelan claves.
  - [ ] Cuenta/membresía owner únicas; login/logout/reset correctos y alta deshabilitada rechazada.
  - [ ] CSRF se comprueba por prueba HTTP negativa complementaria; no se deduce del formulario.
- **Capturas esperadas**: registro, panel y confirmación reset sin token visible.
- **Cubre tareas**: T-01, T-02, T-04, T-20.

### E2E-02 — Selector y aislamiento de cuentas

- **Objetivo**: CA-13/15/18; contexto vigente, sin arrastre de permisos.
- **Precondiciones**: identidad con membresía Admin A y Read-only B; ajeno C.
- **Pasos**:
  1. Iniciar sesión, seleccionar A y luego B; comparar acciones permitidas.
  2. Alterar identificador de cuenta/ruta y payload Livewire hacia C.
  3. Revocar membresía activa y repetir una acción en sesión ya abierta.
- **Aserciones**:
  - [ ] Solo cuentas vigentes aparecen y contexto no acepta identificadores arbitrarios.
  - [ ] Se deniegan accesos cruzados y permisos antiguos; no se modifica C.
- **Capturas esperadas**: selector y denegación legible.
- **Cubre tareas**: T-02, T-03, T-04, T-20.

### E2E-03 — Invitación de Teammate

- **Objetivo**: CA-17 y aceptación de identidad prevista.
- **Precondiciones**: owner A, correo invitado y otro usuario; reloj controlable para caducidad.
- **Pasos**:
  1. Invitar, capturar email Mailpit y aceptar como destinatario previsto.
  2. Repetir aceptación y probar identidad de otro email.
  3. Probar invitación caducada, revocada y reenviada con token antiguo.
- **Aserciones**:
  - [ ] Una sola membresía; rechazos no crean membresías adicionales.
  - [ ] Prueba MySQL complementaria de aceptación concurrente y hash de token.
- **Capturas esperadas**: listado de invitaciones y membresía aceptada, sin enlaces secretos.
- **Cubre tareas**: T-03, T-04, T-20.

### E2E-04 — Roles, owner y revocación

- **Objetivo**: CA-13/15/20; permisos centrales y auditoría.
- **Precondiciones**: Admin, Read-only, Restricted con presets distintos y owner protegido.
- **Pasos**:
  1. Intentar gestión de miembros y ampliación de scopes desde cada rol.
  2. Alterar petición directa aunque la UI oculte el botón.
  3. Revocar/reducir membresía, repetir acción y consultar auditoría como Admin.
  4. Intentar eliminar/degradar owner y transferir conforme a política protegida.
- **Aserciones**:
  - [ ] Read-only/Restricted no elevan privilegios; catálogo de scopes rechaza valores ajenos.
  - [ ] Último owner/Admin protegido; recursos permanecen y actor/cuenta/acción se auditan.
- **Capturas esperadas**: permisos y auditoría depurada.
- **Cubre tareas**: T-03, T-04, T-20.

### E2E-05 — Contactos, listas e importación

- **Objetivo**: CA-02/03/13; recursos compartidos y consentimiento.
- **Precondiciones**: cuentas A/B y CSV válido, repetido, inválido y sobre límite.
- **Pasos**:
  1. Crear contacto/lista como owner y consultarlos como teammate autorizado.
  2. Importar CSV y revisar resumen por fila/consentimiento.
  3. Intentar listar/exportar/modificar recursos de B y repetir sin scope.
- **Aserciones**:
  - [ ] Deduplicación solo dentro de A, origen/fecha trazables y límites aplicados.
  - [ ] Listados, exportaciones y agregados no mezclan cuentas.
- **Capturas esperadas**: lista compartida y resumen de importación.
- **Cubre tareas**: T-05, T-06, T-09, T-20.

### E2E-06 — Plantilla y preview segura

- **Objetivo**: CA-04/12/13.
- **Precondiciones**: plantilla de A con variables, HTML y scripts hostiles.
- **Pasos**:
  1. Editar variables y abrir preview; observar que el panel no ejecuta scripts.
  2. Intentar acceder desde B y enviar prueba sin permiso.
- **Aserciones**:
  - [ ] Variables admitidas se renderizan según contrato; sandbox no escapa al panel.
  - [ ] Preview y prueba respetan cuenta y scopes.
- **Capturas esperadas**: editor y preview aislada.
- **Cubre tareas**: T-07, T-09, T-20.

### E2E-07 — Configuración SMTP y log privado

- **Objetivo**: CA-07/08/12/14.
- **Precondiciones**: dos cuentas con transportes distintos, Mailpit y log privado.
- **Pasos**:
  1. Guardar configuración y comprobar campos secretos enmascarados.
  2. Probar log, descargar eml con usuario permitido e intentar acceso ajeno.
  3. Probar Mailpit y TLS inválido mediante fixture controlado.
  4. Intentar activar correo externo sin dominio/remitentes verificados; aportar después evidencia DNS simulada y configuración del relay elegido, sin enviar a Internet desde la suite.
- **Aserciones**:
  - [ ] Modo log no abre SMTP, comprobado por integración complementaria.
  - [ ] Solo se informa aceptación SMTP; secretos no aparecen en respuesta/logs.
  - [ ] La habilitación externa exige autenticación de dominio y evidencia válida; Mailpit/log siguen disponibles. La comprobación real de DNS del proveedor se ensaya antes de habilitar su transporte, fuera de C-01.
- **Capturas esperadas**: configuración enmascarada y diagnóstico sin claves.
- **Cubre tareas**: T-08, T-09, T-20.

### E2E-08 — Campaña, fecha efectiva y bajas

- **Objetivo**: CA-03/05/10/11/13/14.
- **Precondiciones**: campaña borrador/futura, contacto común A/B y tokens de baja.
- **Pasos**:
  1. Revisar/programar campaña; ejecutar procesamiento antes y después de fecha efectiva.
  2. Encolar trabajo y dar de baja antes del transporte; repetir baja y manipular token.
  3. Ingerir fixtures de rebote definitivo/queja mediante el mecanismo autenticado del relay elegido; repetir el evento y falsificar su autenticación.
- **Aserciones**:
  - [ ] Borrador/futura no envían antes de tiempo.
  - [ ] Baja idempotente evita pendiente en A y no altera B; token manipulado rechazado.
  - [ ] Feedback válido suprime el destinatario afectado de forma idempotente; un evento falsificado o de otra cuenta se rechaza. Si el relay no ofrece feedback, se informa esa limitación y se verifica la política de habilitación externa acordada.
- **Capturas esperadas**: resumen campaña y confirmación de baja sin datos innecesarios.
- **Cubre tareas**: T-10, T-11, T-16, T-20.

### E2E-09 — Actividad y recuperación de trabajos

- **Objetivo**: CA-06/08/09/14/22/23/25.
- **Precondiciones**: outbox, dos workers Linux y relay SMTP controlable; fixtures de fallos.
- **Pasos**:
  1. Producir aceptado, fallido, suprimido y ambiguo; revisar actividad por cuenta.
  2. Integración complementaria corta broker antes/después de publicar y antes de confirmación.
  3. Matar consumidor entre SMTP/persistencia/ack y ejecutar reconciliación/reentrega.
  4. Disparar acción finita Cronicle con/sin auth y repetir disparo.
  5. Alcanzar límites por cuenta y agregados del relay; pausar una campaña por incidencia y provocar su reentrega. Consultar actividad con feedback ausente y con una muestra pequeña.
- **Aserciones**:
  - [ ] UI refleja estados durables sin afirmar entrega; cuenta/origen preservados.
  - [ ] Sin pérdida silenciosa ni dos reservas válidas; ambiguo sin reenvío automático.
  - [ ] Ack posterior a persistencia, reintentos acotados y cola de fallos demostrados por integración.
  - [ ] Cronicle no crea segundo scheduler ni ejecución permanente; auth obligatoria.
  - [ ] Los límites y la pausa siguen efectivos al reentregar; tasas y alertas conservan fuente, ventana y denominador. Datos insuficientes no se muestran como cero incidencias.
- **Capturas esperadas**: actividad con estados y detalle depurado.
- **Cubre tareas**: T-12, T-13, T-14, T-15, T-16, T-20.

### E2E-10 — Llaves y API idempotente

- **Objetivo**: CA-01/12/13/19/20; contrato validado antes de endpoints.
- **Precondiciones**: OpenAPI válido y Admin/Read-only/Restricted con cuentas diferentes.
- **Pasos**:
  1. Crear llave con scopes permitidos, usarla en envío y repetir clave idempotente.
  2. Revocar y repetir; intentar llave más privilegiada o cuenta ajena.
  3. Pruebas Feature complementarias validan contrato, límites y concurrencia.
- **Aserciones**:
  - [ ] Un trabajo por clave/cuenta; cambio de payload produce conflicto.
  - [ ] Llaves inválidas/revocadas o sin scope rechazadas; secretos no vuelven a mostrarse.
- **Capturas esperadas**: gestión de llaves sin bearer visible.
- **Cubre tareas**: T-17, T-18, T-20.

### E2E-11 — Operación, carga y restauración

- **Objetivo**: CA-21/24/25, con integración de protocolos y reporte de recursos.
- **Precondiciones**: segunda aplicación mínima aislada, entorno de restauración temporal y backups de prueba.
- **Pasos**:
  1. Probar publish/consume/manage ajenos con credenciales separadas.
  2. Ejecutar 5 workers (2/3) y 10 (4/6), con emailing 100–200 mensajes/día simulados y picos fijados antes de medir.
  3. Registrar latencia/pendientes/CPU/RAM/disco/fallos; reiniciar y restaurar en destino aislado.
- **Aserciones**:
  - [ ] ACL deniega topología ajena; recursos compartidos y efectos de carga se documentan.
  - [ ] Se recuperan datos/topología/trabajos conforme a política; no se borran volúmenes existentes.
  - [ ] Parámetros/umbrales/resultados registrados; no se acredita capacidad sin medir.
- **Capturas esperadas**: estado operativo sin credenciales y gráficas de medición real si disponibles.
- **Cubre tareas**: T-19, T-20.

## Manuales (M — revisión por una persona)

### M-01 — Claridad y adaptación del panel español

- **Qué revisar**: formularios, estados vacíos, errores, navegación de cuentas y contraste/legibilidad en móvil y escritorio.
- **Por qué no se automatiza**: juicio de claridad visual y lenguaje.
- **Criterio de aceptación**:
  - [ ] Una persona distingue cuenta activa, permisos y estados sin explicación técnica ni métricas ficticias.
- **Cubre tareas**: T-01, T-02, T-03, T-04, T-05, T-07, T-09, T-11, T-14, T-16, T-20.

### M-02 — Revisión operativa y límites

- **Qué revisar**: instrucciones de Laragon/Compose, backup/restore y estados SMTP ambiguos; no envío externo para probar entrega.
- **Por qué no se automatiza**: comprensión de operación y aceptación de limitaciones del laboratorio.
- **Criterio de aceptación**:
  - [ ] Procedimientos reproducibles sin contraseñas en docs, sin destruir volúmenes y sin confundir SMTP con entrega.
- **Cubre tareas**: T-19, T-20.

## Cobertura (tarea → escenarios)

| Tarea | Cubierta por |
|---|---|
| T-01 | E2E-01, M-01 |
| T-02 | E2E-01, E2E-02, M-01 |
| T-03 | E2E-02, E2E-03, E2E-04, M-01 |
| T-04 | E2E-01, E2E-02, E2E-03, E2E-04, M-01 |
| T-05 | E2E-05, M-01 |
| T-06 | E2E-05 |
| T-07 | E2E-06, M-01 |
| T-08 | E2E-07 |
| T-09 | E2E-05, E2E-06, E2E-07, M-01 |
| T-10 | E2E-08 |
| T-11 | E2E-08, M-01 |
| T-12 | E2E-09 |
| T-13 | E2E-09 |
| T-14 | E2E-09, M-01 |
| T-15 | E2E-09 |
| T-16 | E2E-08, E2E-09, M-01 |
| T-17 | E2E-10 |
| T-18 | E2E-10 |
| T-19 | E2E-11, M-02 |
| T-20 | E2E-01–11, M-01, M-02 |

## Trazabilidad CA → pruebas verificables

| CA | Prueba principal y complemento |
|---|---|
| CA-01 | E2E-01/10; Feature auth/CSRF y API |
| CA-02 | E2E-05; ContactImportTest |
| CA-03 | E2E-05/08; ContactListTest/CampaignTest |
| CA-04 | E2E-06; TemplateTest |
| CA-05 | E2E-08/09; CampaignTest/SchedulerTriggerTest |
| CA-06 | E2E-09; WorkerRecoveryTest/OutboxIntegrationTest |
| CA-07 | E2E-07; MailTransportTest sin apertura SMTP |
| CA-08 | E2E-07/09; relay Mailpit y ActivityTest |
| CA-09 | E2E-09; WorkerRecoveryTest con aceptación ambigua |
| CA-10 | E2E-08; SuppressionTest |
| CA-11 | E2E-08; SuppressionTest token alterado |
| CA-12 | E2E-01/06/07/10; secretos y descargas negativas |
| CA-13 | E2E-02/04/05/06/10; matriz CRUD/listado/export/agregado |
| CA-14 | E2E-07/08/09; worker sin sesión y cuentas distintas |
| CA-15 | E2E-02/04; Teammate tests y auditoría |
| CA-16 | E2E-01; AccountAuthenticationTest |
| CA-17 | E2E-03; Teammate tests concurrentes MySQL |
| CA-18 | E2E-02; AccountAuthenticationTest |
| CA-19 | E2E-10; SendingApiTest concurrente |
| CA-20 | E2E-04/10; Teammate/SendingApiTest |
| CA-21 | E2E-11; broker-isolation/RuntimeIntegrationTest |
| CA-22 | E2E-09; OutboxIntegrationTest |
| CA-23 | E2E-09; WorkerRecoveryTest |
| CA-24 | E2E-11; carga cruzada con parámetros/umbrales previos |
| CA-25 | E2E-09/11; reinicio/restauración aislada |

Smokes curl API son opcionales adicionales; el contrato y los tests de API anteriores forman parte del alcance C-07 autorizado. No instalar axe-core ni publicar evidencia externa sin opt-in. Manuales pendientes permanecen pendientes y se reportan como tales.
