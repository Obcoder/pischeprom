<?php

namespace App\Http\Controllers;

use App\Models\Check;
use Illuminate\Http\Request;

class CheckController extends Controller
{
    public function show(string $id){
        $data = [
            'check' => Check::findOrFail($id),
        ];

        return Inertia::render('Checks', $data);
    }
}
