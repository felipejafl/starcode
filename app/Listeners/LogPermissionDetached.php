<?php

namespace App\Listeners;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\PermissionDetachedEvent;

class LogPermissionDetached
{
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
     * Resolve permission IDs or permission objects to a collection.
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
