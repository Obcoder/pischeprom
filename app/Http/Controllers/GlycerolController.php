<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class glycerolController extends Controller
{
    public function index(){
        $data = [
            'title' => 'Глицерины (пищевое производство, косметика, промышленность)'
        ];

        return Inertia::render('Glycerol', $data);
    }
}