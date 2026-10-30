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
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30); // comanda | factura | precuenta | precuenta_parcial | despacho | cuadre
            $table->unsignedBigInteger('referencia_id')->nullable(); // ID de RestaurantOrder o Sale
            $table->longText('contenido'); // Bytes ESC/POS codificados en Base64
            $table->string('estado', 20)->default('pendiente')->index(); // pendiente | imprimiendo | impreso | error
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamp('tomado_at')->nullable();
            $table->timestamp('impreso_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};

