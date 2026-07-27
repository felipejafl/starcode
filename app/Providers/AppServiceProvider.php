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
     * Inicializa las convenciones globales y los listeners de auditoría.
     *
     * Lo ejecuta el contenedor de Laravel al arrancar la aplicación; registra reglas que afectan
     * autorización, fechas, contraseñas y la captura de eventos de autenticación y permisos.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuditListeners();
    }

    /**
     * Configura comportamientos globales para los entornos de la aplicación.
     *
     * Define fechas inmutables, otorga acceso total al rol Super Administrador, bloquea comandos
     * destructivos en producción y endurece la política de contraseñas en ese entorno. Lo invoca boot().
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
     * Registra los listeners que convierten eventos de autenticación y permisos en auditorías.
     *
     * Lo invoca boot(); sus registros hacen que el dispatcher de Laravel ejecute los listeners
     * correspondientes cuando Fortify, el guard web o Spatie Permission emiten esos eventos.
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
