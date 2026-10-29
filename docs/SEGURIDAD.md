# Seguridad — CaliFit Sistema POS

## Autenticación y autorización
- Laravel Auth nativo (sesión), sin frameworks adicionales.
- Autorización siempre en backend vía Policies/Gates; nunca confiar en ocultar elementos de UI.
- Roles: Administrador / Dueño (`admin`), Empleado / Cajero / Mesero (`employee`) (permisos detallados en `docs/PLAN_PROYECTO.md`).
- **Nota:** Este es un sistema mono-negocio. No existe multi-tenancy, ni consola `/superadmin`, ni aislamiento por `business_id`.

## OWASP — controles obligatorios
| Riesgo | Control |
|---|---|
| SQL Injection | Eloquent/Query Builder parametrizado exclusivamente; prohibidas queries crudas con input interpolado |
| XSS | Escape por defecto de Blade (`{{ }}`); prohibido `{!! !!}` con input de usuario |
| CSRF | `@csrf` en todo formulario; verificación de token en todas las rutas POST/PUT/DELETE |
| Mass Assignment | `$fillable` explícito o Form Requests; prohibido `$guarded = []` |
| Broken Access Control | Policy en cada recurso sensible, verificada en backend. RBAC bipartito (Admin, Empleado) |
| Secretos expuestos | Todo secreto en `.env`, nunca en el repositorio; `.env.example` con placeholders |
| Errores en producción | `APP_DEBUG=false`; páginas de error genéricas; sin stack traces visibles |
| Logs | Sin contraseñas, tokens, ni datos sensibles en logs |
| Fuerza bruta | Rate limiting en login (5 intentos/min) |
| Registro no autorizado | Registro público `/register` desactivado (404); alta de equipo controlada por Administrador / Dueño |
| Falsificación de Consumidor Final | Prohibición en Form Requests (`not_in:222222222222`) |
| XSS en autocomplete y modal POS | Renderizado estricto mediante `textContent` en Vanilla JS; prohibido `innerHTML` |
| Alteración de histórico de ventas | Snapshot inmutable en `sales` y `venta_adicion` |
| Abuso de búsqueda de clientes | Rate limiting, longitud mínima y escape de comodines SQL |
| Discrepancia en pagos | Validación autoritativa en centavos enteros |
| Inyección de cambio o vueltos | Cálculo autoritativo en backend |
| Duplicación de efectivo | Máximo una sola línea en efectivo por venta |
| Inyección de fórmulas CSV | Mitigación anteponiendo apóstrofe `'` a celdas que inicien con `=`, `+`, `-`, `@` en exportaciones |
| DOM-based XSS en POS y Despacho | Asignación estricta vía `textContent` y templates parametrizados; prohibido concatenar HTML |
| Deuda huérfana en Cartera | Filtrado autoritativo `where('status', 'completed')` excluyendo ventas anuladas en saldos |
| Modificación no autorizada de órdenes | Validación de estados operativos en transiciones Kanban con HTTP 422 si la orden está cerrada |
| Auditoría de Anulaciones | Registro obligatorio de `cancellation_reason`, `cancelled_by` y `cancelled_at` con reversión simétrica |

## Integridad del Motor de Recetas y Adiciones
- **Validación de Recetas:** Las cantidades por porción (`cantidad_por_porcion`) deben ser positivas y en la unidad correcta del insumo. Form Requests validan coherencia entre `unidad_base` del ingrediente y la unidad declarada en la receta.
- **Validación de Adiciones:** El precio de cada adición debe ser positivo. Los insumos en `adicion_detalle` deben existir y tener `tipo_producto = INSUMO`.
- **Tabla Pivote `producto_adicion`:** Solo se pueden vincular adiciones a productos con `tipo_producto = PLATO`. Validación en Form Request.
- **Deducción Atómica:** Toda venta con receta se ejecuta bajo `DB::transaction` con `lockForUpdate`. Si falta un solo gramo de cualquier insumo (de la receta base o de las adiciones), la venta completa se rechaza en bloque.
- **Cero Stock Negativo:** No se admiten ventas parciales ni descuentos fraccionados de inventario.
- **Snapshot de Precios:** `venta_adicion.precio_unitario` congela el precio al momento de la venta. Modificaciones futuras del catálogo no afectan ventas históricas.
- **Anulación Simétrica:** La anulación restaura exactamente los insumos del plato base y sus adiciones, con movimiento `DEVOLUCION_ANULACION`.

## Integridad de Pagos Mixtos y Vueltos
- **Comparación en Centavos Enteros**: `(int) round($amt * 100)` para evitar imprecisiones IEEE 754.
- **Cálculo Autoritativo de Vueltos**: El backend calcula `change_given = cash_received - amount`. El frontend solo sugiere.
- **Regla de Efectivo Único**: Máximo una sola línea con método `cash` por venta.
- **Sanitización de Referencias**: `strip_tags()` y acotado a 60 caracteres.
- **Reportes Contables**: Ingresos calculados por `SUM(sale_payments.amount)`, no por `cash_received`.

## Integridad de Clientes y Consumidor Final (DIAN)
- **Consumidor Final**: `customer_id = NULL` en ventas. Snapshot: `CONSUMIDOR FINAL` / `222222222222`.
- **Inmutabilidad del Comprador**: Vistas leen de snapshot, no de FK dinámica.
- **Unicidad de Documento**: Índice UNIQUE en `customers.document`.

## Integridad Financiera
- **Precio unitario**: Resuelto desde `productos.precio_venta` en BD bajo `lockForUpdate`.
- **Subtotales y Total**: Calculados en `SaleService`, no aceptados del frontend.
- **Descuentos**: Porcentaje validado (0-100), monto derivado en backend.
- **Stock**: Validado bajo transacción con bloqueo pesimista.
- **Cantidad por ítem**: Acotada (`min: 1`, `max: 99999`).

## Gastos, Adjuntos y Métricas
- **Adjuntos en disco privado** (`storage/app/private/expenses`) con hash aleatorio.
- **Validación de MIME**: Bloqueo de ejecutables, PHP, HTML, SVG, doble extensión.
- **Descarga segura** con `X-Content-Type-Options: nosniff` y `ExpensePolicy`.
- **Métricas financieras enmascaradas** para empleados en Dashboard y API.
- **Gastos exclusivos** para Administradores (403 para empleados).

## Cumplimiento Normativo Colombiano
- **Ley 1935/2018**: Propina voluntaria sugerida (10%), no genera IVA ni INC.
- **Art. 512-1 E.T.**: INC (8%) sobre consumo neto de alimentos y bebidas (`Subtotal - Descuento`), sin incluir propina ni flete.
- **Rotulación**: "Comprobante interno de venta. No reemplaza la factura electrónica de venta ante la DIAN."

## Riesgos específicos del módulo IA
- **Prompt injection**: Mitigado por whitelist de intents en código.
- **Evasión**: Cualquier intent fuera de whitelist se rechaza.
- **Alucinaciones**: Intención validada antes de ejecutar cualquier acción.
- **Abuso de cuota**: Rate limiting en endpoint del asistente.
- Detalle completo → `docs/MODULO_IA.md`.

## Certificación Automatizada de Seguridad (Fase 19)
Toda la matriz de controles de seguridad y consistencia transaccional se certifica mediante tests automatizados en CI/CD:
1. **Concurrencia Pesimista y Cero Stock Negativo** (`tests/Feature/Concurrency/RecipeConcurrencyTest.php`):
   - Ventas concurrentes compitiendo por stock límite de insumo base (aprobación atómica de 1 venta y rechazo de la excedente sin generar gramos negativos).
   - Ventas concurrentes compitiendo por insumo compartido entre plato base y adición extra.
2. **Atomicidad y Rollback Transaccional** (`tests/Feature/Sales/SaleAtomicityTest.php`):
   - Fallo en insumo secundario de recetas compuestas revierte la totalidad de la transacción (rollback simétrico en cabeceras, detalles e inventario).
   - Anulación de ventas restaura simétricamente los insumos tanto de platos como de adiciones cobradas con movimientos `DEVOLUCION_ANULACION`.
3. **Auditoría Estricta OWASP** (`tests/Feature/Security/OwaspHardeningAuditTest.php`):
   - Verificación reflexiva de que el 100% de los modelos Eloquent declaran `$fillable` explícito (prohibición de `$guarded = []`).
   - Matriz RBAC validada: 403 Forbidden para usuarios `employee` en 10 endpoints administrativos.
   - Protección DIAN estricta contra la asignación de `222222222222` a clientes.
   - Almacenamiento privado de adjuntos de gastos con cabecera forzada `X-Content-Type-Options: nosniff`.
   - Escaneo estático del 100% de vistas Blade: cero directivas no escapadas `{!! $... !!}` con variables de usuario.
4. **Sanitización de Credenciales IA** (`tests/Feature/Security/AiApiKeyLogSanitizationTest.php`):
   - Clave de API enviada exclusivamente por headers HTTP (`x-goog-api-key`), nunca en querystring URL.
   - Enmascaramiento garantizado de credenciales en logs de Laravel ante fallas de red del proveedor.

## Auditoría Final de Cierre y Blindaje AppSec (Fase 20)
En la fase de cierre definitivo se subsanaron y blindaron integralmente 7 vectores de riesgo operativo y consistencia:
1. **Blindaje de Búsqueda de Comandas:** Corrección estricta de nombres de columnas relacionales (`customers.document` y `customers.phone`) previniendo excepciones SQL 500 bajo parámetros de búsqueda arbitrarios.
2. **Unicidad e Integridad de Documentos de Clientes:** Importación canónica del modelo `Customer` y autogeneración de identificadores con garantía de unicidad vía bucle `do...while` defensivo ante registros sin documento.
3. **Integridad de Catálogo y Recetas:** Regla de validación `distinct` en insumos de recetas y adiciones, impidiendo duplicidad de claves foráneas e inconsistencias de inventario, reforzada con validación estricta de `tipo_producto === 'INSUMO'`.
4. **Erradicación de DOM-based XSS en POS:** Asignación exclusiva mediante `textContent` en las líneas de productos y adiciones dinámicas en mostrador, previniendo inyección de script desde nombres de platos manipulados.
5. **Máquina de Estados Finita en Despachos:** Restricción estricta en `RestaurantDispatchController::updateStatus` que prohíbe transiciones operativas si la comanda se encuentra cerrada (`closed`) o cancelada (`cancelled`), respondiendo con HTTP 422.
6. **Protección de Pre-cuentas:** Blindaje en `RestaurantOrderController::preBill` para no mutar el estado de órdenes que ya hayan sido canceladas o liquidadas.
7. **Inmutabilidad y Aislamiento Contable de Cartera:** Exclusión autoritativa de ventas anuladas en la agregación de deudas (`where('status', 'completed')`) en el módulo de Cartera, saldo deudor por cliente, arqueo de turnos de caja y módulo de IA, garantizando cuadres exactos y trazabilidad 100% auditada.

