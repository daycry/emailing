---
id: ADR-009
estado: propuesta
fecha: 2026-10-25
iniciativa: mcp-google
---

# ADR-009: Frontera de entrada y transporte MCP

## Contexto

Laravel MCP 1.1.1 admite Laravel 13 y contiene ambos perfiles contratados, pero su lista predeterminada de servidor sólo anuncia 2026-07-28. Su paginación predeterminada es 15/50 y su middleware decodifica JSON antes de validar cabeceras. Los normalizadores globales de Laravel también llaman al decoder. Estas opciones no satisfacen los límites 25/100, la conservación de lexemas ni el servidor de autorización propio de ADR-006.

## Decisión propuesta

Usar el SDK estable fijado en composer.lock para negociación, descubrimiento, despacho y serialización. La ruta HTTP propia construye su HttpTransport con el cuerpo validado; no instala el adaptador de autenticación ni los validadores de ruta predeterminados del SDK. TokenResolver consulta exclusivamente tokens MCP y verifica el contexto SQL sin cambiar Auth o session.

Una frontera global específica de /mcp consume admisión SQL por IP antes de leer hasta 4 MiB más un byte, valida UTF-8, profundidad y miembros duplicados y retiene lexemas numéricos. Se excluyen de esa ruta los normalizadores de strings de Laravel. La validación de enteros contractuales antecede al decoder del SDK; los demás números y strings se conservan. El parser REST permanece independiente e intacto. Al ejecutarse antes de TrustProxies, esta frontera cobra al peer del socket y no interpreta cabeceras reenviadas.

El perfil moderno es sin sesión, exige metadatos completos y espejos de cabecera coherentes. El perfil legado crea una sesión SQL ligada al token, con caducidad hasta la del token y un máximo local de ocho sesiones activas por token. Crear e inicializar una sesión revalida autoridad bajo el orden de locks común. Cada petición vuelve a verificar token, grant y membresía.

El catálogo usa keyset por nombre y un cursor Crypt con propósito propio de registro, ligado al recurso y autoridad actual; no sustituye el cursor de datos de negocio contratado para las tareas posteriores. BoundedHttpTransport cuenta el sobre final del SDK y corta páginas sin saltar elementos. El servidor usa sólo respuestas síncronas; rechaza generadores antes de enviar bytes. Slots MySQL por cuenta limitan a ocho ejecuciones simultáneas por defecto y se liberan en finally o al desconectar la conexión. No se habilita SSE ni otro transporte.

Los errores de herramientas sólo pasan como DTO fijo producido por ToolError, nunca como texto arbitrario del mecanismo Errable del SDK, incluso con APP_DEBUG. Se conserva outcome y referencia propia cuando una operación ya confirmó su efecto. Las pruebas de esta frontera usan probes explícitamente de prueba y un efecto SQL de auditoría sintético; no acreditan las herramientas de negocio, la entrega ni clientes externos.

La fábrica valida las enumeraciones del contrato y proyecta referencias sólo a kind+id ASCII de 1 a 100 caracteres; un identificador inválido se descarta completo. Comprueba además el presupuesto de 16 KiB del texto de error, separado del límite de 8 MiB del sobre final. El contrato JSON es una entrada runtime única: el contexto Docker incluye sólo docs/api/mcp-tools.json y excluye los demás documentos y ficheros *.private.env. Un build y un probe de imagen sin montajes verifican esta presencia; no acreditan el transporte HTTP dentro de Docker.

Para satisfacer composer validate --strict se expresan tres constraints como rangos patch (~7.2.1, ~5.31.0 y ~1.1.1). El lock conserva todas las versiones y metadatos de paquetes; sólo cambia content-hash tras el refresco oficial de Composer.

## Consecuencias

Los límites y errores dependen de una frontera pequeña mantenida por la aplicación, comprobada contra el SDK real. Las actualizaciones del SDK requieren repetir ambos perfiles y los límites del sobre final. Los contratos aceptados, las 37 operaciones y el pin REST no se modifican. La cualificación con clientes reales y el despliegue siguen en T-14; revisión y QA de esta propuesta están pendientes.

## Fuentes

- [Laravel MCP 1.1.1](https://github.com/laravel/mcp/releases/tag/v1.1.1), commit e77af70cd009aaa31455626bd463b0e7e39cfcf9.
- [Laravel 13 MCP](https://laravel.com/framework/docs/13.x/mcp).
- [Streamable HTTP 2026-07-28](https://modelcontextprotocol.io/specification/2026-07-28/basic/transports/streamable-http).
- [Contrato MCP](../../aplicacion/mcp-contrato.md) y [ledger](../../roadmap/2026-10-24-mcp-google/tasks.md).
