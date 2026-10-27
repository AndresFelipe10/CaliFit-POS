<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            ['name' => 'Administrador']
        );

        $roleEmployee = Role::firstOrCreate(
            ['slug' => Role::ROLE_EMPLOYEE],
            ['name' => 'Empleado POS']
        );

        $this->admin = User::create([
            'role_id' => $roleAdmin->id,
            'name' => 'Admin CaliFit',
            'email' => 'admin@califit.com',
            'password' => bcrypt('password123'),
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $this->employee = User::create([
            'role_id' => $roleEmployee->id,
            'name' => 'Mesero CaliFit',
            'email' => 'mesero@califit.com',
            'password' => bcrypt('password123'),
            'must_change_password' => false,
            'is_active' => true,
        ]);
    }

    /**
     * 1. Usuario mesero/empleado recibe 403 Forbidden en todas las rutas de /reports/*.
     */
    public function test_employee_cannot_access_reports(): void
    {
        $reportRoutes = [
            route('reports.index'),
            route('reports.sales'),
            route('reports.sales.export'),
            route('reports.inventory'),
            route('reports.inventory.export'),
            route('reports.top-products'),
            route('reports.top-products.export'),
        ];

        foreach ($reportRoutes as $url) {
            $response = $this->actingAs($this->employee)->get($url);
            $response->assertStatus(403);
        }
    }

    /**
     * 2. Administrador accede al Centro de Reportes y visualiza las métricas correctas.
     */
    public function test_admin_can_view_reports_hub_and_metrics(): void
    {
        $now = Carbon::now('America/Bogota');

        // Venta del mes
        Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Cliente CaliFit',
            'customer_document' => '222222222222',
            'order_type' => 'retail',
            'invoice_number' => 'FAC-HUB-01',
            'subtotal' => 50000,
            'total' => 50000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Producto en inventario (Insumo)
        Product::create([
            'sku' => 'INS-HUB-01',
            'nombre' => 'Proteína Whey Aislada',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'stock' => 1000,
            'stock_minimo' => 200,
            'precio_compra' => 50.00, // 1000 * 50 = $50.000 costo
            'precio_venta' => 80.00,
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.index'));

        $response->assertOk();
        $response->assertViewIs('reports.index');
        $response->assertViewHas('salesSummary');
        $response->assertViewHas('inventorySummary');
        $response->assertSee('SABORCALIFIT | BOCHALEMA');
        $response->assertSee('Cuadre de Caja');
        $response->assertSee('Reporte de Ventas');
        $response->assertSee('Valoración Inventario');
        $response->assertSee('Top y Rentabilidad');
    }

    /**
     * 3. El reporte de ventas filtra y totaliza ingresos, descuentos y ticket promedio correctamente.
     */
    public function test_admin_can_filter_sales_report_by_date(): void
    {
        $bogota = Carbon::now('America/Bogota');
        $targetDate = $bogota->copy()->subDays(5);
        $otherDate = $bogota->copy()->subDays(20);

        // Venta 1 dentro del rango: $80.000 con $10.000 descuento
        $s1 = Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Cliente Rango 1',
            'customer_document' => '11111111',
            'order_type' => 'retail',
            'invoice_number' => 'FAC-FLT-01',
            'subtotal' => 90000,
            'discount' => 10000,
            'total' => 80000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);
        $s1->created_at = $targetDate->copy()->hour(10);
        $s1->saveQuietly();

        // Venta 2 dentro del rango: $40.000 sin descuento
        $s2 = Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Cliente Rango 2',
            'customer_document' => '22222222',
            'order_type' => 'retail',
            'invoice_number' => 'FAC-FLT-02',
            'subtotal' => 40000,
            'discount' => 0,
            'total' => 40000,
            'payment_method' => 'transfer',
            'status' => 'completed',
        ]);
        $s2->created_at = $targetDate->copy()->hour(14);
        $s2->saveQuietly();

        // Venta 3 fuera del rango: $200.000
        $s3 = Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Cliente Fuera',
            'customer_document' => '33333333',
            'order_type' => 'retail',
            'invoice_number' => 'FAC-FLT-03',
            'subtotal' => 200000,
            'discount' => 0,
            'total' => 200000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);
        $s3->created_at = $otherDate;
        $s3->saveQuietly();

        $response = $this->actingAs($this->admin)->get(route('reports.sales', [
            'start_date' => $targetDate->format('Y-m-d'),
            'end_date' => $targetDate->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertViewHas('total_revenue', 120000.0);
        $response->assertViewHas('sales_count', 2);
        $response->assertViewHas('average_ticket', 60000.0); // 120.000 / 2 = 60.000
        $response->assertViewHas('total_discounts', 10000.0);
        $response->assertSee('Acciones');
        $response->assertSee('Ver');
        $response->assertSee(route('sales.show', $s1));
        $response->assertSee(route('sales.show', $s2));
    }

    public function test_sale_detail_view_renders_successfully_without_format_error_on_null_sale_date(): void
    {
        $sale = Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'CONSUMIDOR FINAL',
            'customer_document' => '222222222222',
            'order_type' => 'retail',
            'invoice_number' => 'FAC-000075',
            'subtotal' => 33000,
            'total' => 33000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->admin)->get(route('sales.show', $sale));

        $response->assertOk();
        $response->assertSee('FAC-000075');
        $response->assertSee('Completada');
        $response->assertSee($sale->created_at->format('d/m/Y H:i'));
        $response->assertSee(route('reports.sales'));
        $response->assertSee('Volver');
    }

    public function test_legacy_sales_index_endpoint_redirects_to_reports_sales(): void
    {
        $response = $this->actingAs($this->admin)->get(route('sales.index'));

        $response->assertRedirect(route('reports.sales'));
    }

    /**
     * 4. La valoración de inventario calcula adecuadamente el valor al costo y comercial sin divisiones por cero.
     */
    public function test_inventory_valuation_calculates_correct_costs_and_margins(): void
    {
        $cat = Category::create([
            'name' => 'Suplementación',
            'description' => 'Proteínas y creatinas',
            'is_active' => true,
        ]);

        // Producto 1: Creatina Monohidratada (Suplemento)
        Product::create([
            'sku' => 'SUP-001',
            'nombre' => 'Creatina Creapure 300g',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $cat->id,
            'stock' => 10,
            'stock_minimo' => 2,
            'precio_compra' => 80000.00,
            'precio_venta' => 120000.00,
            'activo' => true,
        ]);
        // Costo: 10 * 80.000 = 800.000
        // Venta: 10 * 120.000 = 1.200.000
        // Ganancia: 400.000
        // Margen: (400.000 / 1.200.000) * 100 = 33.3%

        // Producto 2: Avena en hojuelas (Insumo)
        Product::create([
            'sku' => 'INS-002',
            'nombre' => 'Avena Integral Molida',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'stock' => 5000,
            'stock_minimo' => 1000,
            'precio_compra' => 10.00,
            'precio_venta' => 20.00,
            'activo' => true,
        ]);
        // Costo: 5.000 * 10 = 50.000
        // Venta: 5.000 * 20 = 100.000
        // Ganancia: 50.000
        // Margen: 50.0%

        // Producto 3 con stock cero (verificar que no haya división por cero)
        Product::create([
            'sku' => 'INS-ZERO',
            'nombre' => 'Insumo Sin Stock',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'stock' => 0,
            'stock_minimo' => 500,
            'precio_compra' => 15.00,
            'precio_venta' => 0.00,
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.inventory'));

        $response->assertOk();
        $response->assertViewHas('total_cost_valuation', 850000.0);
        $response->assertViewHas('total_retail_valuation', 1300000.0);
        $response->assertViewHas('total_potential_profit', 450000.0);

        // Margen: 450.000 / 1.300.000 = 34.6%
        $response->assertViewHas('potential_margin_percent', 34.6);
    }

    /**
     * 5. El cálculo de COGS y utilidad bruta refleja los consumos reales.
     */
    public function test_top_products_calculates_cogs_and_gross_profit(): void
    {
        $now = Carbon::now('America/Bogota');

        $p1 = Product::create([
            'sku' => 'SUP-WHEY',
            'nombre' => 'Whey Protein Vainilla',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'stock' => 50,
            'stock_minimo' => 5,
            'precio_compra' => 100000.00,
            'precio_venta' => 160000.00,
            'activo' => true,
        ]);

        $sale = Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Cliente Top',
            'customer_document' => '222222222222',
            'order_type' => 'retail',
            'invoice_number' => 'FAC-TOP-01',
            'subtotal' => 320000.00,
            'total' => 320000.00,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Venta de 2 unidades de Whey Protein
        // Ingresos: 2 * 160.000 = 320.000
        // COGS: 2 * 100.000 = 200.000
        // Utilidad Bruta: 120.000
        // Margen: (120.000 / 320.000) * 100 = 37.5%
        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $p1->id,
            'quantity' => 2,
            'unit_price' => 160000.00,
            'subtotal' => 320000.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.top-products'));

        $response->assertOk();
        $response->assertViewHas('total_items_sold', 2.0);
        $response->assertViewHas('total_revenue', 320000.0);
        $response->assertViewHas('total_cogs', 200000.0);
        $response->assertViewHas('total_gross_profit', 120000.0);
        $response->assertViewHas('overall_margin_percent', 37.5);
    }

    /**
     * 6. Las rutas de exportación retornan código 200 y cabeceras text/csv con UTF-8 BOM.
     */
    public function test_admin_can_export_reports_to_csv(): void
    {
        $now = Carbon::now('America/Bogota');

        $prod = Product::create([
            'sku' => 'SUP-EXP-01',
            'nombre' => 'Colágeno Hidrolizado',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'stock' => 20,
            'stock_minimo' => 2,
            'precio_compra' => 45000.00,
            'precio_venta' => 70000.00,
            'activo' => true,
        ]);

        $sale = Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Cliente Export',
            'customer_document' => '222222222222',
            'order_type' => 'retail',
            'invoice_number' => 'FAC-EXP-01',
            'subtotal' => 70000.00,
            'total' => 70000.00,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $prod->id,
            'quantity' => 1,
            'unit_price' => 70000.00,
            'subtotal' => 70000.00,
        ]);

        // Export Ventas
        $resSales = $this->actingAs($this->admin)->get(route('reports.sales.export'));
        $resSales->assertOk();
        $this->assertStringContainsString('text/csv', $resSales->headers->get('content-type'));
        $this->assertStringContainsString('califit_reporte_ventas_', $resSales->headers->get('content-disposition'));
        $salesCsv = $resSales->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $salesCsv);
        $this->assertStringContainsString('FAC-EXP-01', $salesCsv);

        // Export Inventario
        $resInv = $this->actingAs($this->admin)->get(route('reports.inventory.export'));
        $resInv->assertOk();
        $this->assertStringContainsString('text/csv', $resInv->headers->get('content-type'));
        $this->assertStringContainsString('califit_reporte_inventario_', $resInv->headers->get('content-disposition'));
        $invCsv = $resInv->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $invCsv);
        $this->assertStringContainsString('SUP-EXP-01', $invCsv);

        // Export Top Productos
        $resTop = $this->actingAs($this->admin)->get(route('reports.top-products.export'));
        $resTop->assertOk();
        $this->assertStringContainsString('text/csv', $resTop->headers->get('content-type'));
        $this->assertStringContainsString('califit_reporte_top_productos_', $resTop->headers->get('content-disposition'));
        $topCsv = $resTop->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $topCsv);
        $this->assertStringContainsString('Colágeno Hidrolizado', $topCsv);
    }
}
