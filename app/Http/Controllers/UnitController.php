<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Unit;
use Inertia\Inertia;

class UnitController extends Controller
{
    public function show($id){
        $unit = Unit::findOrFail($id);
        $data = [
            'unit' => $unit,
        ];
        return Inertia::render('Unit', $data);
    }
}
