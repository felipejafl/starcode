<?php

namespace App\Listeners;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\RoleDetachedEvent;

class LogRoleDetached
{
    /**
     * Registra la revocación de uno o varios roles de un modelo autorizable.
     *
     * Lo invoca el dispatcher de Laravel cuando Spatie Permission emite RoleDetachedEvent;
     * conserva un actor nulo si la revocación ocurre fuera de una solicitud autenticada.
     */
    public function handle(RoleDetachedEvent $event): void
    {
        $roles = $this->resolveRoles($event->rolesOrIds);
        $roleNames = $roles->pluck('name')->join(', ');

        activity()
            ->causedBy($this->resolveCauser())
            ->performedOn($event->model)
            ->log("removed role {$roleNames}");
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
     * Convierte los roles o identificadores del evento en modelos de rol.
     *
     * Lo invoca handle(); admite un modelo, una colección o un arreglo para cubrir las formas que
     * Spatie Permission puede publicar en RoleDetachedEvent.
     *
     * @return Collection<int, Role>
     */
    protected function resolveRoles(mixed $rolesOrIds): Collection
    {
        if ($rolesOrIds instanceof Role) {
            return collect([$rolesOrIds]);
        }

        if ($rolesOrIds instanceof Collection) {
            return $rolesOrIds->map(fn ($item) => $item instanceof Role ? $item : Role::find($item));
        }

        if (is_array($rolesOrIds)) {
            return collect($rolesOrIds)->map(fn ($item) => $item instanceof Role ? $item : Role::find($item));
        }

        return collect();
    }
}
