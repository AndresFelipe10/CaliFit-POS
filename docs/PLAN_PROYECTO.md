# Plan del Proyecto — CaliFit Sistema POS

> Esta es la **fuente principal de verdad** del proyecto. Ante cualquier duda de alcance u objetivo, este documento prevalece.

## Objetivo
Diseñar y desarrollar un software de Punto de Venta (POS) a la medida para **SABORCALIFIT | BOCHALEMA**, un negocio con modelo mixto de suplementación deportiva y restaurante de alimentación saludable ubicado en **Arbolatta Mall, Cl. 48 #106-86, Cali, Valle del Cauca, Colombia**.

El sistema gestiona de manera centralizada ventas, inventario con motor de recetas por porciones, adiciones reutilizables con precio, salón de mesas, comandas de cocina, despachos a domicilio/para llevar, cuadre de caja adaptativo, impresión térmica de 80 mm y reportes gerenciales.

## Contexto
Software a la medida (mono-negocio), construido con estándares profesionales de producción. **No es un SaaS multi-tenant.** Toda la instancia pertenece exclusivamente a SaborCaliFit. Los flujos de registro público, creación de múltiples negocios, planes de suscripción y cobros mensuales han sido eliminados.

**Línea de Soporte:** +57 312 770 4455
**Zona Horaria Canónica:** `America/Bogota` (UTC-5)

## Modelo de Negocio Mixto
1. **Tienda de Suplementación Deportiva y Vitaminas** — Productos terminados de venta directa por unidad y SKU. Descuento de stock unitario directo (`stock - cantidad_vendida`).
2. **Restaurante / Cafetería Saludable** — Almuerzos fit con porciones pesadas, batidos proteicos, bowls, servicio a la mesa en salón, pedidos para llevar y despachos a domicilio. Descuento de inventario mediante el motor de recetas y adiciones.

## Alcance
- Aplicación web real, multiusuario, mono-negocio, persistente, segura, desplegable.
- Catálogo unificado con dos modalidades de venta (directa y por receta con adiciones).
- Motor de recetas con porciones en gramos/mililitros y adiciones reutilizables con precio.
- Núcleo funcional independiente de cualquier proveedor de IA.
- Módulo adicional de consultas en lenguaje natural, opcional y desacoplado.
- Fuera de alcance en esta etapa: apps móviles nativas, microservicios, frontend SPA (React/Vue/Angular), múltiples proveedores de IA simultáneos, facturación electrónica DIAN.

## Usuarios y Roles
- **Dueño / Administrador (`admin`):** Control total del negocio, gestión del equipo interno y colaboradores (`/users`), catálogo integral (suplementos, platos, insumos), fórmulas de recetas, adiciones reutilizables, compras/gastos, proveedores, arqueos de caja, reportes financieros y asistente de IA.
- **Empleado / Cajero / Mesero (`employee`):** Operación diaria de Punto de Venta (POS), mapa de mesas (Salón), comandas de cocina/barra, tablero de despachos (domicilios/para llevar), clientes y cuadre de caja de su turno. Acceso bloqueado con 403 a márgenes de ganancia, costos y rutas administrativas.

## Módulos
1. Autenticación (registro cerrado, cambio forzado de contraseña inicial)
2. Gestión de Equipo y Colaboradores (`/users` protegido por rol admin)
3. Categorías
5. Catálogo Unificado (Suplementos + Insumos + Platos/Preparaciones)
6. Motor de Recetas y Porciones (con factor de conversión libras→gramos)
7. Adiciones Reutilizables con Precio y Receta de Insumos
8. Inventario (con trazabilidad por `inventory_movements`)
9. Punto de Venta (POS) con selector de adiciones, pagos mixtos y vueltos
10. Salón: Mapa táctil de mesas (`LIBRE`, `OCUPADA`, `EN COBRO`)
11. Comandas de cocina/barra por tandas con ticket térmico 80 mm
12. Despacho: Tablero Kanban (Domicilios y Para Llevar, flete de envío, contraentrega)
13. Clientes
14. Proveedores
15. Gastos y facturas de compra (exclusivo Administrador)
16. Cuadre de Caja Diario adaptativo (Salón, Domicilios, Para Llevar)
17. Dashboard gerencial y financiero
18. Reportes (ventas, inventario, ranking de productos)
19. Panel inteligente de inventario y alertas (reglas determinísticas)
20. Impresión Térmica 80 mm (Comanda cocina, Tirilla despacho, Pre-cuenta, Comprobante de venta)
21. Módulo de consultas en lenguaje natural (IA, opcional)

## Motor de Recetas — Reglas de Negocio Clave
- **Unidades base internas:** Gramos (`g`) para sólidos/carnes/granos, Mililitros (`ml`) para líquidos/aceites, Unidades (`UNIDAD`) para suplementos y platos.
- **Factor de conversión:** 1 libra = 500 g (regla del negocio).
- **Porciones base por plato:** Carne grande 200 g, carne pequeña 120 g, arroz 62.5 g, frijoles 23.81 g, lentejas 14.29 g.
- **Exclusión mutua:** En platos con frijoles, lentejas = 0 g y viceversa.
- **Adiciones:** Extras reutilizables con precio y receta propia. Se vinculan a platos mediante tabla pivote `producto_adicion`.
- **Fórmula de precio:** `Precio Total = (Precio Plato × Cantidad) + Σ(Precio Adición_i × Cantidad Adición_i)`.
- **Fórmula de consumo:** `Consumo Insumo = (Cant. Vendida × Cant. Base Insumo) + Σ(Cant. Adición_k × Consumo Insumo en Adición_k)`.
- **Cero Stock Negativo:** Validación bajo `lockForUpdate` en `DB::transaction`. Si falta un solo gramo, la venta se rechaza en bloque.
- **Anulación simétrica:** Restaura exactamente los insumos del plato base y sus adiciones (`DEVOLUCION_ANULACION`).

## Impresión Térmica 80 mm
- **Comanda de cocina/barra:** Plato principal, adiciones seleccionadas, observaciones. Sin precios ni valores fiscales.
- **Tirilla de despacho:** Cliente, dirección, teléfono, adiciones, desglose de ítems y monto de contraentrega.
- **Pre-cuenta y Comprobante de venta:** Desglose completo, propina voluntaria (10% Ley 1935/2018), INC (8% Art. 512-1 E.T.), pagos mixtos.
- **Logo SaborCaliFit y Contraste:** Imagotipo oficial de alta legibilidad, contraste térmico puro `#000000`.
- **Leyenda legal:** "Comprobante interno de venta. No reemplaza la factura electrónica de venta ante la DIAN." (Consumidor Final: 222222222222).

## Arquitectura general
Resumen ejecutivo: Docker (`califit_app` + `califit_nginx` + `califit_db`) en desarrollo; en producción, Internet → HTTPS → Nginx → PHP-FPM/Laravel → MySQL. Detalle completo → `docs/ARQUITECTURA.md`.

## Roadmap
Fases reorganizadas por funcionalidad del sistema CaliFit → `docs/ROADMAP.md`.

## Criterios de aceptación globales
El proyecto se considera exitoso cuando, con datos reales de SaborCaliFit, se puede demostrar el flujo completo: login → gestión de catálogo unificado (suplementos + insumos + platos) → creación de recetas con porciones en gramos → configuración de adiciones reutilizables → venta POS con selector de adiciones y deducción atómica de inventario → salón con mapa de mesas → comandas por tandas → despacho de domicilios con flete y contraentrega → cuadre de caja adaptativo → dashboard con métricas protegidas → reportes gerenciales → impresión térmica 80 mm con marca CaliFit → módulo IA en modo solo lectura con aislamiento → sistema operando sin IA → sistema desplegable sin herramientas técnicas del lado del cliente → proveedor de IA reemplazable sin reescribir el núcleo.
