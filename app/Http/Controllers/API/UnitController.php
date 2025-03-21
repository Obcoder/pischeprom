<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $like = $request->search;
        $limit = $request->limit;
        $units = Unit::where('name', 'like', "%{$like}%")
            ->with('labels')
            ->with('consumptions')
            ->with('products')
            ->with('entities')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $units;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $unit = Unit::create($request->all());
        $unit->uris()->attach($request->input('uris'));
        $unit->labels()->attach($request->input('labels'));
        $unit->buildings()->attach($request->input('buildings'));

        // Создаем "папку" в S3 (на самом деле это просто пустой файл, так как в S3 нет папок)
        Storage::disk('yandex')->put("units/{$unit->name}/placeholder.txt", $unit);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
//        $data = [
//            'unit' => Unit::with('uris')
//                ->with('labels')
//                ->with('stages')
//                ->with('buildings')
//                ->with('consumptions.product')
//                ->with('consumptions.measure')
//                ->findOrFail($id),
//        ];
//        return Inertia::render('Unit', $data);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
