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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->enum('tipo_producto', ['INSUMO', 'PREPARACION', 'PLATO', 'SUPLEMENTO']);
            $table->enum('unidad_base', ['UNIDAD', 'GRAMO', 'MILILITRO']);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->decimal('stock', 12, 3)->default(0.000);
            $table->decimal('stock_minimo', 12, 3)->default(0.000);
            $table->decimal('precio_compra', 12, 2)->default(0.00);
            $table->decimal('precio_venta', 12, 2)->default(0.00);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('tipo_producto');
            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
