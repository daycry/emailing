---
id: GOT-006
tipo: gotcha
area: testing
estado: aceptada (validada: revisión de dos lentes, 2026-10-24, intento 18)
fuente: docs/roadmap/2026-10-21-plataforma-emailing/tasks.md
fecha: 2026-10-24
---

# Separar procedencia, barreras de dominio y vida del relay

## SÃ­ntoma

Un escenario de competencia entre cuentas agota su barrera aunque el inventario
del consumidor termina correctamente. Al separar ese arranque, la recuperaciÃ³n
llega al ACK y al siguiente minuto real, pero el teardown falla por el plazo
total del relay y deja pendiente parte de su limpieza.

## Causa raÃ­z

La barrera semÃ¡ntica de 35 segundos incluÃ­a el arranque de Docker, Laravel y el
inventario completo de dependencias. Una observaciÃ³n real consumiÃ³ unos 33,7
segundos antes de publicar siquiera la identidad cargada. Ese presupuesto no
medÃ­a Ãºnicamente la etapa de dominio. La vida total del relay, limitada a 120
segundos, tambiÃ©n incluÃ­a bootstrap, alineaciÃ³n UTC y recuperaciÃ³n: expirÃ³ tras
completarse los resultados durables, sin que hubiera vencido el timeout SMTP.
El error de `relay.wait()` interrumpÃ­a despuÃ©s la limpieza de broker y base.

El lÃ­mite total de Symfony se mide desde `Process::start()`. El relay Python
termina al recibir su archivo de cierre y no aplica ese lÃ­mite por su cuenta.
Por eso pueden coexistir un `exit 0` real y una `ProcessTimedOutException`
observada al llamar a `wait()` despuÃ©s de agotar el presupuesto. Deben conservarse
ambos hechos. Otro caso reservÃ³ antes de arrancar el segundo consumidor: su
bootstrap consumiÃ³ el lease de 30 segundos y el dominio rechazÃ³ correctamente el
transporte. El locker nativo conservaba asimismo un plazo de 35 que incluÃ­a un
bootstrap observado de 42 segundos.

## QuÃ© hacer

Medir y acotar por separado el bootstrap de procedencia y la observaciÃ³n de la
etapa de dominio. En una prueba de competencia, preparar ambos consumidores antes
de permitir reservas; comprobar identidad FIFO, leases vigentes y una misma
ventana SQL UTC real. Conservar los plazos de transporte, lease y barrera de
dominio; no atrasar timestamps para fabricar concurrencia ni omitir vendor para
acelerar el inventario.

El proceso relay puede tener un presupuesto de vida del escenario que cubra la
preparaciÃ³n y el siguiente minuto real, distinto de su timeout por conexiÃ³n.
Registrar PID, comandos, fuentes, tiempos y terminales. La limpieza debe intentar
todos los recursos atribuidos y el teardown padre aunque un intento falle,
guardar los recibos en `finally` y conservar el error original. Un terminal no
capturado se declara desconocido; no se reconstruye como exit 0.

Declarar por escenario el mÃ¡ximo de consumidores y de ventanas UTC, junto con
los lÃ­mites de los procesos auxiliares. El presupuesto del relay debe cubrir
al menos la suma de esas cotas; el timeout total normal de cada consumidor es 150
segundos, mayor que bootstrap de 90 mÃ¡s etapa de 35. Los escenarios existentes requieren
mÃ¡ximos de 1, 2, 3, 4 o 7 consumidores. Registrar parÃ¡metros, fÃ³rmula, procesos
realmente iniciados y el resultado del cierre. Exigir ademÃ¡s un cierre propio
acotado a 10 segundos aunque quede margen en el presupuesto total del relay.

Cuando la alineaciÃ³n UTC ocurre despuÃ©s del bootstrap, tambiÃ©n transcurre dentro
del gate y del timeout del hijo. Componer sus lÃ­mites de preparaciÃ³n, espera UTC
y operaciÃ³n. Preparar el consumidor y el locker antes de escoger el minuto y
conceder la reserva evita consumir un lease durante la procedencia. No aumentar
por ello los 35 segundos semÃ¡nticos, los plazos SMTP ni los leases de dominio.

Para JSON parcial sigue aplicando
[GOT-003](GOT-003-barreras-de-prueba-atomicas.md): existencia de archivo no implica
contenido completo. Esta causa distinta no justifica ampliar esa barrera.

## Evidencia

El caso
`test_two_accounts_competing_for_shared_pool_cap_defer_and_resume_on_real_utc_window`
de [WorkerRecoveryTest](../../../tests/Feature/WorkerRecoveryTest.php) registra
bootstrap, presupuesto semÃ¡ntico, ausencia de reservas antes de habilitar el
consumo, dos leases vÃ¡lidas y el bucket UTC real. Conserva la competencia por el
pool, la espera hasta disponibilidad y el reenvÃ­o seguro en el minuto posterior.
El focal corregido pasÃ³ con 52 aserciones; la tanda fallida y sus cronologÃ­as se
conservan como diagnÃ³stico privado separado.

La comprobaciÃ³n controlada de cleanup reduce sÃ³lo la vida del relay en una copia
diagnÃ³stica: debe conservar `ProcessTimedOutException`, registrar PID/salida real,
completar los demÃ¡s intentos y verificar vacÃ­a la base protegida. Su fallo
esperado no se presenta como un verde de PHPUnit canÃ³nico. RevisiÃ³n independiente,
grupo completo y QA integral siguen siendo puertas separadas.

Una segunda tanda detectÃ³ los casos de reserva durante bootstrap, cierre de un
escenario de cuatro consumidores y bootstrap nativo incluido en 35 segundos.
El focal de los tres casos corregidos pasÃ³3 pruebas y 119 aserciones, con cruce de
minuto SQL real. El control posterior impide deliberadamente atender el archivo
de cierre en una copia privada: conserva el fallo de cierre a 10 segundos,
registra la terminaciÃ³n efectiva y completa broker y teardown de la base. Estos
controles no equivalen al grupo completo ni a una aprobaciÃ³n de QA o humana.
