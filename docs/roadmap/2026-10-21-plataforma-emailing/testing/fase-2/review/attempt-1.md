# Revisión de dos lentes — intento 1 · T-09 / fase 2

**Resultado: correcciones requeridas.** Tres Important abiertos; cero Critical.
La revisión no sustituye a QA de navegador, todavía pendiente.

| Campo | Evidencia |
|---|---|
| Base / checkpoint | `6056343` / `db18d4a7499fa9b1c9d2baae9ac12f74d9c81966` |
| Diff | 54 archivos, 254.731 bytes, completo y congelado |
| SHA256 | `46b233731c5e7d2fff50d34d4dedf20159485a6f83bab92bf58f10026172403f` |
| Patch / manifiesto | [attempt-1.patch](attempt-1.patch) · [hashes](attempt-1.patch.json) |
| Alcance | exit 0; cero fuera de alcance, avisos o exclusiones de usuario |
| Lentes | A+B+C+D, contexto fresco y solo lectura |
| Degradación | Subagentes genéricos con rol reviewer: el runtime no expone `subagent_type`; modelo heredado. Tier frontmatter resuelto, sin simular otro motor. |
| Paralelismo | A/B/C en paralelo; D al liberarse un slot, sobre el mismo snapshot. |

## Veredictos fusionados

| ID | Grado | Tarea | Ubicación | Escenario y comprobación | Estado |
|---|---|---|---|---|---|
| B-01 | Important | T-07 | `app/Support/MailInput.php:19`; `TemplateService.php:99` | HTML válido compuesto solo por imagen y texto opcional vacío se guarda/previsualiza. El fallback sigue vacío y el transporte rechaza `text`. Reproducción PHP pura con métodos reales, antes de SMTP o persistencia. | Pendiente |
| B-02 | Important | T-07 | `app/Support/TemplateRenderer.php:45` | Token raw en href y token escrito como entidades en texto producen conteos iguales; se interpola el atributo prohibido. Reproducción independiente de C y B con el renderer real. Defecto contractual, sin vulnerabilidad adicional acreditada. | Pendiente |
| D-01 | Important | T-05 | `app/Services/ContactService.php:41`; `app/Models/ContactList.php:14`; migración `2026_10_22_000002_create_contacts_and_lists.php:37` | Conteo correlacionado por list_id carece de índice con ese prefijo. EXPLAIN real: DEPENDENT SUBQUERY, pivot type=index, possible_keys=null, hash join. Con diez listas de 1.000 afiliaciones cada una, los diez conteos recorren 100.000 entradas globales frente a 10.000 por rangos. Cota analítica, sin tiempo medido en tablas vacías. | Pendiente |

Reproducción B-02:

```html
<a href="{{ contact.name }}">&#123;&#123; contact.name &#125;&#125;</a>
```

Con `contact.name=javascript:alert(1)`, el renderer acepta y sustituye href,
dejando el texto codificado. El autor ya puede escribir enlaces arbitrarios y
la preview elimina href; se clasifica como corrección del contrato de variables,
sin presentarlo como una escalada de seguridad.

## Alcance de las conclusiones

A recorrió cada criterio y evidencia de T-05/T-06/T-07/T-08: inicialmente no
reportó gaps de requisitos. B confirmó B-01 y, ante evidencia nueva enviada por
C, B-02. La fusión conserva ambos aunque el primer dictamen de A fuera verde.
C no confirmó vulnerabilidades de SQL/comandos, sesión/autorización, secretos,
traversal, SSRF/TLS o preview. D no halló otro problema de rendimiento en lotes,
renderizado o transporte.

Las cuatro lentes leyeron el patch completo por bloques y comprobaron los 54
hashes de fuentes. A/B/C leyeron evidencia original; B/C ejecutaron probes PHP
puros. D usó guard de base/usuario, SELECT, SHOW INDEX y EXPLAIN en
`emailing_testing`, sin DML ni fixtures. No hubo suites concurrentes, navegador,
migraciones ni cambios de fuente durante la revisión.

Las pruebas del checkpoint eran 82/899, con Pint y build verdes. Eso no elimina
los escenarios adicionales hallados ni acredita QA. El candidato de fechas
antiguas no se reportó sin evidencia suficiente de fallo MySQL; no se convirtió
una sospecha en hallazgo. Una comprobación adicional del coordinador sobre
efectos antes de403 queda como candidato, no como vulnerabilidad confirmada.

## Medición y siguiente paso

Ventana de revisión 2026-10-22T20:10:24Z–20:31:32Z cerrada; consumo real desconocido
(tokens, euros y horas IA null). La duración de reloj no se imputa como consumo.
Corresponde reproducir, corregir o rebatir con evidencia, ejecutar las pruebas
afectadas y revisar el nuevo diff. T-09 permanece en progreso.
