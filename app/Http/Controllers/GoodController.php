<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGoodRequest;
use App\Http\Requests\UpdateGoodRequest;
use App\Models\Good;
use Inertia\Inertia;

class GoodController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $goods = Good::orderBy('name')
            ->get();

        $data = [
            'goods' => $goods,
        ];

        return Inertia::render('Goods', $data);
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
    public function store(StoreGoodRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Good $Good)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Good $Good)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGoodRequest $request, Good $Good)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Good $Good)
    {
        //
    }
}
