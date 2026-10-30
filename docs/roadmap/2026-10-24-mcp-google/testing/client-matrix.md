# Matriz de perfiles y clientes MCP

Fecha: 2026-10-30. Una versión inventariada o un ejemplo que parsea no acredita
conexión. T11 prepara configuración y operación; Google real, compatibilidad de
clientes y los manuales M-01–M-04 conservan su cierre en T14.

## Clientes nativos

Los dominios y UUID de [los ejemplos](../../../aplicacion/mcp-clientes.md) son
ficticios. Sustitúyelos sólo en configuración privada. Todos los clientes son
OAuth públicos con PKCE S256; no llevan secreto de cliente. El registro dinámico
de Emailing está deshabilitado por defecto y no se anuncia CIMD.

| Producto | Versión observada y procedencia | URL/transporte objetivo | Registro y callback propuesto | Red y estado de conexión |
| --- | --- | --- | --- | --- |
| Codex CLI | 0.165.0; metadata del paquete instalado, 2026-10-29. | `https://emailing.example.test/mcp`, Streamable HTTP. | Cliente prerregistrado, `http://127.0.0.1:18791/callback`; `oauth_resource` igual al recurso MCP. | El cliente necesita alcanzar HTTPS y confiar en su CA; callback en su propio equipo. **Documental, no aprobado como conexión.** |
| Claude Code | 2.1.311; CLI observada el 2026-10-28, sin nueva ejecución de versión. | Mismo origen ficticio y transporte HTTP. | Prerregistrado; `http://localhost:18792/callback`, `oauth.clientId` y `callbackPort`. | `localhost` e IP no son intercambiables en el registro exacto. **Documental, no aprobado como conexión.** |
| OpenCode V2 | 2.0.12; metadata del paquete instalado, 2026-10-29; el intento anterior de CLI falló por permisos de log. | Mismo origen ficticio; `protocol: legacy` en el ejemplo. | Prerregistrado; `http://127.0.0.1:18793/callback`, `client_id`, `callback_port`, `redirect_uri`. | Probar `legacy` y `2026-07-28` por separado; configuración V2 bajo `mcp.servers`. **Documental, no aprobado como conexión.** |
| Claude Desktop / cliente alojado | No ejecutado; producto distinto de Claude Code. | Fuera de los tres objetivos nativos iniciales. | No deducido de la receta de Code. | Sin acreditación ni compatibilidad declarada. |

Las fuentes oficiales de sintaxis se cotejaron el 2026-10-30:
[Codex](https://learn.chatgpt.com/docs/config-file/config-reference),
[Claude Code](https://code.claude.com/docs/en/mcp) y
[OpenCode V2](https://opencode.ai/v2/docs/mcp-servers).
Las versiones efectivamente conectadas, sus capacidades y los resultados OAuth,
llamada útil, denegación, caducidad y revocación se incorporarán al ejecutar T14.

## Perfiles de servidor

| Perfil | Origen y contrato | Runtime | Alcance de la prueba |
| --- | --- | --- | --- |
| Laragon / Windows nativo | `https://localhost:18443`, recurso `/mcp`; revisiones 2025-11-25 y 2026-07-28. | PHP/PHP-CGI 8.5.12; Nginx 1.28.2, OPcache aislada por ID. | Ensayo técnico completo y limpieza correcta: reinicio, rotación, revocación y restore. [Evidencia](fase-5/t11/laragon-admission.public.json). No acredita Apache ni cliente nativo. |
| Docker / Linux | Mismo origen, recurso, contratos y migraciones; infraestructura externa compartida. | PHP 8.4.27, Nginx1.28.3; Compose5.6.1. | Mismo ciclo técnico completo y limpieza correcta, imágenes por ID y sin montar código del host. [Evidencia](fase-5/t11/docker-admission.public.json). No acredita Google ni cliente nativo. |

La guía [de operación](../../../aplicacion/mcp-operacion.md) describe TLS, secretos
por fichero, procesos, backups, rotación e invalidación de copias antiguas. El
runner distingue smoke de calificación completa. No se instala una CA global ni
se modifica la configuración de los clientes durante estas pruebas.
El scheduler de ambos perfiles ejecutó dos pasadas periódicas reales, antes y
después de reinicio/recuperación de mutex; [recibos](fase-5/t11/fix1/scheduler-admission.json).
La composición final se probó con el servicio añadido y Docker volvió a ejecutar
ambos protocolos con el scheduler activo. Es operación Laravel de esta aplicación,
con Cronicle y el resto de infraestructura compartidos.

## Google y manuales

El operador indicó que Google OAuth todavía no está configurado. Sus credenciales
y callback se suministran privadamente. No se ha realizado un login real con
Google en ninguno de estos perfiles. Las identidades sintéticas usadas por las
pruebas no prueban propiedad de una cuenta Google.

Los checklists manuales permanecen en [test-plan.md](../test-plan.md): M-01 Google
real, M-02 controles que requieren revisión humana, M-03 clientes reales y M-04
configuración/recuperación del servidor de destino. La evidencia local de T11
podrá cubrir el ensayo técnico de M-04 sin marcar hecha la validación del destino
privado ni cerrar T14.
