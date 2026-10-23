Lente D · revisión global9 · intento 1/3 · incremento de 18 archivos · fallback genérico, runtime heredado.

| Criterio de rendimiento T-11 | Veredicto | Evidencia |
|---|---|---|
| Consultas por destinatario | ✓ Sin N+1 introducido | `app/Services/CampaignService.php:131`: audiencia conjunta; `:137`: supresiones conjuntas; `:199`: inserciones de 500. Para 10.000 destinatarios son 20 inserciones, no 10.000. |
| Trabajo repetido del renderer | ✓ | `app/Services/CampaignService.php:174`: tres compilaciones por lote; cada expansión mantiene sus validaciones. |
| Volumen de audiencia y UI | ✓ | `app/Services/CampaignService.php:133`: límite de 10.001 candidatos para rechazar más de 10.000. `app/Livewire/Campaigns.php:179`: listados y audiencia paginados de diez en diez. |
| Bloqueos/E/S y complejidad | ✓ Sin defecto medible identificado | Ningún transporte ni espera añadidos al camino productivo; recorrido de candidatos y validación lineales y acotados. El mutex de cuenta forma parte del contrato recibido. |

**Gaps: sin hallazgos. Critical 0 / Important 0 / Minor 0.**

Ejecutado: benchmark CPU puro con clases congeladas, sin bootstrap/env/SQL/red: HTML de 96.025 bytes, 100/200/10.000 destinatarios → **0,1591008 / 0,1730443 / 1,3138421 s**, pico **10 MiB**. No reemplaza el recibo original ni mide SQL de review, SMTP o capacidad global.

Cobertura: **18/18 archivos del diff completo**, leído por partes; contexto congelado de T-11, arquitectura, pruebas, modelos e índices relacionados. Scope completo: sin exclusiones de usuario, avisos ni información oculta.

Integridad antes/después: **202 comprobaciones** —patch, 107 fuentes, cinco versiones anteriores y 89 evidencias—, **cero discrepancias**. Patch SHA256 `921f5f99495aa2f41a656111bd70b8f57e5856672219e2036be7e9eab9627ca2`. Verificados además **22 recibos + 44 streams** y ZIP final de 16 fuentes.

Procedencia: manifest final coincide con fuentes/ZIP; campaign-2 y campaign-3 conservan sus manifests distintos. Sus fallos y el benchmark anterior no se atribuyen al estado final. Fuera de mi lente: sin hallazgos reportados; SQL bajo carga, worker literal, E2E-08/M-01 y QA siguen pendientes. Sin escrituras.
