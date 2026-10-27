# Regla 05 — Testing

**Nivel de aplicación:** al cierre de cada fase del roadmap y ante cualquier cambio en modelos, controladores, Policies o el módulo IA.

## Reglas
- PROHIBIDO avanzar de fase si existen tests fallando.
- Los tests de **deducción atómica de inventario** (receta base + adiciones) son obligatorios desde la Fase 7 en adelante.
- Todo nuevo endpoint de escritura requiere al menos un feature test de autorización (rol correcto/incorrecto).
- Tests de concurrencia de inventario deben verificar que con stock límite de un insumo, dos ventas simultáneas resulten en una aprobada y una rechazada (cero stock negativo).
- Tests de anulación deben verificar la restauración simétrica de insumos (plato base + adiciones).
- El módulo IA requiere, como mínimo y sin excepción, los siguientes casos de prueba:
  - Pregunta válida → intent correcto → datos correctos.
  - Pregunta pidiendo datos sensibles → rechazada sin ejecutar consulta.
  - Intent fuera de whitelist o entrada malformada → rechazada con mensaje controlado, nunca error 500.
  - Proveedor IA caído/timeout → fallback activado correctamente.
- Ante un test fallido: detener el desarrollo de la fase actual, diagnosticar, corregir, volver a ejecutar toda la suite antes de continuar.
