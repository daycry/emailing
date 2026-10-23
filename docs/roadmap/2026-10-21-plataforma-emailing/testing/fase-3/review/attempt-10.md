# Revisión de dos lentes — intento 10 · T-11

**Componente aprobado: 0 Critical / 0 Important / 0 Minor pendientes.**
Segunda revisión de tres del mismo incremento, con tablas previas completas.
Lentes frescas A+B+D; C no activada y D por la migración del diff acumulado,
sin cambios en esa migración durante el fix. Alcance exit 0: 89 archivos
acumulados, sin fuera de alcance, avisos, información oculta ni exclusiones.

| Lente | Dictamen | Evidencia |
|---|---|---|
| A | Criterios del componente conservados; B-01 corregido | [Tabla completa](attempt-10-lens-a.md) |
| B | B-01 corregido; sin defectos nuevos | [Tabla completa](attempt-10-lens-b.md) |
| D | Sin regresión identificada ni trabajo por destinatario añadido | [Tabla completa](attempt-10-lens-d.md) |

| # | Grado previo | Tarea | Disposición | Evidencia |
|---|---|---|---|---|
| B-01 | Important | T-11 | Corregido | `Campaigns.php:109–119`, vista `campaigns.blade.php:42–43` y `CampaignTest.php:381–451`: retirada explícita de IDs, recuperación del mismo borrador, sin escritura antes de guardar ni autorización con cambios pendientes. |

Root contrastó la acción con el formulario y las regresiones: revalida
cuenta, membresía, scopes, versión y estado; vacía únicamente la selección
local y retira la confirmación. El servicio de dominio, renderer, migración,
modelos y pruebas concurrentes conservan sus bytes aprobados. Se aprueban
solo las casillas de scopes/recursos, UI y fechas/concurrencia de T-11.

[Fix y pruebas](t11-b01-fix.md): Campaign final **28/301**, incluidos cuatro
escenarios MySQL concurrentes; Pint acotado y build exit 0. Los dos métodos
RED/GREEN/final mantienen las mismas expectativas; ocho recibos y 16 streams
del fix verificados. Feature **133/1404**, plantillas **20/185** y benchmarks
son anteriores al fix y no se presentan como nuevas ejecuciones. Los fallos
RED y build EPERM originales permanecen intactos. No se repitieron pruebas,
benchmark, DB, servicios ni navegador durante esta revisión.

[Patch incremental completo](attempt-10.patch), [manifest](attempt-10.patch.json).
SHA256 `6331c8711dc88d90935e5f17da780d14fd2f57d530ffbd31fd86e016556a62db`. Son 75 archivos incrementales, tres de
código/pruebas, respecto a las fuentes congeladas de revisión 9. Root
revalidó 178 fuentes, cuatro preimágenes, 41 evidencias y patch: **224 rutas
únicas sin discrepancias**. Las 75 entradas modificadas están incluidas en
las fuentes; sumarlas produce 299 comprobaciones, no 299 rutas distintas.
Las 41 evidencias son 40 archivos del handoff más la verificación RED/GREEN
de root; el manifest del implementer cubre los 38 artefactos previos a su
manifest y ZIP finales. Se preservan los 60 streams públicos y 30 recibos.

Nota de fusión: B escribió «cinco preimágenes» en su desglose, aunque dio
224 como total. El manifest contiene **cuatro** entradas before no nulas,
confirmadas por root y A/D. Es una errata del informe B, conservado sin
reescribir; no un cambio de fuentes ni un defecto del componente. Los
digests de paquete de B y D pertenecen a sus cálculos propios y no se
equiparan entre sí. Todos verificaron el mismo SHA del patch.

El patch normaliza LF y, en cinco artefactos, el salto final. Las fuentes,
streams y JSON originales mantienen bytes/hashes exactos; no se afirma que
aplicar el patch reproduzca byte a byte esos cinco archivos de evidencia.

Runtime genérico heredado por falta de Agent(reviewer); opus/high es el
tier de frontmatter, sin atribuir ejecución Claude. Ventana
2026-10-23T09:19:21Z–2026-10-23T09:48:46Z; reloj 29m.
Tokens/euros/horas IA reales NULL. Jira sin opt-in; ninguna publicación allí.

T-10/T-11 continúan en-progreso, **9/20**. CA-05 con worker real, E2E-08,
M-01 y QA integral fase 3 siguen pendientes de T-12/T-13/T-16. Pint global
anterior mantiene exit 1, pendiente T-20. ADR-003 sigue propuesta porque
la entrega durable no ha sido implementada ni validada; esta aprobación
del componente de campañas no acredita ese ADR. Envío externo deshabilitado.
Siguiente paso: checkpoint revisado en Git y T-12 outbox transaccional.


### Preparación del checkpoint T-11 — bytes de evidencia y formato pendiente

La comprobación exacta del índice detectó que Git normalizaba los CRLF de
`raw/t11-b01-method-verification.json`. Se añade a `.gitattributes` la regla
`docs/roadmap/**/testing/**/raw/*.json -text`, como las reglas existentes
de streams/patches, para conservar los bytes originales de evidencia.
El cambio se declara en Archivos de T-11; no cambia runtime ni resultados.

El control adicional `git diff --cached --check` detectó espacios al final
de 29 líneas de la vista de campañas, incluidas 24 líneas vacías y cinco
líneas con HTML/texto. El control queda rojo y se incorpora al formato
global pendiente T-20; no se altera la vista congelada ni se afirma una
verificación global de formato verde. Los snapshots, manifests y streams
originales permanecen intactos. No es un defecto funcional de revisión ni
un cierre de QA; debe corregirse y verificarse antes del cierre global.
