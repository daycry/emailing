# Borrador para la retrospectiva de proveedores-email

Este documento prepara la revision humana. No es `retro.md`, no alimenta CALIBRATION y no abre la puerta formal de cierre.

## Datos que se pueden verificar

| Concepto | Estimado | Real observado |
|---|---:|---|
| Trabajo humano equivalente |116h base;139,2h con margen (incluye C07; original104h) |No medido |
| Tokens |1024000base;1228800con margen (incluye C07) |No medidos |
| Coste equivalente |6960EUR con margen, mas tokens por verificar |No medido |
| Revision independiente |Hasta3intentos |3intentos, ultimo sin gaps |
| QA de navegador |Hasta3intentos |QA1:2/6, QA2:5/6, QA3:6/6; originales conservados |
| Manual |C07 adicional12h/160000tokens |57paginas,8Mermaid; revision documental2 sin gaps; QA independiente verde,57/57paginas revisadas |
| Proveedor real |M-01 |Pendiente de configuracion y cualificacion privada |

No hay desviacion porcentual calculable sin horas/tokens/coste reales. Los marcadores declararon fuente estimada por falta de telemetria; el reloj de herramientas no se convierte en horas IA ni gasto.

## Hechos tecnicos del ciclo

- Una prueba de cable reprodujo que Symfony8.1.7 podia eludir requireTls al caer deEHLO aHELO; se cerro el fallback inseguro antes de AUTH/sobre/DATA.
- AUTH535 perdia su rechazo terminal durante negociacion; se conserva el ultimo negativo sin impedir un mecanismo alternativo que autentique correctamente.
- Un callback entre requeue y nuevo inicio durable podia permitir otro envio. El mutex de cuenta ahora comprueba feedback de todos los intentos antes del inicio y antes de permitir mas requeue.
- QA1/2 detectaron selectores y sincronizacion del arnes; se corrigieron sin borrar escenarios, subir timeout o activar retries. QA3 valida tambien teclado y movil.
- Guzzle8 y TLS1.3 exigieron corregir el seam/oraculo de las pruebas, manteniendo verificacion de certificado y la evidencia de ceroHTTP/credenciales ante CArechazada.

Los detalles y originales estan en [testing/acceptance.md](testing/acceptance.md) y los informes enlazados. Estos hechos no sustituyen la valoracion ni las causas de desviacion del usuario.

## Respuestas humanas que faltan

1. Valoracion del resultado y cambios que se harian en el proximo ciclo.
2. Si se dispone de tiempo humano o coste real, su medida y las causas de cualquier desviacion; si no, mantener no medido.

Tras recibirlas se puede redactar `retro.md`, incorporar la fila honesta a CALIBRATION y ejecutar retro-gate. Mientras tanto la spec permanece aprobada y el cierre formal pendiente, aunque la implementacion y la QA local se acepten tecnicamente.
