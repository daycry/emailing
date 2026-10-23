# Revisión de dos lentes — intento 12: T-12

**0 Critical / 0 Important / 0 Minor actuales. D-01 corregido.** Segunda
revisión de tres del mismo incremento, global 12; sin reiniciar contador.
Lentes frescas A+B+D, fallback genérico y runtime heredado. C no activada;
D por migración. Scope exit 0, 83/83 en alcance, sin exclusiones, avisos ni info.
Los dictámenes íntegros están en [A](attempt-12-lens-a.md),
[B](attempt-12-lens-b.md) y [D](attempt-12-lens-d.md).

| # | Grado original | Gap | Tarea | Corrección y veredicto | Evidencia |
|---|---|---|---|---|---|
| D-01 | Important | Claim global recorría y ordenaba el backlog por evento | T-12 | Corregido y aprobado por A+B+D: elegibilidad generada e índice compatible | [Triage](../raw/t12-claim-triage-stdout.txt), [RED](../raw/t12-claim-red-stdout.txt), [GREEN final](../raw/t12-claim-green-final-stdout.txt), [benchmark](../raw/t12-claim-benchmark-stdout.txt) |

La columna almacenada eligible_at deriva de available_at para pending y de
GREATEST(available_at,lease_expires_at) para publishing; los demás estados y el
lease nulo quedan fuera. La consulta usa eligible_at,id y SKIP LOCKED. El
conjunto elegible se conserva; el orden pasa explícitamente al instante de
elegibilidad, después ID. Los contratos no imponen FIFO global por available_at.

Triage real con 10.000: 1/10/100 claims globales requieren
10.005/100.050/1.000.500 lecturas Handler y scan+sort. Final: 3/30/300 y rango
indexado sin sort. Con 10.000 publicados o 9.999 más un pending futuro, la
consulta vacía requiere una lectura; reactivar el pendiente requiere tres.
Son contadores internos, no consultas SQL, filas únicas, throughput SMTP ni SLA.
La preparación y completion SQL quedan fuera de esa medición.

RED conserva 101.050 > presupuesto 4.700; GREEN final 132/30 para diez claims
sobre 100/10.000. Método idéntico normalizando solo CRLF→LF; hashes raw se
conservan separados. Pruebas de fechas/leases y dos procesos que terminan con
IDs distintos mientras el padre retiene el primer lock respaldan la corrección.
CAS, generación, token, identidad y publicación fuera de SQL no cambian.

Gates finales: regresión 3/120, focal 20/293 y full 161/1.783, exit 0; Pint
acotado exit 0. Full: 154 fuentes, cero runtime; 129 comparables directamente
y 25 resources en ZIP. Los 25 core son contexto de los gates, no 25 cambios.
Materializador actual: 9/9/47 SQL; última muestra de 10.000: 6,9351635 s.
Los 3,2206176 s pertenecen al checkpoint histórico anterior; ninguna muestra
constituye comparación temporal controlada ni capacidad de envío.

Root volvió a verificar los 1.135 archivos del paquete sin cambios. Las tablas
completas conservan los aprobados, no verificables y límites del intento 11.
Patch SHA `93c5525c37f1c49b1bd54376009fd0dc4f684959dfc24b02febc165f5f596e3a`; metadata original SHA
`dc9ac7d8e810e4e726ea3fe06c2c42b8ff5df00fe344a90b3323fea275383f97`. Se preservan sus bytes originales junto a este
informe. Los revisores analizaron en solo lectura; después entregaron sus
Markdown íntegros mediante un encargo separado de registro fuera del snapshot.

Se publican 66 evidencias seleccionadas, conservando hashes/bytes y originales
privados. El stdout histórico sensible y los ZIP completos permanecen privados.
El handoff declara captura posterior de los primeros helpers sin cambios y
ausencia del stream original del primer helper audit fallido: no se inventa un
recibo ni se cuenta como suite/QA. Los gates finales sí conservan recibos y
streams. La limpieza final registra cero recursos propios pendientes.

Revisión 2026-10-23T12:49:52Z–2026-10-23T13:04:24Z; reloj 15m.
Tokens/euros/horas IA reales NULL. GOT-003 se valida para barreras de prueba;
ADR-003 permanece propuesta porque faltan sus invariantes de consumidor/SMTP.
T-10/T-11/T-12 siguen en progreso y total 9/20: quedan worker, E2E-08/09,
QA de fase 3, M-01 y criterios integrales. El siguiente componente es T-13.
Correo externo deshabilitado; Mailpit/log. No se acredita Pint global,
whitespace global, historial Git limpio ni estado del futuro índice.

Preparaci?n del ?ndice posterior a la revisi?n: .gitattributes conserva
*.patch.json como -text para evitar que autocrlf cambie los hashes originales
de metadata. Es una regla de conservaci?n de artefactos; el snapshot revisado
y el c?digo PHP permanecen intactos.

Los JSON inmutables de raw y metadata tambi?n conservan su whitespace de
herramienta: el diagn?stico del ?ndice encontr?9.381 avisos CRLF en35JSON,
ninguno en c?digo. La regla -whitespace se limita a esos artefactos y evita
reescribir originales ya hashados; el gate sigue aplicado al c?digo.
