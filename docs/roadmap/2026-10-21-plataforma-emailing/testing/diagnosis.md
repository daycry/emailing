# Diagnóstico tras el tercer intento de QA

Fecha: 2026-10-22. Estado: fases 1–3 de `custom-agents:debug-root-cause`;
corrección propuesta, **sin aplicar**. T-04 sigue en progreso.

**Actualización posterior:** tras la reactivación explícita del objetivo por
el usuario, se aplicó el diff propuesto y se abrió su revisión y un segundo
ciclo acotado de QA. El texto siguiente conserva el diagnóstico y el estado
del primer ciclo; sus resultados originales viven en `archive/cycle-1/`.
El timeout todavía no se da por resuelto. Ver el ledger para el estado actual.

El tercer intento termina con **4 passed, 4 failed, 0 flaky, 0 skipped y
0 interrupted**. E2E-02 y E2E-03 pasan en ambos perfiles. La evidencia de
registro deshabilitado corresponde al perfil nativo; Docker E2E-01 falla
antes, al navegar a `/dashboard`. El JSON del runner y sus gates conservan
los fallos originales. Estas reproducciones mínimas no constituyen un cuarto
intento de la suite ni convierten sus resultados en verdes.

## Registro deshabilitado: causa probada

**Reproducción mínima:** obtener `/login` en el perfil temporal 8084, mantener
sus cookies, extraer el CSRF real y enviar un alta sintética UUID a
`POST /register`. Consultar antes y después sus usuarios, cuentas y membresías
en `emailing_testing`, con `TestingDatabaseGuard` obligatorio.

Resultado real: login 200, CSRF presente, POST 404; los tres conteos son cero
antes y después. Se observó el mismo resultado en dos ejecuciones con UUID
diferentes. La [salida conservada](fase-1/raw/diagnosis-registration-off.json)
es stdout real de la segunda ejecución, no una reconstrucción del reporte.

**Aislamiento:** `config/fortify.php:9` excluye `Features::registration()` cuando
la configuración está deshabilitada. Fortify sólo publica GET y POST de
registro dentro de esa condición (`vendor/laravel/fortify/routes/routes.php:74`).
El perfil temporal comprueba la configuración y la base exclusiva al arrancar.

**Hipótesis probada:** el test espera 403 para una ruta que no está publicada;
el 404 representa correctamente el bloqueo. Se descartan CSRF ausente y alta
creada en esta reproducción. El assert de ausencia de usuario de E2E-01 quedó
sin ejecutar tras su primer fallo; la prueba independiente anterior acredita
esa ausencia, sin atribuírsela retrospectivamente a la suite.

**Propuesta:** cambiar únicamente la expectativa de `denied.status()` a 404 y
conservar la comprobación SQL de ausencia de alta y todos los demás asserts.

## Transferencia: causa probada en la visibilidad del formulario

**Reproducción mínima:** fixture UUID propio, login del propietario, abrir el
formulario, elegir otro miembro y confirmar una contraseña incorrecta. No se
intenta una transferencia válida en el diagnóstico.

La [salida real](fase-1/raw/diagnosis-transfer-details.json) observa:

| Momento | Details abierto | Campo visible | Propietario |
|---|---|---|---|
| Tras el rechazo de contraseña | false | false | Conservado |
| Tras volver a pulsar el summary | true | true | Sin transferencia intentada |

**Aislamiento:** ambos errores E2E-04 están en el segundo `fill(f.password)`.
El rechazo de contraseña y el propietario intacto ya se habían comprobado.
La vista usa un `<details>` que vuelve cerrado después del render de error.
Se descarta un cambio de propietario o un rechazo de la contraseña correcta:
ese segundo envío nunca llegó a ejecutarse.

**Hipótesis probada:** el test intenta rellenar un campo oculto porque presupone
que el desplegable sigue abierto. Una acción normal del usuario lo vuelve
visible; no se fuerza el relleno ni se altera el DOM mediante JavaScript.

**Propuesta:** reabrir el summary si el campo está oculto y esperar su
visibilidad antes del segundo relleno. Se conservan reautenticación, nuevo
propietario administrador, antiguo propietario administrador y auditoría.
Esos últimos pasos siguen pendientes de acreditación E2E.

## Timeout inicial de Docker: diagnóstico parcial

Docker E2E-01 agota 30 s en su primer `page.goto('/dashboard')`, esperando
`load`. No acredita registro, reset ni el POST deshabilitado en ese caso.

Se comprobaron dos hipótesis acotadas:

1. **Caída persistente del endpoint:** una petición HTTP anónima responde 302
   hacia `/login` en 56 ms en Docker y 641 ms en nativo. No se reproduce una
   caída persistente durante el diagnóstico.
2. **Documento disponible con recursos que impiden finalizar `load`:** una
   navegación nueva completa DOM/load en 230/306 ms en Docker y 694/758 ms en
   nativo, con cero peticiones fallidas o pendientes. Esa condición tampoco
   se reproduce en esta observación.

La [salida real con tiempos](fase-1/raw/diagnosis-navigation.json) no demuestra
la causa del timeout anterior. No se elimina el fallo, no se declara resuelto
y no se propone aumentar el timeout sin evidencia adicional. Un siguiente
ciclo debe conservar mediciones y examinar cualquier repetición del fallo.

## Propuesta revisable y límite del workflow

El [diff propuesto](diagnostics/proposed-harness-fix.patch) afecta sólo a
E2E-01 y E2E-04 y pasa `git apply --check`; aún no se aplica. No cambia el
presupuesto de tiempo ni los resultados del runner.

Se publican los [scripts de reproducción](diagnostics/) con resolución de
rutas adaptada al árbol del repositorio; sus cuerpos de observación son los
ejecutados originalmente desde archivos locales ignorados. Usan fixtures
sintéticos y guardas de la base de pruebas. Los scripts de navegador usan el
Chromium y Playwright locales ya instalados, sin descargas. El perfil 8084 es
temporal y debe arrancarse expresamente para repetir el diagnóstico de alta.

La regla de `custom-agents` 1.22.0, `skills/debug-root-cause/SKILL.md`, en el
gancho del tercer rojo establece que la fase 4 sólo se ejecuta después de que
el usuario decida seguir con el fix. Se solicita esa decisión con este diff y
el timeout aún abierto. Si se autoriza, se abre una sola vez un nuevo ciclo
acotado; no hay cuarto intento automático del ciclo actual.

La aceptación humana M-01 y E2E-05–11/M-02 siguen pendientes. La aplicación
continúa con Mailpit/log; el envío externo permanece deshabilitado. No se
promueven ADR ni se documenta la fase como completada mientras el gate sea rojo.
