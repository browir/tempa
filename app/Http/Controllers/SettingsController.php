<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class SettingsController extends Controller
{
    public function edit()
    {
        return Inertia::render('settings/Index');
    }
}
