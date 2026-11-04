# Perfil de navegador Google/MCP

Vista previa y pruebas de preparación desde la raíz:

```powershell
python -B scripts/browser_lab/preview.py
python -B scripts/browser_lab/controller.py
python -B -m unittest discover -s tests/browser_lab -p 'test_*.py'
```

La [guía](../../docs/desarrollo/navegador-google-docker.md) describe Nginx/FPM,
MySQL propio, red interna, fixtures, configuración y límites. El controlador
requiere ejecución explícita, tres imágenes y una regresión completa del código
exacto. No se ejecuta Docker ni SQL desde la vista previa.
Los tests locales usan guardas puras, procesos simulados y archivos sintéticos;
no acreditan los nueve E2E, Google real, TLS o aceptación de T-12.
