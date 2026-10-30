# Clientes MCP del laboratorio

**Última actualización: 2026-10-30.** Ejemplos ficticios preparados en T-11 y
cotejados con las fuentes oficiales en esa fecha. No se instalan en los clientes
ni acreditan una conexión. Google real y las conexiones nativas se validan en T-14.

## Preparar el perfil y los clientes públicos

El servidor debe publicar su recurso MCP mediante un origen HTTPS canónico, accesible y con certificado confiable para el cliente. `APP_URL`, `MCP_ISSUER` y `MCP_RESOURCE` deben corresponder al perfil. Sustituye el dominio reservado de los ejemplos por ese origen, también en `oauth_resource` de Codex. El perfil `https://localhost:18443` superó los ensayos técnicos de aplicación/TLS y recuperación en ambos runtimes; consulta la [matriz](../roadmap/2026-10-24-mcp-google/testing/client-matrix.md). Esto no acredita clientes nativos.

Emailing usa clientes OAuth públicos con PKCE, sin secreto de cliente. El registro dinámico está deshabilitado por defecto y no se publica soporte CIMD. Prerregistrar cada producto permite distinguirlo en la auditoría. Estas recetas modifican SQL: aplicarlas sólo cuando el perfil esté preparado, en una ventana exclusiva, después de las suites:

```powershell
php artisan mcp:client-register "Codex CLI laboratorio" --redirect=http://127.0.0.1:18791/callback
php artisan mcp:client-register "Claude Code laboratorio" --redirect=http://localhost:18792/callback
php artisan mcp:client-register "OpenCode laboratorio" --redirect=http://127.0.0.1:18793/callback
```

Usar el ID devuelto para cada producto en lugar de los UUID ficticios. Los puertos fijos permiten redirects exactos; no necesitan la excepción `--native-port`. Fuente del comando y del registro: `app/Console/Commands/McpRegisterClient.php` y `app/Services/Mcp/OAuth/AuthorizationServer.php`. Las recetas anteriores están preparadas, no ejecutadas.

## Codex CLI

[codex.example.toml](ejemplos-mcp/codex.example.toml) separa `oauth_resource`, al nivel del servidor, de los campos `oauth.client_id`, `callback_url` y `callback_port`. La URL y el listener deben utilizar el mismo puerto en un callback loopback directo. El servidor Emailing anuncia soporte de `iss` y devuelve su emisor para conservar la vinculación del callback. [Referencia de configuración](https://learn.chatgpt.com/docs/config-file/config-reference) y [MCP de Codex CLI](https://learn.chatgpt.com/docs/extend/mcp?surface=cli).

Para solicitar sólo lectura de contactos en una autorización concreta, la CLI documenta:

```text
codex mcp login correo --scopes contacts.read
```

La documentación específica de CLI prioriza ese override y después los scopes configurados; una lista vacía permite elección de la biblioteca. No trasladar a CLI las reglas descritas para la superficie app. Revisar los permisos de Emailing antes de consentir. [Referencia de comandos](https://learn.chatgpt.com/docs/cli/reference) y [scopes de CLI](https://learn.chatgpt.com/docs/extend/mcp?surface=cli).

## Claude Code

[claude.example.json](ejemplos-mcp/claude.example.json) es un ejemplo del objeto `mcpServers`, con transporte HTTP y `oauth.clientId`/`callbackPort`. Prerregistrar `http://localhost:18792/callback`: las versiones actuales documentadas utilizan `localhost`. No intercambiarlo con la IP en el registro exacto. El ámbito de instalación de la CLI y los scopes OAuth son conceptos diferentes; `--scope project` no concede permisos de negocio. Para un cliente público se omite `--client-secret`. Después de preparar su configuración, `/mcp` abre el acceso por navegador. [Documentación de Claude Code](https://code.claude.com/docs/en/mcp).

Esta fila corresponde a **Claude Code**. Claude Desktop, su pestaña Code y los conectores alojados requieren filas y pruebas propias; este ejemplo no acredita su compatibilidad.

## OpenCode V2

[opencode.example.json](ejemplos-mcp/opencode.example.json) usa `mcp.servers` y los campos OAuth en snake_case, incluido `scope`, `client_id`, `callback_port` y `redirect_uri`. Se omite `client_secret` para el cliente público. `protocol: legacy` selecciona `initialize` hasta la revisión 2025-11-25; `auto` y `2026-07-28` tienen recorridos distintos que deben probarse por separado. El acceso se inicia mediante `opencode mcp auth correo`. [MCP de OpenCode V2](https://opencode.ai/v2/docs/mcp-servers).

## Acceso, permisos y estado de la validación

El navegador puede acceder con contraseña o con Google cuando el operador lo configure. La aplicación pide elegir cuenta y consentir permisos compatibles con la membresía. Ninguno de estos ejemplos contiene el secreto Google ni tokens de Emailing. La conexión de Emailing caduca como máximo en una hora y esta versión no emite refresh tokens; repetir la autorización cuando corresponda. La revocación se comprueba con una petición rechazada usando la credencial anterior, no sólo con un acuse HTTP de revocación.

| Producto observado | Evidencia disponible | Estado real pendiente |
| --- | --- | --- |
| Codex CLI 0.165.0 | Manifest del paquete instalado consultado el 29; CLI observada en la preparación anterior. | Configuración privada, OAuth, llamada útil, denegación, revocación y caducidad. |
| Claude Code 2.1.311 | CLI observada el 28; versión no revalidada hoy. | Mismo recorrido real; la documentación no sustituye la versión ejecutada. |
| OpenCode V2 2.0.12 | Manifest del paquete instalado consultado el 29; su comando de versión anterior no terminó por permisos de log. | Ejecución nativa real y mismo recorrido OAuth/MCP. |

La lectura de manifest identifica paquetes, no demuestra qué cliente se ha conectado.
Las comprobaciones de estos ejemplos analizan sintaxis TOML/JSON y cotejan campos
documentados. Los ensayos técnicos de servidor Laragon/Docker incluyen ambos
protocolos, reinicio, rotación, revocación, restore y scheduler; constan en la
matriz y en [operación MCP](mcp-operacion.md). T11 está aceptada técnicamente
en el ledger. Google real, conexiones nativas y manuales
M-01–M-04 del destino permanecen en T14. No publicar configuraciones reales,
credenciales, códigos de callback ni capturas sin sanitizar.
