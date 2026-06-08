<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SupplierPipeline;
use App\Models\Unit;
use App\Services\Mail\UnansweredOutgoingMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierWorkBoardController extends Controller
{
    public function index(Request $request, UnansweredOutgoingMailService $mailService): JsonResponse
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
                                    $cardQuery->with($this->cardRelations())
                                        ->orderBy('sort_order')
                                        ->orderBy('id');
                                },
                            ]);
                    },
                    'cards' => function ($query) {
                        $query->whereNull('supplier_pipeline_stage_id')
                            ->with($this->cardRelations())
                            ->orderBy('sort_order')
                            ->orderBy('id');
                    },
                ])
                ->find($pipelineId)
            : null;

        $this->attachMailFollowUps($pipeline, $mailService);

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

    private function cardRelations(): array
    {
        return [
            'unit' => function ($query) {
                $query
                    ->select(['id', 'name', 'is_customer', 'is_supplier'])
                    ->without(['fields', 'labels', 'telephones', 'uris'])
                    ->with([
                        'entities' => function ($entityQuery) {
                            $entityQuery
                                ->select(['entities.id', 'entities.name'])
                                ->without(['buildings', 'classification', 'country'])
                                ->with([
                                    'telephones:id,number',
                                    'emails:id,address,name',
                                ])
                                ->orderBy('entities.name');
                        },
                    ]);
            },
        ];
    }

    private function attachMailFollowUps(?SupplierPipeline $pipeline, UnansweredOutgoingMailService $mailService): void
    {
        if (!$pipeline) {
            return;
        }

        $cards = collect($pipeline->stages ?? [])
            ->flatMap(fn ($stage) => $stage->cards ?? [])
            ->merge($pipeline->cards ?? []);

        $unitIds = $cards
            ->pluck('unit_id')
            ->filter()
            ->unique()
            ->values();

        $followUps = $mailService->summarizeForUnits($unitIds);

        $cards->each(function ($card) use ($followUps) {
            if ($card->unit) {
                $card->unit->setAttribute('mail_follow_up', $followUps[$card->unit_id] ?? null);
            }
        });
    }
}
