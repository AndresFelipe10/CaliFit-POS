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
        if (Schema::hasTable('restaurant_order_items') && ! Schema::hasColumn('restaurant_order_items', 'paid_quantity')) {
            Schema::table('restaurant_order_items', function (Blueprint $table) {
                $table->decimal('paid_quantity', 12, 3)->default(0.000)->after('is_paid');
            });

            DB::table('restaurant_order_items')
                ->where('is_paid', true)
                ->update(['paid_quantity' => DB::raw('quantity')]);
        }

        if (Schema::hasTable('restaurant_order_item_additions') && ! Schema::hasColumn('restaurant_order_item_additions', 'paid_quantity')) {
            Schema::table('restaurant_order_item_additions', function (Blueprint $table) {
                $table->decimal('paid_quantity', 12, 3)->default(0.000)->after('quantity');
            });

            if (Schema::hasTable('restaurant_order_items')) {
                $paidItemIds = DB::table('restaurant_order_items')->where('is_paid', true)->pluck('id');
                if ($paidItemIds->isNotEmpty()) {
                    DB::table('restaurant_order_item_additions')
                        ->whereIn('order_item_id', $paidItemIds)
                        ->update(['paid_quantity' => DB::raw('quantity')]);
                }
            }
        }

        if (Schema::hasTable('restaurant_order_splits')) {
            Schema::table('restaurant_order_splits', function (Blueprint $table) {
                if (! Schema::hasColumn('restaurant_order_splits', 'is_credit')) {
                    $table->boolean('is_credit')->default(false)->after('status');
                }
                if (! Schema::hasColumn('restaurant_order_splits', 'customer_id')) {
                    $table->foreignId('customer_id')->nullable()->after('user_id')->constrained('customers')->nullOnDelete();
                }
                if (! Schema::hasColumn('restaurant_order_splits', 'items_summary')) {
                    $table->json('items_summary')->nullable()->after('reference');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('restaurant_order_splits')) {
            Schema::table('restaurant_order_splits', function (Blueprint $table) {
                if (Schema::hasColumn('restaurant_order_splits', 'customer_id')) {
                    $table->dropForeign(['customer_id']);
                    $table->dropColumn('customer_id');
                }
                if (Schema::hasColumn('restaurant_order_splits', 'is_credit')) {
                    $table->dropColumn('is_credit');
                }
                if (Schema::hasColumn('restaurant_order_splits', 'items_summary')) {
                    $table->dropColumn('items_summary');
                }
            });
        }

        if (Schema::hasTable('restaurant_order_item_additions') && Schema::hasColumn('restaurant_order_item_additions', 'paid_quantity')) {
            Schema::table('restaurant_order_item_additions', function (Blueprint $table) {
                $table->dropColumn('paid_quantity');
            });
        }

        if (Schema::hasTable('restaurant_order_items') && Schema::hasColumn('restaurant_order_items', 'paid_quantity')) {
            Schema::table('restaurant_order_items', function (Blueprint $table) {
                $table->dropColumn('paid_quantity');
            });
        }
    }
};
