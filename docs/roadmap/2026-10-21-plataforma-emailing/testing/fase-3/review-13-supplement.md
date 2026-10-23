# Adenda de revisión 13 — baja posterior a publicar en RabbitMQ

La comprobación suplementaria pasa **1 prueba y 51 aserciones**, exit 0. Dos
revisores frescos, [A](review-13-addendum-A.md) y [B](review-13-addendum-B.md),
la consideran suficiente para el pendiente literal de T-10. Ambos informan
0 Critical, 0 Important y 0 Minor. Se conserva el intento 1/3 y los cuatro
dictámenes primarios; no hay corrección del runtime ni repetición de su full.

| Paso observado | Resultado | Original |
|---|---|---|
| Publicación de los dos eventos | Dos eventos `published`, ready 2, consumidores 0; mismo email en cuentas diferentes | [Antes de la baja](raw/t13-supplement-queue-before-unsubscribe.json) |
| Baja pública de A | POST 200; ready 2, ambas entregas pendientes y ningún MailMessage | [Solicitud sin capability en claro](raw/t13-supplement-unsubscribe-request.json), [respuesta](raw/t13-supplement-unsubscribe-response.json) |
| Consumo posterior | A suprimida, 0 intentos y sin mensaje; B aceptada, 1 intento y un mensaje de B | [Después de los workers](raw/t13-supplement-after-workers.json), [estado SQL](raw/t13-supplement-sql-final.json) |
| Envelope y relay | Un único MAIL FROM recibido: `second@example.test`; un DATA aceptado y 250 | [Envelope observado](raw/t13-supplement-envelope-observer.jsonl.txt), [relay](raw/t13-supplement-relay.jsonl.txt) |
| Asentamiento | ACK de ambos trabajos, ready 0, consumidores 0, fallos vacíos y `basic_get` vacío | [ACK A](raw/t13-supplement-acked-account0.json), [ACK B](raw/t13-supplement-acked-account1.json), [resultado final](raw/t13-supplement-after-workers.json) |
| Ejecución real | 1 passed / 51 assertions; 22,86 s; stderr vacío | [stdout](raw/t13-supplement-green-stdout.txt), [recibo](raw/t13-supplement-green-receipt.json) |

Los consumidores se ejecutan secuencialmente, uno por entrega, en Linux. Los
[loaded de A](raw/t13-supplement-loaded-account0.json) y
[B](raw/t13-supplement-loaded-account1.json) contienen 16 hashes de ocho clases,
coincidentes con el runtime primario 32f. Esta adenda prueba el orden concreto;
la concurrencia, consumo continuo y replay conservan sus pruebas primarias.

El observador privado [publicado como texto](raw/t13-supplement-envelope-observer.py.txt)
envuelve el relay público intacto y verifica su hash. Solo observa su llamada
`fact(mail)` y la dirección sintética de MAIL FROM, sin AUTH ni cuerpo. No se
infiere el envelope a partir del campo SQL. Se conservan la
[variante ejecutada](raw/t13-supplement-fixture.php.txt), su
[preimagen](raw/t13-supplement-fixture-preimage.php.txt), el
[diff](raw/t13-supplement-fixture.diff.txt) y su
[manifiesto privado de fuentes](raw/t13-supplement-private-source-manifest.json).
Las extensiones `.txt` preservan los bytes como evidencia y evitan incorporarla
a la suite normal. El caso se incorporará a la validación integral de T-16.

El primer intento falló al comprobar el loaded de A porque faltaba importar
`ReflectionClass`: [stdout original rojo](raw/t13-supplement-red-stdout.txt),
exit 2 y 14 aserciones. No acredita ejecución de B ni resultados finales. La
variante corregida conserva el mismo runtime185; la diferencia de fixture,
normalizando el token de ejecución, es esa importación. No es un fallo nuevo
del código de envío.

La [adenda sellada](raw/t13-addendum-manifest.json) contiene 158 archivos. Su
selección inicial omitía los originales terminales raíz; se añadió una
[cápsula separada de 15 originales](raw/t13-addendum-terminals-manifest.json),
vinculada por hash, antes de aprobar suficiencia. Ambos revisores comprobaron
las dos cápsulas antes y después. Los resúmenes hashados no sustituyeron a leer
los originales. Se publica una selección inspeccionada, con ZIP, requests e
inputs de workers conservados privadamente.

El [censo final](raw/t13-supplement-census.json) acredita tres contenedores y dos
relays propios ausentes, y topologías eliminadas. El
[guard SQL](raw/t13-supplement-database-clean.json) registra exclusivamente la
base y usuario testing esperados, con la única tabla existente `migrations:0`.
Las tablas de aplicación ya se habían retirado por el aislamiento de pruebas;
no se presenta como un conteo de tablas todavía existentes. Los dos fallos
iniciales del auxiliar de censo permanecen como diagnósticos históricos fallidos.

La [metadata revisada](raw/t13-addendum-publication-metadata.patch) extiende a
Markdown la política `-text -whitespace` de artefactos raw y la declara en T-13.
Una comprobación del índice detectó conversión CRLF/LF del handoff; se corrigió
su preservación antes del commit. Los originales raw se verifican como bytes,
sin normalizar ni reetiquetar sus hashes.

El [handoff](raw/t13-supplement-handoff.json) libera fuentes y base de pruebas,
con todos los handles terminales. No se repitió el full: los 185 archivos del
manifiesto conservan sus bytes iguales a 32f. Esto no cierra QA de fase, M-01,
despliegue, T-14–T-20 o ADR-003; no acredita SMTP externo, entrega al destinatario
ni reputación de la IP.
