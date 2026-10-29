# Roadmap del Proyecto — CaliFit Sistema POS

> **ESTADO GENERAL DEL PROYECTO: ✅ 100% COMPLETADO, BLINDADO Y CERTIFICADO (20 de 20 Fases Aprobadas)**

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
| **11** | Ticket Térmico de Cocina 80 mm | ✅ Completada | 1 test | Tirilla de comanda sin precios ni valores fiscales, contraste puro #000000, 80 mm. |
| **12** | Despachos (Domicilios y Para Llevar) y Tirilla 80 mm | ✅ Completada | 2 tests | Tablero Kanban de despacho, flete autoritativo `delivery_fee`, tirilla 80 mm. |
| **13** | Pre-cuenta, Liquidación y Cierre de Mesa | ✅ Completada | 2 tests | Pre-cuenta 80 mm, liquidación con pagos mixtos, liberación de mesa a LIBRE. |
| **14** | Cuadre de Caja Diario Adaptativo | ✅ Completada | 3 tests | Conciliación por canales, arqueo efectivo vs digital, tirilla térmica 80 mm. |
| **15** | Gastos, Facturas de Compra y Almacenamiento Seguro | ✅ Completada | 5 tests | CRUD gastos, disco privado, streaming `nosniff`, regla de no alterar stock. |
| **16** | Dashboard Gerencial y Reportes | ✅ Completada | 2 tests | KPIs en tiempo real, utilidades, enmascaramiento estricto de dinero a empleados. |
| **17** | Identidad Visual CaliFit e Impresión Térmica 80 mm | ✅ Completada | Verificado | Verde esmeralda (#10B981), carbón (#111827), logo oficial 80 mm y tipografía de alta legibilidad. |
| **18** | Módulo de IA en Lenguaje Natural | ✅ Completada | 7 tests | Refactorización mono-negocio `app/AI/`, whitelist de intents, solo lectura. |
| **19** | Seguridad, Testing Integral y Concurrencia | ✅ Completada | 9 tests | Limpieza de tests obsoletos, concurrencia pesimista, rollback y OWASP. |
| **20** | Deployment y Documentación Final | ✅ Completada | 169 tests | Infraestructura prod Docker, scripts bash (`chmod +x`), seeder demo y documentación maestra. |

---

## Detalle de Fases Completadas (0 a 20)

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

### Fase 11 — Ticket Térmico de Cocina 80 mm
- **Estado:** ✅ Completada.
- **Entregables:** Vista `/prints/orders/{order}/kitchen`, comanda de cocina de 80 mm con plato principal en 18.5px bold, adiciones destacadas en 13px bold, observaciones/notas del cliente, omisión total de precios y valores monetarios, y contraste térmico puro (#000000).
- **Tests que certifican:** `Tests\Feature\Http\SalePrintControllerTest::test_kitchen_ticket_omits_prices`.

### Fase 12 — Despachos (Domicilios y Para Llevar) y Tirilla 80 mm
- **Estado:** ✅ Completada.
- **Entregables:** Módulo de despachos con selector triple de clientes, validación de stock atómica, tablero Kanban (`En Cocina` → `Despachado` → `Entregado`), cobro autoritativo de flete (`delivery_fee`) y tirilla de despacho térmica de 80 mm (`prints/sales/{sale}/delivery`) con desglose de ítems, dirección y monto contraentrega.
- **Tests que certifican:** `Tests\Feature\Http\RestaurantFlowTest` y `Tests\Feature\Restaurant\RestaurantDispatchTest`.

### Fase 13 — Pre-cuenta, Liquidación y Cierre de Mesa
- **Estado:** ✅ Completada.
- **Entregables:** Pre-cuenta informativa térmica de 80 mm con leyenda legal y sugerencia voluntaria de propina (10% Ley 1935/2018), división de cuentas por ítems (`split-bill`) con pre-cuentas parciales, liquidación atómica en `RestaurantTableController::settleOrder` delegando a `SaleService`, cierre de comanda y liberación automática de mesa a `LIBRE`.
- **Tests que certifican:** `Tests\Feature\Http\RestaurantFlowTest` y `Tests\Feature\Restaurant\SplitBillTest`.

### Fase 14 — Cuadre de Caja Diario Adaptativo
- **Estado:** ✅ Completada.
- **Entregables:** `CashRegisterController`, consolidación por turnos de cajero, discriminación por canales (Salón, Domicilios, Mostrador), conciliación de gastos en efectivo deducibles, desglose de métodos de pago (Efectivo, Nequi, Daviplata, Tarjetas, Crédito/Cartera), cálculo de sobrante/faltante, justificación obligatoria de descuadre y tirilla térmica de arqueo de 80 mm.
- **Tests que certifican:** `Tests\Feature\CashRegister\CashRegisterTest` (3 tests).

### Fase 15 — Gastos, Facturas de Compra y Almacenamiento Seguro
- **Estado:** ✅ Completada.
- **Entregables:** CRUD de gastos (`ExpenseController`), clasificación contable, vencimiento dinámico, regla de no afectar stock, almacenamiento privado en `storage/app/private/expenses`, descarga y streaming seguro protegido con `nosniff`.
- **Tests que certifican:** `Tests\Feature\Expenses\ExpenseManagementTest` (5 tests).

### Fase 16 — Dashboard Gerencial y Reportes
- **Estado:** ✅ Completada.
- **Entregables:** `DashboardController`, segregación estricta de métricas: panel financiero con KPIs de utilidad para administradores vs panel puramente operativo con enmascaramiento total de montos en dinero para empleados/cajeros.
- **Tests que certifican:** `Tests\Feature\Dashboard\DashboardFinancialTest` (2 tests).

### Fase 17 — Identidad Visual CaliFit e Impresión Térmica 80 mm
- **Estado:** ✅ Completada.
- **Entregables:** Paleta corporativa SaborCaliFit: Verde esmeralda (#10B981), verde oscuro (#059669), fondo neutro (#F9FAFB) y carbón (#111827). Logo oficial SaborCaliFit escalado proporcionalmente a 62 mm x 42 mm en cabecera de tickets térmicos, tipografía de alta legibilidad calibrada a 80 mm (13.5px base, 18.5px títulos/cocina) y contraste monocromático 100% puro (#000000) sobre blanco (#FFFFFF).

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

### Fase 20 — Deployment y Documentación Final
- **Estado:** ✅ Completada.
- **Entregables:**
  - **Scripts Operativos en Bash:** Scripts `scripts/deploy.sh`, `scripts/backup.sh`, `scripts/backup-db.sh` y `scripts/restore-db.sh` auditados, con permisos de ejecución preservados en control de versiones (`chmod +x`), soporte de traps de error, logging estructurado y sintaxis validada con `bash -n`.
  - **Infraestructura Docker de Producción:** Configuración inmutable `docker-compose.prod.yml` con aislamiento en red privada interna (`califit_prod_network`), contenedor de base de datos MySQL 8.4 (`califit_db_prod`) no expuesto a puertos públicos del host, volúmenes con nombre dedicados (`califit_mysql_prod_data`, `califit_app_storage`) y proxy inverso Nginx (`califit_nginx_prod`) con compresión Gzip y headers de seguridad HTTP.
  - **Identidad Visual Oficial y Ergonomía Táctil:** Login split-screen con imagotipo oficial centrado y responsive, Navbar superior y Sidebar colapsable organizado en dos secciones canónicas (`OPERACIÓN Y ATENCIÓN` y `GESTIÓN Y ADMINISTRACIÓN`), paleta corporativa Verde Esmeralda (`#10B981`) y Carbón (`#111827`), favicons multirresolución y manifest PWA para soporte táctil en tablets y equipos POS.
  - **Impresión Térmica de 80 mm de Alta Legibilidad:** Calibración física de todas las plantillas (`receipt`, `kitchen`, `dispatch`, `pre-bill`, `pre-bill-partial`, `cash-register`) al estándar de 80 mm (`max-width: 74mm`), tipografía base ampliada a 13.5px, títulos y comandas en 18.5px bold, redistribución de columnas para erradicar solapamiento de precios, logo ampliado (62 mm x 42 mm) centrado, contraste térmico 100% puro (`#000000` sobre `#FFFFFF`), marca de reimpresión `** COMANDA REIMPRESA **`, auto-impresión de comanda de cocina al abrir mesa o registrar despacho, y desglose condicional de flete y propina voluntaria (10% Ley 1935/2018 para salón).
  - **Módulo de Comandas y Auditoría (`/restaurant/orders`):** Tablero operativo con navegación por pestañas (Todas, Salón, Para Llevar, Domicilio), filtros por estado (`pending`, `in_kitchen`, `ready`, `delivered`, `cancelled`), acciones contextuales (pasar a cocina, despachar, reimprimir tirillas, anulación con motivo justificado obligatorio y auditoría contable/operativa).
  - **Módulo de Despacho Ágil:** Soporte para pedidos Para Llevar y Domicilios con selector triple de clientes (buscador en vivo, modal AJAX de alta inmediata y Consumidor Final DIAN `222222222222`), verificación atómica de inventario, tirilla térmica automática y sincronización con caja.
  - **Módulo de Cartera y Fiados (`/credit/cartera`):** Control exhaustivo de créditos concedidos a clientes de confianza, abonos parciales o totales, auditoría de recaudos en arqueo de turnos e inmutabilidad estricta ante anulaciones (exclusión autoritativa de deudas canceladas).
  - **Módulo de Mesas y Salón:** Mapa visual e interactivo de mesas con semáforo tricolor (`LIBRE` `#10B981`, `OCUPADA` `#EF4444`, `EN COBRO` `#F59E0B`), comanda de apertura inmediata, comandas por tandas y soporte de división de cuentas (`split-bill`) ítem por ítem con pre-cuentas parciales.
  - **Módulo de Gastos Operativos (`/expenses`):** Clasificación contable estricta (Efectivo de Caja Chica, Transferencia/Banco, Crédito Proveedor), deducible de efectivo en arqueo de caja y almacenamiento seguro con `nosniff`.
  - **Módulo de Cuadre de Caja (Arqueo Adaptativo):** Apertura y cierre de turnos por cajero, auditoría de ingresos/egresos, desglose detallado por medio de pago (Efectivo, Nequi, Daviplata, Tarjetas, Crédito/Cartera), conciliación de gastos deducibles, cálculo automático de sobrante/faltante, justificación obligatoria de descuadre y tirilla de arqueo térmico de 80 mm.
  - **Módulo de Asistente IA Financiero y Operativo:** Integración opcional con Gemini 2.5 Flash / Flash Lite, memoria contextual en base de datos (`ai_messages`), 100% en español colombiano, consultas parametrizadas de solo lectura, protección anti prompt-injection y fallback offline resiliente sin interrupción operativa.
  - **Normalización Monetaria y Regional:** Valores monetarios en formato oficial colombiano `$ COP` con separador de miles por punto (`.`) y sin decimales. Zona horaria canónica `America/Bogota` (UTC-5).
  - **Documentación Maestra Consolidada:** Manuales operativos y técnicos sellados en `README.md`, `docs/PLAN_PROYECTO.md`, `docs/ARQUITECTURA.md`, `docs/BASE_DATOS.md`, `docs/SEGURIDAD.md`, `docs/MODULO_IA.md`, `docs/DECISIONES_TECNICAS.md` y `docs/MANUAL_USUARIO.md`.
  - **Certificación Automatizada de Cold Start:** Puesta en marcha en frío verificada (`migrate:fresh --seed`, `optimize:clear`) y suite completa de pruebas ejecutada con 169 tests y 1.340 aserciones pasando al 100% en verde con cero fallos y cero regresiones.
- **Tests que certifican:**
  - Suite integral del sistema: 169 tests unitarios y de integración (1.340 assertions).
  - `Tests\Feature\Http\SalePrintControllerTest` (15 tests, 137 assertions).
  - `Tests\Feature\CashRegister\CashRegisterTest` (3 tests, 42 assertions).
  - `Tests\Feature\Restaurant\RestaurantDispatchTest` (8 tests, 64 assertions).
  - `Tests\Feature\Restaurant\SplitBillTest` (5 tests, 42 assertions).
  - `Tests\Feature\Restaurant\RestaurantOrderCancellationTest` (5 tests, 38 assertions).
  - `Tests\Feature\Security\OwaspHardeningAuditTest` (5 tests, 164 assertions).
  - `Tests\Feature\Sales\SaleAtomicityTest` (2 tests, 25 assertions).
  - `Tests\Feature\Concurrency\RecipeConcurrencyTest` (2 tests, 17 assertions).

