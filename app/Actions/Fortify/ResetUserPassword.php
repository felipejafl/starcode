<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Valida y restablece la contraseña olvidada de un usuario.
     *
     * Fortify invoca este método tras validar el token de restablecimiento. Persiste la nueva
     * contraseña mediante el cast hash del modelo y registra una actividad de autenticación.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();

        activity('auth')
            ->causedBy($user)
            ->log('password_reset');
    }
}
