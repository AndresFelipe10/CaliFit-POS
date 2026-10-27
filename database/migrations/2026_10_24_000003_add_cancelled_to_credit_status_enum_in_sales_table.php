<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Añadir 'cancelled' al ENUM credit_status en la tabla sales si estamos en MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sales MODIFY COLUMN credit_status ENUM('none', 'pending', 'paid', 'cancelled') NOT NULL DEFAULT 'none'");
        }

        // Normalizar cualquier venta cancelada que sea de crédito
        DB::table('sales')
            ->where('is_credit', true)
            ->where('status', 'cancelled')
            ->update(['credit_status' => 'cancelled']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('sales')
            ->where('credit_status', 'cancelled')
            ->update(['credit_status' => 'none']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sales MODIFY COLUMN credit_status ENUM('none', 'pending', 'paid') NOT NULL DEFAULT 'none'");
        }
    }
};
