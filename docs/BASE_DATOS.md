# Base de Datos — CaliFit Sistema POS

## Motor
MySQL 8.4, con integridad referencial, índices, constraints, transacciones ACID y normalización apropiada.

## Modelo Mono-Negocio
Este sistema NO utiliza multi-tenancy. No existe columna `business_id` ni tabla `businesses`. Toda la instancia de base de datos pertenece a un único negocio: **SABORCALIFIT | BOCHALEMA**.

---

## Entidades del Sistema

| Tabla | Propósito |
|---|---|
| `roles` | Roles del sistema (`admin`, `employee`) |
| `users` | Cuentas de usuario (login, FK role_id) |
| `products` | Catálogo unificado: suplementos, insumos de cocina, platos/preparaciones |
| `categories` | Categorías de producto |
| `recetas` | Recetas (fórmulas) de preparación vinculadas a platos |
| `receta_detalle` | Ingredientes y cantidades por porción de cada receta |
| `adiciones` | Extras reutilizables con precio (carne adicional, arroz extra, etc.) |
| `adicion_detalle` | Insumos consumidos por cada adición |
| `producto_adicion` | Tabla pivote: qué adiciones aplican a qué platos |
| `customers` | Clientes del negocio |
| `suppliers` | Proveedores de insumos y suplementos |
| `sales` | Cabecera de venta (POS, Salón, Domicilio, Para Llevar) |
| `sale_details` | Detalle de venta (líneas de productos/platos con precio snapshot) |
| `sale_payments` | Métodos de pago y vueltos aplicados por venta |
| `sale_detail_additions` | Snapshot inmutable de adiciones cobradas por línea (alias `venta_adicion`) |
| `expenses` | Facturas de compra y gastos operativos |
| `inventory_movements` | Trazabilidad de movimientos de stock con tipos de operación |
| `restaurant_tables` | Catálogo y estado de mesas de salón |
| `restaurant_orders` | Comandas y pedidos (salón/domicilio/para llevar) |
| `restaurant_order_items` | Platos y productos por tanda de comanda |

---

## Diccionario de Datos Detallado

### `users`
| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Identificador único |
| `role_id` | BIGINT UNSIGNED FK | Relación con la tabla `roles` (`admin`, `employee`) |
| `name` | VARCHAR(255) | Nombre completo |
| `email` | VARCHAR(255) UNIQUE | Correo electrónico |
| `password` | VARCHAR(255) | Hash de contraseña |
| `must_change_password` | BOOLEAN DEFAULT false | Forzado de cambio de contraseña |
| `is_active` | BOOLEAN DEFAULT true | Estado de activación |
| `timestamps` | — | created_at, updated_at |

### `productos`
| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Identificador único |
| `nombre` | VARCHAR(255) | Nombre del producto/insumo/plato |
| `descripcion` | TEXT NULLABLE | Descripción opcional |
| `sku` | VARCHAR(50) UNIQUE | Código único de producto |
| `tipo_producto` | ENUM('INSUMO', 'PREPARACION', 'PLATO', 'SUPLEMENTO') | Clasificación del ítem |
| `unidad_base` | ENUM('UNIDAD', 'GRAMO', 'MILILITRO') | Unidad interna de medición |
| `category_id` | FK categories NULLABLE | Categoría del producto |
| `stock` | DECIMAL(12,3) DEFAULT 0.000 | Stock actual en unidad base |
| `stock_minimo` | DECIMAL(12,3) DEFAULT 0.000 | Umbral mínimo para alertas |
| `precio_compra` | DECIMAL(12,2) NULLABLE | Precio de compra por unidad base |
| `precio_venta` | DECIMAL(12,2) | Precio de venta al público |
| `activo` | BOOLEAN DEFAULT true | Estado de activación |
| `timestamps` | — | created_at, updated_at |

**Notas sobre `tipo_producto`:**
- `INSUMO`: Materia prima de cocina (carnes, granos, verduras, aceites). Stock en gramos/mililitros.
- `PREPARACION`: Producto intermedio elaborado en cocina (salsas, bases).
- `PLATO`: Preparación compuesta con receta y adiciones. Descuenta insumos via motor de recetas.
- `SUPLEMENTO`: Producto terminado de venta directa. Stock en unidades.

### `recetas`
| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Identificador único |
| `producto_plato_id` | FK productos | Plato al que pertenece esta receta |
| `nombre` | VARCHAR(255) | Nombre descriptivo de la receta |
| `activa` | BOOLEAN DEFAULT true | Estado de activación |
| `timestamps` | — | created_at, updated_at |

**UNIQUE:** `(producto_plato_id)` — Un plato tiene exactamente una receta activa.

### `receta_detalle`
| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Identificador único |
| `receta_id` | FK recetas CASCADE | Receta padre |
| `ingrediente_id` | FK productos RESTRICT | Insumo utilizado |
| `cantidad_por_porcion` | DECIMAL(12,3) | Gramos/ml consumidos por cada porción vendida |
| `unidad` | ENUM('GRAMO', 'MILILITRO') | Unidad de medida del ingrediente |
| `timestamps` | — | created_at, updated_at |

**UNIQUE:** `(receta_id, ingrediente_id)` — Un ingrediente aparece una sola vez por receta.

### `adiciones`
| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Identificador único |
| `nombre` | VARCHAR(255) | Nombre descriptivo (ej. "Carne adicional 120 g") |
| `precio` | DECIMAL(12,2) | Precio unitario de venta al público |
| `activo` | BOOLEAN DEFAULT true | Estado de activación |
| `timestamps` | — | created_at, updated_at |

### `adicion_detalle`
| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Identificador único |
| `adicion_id` | FK adiciones CASCADE | Adición padre |
| `ingrediente_id` | FK productos RESTRICT | Insumo consumido |
| `cantidad` | DECIMAL(12,3) | Gramos/ml consumidos por cada unidad de esta adición |
| `timestamps` | — | created_at, updated_at |

**UNIQUE:** `(adicion_id, ingrediente_id)`.

### `producto_adicion` (Tabla Pivote)
| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Identificador único |
| `producto_id` | FK productos RESTRICT | Plato al que aplica |
| `adicion_id` | FK adiciones RESTRICT | Adición permitida |

**UNIQUE:** `(producto_id, adicion_id)` — Una adición se vincula una sola vez a cada plato.

### `sale_detail_additions` (Snapshot inmutable — alias `venta_adicion`)
| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Identificador único |
| `sale_detail_id` | FK sale_details CASCADE | Línea de venta padre |
| `adicion_id` | FK adiciones RESTRICT | Adición cobrada |
| `cantidad` | DECIMAL(12,3) | Cantidad de esta adición en la venta |
| `precio_unitario` | DECIMAL(12,2) | Precio al momento de la venta (snapshot) |
| `subtotal` | DECIMAL(12,2) | cantidad × precio_unitario |
| `timestamps` | — | created_at, updated_at |

### `customers`
| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Identificador único |
| `nombre` | VARCHAR(150) | Nombre completo |
| `document` | VARCHAR(30) UNIQUE | Documento de identidad (formato DIAN) |
| `email` | VARCHAR(150) NULLABLE | Correo electrónico |
| `telefono` | VARCHAR(20) NULLABLE | Teléfono de contacto |
| `direccion` | VARCHAR(255) NULLABLE | Dirección |
| `is_active` | BOOLEAN DEFAULT true | Estado de activación |
| `timestamps` | — | created_at, updated_at |

### `sales`
| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Identificador único |
| `user_id` | FK users | Cajero/mesero que registró la venta |
| `customer_id` | FK customers NULLABLE | NULL = Consumidor Final |
| `customer_name` | VARCHAR(150) | Snapshot inmutable del comprador |
| `customer_document` | VARCHAR(30) | Snapshot inmutable del documento |
| `invoice_number` | VARCHAR(30) UNIQUE | Consecutivo (`FAC-000001`) |
| `restaurant_order_id` | FK restaurant_orders NULLABLE | Comanda asociada |
| `order_type` | ENUM('retail', 'table', 'delivery', 'takeout') NULLABLE | Canal de venta |
| `delivery_fee` | DECIMAL(12,2) DEFAULT 0.00 | Flete de envío (CHECK ≥ 0) |
| `service_fee` | DECIMAL(12,2) DEFAULT 0.00 | Propina voluntaria (CHECK ≥ 0) |
| `tax_inc` | DECIMAL(12,2) DEFAULT 0.00 | INC 8% (CHECK ≥ 0) |
| `subtotal` | DECIMAL(12,2) | Suma de líneas |
| `discount` | DECIMAL(12,2) DEFAULT 0.00 | Descuento en pesos |
| `discount_percentage` | DECIMAL(5,2) DEFAULT 0.00 | Porcentaje de descuento (CHECK 0-100) |
| `total` | DECIMAL(12,2) | subtotal - discount + delivery_fee + service_fee + tax_inc |
| `payment_method` | ENUM('cash', 'card', 'transfer', 'other', 'mixed') | Método de pago principal |
| `status` | ENUM('completed', 'cancelled') DEFAULT 'completed' | Estado |
| `notes` | VARCHAR(500) NULLABLE | Notas de la venta |
| `date` | DATETIME | Fecha/hora de la venta |
| `timestamps` | — | created_at, updated_at |

### `sale_details`
| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Identificador único |
| `sale_id` | FK sales CASCADE | Venta padre |
| `product_id` | FK productos RESTRICT | Producto/plato vendido |
| `quantity` | DECIMAL(12,3) | Cantidad vendida |
| `unit_price` | DECIMAL(12,2) | Precio unitario snapshot |
| `subtotal` | DECIMAL(12,2) | quantity × unit_price |
| `timestamps` | — | created_at, updated_at |

### `sale_payments`
| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Identificador único |
| `sale_id` | FK sales CASCADE | Venta asociada |
| `method` | ENUM('cash', 'card', 'transfer', 'other') | Método de pago |
| `amount` | DECIMAL(12,2) | Monto pagado (CHECK > 0) |
| `reference` | VARCHAR(60) NULLABLE | Referencia de transacción |
| `cash_received` | DECIMAL(12,2) NULLABLE | Efectivo recibido |
| `change_given` | DECIMAL(12,2) NULLABLE | Vuelto entregado |
| `timestamps` | — | created_at, updated_at |

### `expenses`
| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Identificador único |
| `supplier_id` | FK suppliers NULLABLE | Proveedor asociado |
| `invoice_number` | VARCHAR(50) NULLABLE | Número de factura |
| `issue_date` | DATE | Fecha de emisión |
| `due_date` | DATE NULLABLE | Fecha de vencimiento |
| `category` | ENUM('merchandise', 'utilities', 'rent', 'supplies', 'payroll', 'other') | Categoría |
| `description` | VARCHAR(255) NULLABLE | Descripción |
| `amount` | DECIMAL(12,2) | Monto (CHECK > 0) |
| `status` | ENUM('paid', 'pending') DEFAULT 'pending' | Estado de pago |
| `paid_at` | DATE NULLABLE | Fecha de pago |
| `payment_method` | ENUM('cash', 'card', 'transfer', 'other') NULLABLE | Método de pago |
| `attachment_path` | VARCHAR(255) NULLABLE | Ruta hash del adjunto |
| `attachment_original_name` | VARCHAR(255) NULLABLE | Nombre original del adjunto |
| `created_by` | FK users | Usuario que registró |
| `deleted_at` | TIMESTAMP NULLABLE | Soft delete |
| `timestamps` | — | created_at, updated_at |

### `inventory_movements`
| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Identificador único |
| `product_id` | FK productos RESTRICT | Producto afectado |
| `type` | ENUM('SALIDA_VENTA', 'ENTRADA_COMPRA', 'MERMA', 'AJUSTE', 'DEVOLUCION_ANULACION') | Tipo de movimiento |
| `quantity` | DECIMAL(12,3) | Cantidad del movimiento |
| `previous_stock` | DECIMAL(12,3) | Stock antes del movimiento |
| `new_stock` | DECIMAL(12,3) | Stock después del movimiento |
| `reason` | VARCHAR(255) NULLABLE | Motivo del movimiento |
| `user_id` | FK users | Usuario responsable |
| `sale_id` | FK sales NULLABLE | Venta asociada (si aplica) |
| `date` | DATETIME | Fecha/hora del movimiento |
| `timestamps` | — | created_at, updated_at |

### `restaurant_tables`
| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Identificador único |
| `name` | VARCHAR(50) UNIQUE | Nombre/número de mesa |
| `capacity` | INT UNSIGNED DEFAULT 4 | Capacidad (CHECK > 0) |
| `status` | ENUM('available', 'occupied', 'billed') DEFAULT 'available' | Estado operativo |
| `is_active` | BOOLEAN DEFAULT true | Mesa habilitada |
| `timestamps` | — | created_at, updated_at |

### `restaurant_orders`
| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Identificador único |
| `table_id` | FK restaurant_tables NULLABLE | Mesa asociada |
| `user_id` | FK users | Mesero/cajero |
| `sale_id` | FK sales NULLABLE | Venta tras liquidación |
| `order_number` | VARCHAR(30) UNIQUE | Consecutivo (`ORD-XXXX`) |
| `order_type` | ENUM('table', 'delivery', 'takeout') DEFAULT 'table' | Tipo de pedido |
| `status` | ENUM('open', 'in_kitchen', 'dispatched', 'delivered', 'billed', 'closed', 'cancelled') DEFAULT 'open' | Estado |
| `customer_id` | FK customers NULLABLE | Cliente asociado |
| `customer_name` | VARCHAR(150) NULLABLE | Nombre del cliente |
| `delivery_fee` | DECIMAL(12,2) DEFAULT 0.00 | Flete (CHECK ≥ 0) |
| `guest_count` | INT UNSIGNED NULLABLE | Comensales (CHECK 1-99) |
| `subtotal` | DECIMAL(12,2) | Subtotal |
| `total` | DECIMAL(12,2) | Total |
| `notes` | VARCHAR(500) NULLABLE | Observaciones |
| `closed_at` | TIMESTAMP NULLABLE | Fecha/hora de cierre |
| `timestamps` | — | created_at, updated_at |

### `restaurant_order_items`
| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Identificador único |
| `order_id` | FK restaurant_orders CASCADE | Comanda padre |
| `product_id` | FK productos RESTRICT | Plato/producto |
| `quantity` | DECIMAL(12,3) | Cantidad (CHECK > 0) |
| `unit_price` | DECIMAL(12,2) | Precio unitario |
| `subtotal` | DECIMAL(12,2) | quantity × unit_price |
| `notes` | VARCHAR(255) NULLABLE | Observaciones de preparación |
| `status` | ENUM('pending', 'kitchen', 'served', 'cancelled') DEFAULT 'pending' | Estado |
| `printed_to_kitchen` | BOOLEAN DEFAULT false | Ya impreso en cocina |
| `batch_number` | INT UNSIGNED DEFAULT 1 | Tanda de cocina |
| `timestamps` | — | created_at, updated_at |

---

## Reglas de Inventario y Consistencia

### Transaccionalidad Atómica con Recetas y Adiciones
- Toda venta de un plato con receta consulta la disponibilidad de inventario para la receta base **y** todas las adiciones bajo bloqueo pesimista (`lockForUpdate`).
- Si un solo ingrediente de la receta base o de las adiciones no tiene stock suficiente, la venta completa se rechaza en bloque dentro de `DB::transaction`.
- **Cero Stock Negativo**: No se admiten ventas registradas a medias.

### Descuento de Inventario
- **Ítems de Venta Directa (Suplementos):** Descuentan stock unitario directo (`stock - cantidad_vendida`).
- **Platos/Preparaciones Compuestas:** Descuentan insumos mediante el motor de recetas:
  - `Consumo Insumo = (Cant. Vendida × Cant. Base por Porción) + Σ(Cant. Adición_k × Consumo Insumo en Adición_k)`

### Anulación Simétrica
- La anulación restaura exactamente los insumos consumidos por el plato base y sus adiciones, registrando `DEVOLUCION_ANULACION` en `inventory_movements`.

### Inmutabilidad de Precios
- Los precios cobrados quedan congelados en `sale_details.unit_price` y `venta_adicion.precio_unitario` aunque el catálogo modifique sus valores futuros.

### Regla contable de gastos
- Los gastos de categoría `merchandise` no generan movimientos de inventario ni alteran stock. El inventario físico se gestiona desacopladamente.

### Trazabilidad
- Toda modificación de inventario pasa por `inventory_movements` con tipos:
  - `SALIDA_VENTA`: Consumo por venta (receta base + adiciones).
  - `ENTRADA_COMPRA`: Ingreso de insumos/suplementos.
  - `MERMA`: Pérdida por deterioro, caducidad o error.
  - `AJUSTE`: Corrección manual de conteo.
  - `DEVOLUCION_ANULACION`: Restauración por anulación de venta.

---

## Índices y Constraints

### Claves Únicas
- `productos.sku`: UNIQUE.
- `customers.document`: UNIQUE.
- `sales.invoice_number`: UNIQUE.
- `restaurant_tables.name`: UNIQUE.
- `restaurant_orders.order_number`: UNIQUE.
- `recetas.producto_plato_id`: UNIQUE (una receta por plato).
- `receta_detalle`: UNIQUE(`receta_id`, `ingrediente_id`).
- `adicion_detalle`: UNIQUE(`adicion_id`, `ingrediente_id`).
- `producto_adicion`: UNIQUE(`producto_id`, `adicion_id`).
- `expenses`: UNIQUE(`supplier_id`, `invoice_number`) cuando ambos existan.

### CHECK Constraints
- `productos.tipo_producto` IN ('INSUMO', 'PREPARACION', 'PLATO', 'SUPLEMENTO').
- `productos.unidad_base` IN ('UNIDAD', 'GRAMO', 'MILILITRO').
- `sale_payments.amount > 0`.
- `expenses.amount > 0`.
- `restaurant_tables.capacity > 0`.
- `restaurant_orders.delivery_fee >= 0`.
- `restaurant_order_items.quantity > 0`.
- `sales.delivery_fee >= 0`, `sales.service_fee >= 0`, `sales.tax_inc >= 0`.
- `sales.discount_percentage` BETWEEN 0 AND 100.

### FK con ON DELETE
- `RESTRICT` en relaciones financieras (nunca eliminar en cascada datos de ventas, gastos o movimientos).
- `CASCADE` en relaciones de composición (detalle de receta, detalle de adición, ítems de comanda, pagos de venta, adiciones de venta).

### Índices de Rendimiento
- `sale_payments(sale_id)`, `sale_payments(method)`.
- `expenses(issue_date)`, `expenses(status)`, `expenses(supplier_id)`.
- `inventory_movements(product_id)`, `inventory_movements(type)`.
- `restaurant_orders(status)`, `restaurant_orders(order_type)`.
- `receta_detalle(receta_id)`, `receta_detalle(ingrediente_id)`.
- `adicion_detalle(adicion_id)`, `adicion_detalle(ingrediente_id)`.
- `producto_adicion(producto_id)`, `producto_adicion(adicion_id)`.
- `venta_adicion(sale_detail_id)`, `venta_adicion(adicion_id)`.
- `sales(restaurant_order_id)`, `sales(order_type)`.
