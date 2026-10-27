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
            $table->boolean('is_credit')->default(false)->after('payment_method');
            $table->enum('credit_status', ['none', 'pending', 'paid', 'cancelled'])->default('none')->after('is_credit');
            $table->date('credit_due_date')->nullable()->after('credit_status');

            $table->index(['is_credit', 'credit_status']);
            $table->index('customer_id');
        });

        Schema::create('customer_credit_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('method', ['cash', 'card', 'transfer', 'other'])->default('cash');
            $table->decimal('amount', 12, 2);
            $table->decimal('cash_received', 12, 2)->nullable();
            $table->decimal('change_given', 12, 2)->nullable();
            $table->string('reference', 60)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index('sale_id');
            $table->index('customer_id');
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_credit_payments');

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['is_credit', 'credit_status']);
            $table->dropIndex(['customer_id']);
            $table->dropColumn(['is_credit', 'credit_status', 'credit_due_date']);
        });
    }
};
