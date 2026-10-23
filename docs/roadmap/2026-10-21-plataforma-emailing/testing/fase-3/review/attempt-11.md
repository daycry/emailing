# Revisión de dos lentes — intento 11 · T-12

**0 Critical / 1 Important / 0 Minor. Primera revisión de tres del incremento.**
Lentes frescas A+B+D por fallback genérico/runtime heredado. C no activada;
D activada por la migración. Alcance exit 0: 18/18 archivos, sin fuera de
alcance, exclusiones, avisos ni info. Las tablas completas se conservan en
[A](attempt-11-lens-a.md), [B](attempt-11-lens-b.md) y [D](attempt-11-lens-d.md).

| # | Grado | Gap | Tarea | Estado | Evidencia |
|---|---|---|---|---|---|
| D-01 | Important | La selección global de outbox combina estados y orden sin índice compatible, repetida por publicación | T-12 | Pendiente de contraste SQL y corrección; no aprobado | OutboxDispatcher.php:20–22, migración:47, CLI:23; [dictamen D](attempt-11-lens-d.md) |

El índice empieza por status; el orden solicitado es available_at,id entre
las dos ramas. D estima 995.050 candidatos para las primeras 100 reclamaciones
de 10.000 pendientes y 50.005.000 al drenarlos. Son estimaciones estructurales,
no latencias ni EXPLAIN medidos. El benchmark existente solo mide materialize.
Root contrastará el plan/coste real antes de corregir o rebatir con evidencia;
un verde funcional no cierra este hallazgo de rendimiento.

La corrección anterior del materializador sí queda acreditada: 10.000 pasa
de 20.007 SQL/214,0082381 s a 47/3,2206176 s en muestras de laboratorio.
Ese problema histórico no se reabre como defecto actual. Focal 17/173 y
full 158/1.663, exit 0; rollback real 3819 después del INSERT de entrega,
confirm perdido, return→ack, KILL Linux tras barrera y corte SQL tras ACK.
Los resultados corresponden al snapshot revisado, no a cambios posteriores.

Root verificó antes de escribir esta traza 218 fuentes, 570 evidencias,
dos preimágenes y patch: 791 rutas únicas, sin discrepancias. Patch SHA
`2406d504e18ce1697e24a9780ea67937b1f78812c63d9bb99376c0e72dc05d5a`; metadata original SHA `9087e3ab1482037f03bbd6d1a220b830139590f9e81530d0803daefba9f9bbc7`.
El full contiene 193 entradas: 154 fuentes y 39 JSON de ejecución.

**Aclaración factual del informe A:** su cierre indica 138 comparaciones
directas y 16 resources fuera de sources/. La comparación por conjuntos
del manifest completo con all_source_files da **129 directas y 25 resources**
en el ZIP, como indica D. Se conserva la respuesta A sin reescribirla; la
fusión corrige estas cifras. Las 154 coincidencias live previas quedan en la
evidencia root, y los JSON de ejecución no se tratan como fuentes estables.

Se publican 59 artefactos de evidencia seleccionados del paquete revisado,
121.158 bytes originales, con cero hallazgos de privacidad al preparar/copiar.
Los ZIP completos y el stdout histórico con secreto detectado quedan privados;
no se publica ese raw ni se sustituye su original por una versión saneada.
Los hashes y el tratamiento constan en el ledger. La comprobación del índice
Git antes del commit sigue siendo necesaria; esto no audita el historial.

Ventana revisión 2026-10-23T11:49:08Z–2026-10-23T12:03:29Z; reloj 14m.
Tokens/euros/horas IA reales NULL, sin atribuir reloj a consumo. Sin Jira opt-in.
No se promueven GOT-003 ni ADR-003, no se cierran T-10/T-11/T-12 ni QA de fase 3.
Total 9/20; worker, SMTP, E2E-08/09, M-01 y criterios integrales conservan sus
pendientes. Siguiente paso: T-12-fix1 para D-01 y segunda revisión con traspaso
completo de los tres dictámenes; no reiniciar el contador.
