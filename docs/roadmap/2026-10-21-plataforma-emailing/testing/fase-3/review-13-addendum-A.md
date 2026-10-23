Lente A · adenda de revisión global 13 · T-13 · intento conservado 1/3. Revisor genérico fresco, runtime heredado.

**Conforme dentro del alcance de la adenda. Critical 0 / Important 0 / Minor 0.** El suplemento acredita ahora el orden **publicación AMQP → baja pública de A → consumidores → A suprimida/B aceptada → único MAIL FROM de B → ACK y cola vacía**. Permite cerrar probatoriamente la casilla literal de T-10 «Probar baja después de encolar y antes del transporte» y su criterio de aislamiento, conservando las aprobaciones anteriores. El cierre del ledger corresponde a root.

Referencias abreviadas, todas dentro de los snapshots autorizados:

- `A`: `storage/phase3-t13-review13-addendum/`.
- `E`: `A/evidence/storage/phase3-t13/implementation/`.
- `H`: `E/review13-supplement-handoff-20261023T202228778686Z/`.
- `G`: `H/attributed-evidence/runtime-79b7a596-d0f8-4ac2-9fa2-811880f24be1/`.
- `V`: `H/attributed-evidence/supplement-published-unsubscribe-22abde58-ba89-4a53-b128-de235a1b938a/`.
- `T`: `storage/phase3-t13-review13-addendum-terminals/`.

| Tarea → criterio | Dictamen | Evidencia, escenario y límite | Grado |
|---|---|---|---|
| T-10 → interpretación literal del pendiente | ✓ | `A/sources/docs/roadmap/2026-10-21-plataforma-emailing/tasks.md:512` y `:518`; spec primaria `spec.md:160`, `:176`, `:180`. La nueva prueba cubre específicamente publicación en broker antes de la baja. | — |
| T-10 → dos mensajes realmente publicados antes del POST | ✓ | `V/WorkerRecoveryPublishedBeforeUnsubscribeSupplementTest.php:542`–`:551` publica ambos eventos y consulta pasivamente las colas. `G/queue-before-unsubscribe.json:6` registra **ready 2, consumidores 0**, fallo vacío, dos eventos `published` y cuentas distintas. | — |
| T-10 → mismo email en dos cuentas | ✓ | Variante `:538`–`:541` comprueba dos destinatarios y un único email. El helper `:253`–`:266` conserva el email original al crear B y restaura el contexto de A. `G/queue-before-unsubscribe.json:18` conserva el hecho y las identidades de ambas cuentas. | — |
| T-10 → baja pública posterior a AMQP y anterior al consumo | ✓ | Variante `:552`–`:565`: olvida guards/sesión, realiza POST y solo después inicia los workers. `G/unsubscribe-request.json:3`, `G/unsubscribe-response.json:3` y `G/queue-before-unsubscribe.json:3` mantienen orden monotónico en el mismo host. Respuesta **200**, todavía ready 2, entregas pendientes/0 intentos y 0 mensajes (`unsubscribe-response.json:6`–`:33`). | — |
| T-10 → consumidor real de las fuentes revisadas | ✓ | `G/loaded-account0:1` y `loaded-account1:1` registran Linux y ocho hashes de clases cada uno; los **16 hashes** coinciden con el manifiesto de runtime revisado. Recibos de ambos workers: exit 0 y streams vacíos. Se ejecutan secuencialmente; esta adenda no pretende demostrar competencia concurrente adicional. | — |
| T-10 → A suprimida antes del transporte | ✓ | Variante `:572`–`:574`; `G/after-workers.json:19`–`:25` y `sql-final.json:4`–`:13`: A queda `suppressed`, diagnóstico `recipient_suppressed`, **0 intentos**, sin MailMessage ni lease vigente. | — |
| T-10 → B permanece independiente | ✓ | Variante `:575`–`:579`; `G/after-workers.json:27`–`:41`: B queda `smtp_accepted`, **1 intento**, único MailMessage de B y remitente `second@example.test`. | — |
| T-10 → remitente observado en el envelope SMTP | ✓ | `V/envelope-observer.py:15`, `:23`–`:42` verifica el relay público por hash, observa exclusivamente su llamada `fact(mail)` y extrae el MAIL FROM del frame llamador. `G/envelope-observer.jsonl:1` contiene una única observación normalizada de `second@example.test`, vinculada al token, observer y relay; `envelope-observer-complete.json:1` confirma `seen:1`. | — |
| T-10 → único transporte y aceptación observable | ✓ | `G/relay.jsonl:1`–`:6`: una conexión, un MAIL, un DATA aceptado y un `final_250`. Variante `:580`–`:586` contrasta tanto relay como envelope. Es aceptación del relay sintético local, no entrega final. | — |
| T-10/T-13 → resultado y asentamiento | ✓ | `G/durable_result-account1:1` precede a `before_ack-account1:1` y `acked-account1:1` en el reloj del worker. A también tiene `before_ack/acked`. `G/after-workers.json:6`–`:18` registra ready 0, consumidores 0, fallo vacío y `basic_get` vacío; variante `:587`–`:595`. No comparé valores monotónicos entre Windows y Linux como si compartieran reloj. | — |
| Evidencia → variante/preimagen exactas | ✓ | Ambos diffs privados reconstruyen exactamente sus variantes. Las preimágenes de WorkerRecovery y relay coinciden byte a byte con las fuentes del snapshot primario. Tras normalizar únicamente el token de ejecución, la diferencia rojo→verde es la importación de `ReflectionClass`. No hay fix de runtime. | — |
| Verificación → rojo conservado y verde realmente ejecutado | ✓ | `T/review13-published-unsubscribe-20261023T201637092157Z/stdout.txt:6` registra `Tests\Feature\ReflectionClass` inexistente; receipt exit **2**, 1 fallo/14 aserciones. Se detuvo comprobando el loaded de A: no acredita B ni assertions finales. El corregido `stdout.txt:5` registra **1 passed/51 assertions**, receipt exit **0**, stderr vacío. | — |
| Limpieza propia → recursos del suplemento | ✓ | `E/supplement-final-owned-census-20261023T202043532892Z/supplement-owned-census.py:26`–`:54` enlaza recursos por token y recibos. Sus originales acreditan tres contenedores ausentes y dos relays propios ausentes; topologías eliminadas. El censo es una observación fechada y mantiene la exclusión histórica explícita. | — |
| Limpieza propia → guard SQL | ✓ con alcance preciso | `E/supplement-final-db-guard-20261023T202107008226Z/supplement-final-db-guard.php:9`–`:29` valida identidad testing y enumera las tablas existentes. `database-clean.json:4`–`:7` y stdout original registran únicamente `migrations:0` y ausencia de fixtures. No lo presento como un conteo nuevo de todas las tablas de negocio todavía existentes. | — |
| Publicación → metadata limitada y declarada | ✓ | `A/publication-metadata.patch:7` añade solo la regla `raw/*.md -text -whitespace`, siguiendo txt/json. `:15` declara `.gitattributes` y su finalidad en Archivos T-13. Las dos preimágenes coinciden con el snapshot primario y el patch reconstruye exactamente ambos resultados. No modifica estados, casillas ni reglas de envío. | — |
| Alcance → selección de lentes | ✓ | Ejecuté el selector oficial con `--files` sobre las dos rutas de `sources/`: exit **0**, **C=false, D=false**, modo auto, dos archivos y avisos vacíos. A/B son obligatorias. El alcance público es coherente con Archivos; no ejecuté scope contra Git vivo. | — |
| Continuidad T-10/T-11/T-12/T-13 y tareas posteriores | ✓ conservada | Leídos los cuatro dictámenes primarios y la aclaración A. La prueba primaria acreditaba SQL durable→baja→AMQP, no el nuevo orden; la adenda subsana esa precisión. Las restantes aprobaciones se conservan sin nueva auditoría del diff de 41 archivos. No cierro T-14+, QA de fase, despliegue, ni ADR-003. | — |

La selección inicial de 158 archivos omitía los **originales terminales raíz**. Lo comuniqué y suspendí la conclusión de suficiencia hasta recibir la cápsula adicional inmutable de 15 archivos. Leí después sus cinco receipts y diez streams, comprobando hashes, tamaños, fechas, exit codes y manifiesto contra los `root-reports` ya sellados. **La subsanación es probatoria; no afirmo que los originales estuvieran disponibles en la selección inicial.**

Los dos fallos auxiliares iniciales de diagnóstico del censo siguen siendo **históricos fallidos**, tal como los describe `H/supplement-handoff.json:63`; no les atribuyo un gate verde. Los originales terminales incorporados corresponden al censo final correcto. El rojo de ReflectionClass está respaldado además por su stdout original y conservado separadamente.

Integridad comprobada antes y después:

- **158/158** archivos de la adenda, tamaños y hashes correctos; manifiesto `f783fc674dbdd46d25c9bc2cc68bb995a7c1e46fe641b913b78bc9ec0d2b2b62`.
- **15/15** originales adicionales correctos; manifiesto `f47f742e36cc9c918b1f1c77b9eb8571775103342f661777d9b78ab6c498bb19`, enlazado al anterior.
- Cinco ZIP: **185 miembros únicos cada uno**, conjuntos y hashes coincidentes con sus manifiestos externos, todos `32f798edf22b2e2385fa4846953c25814cdf79160aaa4c31d7cece8bdcd3996f`. Las 184 entradas de proyecto coinciden con `sources/` primario; el runner privado coincide con su copia primaria de evidencia.
- **80 asociaciones** de evidencia atribuida y **16 hashes loaded** contrastados sin discrepancias.
- Metadata pública SHA `ee370f27a6f50d0fe7265af3c84ac3ee847b49e3538ec5a1b865cfe1101e1d75`; reconstrucción exacta en memoria.
- Handoff SHA `7b36f76f71dc327efc816b39b019c8efc72a6bc0463c6246ff57e97c59398a58`.

La lectura semántica se centró en la variante focal, arnés pertinente, diffs/preimágenes, observer, protocolos originales, recibos, limpieza, contratos pertinentes y metadata completa. **No equiparo los 173 archivos hashados con 173 auditorías semánticas exhaustivas**, ni reaudité las 41 modificaciones primarias. La adenda no ejecuta replay ni repite el full; conserva esa limitación y no inventa cobertura porcentual.

**Gaps y escenarios de reproducción: ninguno demostrado.** Solo realicé lecturas, selección oficial sin Git y comprobaciones puras de parsing/hash/comparación en memoria. Sin aplicación, SQL, broker, Docker, navegador, servicios, escrituras ni pruebas con efectos. Root conserva fusión, publicación, ledger, medidores y cierre formal.
