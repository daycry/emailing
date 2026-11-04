# Preparación de clientes nativos: versiones y ayuda local

Se ejecutaron los binarios locales con argumentos de versión y ayuda, sin
registrar servidores, autenticar, listar configuraciones, llamar modelos o
conectar a Emailing. Los recibos son proyecciones estructuradas del coordinador;
no contienen stdout/stderr literales ni rutas personales.

| Cliente | Versión nativa observada | Resultado |
|---|---|---|
| Codex CLI |0.166.0 |`--version` exit0; distinto del0.165.0 inventariado antes. |
| Claude Code |2.1.311 |`--version` exit0. |
| OpenCode V2 |2.0.12 |`--version` exit0; no se reinterpreta el intento histórico fallido por permisos. |

[Versiones](versions.public.json) y [ayuda MCP](help.public.json).
Las cinco órdenes de ayuda terminaron exit0: Codex `mcp`, `mcp add` y
`mcp login`; Claude Code `mcp`; OpenCode `mcp`, siempre con `--help`.
No se ejecutó una orden de mutación o autenticación sin ese indicador.

Para Codex/Claude se extraen sólo opciones declaradas en su sección de ayuda.
Los ejemplos no equivalen a opciones declaradas: el extractor inicial capturaba
el placeholder `--some-flag` y se corrigió antes de publicar. OpenCode tiene un
formato de opciones no reconocido por ese extractor; se conserva `null`, sin
deducir que no tenga opciones. Los nombres de subcomandos son menciones de
ayuda, sin acreditar su comportamiento.

La [matriz](../../client-matrix.md) y la [guía de clientes](../../../../../aplicacion/mcp-clientes.md)
conservan la distinción entre recetas, versiones y conexión efectiva. La sintaxis
de los ejemplos sigue identificada con el cotejo oficial del30 de octubre;
la ayuda nueva no valida automáticamente todos sus campos de configuración.

No se solicitan cambios de perfil ni se abren credenciales manualmente. Las561
fuentes de la regresión y sus fixtures no cambian. OAuth, llamada útil,
denegación, caducidad, revocación, Google real y manuales siguen pendientes en
T-14. Este inventario no cierra ninguna tarea ni constituye QA independiente.
