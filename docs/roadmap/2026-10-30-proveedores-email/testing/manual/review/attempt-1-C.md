# T07 — revisión adversarial, lente C, intento DOC1

Fecha: 2026-10-31. Contexto fresco. Revisor genérico heredado: agente reviewer/Opus no disponible.

## Veredicto

**Sin hallazgos de seguridad introducidos o reabiertos por el delta documental T07.**

Critical: 0. Important: 0. Minor: 0. No hay defectos que requieran escenario de explotación o clasificación CWE.

## Alcance y evidencia

Revisión estática de `docs/manual/` (fuente completa, exportador, verificador, README, matriz, tema y diagramas), más los cambios de prosa e índices de README, infraestructura, aplicación y API. T01–T06 y los contratos/runtime previamente aceptados no se reabren. La activación global de C procede de cambios de código/IaC anteriores; aquí se revisa exclusivamente el incremento T07 y su CLI local de autoría.

| Frontera revisada | Evidencia | Resultado |
|---|---|---|
| Inyección de HTML/plantilla durante exportación | `docs/manual/exportar.mjs:33` escapa metadatos; `:34` deshabilita HTML Markdown; `:64` inicializa Mermaid con seguridad strict y sin HTML labels; `:85` recibe el SVG de ese renderer antes de `innerHTML` en `:86`; `:105` rechaza imágenes Markdown | No aparece una entrada web/MCP ni un origen atacante que atraviese estas guardas; fuente manual propia |
| Lectura de archivos por servidor temporal | `docs/manual/exportar.mjs:60` permite solo GET; `:66` limita el prefijo Mermaid; `:67` resuelve el destino; `:69` comprueba pertenencia al dist, extensión y ruta real; `:74` escucha en 127.0.0.1 | No hay ruta de lectura de configuración o datos de aplicación |
| Solicitudes externas/SSRF | `docs/manual/exportar.mjs:77` bloquea service workers y `:78` solo deja GET al origen propio, incluido su puerto y separador `/` | No se añade un proxy de red ni una descarga arbitraria desde el contenido |
| Escrituras y carga de dependencias | `docs/manual/exportar.mjs:26` fija salida relativa al proyecto; `:28` limita pertenencia y `:30` comprueba ruta real; `docs/manual/verificar.py:24` resuelve la entrada y `:25` exige pertenencia al proyecto | Opciones CLI y caché de herramientas son control del operador local; no se identifica un llamante remoto ni consumidor no confiable |
| Secretos y procedimientos de acceso | `docs/manual/guia-completa.md:249`, `:286`, `:455`, `:517`, `:1321`, `:1605`, `:1728` | Publica nombres/rutas de ejemplo y custodia privada; no publica valores de credenciales ni propone desactivar validación TLS, compartir administración o servir el repositorio completo |
| Recuperación y autoridad restaurada | `docs/manual/guia-completa.md:1912` y `:1941` y siguientes | Mantiene el destino aislado, la salida cerrada, la invalidación MCP previa a reapertura y la distinción frente a llaves REST |

Los ocho SVG generados no contienen coincidencias literales de `<script`, `javascript:`, `<foreignObject`, `onload=` ni `onclick=`. Es una comprobación estática acotada, no un certificado universal de sanitización.

La fuente y PDF públicos revisados coinciden con `docs/manual/verificacion.json`:

- Markdown SHA-256: `dacb156aa8b69e8ce23cce3562bcacac7192cfbee5ed3d9b4284178ab1e06120`.
- PDF SHA-256: `578f1e3cd86cc22a07a26304e14487097b63665a86bcfe4ad0fe5be7f74e71cd`.

## Límites

No se ejecutaron instalaciones, migraciones, comandos operativos del manual, suites, builds, renderizadores ni conexiones reales. No se leyeron entornos privados, claves, volcados SQL ni los artefactos prohibidos. No se modificó código fuente ni documentación del producto. Esta lente no sustituye QA visual del PDF ni auditoría completa de seguridad.
