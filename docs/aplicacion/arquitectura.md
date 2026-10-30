# Cómo relaciona la aplicación cuentas, permisos y módulos

**Última actualización: 2026-10-30.** Arquitectura de los componentes de laboratorio implementados: cuentas, campañas, entrega y API, con identidad Google y autorización MCP delegada. Consulta [desarrollo y pruebas](desarrollo.md) y los ledgers para los límites de instalación, aceptación y QA.

## Qué pertenece a una cuenta

Laragon y Docker conservan los mismos contratos y migraciones. El perfil HTTPS
del laboratorio termina TLS en Nginx, fija el esquema en FastCGI y usa un origen
canónico configurado; no deriva la autoridad de cabeceras de proxy arbitrarias.
Consulta [operación MCP](mcp-operacion.md) para secretos persistentes, almacenamiento,
reinicios y el procedimiento de invalidación tras restaurar una copia antigua.
T11 verificó ambos perfiles HTTPS con sus dos protocolos MCP, reinicio, rotación,
revocación y restore de fixtures; cada scheduler completó dos pasadas reales.
La aceptación está registrada en el ledger; esta evidencia no acredita Google, clientes
nativos ni el servidor privado de destino de T14.

Una identidad `User` puede participar en varias cuentas mediante `Membership`. La cuenta es el límite de recursos compartidos. Retirar una membresía quita acceso y conserva los recursos de esa cuenta.

```text
User ──< Membership >── Account
                         ├── owner_id → User
                         ├── Invitation
                         ├── AuditEvent
                         ├── Contact ──< contact_list_memberships >── ContactList
                         ├── ContactImport ──< ContactImportRow
                         ├── EmailTemplate
                         ├── MailSetting / MailSender
                         └── MailMessage → artefacto privado opcional
```

Fuentes: [modelos](../../app/Models/Account.php), [Membership](../../app/Models/Membership.php), [User](../../app/Models/User.php) y [migraciones](../../database/migrations). Contactos, listas, informes CSV, plantillas, transporte, campañas, entregas, supresiones y llaves API son recursos de cuenta. Las identidades Google pertenecen al usuario. Cada grant MCP liga una persona, un cliente, una cuenta, un recurso y unos permisos; no hereda la cuenta activa del navegador.

## Cómo se resuelve la cuenta activa

[routes/web.php](../../routes/web.php) agrupa las rutas bajo autenticación. `/accounts` muestra las membresías y `POST /accounts/switch` comprueba pertenencia antes de guardar `account_id` en sesión.

[AccountContext](../../app/Services/AccountContext.php) vuelve a consultar la membresía al resolver la cuenta y al autorizar acciones. Si la sesión apunta a una cuenta revocada, devuelve 403 y solicita seleccionar otra disponible. No confía en un rol guardado previamente en el navegador.

```text
Petición autenticada → cuenta en sesión → membresía vigente
                    → permiso → operación delimitada por account_id
```

Los identificadores de miembros, invitaciones y recursos de fase 2 se buscan dentro de la cuenta activa. Los recursos ajenos conservan 404. La reducción de permisos o retirada de acceso se aplica en la siguiente acción y al leer el módulo. Los archivos privados se descargan mediante rutas autenticadas que vuelven a comprobar cuenta y scope.

## Cómo se crean las cuentas y sesiones

[CreateNewUser](../../app/Actions/Fortify/CreateNewUser.php) normaliza email y exige contraseña de al menos 12 caracteres. Si `REGISTRATION_ENABLED` lo permite, crea identidad, cuenta, membresía Admin y evento en una transacción.

Fortify proporciona registro, acceso y recuperación con vistas propias. El login limita intentos por email e IP. [bootstrap/app.php](../../bootstrap/app.php) incorpora la comprobación de sesiones autenticadas. Tras restablecer contraseña, también se invalidan sesiones anteriores al primer acceso al panel, según las regresiones de [PasswordResetSessionTest](../../tests/Feature/PasswordResetSessionTest.php).

[TrustedUrl](../../app/Support/TrustedUrl.php) construye el origen de los enlaces secretos con `APP_URL` confiable y una ruta relativa. Las notificaciones de recuperación e invitación no toman su origen de la cabecera Host de la petición.

## Qué significa cada rol

[Permissions](../../app/Services/Permissions.php) centraliza un catálogo de 14 scopes y tres roles:

| Rol | Regla actual |
|---|---|
| `admin` | Todos los scopes conocidos y administración de Teammates. |
| `read-only` | Scopes conocidos terminados en `.read`; puede consultar el equipo. |
| `restricted` | Solo los scopes seleccionados de su membresía; sin administración de Teammates. |

Los presets `developer`, `marketer` y `accountant` rellenan scopes del rol Restricted. Son puntos de partida editables, no roles adicionales. Contactos, plantillas, envío, actividad, ajustes, campañas, supresiones y llaves API aplican sus scopes actuales. Una credencial delegada añade sus propios permisos como límite: nunca amplía los de la membresía vigente.

La web, la API y las herramientas MCP comparten servicios de negocio y límites por cuenta. Los workers procesan trabajo durable ligado a la cuenta; no utilizan una sesión web para elegirla. Los resultados de cada capa se acreditan en su tarea y no se deducen de las pruebas de otra capa.

## Cómo se reparte el código de cada módulo

| Capa | Piezas y responsabilidad |
|---|---|
| Entrada HTTP | [routes/web.php](../../routes/web.php): páginas autenticadas y descargas privadas. |
| Formularios | [app/Livewire](../../app/Livewire): estado de UI, paginación, validación y mensajes visibles en vistas propias. |
| Cuenta y autorización | `AccountContext` y `Permissions`: membresía vigente y scopes conocidos. |
| Contactos e importación | [ContactService](../../app/Services/ContactService.php), [ContactImportService](../../app/Services/ContactImportService.php) y [ContactCsv](../../app/Support/ContactCsv.php): CRUD, validación estructural, deduplicación y transacciones. |
| Plantillas | [TemplateService](../../app/Services/TemplateService.php), [TemplateRenderer](../../app/Support/TemplateRenderer.php) y [TemplatePreview](../../app/Support/TemplatePreview.php): persistencia, sustitución acotada y documento aislado. |
| Ajustes y envío de prueba | [MailSettingsService](../../app/Services/MailSettingsService.php): scopes, remitentes, credenciales cifradas y descargas. |
| Transporte | [AccountMailTransport](../../app/Services/AccountMailTransport.php) y [SmtpConnector](../../app/Services/SmtpConnector.php): cuenta explícita, log/Mailpit y resultado SMTP sin promesa de entrega. |
| Almacenamiento | MySQL aislado por cuenta y discos privados `contact-imports` / `mail-artifacts`; no son archivos del disco público. |

```text
Ruta autenticada → componente Livewire → servicio → AccountContext/scopes
                                      → transacción SQL y auditoría
CSV → validación completa de estructura → filas válidas/duplicadas/inválidas
Plantilla guardada + muestra → renderizador → transporte log o Mailpit
Formulario de plantilla → renderizador → filtro de preview → iframe aislado
```

Las escrituras de contactos, plantillas y ajustes, y la importación, bloquean la cuenta y vuelven a leer la membresía para serializarse con cambios de permisos. El importador agrupa consultas e inserciones de 500 elementos y guarda informe por fila. Sus límites no equivalen a capacidad ilimitada.

El transporte recibe una cuenta explícita y no depende de sesión HTTP. Las fachadas autorizan antes de aceptar el trabajo; outbox, publicación, consumo y finalización mantienen estados durables. Consulta [entrega y recuperación](entrega-y-recuperacion.md) para las garantías y los resultados ambiguos. La prueba de plantilla usa contenido guardado, mientras la preview usa los valores actuales del formulario.

Consulta [contactos e importación](contactos-e-importacion.md) y [plantillas y transporte](plantillas-y-transporte.md) para campos, límites, operaciones y ejemplos.

## Cómo protege las invitaciones y los cambios concurrentes

[TeamService](../../app/Services/TeamService.php) genera tokens aleatorios de 64 caracteres y almacena únicamente SHA-256. El enlace tiene siete días de validez. La aceptación exige sesión con el email destinatario, token vigente y ausencia de aceptación o revocación previa.

| Acción | Efecto |
|---|---|
| Invitar otra vez al mismo email pendiente | Revoca las invitaciones abiertas anteriores y crea una nueva. |
| Reenviar | Rota el token y renueva siete días; el enlace anterior deja de servir. |
| Revocar | Impide aceptación posterior. |
| Aceptar | Crea o reutiliza membresía, marca aceptación y activa la cuenta. |

Las transacciones bloquean primero la cuenta y después el recurso afectado. El servicio vuelve a leer el rol del actor bajo bloqueo. Así serializa aceptación, cambios de permisos y propiedad sobre esa cuenta. Véase [TeammateConcurrencyTest](../../tests/Feature/TeammateConcurrencyTest.php).

La notificación SMTP se envía después de confirmar la transacción. Su transporte actual es Mailpit local. El envío fiable mediante outbox y reconciliación pertenece a fases futuras; no se deduce de esta operación.

## Cómo se conserva un administrador y se registra el cambio

Solo el propietario puede transferir propiedad, con comprobación de su contraseña actual. La operación promueve al destinatario a Admin y conserva al propietario anterior como Admin.

El servicio impide eliminar o degradar al propietario y protege al último administrador. La auditoría guarda cuenta, actor, acción y metadatos de invitación, permisos, retirada y transferencia. La UI muestra los últimos 30 eventos de la cuenta a sus administradores.

[Team](../../app/Livewire/Team.php) limpia errores de validación después de completar invitación, edición o transferencia. La regresión FB01 comprueba el reintento correcto sobre el mismo formulario. El [GOT-001 aceptado](../knowledge/gotchas/GOT-001-errores-livewire-tras-reintento.md) añade la conservación de errores ajenos al validar campos auxiliares. Importación usa `validateOnly` para que buscar listas no oculte el rechazo del archivo; las pruebas comprueban el aviso renderizado, además del estado interno.

## Qué decisiones consultar

La [ADR-001](../knowledge/adr/ADR-001-rabbitmq-centralizado.md) fija el broker compartido. [ADR-002](../knowledge/adr/ADR-002-laravel-mysql-compose.md) recoge el stack y [ADR-004](../knowledge/adr/ADR-004-limites-del-runtime-y-origen-publico.md), los límites del runtime y el origen de enlaces. Consulta el estado vigente en cada entrada; esta documentación no decide su aceptación.

[ADR-003](../knowledge/adr/ADR-003-publicacion-confirmada-y-estado-smtp.md) sigue
siendo una propuesta para T-10–T-16. La revisión 13 aprueba los componentes de
campañas, bajas/supresiones, outbox y consumidor JSON, con reservas y cuotas.
El Compose separa ese consumidor del worker ordinario Laravel. Actividad T-14,
Cronicle T-15 y QA integral T-16 están aceptados técnicamente en laboratorio. Consulta
[entrega y recuperación](entrega-y-recuperacion.md),
[actividad y métricas](actividad-y-metricas.md) y el ledger para los resultados.
El feedback externo sigue sin acreditación.

[GOT-002 aceptado](../knowledge/gotchas/GOT-002-limpieza-de-uploads-de-qa.md) documenta la limpieza atribuida de temporales: raíz efectiva de cada perfil, referencias exactas y ejecución de limpiezas independientes aunque otra falle.

La infraestructura sigue en un Compose separado, con una sola instancia central de RabbitMQ y Cronicle. Consulta [integración de aplicaciones](../infraestructura/integracion-aplicaciones.md). La [evidencia final QA3](../roadmap/2026-10-21-plataforma-emailing/testing/fase-2/report-attempt-3.md) cubre ambos perfiles; M-01 humana y las siguientes fases siguen pendientes.

El servicio `emailing-scheduler` pertenece al Compose de aplicación y ejecuta
`schedule:work`; `routes/console.php` programa `mcp:prune --limit=1000` cada minuto
con `withoutOverlapping`. Comparte configuración, almacenamiento y caché de la
aplicación. El Cronicle central conserva sus triggers de negocio. La [guía de
operación MCP](mcp-operacion.md#scheduler-de-la-aplicación) evita duplicar ese
calendario y explica la recuperación de su mutex con las pasadas detenidas.

## Contexto delegado MCP

T-04 introduce `ExecutionContext` y `DelegatedContext`: cuenta, actor y techo
consentido proceden de filas SQL verificadas, separadas de las llaves API de
cuenta. `AccountContext` conserva el adaptador web y admite un contexto explícito
por constructor. Los servicios no cambian el usuario o selector de sesión para
ejecutar una delegación. Campaign y Suppression comprueban también que su cuenta
y actor explícitos coincidan con el contexto recibido.

Las mutaciones revalidan permisos dentro de su transacción bajo el orden cuenta,
membresía, cliente, grant, token y recurso. TeamService usa el mismo mutex de
cuenta; retirar una membresía revoca sus grants de forma terminal. Reducir scopes
limita inmediatamente la intersección vigente. Las llaves API de cuenta conservan
su ciclo independiente. La [ADR-008 propuesta](../knowledge/adr/ADR-008-contexto-explicito-y-mutex-delegado.md)
registra la decisión y el adelanto de la persistencia necesaria desde T-05.
El [cierre técnico de fase 3](../roadmap/2026-10-24-mcp-google/testing/fase-3/acceptance.md)
acredita revisión 2 A+B+C+D, backend QA1 y navegador QA2 sobre las mismas fuentes.
Se aceptan T-04–T-07 en laboratorio. El [contrato y catálogo](mcp-contrato.md)
documenta las 37 operaciones T-08–T-10 aceptadas después, incluido el envío por
el pipeline común. [Conexiones MCP](conexiones-mcp.md) describe consentimiento
y revocación; [clientes](mcp-clientes.md), los ejemplos ficticios y su procedencia.
La verificación técnica T11 añade perfiles HTTPS y recuperación; el
[QA independiente](../roadmap/2026-10-24-mcp-google/testing/fase-5/t11/qa-2026-10-30/report/report.md)
pasó nueve escenarios originales con limpieza propia admitida. La regresión
global sigue en T12; Google/clientes reales y M-01–M-04 del destino privado, en
T14. El simulacro global de restauración y la entrega mantienen T19/T20.
ADR-008 permanece como propuesta de conocimiento, sin promoción automática.
