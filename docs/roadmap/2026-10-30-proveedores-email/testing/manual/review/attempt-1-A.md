# Lente A — revisión documental T-07, intento 1 de 3

Fecha: 2026-10-31. Revisor genérico de contexto fresco; `reviewer` nativo no disponible. Se aplicaron el contrato y el prompt A de `custom-agents:adversarial-review`. Solo requisitos de T-07; sin correcciones de fuentes, pruebas de producto, instalaciones, red ni publicación.

**Veredicto: con gaps. 0 Critical, 3 Important, 0 Minor.** La revisión de código T01–T06 y su QA3 no se reabren. La QA visual independiente del PDF continúa pendiente y este informe no la sustituye.

## Identidad y alcance

- Fuente revisada: `docs/manual/guia-completa.md`, SHA-256 `dacb156aa8b69e8ce23cce3562bcacac7192cfbee5ed3d9b4284178ab1e06120`.
- PDF revisado: `docs/manual/guia-completa.pdf`, SHA-256 `578f1e3cd86cc22a07a26304e14487097b63665a86bcfe4ad0fe5be7f74e71cd`; 54 páginas.
- Criterios: `docs/roadmap/2026-10-30-proveedores-email/spec.md:163`–166 y `tasks.md:437`–459; plan T07 en `improvement-plan.md:285`.
- Puerta previa aportada: `storage/providers-email-20261030/manual/scope-review1.json`, base `74970e0`, 339 rutas, sin fuera de alcance, criterios alterados ni avisos. Se contrastó el delta documental con los archivos declarados en T07; los archivos de producto pertenecen al alcance anterior aceptado, no son nuevos cambios T07.
- Lectura fragmentada completa de las 2100 líneas del manual, la matriz de 262 líneas y los archivos nuevos README/exportador/verificador/tema/verificación. Se recorrieron los diffs de README, infra README, índices y guías vigentes indicadas por el encargo. Las fuentes de contraste se limitaron a rutas propias, navegación/formularios, configuración pública, Compose, Dockerfile y scripts públicos relevantes.
- No se abrió ni calculó hash de ningún entorno privado. Las identidades del manual/PDF se recalcularon al terminar la lectura y coinciden con el encargo.

## T-07 → criterios de aceptación

| Criterio | Resultado | Evidencia y límite |
|---|---|---|
| CA-19 — Docker desde cero, requisitos y obtención del código | ✓ | Manual:124–367: Git/Docker/WSL, comprobaciones Client/Server/Compose, clonación, secretos, infraestructura, aprovisionamiento, build, migración explícita, primer registro y tres procesos. Contraste: `infra/init.ps1:13`, `scripts/provision-local.ps1:19`, `compose.yaml:8`, `docker/Dockerfile:1`. El script de provisión no exige PHP/Composer en Windows; la receta lo distingue correctamente. |
| CA-19 — Laragon desde cero y requisitos efectivos | ✗ | Manual:373–389 presupone Composer/Node/PHP/Laragon preparados, sin pasos para obtenerlos/seleccionarlos ni comprobar sus ejecutables/extensiones antes de los primeros comandos. Gap A1-01. |
| CA-19 — privacidad, persistencia, primer acceso, procesos y separación QA | ✓ | Manual:275–290, 325–367, 392–435, 1728–1733, 1814–1840 y 2011–2014. Cuenta operativa `emailing` diferenciada de `emailing_testing`; volúmenes/socket separados, APP_KEY persistente, workers distintos y Cronicle no sustituido por Laravel scheduler. |
| CA-20 — inventario, documentos dueños, 37 MCP y 71 variables públicas | ✓ parcial | Matriz:24–262; manual:631–640, 1491–1563 y 1724–1810. `coverage-exact.json` verifica identidad de nombres; el catálogo de rutas y navegación coincide con las familias descritas. La existencia del nombre no acredita por sí sola explicación de toda opción. |
| CA-20 — explicación autosuficiente de todas las opciones inventariadas | ✗ | Opciones concretas conservan solo enlace o una mención genérica; faltan memoria PHP, logging Docker, campos/limpieza SMTP genérica y la casilla Recordarme. Gap A1-02. |
| CA-20 — ejemplos, glosario, diagnóstico y límites reales | ✓ | CSV completo manual:785–791; plantilla:873–878; petición REST:1362–1374; Cronicle:1619–1636; copia:1882–1902; glosario:2030–2072. Manual:53–82 y 2094–2100 conserva principal18/20, MCP11/14 y cualificación externa/humana pendiente. No presenta instalación ni restore global como ejecutados. |
| CA-21 — fuente común, Mermaid renderizado, texto y paginación | ✓ parcial | Manual y PDF con hashes coincidentes; `pass2/validation.json`: PASS, 879/879 bloques, 8 diagramas, sin bloques/etiquetas omitidos ni texto fuera o cuerpo pequeño; los ocho SVG son XML válido con viewBox. Mapas TOC de ambas pasadas iguales. PyMuPDF resolvió los 24 destinos nombrados del índice dentro de las 54 páginas. Esto no acredita ausencia de solapes visuales. |
| CA-21 — enlaces utilizables en el PDF entregable | ✗ | 26 enlaces a fuentes/matriz apuntan al loopback temporal del generador en página54. Gap A1-03. |
| CA-21 — revisión visual de todas las páginas | Pendiente previsto | `docs/manual/verificacion.json:16` y `verificar.py:96` declaran revisión independiente pendiente. T07 sigue en-progreso en `tasks.md:440`; no se convierte esta puerta posterior en defecto por falta de cierre. |
| CA-22 — guías e índices coherentes y sin huecos/enlaces rotos | ✗ parcial | Las guías actualizan estados obsoletos y separan aceptación/feedback, QA/operación y proveedor local/real. `documenter-validation.json` acredita 33 páginas/1003 enlaces Markdown de su instantánea anterior. Persisten el hueco de cobertura A1-02 y las referencias PDF A1-03; el recibo anterior tiene otra fuente y no sustituye la validación final tras los últimos cambios. |
| CA-22 — sin secretos/datos reales y cierre/publicación posterior | ✓ / pendiente previsto | Los ejemplos leídos usan dominios reservados y marcadores privados. No se observaron credenciales ni rutas personales reales en el delta T07. Revisión documental, QA visual, actualización de evidencia/changelog y commits/push siguen como pasos posteriores expresos; aún no se afirma su cierre. |

## Gaps

### A1-01 — Important — La ruta Laragon no comienza desde un equipo nuevo

**Estado: pendiente. Tarea T-07. Criterio CA-19.**

Ubicación: `docs/manual/guia-completa.md:373`–389, especialmente «Necesitas PowerShell, Composer, Node/npm y Laragon» y «con sus extensiones» antes de ejecutar `scripts/composer.ps1 install`, `npm ci` y `npm run build`.

Escenario: una persona parte de Windows nuevo, sigue la preparación Docker/Git del paso0 y elige Laragon como permite el manual:15–18. El primer wrapper nativo ejecuta `composer` del PATH (`scripts/composer.ps1:7`) y el wrapper PHP ejecuta `php` del PATH (`scripts/php.ps1:9`). No instalan esas herramientas ni seleccionan el PHP de Laragon. El lector no dispone de los comandos ni de una instrucción para conseguirlos, habilitar/seleccionar PHP compatible, preparar las extensiones o comprobar que Composer y el servidor usan el PHP esperado. Por tanto, la receta queda bloqueada antes de instalar dependencias.

No se exige que el revisor instale el producto ni que T20 esté aceptada. Se exige que el procedimiento prometido incluya esos prerrequisitos efectivos con acciones y resultados observables. Completar un paso previo Laragon con obtención de herramientas, selección de PHP compatible con el lock, extensiones/PATH y comprobaciones de versiones/extensiones/plataforma antes del bloque actual. Mantener la separación con QA.

### A1-02 — Important — La matriz enlaza opciones sin explicación autosuficiente en el manual

**Estado: pendiente. Tarea T-07. Criterios CA-20/CA-22.**

Ubicación principal: `docs/manual/cobertura-documentacion.md:252`, `:256` y afirmación final `:260`; `docs/manual/guia-completa.md:954`–955 y `:1774`–1787.

Contrastes concretos:

| Opción vigente | Fuente real | Qué falta en el manual |
|---|---|---|
| Memoria PHP 256M | `docker/emailing-uploads.ini:6`; matriz:252 y guía dueña configuración | Manual:1774–1777 menciona upload6M/POST8M y memoria genérica, pero no el límite de memoria256M ni su función. La fila promete que el manual lo explica. |
| Logging de infraestructura `json-file`, 10m × 3 | `infra/compose.yaml:3`–7, aplicado por servicio; matriz:256 | No aparece la política ni sus valores en ninguna de las2100líneas del manual. El lector del PDF no conoce esta retención aunque figure inventariada y explicada en la guía dueña. |
| Preparación SMTP genérica: host, puerto, seguridad, usuario/contraseña y borrar credenciales | `resources/views/livewire/mail-settings.blade.php:11`; validación/persistencia `app/Services/MailSettingsService.php:57`–67; matriz:49 | Manual:954–955 solo declara que existe por compatibilidad y que no habilita relay. No explica sus campos, puerto por defecto/rango, conservación por vacío ni la casilla específica para borrar estas credenciales. La limpieza de perfiles Brevo/SendGrid descrita en1071–1088 es una operación distinta. |
| Recordarme al iniciar sesión | `resources/views/auth/login.blade.php:1`; matriz:33 agrupa login | Manual:588–609 explica login/contraseña/cuenta/Salir pero no esta opción ni su efecto en un equipo compartido. No aparece «Recordarme» en el manual. |

Escenario: una persona usa exclusivamente el PDF/manual, como promete su introducción:21–23, para decidir qué conserva la instalación o cómo limpiar credenciales genéricas. La tabla de cobertura dice que no quedan funciones sin explicar, pero el lector tiene que abrir la guía técnica o la vista del producto para conocer las opciones anteriores. El chequeo exacto de37 nombres y71variables no detecta estas omisiones porque no valida el significado ni cubre todas las casillas/valores fijos.

Completar las explicaciones en la fuente común y ajustar la matriz a esa evidencia. No se solicitan opciones nuevas, edición del runtime ni un inventario de todos los ajustes genéricos de dependencias; son opciones ya expuestas o expresamente inventariadas por T07.

### A1-03 — Important — Veintiséis enlaces PDF apuntan al servidor temporal de exportación

**Estado: pendiente. Tarea T-07. Criterios CA-21/CA-22.**

Ubicación: enlaces fuente de `docs/manual/guia-completa.md:2076`–2096; tratamiento en `docs/manual/exportar.mjs:80`, `:128` y `:140`–145; artefacto `docs/manual/guia-completa.pdf`, página54.

Evidencia de lectura independiente mediante PyMuPDF, sin regeneración ni red: el PDF tiene54 anotaciones de enlace, 24 destinos nombrados internos válidos,4 enlaces HTTPS de prerrequisitos y26 URI cuyo origen es `http://127.0.0.1:59263/`. Ejemplos exactos:

- `http://127.0.0.1:59263/cobertura-documentacion.md`
- `http://127.0.0.1:59263/aplicacion/arquitectura.md`
- `http://127.0.0.1:59263/routes/web.php`
- `http://127.0.0.1:59263/compose.yaml`
- `http://127.0.0.1:59263/api/mcp-tools.json`

Escenario: el lector abre la página54 del PDF entregado y pulsa «matriz de cobertura», una guía o una fuente. El navegador intenta conectar al loopback del ordenador del lector, con un puerto efímero que únicamente existió durante la exportación. El servidor del exportador se cierra en `exportar.mjs:154`; además, sus rutas admitidas solo servían Mermaid. Esas referencias no pueden funcionar como documentación enlazada de la entrega.

El exportador conserva los href relativos y Chromium los resuelve contra `origin` del renderer. `verificar.py` comprueba contenido/texto/dimensiones pero no el destino de las anotaciones PDF, de modo que su PASS no detecta este fallo. Convertir referencias de repositorio a destinos estables apropiados para el PDF, conservar los anchors internos y añadir comprobación de URI temporales/rotas antes de entregar. Regenerar/verificar el PDF con la fuente final. No hay defecto en los24destinos internos del índice observados.

## Comprobaciones transversales del prompt A

| Comprobación | Resultado |
|---|---|
| Tarea marcada hecha realmente implementada | T07 está en-progreso; no se le atribuye cierre ni se denuncia falsamente verificación cerrada sin evidencia. Sus fallos de requisitos actuales figuran arriba. |
| Alcance y criterios preservados | ✓ según puerta aportada y contraste del delta; T07 está autorizado explícitamente. No se alteran los18CA anteriores. |
| Pruebas declaradas y evidencia | Evidencia documental/paridad existente examinada; QA visual independiente posterior pendiente correctamente declarada. Sin suites SQL/E2E, builds ni instalación ejecutados por este revisor. |
| Constitución explícita | No existe `docs/CONSTITUTION.md` en el destino consultado; no se inventan principios. |
| Generado y documentación actualizados | PDF coincide con fuente y recibos de la instantánea revisada; falla su política de enlaces A1-03. `commands/`, `agents/`, `hooks/` no forman parte del deltaT07: exportación interop no aplica. |
| OpenAPI condicional | T07 no cambia esquemas OpenAPI: el linter/diff de contratos no se reejecuta ni se reabre la revisión de T01–T06. |
| Puertas relajadas | No se identificaron supresiones de test/linter ni umbrales rebajados en T07. El nuevo verificador del documento no sustituye la QA visual y así lo declara. |
| Prosa / docs-style | Se leyó el fragmento compartido. No se reportan gustos ni refactors. Los huecos señalados son requisitos funcionales/documentales citables; no se utiliza estilo para bloquear el cierre. |

Siguiente paso: verificar y corregir los3gaps, regenerar fuente/PDF/evidencia cuando cambien, revisión documental intento2 sobre esas correcciones y QA visual independiente de todas las páginas. Mantener los pendientes reales y la publicación posterior en el orden autorizado.
