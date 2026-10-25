---
id: ADR-008
titulo: Contexto explícito de servicios y mutex delegado por cuenta
estado: propuesta
fecha: 2026-10-25
iniciativa: mcp-google
---

# ADR-008: Contexto explícito de servicios y mutex delegado por cuenta

## Contexto

T-04 necesita demostrar autoridad y revocación mediante SQL real antes de T-05.
T-18 recibe AccountApiKey explícita y serializa mediante la cuenta; no existe
todavía un ExecutionContext común. Los servicios web Contact/Template/Import
usan AccountContext y Campaign/Suppression reciben cuenta y actor explícitos.
Cambiar auth() o session() globales para MCP introduciría autoridad ambiental.

## Decisión propuesta

ExecutionContext expresa actor, cuenta, autorización vigente y procedencia.
DelegatedContext verifica filas Sanctum de propósito MCP, cliente y grant;
AccountContext acepta ese contexto por constructor y conserva su adaptador web.
Los servicios se construyen para la invocación con ese contexto, sin bindings
globales ni suplantación del guard. Campaign/Suppression aceptan también un
contexto opcional y verifican que coincida con su cuenta/actor explícitos.

Se adelantan de T-05 únicamente cliente, grant y token persistidos, su migración
y la revocación básica: sin esas filas no es posible verificar T-04. OAuth
añadirá pendientes/códigos/canje en su propia tarea. Los grants son inmutables;
otra cuenta o techo consentido exige otro grant. La credencial MCP usa una tabla
y modelo Sanctum separados de AccountApiKey. No se construyen API keys ficticias.

Toda mutación conserva la transacción del servicio y revalida bajo el orden
cuenta → membresía → cliente → grant → token → origen. El mutex de cuenta es el
mismo de TeamService y AccountKeyService T-18. Retirar una membresía revoca sus
grants de forma terminal; volver a invitar no los reactiva. Reducir permisos
aplica la intersección vigente sin ampliar scopes consentidos. Revocar no borra
el grant ni su procedencia y no afecta a llaves de cuenta.

## Alternativas descartadas

- Suplantar auth()/session(): mezcla la sesión web con la autoridad del agente.
- DTO con scopes emitidos sin releer SQL: permite sobrevivir a la revocación.
- Esperar T-05 para toda persistencia: impide la prueba obligatoria de T-04.
- Adaptar SendingApiService con AccountApiKey sintética: inventa procedencia y
  rompe su ciclo independiente. T-10 integrará el principal común de envío y
  sus FK/outbox sin alterar el contrato REST aceptado.

## Consecuencias y comprobación

Los adaptadores futuros reciben el contexto por invocación; ningún account_id
de herramienta selecciona autoridad. Las pruebas de T-04 ejercitan los servicios
reales, intersección de scopes, cambios de membresía, llaves independientes y
procesos SQL con barreras bajo locks. Esta propuesta no declara pruebas pasadas,
revisión, QA ni herramientas T-08–T-10 implementadas.

Fuentes: [ledger T-04–T-07](../../roadmap/2026-10-24-mcp-google/tasks.md),
[contrato](../../aplicacion/mcp-contrato.md), [ADR-006](ADR-006-identidad-google-y-oauth-mcp-delegado.md).
