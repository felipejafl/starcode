<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Lockout;

class LogLockout
{
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
