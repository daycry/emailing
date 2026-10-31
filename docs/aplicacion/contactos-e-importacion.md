# Cómo gestionar contactos, listas e importaciones CSV

**Última actualización: 2026-10-31.** T-05/T-06 implementados y comprobados en [QA3 de fase 2](../roadmap/2026-10-21-plataforma-emailing/testing/fase-2/report-attempt-3.md). Los ejemplos son ficticios. Para uso persistente prepara una [instalación propia](../manual/guia-completa.md#instalación-docker-desde-cero); los perfiles QA pueden limpiar sus datos. El [manual CSV](../manual/guia-completa.md#importar-un-csv-y-corregir-errores) incluye un ejemplo y recuperación de filas inválidas.

## Qué comparte el equipo y qué permisos necesita

Los contactos, listas e informes pertenecen a la cuenta activa. Los Teammates autorizados de esa cuenta comparten los mismos recursos. Otra cuenta no puede consultar sus identificadores ni descargar sus informes.

| Operación | Permiso vigente |
|---|---|
| Consultar contactos, listas, importaciones y descargar resultados | `contacts.read` |
| Crear, editar, eliminar o asociar contactos y listas desde el panel | `contacts.read` y `contacts.write` |
| Subir CSV y confirmar su importación | `contacts.read` y `contacts.write` |

Admin dispone de ambos permisos; Solo lectura permite consultar. Un miembro Restricted necesita las casillas correspondientes. La aplicación revalida membresía y scopes al leer y ejecutar acciones, aunque la página estuviera abierta antes de retirar acceso.

Fuentes: [ContactService](../../app/Services/ContactService.php), [ContactImportService](../../app/Services/ContactImportService.php), [AccountContext](../../app/Services/AccountContext.php) y [Permissions](../../app/Services/Permissions.php).

## Cómo crear o corregir un contacto

1. Comprueba la cuenta activa y abre **Contactos y listas** (`/contacts`).
2. Pulsa **Nuevo contacto** e indica email, nombre y consentimiento.
3. Para **Otorgado**, añade fuente y fecha UTC que acrediten el consentimiento.
4. Pulsa **Guardar contacto**. Puedes buscar después por nombre o email.

| Campo | Regla actual |
|---|---|
| Email | Obligatorio, válido y hasta 254 caracteres; se eliminan espacios exteriores y se convierte a minúsculas. |
| Nombre | Opcional en la UI, hasta 150 caracteres. |
| Consentimiento | `unknown` (Desconocido), `granted` (Otorgado) o `revoked` (Revocado). Inicialmente Desconocido. |
| Fuente | Obligatoria con Otorgado, hasta 255 caracteres. |
| Fecha | Obligatoria con Otorgado, UTC, formato `YYYY-MM-DDTHH:MM`, nunca futura. |

Una dirección válida no acredita consentimiento. Si el estado no es Otorgado, el servicio limpia fuente y fecha. Un consentimiento Revocado no puede reactivarse desde este formulario.

El email identifica al contacto y no se cambia al editar. Para otra dirección crea otro contacto. La cuenta no admite dos contactos activos con el mismo email normalizado; otra cuenta sí puede mantener el suyo.

**Eliminar** retira al contacto de todas sus listas y aplica borrado lógico. La fila histórica permanece; el panel deja de mostrarla como contacto activo. Una supresión bloquea la dirección incluso si se recrea: utiliza [Bajas y supresiones](../manual/guia-completa.md#bajas-y-supresiones). El pipeline revalida contactos conocidos y no convierte una ficha eliminada en envío directo para eludir su historia.

La búsqueda admite hasta 150 caracteres. El panel pagina 15 contactos y 10 listas. Fuentes: [componente Contacts](../../app/Livewire/Contacts.php), [vista](../../resources/views/livewire/contacts.blade.php) y [modelo Contact](../../app/Models/Contact.php).

## Cómo organizar listas sin duplicar contactos

Pulsa **Nueva lista**, indica nombre de hasta 150 caracteres y descripción de hasta 500, y guarda. Selecciona su nombre para consultar sus miembros.

En **Añadir contactos**, la lista queda como destino de selección. Pulsa **Añadir a lista** junto a cada contacto y **Terminar selección** cuando acabes. La asociación no crea otra copia del contacto.

**Retirar de lista** conserva al contacto y sus otras asociaciones. **Eliminar lista** elimina esa agrupación y conserva los contactos. No puedes asociar un contacto o lista de otra cuenta.

`contact_list_memberships` relaciona listas y contactos e incluye `account_id`. El servicio bloquea la cuenta y vuelve a leer la membresía al escribir. Además registra eventos de creación, edición, borrado, asociación e importación en `AuditEvent`.

## Qué formato CSV admite la importación

Abre **Importar CSV** o `/contacts/imports`. Prepara un archivo con extensión `.csv`, UTF-8 con o sin BOM y separador coma. Admite comillas dobles, comillas escapadas como `""` y saltos LF o CRLF. Los campos entrecomillados pueden contener comas y saltos de línea.

Los encabezados son exactos y no pueden repetirse. `email` es obligatorio; los demás son opcionales y su orden puede variar. No admite columnas adicionales ni un separador punto y coma.

```csv
email,name,consent_status,consent_source,consented_at
persona@example.test,"Persona de muestra",unknown,,
otra@example.test,"Ejemplo, con coma",granted,Formulario ficticio,2025-01-10T12:30
retirada@example.test,"Ejemplo ""retirado""",revoked,,
```

| Encabezado | Tratamiento |
|---|---|
| `email` | Trim y minúsculas; validación de dirección y máximo 254 caracteres. |
| `name` | Vacío si falta; máximo 150 caracteres. |
| `consent_status` | Vacío o ausente pasa a `unknown`; admite también `granted` y `revoked`. |
| `consent_source` | Trim; máximo 255 caracteres; obligatorio con `granted`. |
| `consented_at` | Fecha UTC exacta `YYYY-MM-DDTHH:MM`, válida y no futura; obligatoria con `granted`. |

Un error de estructura rechaza el archivo completo antes de crear contactos: UTF-8 inválido, byte nulo, comillas mal cerradas, columnas incoherentes, encabezados incorrectos o límites excedidos. También rechaza archivos sin registros. Una fila semánticamente inválida aparece en el informe y permite procesar las otras filas válidas.

Las líneas físicas vacías se ignoran. El número del informe identifica el registro CSV, empezando en 2 tras el encabezado. Un salto dentro de comillas no añade un registro. Fuentes: [ContactCsv](../../app/Support/ContactCsv.php) y [validación del importador](../../app/Services/ContactImportService.php).

## Cómo importar y entender los duplicados

1. Elige una lista de destino si quieres agrupar los contactos nuevos. **Usar sin lista** crea sólo contactos.
2. Selecciona el archivo y espera a que termine **Subiendo archivo…**.
3. Pulsa **Importar y revisar** y consulta el resumen de creados, inválidos y duplicados.
4. Abre otras importaciones de la cuenta para revisar sus resultados o descarga **Descargar resultado CSV**.

El importador conserva la primera fila válida de cada email normalizado del archivo. Las siguientes válidas con ese email son duplicadas. Una fila inválida previa no impide que otra válida cree el contacto.

Si el email ya existe como contacto activo de la cuenta, lo omite. No actualiza nombre, consentimiento ni asociaciones. La lista de destino sólo recibe los contactos nuevos de esa importación. Para añadir un contacto existente a una lista utiliza la asociación del panel.

| Resultado almacenado | Qué significa |
|---|---|
| `imported` / Creado | Creó un contacto nuevo y, si se eligió destino, lo asoció. |
| `duplicate` / Duplicado | Email ya activo en la cuenta o repetido válido dentro del archivo; no actualiza el contacto existente. |
| `invalid` / Inválido | La fila incumple validación de campos; el detalle explica el motivo. |

El informe muestra 25 filas por página, el historial 10 importaciones y el selector 10 listas por página. Buscar listas no borra el aviso visible de un archivo rechazado. Al corregir el archivo y completar la operación, se retiran los errores anteriores.

## Qué límites y almacenamiento utiliza

| Capa | Límite o comportamiento |
|---|---|
| Archivo aceptado por la aplicación | 5 MiB (`5 * 1024 * 1024` bytes) y 10.000 registros de datos por archivo; Livewire limita el tamaño y el importador valida las filas. |
| Persistencia SQL | Consultas de candidatos e inserciones en lotes de 500 dentro de una transacción. |
| Parser | Lee el archivo acotado y prepara sus filas en memoria; no es un importador de tamaño ilimitado ni un trabajo asíncrono. |
| PHP del proyecto | `upload_max_filesize=6M`, `post_max_size=8M`, `memory_limit=256M`. |
| Nginx Docker | `client_max_body_size 8m`, con margen para multipart; el límite funcional sigue siendo 5 MiB. |

Las variables `CONTACT_IMPORT_MAX_BYTES` y `CONTACT_IMPORT_MAX_ROWS` están en [config/emailing.php](../../config/emailing.php). Subir el límite exige revisar también [config/livewire.php](../../config/livewire.php), [emailing-uploads.ini](../../docker/emailing-uploads.ini), [nginx.conf](../../docker/nginx.conf) y reiniciar o reconstruir los runtimes afectados. Los wrappers PHP y `serve-testing.ps1` copian el ini del proyecto; no cambian el PHP global.

El disco privado `contact-imports` tiene raíz `storage/app/private/contact-imports`, con subdirectorio temporal `temporary` y originales importados bajo `sources`. No expone un enlace público. La subida autenticada liga el handle a sesión, usuario y cuenta durante una hora; importarlo revalida ese vínculo y los permisos actuales.

Tras importar correctamente, el componente borra el temporal. Si falla la transacción, el servicio retira la copia de origen creada para ella. Esto no acredita una política global de retención ni limpieza de todos los archivos históricos.

La descarga del resultado consulta nuevamente cuenta y permiso, y responde `private, no-store`. Contiene `registro,email,resultado,detalle`, no una exportación general de contactos. Ante celdas que empezarían por `=`, `+`, `@` o `-`, incluso tras espacios o controles, antepone un apóstrofo para neutralizar fórmulas.

Fuentes: [filesystems.php](../../config/filesystems.php), [AuthorizeContactUpload](../../app/Http/Middleware/AuthorizeContactUpload.php), [ContactImports](../../app/Livewire/ContactImports.php) y [rutas](../../routes/web.php).

## Qué comprueban las pruebas y qué queda pendiente

[ContactListTest](../../tests/Feature/ContactListTest.php) y [ContactImportTest](../../tests/Feature/ContactImportTest.php) cubren reglas backend. QA3 ejecutó tres escenarios E2E-05 por perfil: CRUD/CSV mixto; 9.990 filas y archivo cercano a 5 MiB; y handle auténtico ajeno rechazado con 403. También comprobó paginación, descarga privada, fórmula neutralizada y scopes revocados.

La limpieza final verificó los veinte UUID de toda la tanda y seis entradas de uploads: no quedaron archivos registrados propios. Los históricos sin atribución se conservaron. [GOT-002 aceptado](../knowledge/gotchas/GOT-002-limpieza-de-uploads-de-qa.md) explica por qué no equivale a afirmar cero temporales globales. [GOT-001 aceptado](../knowledge/gotchas/GOT-001-errores-livewire-tras-reintento.md) recoge la comprobación de mensajes visibles.

Los tiempos y planes SQL focales de esta fase no son un benchmark universal. Campañas, selección revisada, bajas, supresiones, cuotas y API están implementadas con evidencia propia. T19/T20 mantienen carga/restauración y cierre operativo; la aceptación humana M-01 sigue pendiente.
