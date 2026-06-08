<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SupplierPipeline;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierWorkBoardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pipelines = SupplierPipeline::query()
            ->withCount('cards')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $pipelineId = $request->integer('pipeline_id') ?: $pipelines->first()?->id;

        $pipeline = $pipelineId
            ? SupplierPipeline::query()
                ->with([
                    'stages' => function ($query) {
                        $query->orderBy('sort_order')
                            ->orderBy('id')
                            ->with([
                                'cards' => function ($cardQuery) {
                                    $cardQuery->with([
                                        'unit:id,name,is_customer,is_supplier',
                                        'unit.labels:id,name',
                                        'unit.cities:id,name',
                                    ])->orderBy('sort_order')->orderBy('id');
                                },
                            ]);
                    },
                    'cards' => function ($query) {
                        $query->whereNull('supplier_pipeline_stage_id')
                            ->with([
                                'unit:id,name,is_customer,is_supplier',
                                'unit.labels:id,name',
                                'unit.cities:id,name',
                            ])
                            ->orderBy('sort_order')
                            ->orderBy('id');
                    },
                ])
                ->find($pipelineId)
            : null;

        return response()->json([
            'pipelines' => $pipelines,
            'pipeline' => $pipeline,
        ]);
    }

    public function unitOptions(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'only_suppliers' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $units = Unit::query()
            ->select(['id', 'name', 'is_customer', 'is_supplier'])
            ->search($request->input('search'))
            ->when($request->boolean('only_suppliers'), fn ($query) => $query->where('is_supplier', true))
            ->orderBy('name')
            ->limit((int) ($request->input('limit') ?: 40))
            ->get();

        return response()->json($units);
    }
}
