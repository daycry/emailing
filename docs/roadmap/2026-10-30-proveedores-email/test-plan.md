# Plan de pruebas — Proveedores de correo

| | |
|---|---|
| **Estado** | borrador |
| **Plan** | [improvement-plan.md](improvement-plan.md) |
| **Spec** | [spec.md](spec.md) |
| **URL local** | Origen loopback propio asignado y registrado por el harness de QA; no reutilizar un servicio del operador |

Se ejecutan seis escenarios originales con Playwright, una vez tras revisión
verde, conservando JSON nativo y fuentes. El harness crea cuenta A/B, personas
con permisos distintos, remitentes y mensajes ficticios, SQL exclusiva
`emailing_testing` y adaptadores/fixtures locales sin salida a proveedores.
El guard comprueba base, usuario, DB_URL y conexión real antes de cualquier DDL.
No se cambia el umbral, assertions o timeout para convertir un fallo en verde.
Los contratos HTTP y SMTP de backend se ejecutan además de estos flujos UI.

## Automáticos (E2E — los ejecuta QA con Playwright)

### E2E-01 — Guardar y alternar los cuatro modos sin devolver secretos

- **Objetivo**: CA-01/03/14/18; configuración de Brevo y SendGrid por SMTP y API con secretos de escritura.
- **Precondiciones**: propietario de cuenta A; flag de salida y perfiles son exclusivamente los de la fixture local.
- **Pasos**:
  1. Abrir ajustes y comprobar el default de laboratorio y las cuatro opciones externas.
  2. Guardar credenciales sintéticas distintas para Brevo SMTP/API y SendGrid SMTP/API; alternar y recargar cada modo.
  3. Guardar con los campos secretos vacíos y comprobar que el indicador de configuración se conserva.
- **Aserciones**:
  - [ ] Los cuatro modos persisten por cuenta; se distinguen claves SMTP/API y autorización externa del remitente.
  - [ ] Secretos guardados ausentes de HTML y snapshots/respuestas Livewire; inputs vacíos tras guardar y recargar.
  - [ ] Alternar proveedor no reutiliza ni modifica el perfil del otro; guardar no inicia una conexión de envío.
- **Capturas esperadas**: opciones, guardado y perfil recargado con indicadores, nunca claves visibles.
- **Cubre tareas**: T-01, T-06.

### E2E-02 — Limpiar y corregir configuración de un único proveedor

- **Objetivo**: CA-01/02/03/14; clear selectivo, validación útil y reintento de guardado.
- **Precondiciones**: perfiles sintéticos de ambos proveedores en cuenta A.
- **Pasos**:
  1. Enviar un dato inválido y comprobar error de campo sin éxito falso.
  2. Corregirlo, guardar y confirmar que el error anterior desaparece.
  3. Limpiar explícitamente una credencial de Brevo y recargar; inspeccionar también SendGrid.
- **Aserciones**:
  - [ ] La validación conserva el estado anterior y permite corregir; el éxito describe sólo lo persistido.
  - [ ] La limpieza afecta al secreto/proveedor elegido; SendGrid permanece configurado y ninguna clave reaparece.
  - [ ] Un perfil incompleto bloquea envío externo antes de red, con explicación recuperable.
- **Capturas esperadas**: error, corrección y estado de credencial eliminada.
- **Cubre tareas**: T-01, T-06.

### E2E-03 — Aislar cuentas y revalidar permisos de ajustes/remitentes

- **Objetivo**: CA-02/03/14; UI y acciones respaldadas por autorización del servicio.
- **Precondiciones**: propietario de A/B y colaborador restringido; datos ficticios distintivos por cuenta.
- **Pasos**:
  1. Cambiar entre A/B y comprobar proveedor, indicadores y remitentes de cada cuenta.
  2. Acceder como colaborador sin escritura y tratar de guardar, limpiar o autorizar remitente.
  3. Retirar permiso a una sesión ya abierta e intentar una mutación pendiente.
- **Aserciones**:
  - [ ] No se filtran ajustes, remitentes o mensajes de la cuenta ajena.
  - [ ] Acciones sin permiso vigente producen denegación y cero escrituras; ocultar un botón no es la única protección.
  - [ ] Autorizar laboratorio no autoriza salida externa; el flag/perfil incompleto impide IO.
- **Capturas esperadas**: cuenta B aislada y vista restringida/denegada.
- **Cubre tareas**: T-01, T-06.

### E2E-04 — Enviar por los cuatro canales y distinguir aceptación de entrega

- **Objetivo**: CA-03/04/06/13/14/15; selección efectiva y proyección coherente.
- **Precondiciones**: remitente externamente autorizado en fixture; SMTP/HTTP locales controlados, sin claves del operador.
- **Pasos**:
  1. Ejecutar una prueba explícita por cada modo externo con su fixture de aceptación.
  2. Consultar los cuatro mensajes y el estado agregado del trabajo durable preparado por la fixture.
  3. Cambiar a log/Mailpit y ejecutar sus comprobaciones de regresión.
- **Aserciones**:
  - [ ] Cada mensaje muestra proveedor/canal correcto; aceptación SMTP y API tienen etiquetas diferentes.
  - [ ] No se afirma entrega sin evento autenticado posterior ni se ocultan estados API en los contadores.
  - [ ] Las llamadas observadas por el harness usan sólo la fixture prevista; log no abre red y Mailpit conserva captura de laboratorio.
- **Capturas esperadas**: resultados SMTP/API y modos de laboratorio.
- **Cubre tareas**: T-01, T-02, T-03, T-04, T-06.

### E2E-05 — Mostrar rechazo, rate limit e incertidumbre sin duplicar

- **Objetivo**: CA-05/07/08/14/15/16; errores normalizados y recuperación segura.
- **Precondiciones**: fixtures de negativo SMTP, HTTP429/4xx, timeout/confirmación perdida y captura del número de llamadas.
- **Pasos**:
  1. Ejecutar pruebas de rechazo permanente y temporal; consultar resultado y espera prevista.
  2. Provocar resultado API/SMTP incierto después de posible aceptación y recargar actividad.
  3. Cambiar proveedor mientras el intento fixture está en curso y completar el resultado anterior.
- **Aserciones**:
  - [ ] Diagnóstico legible y cerrado, sin credenciales/cuerpo remoto; resultado incierto permanece ambiguo.
  - [ ] Sin retry HTTP oculto, failover ni segunda llamada por recarga/redelivery terminal; sólo negativa transitoria demostrada permite reprogramar dentro del límite y `Retry-After`.
  - [ ] El intento conserva cuenta/proveedor/correlación originales y una reserva obsoleta no sobrescribe el resultado vigente.
- **Capturas esperadas**: rechazo, espera de429 y estado ambiguo.
- **Cubre tareas**: T-02, T-04, T-05, T-06.

### E2E-06 — Consultar feedback autenticado, supresiones y operar por teclado

- **Objetivo**: CA-09–CA-14/17/18; feedback separado, aislamiento y navegación.
- **Precondiciones**: fixtures de firma SendGrid y Bearer Brevo; eventos duplicados, alterados, fuera de orden y de otra cuenta/proveedor.
- **Pasos**:
  1. Aplicar evento legítimo correlacionado; repetirlo y enviar una variante alterada/no autorizada.
  2. Aplicar queja/rebote permanente/baja, seguido de entrega antigua; probar otro envío al destinatario suprimido.
  3. Navegar ajustes/actividad con teclado, corregir un error y comprobar viewport estrecho.
- **Aserciones**:
  - [ ] Sólo eventos autenticados/correlacionados modifican feedback; duplicado tiene un efecto y no cruza cuentas.
  - [ ] La aceptación original/procedencia se conserva; la supresión permanece y el nuevo envío se bloquea antes de IO.
  - [ ] Campos con nombres accesibles, foco visible y acciones alcanzables; errores/success distinguibles y sin pérdida de controles en viewport estrecho.
- **Capturas esperadas**: feedback junto a aceptación, supresión y formulario/error con foco.
- **Cubre tareas**: T-02, T-03, T-04, T-05, T-06.

## Verificaciones de backend y contrato

Son pruebas automatizadas de servicio/contrato, no un segundo cómputo de E2E.
No se suman suites solapadas ni se declara porcentaje de cobertura inexistente.

| CA GWT de la spec | Primer oráculo/negativos obligatorios | Tareas |
|---|---|---|
| CA-01 | Test nuevo rojo de perfiles cifrados, serialización y blank/clear; verde con los cuatro modos | T-01 |
| CA-02 | Cuenta ajena, ID manipulado y permiso retirado entre apertura y guardado | T-01, T-06 |
| CA-03 | Flag/perfil/remitente incompletos: cero red; log/Mailpit conservados | T-01, T-06 |
| CA-04 | SMTP observado, autenticación por proveedor, TLS y presupuestos con fixture local | T-03 |
| CA-05 | Negativo4xx/5xx y corte tras terminador; sólo rechazo temporal probado permite retry | T-03, T-06 |
| CA-06 | Payload/autenticación/URL de cada API y estado `api_accepted` independiente | T-04 |
| CA-07 | HTTP400/401/403/422/429/5xx, cuerpo malformado/excesivo, timeout y una llamada sin failover | T-04, T-06 |
| CA-08 | Snapshot, reserva caducada y callback anterior al resultado; finalización con fencing | T-02, T-04, T-06 |
| CA-09 | Firma ECDSA sobre rawbody/timestamp, clave incorrecta, byte modificado y ventana temporal | T-05 |
| CA-10 | Bearer Brevo correcto/incorrecto, cuerpo/lote acotados y cero secreto en salida | T-05 |
| CA-11 | Dedupe concurrente y secuencial, `id` Brevo repetido para eventos distintos, cuenta/proveedor/referencia incorrectos | T-05 |
| CA-12 | Rebote permanente/queja/baja suprimen; temporal no suprime y entrega tardía no revierte | T-05, T-06 |
| CA-13 | Cuatro modos por campañas/API/MCP, cuota/outbox/permiso/supresión/idempotencia y redelivery | T-06 |
| CA-14 | Feature Livewire más seis recorridos de UI, teclado y estados corregibles | T-01, T-06 |
| CA-15 | Diff OpenAPI previo, JSON Schema/pins y proyección real de estados/contadores | T-02, T-06 |
| CA-16 | Endpoint ajeno, redirect, certificado no confiable, presupuesto excedido y diagnóstico cerrado | T-02, T-03, T-04, T-06 |

CA-17 exige revisión fresca y QA con fuentes/evidencias originales; CA-18 exige
documenter y guías que distingan contrato/fixture local de proveedor real. No se
convierten esos dos criterios de calidad/documentación en GWT ficticios.

## Manuales (los realiza una persona)

### M-01 — Cualificar proveedores reales en el destino privado

- **Qué revisar**: cuenta, dominio/remitentes autorizados por cada proveedor, SPF/DKIM/DMARC, claves SMTP/API distintas, callback HTTPS y autenticación configurada; realizar una prueba por cada canal con destinatario autorizado y comprobar feedback.
- **Por qué no se automatiza aquí**: requiere credenciales/cuentas del operador, DNS/destino privado y autorización de envío concreto. Fixtures locales no acreditan disponibilidad comercial o entrega real.
- **Criterio de aceptación**:
  - [ ] Las cuatro combinaciones tienen evidencia privada propia de envío y evento, sin publicarse credenciales, direcciones personales o cuerpos reales.
  - [ ] Rotación/revocación conserva la semántica documentada y los eventos en vuelo; una clave retirada no se acepta silenciosamente.
- **Estado**: pendiente; no bloquea implementar o aceptar QA local y no se marca realizado por simulaciones.
- **Cubre tareas**: T-03, T-04, T-05, T-06.

### M-02 — Revisión humana de operación y cierre

- **Qué revisar**: legibilidad de guías/formularios, distinción aceptación/entrega y política de resultados ambiguos; retro cuando se soliciten sus datos reales.
- **Por qué no se automatiza**: juicio humano y causas de desviación no aportadas no se inventan.
- **Criterio de aceptación**:
  - [ ] Operador confirma sus resultados privados y decisiones de uso; el workflow conserva la puerta de retro sin simular aprobación humana.
- **Estado**: pendiente.
- **Cubre tareas**: T-06.

## Cobertura (tarea → escenarios)

| Tarea | Cubierta por |
|---|---|
| T-01 | E2E-01, E2E-02, E2E-03, E2E-04 |
| T-02 | E2E-04, E2E-05, E2E-06 |
| T-03 | E2E-04, E2E-06, M-01 |
| T-04 | E2E-04, E2E-05, E2E-06, M-01 |
| T-05 | E2E-05, E2E-06, M-01 |
| T-06 | E2E-01–E2E-06, M-01, M-02 |

## Puerta de QA y limpieza

QA congela fuentes y ejecuta los seis originales con su JSON nativo; el
`qa-gate.py` del plugin emite el veredicto, no una impresión visual ni un resumen
reconstruido. Conserva escenarios, resultados, capturas e informe. Verifica por
identidad los fixtures propios y cierra sólo sus procesos/puertos. No enumera
procesos globalmente ni elimina recursos compartidos. Cualquier fallo de
limpieza mantiene pendiente la aceptación aunque el navegador aparezca verde.


## Ejecucion tecnica local

[QA3](testing/qa-3/report.md):6/6originales,gate0,0fallos/flaky/omitidos/interrumpidos,unaejecucion/retries0; fuentes507+bundle antes/despuesidenticos y cleanupcompleto. [QA1](testing/qa-1/report.md) y [QA2](testing/qa-2/report.md) rojos se conservan. [Backend](testing/acceptance.md) detalla tandas originales sin sumar solapadas. M-01proveedorreal y M-02humana/retro siguenpendientes; no se acreditan con fixtures.


## Validacion del manual T07 (CA-19 a CA-22)

Esta ampliacion es QA de artefacto documental, no escenarios Playwright nuevos. Se conserva browserQA3 original para el runtime sin cambios.

1. Contrastar inventario de interfaces/configuracion y matriz documental con fuentes publicas; 37 herramientas y 71 variables por identidad exacta.
2. Verificar la misma fuente Markdown/PDF, todos sus bloques y etiquetas Mermaid, paginas A4, TOC/paginacion y referencias estables sin renderer temporal.
3. Comprobar enlaces y anchors de todas las guias actuales.
4. Renderizar e inspeccionar todas las paginas individualmente, ademas de hojas de contacto; corregir cualquier recorte, solape o ilegibilidad antes de publicar.

Resultado: [QA independiente](testing/manual/qa-1/report.md) VERDE; 57paginas,919/919bloques,8diagramas,26referencias,34MD/1015links0errores. CA19-22 cubiertos por revision documental2 y esta QA. Instalacion real, proveedores externos M-01 y retro humana M-02 se mantienen pendientes; no se fabrican E2E para sustituirlos.
