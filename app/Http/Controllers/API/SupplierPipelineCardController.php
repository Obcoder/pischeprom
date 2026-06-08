<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SupplierPipeline;
use App\Models\SupplierPipelineCard;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupplierPipelineCardController extends Controller
{
    public function store(Request $request, SupplierPipeline $pipeline): JsonResponse
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'supplier_pipeline_stage_id' => [
                'nullable',
                'integer',
                Rule::exists('supplier_pipeline_stages', 'id')
                    ->where('supplier_pipeline_id', $pipeline->id),
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'next_contact_at' => ['nullable', 'date'],
        ]);

        $existingCard = SupplierPipelineCard::query()
            ->where('supplier_pipeline_id', $pipeline->id)
            ->where('unit_id', $data['unit_id'])
            ->first();

        if ($existingCard) {
            return response()->json([
                'message' => 'Этот Unit уже есть в выбранной воронке.',
                'card' => $existingCard->load($this->cardRelations()),
            ], 422);
        }

        $stageId = $data['supplier_pipeline_stage_id'] ?? $pipeline->stages()->orderBy('sort_order')->value('id');
        $sortOrder = SupplierPipelineCard::query()
            ->where('supplier_pipeline_id', $pipeline->id)
            ->where('supplier_pipeline_stage_id', $stageId)
            ->max('sort_order');

        $card = DB::transaction(function () use ($data, $pipeline, $stageId, $sortOrder) {
            Unit::whereKey($data['unit_id'])->update(['is_supplier' => true]);

            return SupplierPipelineCard::create([
                'supplier_pipeline_id' => $pipeline->id,
                'supplier_pipeline_stage_id' => $stageId,
                'unit_id' => $data['unit_id'],
                'title' => $data['title'] ?? null,
                'notes' => $data['notes'] ?? null,
                'next_contact_at' => $data['next_contact_at'] ?? null,
                'sort_order' => ((int) $sortOrder) + 10,
            ]);
        });

        return response()->json($card->load($this->cardRelations()), 201);
    }

    public function update(Request $request, SupplierPipelineCard $card): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'next_contact_at' => ['nullable', 'date'],
            'supplier_pipeline_stage_id' => [
                'nullable',
                'integer',
                Rule::exists('supplier_pipeline_stages', 'id')
                    ->where('supplier_pipeline_id', $card->supplier_pipeline_id),
            ],
        ]);

        $card->update($data);

        return response()->json($card->fresh()->load($this->cardRelations()));
    }

    public function move(Request $request, SupplierPipelineCard $card): JsonResponse
    {
        $data = $request->validate([
            'supplier_pipeline_stage_id' => [
                'nullable',
                'integer',
                Rule::exists('supplier_pipeline_stages', 'id')
                    ->where('supplier_pipeline_id', $card->supplier_pipeline_id),
            ],
            'ordered_card_ids' => ['required', 'array'],
            'ordered_card_ids.*' => [
                'integer',
                Rule::exists('supplier_pipeline_cards', 'id')
                    ->where('supplier_pipeline_id', $card->supplier_pipeline_id),
            ],
        ]);

        DB::transaction(function () use ($card, $data) {
            $card->update([
                'supplier_pipeline_stage_id' => $data['supplier_pipeline_stage_id'] ?? null,
            ]);

            foreach ($data['ordered_card_ids'] as $index => $cardId) {
                SupplierPipelineCard::whereKey($cardId)->update([
                    'supplier_pipeline_stage_id' => $data['supplier_pipeline_stage_id'] ?? null,
                    'sort_order' => ($index + 1) * 10,
                ]);
            }
        });

        return response()->json($card->fresh()->load($this->cardRelations()));
    }

    public function destroy(SupplierPipelineCard $card): JsonResponse
    {
        $card->delete();

        return response()->json(['message' => 'Card deleted']);
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
}
