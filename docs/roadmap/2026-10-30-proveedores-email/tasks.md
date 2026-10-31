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
| Fase 2 — Transportes y feedback | 3 | 3 | 100.0% | — /60h | — /1.6h | — /0.4h | — /480000 |
| Fase 3 — Integración, UI y validación | 0 | 1 | 0% | — /28h | — /0.826667h | — /0.206667h | — /248000 |
| Fase 4 — Manual completo e instalacion desde cero | 0 | 1 | 0% | — /12h | — /0.533333h | — /0.133333h | — /160000 |
| **TOTAL** | **5** | **7** | **71.4%** | **— /116h** | **— /3.413333h** | **— /0.853333h** | **— /1024000** |

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

**Estado**: completado · **Estimado**:60h base · **Real**:— · **Coste est.**:3000 € equivalentes+tokens por verificar · **Tokens est.**:480000

### T-03 — Integrar Brevo SMTP y SendGrid SMTP con aceptación observada

- **Descripción**: C-02 completa, CA-04/05/08/16. Usar perfiles fijos y el conector observado existente para autenticar/enviar con TLS y clasificar rechazo demostrado, aceptación o incertidumbre. Primer oráculo: test nuevo rojo de perfil/credencial de ambos proveedores.
- **Partida**: C-02,16h/120000tokens heredados.
- **Estado**: completado
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

- [x] CA-04: perfiles Brevo/SendGrid usan host fijo, credencial correcta del proveedor/canal, TLS verificado y timeout inferior al presupuesto de reserva.
- [x] CA-05: negativo transitorio/permanente se distingue por código/etapa observados; sólo el transitorio demostrado puede reprogramarse y una posible aceptación sin respuesta permanece ambigua.
- [x] CA-08/16: el envío respeta snapshot/correlación del intento, heartbeat/fencing y red fuera de locks; no se cambia de proveedor ante fallo.
- [x] Se prueban rechazo de certificado no confiable, aceptación con credencial sintética y captura/recibos sin AUTH ni cuerpo sensible; Mailpit no hereda credenciales externas.

**Subtareas**

- [x] Observar el nuevo test rojo antes de añadir perfiles y despacho SMTP.
- [x] Reutilizar `SmtpConnector`/`ObservedSmtpTransport` y añadir metadatos opacos para correlación de callbacks.
- [x] Ejecutar fixture local con aceptación, negativo temporal/permanente y caída tras terminador; conservar resultados separados.

**Notas**: Fuente: encargo «SMTP los dos proveedores», spec CA-04/05/16. Referencias: `SmtpConnector::send`, observador de etapas y fixtures `SmtpTlsServer.php`. Brevo distingue clave SMTP de API; SendGrid emplea su autenticación SMTP publicada. Las opciones concretas se cotejan con documentación oficial vigente. Un certificado de tests no justifica desactivar verificación en producto.


RED: `tests/Feature/MailProviderSmtpTest.php` falló con RED funcional: con perfil Brevo completo y flag habilitado el transporte sigue bloqueado;1 caso/1 asercion/1 fallo, guarda real correcta, sin red externa. · 2026-10-30.


- **Checkpoint de implementación**: SMTP tiene10 unitarias/56 aserciones,8 sesiones TLS/SMTP reales de fixture/256 aserciones y1 integración SQL/9 aserciones verdes. Incluye STARTTLS y TLS implícito de ambos perfiles, AUTH,4xx/5xx y desconexión tras DATA. Se conserva en-progreso hasta ejecutar la regresión compartida MailTransportTest tras instalar también API; no se duplica esa tanda sobre el mismo núcleo. Marcador T03 cerrado antes de abrir T04.

- **Changelog**: Envio SMTP de Brevo/SendGrid con TLS, negativos observados y bloqueo de degradacion EHLO.

- **Verificacion de cierre T-03**: Cierre tecnico: final-provider-units90/1045 incluye13sesionesSMTP/432aserciones y10unitarias/58; las11MailTransport existentes pasaron en fix1-regression-final. Revision3sin gaps; originales en testing/backend y testing/review. RED funcional representativo Brevo y contrato comun anterior al codigo; no se afirma una sesion roja independiente por cada perfil.

### T-04 — Integrar API transaccional Brevo y SendGrid sin reenvíos inciertos

- **Descripción**: C-03 completa, CA-06–CA-08/16: adaptadores pequeños que usan HTTP instalado, endpoints fijados y resultados propios. Primer oráculo: test nuevo rojo de payload/autenticación de cada API antes del adaptador.
- **Partida**: C-03,20h/168000tokens heredados.
- **Estado**: completado
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

- [x] CA-06: peticiones de cada proveedor respetan URL fija, cabeceras, remitente/cuerpos/destinatario y referencia opaca; se registra aceptación API sin etiquetarla SMTP o entrega.
- [x] CA-07: errores deterministas no se repiten;429 respeta una reprogramación segura y acotada; timeout/5xx/respuesta incierta no se convierte en prueba de no aceptación ni dispara retry automático.
- [x] CA-08: cambio concurrente de configuración o pérdida de reserva no cambia el destinatario/proveedor del intento ni permite una finalización obsoleta.
- [x] CA-16: TLS activo, redirects deshabilitados, límites de conexión/total/cuerpo y diagnósticos cerrados; ninguna excepción/cabecera/cuerpo crudo alcanza usuario, logs de producto o recibos públicos.

**Subtareas**

- [x] Observar tests nuevos rojos para los dos contratos y su distinción de estados.
- [x] Separar mapeo del proveedor, IO y resultado del dominio; reutilizar políticas comunes sin capas innecesarias.
- [x] Probar cantidad real de llamadas, clasificación antes de interpretar cuerpo y falta de failover ante incertidumbre.

**Notas**: Fuente: encargo «API los dos proveedores», spec CA-06–CA-08/16. Las referencias son `AccountMailTransport::perform` y el cliente HTTP de Laravel; no existe adaptador API de proveedor previo. El contrato oficial y las fixtures sintéticas se identifican como tales; aceptación real con claves del operador queda en M-01.


RED: `tests/Feature/MailProviderApiTest.php` falló con RED funcional: perfil API configurado devuelve api_rejected antes del adaptador;1 caso/1 asercion/1 fallo, guarda SQL real y fuentes estables, cero HTTP externo. · 2026-10-30.

- **Resultado parcial T-04**: API42 casos/227 aserciones y8 sesiones HTTPS reales de fixture/273 aserciones verdes; integración SQL1 caso/4 aserciones. El primer arnes HTTPS no entraba en red: Guzzle8 rechaza handler en opciones de peticion; seam corregido en PendingRequest::setHandler, sin cambiar politica productiva. Certificado ajeno rechazado, redirects no seguidos, chunked excesivo acotado y cero failover. Regresion comun pendiente de T-06. Medicion estimada sin telemetria; reloj no imputado como horas IA.

- **Changelog**: Envio API Brevo/SendGrid con respuestas acotadas y sin reintentos de aceptacion incierta.

- **Verificacion de cierre T-04**: Cierre tecnico: final-provider-units90/1045 incluyeAPI42/227yHTTPS8/291; final-provider-pipeline8/241 acredita ambos proveedores por los3origenes y requeue/ambiguedad. Revision3sin gaps. La subtarea roja se acredita con RED funcional Brevo representativo mas contrato comun api_accepted; no hubo un RED independiente por proveedor ni se exige TDD retrospectivo (tddfalse).

### T-05 — Procesar feedback autenticado con idempotencia y supresiones

- **Descripción**: C-04 completa, CA-09–CA-12: receptor acotado que autentica antes de interpretar/aplicar, correlaciona cada evento con su intento y conserva feedback aparte de la aceptación. Primer oráculo: test nuevo rojo de callback legítimo/alterado antes del endpoint.
- **Partida**: C-04,24h/192000tokens heredados.
- **Estado**: completado
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
- **Verificación**: `php vendor/bin/phpunit tests/Feature/MailProviderWebhookTest.php` → firma/timestamp/Bearer, cuerpo/lote acotados, duplicados, reordenación, proveedor/cuenta/referencia incorrectos y callback antes de respuesta; Regresion focal de3casos de `SuppressionTest` y UIfeedback existente → bajas/supresiones existentes conservadas. Ajuste proporcional explicito: no se modifico SuppressionService y las guardas nuevas se comprueban tambien en webhook/pipeline; no se acredita la clase completa ni se borra su plan original del historial Git.

**Criterios de aceptación**

- [x] CA-09: SendGrid verifica firma ECDSA sobre timestamp+cuerpo original, con política temporal explícita; clave incorrecta, byte modificado o fuera de ventana no produce efectos.
- [x] CA-10: Brevo verifica Bearer privado por cabecera, payload/lote acotados y TLS requerido para operación real; secreto ausente de errores, auditoría y evidencia.
- [x] CA-11: identidad de evento/proveedor/cuenta y referencia opaca impiden duplicación o cruce de cuentas; el `id` repetido del webhook Brevo no se usa solo como dedupe.
- [x] CA-12: queja, baja y rebote permanente correlacionados aplican la supresión de esa cuenta; temporal no suprime permanentemente y un evento benigno tardío no revierte el efecto.
- [x] Feedback separado conserva aceptación/procedencia; callback anticipado, cambio de modo y duplicado simultáneo tienen resultado durable sin reenvío ni rechazo por usar la sesión equivocada.

**Subtareas**

- [x] Observar el nuevo rojo de autenticación/correlación antes de implementar rutas.
- [x] Persistir recibo mínimo/dedupe y precedencia de feedback bajo account mutex; sin cuerpos crudos.
- [x] Probar rotación/revocación y eventos en vuelo; documentar política sin aceptar silenciosamente claves retiradas.

**Notas**: Fuente: propuesta de feedback aceptada y spec CA-09–CA-12. Referencias: `SuppressionService::apply/isSuppressed`, orden account→recurso y middleware de cuerpo crudo MCP como patrón de lectura acotada, sin reutilizar su protocolo. El emisor remoto no autoriza recursos mediante un `account_id` del payload. Un204 sólo confirma persistencia/consumo idempotente local, no entrega de correo.


RED: `tests/Feature/MailProviderWebhookTest.php::test_authenticated_brevo_unknown_reference_is_consumed_without_effects` falló con RED: callback autenticado devuelve 404 en vez de 204;1 caso/1 fallo, identidad SQL testing comprobada y fuentes estables. · 2026-10-30.

- **Resultado parcial T-05**:5 casos/79 aserciones verdes sobre SQL testing, firma P256 de bytes originales, timestamp, Bearer, rotacion, deshabilitacion, referencia/proveedor/cuenta, replay y1000 eventos con maximo16 consultas comprobado. Primer verde fallaba401 por URL HTTP creada por Symfony pese a HTTPS en server vars; URLs HTTPS explicitas corrigen el arnes manteniendo rechazo HTTP. Falta carrera con resultado de worker y regresion de supresiones, previstas T-06.

- **Changelog**: Webhooks autenticados y deduplicados con feedback separado y supresiones por cuenta.

- **Verificacion de cierre T-05**: Cierre tecnico: webhook5/79, carrera entre2procesos en pipeline anterior6/213 y callbacks anticipado/entre intentos/durante retry en finalpipeline8/241. Las3guardas de supresion legadas se verificaron proporcionalmente en regresion/focal, junto UIfeedback existente; NO se ejecuto completa la clase SuppressionTest. SuppressionService permanece sin cambios. El lote1000 usa una referencia y max16queries, no1000referencias distintas. Originales y limites en testing/acceptance.md.

## Fase 3 — Integración, UI y validación

**Estado**: en-progreso · **Estimado**:28h base · **Real**:— · **Coste est.**:1400 € equivalentes+tokens por verificar · **Tokens est.**:248000


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

- [x] CA-08/13: ambas ramas de `beforeStart`, campañas/API/MCP y prueba explícita usan los cuatro modos con permiso/cuenta/remitente vigentes, cuota común, fencing, supresiones, outbox, recibos idempotentes y procedencia; redelivery terminal no repite IO.
- [x] CA-14: ajustes/actividad muestran proveedor/canal, errores corregibles, estado vacío/guardado/limpieza, teclado y feedback posterior; los secretos no se hidratan ni aparecen tras recargar/cambiar de cuenta.
- [x] CA-15: consultas/contadores y contratos conservan semántica SMTP y exponen aceptación API propia; pins, esquema y proyecciones reales coinciden.
- [x] CA-16/17: rechazo de destino/redirect/certificado/respuesta fuera de política probado; adaptadores cohesionados sin duplicar pipeline ni añadir SDK injustificado. Revisión fresca sin Critical/Important pendientes y QA local verde con fuentes/evidencias originales.
- [x] CA-18: guías detallan cuatro recetas, claves diferenciadas, webhooks/rotación, incertidumbre/retry, activación y diagnóstico, distinguiendo simulación local de validación privada real pendiente.
- [x] Se conservan log/Mailpit y datos operativos; limpia recursos/fixtures propios y no se afirma cierre MCP T12–T14 ni principal T19/T20, cobertura porcentual o aceptación humana inexistentes.

**Subtareas**

- [x] Observar el rojo de integración y adaptar ambas guardas/proyecciones; probar carrera de supresión, reserva caducada y callback anterior al resultado.
- [x] Implementar formularios con secretos de escritura y etiquetas de resultado coherentes; ejecutar regresiones y verificar originalidad de fuentes.
- [x] Despachar revisión fresca y corregir gaps con máximo de tres intentos; QA independiente después, con JSON/capturas/informe y limpieza.
- [ ] Documenter actualiza guías; Knowledge Gate sólo juzga candidatos reales si aparecen. Registrar límites manuales/retro y publicar únicamente selección explícita segura.

**Notas**: Hallazgo de integracion: TemplateService, selector de campanas y connection_info tambien filtraban lab_authorized. Se incluyen sus fronteras antes de editarlas. MCP0.2.0 documenta el booleano real lab_authorized y el campo opcional external_authorized; se amplia un caso antes invalido por literal y se conservan los fixtures0.1.0. Las revocaciones API/MCP siguen bloqueando nuevas llamadas/replays, sin cancelar trabajo ya aceptado (contrato vigente); no se reinterpretan como cancelacion de cola. Fuente: exigencia de código limpio/robusto, flujo completo elegido y CA-08/13–CA-18. Referencias: `DeliveryLifecycle::beforeStart/apiBeforeStart/finish`, `AccountMailTransport::execute`, `ActivityService` y `BusinessDto`; UI existente `MailSettings` recibe credenciales como argumentos de acción, no propiedades. La integración final se reserva aquí para no exigir adaptadores aún inexistentes en T-02. El rol genérico hereda el modelo real; no se atribuye ejecución nativa Sonnet/Opus. No se instala CA global ni se habilita SMTP/API del operador para ejecutar QA. Con la implementación/QA local aceptadas, la spec sólo pasa a implementada al superar las puertas finales del workflow, incluida retro cuando corresponda.

RED: `tests/Feature/MailProviderDeliveryTest.php::test_rest_accepts_an_external_only_sender_for_a_prepared_provider` falló con RED: REST devuelve422 para remitente externo preparado en vez de202;1 caso/1 fallo, cero red externa y SQL testing guardado. · 2026-10-30.


- **Changelog**: Configuracion y actividad de proveedores integradas con campanas, API y MCP.


RED: `tests/Feature/MailProviderDeliveryTest.php::test_feedback_received_after_requeue_blocks_the_next_durable_start` falló con RED: callback delivered tras requeue429 permite un segundo envio; assert null falla. 1caso/6aserciones, guard SQL testing y fuentes estables. · 2026-10-31.

## Revisión de dos lentes — intento 1: Integracion de proveedores SMTP/API y feedback; revision fresca previa a QA (T-01, T-02, T-03, T-04, T-05, T-06)

Veredicto del intento1: con gaps;1Critical,1Important y1Minor. Correcciones implementadas, revalidacion independiente pendiente en intento2.

### Fusion del intento1 (A+B+C+D)

| # | Grado | Gap | Tarea | Correccion | Evidencia |
|---|---|---|---|---|---|
| C1-01 | Critical | EHLO500/HELO250 elude requireTls del vendor y permite DATA sin TLS | T-03/T-06 | corregido; ESMTP obligatorio y fallback EHLO bloqueado antes de AUTH/sobre/cuerpo; revalidacion2 pendiente | LenteC, probe de stream real en memoria; CA04/16 |
| B1-01 | Important | AUTH535 se pierde durante negociacion y se reprograma como fallo previo a terminador | T-03/T-06 | corregido; ultimo negativo de negociacion conservado; revalidacion2 pendiente | LentesA/B; cadena completa Symfony/conector/lifecycle |
| A1-03 | Minor | Guia de transporte conserva parrafo antiguo de proveedores/feedback pendientes | T-06 | corregido; parrafos97/99 sincronizados; revalidacion2 pendiente | docs/aplicacion/plantillas-y-transporte.md:99 |

LenteD sin hallazgos; sin benchmark de5-10workers ni lote de1000 referencias distintas. Lentes genericas de contexto fresco por ausencia de reviewer nativo; no se atribuye modelo distinto. Informes originales privados review-{A,B,C,D}1.md. No se convierte la ausencia de otros hallazgos en QA verde.

Otros pendientes de implementacion: oraculo TLS1.3 de API (2fallos de85/823), deprecacion RFC7231 en PHP8.5,2mocks sin expectativa y etiqueta concisa Mailpit. La regresionlab22casos se cancelo por su handle propio tras localizar etiqueta rota y gaps del conector; no acredita verde. Probe posterior confirma cero conexiones SQL testing propias vivas. Nueva tanda tras fixes, preservando pruebas sin debilitar sus garantias. Telemetria no disponible: horas/tokens reales no inventados.

### Evidencia de correcciones del intento1

RED de cable nuevo: fix1-smtp-red,2casos/38aserciones/2fallos; AUTH535retryable y DATA en claro tras EHLO500/HELO250 reproducidos antes de corregir. Verde unitario final fix1-unit-final:90casos/1045aserciones,0fallos/errores/omitidos y sin deprecaciones/notices;506fuentes estables. Incluye SMTP13sesiones, AUTH535 permanente,454 temporal, fallback valido y downgrade sin AUTH/sobre/DATA; HTTPS8sesiones con curl60 y ceroHTTP/credenciales al rechazarCA. Guzzle8 on_stats aporta error estructurado, singetHandlerContext de Guzzle7 ni imprimir excepciones. Primer guard incorrecto uso isTLS (configuracion de TLS implicito) y rechazo STARTTLS valido; eliminado por ESMTP obligatorio y prohibicion de fallback inseguro.

Regresion SQL fix1-lab-and-smtp-worker:12casos/214aserciones,1fallo de etiquetaSMTP incierta; los otros11 pasan, incluido worker535/politicaTLS sin nueva generacion. Aviso SMTP restaurado sin tocar el test; focal fix1-legacy-notice1caso/8aserciones verde. Tanda final completa y regresiones de actividad/MCP/supresiones se ejecutan antes de QA. Source snapshots de cada tanda conservados privadamente; no se suman ejecuciones solapadas ni se afirma un snapshot unico anterior al ultimo ajuste de texto.

## Revisión de dos lentes — intento 2: Revalidar guardas ESMTP y negativos AUTH, oraculos y guias corregidas (T-01, T-02, T-03, T-04, T-05, T-06)

Veredicto: revision A+B+C+D superada,0Critical/Important/Minor pendientes. C1-01/B1-01/A1-03 corregidos y revalidados; regresion final y QA siguen pendientes.

## Propuestas para Knowledge Gate

Propuestas del implementer, pendientes del juicio exclusivo de Curator. CLI2.3devolvio incomplete_inventory sin publicar; la API documentada con rootPath preservo las guardas y publico ambas por el servicio, sin edicion manual de memoria. No se exporta a backend externo.

```curation-refs-v1
[
  {
    "candidate": "docs/knowledge/candidates/pending/2026-10-31-smtp-starttls-ehlo-fallback.md",
    "sha256": "9a6a917e6d2609a4e4430947e71dd9f4cfc3bf19f939d394a888970cf6cc569e",
    "operation_id": "8b5249bdecca726dee392688ebb823d3f4f43a8e3eb775b087736540dac82489"
  },
  {
    "candidate": "docs/knowledge/candidates/pending/2026-10-31-smtp-auth-terminal-negative.md",
    "sha256": "84a2468eebeadfe8d72393ef4ef822a21354a4bb2a57758a8ed5f394821cb464",
    "operation_id": "528326ba3539cbef19d631aa8a2bb2a7f2d9831e28b60a0ac687beb561586c5a"
  }
]
```

### Fusion del intento2

| # | Grado | Gap | Tarea | Correccion | Evidencia |
|---|---|---|---|---|---|
| C1-01 | Critical previo | Downgrade de STARTTLS | T-03/T-06 | corregido y verificado por C2/A2 | Probe C2 en memoria7escenarios,0AUTH/sobre/DATA en5negativos; cable13/432verde |
| B1-01 | Important previo | AUTH535 reintentable | T-03/T-06 | corregido y verificado por B2/A2 |535definitivo,454transitorio,fallbackvalido;cable+workerSQL sinrequeue |
| A1-03 | Minor previo | Documentacion desactualizada | T-06 | corregido y verificado por A2 | Parrafos97/99 y guia proveedores |

LenteD2sin hallazgos de coste/locks/IO en corregido; se conservan limites de benchmark D1. Informes originales [A2](testing/review/attempt-2-A.md), [B2](testing/review/attempt-2-B.md), [C2](testing/review/attempt-2-C.md), [D2](testing/review/attempt-2-D.md). OpenAPIdiffs/lint y599fixtures verificados por A2. No se acredita QA global con esta revision.

### Ultima regresion antes de QA

fix1-regression-final ejecuto16casos/277aserciones:15pasaron y1oraculo de ActivityTest mantenia9estados al sembrar el nuevo decimo api_accepted. No fallo de producto. Se cambio el esperado a10 y se anadio exigencia explicita de una fila api_accepted, conservando aislamiento de cuenta y todas las etiquetas. final-focused-regression acredita4casos/73aserciones,0fallos/errores/omitidos: ese caso, replay REST y2guardas legadas de supresion. No cambia codigo productivo respecto de la revision2. El escenario anterior de aviso SMTP ya fue verde y los11MailTransport pasaron completos en regfinal. No se suman filas solapadas como si fueran tests nuevos ni se borra la evidencia roja.

QA de navegador intento1 autorizado sobre seis originales, despues de revision2 sin gaps y sin conexiones SQL de tests activas. Congelacion y limpieza por harness propio; pruebas reales de proveedor y juicio humano siguen M-01/M-02.

## Revisión de dos lentes — intento 3: Validar feedback tardio entre intentos y correcciones de selectores QA; solo delta posterior al intento2 (T-01, T-02, T-03, T-04, T-05, T-06)

Pendiente de revisión; sin veredicto.


### Diagnostico y correccion tras QA1

QA1 original:2/6 pasan,4fallan,0flaky/omitidos/interrumpidos; gate1, limpieza completa y507fuentes identicas. Se conservan [informe rojo](testing/qa-1/report.md) y JSON nativo. Checkbox con regex anclado whitespace usa ahora rol/nombre accesible; vuelta a cuentaA espera dashboard como ida aB; estado ambiguo se exige en lista visible en vez de option oculto; feedback exige etiqueta y fecha UTC que el producto concatena. No se elimina ningun escenario/assert, no sube timeout ni se habilitan retries.

Caso adicional confirmado: callback delivered despues de requeue429 y antes del siguiente inicio durable permitia otro envio (RED1/6). beforeStart bajo mutex de cuenta comprueba feedback de cualquier intento antes de cuota/IO; finish consulta todos los intentos para impedir una reprogramacion posterior si el callback anterior llega durante IO actual. El inicio durable sigue siendo el punto de linearizacion: un callback posterior no cancela IO ya iniciado ni acredita exactly-once externo. Verde late-feedback-green3/38 (incluye rate limit normal y callback anticipado), prior-feedback-green1/11 (SMTP454, callback del intento previo durante segundo IO, cero tercera generacion). Pint verde. Clave T-06-fix2 cerrada con telemetria estimada/no disponible, sin horas/tokens reales inventados. Revision3 reevalua solo delta y conserva veredictos2 completos; QA2 pendiente.


### Fusion del intento3 (A+B+C+D)

Veredicto: revision3 superada,0Critical/Important/Minor nuevos o reabiertos. Los veredictos2 completos se conservan; C1-01/B1-01/A1-03 siguen cerrados. A3 confirma criterios y que los selectores no debilitan puertas; B3 sigue caller y settlement; C3 verifica scope de feedback y mutex autenticado; D3 comprueba EXISTS indexado sin IO bajo locks. [A3](testing/review/attempt-3-A.md), [B3](testing/review/attempt-3-B.md), [C3](testing/review/attempt-3-C.md), [D3](testing/review/attempt-3-D.md). Scope3exit0:174cambios,0fuera/criteriosalterados/avisos (antes de mover auxiliares QA a storage). No hay Jira opt-in.

Validacion final del codigo: final-provider-pipeline8/241 y final-provider-units90/1045 verdes,0fallos/errores/omitidos y506fuentes identicas; incluye matriz4canales×REST/MCP/campanas, callback antes/entre/durante intentos y TLS. Contratos599casos y packaging3verdes sobre indice aislado con12entradaspublicas. Primera prueba packaging omitio REST en su seleccion temporal y comparo HEADantiguo: corregida SOLO la seleccion del arnes, sin cambiar pins/producto/pruebas. QA2 independiente repite6originales con contador2de3, tras cerrar todos los SQLtests root.


### QA2 y correccion acotada de whitespace

QA2gate1:5/6pasan,0flaky/omitidos/interrumpidos,507fuentes estables y cleanup completo. SoloE2E06fallo: snapshot nativo contiene Queja; destinatario suprimido con fechaUTC; `activity.blade.php:48` deja espacio trasUTC antes deendif y regexUTC$lo rechazaba. El ajuste unico es UTC\s*$, en el mismo parrafo/lista visible, manteniendo etiqueta/fecha y todas las demas aserciones. Sin cambio productivo desde revision3. [Informe original QA2](testing/qa-2/report.md). Teclado/mobile y envio posterior suprimido NO alcanzados enQA2.

Build final `npm run build` exit0, outputpublic/build contenido verificado dentroworkspace, despues de detenerQA2. Manifiesto5360413f27210f025d350c599fe8f951ae85084637b81cc549a801a2ba39facd. QA3finalde3 parte de ese bundle actual y conserva snapshotmanifest antes/despues; no se cambia asset durante su ejecucion. Marcadores sintelemetria, valores realesnull.


### QA3 verde y documentacion

QA3finalde3:6/6originales verdes,gate0,0flaky/omitidos/interrumpidos y cleanup completo.507fuentes+bundlemanifestCSS/JS identicos antes/despues; manifiesto final5360413f27210f025d350c599fe8f951ae85084637b81cc549a801a2ba39facd. Handoff directo a Documenter recibido, informe/PDF en cierre porQA. [Originales](testing/qa-3/raw/results.json), [gate](testing/qa-3/raw/qa-gate.json), [aceptacion](testing/acceptance.md). Pint--test y PHPlint71rutasexplicitas verdes, sin mutacion. Se fijan atributos de bytes para evidencia testing; sin cambios runtime desde revision3, salvo regex de prueba whitespace explicado. Documenter independiente actualiza guias con estructura autorizada; Knowledge Gate se ejecutara despues, sin promover manualmente los candidatos.


### Documenter y Knowledge Gate terminados

Documenter independiente:10paginasactualizadas (7aplicacion,2indices,APIREADME),350enlaceslocales/anchors validos y diffcheck0; fecha31oct2026. Guia4recetas detalladas, permisos, activacion, rotacion/clear, incertidumbre, callbackdurable y limites reales. Sinruntime/esquemas modificados.

Curator aprobo2GOTCHAs porservicio tras comparar12entradaslegadas+5approved: emailing.GOT-018 y emailing.GOT-019. No contradiccion ni nueva decision; fixesTLS/AUTH yaautorizados. Gateoriginal evidencialista corregido mediante metadatafinalvalidated_case+fuentes justificadas, sin editaroriginal. Recibos completos, destinosverificados y fuentesexactas retiradas; indexfinal7entradas/0errores. Los2operation_idreales quedan enhandofforiginal candidato/SHAarriba; approved/gotchas conserva los documentos. No exportacionexternal ni promocionlegacy.

Publicacion y cierretecnico T06 en curso. La spec permanecera aprobada hasta retrohumana/gate, sin inventar medidas ni cierreprincipal/MCP.


## Fase 4 — Manual completo e instalacion desde cero

**Estado**: en-progreso ? **Estimado**:12h base ? **Real**:? ? **Coste est.**:600 EUR equivalentes+tokens por verificar ? **Tokens est.**:160000

### T-07 — Completar documentacion y manual Markdown/PDF para publico no tecnico

- **Descripción**: Ampliacion explicita del usuario del31oct: manual detallado con ejemplos y diagramasMermaid, instalaciondesde cero importante, sin opciones actuales sin documentar tanto en docs como en PDF. No cambia runtime ni reinicia contadores de revision/QA de T01-T06.
- **Estado**: en-progreso
- **Tiempo humano**: est.12h ? real?
- **Tiempo IA (ejec.)**: est.0.533333h (estimado por tokens) ? real?
- **Supervisión**: est.0.133333h (25 % IA) ? real?
- **Previsión IA**:133333in/26667outtok; tarifas por verificar
- **Coste estimado**:600 EUR equivalentes base+tokens, no gasto observado ni cargo.
- **Dependencias**: T-01, T-02, T-03, T-04, T-05
- **Tipo**: documentation
- **Archivos**: `.gitattributes`, `docs/manual/**`, `docs/aplicacion/**`, `docs/infraestructura/**`, `docs/api/README.md`, `docs/api/implementacion.md`, `docs/api/ejemplos.md`, `docs/api/decisiones.md`, `docs/README.md`, `docs/RAG-INDEX.md`, `README.md`, `infra/README.md`, `docs/roadmap/2026-10-30-proveedores-email/**`, `docs/roadmap/README.md`, `CHANGELOG.md`, `docs/en/CHANGELOG.md`
- **Cubre (tests)**: E2E-01, E2E-02, E2E-03, E2E-04, E2E-05, E2E-06, M-01, M-02
- **Capacidades**: to-pdf, frontend-quality, delivery-practices
- **Verificación**: inventario trazable de pantallas/configuracion/servicios/endpoints y37herramientas contra Markdown/manual; enlaces/anchors validos; todos los Mermaid parseados y renderizados en PDF; comprobacion de paridad de contenido, texto, paginas y capturas de todas las paginas, sin recortes/solapes ni referencias rotas. Revisores frescos de la documentacion ampliada y QA visual independiente, sin repetir SQL/E2E de producto sin cambios.
- **Changelog**: Manual completo en Markdown y PDF con instalacion desde cero, ejemplos, diagramas y cobertura de uso y administracion.

**Criterios de aceptación**

- [ ] CA-19: instalacion desde cero Docker y Laragon, requisitos efectivos, configuracion privada, datos persistentes, primer acceso, workers/scheduler y comprobaciones explicados con pasos y resultados; perfiles QA y operativo identificados correctamente.
- [ ] CA-20: todas las opciones actuales de aplicacion/infra/API/MCP inventariadas tienen documento dueno y explicacion autosuficiente en el manual; ejemplos completos, glosario y resolucion de problemas accesibles a publico no tecnico; capacidades no cualificadas identificadas sin inventarlas.
- [ ] CA-21: Markdown y PDF contienen el mismo manual completo, con diagramas Mermaid renderizados, indice, enlaces, tablas, paginacion y revision visual de todas las paginas sin defectos pendientes.
- [ ] CA-22: guias Markdown e indices coherentes y sin huecos de cobertura, enlaces/anchors validos, sin secretos ni datos reales; revision documental y QA de artefacto acreditadas; commits/push posteriores a completar el manual.

**Subtareas**

- [ ] Inventariar funciones, rutas, configuracion, servicios y comandos; cerrar huecos documentales y actualizar guias actuales.
- [ ] Escribir manual pedagogico completo e instalacion limpia, ejemplos y Mermaid; generar PDF desde la misma fuente.
- [ ] Validar cobertura, enlaces, paridad y diagramas; revisar todas las paginas y corregir defectos de contenido/maquetacion.
- [ ] Conservar evidencia original de revision/QA documental, completar changelog y publicar seleccion segura despues del manual.

**Notas**: Fuente literal: peticion de documentoPDFdetallado,no tecnico,ejemplos/graficosMermaid; instalaciondecerodestacada; nada sin documentarenMarkdownniPDF; despuescommitsypush. El presupuesto anterior C01-C06/104h/864000tokens se conserva como linea base; esta ampliacion C07 estima12h/160000tokens (baja confianza), subtotal116h/1024000tokens, con margen20%139.2h/1228800tokens/6960EUR equivalentes+tokens sin verificar. No hay medidas reales. Manual es documentacion de funciones actuales, no implementacion de los pendientes globales. Se respetan las revisiones3/QA3 ya cerradas de codigo; esta tarea nueva tiene revision/QA documental propias.


### Implementacion documental T07 para revision

Manualfuente16855palabras,24capitulos,8Mermaid; instalaciondesde ceroconPaso0herramientas/WSL/Git/copiaactual,perfilespersistentesDocker/Laragon,Salir/Editarlistaexplicitos,37MCPy71variables comprobadas poridentidadexacta enmanualymatriz. CorpusDocumenter33paginas/1003enlaces/anclas0faltantes; rootanadioexplicacionespaso0/navegacion ytoolsREADME/exporter/verificador sinruntimecambios. PDF54paginasen2pasadas,879/879bloquesfuente y todasetiquetasde8diagramaspresentes;0textofuera/menor7.75ptencuerpo/omitido. TOCpagina igualentrepasses;graficosmin9.09pt estimados. SourceSHAdacb156aa8b69e8ce23cce3562bcacac7192cfbee5ed3d9b4284178ab1e06120,PDF578f1e3cd86cc22a07a26304e14487097b63665a86bcfe4ad0fe5be7f74e71cd. PDFygraficosproducidosofflineconChrome/PlaywrightsiguiendoHTML/CSStopdf;PyMuPDFparaextraccion/render. Dependenciasherramientasexternasalrepo,nocambiosapp/locks. Estadoverificacionvisualpublica aunpendienteQAindependiente. InstalacionrealNOejecutada paraescribirmanual;procedimientopublicos/limitacionesexplicitas.

LaT07provienedeencargonuevodelusuario;revisiondocumentalintento1 separado dehistorialT01-T06revision3 yQA3vigentes, que nose reinician ni se atribuyenalPDF. Las puertas decodigo siguenaceptadas.

## Revisión de dos lentes — intento 1: Manual completo e instalacion desde cero; ampliacion documental autorizada posterior al codigo (T-07)

Pendiente de revisión; sin veredicto.


### Fusion documental T07 intento1 (A+B+C+D)

0Critical/3Important/0Minor pendientes. A1-01:Laragoncarecedeobtenciontools/PATH/extensiones/pruebasplataforma desdecero. A1-02:coberturaautosu?cienteinsuficienteenSMTPgenericopreparatorio/Recordarme/memoriaPHP256M/logginginfrajson-file10m3; debenexplicarseenmanual, no soloenlazar otra guia. A1-03=B1-01:26URLsfuentePDFseconviertenaHTTPloopbackdelrenderertemporal. Rootverificolas3contrafuentes yPyMuPDF; no se rebaten ni rebajan. C1/D1sin hallazgos. [A1](testing/manual/review/attempt-1-A.md),[B1](testing/manual/review/attempt-1-B.md),[C1](testing/manual/review/attempt-1-C.md),[D1](testing/manual/review/attempt-1-D.md). InstalacionrealnoejecutadacorrectamentedistinguidasinpedirfalsocierreT20; QAvisualsiguependienteprevista. Se corrigecontenido/exportadoryseregeneraartefactoantesdeintento2; no cambioruntime.


### Correccion documental tras intento1

A1-01 corregido:obtencionLaragon/PHP8.4.1+,terminalheredaPATHseleccionado,Composer2/Node22.12+,versiones/phpini/modulos ycheck-platform-reqs--lock antesdelinstall; fuentesoficiales ywrappersreales. A1-02corregido:Recordarme+permisos, formularioSMTPgenericocompleto(puerto587/rango1..65535,TLS,blankconservauserpass,clearprioritariolocal), memoriaPHP256Mporproceso yloggingINFRAjson-file10mx3sinatribuirloappstorage; propietariasguiaCuentas/Transporte alineadas. A1-03/B1-01corregido:relativosMDconvertidosaHTTPSramaGitHub/archivospublicosconexistenciaylimites; indiceinternoconservado. Verificadorahorarechazalinksrenderer/debidosfuenteausentes.

Regeneracion2pasadasfix1-final1/fix1-final2:57paginas,24capitulos,8diagramas,919/919bloques y etiquetascompletas,0textofuera/pequeno/linksrenderer/fuenteausentes;26refsdocestables,TOCmapsiguales,34paginasMD/1015links0errores. Manual18105palabras,source0b8d519494af8efecca184a56ed987d671b2a97a4597467f07fa551aad8494fe,PDF330446b36205f3e63a21f021e44de8b174a7fd5636d7185ffe60ee1b57d39cd7. Guia/publicPDFmetadatos actualizados; QAvisualindependiente siguependiente. No cambiosruntime507fuentesigualQA3. No se alteraron assertions/umbrales para pasar; validatoragrega guardasURI yfuentes. Revalidaciondocumentalintento2solo corregido,veredictos1 completos conservados.

## Revisión de dos lentes — intento 2: Revalidar instalacion Laragon completa, cobertura concreta y enlaces estables PDF corregidos (T-07)

| Lente | Critical | Important | Minor | Veredicto | Evidencia |
|---|---|---|---|---|---|
| A | 0 | 0 | 0 | Sin gaps; tres correcciones previas revalidadas | [A2](testing/manual/review/attempt-2-A.md) |
| B | 0 | 0 | 0 | Sin defectos; 26 referencias estables comprobadas | [B2](testing/manual/review/attempt-2-B.md) |
| C | 0 | 0 | 0 | Sin hallazgos de seguridad del delta | [C2](testing/manual/review/attempt-2-C.md) |
| D | 0 | 0 | 0 | Sin hallazgos de rendimiento del delta | [D2](testing/manual/review/attempt-2-D.md) |

Fusion: 0 Critical / 0 Important / 0 Minor. A1-01, A1-02 y A1-03=B1-01 corregidos; ningun gap pendiente. Revisores genericos de contexto fresco, modelo heredado real al no estar disponible el rol nativo. No se reabre runtime ni QA3. Siguiente puerta: QA independiente del artefacto completo; T07 permanece en progreso.


### QA documental independiente T07

[Informe y originales](testing/manual/qa-1/report.md): VERDE, verificador exit0; 57 paginas A4 inspeccionadas individualmente a la resolucion completa del render1.15, cuatro hojas de contacto y los ocho diagramas. 919/919 bloques, 26 referencias estables, cero links temporales, cuerpos pequenos o texto fuera; 37/37 herramientas y 71/71 variables en manual/matriz; 34 Markdown/1015 enlaces sin errores. Ledger, coverage y gate sobre JSON original browserQA3 exit0 (6/6), sin relanzar runtime. Source0b8d519494af8efecca184a56ed987d671b2a97a4597467f07fa551aad8494fe; PDF330446b36205f3e63a21f021e44de8b174a7fd5636d7185ffe60ee1b57d39cd7.

[Paridad runtime](testing/manual/validation/runtime-parity.json):507 fuentes identicas a QA3; documentacion no exige nuevas pruebas SQL/E2E. Ninguna instalacion operativa ni proveedor real ejecutados. Knowledge Gate del codigo completo; la ampliacion documental no identifica nuevos candidatos sustantivos. Publicacion posterior a esta aceptacion, todavia pendiente hasta comprobar commits/push.


Control de publicacion: alcance373rutas/0fuera/0criterios alterados; identificadores2333/0. El checker de patrones conserva exit1 por una coincidencia binaria en PNG, no un dato visible: [original](testing/manual/validation/public-patterns-original.json) y [disposicion comprobada](testing/manual/validation/binary-pattern-disposition.json). Unico match en IDAT comprimido, cero en pixeles descomprimidos y sin metadata de texto; imagen original revisada y conservada sin modificar. No se rebaja el checker, no se oculta el exit ni se certifican secretos privados/historial.
