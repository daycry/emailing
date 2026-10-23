---
id: GOT-002
tipo: gotcha
area: testing
estado: aceptada (validada: revisión de dos lentes, 2026-10-23, intento 6)
fuente: docs/roadmap/2026-10-21-plataforma-emailing/tasks.md
fecha: 2026-10-23
---

# Limpieza atribuible de archivos temporales de QA

## Síntoma

Un finalizador declara que no quedan uploads, pero busca en un directorio
distinto del configurado. Los uploads que nunca se importan carecen de fila SQL
con su ruta. Además, una excepción en la primera limpieza impide ejecutar las
siguientes y deja fixtures o mensajes propios.

## Causa raíz

Se asumió `storage/app/private/livewire-tmp` en vez del disco y directorio
efectivos de Livewire. El propietario del archivo se reconstruía demasiado
tarde, después de retirar fixtures, y las operaciones independientes compartían
una única salida ante errores.

## Qué hacer

Derivar la raíz de la configuración real de cada perfil. Registrar al subir
la referencia exacta, usuario, cuenta, UUID y hash del contenido; comprobar
firma, ruta canónica y atribución antes de borrar. Conservar los archivos sin
atribución comprobada. Retirar temporales y sidecars propios incluso si ya se
eliminaron las filas SQL o se revocaron permisos.

Ejecutar cada limpieza independiente aunque otra falle; comunicar todos los
errores al final. Probar rutas de escape y enlaces reales, y comprobar los
restos registrados de la tanda en vez de afirmar cero archivos globales.

## Evidencia

Los informes [B-03](../../roadmap/2026-10-21-plataforma-emailing/testing/fase-2/raw/b03-fix/report.md)
y [B-04](../../roadmap/2026-10-21-plataforma-emailing/testing/fase-2/raw/b04-fix/report.md)
conservan el fallo inicial, la limpieza real en ambos perfiles y siete
escenarios de continuidad ante errores. El
[recibo de QA2](../../roadmap/2026-10-21-plataforma-emailing/testing/fase-2/raw/teardown-attempt-2.json)
acredita cero temporales propios registrados restantes; los no atribuibles se
preservaron.
