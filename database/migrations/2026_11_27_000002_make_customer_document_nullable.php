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
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'document')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('document', 30)->nullable()->change();
            });
        }

        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'customer_document')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->string('customer_document', 30)->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'customer_document')) {
            DB::table('sales')->whereNull('customer_document')->update([
                'customer_document' => '222222222222',
            ]);
            Schema::table('sales', function (Blueprint $table) {
                $table->string('customer_document', 30)->nullable(false)->change();
            });
        }

        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'document')) {
            DB::table('customers')->whereNull('document')->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('customers')->where('id', $row->id)->update([
                        'document' => sprintf('000%07d', $row->id),
                    ]);
                }
            });
            Schema::table('customers', function (Blueprint $table) {
                $table->string('document', 30)->nullable(false)->change();
            });
        }
    }
};
