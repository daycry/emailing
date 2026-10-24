# Revisión 15 — actividad y métricas

T-14 cierra como componente revisado tras corregir A-01, sin defectos
Critical, Important ni Minor pendientes. Es el segundo intento acotado de
T14. El primero encontró un texto que deducía ausencia de transporte a partir
de una página sin intentos vinculados. La vista ahora describe únicamente
la página consultada, sin contradecir los comienzos históricos disponibles.

| Lente independiente | Critical | Important | Minor | Informe íntegro |
|---|---:|---:|---:|---|
| A — requisitos | 0 | 0 | 0 | [Informe A](review-15-A.md) |
| B — corrección | 0 | 0 | 0 | [Informe B](review-15-B.md) |
| D — rendimiento | 0 | 0 | 0 | [Informe D](review-15-D.md) |

Se utilizaron revisores genéricos con contexto fresco y runtime heredado.
El tier declarado por el plugin no identifica un runtime Claude ejecutado.
La selección oficial activó D por la migración; C no se activó. El scope
incluyó los 24 archivos sin exclusiones efectivas ni avisos. Los veredictos
anteriores completos se trasladaron a cada lente. No se reabrió ninguna
aprobación sin evidencia nueva ni se aceptó deuda.

## Fuentes y trazabilidad

Base: `defc14eb7ad40ef1b8c651a36d338a8365d75669`.
El [patch revisado](review-15.patch) y su [índice público](review-15.patch.json)
conservan la fotografía anterior al cierre documental:

```text
patch: 0d9e938a4d7934fecee6c1b5033b6cf410bf83f284568f2c0c273fa5affffa29
sello privado de revisión: a0889303a1d7ba363bc473eecaea7e05f26a30c0dc8ea52f6863eda760a39da4
manifiesto de fuentes finales: 30d3025450edd434386fb0a045f45cf5cd95a92e1f010e5fd033fbd86f8e594f
```

Los ZIP privados contienen 196 entradas: 195 fuentes de producto y un
productor auxiliar privado. Se contrastaron fuentes, recibos y streams;
no cambiaron las fuentes durante los seis gates finales. Root verificó
la integridad de las 4.420 entradas de la cápsula tras recibir los informes.
A declara 4.419/4.420 al omitir voluntariamente `.env.example`; B y D
verificaron las 4.420. Inventarios y hashes no equivalen a lectura semántica
de todas las dependencias o artefactos: cada informe detalla su alcance.

Los cambios posteriores a esta fotografía son el cierre del ledger,
documentación y publicación de evidencia; el código probado se conserva.
Los ZIP, productores, fuentes auxiliares, credenciales y el resto de la
evidencia privada permanecen fuera del repositorio.

## Gates finales

| Comando o comprobación | Resultado | Evidencia pública seleccionada |
|---|---|---|
| Pint focal `--test` | Exit0 | [Salida](raw/t14-pint-stdout.txt), [recibo](raw/t14-pint-receipt.json) |
| `npm.cmd run build` | Exit0 | [Salida](raw/t14-build-stdout.txt), [recibo](raw/t14-build-receipt.json) |
| `php artisan test --filter=ActivityTest` | 20 pruebas / 237 aserciones | [Salida](raw/t14-activity-stdout.txt), [recibo](raw/t14-activity-receipt.json), [fuentes](raw/t14-activity-source-manifest.json) |
| Migración incremental down/up, filtro literal de OutboxIntegrationTest | 1 prueba / 15 aserciones | [Salida](raw/t14-migration-stdout.txt), [recibo](raw/t14-migration-receipt.json) |
| `php artisan test` | 203 pruebas / 2.466 aserciones | [Salida](raw/t14-full-stdout.txt), [recibo](raw/t14-full-receipt.json), [fuentes](raw/t14-full-source-manifest.json) |
| Censo de recursos propios y guard SQL | Exit0, alcance fix1 | Recibos y procedencia privados contrastados por root y las lentes; límites descritos abajo |

Los streams seleccionados se copian sin alterar sus bytes; stderr está vacío
y su hash figura en cada recibo. Los manifiestos incluyen rutas relativas y
hashes, sin publicar el código del productor privado. La suite completa
conserva 18 clases; los recibos verdes respaldan los escenarios descritos,
sin probar cobertura de código o toda la conducta posible.

## A-01: causalidad y regresión

Con la vista anterior, el nuevo caso histórico falla al excluir la frase
incorrecta: **1 fallo / 7 aserciones** ([recibo rojo](raw/t14-causal-red-receipt.json)).
El stdout fallido completo se conserva privado; su hash original está en el
recibo. No se atribuyen al rojo los escenarios que no llegó a ejecutar.

La vista corregida pasa **1 prueba / 25 aserciones**
([salida verde](raw/t14-causal-green-stdout.txt), [recibo](raw/t14-causal-green-receipt.json)).
El verde conserva el prefijo causal y añade después el caso de segunda página.
La regresión se repite en Activity y en el full finales, comprobando:

- Dos entregas históricas, aceptada y ambigua, con dos comienzos y cero
  asociaciones recuperables tras un down/up real con puntero compartido.
- Una entrega pendiente con cero comienzos reales.
- Una entrega procesada mediante log, con total uno y cero filas visibles
  en la segunda página.

Los comienzos históricos son datos de fixture, no conexiones SMTP observadas.
Los ensayos SMTP controlados conservan rechazado/rechazado/aceptado para una
misma entrega, respuesta final perdida después de DATA y cero conexiones
para pruebas bloqueadas/suprimidas. Aceptación SMTP no acredita entrega al
buzón ni exactly-once.

## Límites y continuación

El censo comprobó únicamente recursos atribuidos a los cinco runs fix1:
46 nombres propios de contenedores ausentes, 42 entidades de broker ausentes
mediante 84 consultas pasivas y ninguna coincidencia de proceso nativo
propio. La identidad SQL de pruebas conservaba solo `migrations: 0`.
Los identificadores ajenos documentados se excluyeron de las consultas.
No es inventario global del host ni garantía de liveness posterior.

EXPLAIN y los conteos 6/6 del fixture de 601 entregas y 1.202 mensajes
son evidencia acotada de consultas y planes; no son un benchmark de capacidad.
Feedback externo, reputación/blacklist y entrega al buzón continúan
desconocidos en el laboratorio log/Mailpit. El envío externo sigue deshabilitado.

La revisión15 se midió del 2026-10-24 00:34:32Z al 00:48:09Z: **CLOCK14m**.
Tokens reales, coste y horas IA permanecen desconocidos, sin convertir
duración de reloj en consumo medido.

El roadmap queda en **14/20 componentes cerrados**. T15 Cronicle, T16 QA
integral de envío y navegador/E2E-08/09/M-01, T17/T18 API y claves,
T19 carga/restauración y T20 despliegue/cierre mantienen su alcance pendiente.
ADR-003 permanece propuesta hasta acreditar sus invariantes completos.
