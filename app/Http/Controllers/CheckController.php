<?php

namespace App\Http\Controllers;

use App\Models\Check;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckController extends Controller
{
    public function show(string $id){
        $data = [
            'check' => Check::with('entity')
                ->with('commodities')
                ->findOrFail($id),
        ];

        return Inertia::render('Check', $data);
    }
}
