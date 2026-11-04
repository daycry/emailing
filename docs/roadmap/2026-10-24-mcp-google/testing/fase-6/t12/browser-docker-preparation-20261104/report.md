# Preparación del perfil Docker de navegador

Fecha:4 de noviembre de2026. Rama:`feature/t12-browser-docker`.

Este informe conserva la preparación inicial de13 casos y sus hashes históricos.
El controlador posterior ya está implementado y tiene su
[cualificación propia](../browser-docker-controller-20261104/report.md); no se
reescriben aquí resultados previos como si correspondieran al código actual.

Se implementan el documento Compose, las fronteras PHP previas a bootstrap y el
transporte Node de fixtures para ejecutar los nueve escenarios existentes con
Nginx/FPM. No se ha ejecutado Docker, SQL, navegador ni Google real para esta
preparación. El controlador de ejecución sigue pendiente.

| Comprobación ejecutada | Resultado y límite |
|---|---|
| Unittest |13 casos locales Python, cero fallos/errores. |
| Frontera PHP pura |40 comprobaciones sin Laravel, configuración ni SQL. Una prueba Python las ejecuta. |
| Transporte Node |8 tests con procesos simulados y archivos temporales sintéticos. Otra prueba Python los ejecuta. |
| Sintaxis |4 archivos PHP y4 módulos Node, exit0. |
| Pint acotado |4 archivos PHP, exit0; no es un resultado global del producto. |
| Escenarios originales |9 nombres de E2E iguales a la base; ninguna línea de aserción modificada. No acredita ejecución de esos E2E. |
| Alcance y ledger |Herramientas oficiales exit0; sin criterios alterados, exclusiones ni fuera de alcance. Los avisos heredados del ledger se conservan. |
| Fuentes originales |538 hashes de la regresión activa sin cambios; esa ejecución sigue siendo independiente. |

Los40 checks PHP y ocho tests Node están anidados dentro de los13 casos Python;
no se suman de nuevo al total. [qualification.public.json](qualification.public.json)
identifica las13 fuentes actuales, sus hashes y los códigos de comandos.

Una prueba nueva detectó que datos Docker con el selector de perfil omitido
podían elegir el transporte PHP del host. El rojo observado tuvo ocho tests
Node, un fallo, cero skips y exit1; usó un runner simulado, sin ejecutar ese
transporte. Se exige ahora `docker` explícito ante un ID o imagen Docker. La
suite local posterior vuelve a pasar13 casos, incluidos los ocho Node.
[profile-selection-red.public.json](profile-selection-red.public.json) conserva
la proyección del rojo y el enlace al verde.

[qualification-initial.public.json](qualification-initial.public.json) conserva
la cualificación anterior al añadido de esas negativas y su corrección. Cambian
sólo el módulo de transporte y su test Node. Sus hashes iniciales son históricos;
la cualificación actual identifica la revisión corregida. No se borran resultados
ni se presenta el primer verde como prueba de una guarda que todavía faltaba.

La [guía](../../../../../../desarrollo/navegador-google-docker.md) detalla archivos,
puertos, configuración y fronteras. Faltan controlador completo, pruebas de su
contención/limpieza, revisión independiente, regresión del checkout integrado
exacto, nueve E2E reales en cada perfil, estabilidad y QA. T-12 sigue abierta;
no hay cualificación Docker, TLS, OAuth Google externo o clientes nativos.
