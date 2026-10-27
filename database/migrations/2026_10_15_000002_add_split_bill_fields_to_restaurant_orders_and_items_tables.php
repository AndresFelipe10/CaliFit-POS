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
            $table->enum('split_type', ['none', 'equal', 'items'])->default('none')->after('order_type');
            $table->unsignedTinyInteger('total_shares')->nullable()->after('split_type');
            $table->unsignedTinyInteger('paid_shares')->default(0)->after('total_shares');
        });

        Schema::table('restaurant_order_items', function (Blueprint $table) {
            $table->boolean('is_paid')->default(false)->after('printed_to_kitchen');
            $table->foreignId('sale_id')->nullable()->after('is_paid')->constrained('sales')->nullOnDelete();

            $table->index('is_paid');
        });

        Schema::create('restaurant_order_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_order_id')->constrained('restaurant_orders')->cascadeOnDelete();
            $table->unsignedTinyInteger('share_number');
            $table->unsignedTinyInteger('total_shares');
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->string('payment_method', 20)->nullable();
            $table->decimal('cash_received', 12, 2)->nullable();
            $table->decimal('change_given', 12, 2)->nullable();
            $table->string('reference', 60)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_order_splits');

        Schema::table('restaurant_order_items', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropIndex(['is_paid']);
            $table->dropColumn(['is_paid', 'sale_id']);
        });

        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->dropColumn(['split_type', 'total_shares', 'paid_shares']);
        });
    }
};
