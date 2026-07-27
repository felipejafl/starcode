<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Inicializa los permisos y crea la cuenta administrativa de desarrollo.
     *
     * Lo invoca Laravel mediante db:seed; delega los permisos al seeder especializado y asigna
     * el rol Super Administrador al usuario existente o recién creado.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@test.local'],
            [
                'name' => 'Administrador',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        $admin->assignRole('Super Administrador');
    }
}
