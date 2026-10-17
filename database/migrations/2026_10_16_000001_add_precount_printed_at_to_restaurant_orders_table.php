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
            if (! Schema::hasColumn('restaurant_orders', 'precount_printed_at')) {
                $table->timestamp('precount_printed_at')->nullable()->after('closed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            if (Schema::hasColumn('restaurant_orders', 'precount_printed_at')) {
                $table->dropColumn('precount_printed_at');
            }
        });
    }
};
