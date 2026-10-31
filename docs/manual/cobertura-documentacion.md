# Cobertura de la documentación vigente

**Última actualización: 2026-10-31.** Inventario de funciones, pantallas, configuración,
servicios e interfaces del producto actual. Cada fila enlaza su documento dueño
y la explicación autosuficiente del [manual](guia-completa.md). Los esquemas y
la evidencia histórica se conservan sin reescribir. Esta matriz acredita cobertura
documental, no ejecución nueva de instalación ni aceptación de pendientes.

## Cómo interpretar los estados

**Implementado** significa que existe el comportamiento descrito. **Condicional**
indica que necesita configuración/permiso vigente. **Procedimiento propuesto**
identifica una receta derivada de archivos públicos que todavía requiere
validación operativa. **Pendiente real** conserva cualificación humana, carga,
restauración global o integración real externa. Un pendiente documentado no es
un hueco de documentación ni se declara resuelto por escribir una receta.

El universo funcional se obtiene de rutas propias, formularios Livewire/vistas,
servicios de dominio, comandos de aplicación, `.env.example`, configuración
propia y Compose. Las opciones genéricas de dependencias que el producto no
expone no se presentan como funciones de Emailing. No se inventan pantalla de
cancelación, importador MCP, administrador SQL de producto ni monitor DNSBL.

## Pantallas y tareas de negocio

| Función o pantalla | Documento dueño | Sección del manual | Estado real |
|---|---|---|---|
| Instalación nueva Docker y primer registro | [Desarrollo](../aplicacion/desarrollo.md) | [Docker desde cero](guia-completa.md#instalación-docker-desde-cero) | Procedimiento público; puesta en operación T20 pendiente |
| Instalación Laragon persistente | [Desarrollo](../aplicacion/desarrollo.md) | [Windows nativo](guia-completa.md#instalación-nativa-en-windows-con-laragon) | Procedimiento; perfiles técnicos probados, destino pendiente |
| TLS, certificados y autoridad | [Operación MCP](../aplicacion/mcp-operacion.md) | [HTTPS y Linux](guia-completa.md#https-google-mcp-y-traslado-a-linux) | Laboratorio probado; dominio privado requiere adaptación |
| Linux y aprovisionamiento PowerShell 7 | [Desarrollo](../aplicacion/desarrollo.md) | [Traslado Linux](guia-completa.md#llevarlo-a-un-servidor-linux) | Procedimiento; no instalador Bash general |
| Primer recorrido log/Mailpit | [Plantillas](../aplicacion/plantillas-y-transporte.md) | [Primer día](guia-completa.md#primer-día) | Implementado, sin entrega externa |
| Registro, login, logout y reset | [Cuentas](../aplicacion/cuentas-y-equipo.md) | [Acceso](guia-completa.md#acceso-cuentas-y-equipo) | Implementado; registro configurable |
| Panel y selección de cuenta | [Cuentas](../aplicacion/cuentas-y-equipo.md) | [Acceso](guia-completa.md#acceso-cuentas-y-equipo) | Implementado |
| Teammates, roles y 14 scopes | [Arquitectura](../aplicacion/arquitectura.md) | [Roles](guia-completa.md#roles-y-permisos-que-existen) | Implementado |
| Invitación, aceptación, reenvío y revocación | [Cuentas](../aplicacion/cuentas-y-equipo.md) | [Invitaciones](guia-completa.md#invitar-reenviar-y-retirar-accesos) | Implementado; enlace siete días/un uso |
| Cambiar permisos, retirar y transferir propietario | [Cuentas](../aplicacion/cuentas-y-equipo.md) | [Invitaciones y propiedad](guia-completa.md#invitar-reenviar-y-retirar-accesos) | Implementado, propietario/último Admin protegidos |
| Auditoría de equipo, identidad y negocio | [Arquitectura](../aplicacion/arquitectura.md) | [Acceso](guia-completa.md#acceso-cuentas-y-equipo) | Implementada, sin consola general de edición |
| Google login/registro/vínculo/desvínculo | [Google](../aplicacion/acceso-google.md) | [Google](guia-completa.md#google-y-vinculación-de-identidad) | Condicional; Google real T14 pendiente |
| Contactos crear/editar/buscar/borrado lógico | [Contactos](../aplicacion/contactos-e-importacion.md) | [Contactos](guia-completa.md#contactos-consentimiento-y-listas) | Implementado |
| Consentimiento, fuente/UTC y revocado | [Contactos](../aplicacion/contactos-e-importacion.md) | [Contacto](guia-completa.md#crear-y-corregir-un-contacto) | Implementado; no reconsentimiento general |
| Listas crear/editar/eliminar/asociar/retirar | [Contactos](../aplicacion/contactos-e-importacion.md) | [Listas](guia-completa.md#buscar-agrupar-y-mantener-listas) | Implementado |
| CSV columnas, estructura y mapeo fijo | [Contactos](../aplicacion/contactos-e-importacion.md) | [CSV](guia-completa.md#importar-un-csv-y-corregir-errores) | Implementado; sin mapeo libre |
| CSV duplicados, resultados, descarga y recuperación | [Contactos](../aplicacion/contactos-e-importacion.md) | [Recuperar CSV](guia-completa.md#resolver-duplicados-y-recuperar-una-carga) | Implementado; no update masivo de existentes |
| Plantillas CRUD, variables y límites | [Plantillas](../aplicacion/plantillas-y-transporte.md) | [Plantillas](guia-completa.md#plantillas-y-pruebas) | Implementado |
| Preview aislada frente a versión guardada | [Plantillas](../aplicacion/plantillas-y-transporte.md) | [Preview](guia-completa.md#preview-del-formulario-y-versión-guardada) | Implementado; sin historial general editable |
| Remitentes, Reply-To y autorizaciones separadas | [Plantillas](../aplicacion/plantillas-y-transporte.md) | [Remitentes](guia-completa.md#remitentes-y-prueba-explícita) | Implementado |
| Pruebas log/Mailpit y archivo privado | [Plantillas](../aplicacion/plantillas-y-transporte.md) | [Pruebas](guia-completa.md#remitentes-y-prueba-explícita) | Implementado |
| Preparación SMTP genérica legada | [Plantillas](../aplicacion/plantillas-y-transporte.md) | [Modos](guia-completa.md#elegir-el-modo-conociendo-su-efecto) | Almacena preparación; no relay arbitrario |
| Cuatro canales Brevo/SendGrid | [Proveedores](../aplicacion/proveedores-email.md) | [Proveedores](guia-completa.md#transporte-y-proveedores-externos) | Implementados; cualificación privada M-01 pendiente |
| Cifrado, rotación, clear y deshabilitación | [Proveedores](../aplicacion/proveedores-email.md) | [Rotación](guia-completa.md#rotar-limpiar-y-suspender-salida) | Implementado, secretos no reexpuestos |
| Webhooks, correlación, dedupe y supresiones | [Proveedores](../aplicacion/proveedores-email.md) | [Feedback](guia-completa.md#feedback-reintentos-y-protección-de-destinatarios) | Simulado local probado; callback real pendiente |
| Campañas crear/guardar/vaciar selección | [Entrega](../aplicacion/entrega-y-recuperacion.md) | [Campañas](guia-completa.md#preparar-y-seguir-una-campaña) | Implementado |
| Revisar/autorizar/programar UTC y conflictos | [Entrega](../aplicacion/entrega-y-recuperacion.md) | [Revisión](guia-completa.md#revisión-y-autorización-explícita) | Implementado |
| Pausa/reanudación y ausencia de cancelar | [Entrega](../aplicacion/entrega-y-recuperacion.md) | [Pausa](guia-completa.md#qué-conservan-las-revisiones-y-cómo-pausar) | Pausa/reanudación implementadas; cancelar no existe |
| Bajas públicas y supresión manual | [Entrega](../aplicacion/entrega-y-recuperacion.md) | [Bajas](guia-completa.md#bajas-y-supresiones) | Implementado |
| Emitir/descargar/revocar enlaces de baja | [Entrega](../aplicacion/entrega-y-recuperacion.md) | [Enlaces](guia-completa.md#crear-y-revocar-un-enlace-de-baja) | Implementado; sin caducidad automática |
| Actividad: cinco poblaciones y filtros UTC | [Actividad](../aplicacion/actividad-y-metricas.md) | [Actividad](guia-completa.md#actividad-estados-y-métricas) | Implementado |
| Estados, intentos, diagnósticos y feedback | [Actividad](../aplicacion/actividad-y-metricas.md) | [Estados](guia-completa.md#interpretar-todos-los-estados) | Implementado, aceptación separada de entrega |
| Métricas, denominador, CSV y descargas | [Actividad](../aplicacion/actividad-y-metricas.md) | [Métricas](guia-completa.md#porcentajes-feedback-y-exportación) | Implementado; sin aperturas/clics/DNSBL |
| Llaves UI, concesión, caducidad y revocación | [API](../api/implementacion.md) | [Llaves](guia-completa.md#llaves-y-api-rest) | Implementado |
| REST recibos, idempotencia y errores | [Reglas API](../api/decisiones.md) | [Ejemplo REST](guia-completa.md#ejemplo-de-envío-y-recuperación-tras-respuesta-perdida) | Implementado y probado en laboratorio |
| MCP consentimiento, cuenta, scopes y revocación | [Conexiones](../aplicacion/conexiones-mcp.md) | [MCP](guia-completa.md#conexiones-mcp-y-herramientas) | Implementado; clientes reales pendientes |
| Configuración de clientes MCP y protocolos | [Clientes](../aplicacion/mcp-clientes.md) | [Preparación MCP](guia-completa.md#preparación-administrativa-y-límites-de-protocolo) | Ejemplos ficticios; T14 pendiente |
| Outbox, confirms, ack, reservas y fencing | [Entrega](../aplicacion/entrega-y-recuperacion.md) | [Colas](guia-completa.md#colas-workers-y-calendarios) | Implementado; sin exactly-once externo |
| Cuotas, backoff, incertidumbre y fallo | [Entrega](../aplicacion/entrega-y-recuperacion.md) | [Procesos](guia-completa.md#qué-paso-hace-cada-proceso) | Implementado; carga T19 pendiente |
| Dos eventos Cronicle y resultado de trigger | [Cronicle](../aplicacion/programacion-con-cronicle.md) | [Eventos](guia-completa.md#preparar-los-dos-eventos-cronicle) | Integración técnica aceptada; habilitación operativa propia |
| Scheduler Laravel y prune MCP | [Operación MCP](../aplicacion/mcp-operacion.md) | [Scheduler](guia-completa.md#scheduler-y-mantenimiento-mcp) | Implementado/probado técnicamente |
| RabbitMQ panel, vhost, usuarios y backlog | [Servicios](../infraestructura/servicios.md) | [RabbitMQ](guia-completa.md#rabbitmq-management) | Servicio local de administración |
| Cronicle panel, eventos, historial y logs | [Servicios](../infraestructura/servicios.md) | [Cronicle](guia-completa.md#cronicle) | Servicio local de administración |
| Mailpit panel, SMTP y retención | [Servicios](../infraestructura/servicios.md) | [Mailpit](guia-completa.md#mailpit) | Captura local; no relay de Internet |
| Red, proyectos, DNS y puertos | [Integración](../infraestructura/integracion-aplicaciones.md) | [Organización](guia-completa.md#cómo-se-organiza-la-instalación) | Configurados en Compose |
| Logs, salud, smoke y persistencia | [Operación](../infraestructura/operacion.md) | [Operación diaria](guia-completa.md#comprobaciones-diarias-y-parada-conservando-datos) | Probes escriben; salud no equivale a negocio |
| Actualización y migraciones no destructivas | [Desarrollo](../aplicacion/desarrollo.md) | [Actualizar](guia-completa.md#actualizar-sin-confundir-código-con-datos) | Procedimiento; destino T20 pendiente |
| Copia fría, SQL, volúmenes e imágenes | [Backup](../infraestructura/backup-migracion.md) | [Copia](guia-completa.md#copia-en-frío-propuesta) | Propuesta; restore global T19 pendiente |
| Restore MCP e invalidación de credenciales | [Operación MCP](../aplicacion/mcp-operacion.md) | [Recuperación](guia-completa.md#recuperar-en-un-destino-aislado) | Fixtures MCP probadas; no sustituye restore global |
| DNS/SPF/DKIM/DMARC, IP y reputación | [Entregabilidad](../infraestructura/entregabilidad.md) | [Preguntas](guia-completa.md#preguntas-de-uso) | Responsabilidad real del operador/proveedor; sin garantías |
| Diagnóstico y acciones destructivas | [Troubleshooting](../infraestructura/troubleshooting.md) | [Diagnóstico](guia-completa.md#diagnóstico-y-preguntas-frecuentes) | Documentado, sin ejecución por esta redacción |

## Interfaces HTTP del producto

Las páginas autenticadas y acciones de negocio están agrupadas abajo. Las rutas
Fortify de acceso se identifican por su función habilitada en `config/fortify.php`;
no se afirma que Emailing implemente una UI 2FA general. Los IDs y tokens de las
rutas son parámetros de ejemplo, nunca credenciales publicadas.

| Interfaz | Documento dueño | Sección manual | Estado real |
|---|---|---|---|
| `/`, `/login`, `/logout`, `/register`, recuperación y reset Fortify | [Cuentas](../aplicacion/cuentas-y-equipo.md) | [Acceso](guia-completa.md#acceso-cuentas-y-equipo) | Registro condicional; métodos según Fortify |
| `GET /dashboard`, `GET /accounts`, `POST /accounts/switch` | [Cuentas](../aplicacion/cuentas-y-equipo.md) | [Acceso](guia-completa.md#acceso-cuentas-y-equipo) | Sesión y membresía |
| `GET /team`, `GET/POST /invitations/{token}` | [Cuentas](../aplicacion/cuentas-y-equipo.md) | [Invitaciones](guia-completa.md#invitar-reenviar-y-retirar-accesos) | Autenticadas y delimitadas |
| `GET /contacts`, `GET /contacts/imports`, descarga de informe | [Contactos](../aplicacion/contactos-e-importacion.md) | [CSV](guia-completa.md#importar-un-csv-y-corregir-errores) | Contactos/CSV privados |
| `GET /templates`, `GET /settings/mail`, descarga mail-artifacts | [Plantillas](../aplicacion/plantillas-y-transporte.md) | [Plantillas](guia-completa.md#plantillas-y-pruebas) | Permisos revalidados |
| `GET /campaigns`, `GET /suppressions`, `POST /suppressions/links` | [Entrega](../aplicacion/entrega-y-recuperacion.md) | [Campañas](guia-completa.md#preparar-y-seguir-una-campaña) y [Bajas](guia-completa.md#bajas-y-supresiones) | Implementadas |
| `GET/POST /unsubscribe/{token}` | [Entrega](../aplicacion/entrega-y-recuperacion.md) | [Baja pública](guia-completa.md#crear-y-revocar-un-enlace-de-baja) | Capacidad firmada, POST confirma |
| `GET /activity`, `GET /activity/export` | [Actividad](../aplicacion/actividad-y-metricas.md) | [Actividad](guia-completa.md#actividad-estados-y-métricas) | Lecturas reautorizadas |
| `GET/POST /api-keys`, `DELETE /api-keys/{keyId}` | [API](../api/implementacion.md) | [Llaves](guia-completa.md#llaves-y-api-rest) | Sesión/CSRF, distinto de REST bearer |
| Google redirect/callback/confirm y POST confirm | [Google](../aplicacion/acceso-google.md) | [Google](guia-completa.md#google-y-vinculación-de-identidad) | Configuración condicional y real pendiente |
| `/settings/identities`, POST vínculo, DELETE desvínculo | [Google](../aplicacion/acceso-google.md) | [Google](guia-completa.md#google-y-vinculación-de-identidad) | Sesión y recuperación local |
| `/settings/connections`, POST revocación por grant | [Conexiones](../aplicacion/conexiones-mcp.md) | [MCP](guia-completa.md#conexiones-mcp-y-herramientas) | Conexiones del usuario |
| `POST /api/v1/mail/send` | [API](../api/implementacion.md) | [REST](guia-completa.md#las-cinco-operaciones-publicadas) | mail.send, plantilla añade templates.read |
| `GET /api/v1/mail/requests/{requestId}` | [API](../api/implementacion.md) | [REST](guia-completa.md#las-cinco-operaciones-publicadas) | activity.read |
| `GET /api/v1/keys` | [API](../api/implementacion.md) | [REST](guia-completa.md#las-cinco-operaciones-publicadas) | api-keys.read |
| `POST /api/v1/keys` | [API](../api/implementacion.md) | [REST](guia-completa.md#las-cinco-operaciones-publicadas) | api-keys.write y techo vigente |
| `DELETE /api/v1/keys/{keyId}` | [API](../api/implementacion.md) | [REST](guia-completa.md#las-cinco-operaciones-publicadas) | api-keys.write |
| `/mcp` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [MCP](guia-completa.md#conexiones-mcp-y-herramientas) | Frontera del protocolo; métodos no admitidos se rechazan |
| Tres rutas `.well-known` OAuth/recurso | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Endpoints MCP](guia-completa.md#endpoints-de-acceso-mcp) | Autoridad HTTPS explícita |
| `/oauth/authorize`, `/oauth/authorize/decision` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Autorización](guia-completa.md#autorizar-a-un-cliente-sin-compartir-tu-contraseña) | Cuenta y consentimiento vigentes |
| `/oauth/token`, `/oauth/revoke`, `/oauth/register` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Endpoints MCP](guia-completa.md#endpoints-de-acceso-mcp) | Registro dinámico apagado por defecto |
| `POST /internal/cronicle/trigger` | [Cronicle](../aplicacion/programacion-con-cronicle.md) | [Eventos](guia-completa.md#preparar-los-dos-eventos-cronicle) | Bearer dedicado; dos acciones finitas |
| `POST /webhooks/mail/brevo/{connection}` | [Proveedores](../aplicacion/proveedores-email.md) | [Feedback](guia-completa.md#feedback-reintentos-y-protección-de-destinatarios) | HTTPS/Bearer y correlación |
| `POST /webhooks/mail/sendgrid/{connection}` | [Proveedores](../aplicacion/proveedores-email.md) | [Feedback](guia-completa.md#feedback-reintentos-y-protección-de-destinatarios) | HTTPS/firma P-256 y correlación |
| `GET /up` | [Operación](../infraestructura/operacion.md) | [Salud](guia-completa.md#comprobaciones-diarias-y-parada-conservando-datos) | Sonda; no valida toda la instalación |

## Catálogo MCP y configuración pública

Las filas siguientes se contrastan con el catálogo público y las dos plantillas
de entorno. El manual explica la finalidad de cada operación y grupo de
variables; la guía técnica conserva los límites exactos. No contiene valores
privados ni se ha abierto ningún archivo de entorno real para redactarla.

### Las 37 operaciones MCP

| Operación | Documento dueño | Sección manual | Estado real |
|---|---|---|---|
| `activity_attempts` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `activity.read`; clientes reales pendientes |
| `activity_list` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `activity.read`; clientes reales pendientes |
| `activity_metrics` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `activity.read`; clientes reales pendientes |
| `campaigns_authorize` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `campaigns.read`, `campaigns.write`, `contacts.read`, `mail.send`, `templates.read`; clientes reales pendientes |
| `campaigns_create` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `campaigns.read`, `campaigns.write`, `contacts.read`, `templates.read`; clientes reales pendientes |
| `campaigns_get` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `campaigns.read`; clientes reales pendientes |
| `campaigns_list` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `campaigns.read`; clientes reales pendientes |
| `campaigns_pause` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `campaigns.read`, `campaigns.write`; clientes reales pendientes |
| `campaigns_resume` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `campaigns.read`, `campaigns.write`, `mail.send`; clientes reales pendientes |
| `campaigns_review` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `campaigns.read`, `campaigns.write`, `contacts.read`, `templates.read`; clientes reales pendientes |
| `campaigns_update` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `campaigns.read`, `campaigns.write`, `contacts.read`, `templates.read`; clientes reales pendientes |
| `connection_info` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; Contexto consentido; clientes reales pendientes |
| `contacts_create` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `contacts.write`; clientes reales pendientes |
| `contacts_delete` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `contacts.write`; clientes reales pendientes |
| `contacts_get` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `contacts.read`; clientes reales pendientes |
| `contacts_list` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `contacts.read`; clientes reales pendientes |
| `contacts_update` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `contacts.write`; clientes reales pendientes |
| `imports_get` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `contacts.read`; clientes reales pendientes |
| `imports_list` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `contacts.read`; clientes reales pendientes |
| `lists_attach_contact` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `contacts.write`; clientes reales pendientes |
| `lists_create` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `contacts.write`; clientes reales pendientes |
| `lists_delete` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `contacts.write`; clientes reales pendientes |
| `lists_detach_contact` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `contacts.write`; clientes reales pendientes |
| `lists_get` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `contacts.read`; clientes reales pendientes |
| `lists_list` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `contacts.read`; clientes reales pendientes |
| `lists_update` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `contacts.write`; clientes reales pendientes |
| `mail_request_get` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `activity.read`; clientes reales pendientes |
| `mail_send` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `mail.send`; clientes reales pendientes |
| `suppressions_add` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `suppressions.read`, `suppressions.write`; clientes reales pendientes |
| `suppressions_list` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `suppressions.read`; clientes reales pendientes |
| `suppressions_remove` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `suppressions.read`, `suppressions.write`; clientes reales pendientes |
| `templates_create` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `templates.read`, `templates.write`; clientes reales pendientes |
| `templates_delete` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `templates.read`, `templates.write`; clientes reales pendientes |
| `templates_get` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `templates.read`; clientes reales pendientes |
| `templates_list` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `templates.read`; clientes reales pendientes |
| `templates_preview` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `templates.read`; clientes reales pendientes |
| `templates_update` | [Contrato MCP](../aplicacion/mcp-contrato.md) | [Catálogo explicado](guia-completa.md#catálogo-de-las-37-herramientas) | Implementada; `templates.read`, `templates.write`; clientes reales pendientes |

### Variables de las plantillas de entorno

| Variable pública | Documento dueño | Sección manual | Estado real |
|---|---|---|---|
| `APP_NAME` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `CRONICLE_TRIGGER_SECRET` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `CRONICLE_DISPATCH_CLAIM_LIMIT` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `CRONICLE_DISPATCH_BUDGET_MS` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `APP_ENV` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `APP_KEY` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `APP_PREVIOUS_KEYS` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `EMAIL_EXTERNAL_ENABLED` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `APP_DEBUG` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `APP_URL` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `APP_LOCALE` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `APP_FALLBACK_LOCALE` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `REGISTRATION_ENABLED` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DB_CONNECTION` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DB_HOST` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DB_PORT` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DB_DATABASE` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DB_USERNAME` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DB_PASSWORD` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `SESSION_DRIVER` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `SESSION_LIFETIME` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `CACHE_STORE` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `QUEUE_CONNECTION` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `RABBITMQ_HOST` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `RABBITMQ_PORT` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `RABBITMQ_USER` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `RABBITMQ_PASSWORD` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `RABBITMQ_VHOST` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `RABBITMQ_QUEUE` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `MAIL_MAILER` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `MAIL_HOST` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `MAIL_PORT` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `MAIL_FROM_ADDRESS` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `MAIL_FROM_NAME` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `LOG_CHANNEL` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `LOG_LEVEL` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `MAILPIT_SMTP_HOST` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `MAILPIT_SMTP_PORT` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `MAILPIT_API_URL` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DELIVERY_EXCHANGE` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DELIVERY_QUEUE` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DELIVERY_ROUTING_KEY` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DELIVERY_BINDING_KEY` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DELIVERY_FAILURE_QUEUE` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DELIVERY_PREFETCH` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DELIVERY_WORKER_LEASE_SECONDS` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DELIVERY_PAUSE_POLL_SECONDS` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DELIVERY_MAX_ATTEMPTS` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DELIVERY_BACKOFF_SECONDS` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DELIVERY_BACKOFF_MAX_SECONDS` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DELIVERY_RELAY_POOL` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DELIVERY_ACCOUNT_PER_MINUTE` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DELIVERY_ACCOUNT_PER_DAY` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DELIVERY_POOL_PER_MINUTE` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `DELIVERY_POOL_PER_DAY` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `GOOGLE_CLIENT_ID` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `GOOGLE_CLIENT_SECRET` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `GOOGLE_REDIRECT_URI` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `GOOGLE_WORKSPACE_DOMAINS` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `MCP_ENABLED` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `MCP_ISSUER` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `MCP_RESOURCE` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `MCP_DYNAMIC_REGISTRATION` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `MCP_REGISTRATION_HTTPS_HOSTS` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `MCP_REGISTRATION_LOOPBACK_PROFILES` (app) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `RABBITMQ_ADMIN_USER` (infra) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `RABBITMQ_ADMIN_PASSWORD` (infra) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `MYSQL_ROOT_PASSWORD` (infra) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `MYSQL_EMAILING_PASSWORD` (infra) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `CRONICLE_SECRET_KEY` (infra) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |
| `CRONICLE_ADMIN_PASSWORD` (infra) | [Configuración](../infraestructura/configuracion.md) | [Variables y precedencia](guia-completa.md#configuración-del-despliegue) | Configurable; plantilla pública, secreto real privado cuando corresponde |

### Opciones propias adicionales

| Opción | Documento dueño | Sección manual | Estado real |
|---|---|---|---|
| CONTACT_IMPORT_MAX_BYTES | [Configuración](../infraestructura/configuracion.md) | [Configuración y operación](guia-completa.md#configuración-del-despliegue) | Valor inicial; cambios requieren revisión del conjunto |
| CONTACT_IMPORT_MAX_ROWS | [Configuración](../infraestructura/configuracion.md) | [Configuración y operación](guia-completa.md#configuración-del-despliegue) | Valor inicial; cambios requieren revisión del conjunto |
| SESSION_SECURE_COOKIE | [Configuración](../infraestructura/configuracion.md) | [Configuración y operación](guia-completa.md#configuración-del-despliegue) | Valor inicial; cambios requieren revisión del conjunto |
| CACHE_PREFIX | [Configuración](../infraestructura/configuracion.md) | [Configuración y operación](guia-completa.md#configuración-del-despliegue) | Valor inicial; cambios requieren revisión del conjunto |
| SMTP timeout/API timeout | [Configuración](../infraestructura/configuracion.md) | [Configuración y operación](guia-completa.md#configuración-del-despliegue) | Valor inicial; cambios requieren revisión del conjunto |
| Webhook max age 86400 | [Configuración](../infraestructura/configuracion.md) | [Configuración y operación](guia-completa.md#configuración-del-despliegue) | Valor inicial; cambios requieren revisión del conjunto |
| MCP concurrency_per_account 8 | [Configuración](../infraestructura/configuracion.md) | [Configuración y operación](guia-completa.md#configuración-del-despliegue) | Valor inicial; cambios requieren revisión del conjunto |
| MCP allowed_origins vacío | [Configuración](../infraestructura/configuracion.md) | [Configuración y operación](guia-completa.md#configuración-del-despliegue) | Valor inicial; cambios requieren revisión del conjunto |
| PHP upload 6M/post 8M/memoria 256M | [Configuración](../infraestructura/configuracion.md) | [Configuración y operación](guia-completa.md#configuración-del-despliegue) | Valor inicial; cambios requieren revisión del conjunto |
| Nginx body 8m | [Configuración](../infraestructura/configuracion.md) | [Configuración y operación](guia-completa.md#configuración-del-despliegue) | Valor inicial; cambios requieren revisión del conjunto |
| Cronicle max_jobs 10/historial 30d/log 10MiB | [Configuración](../infraestructura/configuracion.md) | [Configuración y operación](guia-completa.md#configuración-del-despliegue) | Valor inicial; cambios requieren revisión del conjunto |
| Mailpit max_messages 1000 | [Configuración](../infraestructura/configuracion.md) | [Configuración y operación](guia-completa.md#configuración-del-despliegue) | Valor inicial; cambios requieren revisión del conjunto |
| Docker logging json-file 10m x 3 | [Configuración](../infraestructura/configuracion.md) | [Configuración y operación](guia-completa.md#configuración-del-despliegue) | Valor inicial; cambios requieren revisión del conjunto |

## Resultado del inventario

El inventario contiene las 37 herramientas publicadas y 71 variables de las dos plantillas, además de las opciones propias adicionales. Las tablas funcionales y HTTP cubren las superficies visibles y sus rutas. No quedan funciones de este universo sin enlace al documento dueño y al manual.

Los pendientes operativos/humanos se mantienen explícitos: principal 18/20 con T19/T20; MCP 11/14 con T12–T14; proveedores M-01 real y M-02 humana/retro. La validación final de enlaces y la representación PDF se registran en el handoff de documentación, sin modificar los informes históricos.
