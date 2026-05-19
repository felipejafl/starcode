<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the application's roles and permissions.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect([
            'admin.acceder',
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.actualizar',
            'usuarios.eliminar',
            'roles.ver',
            'roles.crear',
            'roles.actualizar',
            'roles.eliminar',
            'permisos.ver',
            'permisos.crear',
            'permisos.actualizar',
            'permisos.eliminar',
            'auditoria.ver',
        ])->map(fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'));

        Role::findOrCreate('Super Administrador', 'web')->syncPermissions($permissions);
    }
}
