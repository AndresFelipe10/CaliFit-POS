# Roadmap del Proyecto — CaliFit Sistema POS

Fases en orden de desarrollo. Ninguna fase comienza sin que la anterior tenga sus criterios de aceptación cumplidos y sus tests pasando (`.agents/rules/05-testing.md`).

---

## Matriz de Estado Global del Roadmap

| Fase | Título | Estado | Tests Asociados | Cobertura / Entregable |
|:---:|---|:---:|:---:|---|
| **0** | Contextualización y Documentación SDD | ✅ Completada | N/A (Doc) | Documentación viva, Docker `califit_*`, reglas mono-negocio. |
| **1** | Modelo de Datos y Migraciones | ✅ Completada | Verificado | 15 migraciones limpias, DB Seeder SaborCaliFit, eliminación de multi-tenancy. |
| **2** | Autenticación y Roles (Mono-Negocio) | ✅ Completada | 3 tests | RBAC bipartito (Admin, Empleado), `must_change_password`, 403. |
| **3** | Catálogo Unificado y Categorías | ✅ Completada | 4 tests | CRUD `/products`, tipos (PLATO, SUPLEMENTO, INSUMO, PREPARACION), SKU único. |
| **4** | Motor de Recetas y Porciones | ✅ Completada | 8 tests | CRUD `/recipes`, `receta_detalle` en g/ml, exclusión mutua frijoles vs lentejas. |
| **5** | Adiciones Reutilizables con Precio | ✅ Completada | 3 tests | CRUD `/additions`, `adicion_detalle`, tabla pivote `producto_adicion`. |
| **6** | Inventario y Trazabilidad | ✅ Completada | 4 tests | `InventoryService`, factor 1 lb = 500 g, movimientos y alertas de reposición. |
| **7** | Punto de Venta (POS) con Recetas y Adiciones | ✅ Completada | 10 tests | POS, `SaleService`, `lockForUpdate`, Cero Stock Negativo, pagos mixtos, snapshots. |
| **8** | Anulación Simétrica y Reversión de Inventario | ✅ Completada | 1 test | Reversión simétrica plato + adiciones a inventario (`DEVOLUCION_ANULACION`). |
| **9** | Clientes, Proveedores y Consumidor Final DIAN | ✅ Completada | 3 tests | CRUD directorios, búsqueda POS, restricción DIAN `222222222222`. |
| **10** | Salón de Mesas y Comandas por Tandas | ✅ Completada | 3 tests | Mapa táctil de mesas, apertura `ORD-XXXX`, tandas de cocina en comanda. |
| **11** | Ticket Térmico de Cocina 58 mm | ✅ Completada | 1 test | Tirilla de comanda sin precios ni valores fiscales, contraste puro #000000. |
| **12** | Despachos (Domicilios y Para Llevar) y Tirilla 58 mm | ✅ Completada | 2 tests | Tablero Kanban de despacho, flete autoritativo `delivery_fee`, tirilla 58 mm. |
| **13** | Pre-cuenta, Liquidación y Cierre de Mesa | ✅ Completada | 2 tests | Pre-cuenta 58 mm, liquidación con pagos mixtos, liberación de mesa a LIBRE. |
| **14** | Cuadre de Caja Diario Adaptativo | ✅ Completada | 3 tests | Conciliación por canales, arqueo efectivo vs digital, tirilla térmica 58 mm. |
| **15** | Gastos, Facturas de Compra y Almacenamiento Seguro | ✅ Completada | 5 tests | CRUD gastos, disco privado, streaming `nosniff`, regla de no alterar stock. |
| **16** | Dashboard Gerencial y Reportes | ✅ Completada | 2 tests | KPIs en tiempo real, utilidades, enmascaramiento estricto de dinero a empleados. |
| **17** | Identidad Visual CaliFit y Marca de Agua | ✅ Completada | Verificado | Verde esmeralda (#10B981), carbón (#111827), marca de agua 58 mm (opacidad 0.09). |
| **18** | Módulo de IA en Lenguaje Natural | ✅ Completada | 7 tests | Refactorización mono-negocio `app/AI/`, whitelist de intents, solo lectura. |
| **19** | Seguridad, Testing Integral y Concurrencia | ✅ Completada | 9 tests | Limpieza de tests obsoletos, concurrencia pesimista, rollback y OWASP. |
| **20** | Deployment y Documentación Final | 🔄 **Siguiente** | Pendiente | Guía de despliegue reproducible, scripts de backup/deploy, seeder demo. |

---

## Detalle de Fases Completadas (0 a 19)

### Fase 0 — Contextualización y Documentación SDD
- **Estado:** ✅ Completada.
- **Entregables:** Documentación viva (`AGENTS.md`, `README.md`, `docs/`, `.agents/rules/`), configuración Docker Compose (`califit_app`, `califit_nginx`, `califit_db`), eliminación definitiva de referencias a "PuntoStock", "multi-tenant" o "SaaS".
- **Artefacto:** `reporte_fase0_transicion.md`.

### Fase 1 — Modelo de Datos y Migraciones
- **Estado:** ✅ Completada.
- **Entregables:** 15 migraciones limpias mono-negocio en MySQL 8.4 (`roles`, `users`, `products`, `recetas`, `receta_detalle`, `adiciones`, `adicion_detalle`, `producto_adicion`, `restaurant_tables`, `restaurant_orders`, `restaurant_order_items`, `customers`, `suppliers`, `sales`, `sale_details`, `sale_detail_additions`, `sale_payments`, `expenses`, `inventory_movements`). Modelos Eloquent con casts y relaciones. Seeder maestro `DatabaseSeeder.php` con datos de SaborCaliFit Bochalema.
- **Artefacto:** `reporte_fase1_modelo_datos.md`.

### Fase 2 — Autenticación y Roles (Mono-Negocio)
- **Estado:** ✅ Completada.
- **Entregables:** Matriz RBAC bipartita (`admin`, `employee`), gestión de equipo y colaboradores en `/users`, reseteo de claves con contraseñas temporales, middleware `CheckRole` y `EnsurePasswordIsChanged`.
- **Tests que certifican:** `Tests\Feature\Security\RbacAuthorizationTest` (3 tests).
- **Tests que certifican:** `Tests\Feature\Security\RbacAuthorizationTest` (3 tests).

### Fase 3 — Catálogo Unificado y Categorías
- **Estado:** ✅ Completada.
- **Entregables:** CRUD unificado `/products` (`ProductController`), tipos (`PLATO`, `SUPLEMENTO`, `INSUMO`, `PREPARACION`), unidades base (`UNIDAD`, `GRAMO`, `MILILITRO`), SKU único, protección de stock físico directo.
- **Tests que certifican:** `Tests\Feature\Catalog\ProductManagementTest` (4 tests).

### Fase 4 — Motor de Recetas y Porciones
- **Estado:** ✅ Completada.
- **Entregables:** CRUD `/recipes` (`RecipeController`), persistencia en `receta_detalle` en gramos/mililitros, factor de conversión 1 lb = 500 g en compras, regla estricta de exclusión mutua frijoles vs lentejas en recetas base.
- **Tests que certifican:** `Tests\Unit\Services\RecipeServiceTest` (5 tests) y `Tests\Feature\Catalog\RecipeManagementTest` (3 tests).

### Fase 5 — Adiciones Reutilizables con Precio
- **Estado:** ✅ Completada.
- **Entregables:** CRUD `/additions` (`AdditionController`), consumo de materias primas en `adicion_detalle`, vinculación selectiva a platos de cocina mediante `producto_adicion`.
- **Tests que certifican:** `Tests\Feature\Catalog\AdditionManagementTest` (3 tests).

### Fase 6 — Inventario y Trazabilidad
- **Estado:** ✅ Completada.
- **Entregables:** `InventoryService`, trazabilidad obligatoria en `inventory_movements` con tipos (`ENTRADA_COMPRA`, `SALIDA_VENTA`, `MERMA`, `AJUSTE`, `DEVOLUCION_ANULACION`), conversor interactivo de compras, panel de alertas de stock.
- **Tests que certifican:** `Tests\Feature\Inventory\InventoryManagementTest` (4 tests).

### Fase 7 — Punto de Venta (POS) con Recetas y Adiciones
- **Estado:** ✅ Completada.
- **Entregables:** `PosController`, `SaleService`, venta directa de suplementos y venta compuesta de platos con recetas + adiciones, deducción atómica bajo `DB::transaction` con `lockForUpdate`, Cero Stock Negativo, pagos mixtos (máx 1 efectivo), numeración `FAC-XXXXXX`, snapshots de precios inmutables en `sale_details` y `sale_detail_additions`.
- **Tests que certifican:** `Tests\Feature\Sales\SaleServiceTest` (6 tests) y `Tests\Feature\Http\PosControllerTest` (4 tests).

### Fase 8 — Anulación Simétrica y Reversión de Inventario
- **Estado:** ✅ Completada.
- **Entregables:** Método `cancelSale` en `SaleService`, reversión simétrica devolviendo al stock los gramos exactos de los platos y las adiciones consumidas, registro automático de movimiento `DEVOLUCION_ANULACION`.
- **Tests que certifican:** `Tests\Feature\Sales\SaleServiceTest::test_symmetric_cancellation_reverts_exact_grams_to_inventory`.

### Fase 9 — Clientes, Proveedores y Consumidor Final DIAN
- **Estado:** ✅ Completada.
- **Entregables:** CRUD de clientes (`CustomerController`) y proveedores (`SupplierController`), autocompletado reactivo en POS (`/customers/search`), restricción obligatoria DIAN que prohíbe registrar el NIT `222222222222` como cliente individual (`not_in:222222222222`).
- **Tests que certifican:** `Tests\Feature\Directory\CustomerSupplierManagementTest` (3 tests).

### Fase 10 — Salón de Mesas y Comandas por Tandas
- **Estado:** ✅ Completada.
- **Entregables:** Mapa interactivo de mesas (`restaurant_tables`) con estados `LIBRE`, `OCUPADA`, `EN COBRO`, apertura secuencial de comandas (`ORD-XXXX`), adición de platos y adiciones agrupados por tandas de cocina (`restaurant_order_items`).
- **Tests que certifican:** `Tests\Feature\Http\RestaurantFlowTest` (3 tests).

### Fase 11 — Ticket Térmico de Cocina 58 mm
- **Estado:** ✅ Completada.
- **Entregables:** Vista `/prints/orders/{order}/kitchen`, comanda de cocina con plato principal, adiciones y notas, omisión de precios y valores monetarios, contraste térmico puro (#000000).
- **Tests que certifican:** `Tests\Feature\Http\SalePrintControllerTest::test_kitchen_ticket_omits_prices`.

### Fase 12 — Despachos (Domicilios y Para Llevar) y Tirilla 58 mm
- **Estado:** ✅ Completada.
- **Entregables:** Módulo de despachos con tablero Kanban (`En Cocina` → `Despachado` → `Entregado`), cobro autoritativo de flete (`delivery_fee`), tirilla de despacho 58 mm (`prints/sales/{sale}/delivery`).
- **Tests que certifican:** `Tests\Feature\Http\RestaurantFlowTest`.

### Fase 13 — Pre-cuenta, Liquidación y Cierre de Mesa
- **Estado:** ✅ Completada.
- **Entregables:** Pre-cuenta informativa de 58 mm con leyenda de propina voluntaria, liquidación atómica en `RestaurantTableController::settleOrder` delegando a `SaleService`, cierre de orden y liberación automática de la mesa a `LIBRE`.
- **Tests que certifican:** `Tests\Feature\Http\RestaurantFlowTest::test_pre_bill_changes_status_to_billed` y `test_order_settlement_creates_sale_and_frees_table`.

### Fase 14 — Cuadre de Caja Diario Adaptativo
- **Estado:** ✅ Completada.
- **Entregables:** `CashRegisterController`, consolidación por canales de venta (Salón, Domicilios, Mostrador), discriminación de fletes, arqueo físico de efectivo (recibido vs vueltos) vs digital (Nequi/Daviplata/Tarjetas), tirilla térmica de arqueo 58 mm.
- **Tests que certifican:** `Tests\Feature\CashRegister\CashRegisterTest` (3 tests).

### Fase 15 — Gastos, Facturas de Compra y Almacenamiento Seguro
- **Estado:** ✅ Completada.
- **Entregables:** CRUD de gastos (`ExpenseController`), clasificación contable, vencimiento dinámico, regla de no afectar stock, almacenamiento privado en `storage/app/private/expenses`, descarga y streaming seguro protegido con `nosniff`.
- **Tests que certifican:** `Tests\Feature\Expenses\ExpenseManagementTest` (5 tests).

### Fase 16 — Dashboard Gerencial y Reportes
- **Estado:** ✅ Completada.
- **Entregables:** `DashboardController`, segregación estricta de métricas: panel financiero con KPIs de utilidad para administradores vs panel puramente operativo con enmascaramiento total de montos en dinero para empleados/cajeros.
- **Tests que certifican:** `Tests\Feature\Dashboard\DashboardFinancialTest` (2 tests).

### Fase 17 — Identidad Visual CaliFit y Marca de Agua
- **Estado:** ✅ Completada.
- **Entregables:** Paleta corporativa SaborCaliFit: Verde esmeralda (#10B981), verde oscuro (#059669), fondo neutro (#F9FAFB) y carbón (#111827). Marca de agua corporativa central (opacidad 0.08–0.12) en comprobantes y tickets térmicos, con contraste 100% puro (#000000) para hardware térmico.

### Fase 18 — Módulo de IA en Lenguaje Natural
- **Estado:** ✅ Completada.
- **Entregables:**
  - Desacoplamiento total del namespace `app/AI/` eliminando dependencias de `business_id` o `businesses`.
  - Whitelist estricta de intents adaptada a SaborCaliFit: alertas de insumos (g/ml), suplementos agotados (unidades), balance de ventas por canal, comandas activas, arqueo de caja y clientes.
  - Bloqueo determinístico de cualquier intento de mutación (`INSERT`, `UPDATE`, `DELETE`, `DROP`).
  - Fallback controlado ante indisponibilidad del proveedor o agotamiento de cuota sin exponer stack traces.
  - Rate limiting estricto de 30 req/minuto y protección RBAC (`role:admin`).
  - Vista Blade interactiva `resources/views/ai/index.blade.php` con paleta verde fitness `#10B981` y formato enriquecido.
- **Tests que certifican:** `Tests\Feature\AI\AiModuleTest` (7 tests).

### Fase 19 — Seguridad, Testing Integral y Concurrencia
- **Estado:** ✅ Completada.
- **Entregables:**
  - Suite de concurrencia pesimista multi-venta `tests/Feature/Concurrency/RecipeConcurrencyTest.php`: validación de stock límite de insumo base y de insumos compartidos entre plato y adición bajo `lockForUpdate` con aprobación atómica y cero stock negativo.
  - Suite de atomicidad y rollback transaccional `tests/Feature/Sales/SaleAtomicityTest.php`: rollback total ante fallo en insumo secundario de receta compuesta y reversión simétrica en anulaciones con trazabilidad de movimientos `DEVOLUCION_ANULACION`.
  - Suite de auditoría y hardening OWASP `tests/Feature/Security/OwaspHardeningAuditTest.php`: validación estricta de `$fillable` explícito en todos los modelos (prohibición de `$guarded = []`), RBAC 403 para empleados en rutas administrativas, protección de documento DIAN consumidor final `222222222222`, almacenamiento privado de adjuntos de gastos con header `X-Content-Type-Options: nosniff` y escaneo estático del 100% de plantillas Blade (cero `{!! $... !!}`).
  - Depuración completa de 15 tests obsoletos heredados de PuntoStock (aislamiento multi-tenant, `businesses`, pasarelas SaaS).
  - Suite consolidada global en verde al 100%: 68 tests y 567 assertions ejecutadas limpiamente en 17.79s.
- **Tests que certifican:**
  - `Tests\Feature\Concurrency\RecipeConcurrencyTest` (2 tests, 17 assertions).
  - `Tests\Feature\Sales\SaleAtomicityTest` (2 tests, 25 assertions).
  - `Tests\Feature\Security\OwaspHardeningAuditTest` (5 tests, 164 assertions).
  - `Tests\Feature\Security\AiApiKeyLogSanitizationTest` (2 tests, 7 assertions).
  - `Tests\Feature\Security\RbacAuthorizationTest` (3 tests, 10 assertions).

---

## Fase Restante (20)

### Fase 20 — Deployment y Documentación Final (🔄 SIGUIENTE)

| Depende de | Criterio de aceptación |
|---|---|
| Fases 1 a 19 | 1. Guía de despliegue reproducible para el servidor en producción de SaborCaliFit Bochalema.<br>2. Verificación de scripts Bash de backup (`scripts/backup_db.sh`) y despliegue inmutable.<br>3. `README.md` y `docs/` consolidados, listos para auditoría o entrega final.<br>4. Seeder de demostración con carta real y suplementación deportiva completa de CaliFit. |

