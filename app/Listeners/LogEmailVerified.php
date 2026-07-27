<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Verified;

class LogEmailVerified
{
    /**
     * Registra la verificación de correo del usuario indicado por Laravel.
     *
     * Lo invoca el dispatcher de Laravel cuando se emite Verified y crea una actividad de
     * autenticación con el contexto de la solicitud que confirmó la dirección de correo.
     */
    public function handle(Verified $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('email_verified');
    }
}
