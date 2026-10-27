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
        Schema::create('adiciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->decimal('precio', 12, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('adicion_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adicion_id')->constrained('adiciones')->cascadeOnDelete();
            $table->foreignId('ingrediente_id')->constrained('products')->restrictOnDelete();
            $table->decimal('cantidad', 12, 3);
            $table->timestamps();

            $table->unique(['adicion_id', 'ingrediente_id']);
        });

        Schema::create('producto_adicion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('adicion_id')->constrained('adiciones')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['producto_id', 'adicion_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producto_adicion');
        Schema::dropIfExists('adicion_detalle');
        Schema::dropIfExists('adiciones');
    }
};
