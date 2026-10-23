# Revisión de dos lentes — intento 9 · T-11

**Corrección necesaria: 0 Critical / 1 Important / 0 Minor.**
Primera revisión de tres del incremento de campañas, sin reiniciar contador.
Lentes A+B+D frescas sobre 18 archivos desde `5a1a52f`. C no activada;
D activada por la migración. Alcance exit 0, sin fuera de alcance, avisos,
información oculta ni exclusiones de usuario.

| Lente | Dictamen | Evidencia |
|---|---|---|
| A | 0 gaps nuevos de requisitos; pendientes integrales explícitos | [Tabla completa](attempt-9-lens-a.md) |
| B | 1 Important, B-01 | [Tabla completa](attempt-9-lens-b.md) |
| D | 0 hallazgos de rendimiento | [Tabla completa](attempt-9-lens-d.md) |

| # | Grado | Gap / escenario | Tarea | Dictamen |
|---|---|---|---|---|
| B-01 | Important | Un borrador conserva un ID de lista/contacto eliminado; el selector muestra solo activos y no permite retirar el ID invisible. Guardar/revisar rechaza el recurso y la UI no permite continuar. | T-11 | Confirmado por root; corrección autorizada y pendiente |

Evidencia de B-01: `resources/views/livewire/campaigns.blade.php:43,47`,
`app/Livewire/Campaigns.php:89,90,185,186` y
`app/Services/CampaignService.php:84,85`. La lectura conjunta confirma el
escenario sin exigir un cambio al contrato de validación de recursos.
La corrección debe permitir retirar explícitamente la selección y conservar
los controles de cuenta/scopes, versión y cambios sin guardar. No se autoriza
eliminar audiencia silenciosamente ni ampliar recursos autorizados.

[Patch completo](attempt-9.patch) y [manifest](attempt-9.patch.json).
SHA256 `921f5f99495aa2f41a656111bd70b8f57e5856672219e2036be7e9eab9627ca2`.
Root verificó 107 fuentes, cinco preimágenes, 89 evidencias y patch: 202 rutas
únicas sin discrepancias antes de añadir esta traza. A efectuó 220 checks por
incluir también los 18 archivos modificados ya contenidos en ese conjunto.
Las 16 fuentes finales, 22 recibos y 44 streams se contrastaron con ZIP/índice.
El ZIP tiene 16 fuentes y su manifest embebido, no 17 fuentes de aplicación.
Los fallos originales mantienen su procedencia; el patch normaliza LF para
lectura, mientras fuentes/evidencia originales conservan sus bytes y hashes.

Verificación previa: [campañas 25/247, plantillas 20/185, Feature 133/1404,
Pint acotado y build](t11-verification.md). No sustituyen QA de navegador,
M-01 ni recorrido real del worker. Pint global anterior sigue exit 1 por tres
helpers activos y nueve copias privadas de QA; pendiente de T-20.

D repitió un benchmark puro con las clases congeladas: 100/200/10.000
destinatarios, 0,1591008 / 0,1730443 / 1,3138421 s y pico 10 MiB. No reemplaza
el recibo original 0,2512206 / 0,2764786 / 3,9095198 s ni mide SQL/SMTP/SLA.

La Lente A se interrumpió una vez por capacidad temporal del modelo y se
reanudó en el mismo agente/snapshot, sin cambiar modelo ni intento. Todos
los dictámenes finales están presentes. Revisores genéricos porque el runtime
no permite Agent(reviewer); tier resuelto opus/high por frontmatter, runtime
heredado, sin atribuir ejecución Claude.

Ventana 2026-10-23T08:31:32Z–2026-10-23T08:48:37Z; reloj 17m.
Tokens/euros/horas IA reales NULL. Jira sin opt-in, sin publicación externa.
T-10/T-11 y sus criterios integrales siguen abiertos, total 9/20, ADR-003
propuesta. Siguiente paso: B-01 fix1 y segunda revisión del mismo incremento,
traspasando completas estas tres tablas y pendientes.
