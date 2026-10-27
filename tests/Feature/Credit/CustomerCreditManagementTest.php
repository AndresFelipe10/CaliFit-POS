<?php

namespace Tests\Feature\Credit;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerCreditPayment;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\Credit\CustomerCreditService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCreditManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $cajero;
    protected User $admin;
    protected Customer $clienteValido;
    protected Customer $clienteSinTelefono;
    protected Customer $consumidorFinal;

    protected Product $insumoCarne;
    protected Product $platoFit;
    protected Product $suplementoProteina;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['slug' => Role::ROLE_ADMIN], ['name' => 'Administrador']);
        $roleEmployee = Role::firstOrCreate(['slug' => Role::ROLE_EMPLOYEE], ['name' => 'Empleado POS']);

        $this->admin = User::create([
            'role_id' => $roleAdmin->id,
            'name' => 'Admin CaliFit',
            'email' => 'admin@califit.com',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $this->cajero = User::create([
            'role_id' => $roleEmployee->id,
            'name' => 'Cajero Turno',
            'email' => 'cajero@califit.com',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $this->clienteValido = Customer::create([
            'name' => 'Carlos Fitness',
            'document' => '1144000888',
            'email' => 'carlos@fit.com',
            'phone' => '3127704455',
            'address' => 'Arbolatta Mall Local 12',
            'is_active' => true,
        ]);

        $this->clienteSinTelefono = Customer::create([
            'name' => 'Cliente Sin Tel',
            'document' => '9988776655',
            'email' => 'sintel@fit.com',
            'phone' => null,
            'is_active' => true,
        ]);

        $this->consumidorFinal = Customer::create([
            'name' => 'Consumidor Final',
            'document' => '222222222222',
            'email' => 'final@califit.com',
            'phone' => '0000000000',
            'is_active' => true,
        ]);

        $catInsumos = Category::create(['name' => 'Insumos Base', 'is_active' => true]);
        $catPlatos = Category::create(['name' => 'Platos Fuertes', 'is_active' => true]);
        $catSupl = Category::create(['name' => 'Suplementación', 'is_active' => true]);

        // Insumo Carne (unidad GRAMO)
        $this->insumoCarne = Product::create([
            'sku' => 'INS-CARNE',
            'nombre' => 'Carne Magra Premium',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $catInsumos->id,
            'stock' => 1000.000, // 1000g en stock
            'precio_compra' => 30.00,
            'activo' => true,
        ]);

        // Plato Fit con Receta (requiere 250g de Carne)
        $this->platoFit = Product::create([
            'sku' => 'PLA-FIT-01',
            'nombre' => 'Almuerzo Fit Proteico',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $catPlatos->id,
            'precio_venta' => 25000.00,
            'activo' => true,
        ]);

        $receta = Recipe::create([
            'producto_plato_id' => $this->platoFit->id,
            'nombre' => 'Receta Almuerzo Fit',
            'activa' => true,
        ]);

        RecipeItem::create([
            'receta_id' => $receta->id,
            'ingrediente_id' => $this->insumoCarne->id,
            'cantidad_por_porcion' => 250.000, // 250g
            'unidad' => 'GRAMO',
        ]);

        // Suplemento directo (unidad UNIDAD con stock directo)
        $this->suplementoProteina = Product::create([
            'sku' => 'SUP-ISO-01',
            'nombre' => 'Iso Whey 2lb',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $catSupl->id,
            'stock' => 10.000,
            'precio_venta' => 120000.00,
            'precio_compra' => 80000.00,
            'activo' => true,
        ]);
    }

    /**
     * Test 1: Rechazo estricto de crédito para Consumidor Final (documento 222222222222 o null).
     */
    public function test_credit_sale_is_strictly_rejected_for_final_consumer_document_222222222222(): void
    {
        $this->actingAs($this->cajero);

        // Intento con documento 222222222222
        $response = $this->postJson(route('pos.store'), [
            'customer_id' => $this->consumidorFinal->id,
            'customer_name' => 'Consumidor Final',
            'customer_document' => '222222222222',
            'is_credit' => true,
            'credit_due_date' => now()->addDays(15)->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->suplementoProteina->id,
                    'quantity' => 1,
                ]
            ],
            'payments' => []
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_id']);

        // Intento con customer_id nulo
        $responseNull = $this->postJson(route('pos.store'), [
            'customer_id' => null,
            'customer_name' => 'Cliente Casual',
            'is_credit' => true,
            'items' => [
                [
                    'product_id' => $this->suplementoProteina->id,
                    'quantity' => 1,
                ]
            ],
            'payments' => []
        ]);

        $responseNull->assertStatus(422);
        $responseNull->assertJsonValidationErrors(['customer_id']);
    }

    /**
     * Test 2: Rechazo de crédito si el cliente registrado no cuenta con teléfono de contacto.
     */
    public function test_credit_sale_is_rejected_if_customer_has_no_phone_number(): void
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson(route('pos.store'), [
            'customer_id' => $this->clienteSinTelefono->id,
            'customer_name' => $this->clienteSinTelefono->name,
            'customer_document' => $this->clienteSinTelefono->document,
            'is_credit' => true,
            'credit_due_date' => now()->addDays(15)->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->suplementoProteina->id,
                    'quantity' => 1,
                ]
            ],
            'payments' => []
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_id']);
    }

    /**
     * Test 3: Venta a crédito exitosa descuenta inventario atómicamente bajo lockForUpdate.
     */
    public function test_credit_sale_deducts_inventory_atomically_under_pessimistic_lock(): void
    {
        $this->actingAs($this->cajero);

        $initialCarneStock = (float) $this->insumoCarne->fresh()->stock;
        $initialSuplStock = (float) $this->suplementoProteina->fresh()->stock;

        $response = $this->postJson(route('pos.store'), [
            'customer_id' => $this->clienteValido->id,
            'customer_name' => $this->clienteValido->name,
            'customer_document' => $this->clienteValido->document,
            'is_credit' => true,
            'credit_due_date' => now()->addDays(15)->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->platoFit->id,
                    'quantity' => 2, // Requiere 2 x 250g = 500g de carne
                ],
                [
                    'product_id' => $this->suplementoProteina->id,
                    'quantity' => 1, // Descuenta 1 unidad de stock directo
                ]
            ],
            'payments' => []
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $saleId = $response->json('sale_id');
        $sale = Sale::findOrFail($saleId);

        // Verificaciones del modelo de venta a crédito
        $this->assertTrue($sale->is_credit);
        $this->assertEquals('pending', $sale->credit_status);
        $this->assertEquals(now()->addDays(15)->format('Y-m-d'), $sale->credit_due_date->format('Y-m-d'));
        $this->assertEquals(170000.00, (float) $sale->total); // 2 * 25000 + 120000 = 170000
        $this->assertEquals(0.00, $sale->credit_paid_amount);
        $this->assertEquals(170000.00, $sale->credit_pending_amount);

        // Verificación de descuento atómico de inventario
        $this->assertEquals($initialCarneStock - 500.000, (float) $this->insumoCarne->fresh()->stock);
        $this->assertEquals($initialSuplStock - 1.000, (float) $this->suplementoProteina->fresh()->stock);

        // Movimientos de inventario registrados como SALIDA_VENTA
        $movCarne = InventoryMovement::where('product_id', $this->insumoCarne->id)
            ->where('type', InventoryMovement::TYPE_SALIDA_VENTA)
            ->where('reference_id', $sale->id)
            ->first();
        $this->assertNotNull($movCarne);
        $this->assertEquals(500.000, (float) $movCarne->quantity);

        $movSupl = InventoryMovement::where('product_id', $this->suplementoProteina->id)
            ->where('type', InventoryMovement::TYPE_SALIDA_VENTA)
            ->where('reference_id', $sale->id)
            ->first();
        $this->assertNotNull($movSupl);
        $this->assertEquals(1.000, (float) $movSupl->quantity);
    }

    /**
     * Test 4: Aislamiento del Cuadre de Caja diario: la venta a crédito NO suma al arqueo ni recaudo de hoy.
     */
    public function test_credit_sale_does_not_add_revenue_to_cash_drawer_or_daily_cash_register(): void
    {
        $this->actingAs($this->cajero);

        // Creamos una venta normal de contado en efectivo de $20.000
        $this->postJson(route('pos.store'), [
            'customer_id' => $this->clienteValido->id,
            'customer_name' => $this->clienteValido->name,
            'customer_document' => $this->clienteValido->document,
            'is_credit' => false,
            'items' => [
                ['product_id' => $this->platoFit->id, 'quantity' => 1]
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 25000.00, 'cash_received' => 30000.00]
            ]
        ])->assertStatus(201);

        // Creamos una venta a crédito por $120.000
        $this->postJson(route('pos.store'), [
            'customer_id' => $this->clienteValido->id,
            'customer_name' => $this->clienteValido->name,
            'customer_document' => $this->clienteValido->document,
            'is_credit' => true,
            'credit_due_date' => now()->addDays(10)->format('Y-m-d'),
            'items' => [
                ['product_id' => $this->suplementoProteina->id, 'quantity' => 1]
            ],
            'payments' => []
        ])->assertStatus(201);

        // Consultamos el cuadre de caja de hoy
        $responseCash = $this->actingAs($this->admin)->get(route('cash-register.index'));
        $responseCash->assertStatus(200);

        // El total facturado incluye ambas ($25.000 + $120.000 = $145.000)
        $responseCash->assertViewHas('netTotalBilled', 145000.00);

        // Pero el total recaudado y efectivo en gaveta SOLO contempla la venta de contado ($25.000), aislando los $120.000 fiados
        $responseCash->assertViewHas('totalCollected', 25000.00);
        $responseCash->assertViewHas('cashInDrawer', 25000.00);
        $responseCash->assertViewHas('creditSalesTotal', 120000.00);
        $responseCash->assertViewHas('creditSalesCount', 1);
    }

    /**
     * Test 5: Módulo de Cartera y Liquidación de Deuda:
     * El abono/liquidación posterior de la factura a crédito entra formalmente al Cuadre de Caja de hoy.
     */
    public function test_register_credit_payment_collects_debt_and_adds_money_to_today_cash_register(): void
    {
        $this->actingAs($this->cajero);

        // 1. Crear venta a crédito con fecha de ayer (simulando que se fió ayer)
        $resSale = $this->postJson(route('pos.store'), [
            'customer_id' => $this->clienteValido->id,
            'customer_name' => $this->clienteValido->name,
            'customer_document' => $this->clienteValido->document,
            'is_credit' => true,
            'credit_due_date' => now()->addDays(5)->format('Y-m-d'),
            'items' => [
                ['product_id' => $this->platoFit->id, 'quantity' => 2] // Total $50.000
            ],
            'payments' => []
        ]);
        $resSale->assertStatus(201);

        $sale = Sale::findOrFail($resSale->json('sale_id'));
        // Modificar timestamp a ayer
        $sale->created_at = Carbon::yesterday();
        $sale->save();

        $this->assertEquals(50000.00, $this->clienteValido->fresh()->credit_balance);

        // 2. Cliente paga un abono de $30.000 en efectivo hoy (entrega billete de $50.000, vuelto $20.000)
        $resPayment = $this->post(route('credit.pay'), [
            'sale_id' => $sale->id,
            'amount' => 30000.00,
            'method' => 'cash',
            'cash_received' => 50000.00,
            'reference' => 'Abono parcial #1'
        ]);

        $resPayment->assertRedirect();
        $resPayment->assertSessionHas('success');

        $saleFresh = $sale->fresh(['creditPayments', 'payments']);
        $this->assertEquals(30000.00, $saleFresh->credit_paid_amount);
        $this->assertEquals(20000.00, $saleFresh->credit_pending_amount);
        $this->assertEquals('pending', $saleFresh->credit_status);

        // Verificamos registro en customer_credit_payments
        $this->assertDatabaseHas('customer_credit_payments', [
            'sale_id' => $sale->id,
            'customer_id' => $this->clienteValido->id,
            'user_id' => $this->cajero->id,
            'method' => 'cash',
            'amount' => 30000.00,
            'cash_received' => 50000.00,
            'change_given' => 20000.00
        ]);

        // Verificamos que el dinero entró formalmente al Cuadre de Caja de hoy
        $responseCashToday = $this->actingAs($this->admin)->get(route('cash-register.index'));
        $responseCashToday->assertStatus(200);
        $responseCashToday->assertViewHas('cashInDrawer', 30000.00);
        $responseCashToday->assertViewHas('totalCollected', 30000.00);
        $responseCashToday->assertViewHas('creditCollectionsTotal', 30000.00);
        $responseCashToday->assertViewHas('creditCollectionsCount', 1);

        // 3. Cliente paga los $20.000 restantes mediante Nequi (Transferencia)
        $resPayment2 = $this->post(route('credit.pay'), [
            'sale_id' => $sale->id,
            'amount' => 20000.00,
            'method' => 'transfer',
            'reference' => 'Nequi M-9988'
        ]);

        $resPayment2->assertRedirect();

        $saleFinal = $sale->fresh();
        $this->assertEquals(50000.00, $saleFinal->credit_paid_amount);
        $this->assertEquals(0.00, $saleFinal->credit_pending_amount);
        $this->assertEquals('paid', $saleFinal->credit_status);
        $this->assertEquals(0.00, $this->clienteValido->fresh()->credit_balance);
    }

    /**
     * Test 6: Rechazo estricto si se intenta abonar un monto mayor al saldo pendiente de la factura fiada.
     */
    public function test_cannot_pay_more_than_pending_credit_balance(): void
    {
        $this->actingAs($this->cajero);

        $resSale = $this->postJson(route('pos.store'), [
            'customer_id' => $this->clienteValido->id,
            'customer_name' => $this->clienteValido->name,
            'customer_document' => $this->clienteValido->document,
            'is_credit' => true,
            'items' => [
                ['product_id' => $this->platoFit->id, 'quantity' => 1] // Total $25.000
            ],
            'payments' => []
        ]);
        $saleId = $resSale->json('sale_id');

        // Intento de abonar $30.000 a una factura de $25.000
        $resPay = $this->post(route('credit.pay'), [
            'sale_id' => $saleId,
            'amount' => 30000.00,
            'method' => 'cash',
            'cash_received' => 30000.00,
        ]);

        $resPay->assertSessionHasErrors(['amount']);
        $sale = Sale::findOrFail($saleId);
        $this->assertEquals(0.00, $sale->credit_paid_amount);
    }

    /**
     * Test 7: El módulo de Cartera / Fiados (/credit/cartera) carga inmediatamente con código 200
     * y sin timeouts, suministrando métricas exactas y facturas pendientes.
     */
    public function test_credit_cartera_index_loads_successfully_without_timeout(): void
    {
        $this->actingAs($this->cajero);

        // Crear una venta a crédito pendiente
        $this->postJson(route('pos.store'), [
            'customer_id' => $this->clienteValido->id,
            'customer_name' => $this->clienteValido->name,
            'customer_document' => $this->clienteValido->document,
            'is_credit' => true,
            'credit_due_date' => now()->addDays(7)->format('Y-m-d'),
            'items' => [
                ['product_id' => $this->platoFit->id, 'quantity' => 2] // Total $50.000
            ],
            'payments' => []
        ])->assertStatus(201);

        $response = $this->get(route('credit.index'));

        $response->assertStatus(200);
        $response->assertViewIs('customers.credit.index');
        $response->assertViewHas('totalReceivable', 50000.00);
        $response->assertViewHas('pendingCount', 1);
        $response->assertViewHas('pendingSales');
        $response->assertViewHas('customersWithCredit');
        $response->assertSee('50.000');
        $response->assertSee($this->clienteValido->name);
    }
}
