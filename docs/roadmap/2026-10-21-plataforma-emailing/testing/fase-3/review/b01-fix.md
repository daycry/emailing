# B-01 · paginación de supresiones corregida

Tras retirar la única fila de la última página, el componente vuelve a la
primera y muestra los registros restantes. `resetPage()` se ejecuta después
de que el servicio autorizado termina correctamente. Solo cambian
`app/Livewire/Suppressions.php` y `tests/Feature/SuppressionTest.php`.

| Verificación | Resultado real |
|---|---|
| Guard de base de pruebas | exit 0; entorno testing y DB exclusiva |
| Regresión antes del fix | exit 1; 1 failed, 6 assertions; falta la dirección restante en la página vacía |
| Misma regresión después | exit 0; 1 passed, 12 assertions |
| Suite SuppressionTest | exit 0; 16 passed, 165 assertions, 56,39 s |
| Pint de los dos PHP | exit 0; passed |

La regresión crea 16 supresiones, abre página 2 y retira su única fila.
Comprueba la página 1, una dirección restante visible, la retirada ausente,
ausencia del mensaje vacío, quince filas persistidas y una sola auditoría.
El hash del test es idéntico en RED/GREEN; el componente RED coincide con el
original revisado y los hashes finales coinciden con la suite.
[Recibos y SHA](../raw/b01-verification-receipts.json) enlazan los stdout/stderr
íntegros, sin redacciones. Root verificó los 28 archivos del freeze del fix.

No se repitieron Feature 107/1147, build ni frontera: corresponden al snapshot
anterior y no se relabelan como ejecución posterior a este cambio. No se
modifican servicios, permisos, transacciones, migración ni vistas/activos.
Pint global anterior sigue exit 1 por doce archivos de QA históricos ajenos.
Todos los procesos auxiliares terminaron y la DB exclusiva fue liberada.

Ventana fix1: 2026-10-23T02:29:21Z–02:39:47Z, diez minutos de reloj.
Tokens, euros y horas IA reales desconocidos/null. Runtime genérico heredado,
tier de implementer resuelto sonnet/medium, sin atribuir ejecución Claude.
La prueba de regresión no representa activar TDD global ni cobertura porcentual.

B-01 queda corregido en implementación, pendiente de revisión fresca 2/3.
Se conserva toda la tabla de [revisión 7](attempt-7.md), sin reiniciar contador
ni reabrir aprobaciones sin evidencia nueva. T-10, E2E-08 y CA-10/14 integrales
continúan pendientes de T-12/T-13/T-16. Esto no acredita QA de fase 3.
