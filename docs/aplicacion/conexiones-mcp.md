# Conectar un cliente MCP

Inicia la conexión desde tu cliente MCP. La aplicación te pedirá entrar con tu acceso local o Google y mostrará el cliente, tus cuentas disponibles y los permisos solicitados. Elige una cuenta y revisa las capacidades antes de autorizar. Los permisos que tu membresía no permite aparecen desactivados. También puedes denegar la solicitud.

El cliente recibe el resultado y completa el canje automáticamente. No necesitas crear ni pegar una llave API. La conexión tiene una duración máxima de una hora; para continuar, vuelve a autorizar desde el cliente. No se emiten refresh tokens en esta versión.

En **Conexiones MCP** puedes consultar tus conexiones y su cuenta, permisos y caducidad, aunque cambies la cuenta activa de la web. Abre **Revocar conexión**, revisa la confirmación y pulsa **Confirmar revocación**. Se cortan sus tokens y sesiones; otras conexiones continúan vigentes. Quitar tu membresía también corta las conexiones de esa cuenta. Una nueva autorización vuelve a comprobar tus permisos actuales.

Tu actividad reciente muestra operación, resultado, cuenta, cliente y correlación. No muestra códigos, tokens ni contenido de contactos. Al denegar antes de elegir una cuenta, la auditoría indica «Sin cuenta elegida».

Implementación actual de esta fase: transporte y autorización con dos herramientas productivas (`lists_get` y `lists_create`) como prerrequisito de las comprobaciones de aislamiento. Las otras herramientas del catálogo siguen las tareas posteriores del roadmap. Los ensayos de proveedor Google y cliente son fixtures de laboratorio; no representan una conexión externa real.
