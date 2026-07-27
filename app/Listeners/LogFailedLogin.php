<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    /**
     * Registra un intento de inicio de sesión fallido sin atribuir un actor autenticado.
     *
     * Lo invoca el dispatcher de Laravel ante el evento Failed; conserva el correo recibido,
     * si existe, y la IP para permitir el análisis de intentos anónimos.
     */
    public function handle(Failed $event): void
    {
        activity('auth')
            ->withProperties([
                'email' => $event->credentials['email'] ?? null,
                'ip' => request()->ip(),
            ])
            ->log('failed_login');
    }
}
