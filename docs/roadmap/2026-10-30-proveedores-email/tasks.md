---
# ventana compartida con improvement-plan.md
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
verificacion: obligatoria
---

# Checklist de Tareas — Proveedores de correo

| | |
|---|---|
| **Estado** | en-progreso |
| **Fecha** | 2026-10-30 |
| **Plan** | [improvement-plan.md](improvement-plan.md) |
| **Diseño** | n/a; decisiones heredadas de la spec aprobada |

> **⚠️ Ledger canónico de progreso.** Este fichero es la **fuente única de verdad** del avance del plan. **Cualquier** implementador —el agente `implementer`, el chat principal, o un orquestador SDD externo— **debe** marcar aquí cada tarea (checkbox + estado) al completarla y actualizar el resumen. Los ledgers propios de otras herramientas son **espejo**, no fuente.

El usuario autorizó implementar Brevo y SendGrid por SMTP y API, con código
limpio y robusto, manteniendo el flujo completo ya elegido. Las características
C-01–C-06 y los18CA están aprobados. Todas las verificaciones siguientes son
**planeadas**, no ejecutadas por planner. La aceptación técnica local y la
validación privada real son resultados distintos. Principal18/20 y MCP11/14 no
cambian por crear este plan.

El presupuesto es base:104h y864000 tokens; margen global20 % una vez. C-05 se
divide en T-02(4h/40000tokens) y T-06(10h/80000tokens); C-06 aporta las otras18h
y168000tokens de T-06. No hay reestimación silenciosa. Consumo real desconocido.

## Resumen de progreso

| Fase | Completadas | Total | Progreso | H. humanas (real/est) | H. IA ejec. (real/est) | Supervisión (real/est) | Tokens (real/est) |
|---|---|---|---|---|---|---|---|
| Fase 1 — Configuración y contrato común | 2 | 2 | 100.0% | — /16h | — /0.453333h | — /0.113333h | — /136000 |
| Fase 2 — Transportes y feedback | 0 | 3 | 0% | — /60h | — /1.6h | — /0.4h | — /480000 |
| Fase 3 — Integración, UI y validación | 0 | 1 | 0% | — /28h | — /0.826667h | — /0.206667h | — /248000 |
| **TOTAL** | **2** | **6** | **33.3%** | **— /104h** | **— /2.88h** | **— /0.72h** | **— /864000** |

Jira/Confluence no están activados para esta entrega. Las horas IA proceden del
ratio supuesto300000tok/h y no miden el reloj de ejecución. El campo Changelog
se rellena al cerrar cada tarea, describiendo el cambio para quien usa el producto.

## Fase 1 — Configuración y contrato común

**Estado**: completado · **Estimado**:16h base · **Real**:— · **Coste est.**:800 € equivalentes+tokens por verificar · **Tokens est.**:136000

### T-01 — Persistir configuración y autorización externa por cuenta/proveedor

- **Descripción**: Implementar C-01, CA-01–CA-03: perfiles independientes para Brevo/SendGrid, secretos cifrados/ocultos, cuatro modos y habilitación explícita antes de red. Es capacidad nueva; primer oráculo: test nuevo rojo de guardado/aislamiento sin implementación.
- **Partida**: C-01 completa de evaluation.md, sin reestimación.
- **Estado**: completado
- **Tiempo humano**: est.12h · real—
- **Tiempo IA (ejec.)**: est.0.32h (estimado por tokens) · real—
- **Supervisión**: est.0.08h (25 % IA) · real—
- **Previsión IA**:80000in/16000outtok · `0.08p_in +0.016p_out` €; tarifas por verificar
- **Coste estimado**:600 € equivalentes base+tokens; margen global una vez.
- **Dependencias**: ninguna
- **Tipo**: backend
- **Archivos**: `app/Models/MailSetting.php`, `app/Models/MailSender.php`, `app/Models/MailProvider*.php`, `app/Services/MailSettingsService.php`, `app/Livewire/MailSettings.php`, `app/Services/MailProviders/**`, `app/Support/MailTransport*.php`, `app/Enums/MailTransportMode.php`, `database/migrations/2026_10_30_*_mail_provider*.php`, `config/emailing.php`, `.env.example`, `tests/Feature/MailProviderSettingsTest.php`, `tests/Feature/MailTransportTest.php`, `tests/Concerns/MailProviderFixtures.php`, `tests/Unit/MailProviders/**`
- **Cubre (tests)**: E2E-01, E2E-02, E2E-03, E2E-04
- **Capacidades**: api-contract, backend-practices, delivery-practices, frontend-quality, stack-practices, unit-tests
- **Procedencia de capacidades**: selector custom-agents2.3.0 sobre manifiestos PHP, áreas api/delivery/frontend/testing; unit-tests heredada del selector implementer. Sin avisos/extensiones de proyecto. Perfil normal; TDD/worktree/subagentes de implementación desactivados; root implementa y mantiene revisión/QA frescas.
- **Verificación**: `php vendor/bin/phpunit tests/Feature/MailProviderSettingsTest.php` → guarda por cuenta/proveedor, permisos revocados, cifrado/serialización, blank-preserve y clear selectivo correctos; `php vendor/bin/phpunit tests/Feature/MailTransportTest.php` → modos de laboratorio y preparación existente conservados. Antes de DDL, guarda real de base/usuario `emailing_testing`, DB_URL vacío y ventana exclusiva.

**Criterios de aceptación**

- [x] CA-01: claves SMTP/API y autenticación de webhook persisten cifradas por `(account_id,provider)` y nunca vuelven en HTML, estado Livewire, serialización, auditoría o diagnóstico.
- [x] CA-02: guardar, alternar, limpiar o revocar ajustes exige permisos vigentes de la cuenta; no lee/modifica configuración ajena ni mezcla credenciales de Brevo/SendGrid.
- [x] CA-03: default `log`, Mailpit existente y flag externo deshabilitado por defecto; configuración/habilitación/remitente externos incompletos bloquean antes de IO.
- [x] La autorización externa del remitente es independiente de `lab_authorized`; no se afirma verificación DNS por un checkbox. Migración expansiva compatible ensayada sólo sobre testing.

**Subtareas**

- [x] Observar el test nuevo rojo y conservar su comando/resultado antes del código.
- [x] Añadir persistencia, modos y validación por proveedor; definir clear explícito y blank que conserva valores.
- [x] Probar aislamiento y revocación de permisos por servicio y por acciones Livewire, sin claves en propiedades públicas.

**Notas**: Fuente: encargo del usuario y spec/configuración. Referencias: `MailSettingsService::writableAccount/saveSettings`, `MailSetting::casts`, `MailSettings::saveSettings`. Primer oráculo concreto: un test nuevo llama al servicio existente `saveSettings` con `mode=brevo_smtp`, campos legados válidos y flag externo deshabilitado; el baseline rechaza el modo antes de crear clases nuevas. El resultado esperado tras implementar es selección preparada por cuenta sin IO, manteniendo bloqueo de salida si falta configuración. Los tests UI corresponden a T-06, sin dependencia circular. Modelo previsto `MailProviderConnection` con UUID por cuenta/proveedor y enum `MailTransportMode`. Ya existe cifrado SMTP; se adapta su patrón, sin usar campos genéricos de un proveedor para autenticar otro. Guardar no realiza llamadas externas ni sustituye flag/autorización de remitente.


RED: `tests/Feature/MailProviderSettingsTest.php::test_an_external_mode_can_be_prepared_without_enabling_or_connecting_it` falló con Baseline: 1 caso, 1 error de validacion al seleccionar brevo_smtp; guarda real testing correcta, sin envio, fuentes estables. · 2026-10-30.


- **Changelog**: Configuración cifrada de Brevo y SendGrid por cuenta y autorización independiente de remitentes externos.
- **Resultado de implementación**: RED nuevo con ValidationException de mode; tras implementar,20 casos/265 aserciones (9 nuevos y11 de regresión) sin fallos/errores/omitidos, identidad SQL real exclusiva y451 fuentes estables. Primera tanda ampliada detenida por un error sintáctico del test nuevo, corregido antes de la tanda verde; no se presenta como fallo funcional del producto. Revisión/QA global de la iniciativa pendientes.

### T-02 — Fijar contratos y resultado común antes de los adaptadores

- **Descripción**: Primera parte C-05,4h: contrato de modo/correlación/resultado y ampliaciones aditivas necesarias de API/MCP/webhooks antes del código de endpoints. CA-08/15/16. Capacidad nueva: test nuevo rojo que distingue aceptación API y fija sus esquemas.
- **Partida**: C-05,4h/40000tokens de14h/120000; resto reservado a T-06.
- **Estado**: completado
- **Tiempo humano**: est.4h · real—
- **Tiempo IA (ejec.)**: est.0.133333h (estimado por tokens) · real—
- **Supervisión**: est.0.033333h (25 % IA) · real—
- **Previsión IA**:33333in/6667outtok · `0.033333p_in +0.006667p_out` €; tarifas por verificar
- **Coste estimado**:200 € equivalentes base+tokens.
- **Dependencias**: T-01
- **Tipo**: backend
- **Archivos**: `app/Support/MailTransport*.php`, `app/Support/Transport*.php`, `app/Services/MailProviders/**`, `app/Models/MailMessage.php`, `database/migrations/2026_10_30_*_mail_provider*.php`, `docs/api/openapi.yaml`, `docs/api/mcp-tools.json`, `docs/api/mcp-oauth.openapi.json`, `.gitattributes`, `docs/api/mail-provider-webhooks.openapi.json`, `docs/api/README.md`, `scripts/check-mcp-contract.py`, `app/Services/Mcp/**`, `tests/Contracts/fixtures/mcp-v0.2.0/**`, `tests/Contracts/fixtures/mcp-v0.1.0/manifest.json`, `tests/Unit/MailProviders/**`, `tests/Feature/MailProviderSchemaTest.php`, `tests/Unit/McpContractArgumentsTest.php`, `tests/packaging/**`, `tests/python/mcp_profile/**`
- **Cubre (tests)**: E2E-04, E2E-05, E2E-06
- **Capacidades**: api-contract, backend-practices, delivery-practices, frontend-quality, stack-practices, unit-tests
- **Procedencia de capacidades**: misma selección compartida de T-01; api-contract gobierna estructura/diff de OpenAPI y el pin conserva los bytes reales de contratos.
- **Verificación**: lector oficial `openapi-lint.py` del bundle privado2.3.0 sobre `docs/api/openapi.yaml` y `docs/api/mail-provider-webhooks.openapi.json`, más `--diff` base/propuesta → sin errores ni cambios rompedores no resueltos antes del endpoint; `python scripts/check-mcp-contract.py` → contratos/pins coherentes; `php vendor/bin/phpunit tests/Unit/MailProviders` → modos/resultados con diagnóstico cerrado y aceptación API propia. Los nombres de tests nuevos se concretan sin saltar la guarda SQL si cargan aplicación.

**Criterios de aceptación**

- [x] CA-15: contrato y contadores distinguen aceptación API/SMTP sin cambiar el significado histórico; conserva campos existentes y documenta cualquier adición antes del código.
- [x] CA-08: referencia opaca por intento y snapshot del canal/cuenta/configuración tienen contrato definido para persistirse antes de IO; no se serializan secretos en jobs/outbox.
- [x] CA-16: allowlist de endpoints/hosts, TLS, redirects, timeout y límites forman parte de la frontera común; ningún campo externo elige una URL arbitraria.
- [x] El diff OpenAPI y los pins se verifican con procedencia; los fixtures históricos conservan identidad. Si exige una nueva versión de contrato, se crea como ampliación explícita, sin reinterpretar evidencias previas.

**Subtareas**

- [x] Observar el test de contrato nuevo rojo y documentar el estado/contador esperado.
- [x] Escribir/validar esquemas, enums y resultado tipado mínimo; añadir correlación al intento.
- [x] Registrar inventario de referencias SMTP/HTTP y decisión de reutilización; no añadir SDK sin necesidad demostrada.

**Notas**: Fuente: spec/arquitectura y CA-08/15/16. Referencias: `SendingApiService::status`, `ActivityService::STATES`, `Mcp/BusinessDto`, `scripts/check-mcp-contract.py`. DTO previsto `TransportOutcome`: separar atributos persistidos de `retryAfter/retryable` para que esos flags nunca lleguen a `Eloquent::update`; el único caller productivo de `DeliveryLifecycle::finish` es el transporte común. Esta tarea prepara la frontera; la prueba de integración real de C-05 pertenece a T-06, evitando el ciclo T-02↔T-03/T-04. No se alteran silenciosamente contratos fijados por hash.


RED: `tests/Unit/MailProviders/TransportContractTest.php` falló con RED funcional: falta api_accepted en el enum publicado;1 caso/1 asercion/1 fallo, sin SQL ni red; primer intento del test corregido por resolver refs locales. · 2026-10-30.


- **Changelog**: Los contratos distinguen aceptación API, aceptación SMTP y feedback posterior del proveedor.
- **Resultado de implementación**: MCP0.2.0/REST1.1.0,597 fixtures de herramientas,37 operaciones y76 esquemas sin errores; OpenAPI validado y diff sin rompedores detectados. Resultado/snapshot tipados:41 casos/61 aserciones,0 fallos/errores/omitidos. MySQL:1 caso/13 aserciones; columnas, unicidad, escritor antiguo y microsegundos correctos. El primer caso SQL reveló truncado por el dateFormat genérico; setter del campo conserva6 decimales sin cambiar las fechas antiguas. Packaging:3 pruebas contra un índice temporal,13 rutas públicas y tres modos de autocrlf. No se tocó el staging del proyecto ni fixtures0.1.0. API/SMTP y pruebas de integración aún corresponden a tareas posteriores.

## Fase 2 — Transportes y feedback

**Estado**: en-progreso · **Estimado**:60h base · **Real**:— · **Coste est.**:3000 € equivalentes+tokens por verificar · **Tokens est.**:480000

### T-03 — Integrar Brevo SMTP y SendGrid SMTP con aceptación observada

- **Descripción**: C-02 completa, CA-04/05/08/16. Usar perfiles fijos y el conector observado existente para autenticar/enviar con TLS y clasificar rechazo demostrado, aceptación o incertidumbre. Primer oráculo: test nuevo rojo de perfil/credencial de ambos proveedores.
- **Partida**: C-02,16h/120000tokens heredados.
- **Estado**: en-progreso
- **Tiempo humano**: est.16h · real—
- **Tiempo IA (ejec.)**: est.0.4h (estimado por tokens) · real—
- **Supervisión**: est.0.1h (25 % IA) · real—
- **Previsión IA**:100000in/20000outtok · `0.1p_in +0.02p_out` €; tarifas por verificar
- **Coste estimado**:800 € equivalentes base+tokens.
- **Dependencias**: T-01, T-02
- **Tipo**: backend
- **Archivos**: `app/Services/MailProviders/**`, `app/Services/AccountMailTransport.php`, `app/Services/DeliveryLifecycle.php`, `app/Services/SmtpConnector.php`, `app/Services/ObservedSmtpTransport.php`, `app/Services/SmtpProtocolObserver.php`, `app/Support/Smtp*.php`, `tests/Feature/MailProviderSmtpTest.php`, `tests/Unit/MailProviders/**`, `tests/Feature/MailTransportTest.php`, `tests/Fixtures/*Smtp*.php`, `tests/support/mail-provider-*`, `tests/support/mail_provider*`
- **Cubre (tests)**: E2E-04, E2E-06, M-01
- **Capacidades**: api-contract, backend-practices, delivery-practices, frontend-quality, stack-practices, unit-tests
- **Procedencia de capacidades**: misma selección compartida; SMTP conserva observación de protocolo y usa fixtures de red propias, sin proveedor real.
- **Verificación**: `php vendor/bin/phpunit tests/Feature/MailProviderSmtpTest.php` → ambos perfiles, autenticación, TLS/certificado, códigos4xx/5xx y pérdida de confirmación correctos; `php vendor/bin/phpunit tests/Feature/MailTransportTest.php` → regresión de SMTP/log/Mailpit conservada. Simulación SMTP controlada propia, sin credenciales ni correo real.

**Criterios de aceptación**

- [ ] CA-04: perfiles Brevo/SendGrid usan host fijo, credencial correcta del proveedor/canal, TLS verificado y timeout inferior al presupuesto de reserva.
- [ ] CA-05: negativo transitorio/permanente se distingue por código/etapa observados; sólo el transitorio demostrado puede reprogramarse y una posible aceptación sin respuesta permanece ambigua.
- [ ] CA-08/16: el envío respeta snapshot/correlación del intento, heartbeat/fencing y red fuera de locks; no se cambia de proveedor ante fallo.
- [ ] Se prueban rechazo de certificado no confiable, aceptación con credencial sintética y captura/recibos sin AUTH ni cuerpo sensible; Mailpit no hereda credenciales externas.

**Subtareas**

- [ ] Observar el nuevo test rojo antes de añadir perfiles y despacho SMTP.
- [ ] Reutilizar `SmtpConnector`/`ObservedSmtpTransport` y añadir metadatos opacos para correlación de callbacks.
- [ ] Ejecutar fixture local con aceptación, negativo temporal/permanente y caída tras terminador; conservar resultados separados.

**Notas**: Fuente: encargo «SMTP los dos proveedores», spec CA-04/05/16. Referencias: `SmtpConnector::send`, observador de etapas y fixtures `SmtpTlsServer.php`. Brevo distingue clave SMTP de API; SendGrid emplea su autenticación SMTP publicada. Las opciones concretas se cotejan con documentación oficial vigente. Un certificado de tests no justifica desactivar verificación en producto.


RED: `tests/Feature/MailProviderSmtpTest.php` falló con RED funcional: con perfil Brevo completo y flag habilitado el transporte sigue bloqueado;1 caso/1 asercion/1 fallo, guarda real correcta, sin red externa. · 2026-10-30.


- **Checkpoint de implementación**: SMTP tiene10 unitarias/56 aserciones,8 sesiones TLS/SMTP reales de fixture/256 aserciones y1 integración SQL/9 aserciones verdes. Incluye STARTTLS y TLS implícito de ambos perfiles, AUTH,4xx/5xx y desconexión tras DATA. Se conserva en-progreso hasta ejecutar la regresión compartida MailTransportTest tras instalar también API; no se duplica esa tanda sobre el mismo núcleo. Marcador T03 cerrado antes de abrir T04.

### T-04 — Integrar API transaccional Brevo y SendGrid sin reenvíos inciertos

- **Descripción**: C-03 completa, CA-06–CA-08/16: adaptadores pequeños que usan HTTP instalado, endpoints fijados y resultados propios. Primer oráculo: test nuevo rojo de payload/autenticación de cada API antes del adaptador.
- **Partida**: C-03,20h/168000tokens heredados.
- **Estado**: en-progreso
- **Tiempo humano**: est.20h · real—
- **Tiempo IA (ejec.)**: est.0.56h (estimado por tokens) · real—
- **Supervisión**: est.0.14h (25 % IA) · real—
- **Previsión IA**:140000in/28000outtok · `0.14p_in +0.028p_out` €; tarifas por verificar
- **Coste estimado**:1000 € equivalentes base+tokens.
- **Dependencias**: T-01, T-02
- **Tipo**: backend
- **Archivos**: `app/Services/MailProviders/**`, `app/Services/AccountMailTransport.php`, `app/Support/Transport*.php`, `app/Models/MailMessage.php`, `tests/Unit/MailProviders/**`, `tests/Feature/MailProviderApiTest.php`, `tests/Fixtures/*Http*.php`, `tests/support/mail-provider-*`, `tests/Fixtures/*Provider*.php`, `tests/support/mail-provider-*`, `tests/support/mail_provider*`
- **Cubre (tests)**: E2E-04, E2E-05, E2E-06, M-01
- **Capacidades**: api-contract, backend-practices, delivery-practices, frontend-quality, stack-practices, unit-tests
- **Procedencia de capacidades**: misma selección; cliente HTTP Laravel/Guzzle instalado y respuesta acotada, sin SDK/dep nueva por defecto.
- **Verificación**: `php vendor/bin/phpunit tests/Unit/MailProviders` y `php vendor/bin/phpunit tests/Feature/MailProviderApiTest.php` → ambos payloads/autenticaciones, aceptación API, HTTP400/401/403/422/429/5xx, redirect, timeout, cuerpo inválido/excesivo y cero retry/failover implícitos. Fake/fixture HTTP por contrato oficial, no llamada externa.

**Criterios de aceptación**

- [ ] CA-06: peticiones de cada proveedor respetan URL fija, cabeceras, remitente/cuerpos/destinatario y referencia opaca; se registra aceptación API sin etiquetarla SMTP o entrega.
- [ ] CA-07: errores deterministas no se repiten;429 respeta una reprogramación segura y acotada; timeout/5xx/respuesta incierta no se convierte en prueba de no aceptación ni dispara retry automático.
- [ ] CA-08: cambio concurrente de configuración o pérdida de reserva no cambia el destinatario/proveedor del intento ni permite una finalización obsoleta.
- [ ] CA-16: TLS activo, redirects deshabilitados, límites de conexión/total/cuerpo y diagnósticos cerrados; ninguna excepción/cabecera/cuerpo crudo alcanza usuario, logs de producto o recibos públicos.

**Subtareas**

- [ ] Observar tests nuevos rojos para los dos contratos y su distinción de estados.
- [ ] Separar mapeo del proveedor, IO y resultado del dominio; reutilizar políticas comunes sin capas innecesarias.
- [ ] Probar cantidad real de llamadas, clasificación antes de interpretar cuerpo y falta de failover ante incertidumbre.

**Notas**: Fuente: encargo «API los dos proveedores», spec CA-06–CA-08/16. Las referencias son `AccountMailTransport::perform` y el cliente HTTP de Laravel; no existe adaptador API de proveedor previo. El contrato oficial y las fixtures sintéticas se identifican como tales; aceptación real con claves del operador queda en M-01.


RED: `tests/Feature/MailProviderApiTest.php` falló con RED funcional: perfil API configurado devuelve api_rejected antes del adaptador;1 caso/1 asercion/1 fallo, guarda SQL real y fuentes estables, cero HTTP externo. · 2026-10-30.

- **Resultado parcial T-04**: API42 casos/227 aserciones y8 sesiones HTTPS reales de fixture/273 aserciones verdes; integración SQL1 caso/4 aserciones. El primer arnes HTTPS no entraba en red: Guzzle8 rechaza handler en opciones de peticion; seam corregido en PendingRequest::setHandler, sin cambiar politica productiva. Certificado ajeno rechazado, redirects no seguidos, chunked excesivo acotado y cero failover. Regresion comun pendiente de T-06. Medicion estimada sin telemetria; reloj no imputado como horas IA.

### T-05 — Procesar feedback autenticado con idempotencia y supresiones

- **Descripción**: C-04 completa, CA-09–CA-12: receptor acotado que autentica antes de interpretar/aplicar, correlaciona cada evento con su intento y conserva feedback aparte de la aceptación. Primer oráculo: test nuevo rojo de callback legítimo/alterado antes del endpoint.
- **Partida**: C-04,24h/192000tokens heredados.
- **Estado**: en-progreso
- **Tiempo humano**: est.24h · real—
- **Tiempo IA (ejec.)**: est.0.64h (estimado por tokens) · real—
- **Supervisión**: est.0.16h (25 % IA) · real—
- **Previsión IA**:160000in/32000outtok · `0.16p_in +0.032p_out` €; tarifas por verificar
- **Coste estimado**:1200 € equivalentes base+tokens.
- **Dependencias**: T-01, T-02, T-03, T-04
- **Tipo**: backend
- **Archivos**: `app/Services/MailProviders/**`, `app/Http/Controllers/MailProvider*.php`, `app/Http/Middleware/MailProvider*.php`, `app/Models/MailProvider*.php`, `app/Models/MailMessage.php`, `app/Services/SuppressionService.php`, `database/migrations/2026_10_30_*_mail_provider*.php`, `bootstrap/app.php`, `routes/mail-providers.php`, `routes/webhooks.php`, `docs/api/mail-provider-webhooks.openapi.json`, `tests/Feature/MailProviderWebhookTest.php`, `tests/Unit/MailProviders/**`, `tests/Fixtures/*Provider*.php`
- **Cubre (tests)**: E2E-05, E2E-06, M-01
- **Capacidades**: api-contract, backend-practices, delivery-practices, frontend-quality, stack-practices, unit-tests
- **Procedencia de capacidades**: misma selección; callbacks sin sesión, firma/Bearer y transacción idempotente con cuenta resuelta desde el perfil local.
- **Verificación**: `php vendor/bin/phpunit tests/Feature/MailProviderWebhookTest.php` → firma/timestamp/Bearer, cuerpo/lote acotados, duplicados, reordenación, proveedor/cuenta/referencia incorrectos y callback antes de respuesta; `php vendor/bin/phpunit tests/Feature/SuppressionTest.php` → bajas/supresiones existentes conservadas. El nombre de la regresión existente se comprueba antes de ejecutar, sin omitir escenarios.

**Criterios de aceptación**

- [ ] CA-09: SendGrid verifica firma ECDSA sobre timestamp+cuerpo original, con política temporal explícita; clave incorrecta, byte modificado o fuera de ventana no produce efectos.
- [ ] CA-10: Brevo verifica Bearer privado por cabecera, payload/lote acotados y TLS requerido para operación real; secreto ausente de errores, auditoría y evidencia.
- [ ] CA-11: identidad de evento/proveedor/cuenta y referencia opaca impiden duplicación o cruce de cuentas; el `id` repetido del webhook Brevo no se usa solo como dedupe.
- [ ] CA-12: queja, baja y rebote permanente correlacionados aplican la supresión de esa cuenta; temporal no suprime permanentemente y un evento benigno tardío no revierte el efecto.
- [ ] Feedback separado conserva aceptación/procedencia; callback anticipado, cambio de modo y duplicado simultáneo tienen resultado durable sin reenvío ni rechazo por usar la sesión equivocada.

**Subtareas**

- [ ] Observar el nuevo rojo de autenticación/correlación antes de implementar rutas.
- [ ] Persistir recibo mínimo/dedupe y precedencia de feedback bajo account mutex; sin cuerpos crudos.
- [ ] Probar rotación/revocación y eventos en vuelo; documentar política sin aceptar silenciosamente claves retiradas.

**Notas**: Fuente: propuesta de feedback aceptada y spec CA-09–CA-12. Referencias: `SuppressionService::apply/isSuppressed`, orden account→recurso y middleware de cuerpo crudo MCP como patrón de lectura acotada, sin reutilizar su protocolo. El emisor remoto no autoriza recursos mediante un `account_id` del payload. Un204 sólo confirma persistencia/consumo idempotente local, no entrega de correo.


RED: `tests/Feature/MailProviderWebhookTest.php::test_authenticated_brevo_unknown_reference_is_consumed_without_effects` falló con RED: callback autenticado devuelve 404 en vez de 204;1 caso/1 fallo, identidad SQL testing comprobada y fuentes estables. · 2026-10-30.

- **Resultado parcial T-05**:5 casos/79 aserciones verdes sobre SQL testing, firma P256 de bytes originales, timestamp, Bearer, rotacion, deshabilitacion, referencia/proveedor/cuenta, replay y1000 eventos con maximo16 consultas comprobado. Primer verde fallaba401 por URL HTTP creada por Symfony pese a HTTPS en server vars; URLs HTTPS explicitas corrigen el arnes manteniendo rechazo HTTP. Falta carrera con resultado de worker y regresion de supresiones, previstas T-06.

## Fase 3 — Integración, UI y validación

**Estado**: borrador · **Estimado**:28h base · **Real**:— · **Coste est.**:1400 € equivalentes+tokens por verificar · **Tokens est.**:248000

### T-06 — Integrar campañas/API/MCP, operar la UI y acreditar QA local

- **Descripción**: Terminar C-05(10h/80000tokens) y C-06(18h/168000tokens): ambas guardas de inicio durable, estados/proyecciones, formularios y feedback, revisión fresca, QA y guías. CA-08/13–CA-18. Primer oráculo: test nuevo rojo de envío durable por cada modo y proyección API antes de integrar.
- **Partida**: resto C-05 más C-06,28h/248000tokens; incluye revisión/correcciones8h base ya presupuestadas.
- **Estado**: en-progreso
- **Tiempo humano**: est.28h · real—
- **Tiempo IA (ejec.)**: est.0.826667h (estimado por tokens) · real—
- **Supervisión**: est.0.206667h (25 % IA) · real—
- **Previsión IA**:206667in/41333outtok · `0.206667p_in +0.041333p_out` €; tarifas por verificar
- **Coste estimado**:1400 € equivalentes base+tokens.
- **Dependencias**: T-01, T-02, T-03, T-04, T-05
- **Archivos**: `app/Services/AccountMailTransport.php`, `app/Services/DeliveryLifecycle.php`, `app/Services/ActivityService.php`, `app/Services/SendingApiService.php`, `app/Services/CampaignService.php`, `app/Services/TemplateService.php`, `app/Services/DeliveryQuota.php`, `app/Services/MailSettingsService.php`, `app/Services/Mcp/BusinessDto.php`, `app/Services/MailProviders/**`, `app/Livewire/MailSettings.php`, `app/Livewire/Templates.php`, `app/Livewire/Campaigns.php`, `app/Support/MailResult.php`, `resources/views/livewire/partials/mail-provider-form.blade.php`, `resources/views/livewire/mail-settings.blade.php`, `resources/views/livewire/activity.blade.php`, `resources/views/livewire/suppressions.blade.php`, `tests/Feature/SuppressionTest.php`, `resources/views/livewire/templates.blade.php`, `routes/api.php`, `routes/web.php`, `docs/api/**`, `tests/Feature/MailProvider*.php`, `tests/Feature/MailTransportTest.php`, `tests/Feature/ActivityTest.php`, `tests/Feature/Delivery*.php`, `tests/Feature/SendingApi*.php`, `tests/Feature/Mcp*.php`, `tests/Unit/MailProviders/**`, `tests/e2e/mail-providers.spec.mjs`, `tests/support/mail-provider-*`, `tests/support/mail_provider*`, `tests/Fixtures/*Provider*.php`, `docs/aplicacion/proveedores-email.md`, `docs/aplicacion/plantillas-y-transporte.md`, `docs/aplicacion/entrega-y-recuperacion.md`, `docs/aplicacion/actividad-y-metricas.md`, `docs/aplicacion/desarrollo.md`, `docs/aplicacion/arquitectura.md`, `docs/aplicacion/mcp-contrato.md`, `docs/README.md`, `docs/RAG-INDEX.md`, `docs/roadmap/README.md`, `docs/roadmap/2026-10-30-proveedores-email/**`, `CHANGELOG.md`, `docs/en/CHANGELOG.md`
- **Cubre (tests)**: E2E-01, E2E-02, E2E-03, E2E-04, E2E-05, E2E-06, M-01, M-02
- **Capacidades**: api-contract, backend-practices, delivery-practices, frontend-quality, stack-practices, unit-tests
- **Procedencia de capacidades**: misma selección compartida. Root implementa con Laravel/Livewire vigentes; no se aplican convenciones CI4 ni React ajenas al stack. Revisión A/B y C/D según selector, QA y documenter independientes; sin gate porcentual configurado.
- **Verificación**: `php vendor/bin/phpunit tests/Feature/MailProviderDeliveryTest.php` y `php vendor/bin/phpunit tests/Feature/MailProviderUiTest.php` → cuatro modos por campañas/API/MCP, redelivery, fencing, feedback y permisos correctos; regresión proporcional de MailTransport/Activity/Delivery/SendingApi/MCP → verde sin sumar tandas solapadas; `python scripts/check-mcp-contract.py` y lint/diff OpenAPI → contratos/pins coherentes; `npx playwright test tests/e2e/mail-providers.spec.mjs` con configuración propia de QA → seis escenarios originales y JSON nativo; `qa-gate.py` oficial contra JSON original y `test-plan.md` → verde, cleanup propio admitido. Pint/lint de archivos PHP tocados y controles de publicación explícita → sin errores. Comandos E2E sólo después de preparar fixtures/puertos/entornos propios.

**Criterios de aceptación**

- [ ] CA-08/13: ambas ramas de `beforeStart`, campañas/API/MCP y prueba explícita usan los cuatro modos con permiso/cuenta/remitente vigentes, cuota común, fencing, supresiones, outbox, recibos idempotentes y procedencia; redelivery terminal no repite IO.
- [ ] CA-14: ajustes/actividad muestran proveedor/canal, errores corregibles, estado vacío/guardado/limpieza, teclado y feedback posterior; los secretos no se hidratan ni aparecen tras recargar/cambiar de cuenta.
- [ ] CA-15: consultas/contadores y contratos conservan semántica SMTP y exponen aceptación API propia; pins, esquema y proyecciones reales coinciden.
- [ ] CA-16/17: rechazo de destino/redirect/certificado/respuesta fuera de política probado; adaptadores cohesionados sin duplicar pipeline ni añadir SDK injustificado. Revisión fresca sin Critical/Important pendientes y QA local verde con fuentes/evidencias originales.
- [ ] CA-18: guías detallan cuatro recetas, claves diferenciadas, webhooks/rotación, incertidumbre/retry, activación y diagnóstico, distinguiendo simulación local de validación privada real pendiente.
- [ ] Se conservan log/Mailpit y datos operativos; limpia recursos/fixtures propios y no se afirma cierre MCP T12–T14 ni principal T19/T20, cobertura porcentual o aceptación humana inexistentes.

**Subtareas**

- [ ] Observar el rojo de integración y adaptar ambas guardas/proyecciones; probar carrera de supresión, reserva caducada y callback anterior al resultado.
- [ ] Implementar formularios con secretos de escritura y etiquetas de resultado coherentes; ejecutar regresiones y verificar originalidad de fuentes.
- [ ] Despachar revisión fresca y corregir gaps con máximo de tres intentos; QA independiente después, con JSON/capturas/informe y limpieza.
- [ ] Documenter actualiza guías; Knowledge Gate sólo juzga candidatos reales si aparecen. Registrar límites manuales/retro y publicar únicamente selección explícita segura.

**Notas**: Hallazgo de integracion: TemplateService, selector de campanas y connection_info tambien filtraban lab_authorized. Se incluyen sus fronteras antes de editarlas. MCP0.2.0 documenta el booleano real lab_authorized y el campo opcional external_authorized; se amplia un caso antes invalido por literal y se conservan los fixtures0.1.0. Las revocaciones API/MCP siguen bloqueando nuevas llamadas/replays, sin cancelar trabajo ya aceptado (contrato vigente); no se reinterpretan como cancelacion de cola. Fuente: exigencia de código limpio/robusto, flujo completo elegido y CA-08/13–CA-18. Referencias: `DeliveryLifecycle::beforeStart/apiBeforeStart/finish`, `AccountMailTransport::execute`, `ActivityService` y `BusinessDto`; UI existente `MailSettings` recibe credenciales como argumentos de acción, no propiedades. La integración final se reserva aquí para no exigir adaptadores aún inexistentes en T-02. El rol genérico hereda el modelo real; no se atribuye ejecución nativa Sonnet/Opus. No se instala CA global ni se habilita SMTP/API del operador para ejecutar QA. Con la implementación/QA local aceptadas, la spec sólo pasa a implementada al superar las puertas finales del workflow, incluida retro cuando corresponda.

RED: `tests/Feature/MailProviderDeliveryTest.php::test_rest_accepts_an_external_only_sender_for_a_prepared_provider` falló con RED: REST devuelve422 para remitente externo preparado en vez de202;1 caso/1 fallo, cero red externa y SQL testing guardado. · 2026-10-30.


## Revisión de dos lentes — intento 1: Integracion de proveedores SMTP/API y feedback; revision fresca previa a QA (T-01, T-02, T-03, T-04, T-05, T-06)

Pendiente de revisión; sin veredicto.
