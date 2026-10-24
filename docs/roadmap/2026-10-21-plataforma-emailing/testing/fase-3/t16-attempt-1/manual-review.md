# M-01 — vistas para revisión humana

Estado: **pendiente de una persona**. Estas capturas proceden de Chromium real del laboratorio local de T-16. No constituyen aprobación visual automática ni cierre global del roadmap.

Requisito: [test-plan.md, M-01](../../../test-plan.md#manuales-m--revisión-por-una-persona), sección «Claridad y adaptación del panel español»: «Una persona distingue cuenta activa, permisos y estados sin explicación técnica ni métricas ficticias».

| Vista comprobada | Evidencia |
|---|---|
| Escritorio: cuenta A, estado suprimido y detalle | [Detalle](screenshots/native-activity-suppressed-detail.png) |
| Escritorio: cuenta B, solo lectura y aceptación SMTP | [Solo lectura](screenshots/native-activity-accepted-read-only.png) |
| Móvil de 390 × 844, selector y detalle | [Móvil](screenshots/native-activity-mobile.png) |
| Filtro sin resultados y denominador sin muestra | [Vacío](screenshots/native-activity-empty.png) |
| Fechas inválidas y mensaje de corrección | [Error](screenshots/native-activity-filter-error.png) |
| Formulario de borrador y revisión | [Borrador](screenshots/native-campaign-draft.png) |

Checklist para una persona:

- [ ] Distingo claramente la cuenta activa y el permiso de administración/solo lectura.
- [ ] Entiendo que «Aceptado por SMTP» no acredita entrega final y que «Suprimido» no se envió.
- [ ] Los textos españoles, mensajes de error y estados vacíos son claros.
- [ ] Puedo leer y usar el formulario, filtros y selector tanto en móvil como en escritorio.
- [ ] El contraste, tamaño del texto y navegación resultan cómodos.

Vistas adicionales reales: [éxito de baja pública](screenshots/native-public-unsubscribe-success.png), [cuenta B independiente](screenshots/native-unsubscribe-account-b-independent.png), [fallo SMTP con intento conservado](screenshots/native-real-failed-attempt.png) y [límites del feedback](screenshots/native-feedback-limits.png). La vista de ambigüedad sigue pendiente por la carrera del driver al cambiar de cuenta.

La revisión de la captura de error detectó el texto literal `validation.before_or_equal`. Es un defecto de traducción pendiente de corregir. La programación ordinaria también rechazó una confirmación marcada al cambiar la fecha en el mismo envío; véanse [captura de autorización](screenshots/native-campaign-authorization-error.png) y [observaciones reales](raw/finding-observations.json).

La ejecución automática 3/3 terminó NO-VERDE: 6 casos pasados, 3 fallidos y 1 flaky. El laboratorio se cerró con recibos; estas capturas conservan las vistas revisables. Este documento no aprueba M-01. Véase el [informe final](report.md).
