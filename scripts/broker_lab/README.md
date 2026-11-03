# Herramientas de carga del laboratorio

Desde la raíz del repositorio:

```powershell
python scripts/broker_lab/regression.py
python scripts/broker_lab/load.py
python scripts/broker_lab/batch.py
```

Sin `--execute` sólo muestran el plan. La
[guía completa](../../docs/desarrollo/carga-broker.md) explica instalación,
prerrequisitos, ventana SQL exclusiva, matriz, métricas, archivos, limpieza y
límites de aceptación. El supervisor real requiere Windows.

| Módulo | Responsabilidad |
|---|---|
| `regression.py` | Suite completa, JUnit y recibos para habilitar carga. |
| `docker_scope.py`, `docker_scope.php` | Atribución por ejecución y reconciliación de contenedores de la suite. |
| `load.py` | Una ronda con Emailing real y aplicación mínima B. |
| `batch.py` | Matriz secuencial de 52 rondas; sin reintentos implícitos. |
| `runtime.py` | Procesos propios, recibos y topología AMQP atribuida. |
| `resources.py` | Muestreo de workers propios y dos servicios seleccionados. |
| `metrics.py` | Contratos y reconciliación puros, percentiles y contadores. |
| `inventory.py` | Inventario cerrado de fuentes; excluye configuración privada. |
| `emailing_action.php` | Operaciones de dominio A y guardas SQL. |
| `protocol_event.php` | Sobre canónico B y efecto deduplicado durable. |
| `protocol_worker.php` | Consumidor B con ack manual. |
| `protocol_publisher.php` | Productor B limitado y confirmaciones. |

Pruebas de contratos en [`tests/broker_lab`](../../tests/broker_lab) y
[`BrokerProtocolTest.php`](../../tests/Unit/BrokerProtocolTest.php). No confundir
sus resultados con la matriz integrada ni con restauración aceptada.
