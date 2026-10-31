<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            DB::statement("UPDATE products SET nombre = REPLACE(nombre, 'Ejercicio', 'Ejecutivo') WHERE nombre LIKE '%Ejercicio%'");
            DB::statement("UPDATE products SET descripcion = REPLACE(descripcion, 'Ejercicio', 'Ejecutivo') WHERE descripcion LIKE '%Ejercicio%'");
        }

        if (Schema::hasTable('recetas')) {
            DB::statement("UPDATE recetas SET nombre = REPLACE(nombre, 'Ejercicio', 'Ejecutivo') WHERE nombre LIKE '%Ejercicio%'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products')) {
            DB::statement("UPDATE products SET nombre = REPLACE(nombre, 'Ejecutivo', 'Ejercicio') WHERE nombre LIKE '%Ejecutivo%'");
            DB::statement("UPDATE products SET descripcion = REPLACE(descripcion, 'Ejecutivo', 'Ejercicio') WHERE descripcion LIKE '%Ejecutivo%'");
        }

        if (Schema::hasTable('recetas')) {
            DB::statement("UPDATE recetas SET nombre = REPLACE(nombre, 'Ejecutivo', 'Ejercicio') WHERE nombre LIKE '%Ejecutivo%'");
        }
    }
};
