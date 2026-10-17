<?php

namespace Tests\Feature\Restaurant;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPrecountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $restaurant;
    protected User $admin;
    protected User $waiter;
    protected RestaurantTable $table;
    protected Category $category;
    protected Product $dish1;
    protected Product $dish2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $this->employeeRole = Role::create([
            'name' => 'Empleado',
            'slug' => Role::ROLE_EMPLOYEE,
        ]);

        $this->restaurant = Business::create([
            'name' => 'Restaurante Fogón Caleño',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create(['name' => 'Admin Restaurante']);
        $this->restaurant->users()->attach($this->admin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->waiter = User::factory()->create(['name' => 'Mesero Pedro']);
        $this->restaurant->users()->attach($this->waiter->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $this->table = RestaurantTable::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Mesa 1',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $this->category = Category::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Platos Fuertes',
        ]);

        $this->dish1 = Product::create([
            'business_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Pollo Asado',
            'sku' => 'PLATO-001',
            'sale_price' => 25000.00,
            'stock' => 50,
            'product_type' => 'dish',
            'is_active' => true,
        ]);

        $this->dish2 = Product::create([
            'business_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Postre Tres Leches',
            'sku' => 'POSTRE-001',
            'sale_price' => 12000.00,
            'stock' => 30,
            'product_type' => 'dish',
            'is_active' => true,
        ]);
    }

    public function test_precount_emission_keeps_order_open_allows_adding_more_items_and_completing_checkout(): void
    {
        // 1. Crear comanda de salón con 1 plato
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'table_id' => $this->table->id,
            'order_number' => 'ORD-0034',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 25000.00,
            'total' => 25000.00,
        ]);

        $this->table->update(['status' => 'occupied']);

        RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->dish1->id,
            'quantity' => 1,
            'unit_price' => 25000.00,
            'subtotal' => 25000.00,
            'status' => 'pending',
            'batch_number' => 1,
        ]);

        // 2. Disparar la impresión de pre-cuenta mediante la ruta print-precount
        $precountResponse = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.print-precount', $order));

        $precountResponse->assertOk();
        $precountResponse->assertViewIs('restaurant.orders.pre-bill-ticket');
        $precountResponse->assertSee('ESTADO DE CONSUMO / PRE-CUENTA');

        // 3. Validar que la orden tenga precount_printed_at != null, estado 'open' y canBeModified() === true
        $freshOrder = $order->fresh();
        $this->assertEquals('open', $freshOrder->status);
        $this->assertNotNull($freshOrder->precount_printed_at);
        $this->assertTrue($freshOrder->canBeModified());

        // La mesa pasa a 'billed' para indicar "En cobro" en el mapa de mesas
        $this->assertEquals('billed', $this->table->fresh()->status);

        // 4. Validar vista de detalle show: debe mostrar badge 'En Cobro / Pre-cuenta',
        // mantener visible el formulario "Agregar Platos a la Comanda" y el botón "Cobrar / Facturar",
        // y NO debe mostrar el banner verde de comanda cerrada.
        $showResponse = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.show', $order));

        $showResponse->assertOk();
        $showResponse->assertSee('En Cobro / Pre-cuenta');
        $showResponse->assertSee('Agregar Platos a la Comanda');
        $showResponse->assertSee('Cobrar / Facturar');
        $showResponse->assertDontSee('Esta comanda ha sido liquidada en su totalidad');

        // 5. Enviar petición para agregar un segundo plato tras imprimir pre-cuenta
        $addDishResponse = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('restaurant.orders.items.store', $order), [
                'items' => [
                    [
                        'product_id' => $this->dish2->id,
                        'quantity' => 1,
                        'notes' => 'Con salsa adicional',
                    ],
                ],
            ]);

        $addDishResponse->assertRedirect();

        // 6. Verificar que el segundo plato se agregó, precount_printed_at se reseteó y la mesa volvió a 'occupied'
        $updatedOrder = $order->fresh(['items', 'table']);
        $this->assertCount(2, $updatedOrder->items);
        $this->assertEquals(37000.00, $updatedOrder->total);
        $this->assertNull($updatedOrder->precount_printed_at, 'precount_printed_at debe limpiarse al agregar nuevos platos');
        $this->assertEquals('occupied', $this->table->fresh()->status, 'La mesa debe volver a occupied al agregar consumos');

        // 7. Cobrar la comanda en su totalidad
        $settleResponse = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('restaurant.orders.bill', $order), [
                'payments' => [
                    [
                        'method' => 'cash',
                        'amount' => 37000.00,
                        'cash_received' => 40000.00,
                    ],
                ],
            ]);

        $settleResponse->assertRedirect();

        // 8. Verificar cierre definitivo de la orden a 'billed' y liberación de la mesa
        $closedOrder = $order->fresh();
        $this->assertEquals('billed', $closedOrder->status);
        $this->assertFalse($closedOrder->canBeModified());
        $this->assertNotNull($closedOrder->sale_id);
        $this->assertEquals('available', $this->table->fresh()->status);

        // Al consultar nuevamente show, ahora sí debe mostrar el banner verde de comanda cerrada
        $finalShowResponse = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.show', $order));

        $finalShowResponse->assertOk();
        $finalShowResponse->assertSee('Esta comanda ha sido liquidada en su totalidad');
        $finalShowResponse->assertDontSee('Agregar Platos a la Comanda');
    }
}
