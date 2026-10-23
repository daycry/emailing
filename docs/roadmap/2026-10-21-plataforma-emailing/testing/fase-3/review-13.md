# Revisión 13 — workers y recuperación de entrega

El incremento T-13 supera las cuatro lentes independientes sin defectos
graduados: conformidad, corrección, seguridad y rendimiento. Es el intento
acotado 1 de 3 de este incremento. Se utilizó el revisor genérico disponible con
runtime heredado; el tier configurado por el plugin no identifica un modelo
Claude ejecutado.

La [comprobación suplementaria](review-13-supplement.md) acredita ahora publicar
primero en RabbitMQ, tramitar después la baja pública y consumir por último:
1 prueba y 51 aserciones, revisada por A/B sin defectos. La prueba primaria
creaba primero el trabajo durable en SQL, tramitaba la baja y publicaba después
en AMQP. La aclaración de A se conserva junto a su informe original. Esta
adenda permite el cierre de los componentes T-10–T-13, sin cerrar el QA de fase.

| Lente | Critical | Important | Minor | Informe íntegro |
|---|---:|---:|---:|---|
| A — conformidad | 0 | 0 | 0 | [Informe A](review-13-A.md) y [aclaración de alcance T-10](review-13-A-clarification.md) |
| B — corrección | 0 | 0 | 0 | [Informe B](review-13-B.md) |
| C — seguridad del incremento | 0 | 0 | 0 | [Informe C](review-13-C.md) |
| D — rendimiento del incremento | 0 | 0 | 0 | [Informe D](review-13-D.md) |

Los revisores conservaron las aprobaciones y límites de las revisiones 10 y 12.
No encontraron evidencia nueva para reabrir la selección de recursos eliminados
ni el claim global indexado. Las comprobaciones del consumidor sí completan la
evidencia de borradores/campañas futuras sin transporte y de publicaciones
duplicadas que convergen a un único comienzo de transporte.

## Fuentes y pruebas finales

Se conservan el [patch exacto](review-13.patch) y su
[manifiesto de revisión](review-13.patch.json). El manifiesto también inventaría
evidencias privadas; su presencia no implica que se publiquen archivos de
trabajo, credenciales, productores o ZIP privados.

SHA-256 del patch revisado:
`97d29208f5c7fd5f57683343746d0b61b78e74728e0e084a186c39eed8410c0e`.
SHA-256 del manifiesto de las pruebas finales:
`32f798edf22b2e2385fa4846953c25814cdf79160aaa4c31d7cece8bdcd3996f`.
Ese manifiesto contiene 184 archivos del proyecto y un productor privado del
snapshot. Los cinco ZIP privados conservan exactamente sus 185 entradas; el
productor y los archivos ZIP permanecen fuera de la publicación. Se publican los
manifiestos y recibos originales seleccionados, sin reescribir sus bytes.

| Comprobación | Resultado original | Evidencia |
|---|---|---|
| Concurrencia de campañas focal | 5 pruebas / 34 aserciones; exit 0 | [Salida](raw/t13-final-gate-1-stdout.txt), [recibo](raw/t13-final-gate-1-receipt.json), [fuentes](raw/t13-final-gate-1-source-manifest.json) |
| Pint acotado | 26 archivos PHP; exit 0 | [Salida](raw/t13-final-gate-2-stdout.txt), [recibo](raw/t13-final-gate-2-receipt.json) |
| `php artisan test` | 183 pruebas / 2227 aserciones; exit 0 | [Salida completa](raw/t13-final-gate-3-stdout.txt), [recibo](raw/t13-final-gate-3-receipt.json), [fuentes](raw/t13-final-gate-3-source-manifest.json) |
| CLI Windows y replay | 8 comandos; todos exit 0 | [Recibo padre](raw/t13-final-gate-4-receipt.json), [verificación del protocolo](raw/t13-root-native-protocol-verification.json) |
| Censo final de recursos propios | 342 identidades de contenedor ausentes y 0 helpers nativos coincidentes | [Recibo padre](raw/t13-final-gate-5-receipt.json), [verificación](raw/t13-root-census-release-audit-20261023T193337629372Z.json) |

La suite final incluye los 21 casos WorkerRecovery, 20 OutboxIntegration y cinco
ConcurrentCampaign. La [comprobación de clases y casos](raw/t13-root-final-full-class-case-counts.json)
corrobora los conteos; no representa un porcentaje de cobertura de código.
Los revisores leyeron semánticamente el diff completo y su contexto pertinente;
las comprobaciones de integridad de 417 fuentes, 6455 evidencias y 23 preimágenes
no se presentan como una auditoría semántica exhaustiva de todos esos archivos.

Las pruebas reales incluyen reservas vencidas con propietario antiguo vivo,
dos consumidores, dos KILL en ventanas distintas de aceptación/persistencia,
corte SQL después del 250, rechazo y backoff acotado, fallo durable,
supresión entre cuentas, cambios de autorización, cuota al cruzar una frontera
UTC y SIGTERM del CLI. El smoke Windows conserva entrega, mensaje, generación,
intento y artefacto tras replay, con cero conexiones SMTP.

## Historial y límites

Se conservan como históricos, con sus fuentes originales, el comando focal
WorkerRecovery de 21/429, el Outbox de 20/299 y la suite que falló durante la
limpieza. Sus resultados no sustituyen a los gates finales. La
[continuidad de las dos fuentes del arnés](raw/t13-root-campaign-fixture-source-continuity.json)
documenta el cambio entre esos snapshots.

La reproducción controlada de Windows demuestra un mecanismo de archivo con
eliminación pendiente y su regresión RED→GREEN. No identifica el proceso ni el
handle del fallo histórico, cuyo error primario pudo quedar oculto por la
limpieza. Se conserva la [verificación del mecanismo](raw/t13-root-windows-delete-protocol-verification.json)
y el [dictamen del full final](raw/t13-root-campaign-protocol-full-campaign-final-frozen-20261023T190743204457Z.json).

Esta revisión no cierra el QA de fase, E2E-08/09, M-01, el despliegue operativo
del nuevo Compose, T-14–T-20 ni ADR-003. El envío externo permanece deshabilitado;
se utiliza Mailpit/log y relays controlados de pruebas. No se afirma entrega al
destinatario, SMTP exactamente una vez, reputación saludable ni ausencia de
blacklists. Los censos describen recursos propios en una observación fechada,
sin consultar ni atribuir procesos ajenos.
