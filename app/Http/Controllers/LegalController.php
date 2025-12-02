<?php

namespace App\Http\Controllers;

class LegalController extends Controller
{
    /**
     * Tampilkan halaman Kebijakan Privasi.
     */
    public function privacyPolicy()
    {
        return view('privacy-policy');
    }
}

