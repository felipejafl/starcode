<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Crea los permisos base y los asigna al rol administrativo principal.
     *
     * Lo invoca DatabaseSeeder; limpia la caché de Spatie Permission antes de sincronizar las
     * capacidades para que la autorización refleje inmediatamente los datos sembrados.
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
