<?php

namespace App\Http\Controllers;

use App\Models\Check;
use Illuminate\Http\Request;

class CheckController extends Controller
{
    public function show($id){
        $check = Check::with('entity')
            ->find($id);

        return Inertia::render('Checks', $check->id);
    }
}
