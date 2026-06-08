<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SupplierPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierPipelineController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['sort_order'] = $data['sort_order'] ?? ((int) SupplierPipeline::max('sort_order') + 10);
        $data['is_active'] = $data['is_active'] ?? true;

        $pipeline = SupplierPipeline::create($data);

        return response()->json($pipeline, 201);
    }

    public function update(Request $request, SupplierPipeline $pipeline): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $pipeline->update($data);

        return response()->json($pipeline->fresh()->loadCount('cards'));
    }

    public function destroy(SupplierPipeline $pipeline): JsonResponse
    {
        $pipeline->delete();

        return response()->json(['message' => 'Pipeline deleted']);
    }
}
