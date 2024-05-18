<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class GlycerolController extends Controller
{
    public function index(){
        $data = [
            'title' => 'Глицерины (пищевое производство, косметика, промышленность)',
        ];

        return Inertia::render('Glycerol', $data);
    }
}
