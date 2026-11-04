# Menú local500: aplicar las migraciones pendientes

El acceso a la aplicación funcionaba, pero Team, Contactos, Plantillas y
Campañas producían500. El log mostraba `SQLSTATE[42S02]` por ausencia de las
tablas correspondientes en la base operativa. No se publica el log original,
usuarios, respuestas HTML, credenciales o datos de negocio.

## Diagnóstico y reparación

La configuración cargada por Laravel y la identidad SQL real confirmaron la
base operativa y un usuario distinto del de pruebas. No había caché de
configuración. `migrate:status` informó cuatro migraciones aplicadas y25
pendientes. La falta de esquema explica los cuatro errores; no depende de
Google OAuth, SMTP ni el proveedor de email.

Se revisó el inventario de migraciones y se aplicaron únicamente las pendientes
mediante el migrador Laravel. Se verificó de nuevo la identidad real antes de
escribir y el plan4/25 observado; el procedimiento rechazaba otro destino o un
plan cambiado. No se utilizaron fresh, refresh, rollback, seed ni borrado de
tablas existentes. [Recibo de aplicación](schema-repair.public.json).

Resultado:exit0, cero migraciones pendientes y las cuatro tablas antes ausentes
presentes. Los recuentos de usuarios, cuentas, membresías, auditoría y sesiones
coincidieron antes/después. No cambió código ni ninguna de las561 fuentes de
la regresión en curso; ésta mantiene su base de pruebas independiente.

## Verificación y límites

Los13 GET del [recibo de comprobación](menu-check.public.json) devolvieron200
desde el kernel HTTP Laravel, incluyendo todas las entradas del menú y las
importaciones. El diagnóstico vinculó un administrador existente sólo a su
proceso; sesión y caché fueron en memoria y las posibles escrituras de negocio
se envolvieron en una transacción revertida. No creó credenciales, sesiones
persistentes ni usuarios y no exportó los cuerpos de respuesta.

El acceso de navegador del agente no estaba disponible; no se afirma una prueba
autenticada a través de Apache ni se modifica la sesión abierta del usuario.
Éste debe recargar su página para comprobarla en su navegador. Una petición HTTP
sin sesión a `/team` sigue redirigiendo al login, como corresponde.

La receta de migración ya está en la
[instalación Laragon](../../../../../../manual/guia-completa.md#instalación-nativa-en-windows-con-laragon)
y en la [actualización](../../../../../../manual/guia-completa.md#actualizar-sin-confundir-código-con-datos).
La [guía de desarrollo](../../../../../../aplicacion/desarrollo.md#si-el-acceso-funciona-pero-el-menú-devuelve500)
explica cómo reconocer el caso. La reparación local no cierra T-12/T-19/T-20
ni acredita Google, clientes externos o recuperación de copias.
