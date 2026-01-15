<?php

namespace App\Http\Controllers\Web\Config;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ConfigMenuController extends Controller
{
    public function index(Request $request)
    {
        return view('web.config.config-menu');
    }
}
