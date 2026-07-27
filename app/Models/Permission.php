<?php

namespace App\Models;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use LogsActivity;

    /**
     * Define los atributos del permiso que Spatie Activitylog debe registrar.
     *
     * Lo consume el trait LogsActivity durante las altas, modificaciones y bajas de permisos,
     * excluyendo cambios que solo actualizan la marca de tiempo.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'guard_name'])
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }
}
