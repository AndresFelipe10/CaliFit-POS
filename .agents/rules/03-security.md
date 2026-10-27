# Regla 03 — Seguridad

**Nivel de aplicación:** siempre.

Checklist completo y contexto → `docs/SEGURIDAD.md`

## Prohibiciones absolutas
- Prohibido `$guarded = []` en modelos Eloquent; usar siempre `$fillable` explícito o Form Requests.
- Prohibido interpolar input de usuario en queries crudas; usar exclusivamente Eloquent/Query Builder parametrizado.
- Prohibido `{!! !!}` en Blade con contenido proveniente de input de usuario.
- Prohibido omitir `@csrf` en formularios.
- Prohibido dejar `APP_DEBUG=true` en cualquier configuración de producción.
- Prohibido loguear contraseñas, tokens, API keys o datos sensibles.
- Prohibido hardcodear secretos; todo secreto vive en `.env`, nunca en el repositorio.

## Obligaciones
- Toda ruta de escritura y toda ruta sensible protegida por Policy o Gate correspondiente al rol.
- Rate limiting obligatorio en login y en el endpoint de consultas del asistente IA.
- Validación exhaustiva vía Form Requests en toda entrada de usuario (incluyendo adiciones con cantidades y precios).
- Páginas de error genéricas en producción; nunca stack traces visibles al usuario final.
- RBAC tripartito (SuperAdmin, Administrador, Empleado) verificado en backend, no solo en UI.

## Específico del motor de recetas
- Las cantidades por porción deben ser positivas y coherentes con la unidad base del insumo.
- Las adiciones solo se vinculan a productos con `tipo_producto = PLATO`.
- La deducción atómica bajo `DB::transaction` con `lockForUpdate` es obligatoria para toda venta con receta.
- Los precios cobrados se congelan en snapshots (`sale_details`, `venta_adicion`); nunca se leen de FK dinámica en vistas de histórico.

## Específico del módulo IA
- La whitelist de intents (ver `.agents/rules/04-ai.md`) es la defensa real contra prompt injection, no las instrucciones dadas al modelo.
- Todo intent no reconocido se rechaza en código, nunca se ejecuta "por si acaso".
