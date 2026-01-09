<?php

namespace App\Http\Controllers;

class LegalController extends Controller
{
    // GET /privacy-policy - Tampilkan halaman Kebijakan Privasi
    public function privacyPolicy()
    {
        return view('privacy-policy');
    }
}

