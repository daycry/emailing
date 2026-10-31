# T07 — Lente B — revisión documental, intento 2 de 3

Fecha: 2026-10-31. Revisor genérico heredado de contexto fresco; el agente nativo reviewer/Opus no estaba disponible. Aplicado el prompt B de `custom-agents:adversarial-review`: solo defectos de corrección, con seguimiento del llamante o marco antes de reportarlos. No se corrige implementación.

**Veredicto: sin defectos pendientes ni nuevos. Critical: 0; Important: 0; Minor: 0.**

## Alcance y continuidad

Se leyeron los informes completos DOC1 A/B/C/D. Se reevalúan únicamente las correcciones de sus tres gaps y sus efectos: prerrequisitos nativos, controles y valores omitidos, normalización de enlaces PDF y verificación asociada. No se reabren T01–T06 ni los aspectos previamente aprobados o los pendientes de cualificación externa/humana. La revisión visual independiente sigue pendiente y esta lente no la sustituye.

Identidad comprobada de forma independiente, mediante lectura de los dos archivos canónicos:

- `docs/manual/guia-completa.md`: SHA-256 `0b8d519494af8efecca184a56ed987d671b2a97a4597467f07fa551aad8494fe`.
- `docs/manual/guia-completa.pdf`: SHA-256 `330446b36205f3e63a21f021e44de8b174a7fd5636d7185ffe60ee1b57d39cd7`; 57 páginas.

## Reevaluación de correcciones

| Gap anterior o efecto revisado | Resultado | Evidencia |
|---|---|---|
| B1-01, duplicado de A1-03: 26 fuentes PDF dependían del renderer temporal | Corregido | `docs/manual/exportar.mjs:54–78` recorre también los tokens hijos, resuelve referencias desde el directorio real del manual, comprueba el destino público y sustituye su href por HTTPS antes del render de `:155` y `page.pdf` en `:172`. La limpieza del servidor en `:179–181` ya no invalida estas fuentes. |
| Artefacto final y conservación de navegación | ✓ | Lectura independiente PyMuPDF `get_links()`: 59 anotaciones, 35 URI externas, las 26 referencias del recibo presentes y cero URI HTTP loopback/localhost o `file:`. Conserva 24 enlaces con destino nombrado. Entre las fuentes comprobadas están matriz documental, Compose de aplicación y Compose de infraestructura. |
| Detección del fallo corregido | ✓ | `docs/manual/verificar.py:74–79` compara las URI extraídas con el origen temporal del recibo y con las 26 referencias estables esperadas; cualquiera ausente o temporal añade un problema y produce salida fallida mediante `:99–110`. No se eliminó una comprobación anterior para obtener PASS. |
| A1-01: incorporación de herramientas nativas y requisitos efectivos | ✓, sin regresión de corrección observada | `docs/manual/guia-completa.md:380–454` incorpora selección de PHP, terminal/PATH heredado, Composer 2, Node 22.12+, `php --ini`, módulos y `check-platform-reqs --lock`. Contraste con `scripts/composer.ps1:1–8` y `scripts/php.ps1:1–11`: ambos utilizan los ejecutables del PATH y cargan el ini adicional, incluido sockets. Lectura estructurada del lock confirma Symfony `>=8.4.1`, sockets requerido y ausencia de requisito obligatorio pcntl para Windows. El paso no afirma una instalación realmente ejecutada. |
| A1-02: controles de sesión y SMTP genérico | ✓, sin contradicción nueva | Manual `:678–681` explica Recordarme sin prometer permisos permanentes. Manual `:1043–1071` explica puerto 587/rango, seguridad, vacíos que conservan credenciales, eliminación prioritaria y su separación de Brevo/SendGrid. Se siguió el formulario `resources/views/livewire/mail-settings.blade.php:8–12` hasta `app/Services/MailSettingsService.php:50–81`: validación, asignación y precedencia de limpieza concuerdan con la receta. Las guías dueñas de cuentas y plantillas/transporte mantienen la misma explicación. |
| A1-02: memoria y retención | ✓, sin contradicción nueva | Manual `:1907–1924` incorpora `memory_limit=256M` por proceso y `json-file`/10m/3 por contenedor de infraestructura; distingue los archivos persistidos de aplicación y Cronicle. No atribuye esa política a todos los contenedores ni a todo el almacenamiento. |

## Evidencia y límites

Se leyeron completos el exportador y verificador corregidos y su README, los fragmentos modificados del manual, las explicaciones dueñas relacionadas y sus llamantes/configuración pública. Se contrastaron `verificacion.json`, `fix1-final2/export-receipt.json` y `fix1-final2/validation.json`: coinciden las identidades canónicas; el recibo de validación conserva 919/919 bloques, 8 diagramas, 26 enlaces estables y cero problemas declarados. Estos recibos son evidencia aportada, no ejecuciones nuevas de esta lente.

Comprobaciones propias sin generar artefactos: `node --check docs/manual/exportar.mjs` finalizó con exit 0; análisis AST de `verificar.py` correcto; extracción de URI y hashes del PDF/Markdown canónicos mediante Python/PyMuPDF en lectura. No se ejecutaron generadores, renders, instalaciones, SQL, Docker, proveedores, E2E ni builds. No se modificaron fuentes, ledger ni conocimiento. El acceso real a GitHub y los commits/publicación posteriores no se presentan como ya ejecutados.

Siguiente paso: integrar este veredicto con las demás lentes y completar la QA visual independiente prevista antes del cierre documental.
