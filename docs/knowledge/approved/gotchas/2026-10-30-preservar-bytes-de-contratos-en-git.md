---
area: delivery
category: GOTCHA
estado: aprobado
evidencia: validated_case
fecha: 2026-10-30
fuentes:
  - .gitattributes
  - tests/packaging/test_pinned_contract_checkouts.py
  - app/Services/Mcp/ContractArguments.php
  - docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t10/packaging/report.md
  - docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t10/packaging/evidence.json
  - docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t10/review-3/report.md
id: emailing.GOT-016
iniciativa: 2026-10-24-mcp-google
proposed_by: debug-root-cause
tags:
  - area:delivery
  - tool:git
  - stack:php
tipo: gotcha
titulo: Conservar los bytes publicados de contratos fijados por hash en Git
version: 1
---
La próxima vez que veas un error de integridad de un contrato que funciona en
el árbol de trabajo y falla tras un checkout, compara el objeto Git y sus
atributos antes de cambiar el hash exigido o relajar el guard.

## Síntoma y causa probada

El catálogo MCP tenía bytes CRLF con hash publicado válido en el working tree,
pero Git guardaba LF. La clase real rechazó el objeto LF por integridad y cargó
los bytes publicados. Siete inputs JSON estaban fijados a CRLF y la spec REST
a LF: ni un checkout global LF ni una conversión global CRLF conservaban los ocho.
El problema era la transformación de empaquetado, no JSON ni el guard PHP.

## Qué hacer

Declarar atributos -text sólo para las ocho rutas fijadas, añadir explícitamente
sus bytes aprobados y conservar todos los hashes. Verificar atributos del origen
seleccionado, identidad del blob y filtros autocrlf=false/true/input. Consultar
atributos de HEAD, no sólo del working tree, evita que un arreglo local aún no
comiteado oculte el fallo del artefacto publicado.

## Evidencia y límites

La regresión final de tres tests produce36 fallos de subcasos contra el HEAD
anterior4ad549b con los atributos locales ya corregidos. Los tres pasan en índice
y sobre el commit6ff7858. A/B/C independientes verifican los14 objetos revisados
y los7 JSON equivalentes salvo EOL sin gaps nuevos. La suite PHP485/13.537 conserva
sus415 fuentes byte a byte; se añade sólo esta prueba Python. Las sondas de clase
LinuxPHP8.4.27 reproducen LF rechazado/bytes publicados aceptados sin Laravel/DB.
Esto no acredita el perfil Docker de aplicación, Google o clientes MCP reales.
