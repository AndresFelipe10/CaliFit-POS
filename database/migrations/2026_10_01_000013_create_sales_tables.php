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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name', 150);
            $table->string('customer_document', 30);
            $table->foreignId('restaurant_order_id')->nullable()->constrained('restaurant_orders')->nullOnDelete();
            $table->enum('order_type', ['retail', 'table', 'delivery', 'takeout'])->nullable();
            $table->string('invoice_number', 50)->unique();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0.00);
            $table->decimal('discount_percentage', 5, 2)->default(0.00);
            $table->decimal('delivery_fee', 12, 2)->default(0.00);
            $table->decimal('service_fee', 12, 2)->default(0.00);
            $table->decimal('tax_inc', 12, 2)->default(0.00);
            $table->decimal('total', 12, 2);
            $table->string('payment_method', 20)->default('cash');
            $table->enum('status', ['completed', 'cancelled'])->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('sale_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();

            $table->index(['sale_id', 'product_id']);
        });

        Schema::create('sale_detail_additions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_detail_id')->constrained('sale_details')->cascadeOnDelete();
            $table->foreignId('adicion_id')->constrained('adiciones')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();

            $table->index('sale_detail_id');
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->enum('method', ['cash', 'card', 'transfer', 'other']);
            $table->decimal('amount', 12, 2);
            $table->string('reference', 60)->nullable();
            $table->decimal('cash_received', 12, 2)->nullable();
            $table->decimal('change_given', 12, 2)->nullable();
            $table->timestamps();

            $table->index('sale_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_detail_additions');
        Schema::dropIfExists('sale_details');
        Schema::dropIfExists('sales');
    }
};
