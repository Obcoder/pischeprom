<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class LegalPageController extends Controller
{
    public function privacy(): Response
    {
        return Inertia::render('Legal/Privacy');
    }

    public function terms(): Response
    {
        return Inertia::render('Legal/Terms');
    }

    public function personalDataConsent(): Response
    {
        return Inertia::render('Legal/PersonalDataConsent');
    }
}
