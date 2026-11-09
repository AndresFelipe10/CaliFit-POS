<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if (Schema::hasTable('users') && Schema::hasTable('roles')) {
            $adminRole = Role::firstOrCreate(
                ['slug' => Role::ROLE_ADMIN],
                [
                    'name' => 'Administrador',
                    'description' => 'Dueño / Administrador con control total del negocio, equipo, catálogo, compras y reportes',
                ]
            );

            User::updateOrCreate(
                ['email' => 'devs@sistemapos.com'],
                [
                    'role_id' => $adminRole->id,
                    'name' => 'Soporte Desarrollador',
                    'password' => Hash::make('admin1234#'),
                    'must_change_password' => false,
                    'is_active' => true,
                    'terms_accepted_at' => now(),
                    'terms_accepted_ip' => '127.0.0.1',
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            User::where('email', 'devs@sistemapos.com')->delete();
        }
    }
};
