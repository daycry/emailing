---
id: GOT-012
tipo: gotcha
area: testing
estado: aceptada (validada: revisión de dos lentes, 2026-10-25, intento 2)
fuente: docs/roadmap/2026-10-24-mcp-google/tasks.md
fecha: 2026-10-25
---

# Probar el bloqueo exterior y el bypass efectivo de Playwright

## Síntoma

Un fake por dominio no interceptaba la redirección de autorización iniciada en
una URL local. Tras añadir denyProxy, la página quedaba vacía en el inicio local.
Los fallos eran del límite del arnés; no justificaban cambiar JWT, CSRF o admisión.

## Causa raíz

La interceptación debe controlar el 302 original antes de que el navegador lo
siga. Además, en APIRequestContext de Playwright 1.64.1 el bypass con puerto no
coincide con el host loopback y el fetch local llega al proxy: 502 con cuerpo
vacío. El mismo listener retorna 200 con bypass por IP. Una navegación HTTPS
denegada también puede terminar su error-page después de rechazar la promesa.

## Qué hacer

Interceptar el inicio local con `route.fetch({maxRedirects:0})`; validar el destino
y sustituir sólo la frontera sintética de autorización. Usar un proxy propio que
no reenvíe ni resuelva DNS y niegue HTTP/CONNECT, service workers bloqueados y
bypass loopback explícito. Probar el bloqueo HTTP/HTTPS y el acceso local reales.
Usar página temporal para el probe HTTPS y cerrarla antes del flujo local.
Vincular el denyProxy antes de crear fixtures o arrancar PHP; cerrar recursos propios.

## Evidencia

Prueba causal privada `mcp-google-proxy-cause-20261025T005857384571Z`: un proceso,
dos listeners propios, sin Laravel/SQL/redirects; port→502 e IP→200. E2E-01 aislado
verde y batería `mcp-google-e2e-third-20261025T010007118186Z`: 3 passed (48.4s),
fuentes inmutables y bloqueo exterior probado en cada escenario. Regresión pública:
`tests/e2e/mcp-google.spec.mjs`, hook de red y E2E-01–03. Es Chromium sobre PHP
cli-server nativo; no acredita Google real, Apache/Nginx, Docker o consentimiento MCP.
