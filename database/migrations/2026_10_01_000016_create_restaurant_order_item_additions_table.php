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
        Schema::create('restaurant_order_item_additions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('restaurant_order_items')->cascadeOnDelete();
            $table->foreignId('adicion_id')->constrained('adiciones')->restrictOnDelete();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0.00);
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->timestamps();

            $table->index('order_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_order_item_additions');
    }
};
