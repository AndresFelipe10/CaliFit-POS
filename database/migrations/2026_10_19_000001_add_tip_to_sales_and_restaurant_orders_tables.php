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
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'tip')) {
                $table->decimal('tip', 12, 2)->default(0.00)->after('tax_inc');
            }
        });

        Schema::table('restaurant_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurant_orders', 'tip')) {
                $table->decimal('tip', 12, 2)->default(0.00)->after('subtotal');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            if (Schema::hasColumn('restaurant_orders', 'tip')) {
                $table->dropColumn('tip');
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'tip')) {
                $table->dropColumn('tip');
            }
        });
    }
};
