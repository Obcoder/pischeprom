<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Email;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailRelationController extends Controller
{
    public function syncUnits(Request $request, Email $email): JsonResponse
    {
        $data = $request->validate([
                                       'unit_ids' => ['array'],
                                       'unit_ids.*' => ['integer', 'exists:units,id'],
                                   ]);

        $email->units()->sync($data['unit_ids'] ?? []);

        return response()->json(
            $email->fresh()->load(['units:id,name', 'entities:id,name'])
        );
    }

    public function syncEntities(Request $request, Email $email): JsonResponse
    {
        $data = $request->validate([
                                       'entity_ids' => ['array'],
                                       'entity_ids.*' => ['integer', 'exists:entities,id'],
                                   ]);

        $email->entities()->sync($data['entity_ids'] ?? []);

        return response()->json(
            $email->fresh()->load(['units:id,name', 'entities:id,name'])
        );
    }
}
