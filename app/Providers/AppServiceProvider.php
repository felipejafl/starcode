<?php

namespace App\Providers;

use App\Listeners\LogEmailVerified;
use App\Listeners\LogFailedLogin;
use App\Listeners\LogLockout;
use App\Listeners\LogLogout;
use App\Listeners\LogPermissionAttached;
use App\Listeners\LogPermissionDetached;
use App\Listeners\LogRoleAttached;
use App\Listeners\LogRoleDetached;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogTwoFactorDisabled;
use App\Listeners\LogTwoFactorEnabled;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuditListeners();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Gate::before(function (User $user): ?bool {
            return $user->hasRole('Super Administrador') ? true : null;
        });

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Register event listeners for audit logging.
     */
    protected function configureAuditListeners(): void
    {
        // Auth events
        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Failed::class, LogFailedLogin::class);
        Event::listen(Logout::class, LogLogout::class);
        Event::listen(Lockout::class, LogLockout::class);
        Event::listen(TwoFactorAuthenticationEnabled::class, LogTwoFactorEnabled::class);
        Event::listen(TwoFactorAuthenticationDisabled::class, LogTwoFactorDisabled::class);
        Event::listen(Verified::class, LogEmailVerified::class);

        // Permission events
        Event::listen(RoleAttachedEvent::class, LogRoleAttached::class);
        Event::listen(RoleDetachedEvent::class, LogRoleDetached::class);
        Event::listen(PermissionAttachedEvent::class, LogPermissionAttached::class);
        Event::listen(PermissionDetachedEvent::class, LogPermissionDetached::class);
    }
}
