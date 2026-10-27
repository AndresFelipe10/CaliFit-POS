<?php

namespace Tests\Feature\Security;

use App\Models\Addition;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerCreditPayment;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\Credit\CustomerCreditService;
use App\Services\Sales\SaleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditCorrectionsSecondReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['slug' => Role::ROLE_ADMIN], ['name' => 'Administrador']);
        $roleEmployee = Role::firstOrCreate(['slug' => Role::ROLE_EMPLOYEE], ['name' => 'Empleado POS']);

        $this->admin = User::create([
            'role_id' => $roleAdmin->id,
            'name' => 'Admin CaliFit',
            'email' => 'admin_audit@califit.com',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $this->employee = User::create([
            'role_id' => $roleEmployee->id,
            'name' => 'Cajero CaliFit',
            'email' => 'cajero_audit@califit.com',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'General',
            'is_active' => true,
        ]);
    }

    /**
     * 1.1: Búsqueda de comandas por texto no arroja error SQL de document_number
     */
    public function test_restaurant_orders_search_by_customer_document_and_phone_works(): void
    {
        $customer = Customer::create([
            'name' => 'Juan Diego Castro',
            'document' => '1144987654',
            'phone' => '3158889900',
        ]);

        $table = RestaurantTable::create([
            'name' => 'Mesa 10',
            'capacity' => 4,
            'status' => 'occupied',
            'activo' => true,
        ]);

        $order = RestaurantOrder::create([
            'table_id' => $table->id,
            'user_id' => $this->admin->id,
            'customer_id' => $customer->id,
            'order_number' => 'ORD-101010',
            'order_type' => 'table',
            'status' => 'open',
            'customer_name' => $customer->name,
            'subtotal' => 30000,
            'total' => 30000,
        ]);

        // Buscar por documento del cliente
        $responseDoc = $this->actingAs($this->admin)
            ->get(route('restaurant.orders.index', ['search' => '1144987654']));

        $responseDoc->assertOk();
        $responseDoc->assertSee('ORD-101010');

        // Buscar por teléfono del cliente
        $responsePhone = $this->actingAs($this->admin)
            ->get(route('restaurant.orders.index', ['search' => '3158889900']));

        $responsePhone->assertOk();
        $responsePhone->assertSee('ORD-101010');
    }

    /**
     * 1.2: Registro de cliente sin documento autogenera código único sin error de clase Customer
     */
    public function test_store_customer_without_document_autogenerates_unique_code_safely(): void
    {
        $payload1 = [
            'name' => 'Cliente Sin Documento Uno',
            'document' => '',
            'phone' => '3001112233',
            'email' => 'sindoc1@test.com',
        ];

        $response1 = $this->actingAs($this->admin)
            ->post(route('customers.store'), $payload1);

        $response1->assertRedirect();

        $customer1 = Customer::where('email', 'sindoc1@test.com')->first();
        $this->assertNotNull($customer1);
        $this->assertNotEmpty($customer1->document);

        // Crear un segundo cliente consecutivo sin documento
        $payload2 = [
            'name' => 'Cliente Sin Documento Dos',
            'document' => '',
            'phone' => '3004445566',
            'email' => 'sindoc2@test.com',
        ];

        $response2 = $this->actingAs($this->admin)
            ->post(route('customers.store'), $payload2);

        $response2->assertRedirect();

        $customer2 = Customer::where('email', 'sindoc2@test.com')->first();
        $this->assertNotNull($customer2);
        $this->assertNotEmpty($customer2->document);
        $this->assertNotEquals($customer1->document, $customer2->document);
    }

    /**
     * 1.3: Validación de ingredientes duplicados y tipo INSUMO en Recetas y Adiciones
     */
    public function test_recipe_and_addition_requests_reject_duplicate_ingredients_and_non_insumo(): void
    {
        $insumo = Product::create([
            'nombre' => 'Pechuga Cruda',
            'sku' => 'INS-PEC-01',
            'category_id' => $this->category->id,
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'precio_compra' => 20,
            'precio_venta' => 0,
            'stock' => 5000,
            'stock_minimo' => 500,
            'activo' => true,
        ]);

        $plato = Product::create([
            'nombre' => 'Bowl Proteico',
            'sku' => 'PLA-BWL-01',
            'category_id' => $this->category->id,
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'precio_compra' => 8000,
            'precio_venta' => 25000,
            'stock' => 0,
            'stock_minimo' => 0,
            'activo' => true,
        ]);

        $recipe = Recipe::create([
            'producto_plato_id' => $plato->id,
            'nombre' => 'Receta Bowl Proteico',
            'activa' => true,
        ]);

        // 1.3a: UpdateRecipeRequest rechaza insumos duplicados
        $responseDupRecipe = $this->actingAs($this->admin)
            ->put(route('recipes.update', $recipe), [
                'nombre' => 'Receta Actualizada',
                'rendimiento_porciones' => 1,
                'items' => [
                    ['ingrediente_id' => $insumo->id, 'cantidad_por_porcion' => 150],
                    ['ingrediente_id' => $insumo->id, 'cantidad_por_porcion' => 50],
                ],
            ]);

        $responseDupRecipe->assertSessionHasErrors(['items.0.ingrediente_id', 'items.1.ingrediente_id']);

        // 1.3b: StoreAdditionRequest rechaza ingredientes duplicados
        $responseDupAdd = $this->actingAs($this->admin)
            ->post(route('additions.store'), [
                'nombre' => 'Adición Pechuga Extra',
                'precio' => 5000,
                'activo' => true,
                'ingredients' => [
                    ['ingrediente_id' => $insumo->id, 'cantidad' => 50],
                    ['ingrediente_id' => $insumo->id, 'cantidad' => 30],
                ],
            ]);

        $responseDupAdd->assertSessionHasErrors(['ingredients.0.ingrediente_id', 'ingredients.1.ingrediente_id']);

        // 1.3c: StoreAdditionRequest rechaza ingredientes que no sean INSUMO (ej. un PLATO)
        $responseNonInsumoAdd = $this->actingAs($this->admin)
            ->post(route('additions.store'), [
                'nombre' => 'Adición Inválida',
                'precio' => 3000,
                'activo' => true,
                'ingredients' => [
                    ['ingrediente_id' => $plato->id, 'cantidad' => 1],
                ],
            ]);

        $responseNonInsumoAdd->assertSessionHasErrors(['ingredients.0.ingrediente_id']);
    }

    /**
     * 3.1: RestaurantDispatchController::updateStatus restringe transiciones y rechaza closed y cancelled
     */
    public function test_restaurant_dispatch_update_status_blocks_unauthorized_transitions(): void
    {
        $order = RestaurantOrder::create([
            'table_id' => null,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-DISP-01',
            'order_type' => 'delivery',
            'status' => 'in_kitchen',
            'customer_name' => 'Cliente Domicilio',
            'subtotal' => 20000,
            'total' => 20000,
        ]);

        // Rechazo 422 si se intenta forzar 'closed' directo por Kanban
        $respClosed = $this->actingAs($this->admin)
            ->patchJson(route('restaurant.dispatch.update-status', $order), [
                'status' => 'closed',
            ]);

        $respClosed->assertStatus(422);
        $respClosed->assertJsonFragment(['success' => false]);

        // Rechazo 422 si se intenta forzar 'cancelled' directo sin auditoría
        $respCancelled = $this->actingAs($this->admin)
            ->patchJson(route('restaurant.dispatch.update-status', $order), [
                'status' => 'cancelled',
            ]);

        $respCancelled->assertStatus(422);
        $respCancelled->assertJsonFragment(['success' => false]);

        // Transición operativa válida en Kanban
        $respValid = $this->actingAs($this->admin)
            ->patchJson(route('restaurant.dispatch.update-status', $order), [
                'status' => 'dispatched',
            ]);

        $respValid->assertOk();
        $respValid->assertJsonFragment(['success' => true, 'status' => 'dispatched']);
        $this->assertEquals('dispatched', $order->fresh()->status);

        // Si la orden pasa a 'closed' o 'cancelled', no se puede volver a modificar vía updateStatus
        $order->update(['status' => 'closed']);

        $respAfterClosed = $this->actingAs($this->admin)
            ->patchJson(route('restaurant.dispatch.update-status', $order), [
                'status' => 'delivered',
            ]);

        $respAfterClosed->assertStatus(422);
        $respAfterClosed->assertJsonFragment(['success' => false]);
    }

    /**
     * 3.2: RestaurantOrderController::preBill no altera estado de órdenes cerradas o canceladas
     */
    public function test_restaurant_order_prebill_preserves_closed_and_cancelled_orders(): void
    {
        $table = RestaurantTable::create([
            'name' => 'Mesa 12',
            'capacity' => 2,
            'status' => 'available',
            'activo' => true,
        ]);

        $closedOrder = RestaurantOrder::create([
            'table_id' => $table->id,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-CLO-01',
            'order_type' => 'table',
            'status' => 'closed',
            'subtotal' => 15000,
            'total' => 15000,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('restaurant.orders.pre-bill', $closedOrder));

        $response->assertOk();
        $this->assertEquals('closed', $closedOrder->fresh()->status);
        $this->assertEquals('available', $table->fresh()->status);
    }

    /**
     * 3.3: Exclusión de abonos de ventas a crédito anuladas en Cartera y Cuadre de Caja
     */
    public function test_cancelled_credit_sales_payments_are_excluded_from_customer_balance_and_cash_register(): void
    {
        $customer = Customer::create([
            'name' => 'Pedro Pablo Deudor',
            'document' => '1144556677',
            'phone' => '3123456789',
        ]);

        // 1. Crear venta a crédito completada por $100.000
        $sale = Sale::create([
            'user_id' => $this->admin->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_document' => $customer->document,
            'invoice_number' => 'FAC-CRED-001',
            'subtotal' => 100000,
            'discount' => 0,
            'total' => 100000,
            'payment_method' => 'credit',
            'status' => 'completed',
            'is_credit' => true,
            'credit_status' => 'pending',
        ]);

        // 2. Registrar un abono previo de $40.000
        $payment = CustomerCreditPayment::create([
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'user_id' => $this->admin->id,
            'amount' => 40000,
            'method' => 'cash',
            'paid_at' => now(),
            'notes' => 'Abono inicial en efectivo',
        ]);

        $creditService = app(CustomerCreditService::class);

        // Antes de anular, el saldo del cliente es $60.000
        $this->assertEquals(60000.00, $creditService->getCustomerBalance($customer));
        $this->assertEquals(60000.00, $customer->credit_balance);

        // 3. Anular la venta a crédito con SaleService
        $saleService = app(SaleService::class);
        $saleService->cancelSale($sale, $this->admin, 'Cliente solicitó anulación total');

        $saleFresh = $sale->fresh();
        $this->assertEquals('cancelled', $saleFresh->status);
        $this->assertEquals('cancelled', $saleFresh->credit_status);

        // 4. Tras la anulación, el balance de crédito del cliente debe ser $0.00 (excluyendo pagos huérfanos de ventas canceladas)
        $this->assertEquals(0.00, $creditService->getCustomerBalance($customer));
        $this->assertEquals(0.00, $customer->fresh()->credit_balance);

        // 5. En el Arqueo de Caja del día, el abono no debe sumarse en recaudos de cartera
        $today = now()->format('Y-m-d');
        $respCash = $this->actingAs($this->admin)
            ->get(route('cash-register.index', ['date' => $today]));

        $respCash->assertOk();
        $this->assertEquals(0, $respCash->viewData('creditCollectionsCount'));
        $this->assertEquals(0.00, $respCash->viewData('creditCollectionsTotal'));
    }
}
