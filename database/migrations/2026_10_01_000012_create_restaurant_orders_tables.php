<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('restaurant_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->nullable()->constrained('restaurant_tables')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('order_number', 30)->unique();
            $table->enum('order_type', ['table', 'delivery', 'takeout'])->default('table');
            $table->enum('status', ['open', 'in_kitchen', 'dispatched', 'delivered', 'billed', 'closed', 'cancelled'])->default('open');
            $table->unsignedTinyInteger('guest_count')->default(1);
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name', 150)->nullable();
            $table->decimal('delivery_fee', 12, 2)->default(0.00);
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('total', 12, 2)->default(0.00);
            $table->string('notes', 500)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('order_type');
        });

        Schema::create('restaurant_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('restaurant_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->string('notes', 255)->nullable();
            $table->enum('status', ['pending', 'kitchen', 'served', 'cancelled'])->default('pending');
            $table->boolean('printed_to_kitchen')->default(false);
            $table->unsignedInteger('batch_number')->default(1);
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_order_items');
        Schema::dropIfExists('restaurant_orders');
    }
};
