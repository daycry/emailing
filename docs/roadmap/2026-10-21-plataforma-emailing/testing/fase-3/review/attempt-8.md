# Revisión de dos lentes — intento 8 · T-10 / B-01

**Aprobado el componente: 0 Critical, 0 Important y 0 Minor pendientes.**
Segundo intento de tres del mismo incremento, sin reiniciar contador.
B-01 queda corregido y no se reabre ningún criterio sin nueva evidencia.
T-10 integral sigue en progreso; no se concede QA de fase 3.

| Control | Evidencia |
|---|---|
| Base publicada | `c585b7856db5814e47ac326b7f8b197dee636e78` |
| Incremento revisado | Contra fuentes congeladas de revisión 7; 33 archivos, solo dos PHP funcionales modificados |
| SHA256 del patch | `7762bfa5d4f9d67a1114e94474fa21e12ef85143a9ce85ba9f2d5fb2cc643c67` |
| Inventario | 124 fuentes, 30 evidencias y 3 preimágenes; cero discrepancias antes/después |
| Alcance | exit 0, 51 archivos acumulados; cero fuera de alcance, avisos y exclusiones de usuario |
| Lentes | A+B+D; C false; D activada por migración en el diff acumulado |
| Roles | Revisores genéricos frescos, runtime heredado; tier resuelto opus/high, sin atribuir ejecución Claude |
| Ventana | 2026-10-23T02:42:41Z–02:49:49Z; siete minutos de reloj; tokens/euros/horas IA reales null |

| # | Grado | Gap | Dictamen | Evidencia |
|---|---|---|---|---|
| B-01 | Minor anterior | Página vacía al retirar única fila de página 2 | Corregido y aprobado por A+B+D | `app/Livewire/Suppressions.php:37`, `tests/Feature/SuppressionTest.php:210`, [fix y pruebas](b01-fix.md) |
| Nuevos | — | Ninguno | 0 Critical / 0 Important / 0 Minor | [A](attempt-8-lens-a.md), [B](attempt-8-lens-b.md), [D](attempt-8-lens-d.md) |

[Patch](attempt-8.patch) y [manifiesto](attempt-8.patch.json). El patch sirve
para revisión: normaliza LF, incluidos saltos finales de cuatro stdout JSON;
los bytes originales de fuentes/salidas conservan sus hashes y no se alteran.
Se preserva íntegra la [tabla anterior](attempt-7.md) y las aprobaciones y
pendientes de sus tres lentes. El freeze del implementer tiene 28 entradas;
las 30 evidencias del snapshot incluyen dos manifiestos auxiliares adicionales.

Root verificó de nuevo fuentes y evidencias antes de añadir esta traza.
RED 1/6 exit 1, mismo test GREEN 1/12 exit 0; suite 16/165 y Pint de dos PHP
exit 0. Feature 107/1147, build y frontera son ejecuciones del snapshot anterior;
no se reejecutaron ni se relabelan. Pint global mantiene su exit 1 histórico.

El componente permite avanzar técnicamente a campañas. El criterio literal
encolar→baja→worker y E2E-08 permanecen pendientes hasta T-12/T-13/T-16.
No se elimina ese requisito ni se cambia la dependencia declarada de T-11;
T-10 se mantiene abierta para su validación integral. ADR-003 sigue propuesta.
No se acepta manualmente la aplicación ni se acredita feedback de proveedor,
capacidad medida o QA de fase 3. Total: 9/20 sin cambios.

Jira no configurado; sin publicaciones fuera del repositorio. Siguiente paso:
checkpoint de código e informes, commit/push normal y composición de campañas.
