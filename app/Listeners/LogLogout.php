<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;

class LogLogout
{
    /**
     * Registra el cierre de sesión junto con el contexto de la solicitud.
     *
     * Lo invoca el dispatcher de Laravel cuando el guard web emite Logout; el usuario del evento
     * puede ser nulo en flujos que cierran una sesión sin un actor autenticado.
     */
    public function handle(Logout $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('logout');
    }
}
