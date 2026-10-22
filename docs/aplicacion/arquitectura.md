# Cómo relaciona la aplicación identidades, cuentas y permisos

**Última actualización: 2026-10-22.** Arquitectura implementada en la fase 1. Consulta [desarrollo y pruebas](desarrollo.md) para los límites de instalación y QA.

## Qué pertenece a una cuenta

Una identidad `User` puede participar en varias cuentas mediante `Membership`. La cuenta es el límite de recursos compartidos. Retirar una membresía quita acceso y conserva los recursos de esa cuenta.

```text
User ──< Membership >── Account
                         ├── owner_id → User
                         ├── Invitation
                         └── AuditEvent
```

Fuentes: [modelos](../../app/Models/Account.php), [Membership](../../app/Models/Membership.php), [User](../../app/Models/User.php) y [migraciones](../../database/migrations). Contactos, plantillas, campañas y llaves API serán recursos de cuenta en fases posteriores. No están implementados por existir su vocabulario de permisos.

## Cómo se resuelve la cuenta activa

[routes/web.php](../../routes/web.php) agrupa las rutas bajo autenticación. `/accounts` muestra las membresías y `POST /accounts/switch` comprueba pertenencia antes de guardar `account_id` en sesión.

[AccountContext](../../app/Services/AccountContext.php) vuelve a consultar la membresía al resolver la cuenta y al autorizar acciones. Si la sesión apunta a una cuenta revocada, devuelve 403 y solicita seleccionar otra disponible. No confía en un rol guardado previamente en el navegador.

```text
Petición autenticada → cuenta en sesión → membresía vigente
                    → permiso → operación delimitada por account_id
```

Los identificadores de miembros e invitaciones se buscan dentro de la cuenta activa. La reducción de permisos o retirada de acceso se aplica en la siguiente acción.

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

Los presets `developer`, `marketer` y `accountant` rellenan scopes del rol Restricted. Son puntos de partida editables, no roles adicionales. El catálogo describe la futura autorización de contactos, plantillas, campañas, envíos, actividad, supresiones, ajustes y llaves API. Su presencia no habilita esos módulos.

La fase actual prueba autorización de cuenta y equipo. API y workers de negocio deberán aplicar el mismo límite al implementarse; no se afirma su cobertura por estas pruebas.

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

[Team](../../app/Livewire/Team.php) limpia errores de validación después de completar invitación, edición o transferencia. La regresión FB01 comprueba el reintento correcto sobre el mismo formulario. La memoria conserva el [gotcha de frontend](../knowledge/gotchas/GOT-001-errores-livewire-tras-reintento.md) como propuesta pendiente; esta página no lo promueve.

## Qué decisiones consultar

La [ADR-001](../knowledge/adr/ADR-001-rabbitmq-centralizado.md) fija el broker compartido. [ADR-002](../knowledge/adr/ADR-002-laravel-mysql-compose.md) recoge el stack y [ADR-004](../knowledge/adr/ADR-004-limites-del-runtime-y-origen-publico.md), los límites del runtime y el origen de enlaces. Consulta el estado vigente en cada entrada; esta documentación no decide su aceptación.

[ADR-003](../knowledge/adr/ADR-003-publicacion-confirmada-y-estado-smtp.md) sigue siendo una propuesta para la entrega futura. No hay campañas, confirms de negocio ni feedback real implementados en esta fase.
