# Aceptación técnica incremental de T-09

**T-09 aceptada técnicamente en laboratorio el 2026-10-26 UTC.** Las catorce operaciones de campañas, actividad y supresiones comparten servicios, permisos y conflictos del dominio existente. El catálogo instalado contiene34 de37 operaciones; las tres de envío/conexión de T-10 siguen pendientes. MCP pasa a9/14 tareas; plataforma principal18/20 e infraestructura3/3.

| Criterio T-09 | Evidencia admitida |
| --- | --- |
| Ocho operaciones de campañas, tres de actividad y tres de supresiones, con permisos | Catálogo/anotaciones y pruebas directas en McpBusinessToolsTest; revisión A conserva T09-CA1 y T09-S1/S2. |
| Autorizar exige campaigns.write y mail.send, revisión vigente, confirmación e idempotencia | Servicios comunes y casos de autorización, revisiones y conflicto; revisión A T09-CA2. E2E-08 atraviesa consentimiento, revisión y autorización reales del laboratorio. |
| Pausa/reanudación y revisiones obsoletas conservan conflictos sin duplicar trabajo | Casos de negocio, cursores por revisión/padre/grant y broker rechazado sin envío alternativo; revisión A T09-CA3/S3. |
| Supresiones conservan los controles de consentimiento y baja | Casos de aislamiento/consentimiento del servicio compartido y revisión A T09-CA4; no se inventa una prueba de consentimiento. |

## Revisión y pruebas

La [revisión3](review3/report.md), A+B+D, terminó con Critical0/Important0/Minor0,298/298 rutas y2.249/2.249 miembros verificados. A registra19 criterios conformes y4 no aplicables. D fue seleccionada por la regresión de migraciones; C no seleccionada. No equivale a auditoría completa de seguridad ni benchmark. Los revisores sólo devolvieron mensajes; root identificó sus copias/transcripciones honestamente.

La [QA3 independiente](qa3/report.md), su [PDF](qa3/report.pdf) y [evidencia](qa3/evidence.json) acreditan:

- Suite completa anterior Unit+Feature:412 pruebas/7967 aserciones/41 clases, sin fallos, errores u omitidos; productor root, admitida independientemente por QA3 desde recibo, ZIP, fuentes y JUnit. El subconjunto T09151/3175/11 está incluido, no se suma al total.
- Regresión propia histórica QA2:8/85 sin fallos, errores u omitidos, originales admitidos de nuevo por QA3. No se presenta como ejecución nueva de QA3.
- Ejecuciones propias QA3: ocho E2E originales8/8 y tanda separada de estabilidad3/3, retry0; ambos qa-gates oficiales VERDE, sin flaky, omitidos o interrumpidos.
- Las399 entradas finales son estables. Frente al backend anterior sólo cambia el E2E aprobado;398 entradas idénticas, incluido todo PHP/manifiestos/configuración relevante. No se repite PHP sin una preocupación nueva.
- Dos tandas SQL propias con28 categorías cero y tx0, guarda de base/usuario testing y tres binds exclusivos antes/después. Callback cerrado y teardown terminal0. Los recibos naturales de limpieza del launcher están ausentes; no se afirma ausencia global de procesos.
- QA3 inspeccionó26 capturas originales y6 páginas PDF. Root inspeccionó esas6 páginas y4 originales representativos de campañas, sin atribuirse las otras22 inspecciones. Las capturas parciales no acreditan toda la página, accesibilidad completa o claridad humana.

Root contrastó161 hashes de artefactos en162 archivos públicos, seis runs nativos/ZIP/fuentes y43 copias nativas. Recibos y sesiones están terminales y QA3 liberó explícitamente el runtime. No se aceptan resúmenes en lugar de originales ni se suman tandas solapadas. Las comprobaciones finales de publicación se ejecutan después de este cierre documental.

## Correcciones y antecedentes conservados

[QA1](qa1/report.md) y [QA2](qa2/report.md) mantienen NO-VERDE. Fix1 separa errores de evidencia/DDL, publica snapshots inmutables, conserva la causa primaria y restaura correctamente la secuencia histórica; actualiza la expectativa antigua de discovery a34 sin retirar initialize legacy o revocaciones. Su regresión causal y la propia de QA2 están conservadas.

[Fix2](fix2/report.md) prueba por HTTP/MySQL la colisión del email en un fixture compartido y aísla cuatro etiquetas con UUID+repeatEachIndex. No relaja unicidad, dependencias de campaña, assertions, reintentos o tiempos. El código cerrado histórico no estaba conservado; validation_failed se atribuye sólo al experimento controlado. Desarrollo8/8+3/3 y QA3 son productores distintos.

## Privacidad, desviación de método y límites

El checker acotado final de QA3 inspeccionó2416 archivos del worktree con cero detecciones por patrones. Excluyó los cuerpos vetados por nombre y rechazó cambios en ellos; no cargó configuraciones privadas ni comparó sus valores. Esta afirmación pertenece al checker, no a todos los helpers de QA.

Se declara una desviación: el inventariador project-pieces se ejecutó sin limitar ámbito de usuario y leyó indirectamente configuración completa antes de proyectar metadatos. Su salida/rutas personales permanecen privadas y no se publican. La presencia o carga interna de valores privados es desconocida; no se reabrieron configuraciones para averiguarlo. Root comprobó el código y hash del helper, sin inspeccionar esos archivos. Se conserva el incidente de método con atribución; no se afirma que nunca se leyera configuración privada durante QA. No acredita exposición de valores, ni certifica su ausencia.

Root comprobó además texto y metadatos del PDF sin detecciones por patrones y sin autor personal. El historial Git y cuerpos excluidos permanecen fuera de esta certificación; su tratamiento final conserva las tareas/decisiones pendientes. Los originales inmutables no se alteraron para ocultar errores. Los atributos del nuevo árbol T09 preservan bytes nativos al clonar.

Los medidores de implementación, corrección, revisión y QA se cerraron por etapas; sin transcripciones/telemetría compatibles no hay tokens, coste ni horas IA reales medidos. El reloj no se presenta como tiempo IA. No se ejecutaron Jira, Confluence, mensajes externos o reescrituras de historial.

## Trabajo que continúa

T-10–T-14 conservan todo su alcance: las37 herramientas completas, envío transaccional por el pipeline común, HTTPS/Laragon/Docker, clientes/Google reales, regresión y revisión/seguridad consolidadas, y aceptación humana M01–M04. PrincipalT19/T20, carga multiaplicación, recuperación, documentación final, Curator/retro y privacidad del historial siguen pendientes. Las propuestas de memoria permanecen pending; esta aceptación no las aprueba. Mailpit/log siguen siendo los únicos transportes habilitados y la aceptación SMTP no acredita entrega final.

Este cierre permite preparar el commit/push normal autorizado de T09 y después comenzar T10. No cierra la iniciativa Google/MCP ni el objetivo global, ni autoriza force push, reescritura de historial o envío SMTP externo.
