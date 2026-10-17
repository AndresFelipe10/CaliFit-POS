<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InputValidationAndSanitizationTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $restaurant;
    protected Business $retail;
    protected User $restaurantAdmin;
    protected User $restaurantEmployee;
    protected User $retailAdmin;
    protected Product $dish;

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
            'name' => 'Restaurante Gourmet Cali',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->retail = Business::create([
            'name' => 'Retail San Fernando',
            'business_type' => 'retail',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->restaurantAdmin = User::factory()->create();
        $this->restaurant->users()->attach($this->restaurantAdmin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->restaurantEmployee = User::factory()->create();
        $this->restaurant->users()->attach($this->restaurantEmployee->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $this->retailAdmin = User::factory()->create();
        $this->retail->users()->attach($this->retailAdmin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $category = Category::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Platos Fuertes',
        ]);

        $this->dish = Product::create([
            'business_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name' => 'Chuleta Valluna',
            'sku' => 'CHU-01',
            'purchase_price' => 12000.00,
            'sale_price' => 28000.00,
            'stock' => 50,
            'min_stock' => 5,
            'product_type' => 'dish',
            'base_unit' => 'unit',
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Delivery rejects invalid phone, customer name and junk addresses.
     */
    public function test_delivery_order_rejects_invalid_phone_customer_name_and_garbage_address(): void
    {
        $this->actingAs($this->restaurantEmployee);

        $payload = [
            'order_type' => 'delivery',
            'customer_name' => 'kakas11111',
            'delivery_phone' => '111111111ssssssssss',
            'delivery_address' => 'ssssssssssssssss',
            'delivery_fee' => 3000,
            'items' => [
                [
                    'product_id' => $this->dish->id,
                    'quantity' => 1,
                    'unit_price' => 28000,
                ],
            ],
        ];

        $response = $this->post(route('restaurant.orders.store-delivery'), $payload);

        $response->assertSessionHasErrors(['customer_name', 'delivery_phone', 'delivery_address']);
    }

    /**
     * Test 2: Delivery accepts valid data and rejects negative or excessive delivery fee.
     */
    public function test_delivery_order_accepts_valid_data_and_rejects_invalid_delivery_fee(): void
    {
        $this->actingAs($this->restaurantEmployee);

        // 2a. Reject negative fee
        $negativePayload = [
            'order_type' => 'delivery',
            'customer_name' => 'Andrés Grajales',
            'delivery_phone' => '3001234567',
            'delivery_address' => 'Calle 5 # 38-25',
            'delivery_fee' => -5000,
            'items' => [
                [
                    'product_id' => $this->dish->id,
                    'quantity' => 1,
                    'unit_price' => 28000,
                ],
            ],
        ];

        $responseNegative = $this->post(route('restaurant.orders.store-delivery'), $negativePayload);
        $responseNegative->assertSessionHasErrors(['delivery_fee']);

        // 2b. Reject excessive fee (> 1.000.000)
        $excessivePayload = array_merge($negativePayload, ['delivery_fee' => 1500000]);
        $responseExcessive = $this->post(route('restaurant.orders.store-delivery'), $excessivePayload);
        $responseExcessive->assertSessionHasErrors(['delivery_fee']);

        // 2c. Accept valid delivery order
        $validPayload = array_merge($negativePayload, ['delivery_fee' => 5000]);
        $responseValid = $this->post(route('restaurant.orders.store-delivery'), $validPayload);

        $responseValid->assertSessionHasNoErrors();
        $responseValid->assertRedirect();

        $this->assertDatabaseHas('restaurant_orders', [
            'business_id' => $this->restaurant->id,
            'customer_name' => 'Andrés Grajales',
            'delivery_phone' => '3001234567',
            'delivery_address' => 'Calle 5 # 38-25',
            'delivery_fee' => 5000,
        ]);
    }

    /**
     * Test 3: Delivery create view renders proper HTML5 constraints and no mouseup preventDefault.
     */
    public function test_delivery_create_view_renders_ergonomic_attributes_and_safe_scripts(): void
    {
        $this->actingAs($this->restaurantEmployee);

        $response = $this->get(route('restaurant.orders.create-delivery'));

        $response->assertOk();

        $content = $response->getContent();

        // HTML5 attributes on delivery_fee
        $this->assertStringContainsString('id="delivery_fee"', $content);
        $this->assertStringContainsString('step="500"', $content);
        $this->assertStringContainsString('min="0"', $content);
        $this->assertStringContainsString('max="9999999"', $content);

        // HTML5 attributes on customer fields
        $this->assertStringContainsString('id="customer_name"', $content);
        $this->assertStringContainsString('minlength="2"', $content);
        $this->assertStringContainsString('maxlength="80"', $content);

        $this->assertStringContainsString('id="delivery_phone"', $content);
        $this->assertStringContainsString('minlength="7"', $content);
        $this->assertStringContainsString('maxlength="15"', $content);
        $this->assertStringContainsString('inputmode="tel"', $content);

        $this->assertStringContainsString('id="delivery_address"', $content);
        $this->assertStringContainsString('minlength="5"', $content);
        $this->assertStringContainsString('maxlength="150"', $content);

        // Header breathing room
        $this->assertStringContainsString('pt-2 pt-md-3', $content);

        // Verify layout does not have the bug-causing e.preventDefault() on mouseup for number inputs
        $this->assertStringNotContainsString("input.addEventListener('mouseup', function (e) { e.preventDefault(); });", $content);
        $this->assertStringNotContainsString('input.addEventListener(\'mouseup\', function (e) { e.preventDefault(); });', $content);

        // Verify wheel scroll listener is present
        $this->assertStringContainsString('activeElement.type === \'number\'', $content);
    }

    /**
     * Test 4: Customer creation form rejects non-numeric/alpha phones and repetitive junk addresses.
     */
    public function test_customer_creation_rejects_invalid_inputs(): void
    {
        $this->actingAs($this->retailAdmin);

        $invalidPayload = [
            'name' => 'Carlos Pérez',
            'identification_number' => '12345678',
            'phone' => '300abc1234',
            'address' => 'aaaaaaaaaaa',
        ];

        $response = $this->post(route('customers.store'), $invalidPayload);
        $response->assertSessionHasErrors(['phone', 'address']);

        $validPayload = [
            'name' => 'Carlos Pérez',
            'identification_number' => '12345678',
            'phone' => '3001234567',
            'address' => 'Carrera 15 # 4-30',
        ];

        $responseValid = $this->post(route('customers.store'), $validPayload);
        $responseValid->assertSessionHasNoErrors();
    }

    /**
     * Test 5: Supplier creation form rejects invalid contact names and malformed phones.
     */
    public function test_supplier_creation_rejects_invalid_contact_and_phone(): void
    {
        $this->actingAs($this->retailAdmin);

        $invalidPayload = [
            'name' => 'Distribuidora del Valle SAS',
            'identification_number' => '900123456-1',
            'contact_name' => '123456Juan',
            'phone' => 'phone-letras-invalido',
            'address' => 'Calle 10 # 20-30',
        ];

        $response = $this->post(route('suppliers.store'), $invalidPayload);
        $response->assertSessionHasErrors(['contact_name', 'phone']);

        $validPayload = [
            'name' => 'Distribuidora del Valle SAS',
            'identification_number' => '900123456-1',
            'contact_name' => 'Juan Valencia',
            'phone' => '+573151234567',
            'address' => 'Calle 10 # 20-30',
        ];

        $responseValid = $this->post(route('suppliers.store'), $validPayload);
        $responseValid->assertSessionHasNoErrors();
    }
}
