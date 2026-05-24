<?php

namespace App\Listeners;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\RoleDetachedEvent;

class LogRoleDetached
{
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
     * Resolve the causer for the activity log.
     *
     * Falls back to null when no authenticated user exists
     * (e.g., console commands, seeders, or event-driven flows).
     */
    protected function resolveCauser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Resolve role IDs or role objects to a collection of Role models.
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
