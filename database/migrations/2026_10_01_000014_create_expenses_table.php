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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('invoice_number', 50)->nullable();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->enum('category', ['merchandise', 'utilities', 'rent', 'supplies', 'payroll', 'other']);
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['paid', 'pending'])->default('pending');
            $table->date('paid_at')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('category');
            $table->index('status');
            $table->index('issue_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
