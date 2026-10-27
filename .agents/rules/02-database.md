# Regla 02 — Base de datos (Mono-Negocio)

**Nivel de aplicación:** siempre que se cree una migración, modelo, query o controlador que toque datos.

Modelo relacional completo, entidades y relaciones → `docs/BASE_DATOS.md`

## Modelo Mono-Negocio (NO Multi-Tenant)
Este sistema opera para un único negocio (SaborCaliFit). **No existe** columna `business_id`, tabla `businesses` ni aislamiento entre tenants. Todas las queries operan directamente sobre la instancia única de la base de datos.

## Motor de Recetas e Inventario
- Las tablas de recetas (`recetas`, `receta_detalle`) definen los ingredientes y cantidades por porción en gramos/ml.
- Las tablas de adiciones (`adiciones`, `adicion_detalle`, `producto_adicion`) definen extras reutilizables con precio y receta propia.
- `venta_adicion` registra el snapshot inmutable de adiciones cobradas por cada línea de venta.
- Toda venta de un plato con receta valida disponibilidad de insumos (base + adiciones) bajo `lockForUpdate`.

## Reglas de integridad
- Operaciones críticas de inventario y ventas SIEMPRE dentro de una transacción DB.
- **Cero Stock Negativo:** Prohibido. Si falta un solo gramo, la venta completa se rechaza.
- Usar bloqueo pesimista (`lockForUpdate`) en la confirmación de venta para evitar condiciones de carrera.
- **Unidades base:** Gramos (`g`) para sólidos, Mililitros (`ml`) para líquidos, Unidades (`UNIDAD`) para suplementos y platos.
- **Factor de conversión:** 1 libra = 500 g (regla del negocio de SaborCaliFit).
- **Exclusión mutua:** Frijoles y lentejas son mutuamente excluyentes en recetas base.
- **Inmutabilidad de precios:** Los precios cobrados se congelan en `sale_details` y `venta_adicion`. Futuras ediciones del catálogo no afectan ventas históricas.
- No crear tablas nuevas sin justificar la necesidad en `docs/DECISIONES_TECNICAS.md`.
