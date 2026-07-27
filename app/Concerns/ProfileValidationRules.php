<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Compone las reglas para crear o actualizar el perfil de un usuario.
     *
     * Lo consumen los componentes Livewire de perfil y administración; cuando recibe un ID,
     * permite conservar el correo electrónico del usuario que se está editando.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Devuelve las reglas reutilizables para el nombre de un usuario.
     *
     * Lo invoca profileRules() para las validaciones de perfil y de administración.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Devuelve las reglas reutilizables para un correo electrónico único.
     *
     * Lo invoca profileRules(); el ID opcional excluye al usuario editado de la restricción de unicidad.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
