<?php

namespace App\Listeners;

use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;

class LogTwoFactorEnabled
{
    /**
     * Registra la activación de doble factor del usuario indicado por Fortify.
     *
     * Lo invoca el dispatcher de Laravel cuando Fortify emite TwoFactorAuthenticationEnabled y
     * crea una actividad de autenticación con la IP y el agente de usuario de la solicitud.
     */
    public function handle(TwoFactorAuthenticationEnabled $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('two_factor_enabled');
    }
}
