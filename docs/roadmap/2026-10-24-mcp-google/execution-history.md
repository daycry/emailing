# Historial de ejecución — mcp-google

## Archivado 2026-10-30 — Revisión de dos lentes — intento 1: Fase 2 (T-02, T-03) — corrección necesaria (bytes sin cambios)

SHA-256 del bloque: `34d6e7dd33e754adc052af9cd7a18ac1b2f5305b5b021a025e70c4be5db6b0bf`.

## Revisión de dos lentes — intento 1: Fase 2 (T-02, T-03) — corrección necesaria

La implementación se revisó sobre la base `ae91752f5a7457834441d13e4707205509fd28f1`.
La cápsula privada e inmutable `storage/mcp-google-phase2-review1/manifest.json`
conserva SHA-256 `b6e418ab767da6198a7fed92803b69cd76ee572ed733c496be8d201b99fc5b5b`:
41 archivos del incremento, contexto completo y 316 hashes verificados por cada
lente antes y después. Los archivos nuevos están incluidos completos.

La puerta de alcance independiente terminó exit 0: 41 archivos, ninguno fuera
de alcance, sin avisos ni exclusiones de usuario. El selector activó C por las
rutas de autenticación/configuración y D por las dos migraciones. A, B y C se
despacharon en paralelo; D comenzó al quedar un puesto libre. Todas las lentes
usaron revisores genéricos con contexto nuevo porque `reviewer` por nombre no
está disponible; el tier declarado por el plugin no acredita ejecución Opus.

| Lente | Critical | Important | Minor | Resultado |
|---|---:|---:|---:|---|
| A — requisitos y alcance | 0 | 0 | 0 | Conforme en el incremento T-02/T-03; puertas globales pendientes |
| B — corrección | 0 | 1 | 0 | B-01 pendiente: el formulario pierde la confirmación Google tras una contraseña inválida |
| C — seguridad | 0 | 0 | 0 | Sin vulnerabilidades introducidas o reabiertas confirmadas |
| D — rendimiento | 0 | 0 | 0 | Sin degradación sustentada con una carga concreta |

| Criterio revisado por A | Veredicto | Evidencia pública |
|---|---|---|
| T-02: clave exacta y estable, sin fusión por email | ✓ | Migración de identidades; `GoogleIdentityResolver`; `GoogleIdentityTest` |
| T-02: propiedad, transacciones, auditoría y carreras | ✓ | Bloqueo de usuario/identidad, auditoría propia y pruebas con procesos SQL independientes |
| T-02: desvínculo con recuperación local viable | ✓ | Estado protegido de contraseña local; acción Fortify; regresión de recuperación |
| T-03: firma, claims, state/código de un uso y actor | ✓ | `GoogleTokenVerifier`, consumo SQL y negativos/concurrencia de `GoogleLoginTest` |
| T-03: alta explícita, Workspace e invitaciones | ✓ | POST con CSRF, política firmada y token real emitido por `TeamService` |
| T-03: retorno confiable, perfil y acceso local | ✓ | Allowlist sobre APP_URL, UI de identidades, CSRF, Fortify y fallback |
| Alcance y evidencia declarados | ✓ | 41 archivos declarados y pruebas finales correctamente diferenciadas por snapshot |
| Constitución, interop y OpenAPI modificado | N/A | No hay constitución; no cambian commands/agents/hooks ni una spec OpenAPI |
| Cobertura porcentual | N/A | No se inventa un porcentaje; se revisan pruebas y evidencia concreta |

### B-01 — Conservar la confirmación tras errores corregibles del formulario

**Important, confirmado por lectura; pendiente de reproducción y corrección.**
`app/Services/Auth/GoogleLoginFlow.php:147` elimina `google.registration` antes de
que `app/Actions/Fortify/CreateNewUser.php:22` valide la contraseña.
`app/Http/Controllers/Auth/GoogleLoginController.php:56` transforma esa excepción
en un error genérico y devuelve a login. Con nombre/cuenta válidos, consentimiento
marcado y dos contraseñas de al menos doce caracteres distintas, no se puede
corregir y reenviar la confirmación: hay que repetir OAuth.

La corrección debe permitir reparar los errores de formulario conservando el
pendiente y su caducidad originales, sin revelar existencia de cuentas, reflejar
respuestas del proveedor ni almacenar o mostrar contraseñas. El alta válida debe
seguir siendo explícita, atómica y de un solo uso. Se requiere una regresión
que falle antes del fix, verifique la corrección sin otro OAuth y compruebe que
no hay usuario/cuenta/identidad creados durante el error.

### Evidencia conservada y siguiente puerta

Root contrastó los recibos y hashes de 19 cápsulas. La batería anterior al ajuste
de invitaciones dio identidad **6/34**, acceso **13/415** y regresiones locales
**27/248**, sobre 289 fuentes inmutables. Después, el acceso **13/418** y los tres
E2E pasaron sobre el mismo snapshot final `bff3d70e…`. La comparación de los dos
ZIP completos confirma que sólo cambiaron la gramática del retorno, su prueba y
el token de navegador; las verificaciones anteriores no se atribuyen al hash nuevo.

La comprobación independiente de privacidad del árbol actual terminó exit 0;
los puertos de prueba 18967/18968 estaban disponibles mediante bind exclusivo
tras la liberación del implementador. Esto no acredita limpieza del historial
público, Google real, Apache/Nginx/Docker ni consentimiento MCP completo.

T-02/T-03 siguen **en-progreso**, MCP **1/14**, plataforma principal **18/20**.
No hay aceptación QA ni promoción de GOT-012 mientras B-01 esté pendiente.
Se devuelve B-01 al implementador y la siguiente revisión conservará todos los
veredictos de este intento; no se cierra ni reduce ningún criterio global.


## Archivado 2026-10-30 — Revisión de dos lentes — intento 1: Fase 3 (T-04, T-05, T-06, T-07) — correcciones requeridas (bytes sin cambios)

SHA-256 del bloque: `ddcb2847b030db0474676fb09dde5eef6ecda5d8475a0cd48ee1d060158c2fdb`.

## Revisión de dos lentes — intento 1: Fase 3 (T-04, T-05, T-06, T-07) — correcciones requeridas

- **Fecha**: 2026-10-25 12:16:22 UTC.
- **Lentes**: A+B+C+D, contexto fresco; respaldo genérico heredado. D arrancó al liberarse capacidad. Revisión completa del patch y de las instantáneas nuevas; sin runtime.
- **Resultado**: 0 Critical, 4 Important, 2 Minor. Se corrigen también los Minor; no se acepta deuda. T-04–T-07 permanecen en progreso; MCP3/14 y aplicación18/20 intactos.
- **Fuente**: base `7cce40bd09997e079013f01e96ccf6a734b0187d`; manifiesto `3e264c548008978898b489a38a44c222e72412ad7e377472a6e74edaa7daaf16`; 98 cambios y 628 archivos fijados.
- **Informe completo y tabla de 17 criterios**: [revisión 1](testing/fase-3/review-1/report.md). Los ✓ de A no aprueban el escenario adicional B-01 ni suplen A-01.

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| A-01 | Important | Faltan negativos HTTP de metadatos modernos — pendiente | T-06 | Probar ausencia, parcialidad en ambos sentidos, tipos erróneos y espejos de cabecera; rechazo definido y ningún servicio ejecutado. | `tests/Feature/McpTransportTest.php:84` |
| B-01 | Important | La retirada puede omitir autorizaciones concurrentes — pendiente | T-04 | Reproducir la intercalación real con MySQL REPEATABLE READ y barreras; impedir reactivar tokens tras retirada/reinvitación, conservando el orden de bloqueos. | `app/Services/TeamService.php:156` |
| B-02 | Minor | El callback admite nombres OAuth reservados con valor vacío — pendiente | T-05 | Rechazar claves reservadas vacías y codificadas sin cambiar que los valores vacíos del protocolo OAuth se tratan como omitidos. | `app/Services/Mcp/OAuth/RedirectPolicy.php:15` |
| B-03 | Minor | El nombre Unicode se limita por bytes — pendiente | T-05 | Admitir hasta 150 caracteres Unicode en client_name y rechazar 151; preservar los límites de bytes de los demás campos y del cuerpo. | `app/Services/Mcp/OAuth/Input.php:78` |
| D-01 | Important | La limpieza programada drena menos ventanas de las que se crean — pendiente | T-05 | Mantener pasadas finitas y demostrar que la frecuencia y el presupuesto drenan varios lotes de ventanas caducadas; conservar las retenciones y referencias. | `routes/console.php:11` |
| D-02 | Important | La retirada recorre autorizaciones históricas ya revocadas — pendiente | T-04 | Evitar trabajo proporcional a tombstones ya revocados; probar un histórico de 600 grants y la autoridad viva, con presupuesto de consultas y auditoría correcta. | `app/Services/TeamService.php:156` |

- **Arbitraje**: seis hallazgos confirmados por lectura; B-01 requiere regresión SQL causal. B-01 y D-02 se corrigen juntos, con evidencia separada para corrección y coste. Sin rebates ni descartes.
- **Puertas**: scope previo 98/98, cero fuera/avisos/criterios alterados/exclusiones; QA independiente no iniciado. Contratos fijados sin cambios. Pint del cambio verde; fallo global preexistente conservado y calificado en el informe.
- **Siguiente paso**: correcciones secuenciales T-04-fix1 → T-05-fix1 → T-06-fix1, luego revisión intento 2/3 con la tabla completa anterior. Jira/Confluence sin opt-in; no publicación externa.




## Archivado 2026-10-30 — Revisión de dos lentes — intento 2: Fase 4 (T-09) — fix1 sin gaps pendientes (bytes sin cambios)

SHA-256 del bloque: `12a65d553d847c54060cb9cbcd803a65b2ed2f095bea041690a2501563bcad26`.

## Revisión de dos lentes — intento 2: Fase 4 (T-09) — fix1 sin gaps pendientes

Lentes A+B+D; C no seleccionada. Selector oficial: D por `tests/Feature/MigrationReplayTest.php`, patrón Migration, cero avisos. Tres subagentes genéricos de contexto fresco con modelo heredado, sin override, porque reviewer nominal no está expuesto. Cobertura80/80 y cápsula2.032/2.032 verificadas por cada lente y por el orquestador; mismo patch/base. A:18 criterios,14 conformes/4 no aplicables; conserva la tabla original y evalúa fix1. No se rebaten ni se aceptan defectos como deuda.

| # | Grado | Gap | Tarea | Corrección | Evidencia |
| --- | --- | --- | --- | --- | --- |
| A | — | Sin gaps de requisitos | T-09 | No necesaria | [Lente A](testing/fase-4/t09/review2/verdict-a.md) |
| B | — | Sin defectos de corrección | T-09 | No necesaria | [Lente B](testing/fase-4/t09/review2/verdict-b.md) |
| D | — | Sin regresión de rendimiento sustentada | T-09 | No necesaria | [Lente D](testing/fase-4/t09/review2/verdict-d.md) |

[Dictamen e integridad](testing/fase-4/t09/review2/report.md). Los marcadores de implementación y revisión se cerraron sin solape; no hay tokens, coste ni tiempo IA medidos. La suite completa seguía ejecutándose sobre399 fuentes congeladas durante esta revisión estática; aún debe admitirse su recibo/JUnit. QA1 queda NO-VERDE y QA2 no ha empezado. T-09 sigue en-progreso,8/14; no hay aceptación técnica ni publicación anticipada. Jira no configurado: no se ejecutaron efectos externos. Esta revisión aporta evidencia a la curación futura, sin aprobar ni editar memoria.



## Archivado 2026-10-30 — Revisión de dos lentes — intento 1: Fase 4 (T-10) — corrección de auditoría pendiente (bytes sin cambios)

SHA-256 del bloque: `e2b41dbd406b952b04e3889884d578623797cc17749bbdebde5711c6592c8347`.

## Revisión de dos lentes — intento 1: Fase 4 (T-10) — corrección de auditoría pendiente

Base aceptada: `f38e282edeeffe885ed56d5d951d89f54fb9b0f1`; diff completo de 55 archivos, incluidos los dos helpers nuevos sin seguir. Puerta de alcance exit0: cero archivos fuera de alcance, avisos, exclusiones de usuario y criterios alterados. Selector 2.0.1: A+B+D; C no activada por su heuristica. Tres revisores de contexto fresco, solo lectura; el runtime no expone el tipo reviewer y se usa el fallback generico, sin afirmar un modelo nativo no aplicado. No sustituye una auditoria de seguridad ni QA.

| # | Grado | Gap | Tarea | Correccion | Evidencia |
|---|---|---|---|---|---|
| B-01 | Important | El guard saving de MailMessage compara el actor de una instancia retenida con el grant actual. Borrar al actor durante IO aplica SET NULL a ambas filas SQL, pero la instancia conserva su actor anterior; guardar un resultado de transporte conocido lanza LogicException y pierde el cierre durable. | T-10 | Pendiente: reproducir con borrado en transport_result; conservar la validacion de procedencia al crear o cambiar identidad y permitir actualizaciones exclusivas del resultado tras el tombstone. | app/Models/MailMessage.php:19; app/Services/DeliveryLifecycle.php:172; AccountMailTransport conserva la instancia hasta finish; FKs actor/grant SET NULL. |

A y D: sin hallazgos; B: 0 Critical, 1 Important, 0 Minor. Informes privados retenidos en `storage/worker-recovery-review-20261027/review-t10-attempt1/`, SHA256: A `da5a8ea68c5c618accc530dfe5ce5e6e2c8b1f327bd61013012133c35bf868f9`, B `73ccfb43302ec3978198103390db4bd41d0fc77be705a18006e652e989c1e7e4`, D `7be66d69828fea52dfad3338cfc7723b360027633d83d5c2680fd1b00f77a393`. Sin configuracion Jira ni publicacion externa. Medicion cerrada sin transcripciones: tokens/coste/horas IA desconocidos, no cero. T-10 permanece en-progreso; revision sin aceptar y QA pendiente.

**WorkerRecovery, seguimiento 2026-10-28**: negativas instaladas GREEN 2 casos/27 aserciones, cero problemas; 415 fuentes estables y streams/ZIP admitidos. Recibo `30700a0bef61456af6d2538bb9badd0337d6e9feac1244f7a80b7dea797201e0`; admision `c968256074b8758f4488157453354c6084be1f3e03fc964220daf54454568c1a`. El inventario hijo termina al cancelar y el permiso caducado no consume ni envia. Cuatro pruebas puras de sockets del proxy pasan; Pint pasa para los tres PHP afectados. Seleccion publica de patrones: 2435 archivos, cero hallazgos, sin cargar configuraciones privadas; no certifica historial.

El focal positivo de cuatro casos continua ejecutandose sobre las mismas fuentes. Su caso SQL ha alcanzado transport_result y el corte solicitado, pero la redelivery posterior conserva un control antiguo de 150s y ya presenta timeout: es un segundo punto del harness pendiente de adoptar la preparacion controlada. No se oculta el rojo ni se edita codigo durante la captura. Las demas pruebas focales, la suite completa ampliada (483 casos antes de instalar la nueva regresion de B-01), revision2 y QA independiente siguen pendientes. Los resultados focales no se suman ni se presentan como una suite unica verde.

## Estado vigente

El progreso y las decisiones actuales se consultan en [tasks.md](tasks.md).
