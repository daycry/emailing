# Cómo relaciona la aplicación cuentas, permisos y módulos

**Última actualización: 2026-10-23.** Arquitectura implementada en las fases 1 y 2. Consulta [desarrollo y pruebas](desarrollo.md) para los límites de instalación y QA.

## Qué pertenece a una cuenta

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

Fuentes: [modelos](../../app/Models/Account.php), [Membership](../../app/Models/Membership.php), [User](../../app/Models/User.php) y [migraciones](../../database/migrations). Contactos, listas, informes CSV, plantillas, transporte y mensajes ya son recursos de cuenta. Campañas y llaves API siguen pendientes; su vocabulario de permisos no los implementa.

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

Los presets `developer`, `marketer` y `accountant` rellenan scopes del rol Restricted. Son puntos de partida editables, no roles adicionales. Contactos, plantillas, pruebas de envío, actividad de pruebas y ajustes aplican sus scopes actuales. Campañas, supresiones y llaves API mantienen permisos reservados para módulos futuros.

La fase actual prueba autorización de cuenta, equipo y módulos de fase 2. API y workers de negocio deberán aplicar el mismo límite al implementarse; no se afirma su cobertura por estas pruebas.

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

El transporte recibe una cuenta explícita y no depende de sesión HTTP. Las fachadas autorizan antes de llamarlo; esto prepara su reutilización sin acreditar todavía workers de entrega. La prueba de plantilla usa contenido guardado, mientras la preview usa los valores actuales del formulario.

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
El Compose separa ese consumidor del worker ordinario Laravel. Actividad está
en validación T-14; Cronicle y QA integral siguen pendientes T-15/T-16. Consulta
[entrega y recuperación](entrega-y-recuperacion.md),
[actividad y métricas](actividad-y-metricas.md) y el ledger para los resultados.
No hay QA integral de fase 3 ni feedback externo acreditado.

[GOT-002 aceptado](../knowledge/gotchas/GOT-002-limpieza-de-uploads-de-qa.md) documenta la limpieza atribuida de temporales: raíz efectiva de cada perfil, referencias exactas y ejecución de limpiezas independientes aunque otra falle.

La infraestructura sigue en un Compose separado, con una sola instancia central de RabbitMQ y Cronicle. Consulta [integración de aplicaciones](../infraestructura/integracion-aplicaciones.md). La [evidencia final QA3](../roadmap/2026-10-21-plataforma-emailing/testing/fase-2/report-attempt-3.md) cubre ambos perfiles; M-01 humana y las siguientes fases siguen pendientes.
