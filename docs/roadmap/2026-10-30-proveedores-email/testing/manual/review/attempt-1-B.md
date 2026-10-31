# Revisión T07 — Lente B — intento 1

Revisor genérico de contexto fresco; el agente nativo reviewer no estaba disponible. Alcance exclusivamente documental T07 y exportador. No se reabre runtime T01–T06 aprobado. Revisión estática y lectura del PDF ya entregado; no se ejecutaron instalaciones, renderizados, migraciones, suites ni proveedores reales.

## Veredicto

**Un defecto Important pendiente; cero Critical y cero Minor.**

### B1 — Important — Los enlaces a fuentes del PDF apuntan al servidor temporal cerrado

- **Localización:** `docs/manual/exportar.mjs:140` (también `:128`, `:139`, `:145` y cierre en `:154`).
- **Escenario concreto:** abrir el PDF canónico y pulsar «matriz de cobertura», «Compose de aplicación» o cualquier otra fuente relativa de la página 54. El destino es `http://127.0.0.1:59263/cobertura-documentacion.md`, `http://127.0.0.1:59263/compose.yaml`, etc. El usuario recibe una conexión fallida; en otro equipo esa dirección representa su propia máquina. No puede consultar las fuentes enlazadas desde el PDF.
- **Causa seguida hasta el marco:** el navegador visita el origen loopback del renderizador; `md.renderer.render` conserva los href relativos y `page.setContent` conserva ese origen como base. `page.pdf` incorpora las URLs ya resueltas. No existe normalización posterior de esos enlaces y el bloque `finally` cierra el servidor. Además, el servidor solo sirve Mermaid, por lo que esos destinos tampoco serían fuentes válidas mientras está abierto.
- **Evidencia sin regeneración:** PyMuPDF `get_links()` sobre `docs/manual/guia-completa.pdf` encuentra **26 URI HTTP loopback**, todas en la página 54, desde `cobertura-documentacion.md` hasta `roadmap/2026-10-30-proveedores-email/tasks.md`. Las cuatro URI HTTPS explícitas de preparación conservan sus destinos. El PDF leído tiene 54 páginas y corresponde al hash canónico comunicado.
- **Resultado esperado:** las fuentes enlazadas del PDF deben resolver a un destino estable y accesible del repositorio/documentación, o a un mecanismo de enlaces locales compatible con su distribución. Regenerar el PDF después de corregir la resolución; comprobar las URI del artefacto final.
- **Estado:** pendiente.

## Comprobaciones restantes

No se encontraron otros defectos de corrección verificables en los procedimientos Docker/Laragon/TLS/Linux, procesos de fondo/Cronicle, conservación de datos, copia en frío o recuperación al contrastarlos con Compose, Dockerfile, scripts públicos y configuración de ejemplo. Las condiciones previas declaradas y las tareas de cualificación pendientes se respetaron al evaluar esos procedimientos. Se revisaron las actualizaciones de guías e índices y el exportador/verificador/CSS; el verificador declara expresamente la necesidad de QA visual. `node --check docs/manual/exportar.mjs` terminó con exit 0. No hay tests borrados, saltados o debilitados en el delta documental T07 revisado.

Fuentes de referencia de la revisión: `docs/manual/guia-completa.md`, `docs/manual/README.md`, `docs/manual/exportar.mjs`, `docs/manual/verificar.py`, `docs/manual/tema.css`, documentación vigente actualizada, `compose.yaml`, `compose.tls.yaml`, `docker/Dockerfile`, configuraciones Nginx, `infra/compose.yaml`, `infra/init.ps1`, `scripts/provision-local.ps1`, wrappers PHP/Composer y `.env.example` pública. No se leyeron archivos privados de entorno o claves.
