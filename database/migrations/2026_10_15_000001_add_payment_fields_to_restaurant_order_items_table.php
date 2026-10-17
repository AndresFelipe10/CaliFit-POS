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
        Schema::table('restaurant_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurant_order_items', 'payment_status')) {
                $table->string('payment_status', 20)->default('pending')->after('status');
                $table->timestamp('paid_at')->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('restaurant_order_items', 'payment_id')) {
                $table->foreignId('payment_id')->nullable()->after('paid_at')->constrained('sales')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('restaurant_order_items', 'payment_id')) {
                $table->dropForeign(['payment_id']);
                $table->dropColumn('payment_id');
            }
            if (Schema::hasColumn('restaurant_order_items', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
            if (Schema::hasColumn('restaurant_order_items', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
        });
    }
};
