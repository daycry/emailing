Confirmado: `WorkerRecoveryTest.php:515–524` ejecuta **materialización SQL → baja pública → publicación AMQP → consumidor**.

Mi ✓ se refiere al trabajo SQL durable creado antes de la baja. **No acredita baja después de publicar en AMQP.** No he identificado una prueba del snapshot con el orden **publish AMQP → baja → consumer**.

Si «después de encolar» exige publicación AMQP previa, falta esa evidencia exacta para cerrar T-10. Es una precisión del alcance probatorio; no demuestra un defecto de runtime ni cambia la gravedad o el intento.
