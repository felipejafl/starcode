<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Devuelve las reglas para una nueva contraseña y su confirmación.
     *
     * Lo consumen las acciones de Fortify y los componentes Livewire de seguridad y administración;
     * Password::default() aplica la política definida por AppServiceProvider.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::default(), 'confirmed'];
    }

    /**
     * Devuelve las reglas para confirmar la contraseña vigente del usuario autenticado.
     *
     * Lo consumen los componentes Livewire que cambian contraseñas o eliminan cuentas.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function currentPasswordRules(): array
    {
        return ['required', 'string', 'current_password'];
    }
}
