<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Verified;

class LogEmailVerified
{
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
