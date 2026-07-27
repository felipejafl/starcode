<?php

namespace App\Listeners;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\PermissionDetachedEvent;

class LogPermissionDetached
{
    /**
     * Registra la revocación de uno o varios permisos de un modelo autorizable.
     *
     * Lo invoca el dispatcher de Laravel cuando Spatie Permission emite PermissionDetachedEvent;
     * conserva un actor nulo si la revocación ocurre fuera de una solicitud autenticada.
     */
    public function handle(PermissionDetachedEvent $event): void
    {
        $permissions = $this->resolvePermissions($event->permissionsOrIds);
        $permissionNames = $permissions->pluck('name')->join(', ');

        activity()
            ->causedBy($this->resolveCauser())
            ->performedOn($event->model)
            ->log("removed permission {$permissionNames}");
    }

    /**
     * Resuelve el actor de la actividad a partir del usuario autenticado.
     *
     * Lo invoca handle(); devuelve null para comandos, seeders o flujos de eventos sin un usuario
     * autenticado, de modo que la actividad quede identificada como ejecutada por el sistema.
     */
    protected function resolveCauser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Convierte los permisos o identificadores del evento en modelos de permiso.
     *
     * Lo invoca handle(); admite un modelo, una colección o un arreglo para cubrir las formas que
     * Spatie Permission puede publicar en PermissionDetachedEvent.
     *
     * @return Collection<int, Permission>
     */
    protected function resolvePermissions(mixed $permissionsOrIds): Collection
    {
        if ($permissionsOrIds instanceof Permission) {
            return collect([$permissionsOrIds]);
        }

        if ($permissionsOrIds instanceof Collection) {
            return $permissionsOrIds->map(fn ($item) => $item instanceof Permission ? $item : Permission::find($item));
        }

        if (is_array($permissionsOrIds)) {
            return collect($permissionsOrIds)->map(fn ($item) => $item instanceof Permission ? $item : Permission::find($item));
        }

        return collect();
    }
}
