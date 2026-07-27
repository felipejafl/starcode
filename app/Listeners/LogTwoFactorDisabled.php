<?php

namespace App\Listeners;

use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

class LogTwoFactorDisabled
{
    /**
     * Registra la desactivación de doble factor del usuario indicado por Fortify.
     *
     * Lo invoca el dispatcher de Laravel cuando Fortify emite TwoFactorAuthenticationDisabled y
     * crea una actividad de autenticación con la IP y el agente de usuario de la solicitud.
     */
    public function handle(TwoFactorAuthenticationDisabled $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('two_factor_disabled');
    }
}
