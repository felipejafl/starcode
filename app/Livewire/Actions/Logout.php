<?php

namespace App\Livewire\Actions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class Logout
{
    /**
     * Cierra la sesión web actual y redirige a la página inicial.
     *
     * Lo consume el flujo de eliminación de cuenta; invalida la sesión y regenera el token CSRF,
     * lo que además dispara el evento Logout para la auditoría.
     *
     * @return RedirectResponse
     */
    public function __invoke()
    {
        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();

        return redirect('/');
    }
}
