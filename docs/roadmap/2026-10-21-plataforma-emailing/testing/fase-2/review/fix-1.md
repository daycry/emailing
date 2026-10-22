# Correcciones del intento 1 · T-09

Pendientes de revisión independiente incremental. QA de navegador aún pendiente.

| ID | Comprobación anterior | Corrección y evidencia posterior |
|---|---|---|
| B-01 | HTML de imagen con texto vacío rechazado al probar. | Cuerpos HTML/texto alternativos, normalización de ausencia/null, tipos y límites conservados. `.eml` privado conserva el HTML; ambos cuerpos vacíos siguen rechazados. |
| B-02 | Token en atributo compensado por entidades en texto aceptado. | Cada ocurrencia raw se marca antes del parseo; solo se aceptan marcas en nodos de texto permitidos. Regresiones decimal/hex y contextos prohibidos; texto codificado literal conservado. |
| D-01 | Con 10 listas y 10.000 afiliaciones, EXPLAIN usa barrido de índice global. | Nueva migración incremental añade índice `(contact_list_id, contact_id)`. EXPLAIN pasa a `ref`, con `contact_list_id` como prefijo utilizado; diez conteos de 1.000 correctos. |
| UI-01 | Retirar solo lectura manteniendo escritura permite que cinco acciones persistan antes de devolver403. | `Contacts::boot` exige lectura antes de ejecutar acciones. Seis recorridos comprobados; `saveContact` ya se frenaba antes. Es coherencia de UI, sin escalada de permiso de escritura. |

Los índices publicados y las FKs no se modifican. La medición puntual del conteo
fue 0,1240237s antes y 0,0496152s después; no representa un benchmark general.
Los planes y carga se conservan junto a los stdout reales.

El rojo conjunto acreditó seis fallos /22 aserciones /27,03s. B-02 tiene además
un rojo aislado del caso exacto: un fallo /una aserción /7,87s. El verde de las
tres clases afectadas terminó con 42 pruebas /531 aserciones /142,36s.

La regresión completa posterior pasó 88 pruebas /957 aserciones /195,29s;
Pint global exit0. Después de esa suite se retiró únicamente `required` del
textarea HTML para permitir el formulario de solo texto. El build de ese markup
con Node22 pasó: 58 módulos, 3,56s. El primer build encontró `spawn EPERM` del
sandbox; se conserva el fallo y la ejecución autorizada exit0. El recorrido de
solo texto desde navegador se exige en QA; el backend de ese caso ya está probado.

Ventana de corrección: 2026-10-22T20:32:55Z–20:45:47Z, cerrada. Tokens, euros y
horas IA reales desconocidos; no se convierte la duración de reloj en consumo.
