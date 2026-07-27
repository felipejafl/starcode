<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Configura las integraciones de Fortify al iniciar la aplicación.
     *
     * Lo ejecuta el contenedor de Laravel y registra la acción de restablecimiento, las vistas
     * de autenticación y los limitadores que consumen las rutas administradas por Fortify.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Asocia el restablecimiento de contraseña con la acción propia de la aplicación.
     *
     * Lo invoca boot(); Fortify resuelve ResetUserPassword al procesar solicitudes válidas a
     * su flujo de restablecimiento de contraseña.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    /**
     * Asocia las pantallas de autenticación con las vistas Livewire de la aplicación.
     *
     * Lo invoca boot(); Fortify utiliza estos callbacks al responder sus rutas de inicio de sesión,
     * verificación de correo, desafío 2FA, confirmación y restablecimiento de contraseña.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login'));
        Fortify::verifyEmailView(fn () => view('pages::auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('pages::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
    }

    /**
     * Define los limitadores para el inicio de sesión y el desafío de doble factor.
     *
     * Lo invoca boot(); Fortify consume estas claves para limitar a cinco intentos por minuto,
     * identificando el desafío por la sesión y el inicio por correo normalizado e IP.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
