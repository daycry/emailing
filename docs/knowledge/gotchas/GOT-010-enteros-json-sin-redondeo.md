---
id: GOT-010
tipo: gotcha
area: backend
estado: aceptada (validada: revisión de dos lentes, 2026-10-25, intento 23)
fuente: docs/roadmap/2026-10-21-plataforma-emailing/tasks.md
fecha: 2026-10-24
---

# Validar la integralidad antes de convertir números JSON

## Síntoma y causa

`json_decode` convierte `9007199254740990.1` a un double aparentemente entero.
Comprobar después `floor`, rango o un cast acepta una fracción prohibida por
el contrato. El problema ocurre antes de buscar el remitente y también puede
alterar el fingerprint de idempotencia.

## Qué hacer

El lexer conserva los lexemas decimales/exponenciales como un valor interno
tipado. ApiJsonNumber verifica dígitos, escala, ceros finales y rango exactos.
Acota el exponente por longitud antes de convertirlo; nunca construye una
potencia arbitraria ni expande una cadena de ese tamaño. Strings y objetos
del cliente conservan sus tipos y no pueden crear ese marcador interno.

## Evidencia

`ApiJsonNumberTest` compara 142 casos con un oráculo independiente Python
Decimal y comprueba strings, escapes, objetos y un coeficiente de 2001 dígitos
que normaliza a 1. `SendingApiTest` comprueba rechazo HTTP de fracciones que
redondeaban y replay de las representaciones integrales equivalentes.
El diagnóstico rojo exacto se conserva privado. Sigue propuesta; el ledger
T-18 conserva los resultados reales sin afirmar revisión o QA completadas.
