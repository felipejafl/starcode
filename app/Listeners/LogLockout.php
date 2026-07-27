<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Lockout;

class LogLockout
{
    /**
     * Registra el bloqueo provocado por demasiados intentos de autenticación.
     *
     * Lo invoca el dispatcher de Laravel ante el evento Lockout; no asocia actor porque el bloqueo
     * puede corresponder a una identidad no autenticada.
     */
    public function handle(Lockout $event): void
    {
        activity('auth')
            ->withProperties([
                'ip' => $event->request->ip(),
                'user_agent' => $event->request->userAgent(),
            ])
            ->log('lockout');
    }
}
