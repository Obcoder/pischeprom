<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SupplierPipeline;
use App\Models\SupplierPipelineStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupplierPipelineStageController extends Controller
{
    public function store(Request $request, SupplierPipeline $pipeline): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['supplier_pipeline_id'] = $pipeline->id;
        $data['sort_order'] = $data['sort_order'] ?? ((int) $pipeline->stages()->max('sort_order') + 10);

        $stage = SupplierPipelineStage::create($data);

        return response()->json($stage, 201);
    }

    public function update(Request $request, SupplierPipelineStage $stage): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $stage->update($data);

        return response()->json($stage->fresh());
    }

    public function destroy(SupplierPipelineStage $stage): JsonResponse
    {
        DB::transaction(function () use ($stage) {
            $replacementStageId = SupplierPipelineStage::query()
                ->where('supplier_pipeline_id', $stage->supplier_pipeline_id)
                ->whereKeyNot($stage->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->value('id');

            $stage->cards()->update([
                'supplier_pipeline_stage_id' => $replacementStageId,
            ]);

            $stage->delete();
        });

        return response()->json(['message' => 'Stage deleted']);
    }

    public function reorder(Request $request, SupplierPipeline $pipeline): JsonResponse
    {
        $data = $request->validate([
            'stage_ids' => ['required', 'array', 'min:1'],
            'stage_ids.*' => [
                'integer',
                Rule::exists('supplier_pipeline_stages', 'id')
                    ->where('supplier_pipeline_id', $pipeline->id),
            ],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['stage_ids'] as $index => $stageId) {
                SupplierPipelineStage::whereKey($stageId)->update([
                    'sort_order' => ($index + 1) * 10,
                ]);
            }
        });

        return response()->json(['message' => 'Stages reordered']);
    }
}
