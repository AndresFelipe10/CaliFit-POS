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
        Schema::table('restaurant_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('restaurant_orders', 'delivery_phone')) {
                $table->string('delivery_phone', 40)->nullable()->after('customer_name');
            }
            if (!Schema::hasColumn('restaurant_orders', 'delivery_address')) {
                $table->string('delivery_address', 255)->nullable()->after('delivery_phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            if (Schema::hasColumn('restaurant_orders', 'delivery_address')) {
                $table->dropColumn('delivery_address');
            }
            if (Schema::hasColumn('restaurant_orders', 'delivery_phone')) {
                $table->dropColumn('delivery_phone');
            }
        });
    }
};

