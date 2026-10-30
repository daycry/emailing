# Verificación de implementación T11

Fecha: 2026-10-30. T11 completada tras los perfiles y las puertas independientes.
La [aceptación](acceptance.md) reúne los criterios, revisión2 y QA9/9. Los estados
pendientes que acompañan recibos anteriores conservan su atribución histórica.

## Persistencia y recuperación

El caso `test_kernel_rebootstrap_keeps_identity_key_and_live_revocation` fallaba al
comparar el hash de un modelo recién creado con el de una fila recargada. La
reproducción mínima dio un fallo. Una sonda previa a cualquier rebootstrap confirmó
que los valores compartidos eran iguales, pero la lectura SQL incluía `last_used_at`
con valor nulo y un orden de columnas distinto. Dos lecturas persistidas resultaban
iguales. No se encontró pérdida de identidad ni cambio de clave.

El test ahora recarga la identidad antes del digest inicial. Conserva todos los
atributos persistidos y las comprobaciones de clave, identidad, grant y rechazo de
la credencial revocada. No se debilita el assert ni se cambia el modelo para adaptar
sus datos a una serialización accidental.

| Prueba | Resultado observado |
| --- | --- |
| Reproducción original focal | 1 caso, 1 fallo en el digest de identidad. |
| Sonda de hidratación independiente | 1 caso correcto; mismos campos compartidos, `last_used_at` añadida por SQL. |
| `McpPersistenceTest` después de corregir | **8 casos, 61 aserciones, 0 fallos/errores/omitidos**. |

Los ocho casos cubren rebootstrap, preview sin escritura, rechazo de `--apply` sin
mantenimiento, invalidación con MCP deshabilitado, preservación del historial de
correo, invalidación de snapshot antiguo, repetición idempotente y rollback ante
un fallo tardío. Usan MySQL real con comprobación de base y usuario
`emailing_testing` antes de cualquier cambio de esquema. Un rebootstrap no se
presenta como reinicio de servidor.

La corrección anterior del comando elimina su dependencia de discovery OAuth:
recuperar autoridad restaurada debe funcionar mientras MCP permanece cerrado.
Se conservan el modo de mantenimiento y la transacción. Las llaves API de cuenta
permanecen fuera del alcance del comando.

## Runner público

La primera suite instalada pasó134 pruebas. El ensayo HTTP descubrió que el
smoke usaba `id` en dos llamadas, mientras que el contrato exige `contact_id` y
`template_id`. Se añadió una regresión contra los esquemas de entrada del catálogo
fijado: falló en ambas operaciones antes de corregir el runner. La suite final
ejecutó **135 pruebas en20,143 s, resultado OK**.

Los tests cubren el layout instalado y el puente PHP real de validación, transporte
HTTP/TLS local, esquemas, protocolo, autoridad de fixtures, recibos sin secretos,
límites y recogida del árbol de procesos. No usan Google real ni acreditan los
clientes nativos. La salida del smoke mantiene `profile_qualified: false`; la
calificación completa exige pruebas separadas de ciclo de vida.

## Ensayos de perfiles

Las imágenes se construyen desde una exportación de448 archivos de un árbol Git
inmutable, con dependencias del lock y sin `.env`, vendor del host, almacenamiento
privado ni configuración de clientes. La etiqueta sólo identifica cómodamente el
artefacto; las pruebas usan el ID real de imagen. El montaje de configuración TLS
y secretos es explícito y no tapa el código de aplicación de la imagen.

Los primeros ensayos detectaron y conservaron estos fallos:

- Certificado sintético sin `Authority Key Identifier`: rechazado por la validación
  estricta. La receta utiliza ahora configuración CA explícita, AKI/SKI y evita
  extensiones duplicadas procedentes del OpenSSL del host.
- Discovery OAuth bloqueado con403 por la regla Nginx para archivos ocultos. Se
  permiten únicamente las tres rutas exactas de metadatos; el resto de la regla
  permanece activo.
- Bootstrap PHP-CGI que devolvía un fatal con HTTP200 al reutilizar OPcache de
  Windows. La sonda aisló el error en Finder al construir un iterador. El mismo
  bootstrap funcionó con OPcache deshabilitado y con un ID de caché propio. El
  perfil adopta el segundo mecanismo, conservando OPcache y la configuración global.
- Argumentos incorrectos del smoke: corregidos con el RED de contrato descrito arriba.

Cada ensayo fallido terminó sus procesos propios y retiró su esquema de fixtures;
no se transforman en una calificación verde. La ejecución final terminó con exit0,
448 fuentes sin cambios durante la prueba y ambos perfiles técnicamente cualificados.

| Comprobación real | Laragon | Docker |
| --- | --- | --- |
| Arranque frío, identidad, lectura útil y denegación; ambos protocolos | Correcto | Correcto |
| Mismas comprobaciones tras parar y volver a arrancar web/PHP | Correcto | Correcto |
| Rotación con clave anterior; ambos protocolos siguen funcionando | Correcto | Correcto |
| Descifrado de canario previo con la clave anterior | Correcto | Correcto también dentro de la imagen PHP8.4.27, sin red |
| Rechazo401 de la credencial revocada al volver a arrancar | Correcto | Correcto |
| Restore de copia actual: conserva revocaciones y datos de negocio | Correcto | Correcto |
| Restore antiguo, servicios detenidos: invalida autoridad antes de reabrir; después401 | Correcto | Correcto |
| Certificado confiable, cookie Secure, rechazo de Host/formato proxy y CA ajena | Correcto | Correcto |
| Procesos/recursos propios retirados y esquema de fixtures limpiado | Correcto | Correcto |

Cada perfil ejecutó seis smokes: dos protocolos en arranque frío, después de
reinicio y después de rotación. Las restauraciones reconstruyeron datos de47
tablas de fixtures en la base exclusiva, conservando esquema/migraciones. Los
rechazos posteriores se comprobaron por HTTP, no sólo consultando SQL.

[Evidencia y procedencia](evidence.json), [backend](backend-summary.json),
[runner](python-summary.json), [Laragon](laragon-admission.public.json),
[Docker](docker-admission.public.json) y [artefactos](artifact.public.json).
Los JSON de perfiles son proyecciones de los recibos que omiten la ruta de entrada
privada. No se publican entornos, claves, snapshots ni cuerpos HTTP.
El [manifiesto de fuentes del artefacto](artifact-source-manifest.json) contiene
sólo nombres de fuentes públicas y sus hashes. Se reprodujo su exportación Git
con `core.autocrlf=true` y coincidieron los448 cuerpos; los contratos fijados
conservan `-text`. La [matriz](../../client-matrix.md) distingue perfiles de clientes.

## Correcciones de revisión 1

La revisión A/B/C/D encontró dos Important: faltaba activar/recuperar el scheduler
reservado a T11 y la receta PowerShell podía ejecutar `up` tras un fallo de
invalidación. Las cuatro [salidas originales](review-1/lens-a.md) se conservan;
los informes B/C/D están en la misma carpeta.

`emailing-scheduler` ejecuta `schedule:work`, comparte imagen/configuración/caché
de aplicación y dispone de `init`, reinicio y parada graciosa. El override TLS
monta también su entorno como secreto. La guía prepara el proceso nativo y explica
la recuperación del mutex sólo después de detener las pasadas de esa aplicación.

El ensayo adicional arrancó el daemon real en cada perfil, esperó la eliminación
de un marcador SQL expirado, lo detuvo, creó y recuperó un mutex abandonado y
arrancó otra vez. **Cuatro pasadas periódicas reales** eliminaron los cuatro
marcadores. Docker ejecutó además los dos protocolos MCP con el scheduler activo.
Los recursos propios y el esquema de pruebas quedaron retirados. La primera
prueba del harness no cargó el entorno privado al invocar PHP con `-r`; se conservó
el error y se corrigió la carga explícita con guarda del store/prefijo de caché
antes de admitir el resultado posterior.

La receta de restore comprueba cada código de salida, conserva el cierre ante
fallo y separa la comprobación privada de la reapertura pública. Se ejecutó su
bloque publicado con un stub PHP puro: fallos de mantenimiento, preview y apply,
y un caso correcto. Los **cuatro casos** terminaron como se esperaba sin reabrir
automáticamente y sin ejecutar Laravel ni servicios.

[Recibos del scheduler](fix1/scheduler-admission.json),
[casos PowerShell](fix1/restore-recipe-admission.json) y
[artefacto final](fix1/artifact.json), con su
[manifiesto](fix1/artifact-source-manifest.json). El nuevo artefacto sólo cambia
`compose.yaml` y `compose.tls.yaml`; los otros446 miembros públicos coinciden con
el de los ciclos completos anteriores, incluidos aplicación, contratos,
migraciones, dependencias y tests. Se conservan sus recibos y se ejecuta la nueva
composición para acreditar el servicio añadido. Al emitir los recibos de fix1,
revisión2 y QA seguían pendientes; después cerraron sin gaps y9/9 respectivamente,
según la [aceptación](acceptance.md), sin modificar esos recibos originales.

## Límites de aceptación

Los datos son ficticios; las identidades Google se crean como fixtures, sin login
externo. No se cambia la configuración de Codex/Claude/OpenCode ni se instala una
CA global. El correo continúa en Mailpit/log. La restauración de datos MCP de T11
no sustituye el simulacro global de SQL, broker y volúmenes de T19.

La regresión global de la ampliación sigue en T12; la revisión consolidada, en T13;
Google, clientes reales y manuales del servidor de destino, en T14. Los resultados
históricos de485 pruebas de T10 se conservan como evidencia de su versión y no
se presentan como una ejecución completa de la versión T11.
