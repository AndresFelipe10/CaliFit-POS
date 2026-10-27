<?php

namespace Tests\Feature\Http;

use App\Models\Addition;
use App\Models\AdditionItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $cajero;
    protected Customer $cliente;
    protected Product $carne;
    protected Product $platoFit;
    protected Product $suplemento;
    protected Addition $adicionCarne;

    protected function setUp(): void
    {
        parent::setUp();

        $roleEmployee = Role::create([
            'slug' => Role::ROLE_EMPLOYEE,
            'name' => 'Empleado POS',
        ]);

        $this->cajero = User::create([
            'role_id' => $roleEmployee->id,
            'name' => 'Cajero Test',
            'email' => 'cajero@califit.com',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $this->cliente = Customer::create([
            'name' => 'Carlos Fitness',
            'document' => '1005223344',
            'email' => 'carlos@fit.com',
            'phone' => '3127704455',
            'is_active' => true,
        ]);

        $catInsumo = Category::create(['name' => 'Insumos', 'is_active' => true]);
        $catPlato = Category::create(['name' => 'Platos', 'is_active' => true]);
        $catSupl = Category::create(['name' => 'Suplementos', 'is_active' => true]);

        // Insumo Carne (5.000 g)
        $this->carne = Product::create([
            'sku' => 'INS-001',
            'nombre' => 'Pechuga de Pollo',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $catInsumo->id,
            'stock' => 5000.000,
            'stock_minimo' => 500.000,
            'precio_compra' => 20.00,
            'precio_venta' => 0.00,
            'activo' => true,
        ]);

        // Plato Fit: Pollo Arbolatta (250 g de pollo)
        $this->platoFit = Product::create([
            'sku' => 'PLA-001',
            'nombre' => 'Bowl Pollo Arbolatta',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $catPlato->id,
            'stock' => 0.000,
            'precio_compra' => 10000.00,
            'precio_venta' => 25000.00,
            'activo' => true,
        ]);

        $receta = Recipe::create([
            'producto_plato_id' => $this->platoFit->id,
            'nombre' => 'Receta Pollo Arbolatta',
            'activa' => true,
        ]);

        RecipeItem::create([
            'receta_id' => $receta->id,
            'ingrediente_id' => $this->carne->id,
            'cantidad_por_porcion' => 250.000,
            'unidad' => 'GRAMO',
        ]);

        // Adición: 120 g Pollo Extra ($8.000)
        $this->adicionCarne = Addition::create([
            'nombre' => 'Pollo Extra 120g',
            'precio' => 8000.00,
            'activo' => true,
        ]);

        AdditionItem::create([
            'adicion_id' => $this->adicionCarne->id,
            'ingrediente_id' => $this->carne->id,
            'cantidad' => 120.000,
        ]);

        $this->platoFit->adiciones()->attach($this->adicionCarne->id);

        // Suplemento venta directa
        $this->suplemento = Product::create([
            'sku' => 'SUP-001',
            'nombre' => 'Proteína Whey Isolate',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $catSupl->id,
            'stock' => 15.000,
            'stock_minimo' => 2.000,
            'precio_compra' => 120000.00,
            'precio_venta' => 180000.00,
            'activo' => true,
        ]);
    }

    public function test_guest_cannot_access_pos(): void
    {
        $response = $this->get(route('pos.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_pos(): void
    {
        $this->actingAs($this->cajero);

        $response = $this->get(route('pos.index'));

        $response->assertStatus(200);
        $response->assertSee('Punto de Venta POS');
        $response->assertSee('Bowl Pollo Arbolatta');
        $response->assertSee('Proteína Whey Isolate');
        $response->assertSee('COBRAR / LIQUIDAR');
        $response->assertSee('id="btnOpenPaymentModal"', false);
        $response->assertSee('id="paymentModal"', false);
        $response->assertSee('id="btnSubmitSale"', false);
        $response->assertDontSee('recalculateSummary()');
    }

    public function test_process_sale_with_additions_via_http(): void
    {
        $this->actingAs($this->cajero);

        // Venta: 1 Plato Fit ($25.000) + 1 Adición ($8.000) = $33.000
        // Pago mixto: $20.000 Efectivo (recibido $50.000) + $13.000 Transferencia
        $payload = [
            'customer_id' => $this->cliente->id,
            'customer_name' => $this->cliente->name,
            'customer_document' => $this->cliente->document,
            'discount_percentage' => 0,
            'delivery_fee' => 0,
            'items' => [
                [
                    'product_id' => $this->platoFit->id,
                    'quantity' => 1,
                    'additions' => [
                        [
                            'addition_id' => $this->adicionCarne->id,
                            'quantity' => 1,
                        ],
                    ],
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 20000.00,
                    'cash_received' => 50000.00,
                ],
                [
                    'method' => 'transfer',
                    'amount' => 13000.00,
                    'reference' => 'NEQUI-998877',
                ],
            ],
        ];

        $response = $this->postJson(route('pos.store'), $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Venta registrada con éxito',
        ]);
        $response->assertJsonStructure(['success', 'sale_id', 'invoice_number', 'message']);

        $saleId = $response->json('sale_id');

        // Validar base de datos
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'subtotal' => 33000.00,
            'total' => 33000.00,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('sale_detail_additions', [
            'adicion_id' => $this->adicionCarne->id,
            'quantity' => 1.000,
            'unit_price' => 8000.00,
        ]);

        // Consumo verificado: 250 g plato + 120 g adición = 370 g
        // Stock final: 5000 - 370 = 4630 g
        $this->carne->refresh();
        $this->assertEquals(4630.000, (float) $this->carne->stock);
    }

    public function test_process_sale_rejected_on_multiple_cash_lines(): void
    {
        $this->actingAs($this->cajero);

        $payload = [
            'customer_id' => $this->cliente->id,
            'items' => [
                [
                    'product_id' => $this->suplemento->id,
                    'quantity' => 1,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 100000.00,
                    'cash_received' => 100000.00,
                ],
                [
                    'method' => 'cash',
                    'amount' => 80000.00,
                    'cash_received' => 80000.00,
                ],
            ],
        ];

        $response = $this->postJson(route('pos.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payments']);
    }

    public function test_pos_counter_sale_of_supplement_traces_inventory_and_completes_successfully(): void
    {
        $this->actingAs($this->cajero);

        $initialStock = (float) $this->suplemento->stock;

        // Venta mostrador de 1 suplemento ($180.000) en efectivo entregando $200.000 (vuelto $20.000)
        $payload = [
            'customer_id' => $this->cliente->id,
            'customer_name' => $this->cliente->name,
            'customer_document' => $this->cliente->document,
            'discount_percentage' => 0,
            'delivery_fee' => 0,
            'items' => [
                [
                    'product_id' => $this->suplemento->id,
                    'quantity' => 1,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 180000.00,
                    'cash_received' => 200000.00,
                ],
            ],
        ];

        $response = $this->postJson(route('pos.store'), $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Venta registrada con éxito',
        ]);

        $saleId = $response->json('sale_id');
        $this->assertNotNull($saleId);

        // Validar trazabilidad en sales
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'customer_id' => $this->cliente->id,
            'subtotal' => 180000.00,
            'total' => 180000.00,
            'status' => 'completed',
        ]);

        // Validar trazabilidad en sale_payments
        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $saleId,
            'method' => 'cash',
            'amount' => 180000.00,
            'cash_received' => 200000.00,
            'change_given' => 20000.00,
        ]);

        // Validar trazabilidad en sale_details
        $this->assertDatabaseHas('sale_details', [
            'sale_id' => $saleId,
            'product_id' => $this->suplemento->id,
            'quantity' => 1.000,
            'unit_price' => 180000.00,
            'subtotal' => 180000.00,
        ]);

        // Validar decremento estricto en inventario (de 15 a 14)
        $this->suplemento->refresh();
        $this->assertEquals($initialStock - 1, (float) $this->suplemento->stock);

        // Validar trazabilidad de compras en el cliente
        $this->cliente->refresh();
        $this->assertEquals(1, $this->cliente->sales()->where('status', 'completed')->count());
    }
}
