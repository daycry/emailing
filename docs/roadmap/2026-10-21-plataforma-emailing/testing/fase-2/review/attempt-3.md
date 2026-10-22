# Revisión de dos lentes — intento 3 · T-09 / fase 2

**Corrección adicional requerida: cero Critical, un Important abierto.** A no
encuentra gaps nuevos; B confirma un defecto en el teardown del harness. QA2
sigue pendiente. No se cierra T-09 ni se modifica el resultado rojo de QA1.

| Campo | Evidencia |
|---|---|
| Base | `d293b88121481eca0cd4aa5709263d6fc5b88f63` |
| Diff completo | 31 fuentes,125.761 bytes,1.369 líneas |
| SHA256 | `1bc528a8bf60bfc6cfb6469afc6a0ea60c0234ea462b6c74cf02e6e852af869f` |
| Snapshot | [patch](attempt-3.patch) · [manifiesto](attempt-3.patch.json) |
| Alcance | exit0,90 cambios incluidas evidencias; cero fuera de alcance/avisos/exclusiones de usuario |
| Lentes | A+B; selector C=false,D=false |
| Integridad | Ambas leen el diff completo y verifican31/31 hashes; A reconstruye el patch sobre la base y coincide31/31, además29/29 del handoff QA |
| Roles | Contexto fresco, genéricos por ausencia de subagent_type reviewer; tier frontmatter resuelto, runtime heredado |

## Tabla fusionada y traspaso

| ID / criterio | Grado | Veredicto | Evidencia |
|---|---|---|---|
| B-01/B-02/D-01/UI-01 del intento2 | Important anteriores | Corregidos, conservados | [Tabla completa anterior](attempt-2.md); sin evidencia nueva que los reabra |
| QA-01 · navegación móvil | Important | Corrección focal aprobada; QA2 pendiente | Sólo flex-wrap/lg:flex-nowrap. Causal DOM551→390 en ambos; navegador nativo390/768/1440, seis enlaces completos y destino/accounts, escritorio column/nowrap |
| Requisitos T05–T08 y verificaciones anteriores | Aprobados | Conservados | Criterios completos del intento2; sin nuevos cambios de dominio |
| Harness · aserciones/umbrales | Sin gap | Conservados o reforzados | ScrollWidth<=viewport,9990filas cerca5MiB y DOM<500000; positivosHTML5 sin retirarrequired; negativosupload excluyen500 y exigen403 conCSRF |
| Harness · readiness E06 | No confirmado | Provisional | Probe no reprodujo pérdida del nombre. Nuevas esperas HTTP/SQL aún deben ejecutarse enQA2 |
| B-03 · limpieza de temporales | Important | Pendiente | `finalize-extras.py:27,35` busca raíz distinta de la configurada; uploads no importados y diagnóstico sinUUID pueden permanecer |
| Candidatos fechas/handles y seguridad anterior | No confirmados / aprobado | No reabiertos | Sin evidencia nueva; no se inventan defectos ni auditoría completa |
| T09 · matriz/E2E verdes | Pendiente | Estado fiel | QA1 sigue8passed/12failed/0flaky; no se atribuye verde a casos fallidos |

## B-03: escenario y comprobación

El finalizador usa `storage/app/private/livewire-tmp` en nativo y su equivalente
hardcodeado enDocker. La configuración real es disk `contact-imports`, directory
`temporary` (`config/livewire.php:7–8`) y raíz
`storage/app/private/contact-imports` (`config/filesystems.php:43`).

E2E05 sube un archivo para reutilizarlo/cambiar permisos sin importarlo: no nace
fila `contact_imports`, así que la limpieza de `source_path` no lo alcanza. El
finalizador consulta otra carpeta y puede reportar cero residuos sin mirar donde
Livewire guardó el upload. `diagnostic.mjs:18` añade un archivo sinUUID en su
contenido: corregir únicamente la raíz tampoco permite atribuirlo con el filtro
actual. Root contrastó esas rutas con las configuraciones después del dictamen.

La revisión fue exclusivamente de lectura: sin fixtures, DML, suites, navegador,
migraciones ni servicios. La conclusión se sostiene por el flujo ejecutado de
upload y las rutas de código, no por un nuevo barrido de datos privados. La
afirmación anterior de cero temporales no queda acreditada y se corrige aquí;
los JSON/resultados/reportes originales se conservan como evidencia histórica.

## Decisión del orquestador tras el tercer intento

El ciclo acotado devuelve la decisión al orquestador. Con el objetivo vigente de
implementar todo el roadmap y corregir los defectos, el orquestador decide
**continuar con una corrección específica de B-03**, sin aceptar deuda ni dar
autorización en nombre del usuario para borrar archivos ajenos. Se abre un
incremento de reparación del harness; QA2 espera a su validación.

La reparación debe derivar la raíz de Laravel/Livewire, registrar exactamente los
handles propios incluidos los diagnósticos y preservar archivos no atribuibles.
Debe demostrar limpieza en nativo/Docker y conservación de un archivo ajeno
controlado. No se corrige borrando carpetas globales ni relajando umbrales.

DockerQA ya se reconstruyó tras el fix de navegación: exit0,tres servicios sanos,
hash del layout coincide y ambos/login responden200. Esto no sustituyeQA2.
