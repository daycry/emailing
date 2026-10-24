# Plan de pruebas — MCP y acceso con Google

| | |
|---|---|
| **Estado** | borrador |
| **Plan** | [`improvement-plan.md`](improvement-plan.md) |
| **URL local** | Perfil Laragon: origen de testing configurado por el runner; perfil Docker: origen HTTP publicado y atribuido por el runner. Valores reales privados, sin asumir puertos disponibles |
| **Ejecución** | Planeada, sin escenarios ejecutados por este documento |

Los escenarios automatizados usan navegador real, aplicación real y Google simulado en el límite de proveedor. No acreditan Google real ni interoperabilidad de clientes. M-01/M-03 conservan esas verificaciones externas separadas. Todos los datos son ficticios y el envío acaba exclusivamente en Mailpit/log.

El implementer creará `tests/e2e/mcp-google.spec.mjs` y su configuración Playwright en T-03/T-12. Los comandos aquí descritos son futuros. Cada ejecución identifica fuentes, perfil, migraciones, fixtures, recibos y limpieza atribuidos. No se ejecuta `migrate:fresh` sobre la base operativa ni se limpia la infraestructura de otras aplicaciones.

## Automáticos (E2E — los ejecuta `qa` con Playwright)

### E2E-01 — Google entra al mismo usuario y retoma el consentimiento

- **Objetivo**: CA-01/CA-05: reutilizar usuario local y cuentas autorizadas; retornar a una solicitud OAuth validada.
- **Precondiciones**: Ambos perfiles preparados por separado; persona ficticia con identidad vinculada, dos membresías propias y una cuenta ajena. Proveedor Google simulado con identidad válida.
- **Pasos**:
  1. Abrir login y pulsar Google; completar el callback simulado.
  2. Consultar usuario/cuentas visibles y cambiar entre las dos membresías propias.
  3. Cerrar sesión; abrir una solicitud OAuth MCP válida; autenticarse con Google.
  4. Comprobar que se retoma el consentimiento del mismo cliente/recurso sin cambiar sus parámetros.
- **Aserciones**:
  - [ ] Usuario local estable y ninguna cuenta ajena visible; no se crean usuarios o membresías adicionales.
  - [ ] Retorno sólo al origen/ruta validados; Host/return_url alterados no desvían el callback.
  - [ ] Google no configura transporte SMTP ni concede scopes de negocio.
- **Capturas esperadas**: Login Google disponible, panel tras callback y consentimiento recuperado; sin query con códigos/tokens.
- **Cubre tareas**: T-03, T-11, T-12, T-14.

### E2E-02 — Callback y vínculo no confían en la coincidencia de email

- **Objetivo**: CA-02/CA-03: rechazar callbacks inválidos y autoenlace por email; conservar sub estable.
- **Precondiciones**: Proveedor simulado con state inválido, código reutilizado, emisor/sub inválidos y correo igual a una cuenta local no vinculada.
- **Pasos**:
  1. Intentar login con cada respuesta inválida y volver al formulario.
  2. Intentar acceso Google con correo coincidente sin vínculo previo.
  3. Autenticarse localmente y vincular explícitamente la identidad en el perfil.
  4. Cambiar el email devuelto por Google manteniendo sub y volver a entrar.
- **Aserciones**:
  - [ ] Cada negativo carece de sesión/vínculo/usuario nuevo y muestra error visible sin filtrar cuentas existentes.
  - [ ] Coincidencia de email no fusiona cuentas; vínculo requiere control local autenticado y CSRF.
  - [ ] Mismo sub usa mismo usuario tras cambiar email; identidad ya vinculada a otra persona rechaza sustitución.
- **Capturas esperadas**: Error seguro, confirmación de vínculo explícito y perfil estable.
- **Cubre tareas**: T-03, T-12.

### E2E-03 — Admisión, invitación, registro y desvínculo conservan sus reglas

- **Objetivo**: CA-03/CA-04: autenticar identidad sin asignar roles ni saltar registro/invitaciones.
- **Precondiciones**: Variantes de registro habilitado/deshabilitado y filtro Workspace, invitación ficticia vigente y usuario con recuperación local.
- **Pasos**:
  1. Intentar acceso con persona/dominio no admitidos.
  2. Completar el flujo explícito de registro permitido y revisar que crea sólo su cuenta propia.
  3. Aceptar invitación con identidad correcta e intentar con identidad ajena.
  4. Vincular/desvincular Google y probar recuperación/acceso local.
  5. Desactivar configuración Google en el perfil aislado y volver al login.
- **Aserciones**:
  - [ ] Persona no admitida no crea membresías; filtro se basa en identidad validada de servidor, no un email enviado por formulario.
  - [ ] Rol invitado y comprobación de destinatario siguen la política existente; Google no impone admin.
  - [ ] Desvínculo no bloquea toda recuperación; login local permanece operativo y Google sin configurar muestra estado comprensible.
- **Capturas esperadas**: Registro explícito, aceptación/denegación de invitación y perfil tras desvínculo.
- **Cubre tareas**: T-03, T-12.

### E2E-04 — Consentimiento elige cuenta y scopes sin elevar permisos

- **Objetivo**: CA-05/CA-07: mostrar y autorizar cliente, cuenta y capacidades concretas.
- **Precondiciones**: Actor ficticio admin en A, restricted en B, sin acceso a C; cliente MCP público registrado y solicitud PKCE válida.
- **Pasos**:
  1. Abrir consentimiento y revisar cliente/cuenta/scopes.
  2. Seleccionar B e intentar conceder scope no disponible o account_id C mediante petición alterada.
  3. Denegar y luego iniciar otra autorización válida con scopes limitados de B.
  4. Consumir código por el cliente de prueba protocolario y llamar herramienta permitida.
- **Aserciones**:
  - [ ] Sólo aparecen A/B; los scopes seleccionables se limitan a membresía vigente y el POST los revalida.
  - [ ] Denegar no emite credencial; manipular cliente/cuenta/recurso/redirect no concede acceso.
  - [ ] Código/token queda ligado al cliente/recurso/grant/cuenta elegidos; no se deriva de una cookie de selección posterior.
  - [ ] El cliente completa el canje de código sin que la persona tenga que crear o pegar una API key; el navegador vuelve a la solicitud validada.
- **Capturas esperadas**: Consentimiento con cuenta/scopes limitados, denegación y conexión aprobada.
- **Cubre tareas**: T-07, T-11, T-12, T-14.

### E2E-05 — Sesión abierta conserva la frontera de cuenta y capacidades

- **Objetivo**: CA-06/CA-07/CA-09: impedir invocación directa y lectura cruzada desde una conexión autorizada.
- **Precondiciones**: Grant restricted de B emitido en E2E-04; fixtures A/B; cliente de prueba que usa el transporte real sin sesión web.
- **Pasos**:
  1. Enumerar capacidades y ejecutar lectura permitida sobre un recurso B.
  2. Invocar una mutación sin scope e invocar recurso A por ID directo.
  3. Intentar el mismo transporte con sólo cookie web, API key de cuenta y token simulado Google.
  4. Consultar conexiones y auditoría mediante la web autorizada.
- **Aserciones**:
  - [ ] No hay recurso ajeno ni efecto de mutación denegada; cada llamada tiene cuenta/actor/cliente/correlación atribuibles.
  - [ ] Credenciales no MCP se rechazan antes de ejecutar servicio; ocultar una herramienta no es la única protección.
  - [ ] Cambiar selección web de cuenta no mueve el grant emitido.
- **Capturas esperadas**: Capacidades limitadas y errores seguros; no capturar bearer o códigos.
- **Cubre tareas**: T-07, T-12.

### E2E-06 — Revocar conexión y membresía corta llamadas y reautorización

- **Objetivo**: CA-08: impedir acceso posterior desde conexión abierta y mantener otras conexiones aisladas.
- **Precondiciones**: Dos grants ficticios de distintas cuentas; conexión MCP abierta; usuario autorizado para gestionar su conexión.
- **Pasos**:
  1. Abrir conexiones, confirmar revocación de un grant y repetir llamada con la sesión MCP anterior.
  2. Intentar refresh_token y comprobar su rechazo, pues el contrato inicial no lo emite.
  3. Quitar membresía o reducir scopes con una nueva conexión y repetir lectura/mutación afectada.
  4. Expirar access token con reloj de prueba; iniciar nueva autorización y revisar permisos vigentes.
- **Aserciones**:
  - [ ] Revocación impide nuevas llamadas; otra cuenta/conexión sigue funcionando.
  - [ ] Reautorización no reactiva membresía ni scopes revocados; cuenta distinta requiere grant distinto.
  - [ ] Revocar mediante GET o sin CSRF no modifica el estado; interfaz muestra resultado y errores útiles.
- **Capturas esperadas**: Conexiones antes/después, confirmación y petición posterior denegada.
- **Cubre tareas**: T-07, T-11, T-12, T-14.

## Manuales (M — los realiza una persona)

### M-01 — Google real en Laragon y Docker

- **Qué revisar**: Configurar privadamente proyecto/callback Google; ejecutar login, vínculo/desvínculo, admisión y retorno OAuth con identidad autorizada en ambos perfiles.
- **Por qué no se automatiza**: Proveedor, consentimientos y cuentas reales del operador; no guardar credenciales ni sesiones en el repo.
- **Criterio de aceptación**:
  - [ ] Google autentica la identidad esperada y la aplicación conserva cuentas/roles locales; callback alterado no inicia sesión.
  - [ ] La política Gmail/Workspace configurada se cumple; no se presupone una aprobación del dominio por el usuario.
  - [ ] Cada perfil conserva evidencia atribuida y sanitizada; un mock verde no marca esta prueba completada.
- **Cubre tareas**: T-03, T-11, T-14.

### M-02 — Claridad del consentimiento y gestión de conexiones

- **Qué revisar**: Entender qué cliente accede, a qué cuenta, con qué capacidades y cómo revocarlo; comprobar estados vacíos, errores y navegación con teclado.
- **Por qué no se automatiza**: Juicio de claridad y presentación; la autorización técnica tiene pruebas separadas.
- **Criterio de aceptación**:
  - [ ] La pantalla identifica cuenta y permisos sin exponer detalles de implementación ni ocultar efectos de envío.
  - [ ] Aprobar, denegar, cambiar la cuenta elegible y revocar tienen resultado comprensible y no muestran datos de otras personas.
  - [ ] Una persona confirma el recorrido; el agente no se atribuye esa aceptación.
- **Cubre tareas**: T-07, T-14.

### M-03 — Clientes reales, expiración y revocación (CA-12/CA-15)

- **Qué revisar**: Codex, Claude Code y OpenCode reales; registrar binario/producto, versión, protocolo, transporte, mecanismo de registro, callbacks y conectividad. Claude Desktop mantiene fila separada; T-01 decide si también forma parte de los clientes objetivo.
- **Por qué no se automatiza**: Sesiones locales de clientes, autorización interactiva y diferencias por versión; el cliente protocolario de tests no los sustituye.
- **Criterio de aceptación**:
  - [ ] Cada cliente objetivo descubre, registra/identifica cliente, autentica con Google/local, consiente y llama una herramienta permitida.
  - [ ] Se acredita llamada denegada por scope/cuenta y revocación durante conexión abierta.
  - [ ] Tras expiración se reautoriza y conserva permisos actuales, sin depender de refresh no implementado. Si un cliente requerido necesita refresh, se registra el caso y se reabre contrato/ADR antes del cierre.
  - [ ] Matriz distingue consulta documental, intento fallido, pendiente y éxito ejecutado; no se promete compatibilidad de otro producto/versión.
- **Cubre tareas**: T-11, T-14.

### M-04 — Reinicio, restauración y configuración del servidor (CA-13/CA-14)

- **Qué revisar**: Ejecutar runner de perfiles; revisar TLS/origen/recurso/callbacks y persistencia de identidades/grants/revocación. Restaurar copia aislada, sin afectar base operativa.
- **Por qué no se automatiza**: Configuración privada del servidor y decisión operativa de recuperación; las regresiones de persistencia se automatizan por separado.
- **Criterio de aceptación**:
  - [ ] Ambos perfiles reinician sin perder identidad ni reactivar un grant revocado; la restauración de copia antigua invalida credenciales potencialmente reactivadas.
  - [ ] No se duplican RabbitMQ/MySQL/Cronicle/Mailpit; workers y web comparten las fuentes esperadas.
  - [ ] Capturas/documentos usan datos ficticios, no contienen códigos/tokens y el envío sigue en Mailpit/log.
- **Cubre tareas**: T-11, T-14.

## Cobertura (tarea → escenarios)

| Tarea | Cubierta por |
|---|---|
| T-03 | E2E-01, E2E-02, E2E-03, M-01 |
| T-07 | E2E-04, E2E-05, E2E-06, M-02 |
| T-11 | E2E-01, E2E-04, E2E-06, M-01, M-03, M-04 |
| T-12 | E2E-01, E2E-02, E2E-03, E2E-04, E2E-05, E2E-06 |
| T-14 | E2E-01, E2E-04, E2E-06, M-01, M-02, M-03, M-04 |

Las tareas backend T-02/04/05/06/08/09/10 tienen verificaciones de integración específicas en el ledger. T-01 fija contratos y T-13 revisa el diff. La matriz CA-01–16 del plan une ambas clases de evidencia. No se añaden por defecto bloques API-xx o A11Y-xx; smoke HTTP y axe-core siguen opt-in.

## Integración backend sin UI

Estas pruebas completan los criterios GWT que no tienen una pantalla propia. Son pruebas de dominio/transporte previstas en el ledger, no smokes API-xx ni sustitutos de clientes reales.

| Criterio | Escenario observable | Verificación planeada | Tareas |
|---|---|---|---|
| CA-10 | Dos procesos API/MCP de la misma cuenta y clave/payload compiten mediante barrera; sólo un request, trabajo y mensaje atribuido. Otro payload da conflicto y otra cuenta queda aislada | `php artisan test --filter=McpApiIdempotencyTest` | T-10, T-12 |
| CA-11 | Token mail.send intenta enviar a destinatario suprimido/sin autorización o programar sin revisión válida; tampoco puede eludir cuotas o la comprobación previa al SMTP | `php artisan test --filter="McpSendTest|McpBusinessToolsTest"` | T-09, T-10, T-12 |

Los casos guardarán manifiestos y recibos de la ejecución real, incluidas fuentes y estado de cola, request/outbox/delivery y mensajes propios en Mailpit/log. El cleanup no afecta fixtures ajenos.

## Condición de cierre

QA registra resultado por escenario y perfil, sin marcar un manual pendiente como aprobado. Cualquier credencial/sesión externa ausente deja T-14 pendiente. T-14 acredita las pruebas propias de este plan y entrega escenarios, fixtures y resultados a T-19/T-20; no requiere su ejecución o cierre previo. Root debe incorporar y ejecutar después en T-19 el QA cruzado, carga y restauración con MCP/Google, y en T-20 la documentación y revisión final. Aceptar el componente MCP no acredita ese QA global ni terminar veinte tareas originales cierra por sí solo el objetivo ampliado.
