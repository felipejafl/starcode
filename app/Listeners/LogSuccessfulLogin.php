<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    /**
     * Registra un inicio de sesión autenticado con el contexto de la solicitud.
     *
     * Lo invoca el dispatcher de Laravel cuando el guard web emite Login; crea una actividad
     * de autenticación asociada al usuario que inició sesión.
     */
    public function handle(Login $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('login');
    }
}
