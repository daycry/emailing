# Cómo acceder a tus cuentas y gestionar Teammates

**Última actualización: 2026-10-23.** Guía de cuentas validada en los perfiles de pruebas y comprobada de nuevo en QA3 de fase 2. La puesta en operación requiere inspección del esquema, copia y plan. Contactos, CSV, plantillas y transporte de laboratorio ya están disponibles; campañas y API siguen pendientes.

## Cómo entrar y recuperar el acceso

Abre la URL del perfil que te haya indicado el equipo. En QA nativo es <http://127.0.0.1:8082>; en QA Docker, <http://127.0.0.1:8083>. Estos perfiles comparten datos de pruebas y no son un espacio para información de trabajo real.

Si el registro está habilitado, utiliza **Crea tu cuenta** e indica tu nombre, nombre de cuenta, email y contraseña. La contraseña necesita al menos 12 caracteres y confirmación. El registro te convierte en propietario y administrador de esa nueva cuenta.

Para recuperar acceso, usa la opción de contraseña olvidada e introduce tu email. En este laboratorio los mensajes aparecen en [Mailpit](http://127.0.0.1:18025), sin salir a Internet. Después del cambio, vuelve a iniciar sesión en tus otros navegadores: las sesiones anteriores dejan de ser válidas.

## Cómo elegir la cuenta correcta

Abre **Mis cuentas** y pulsa **Abrir cuenta** en el espacio deseado. Una misma dirección puede pertenecer a varios equipos y tener permisos diferentes en cada uno.

Comprueba la cuenta activa antes de invitar o modificar accesos. Los miembros comparten los recursos de esa cuenta. Retirar a una persona no borra esos recursos.

Si ves «Ya no tienes acceso a esta cuenta», abre **Mis cuentas** y selecciona otra disponible. Si no queda ninguna, pide una invitación al administrador.

## Qué acceso elegir al invitar

| Acceso | Cuándo utilizarlo |
|---|---|
| Administrador | La persona debe gestionar accesos y recursos de la cuenta. |
| Solo lectura | La persona necesita consultar y no modificar. |
| Restringido | Quieres escoger permisos concretos del catálogo. |

Developer, Marketer y Accountant son selecciones iniciales para el acceso Restringido. Puedes ajustar sus casillas. Contactos, plantillas, envío de pruebas, actividad y ajustes ya aplican esos permisos. Campañas, supresiones y llaves API siguen pendientes.

Consulta [contactos e importación](contactos-e-importacion.md) y [plantillas y transporte](plantillas-y-transporte.md) para elegir los permisos de cada operación. Developer permite probar una plantilla guardada sin acceder a contactos ni configuración; la muestra se escribe en el formulario.

Solo los administradores gestionan invitaciones y accesos. Los demás miembros pueden consultar el equipo.

## Cómo invitar y aceptar una invitación

1. Entra en **Teammates** dentro de la cuenta correcta.
2. Escribe el email, elige el acceso y pulsa **Enviar invitación**.
3. La persona destinataria abre su enlace y accede con la misma dirección que recibió la invitación.
4. Revisa la cuenta e invitación y confirma la aceptación.

El enlace dura siete días y solo puede aceptarse una vez. En el laboratorio, consulta el mensaje en Mailpit. Si la persona todavía no tiene identidad y el registro está habilitado, puede registrarse con ese email y volver al enlace.

| Estado | Qué hacer |
|---|---|
| Pendiente | Esperar la aceptación o usar Reenviar/Revocar. |
| Caducada | Un administrador puede reenviar un enlace nuevo. |
| Revocada | El enlace ya no permite entrar; crea una invitación nueva si corresponde. |
| Aceptada | La persona ya pertenece al equipo; modifica su membresía. |

**Reenviar** invalida el enlace anterior y abre otros siete días. Una invitación emitida de nuevo para el mismo email también sustituye las invitaciones pendientes anteriores. Si recibes varios mensajes, utiliza el enlace más reciente.

## Cómo cambiar permisos o retirar acceso

En la fila de la persona pulsa **Editar**, ajusta el acceso y guarda. Para retirarlo, pulsa **Retirar acceso** y confirma. Los permisos vigentes se comprueban en la siguiente acción, aunque la persona conserve la página abierta.

La aplicación protege al propietario y al último administrador. Si necesitas cambiar al propietario, utiliza la transferencia; no intentes retirarlo primero.

En móvil la tabla permite desplazamiento horizontal dentro de su tarjeta. Deslízala para llegar a las acciones de la derecha.

## Cómo transferir la propiedad

El propietario abre **Transferir la propiedad**, selecciona otro miembro, introduce su contraseña actual y confirma. El nuevo propietario queda como administrador. El propietario anterior conserva su acceso de administrador.

Si la contraseña es incorrecta, corrígela y vuelve a confirmar en el mismo formulario. Cuando la transferencia termina, el panel muestra el éxito y retira el error anterior.

## Cómo consultar los cambios y sus límites

Los administradores ven **Registro de accesos y cambios**, con los últimos 30 eventos de la cuenta y hora UTC. Incluye creación, invitaciones, aceptación, cambios de permisos, retirada y transferencia.

El [informe de fase 1](../roadmap/2026-10-21-plataforma-emailing/testing/report.md) conserva sus pruebas de cuentas. [QA3 de fase 2](../roadmap/2026-10-21-plataforma-emailing/testing/fase-2/report-attempt-3.md) vuelve a comprobar acceso, equipo y roles junto a los nuevos módulos: 20/20 casos en ambos perfiles. La revisión humana de claridad y adaptación, M-01, sigue pendiente.

Para detalles técnicos, consulta [cuentas y permisos](arquitectura.md). Para preparar el entorno, consulta [desarrollo y pruebas](desarrollo.md).
