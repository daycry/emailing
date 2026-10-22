---
id: ADR-004
titulo: FastCGI privado y origen confiable de enlaces secretos
estado: "aceptada (validada: revisión de dos lentes, 2026-10-22, intento 2)"
fecha: 2026-10-22
iniciativa: plataforma-emailing
---

# ADR-004: FastCGI privado y origen confiable de enlaces secretos

## Contexto

La revisión de fase 1 detectó que PHP-FPM atendía TCP en la red compartida del laboratorio y que las notificaciones construían enlaces secretos con el Host de la petición. El acceso a servicios compartidos requiere conservar conectividad saliente de PHP y los workers. Fuente: [revisión, intento 1](../../roadmap/2026-10-21-plataforma-emailing/tasks.md).

## Decisión

PHP-FPM escucha exclusivamente en un socket Unix sobre un volumen propio del proyecto Compose, montado solo por PHP y Nginx. PHP sigue ejecutándose como `www-data`; Nginx monta el volumen en lectura. El directorio pertenece a PHP y el socket permite la conexión de ambos UID mediante modo 0666, sin estar disponible en redes o en otros servicios sin ese volumen.

Los enlaces de recuperación e invitación combinan el `APP_URL` de configuración confiable con la ruta relativa generada por Laravel; ninguna cabecera de petición elige su origen. El worker consume la cola efectiva de configuración, sin duplicarla en sus argumentos de arranque.

Las rutas web habilitan `AuthenticateSession` de Laravel. El `SessionGuard` instalado guarda el hash de contraseña al autenticar, por lo que su comprobación rechaza también las sesiones de registro/login que todavía no navegaron al panel antes del reset. La rotación del remember_token por Fortify complementa esta comprobación.

## Alternativas descartadas

- **FPM TCP con una IP fija de Nginx:** acopla la configuración a direcciones de contenedores y mantiene un endpoint de ejecución en red.
- **Retirar PHP de la red de infraestructura:** impediría su conexión a MySQL, RabbitMQ y SMTP, o exigiría otra capa de encaminamiento.
- **Confiar solo en Host o cabeceras de proxy para enlaces secretos:** la aplicación recibe peticiones con cabeceras controladas por el cliente y las pruebas locales también deben proteger el origen.

## Consecuencias

Cada proyecto Compose mantiene su propio socket, volumen y alias HTTP, conservando las conexiones salientes existentes. La configuración `APP_URL` debe apuntar al origen público correcto de cada entorno. Las regresiones comprueban rutas HTTP, notificaciones, productor y consumidor real con una cola alternativa; las comprobaciones operativas verifican Nginx y el rechazo TCP de FPM.

## Estado

Aceptada tras la revisión de dos lentes, intento 2, con comprobaciones operativas y regresiones HTTP; QA de fase 1 termina verde en ambos perfiles. El cierre documental actualiza las guías de integración. No promueve ADR de las fases de entrega pendientes.
