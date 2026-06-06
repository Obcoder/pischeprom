<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PhoneCallResource;
use App\Models\PhoneCall;
use App\Services\Telephony\BeelinePbxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PhoneCallController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max((int) $request->integer('per_page', 25), 1);

        $query = PhoneCall::query()
            ->with(['telephone', 'entity', 'unit', 'lead'])
            ->search($request->input('search'))
            ->when($request->filled('direction'), fn ($q) => $q->where('direction', $request->input('direction')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('entity_id'), fn ($q) => $q->where('entity_id', $request->integer('entity_id')))
            ->when($request->filled('lead_id'), fn ($q) => $q->where('lead_id', $request->integer('lead_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->where('started_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->where('started_at', '<=', $request->date('date_to')))
            ->orderByDesc('started_at')
            ->orderByDesc('id');

        $calls = $query->paginate($perPage);

        return response()->json([
            'data' => PhoneCallResource::collection($calls->getCollection()),
            'current_page' => $calls->currentPage(),
            'last_page' => $calls->lastPage(),
            'per_page' => $calls->perPage(),
            'total' => $calls->total(),
        ]);
    }

    public function show(PhoneCall $phoneCall): PhoneCallResource
    {
        return new PhoneCallResource($phoneCall->load(['telephone', 'entity', 'unit', 'lead']));
    }

    public function update(Request $request, PhoneCall $phoneCall): PhoneCallResource
    {
        $data = $request->validate([
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $phoneCall->update($data);

        return new PhoneCallResource($phoneCall->load(['telephone', 'entity', 'unit', 'lead']));
    }

    public function createEntity(Request $request, PhoneCall $phoneCall, BeelinePbxService $service): PhoneCallResource
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $service->createEntityForCall($phoneCall->load('lead'), $data['name'] ?? null);

        return new PhoneCallResource($phoneCall->refresh()->load(['telephone', 'entity', 'unit', 'lead']));
    }
}
