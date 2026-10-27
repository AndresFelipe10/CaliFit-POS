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
        Schema::create('recetas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_plato_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('receta_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receta_id')->constrained('recetas')->cascadeOnDelete();
            $table->foreignId('ingrediente_id')->constrained('products')->restrictOnDelete();
            $table->decimal('cantidad_por_porcion', 12, 3);
            $table->string('unidad', 20)->default('GRAMO');
            $table->timestamps();

            $table->unique(['receta_id', 'ingrediente_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receta_detalle');
        Schema::dropIfExists('recetas');
    }
};
