---
id: ADR-010
title: Consentimiento y auditoría MCP con contexto explícito
status: proposed
date: 2026-10-25
---

T-07 necesita auditar una denegación antes de elegir cuenta. `AuditEvent` exige cuenta y la auditoría de identidad no representa clientes/grants. Se añade `mcp_audit_events` con actor, cliente, grant, operación, resultado y UUID de correlación; cuenta nullable significa que todavía no se eligió una. No concede autoridad ni almacena payloads, códigos, tokens o respuestas del proveedor. Las referencias cliente/grant se conservan como identificadores sin FK para permitir la retención independiente de auditoría de 365 días y tombstones de autorización. Los sucesos de negocio conservan también su auditoría de dominio.

El contexto delegado genera una correlación por llamada. El cursor sigue vinculado a recurso, cuenta, actor, procedencia completa de autoridad, rol y scopes; excluye únicamente esa correlación variable. Cada llamada y mutación continúa revalidando la membresía y el grant SQL bajo el orden común de locks.

Como prerrequisito explícito de E2E-04/05 se extraen de T-08 exactamente `lists_get` y `lists_create`, usando `ContactService` y sus transacciones. Son herramientas productivas; el resto del catálogo no se anuncia implementado. T-08 conserva sus 20 operaciones y su verificación completa, sin volver a atribuir estas dos implementaciones a una tarea ya aceptada. El contrato JSON aceptado es la fuente de schemas/descriptores.

Las conexiones se consultan por su propietario, independientemente del selector web. La revocación es POST con CSRF, afecta sólo el grant propio y corta sus tokens y sesiones. El consentimiento usa controles HTML nativos y sólo solicitudes OAuth persistidas, validadas y vinculadas al navegador pueden retomarse después del login local o Google. Los fixtures de proveedor y cliente son de laboratorio y no acreditan Google real, TLS ni clientes externos.

Procedencia de criterios: mapas `api-contract`, `backend-practices`, `delivery-practices`, `frontend-quality` y `stack-practices` de custom-agents 2.0.0; referencias shared-boundaries, contracts-data/service-boundaries, delivery-tools, interaction-performance y criterios PHP genéricos de php-codeigniter. La actualización del kit no modifica el contrato ni las estimaciones y puertas aprobadas.
