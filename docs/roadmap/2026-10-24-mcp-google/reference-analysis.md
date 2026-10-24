# Referencia: Social Scheduler

Revisión de arquitectura realizada el 24-10-2026 sobre el repositorio público
[`daycry/social-scheduler`](https://github.com/daycry/social-scheduler), fijado al
commit `7f542a548613eb535e767fc10081fc21dc5b792a`. El nombre indicado en la conversación
se resolvió contra los metadatos reales de GitHub.

Se descargaron 40 archivos seleccionados y se verificó su identidad contra los
blobs del árbol Git. Se leyeron los componentes de los flujos descritos y varias
pruebas relacionadas. No se ejecutaron el proyecto ni sus pruebas. Este análisis
no constituye una auditoría completa del repositorio ni acredita la conexión con
clientes MCP reales.

## Cómo está organizado

| Función | Implementación observada | Aplicación a Emailing |
|---|---|---|
| Identificar al usuario | Socialite, controlador de login social, identidades por proveedor e identificador estable | Google como método de acceso a la identidad local |
| Conectar una red social | Registro de conectores, contrato común, OAuth por proveedor y selección de cuentas por marca | Referencia para separar proveedores; no añade redes sociales al alcance de Emailing |
| Autorizar al agente | Servidor OAuth propio sobre Sanctum, consentimiento y PKCE S256 | Base candidata para el acceso delegado al MCP |
| Ejecutar herramientas | Laravel MCP, clase base que verifica abilities y servicios con usuario explícito | Herramientas sobre servicios compartidos con web/API y contexto explícito de cuenta |

El [manifiesto de dependencias](https://github.com/daycry/social-scheduler/blob/7f542a548613eb535e767fc10081fc21dc5b792a/composer.json)
declara Laravel 13, Laravel MCP, Sanctum y Socialite. No declara Passport. Las
versiones declaradas no se presentan como versiones resueltas del lockfile.

## Login social y conexión a redes

El [controlador de login](https://github.com/daycry/social-scheduler/blob/7f542a548613eb535e767fc10081fc21dc5b792a/app/Http/Controllers/Auth/SocialLoginController.php)
usa drivers de Socialite dedicados a Google y Facebook. Su configuración se
separa de las credenciales de los conectores de publicación. El
[resolutor de identidades](https://github.com/daycry/social-scheduler/blob/7f542a548613eb535e767fc10081fc21dc5b792a/app/Services/Auth/SocialIdentityResolver.php)
busca proveedor e identificador, conserva la desvinculación mediante una marca de
bloqueo y permite vinculación manual desde el perfil. El primer acceso con Google
puede vincular una cuenta local existente únicamente si los correos están
verificados y coinciden según su comparación explícita; no crea usuarios. El
cierre de sesión inicial pasa por `LoginFlow`, que conserva el reto 2FA cuando
corresponde.

La conexión de redes es otro flujo: el
[controlador OAuth de conexiones](https://github.com/daycry/social-scheduler/blob/7f542a548613eb535e767fc10081fc21dc5b792a/app/Http/Controllers/OAuth/OAuthController.php)
valida `state`, revalida la marca y obtiene candidatos mediante un conector. Si
hay varias cuentas, conserva los candidatos cifrados en sesión para su selección.
El [modelo de cuenta social](https://github.com/daycry/social-scheduler/blob/7f542a548613eb535e767fc10081fc21dc5b792a/app/Models/SocialAccount.php)
declara tokens cifrados y ocultos en la serialización. El registro separa conexión,
publicación y lectura de métricas mediante contratos de capacidades.

## Autorización MCP

El [servidor de autorización](https://github.com/daycry/social-scheduler/blob/7f542a548613eb535e767fc10081fc21dc5b792a/app/Services/Mcp/OAuth/AuthorizationServer.php)
implementa clientes públicos, código de autorización y PKCE S256. Los controladores
propios sirven descubrimiento OAuth, registro dinámico, consentimiento y canje.
Los códigos se guardan mediante hash, su consumo usa una actualización atómica y
un replay revoca el token emitido. El token resultante es de Sanctum, con las
abilities elegidas y caducidad. No implementa refresh tokens en esta versión.

El consentimiento ocurre dentro de la sesión web. Por tanto, el usuario puede
iniciar sesión con Google y volver a autorizar el MCP sin entregar una contraseña
o un token de Google al agente. El scope del protocolo es `mcp:use`; la selección
granular de acciones se representa con abilities de aplicación. La
[clase base de herramientas](https://github.com/daycry/social-scheduler/blob/7f542a548613eb535e767fc10081fc21dc5b792a/app/Mcp/Tools/SchedulerTool.php)
comprueba esas abilities, pasa el usuario a los servicios y audita el resultado.
Por ejemplo, `CreatePost` llama a `PostService` en lugar de implementar otra vía de
publicación.

El [ADR de la referencia](https://github.com/daycry/social-scheduler/blob/7f542a548613eb535e767fc10081fc21dc5b792a/docs/knowledge/adr/ADR-004-oauth-mcp-propio-sobre-sanctum-y-login-social-socialite.md)
explica por qué se eligió OAuth propio sobre Sanctum frente a Passport. Esa
decisión corresponde a aquel proyecto; aquí es una alternativa fundada que debe
evaluarse con los contratos y permisos de Emailing.

## Adaptación propuesta

- Mantener Socialite para Google y Laravel MCP para el protocolo; evaluar primero
  la adaptación del patrón OAuth sobre Sanctum ya existente en la referencia.
- Vincular el grant a usuario, cliente y cuenta de Emailing. Las herramientas
  deben volver a comprobar la membresía y los permisos vigentes de Teammates.
- Distinguir las autorizaciones delegadas de usuario de las API keys de cuenta.
  La revocación de una membresía debe invalidar el acceso delegado correspondiente.
- Hacer explícitos el propósito y la audiencia del token MCP. Los tokens de Google
  y las cookies de sesión no deben servir como Bearer del endpoint MCP.
- Reutilizar los servicios de contactos, listas, plantillas, campañas y envío;
  conservar outbox, idempotencia, supresiones, auditoría y Mailpit/log.
- Adaptar la política de registro y vinculación al funcionamiento de Emailing.
  No trasladar automáticamente la restricción de cuentas preexistentes ni el
  autolink por correo de la referencia.

## Verificación pendiente

La [documentación MCP del repositorio](https://github.com/daycry/social-scheduler/blob/7f542a548613eb535e767fc10081fc21dc5b792a/docs/modulos/mcp.md)
indica expresamente que los smokes manuales con Claude Code, Claude Desktop,
Codex y OpenCode están pendientes. Se observaron pruebas de código del flujo
OAuth, aislamiento y retorno al consentimiento tras el login social; no se
ejecutaron durante esta revisión. No se hereda una afirmación de compatibilidad
probada a Emailing.

Antes de cerrar la implementación deberán verificarse las versiones reales de
los clientes, descubrimiento, registro, callbacks, permisos, expiración,
revocación y aislamiento entre cuentas. También habrá que decidir si se necesita
renovación de tokens y revisar las diferencias entre el protocolo actual y el
servidor mínimo de referencia. Las credenciales de Google se configurarán fuera
del repositorio público.
