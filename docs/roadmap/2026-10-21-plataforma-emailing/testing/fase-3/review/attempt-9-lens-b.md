Lente B · revisión global 9 · intento 1/3 · base `5a1a52f` · 18 archivos. Subagente genérico; solo lectura.

| Criterio T-11 | Veredicto | Evidencia |
|---|---|---|
| Borradores/futuras sin elegibilidad prematura | ✓ componente; worker no verificable | `CampaignService.php:284–286`; `CampaignTest.php:34–55`. Worker literal pendiente. |
| Recursos y scopes efectivos por cuenta | ✓ | `CampaignService.php:40–85,228–236`; pruebas de aislamiento, membresías y revocación concurrente. |
| Composición/resumen y recuperación de errores | ✗ | B-01; las selecciones desaparecidas bloquean el borrador. |
| Fechas UTC, versiones, pausa/reanudación y concurrencia | ✓ | `CampaignService.php:209–269`; `ConcurrentCampaignTest.php`, cuatro escenarios MySQL. |
| Renderer reutilizable conserva validaciones | ✓ | `TemplateRenderer.php:22–98`; `CompiledTemplateTest.php`, cinco pruebas. |
| E2E-08 / M-01 / QA fase 3 | No verificable | Pendientes expresamente en ledger congelado; Feature/build no los sustituyen. |

| # | Grado | Gap | Tarea | fichero:línea | Escenario concreto | Estado |
|---|---|---|---|---|---|---|
| B-01 | Important | Una selección eliminada deja el borrador sin recuperación desde la UI | T-11 | `resources/views/livewire/campaigns.blade.php:43` y `:47`; `app/Livewire/Campaigns.php:89–90,185–186`; `app/Services/CampaignService.php:84–85` | Guardar un borrador con lista L o contacto individual C; eliminar L/C desde Contactos; reabrir el borrador. `open()` conserva sus IDs, pero los selectores solo muestran recursos existentes/activos. No existe control para retirar la selección desaparecida. Añadir o desmarcar otros recursos conserva ese ID; guardar devuelve 404 y revisar también lo rechaza. El borrador no puede continuar desde la interfaz. | Pendiente |

**Conteos:** Critical **0** · Important **1** · Minor **0**.

**Evidencia:** revisión estática completa de los **18/18 archivos modificados**, sus cambios anteriores y contexto congelado de contactos, permisos, plantillas, documentación y ADR. Estados UI vacío/carga/error/éxito contrastados con `CampaignTest.php:329–368`; sin navegador ni nuevas pruebas con efectos.

**Integridad:** 107 fuentes y 89 evidencias verificadas; cero discrepancias. Los 22 recibos y sus 44 streams coinciden con el índice; ZIP final **16/16** fuentes coincidentes. Campaign final **25/247**, Template **20/185**, Feature **133/1404**, exit 0; conservados los fallos anteriores y sus manifests diferenciados.

SHA del patch antes/después: `921f5f99495aa2f41a656111bd70b8f57e5856672219e2036be7e9eab9627ca2`. Paquete completo **215 archivos**, digest antes/después idéntico: `c5a32471c26c7cb4f95c7febe0ed81fa338e327aafc7168bdb451bac8dce173d`.

Fuera de mi lente: rendimiento corresponde a D; T10 histórica no reabierta. Sin modificaciones a archivos, ledger, Git, DB ni servicios.
