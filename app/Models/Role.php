<?php

namespace App\Models;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use LogsActivity;

    /**
     * Define los atributos del rol que Spatie Activitylog debe registrar.
     *
     * Lo consume el trait LogsActivity durante las altas, modificaciones y bajas de roles,
     * excluyendo cambios que solo actualizan la marca de tiempo.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'guard_name'])
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }
}
