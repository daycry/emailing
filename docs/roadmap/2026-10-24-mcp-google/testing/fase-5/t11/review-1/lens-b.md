# Lente B — intento 1, T11

Veredicto: **1 defecto Important pendiente**. Critical: 0; Important: 1; Minor: 0.

Revisión por subagente genérico de respaldo, con contexto fresco. Perfil de dominio aplicado: devops del kit compartido; no existe perfil devops de proyecto en los ámbitos consultados. No se corrigen fuentes ni se modifica el ledger.

## B-01 — La receta de restore retira mantenimiento aunque falle la invalidación

- **Grado:** Important.
- **Ubicación:** `docs/aplicacion/mcp-operacion.md:203` (bloque PowerShell de las líneas 199–204).
- **Escenario:** el operador restaura un snapshot anterior a una revocación y ejecuta el bloque indicado. `mcp:invalidate-restored-access --apply` falla, por ejemplo por un error SQL tardío; la transacción revierte y el comando retorna 1. PowerShell continúa con la siguiente orden nativa y ejecuta `php artisan up`. Queda retirado mantenimiento a pesar de que los grants/tokens del snapshot siguen sin invalidar; al volver a servir HTTP con MCP habilitado vuelven a admitir esas credenciales restauradas. Incluso en el camino correcto, el bloque hace `up` antes de la comprobación 401 que el párrafo posterior exige antes de abrir.
- **Llamantes/framework seguidos:** `app/Console/Commands/McpInvalidateRestoredAccess.php:54`–57 devuelve FAILURE ante la excepción; el test `test_failure_late_in_the_transaction_rolls_back_every_revocation_and_session_delete` conserva exactamente ese camino de rollback. `vendor/laravel/framework/src/Illuminate/Foundation/Console/UpCommand.php` desactiva mantenimiento sin consultar el resultado de la invalidación. No hay una guarda superior que impida el `up` de la receta.
- **Comprobación aislada:** en la misma shell, sin Laravel/SQL/red/servicios, un proceso `python -B -c "raise SystemExit(1)"` seguido de dos salidas PowerShell produjo `native_failure_exit=1` y `subsequent_reopen_step_reached`. Confirma que el código no cero no interrumpe el bloque publicado.
- **Resultado exigible:** la receta debe detenerse y conservar mantenimiento ante cualquier fallo, y la reapertura debe quedar después de verificar la invalidación y el rechazo de las credenciales antiguas. No requiere cambiar el rollback del comando.
- **Estado:** pendiente.

## Cobertura y comprobaciones

Se recorrieron los 56 archivos del diff congelado: comando de recuperación, runner público y todos sus componentes, configuración TLS/Compose, documentación y ejemplos, tests PHP y Python, cambios del ledger y las proyecciones públicas de evidencia. El manifiesto del artefacto se comprobó completo como datos: 448 entradas, 448 rutas únicas y hashes SHA-256 bien formados. Se contrastaron T11 y sus criterios con `tasks.md`, `improvement-plan.md` y `testing/fase-5/t11/*` público.

Se siguieron también el contrato PHP fijado, la configuración de claves anteriores, el Compose base y el arranque/mantenimiento del framework. El refresh previo al digest de Eloquent conserva los atributos persistidos y las comprobaciones de identidad/clave/revocación; no se considera debilitamiento de aserciones. No se encontraron otros defectos de corrección, ni tests eliminados o aserciones debilitadas en el diff.

SHA-256 del patch verificado: `faa13b6098248b1191b6611bd2fec9e3d8d26048886cbb3e73d20a20986115a2`. Todas las fuentes del snapshot permanecían iguales al concluir la revisión. Los resultados backend8/61 y Python135 se toman como evidencia publicada, sin sustituir la lectura de código. No se ejecutaron suites, DB, red, Docker, broker ni navegador. No se leyeron entornos, solicitudes privadas ni los artefactos privados expresamente excluidos.

La revisión B no acepta T11 ni acredita Google o clientes reales; QA independiente y esas puertas conservan su alcance.
