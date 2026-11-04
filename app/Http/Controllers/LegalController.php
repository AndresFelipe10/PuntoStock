<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class LegalController extends Controller
{
    /**
     * Redirección 301 permanente a los Términos y Condiciones oficiales en puntostockpos.com.
     */
    public function terms(): RedirectResponse
    {
        return redirect()->away(
            config('app.legal_terms_url', 'https://puntostockpos.com/terminos-y-condiciones/'),
            301
        );
    }

    /**
     * Redirección 301 permanente a la Política de Privacidad oficial en puntostockpos.com.
     */
    public function privacy(): RedirectResponse
    {
        return redirect()->away(
            config('app.legal_privacy_url', 'https://puntostockpos.com/politica-de-privacidad/'),
            301
        );
    }
}
