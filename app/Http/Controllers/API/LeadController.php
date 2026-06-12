<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max((int) $request->integer('per_page', 25), 1);

        $query = Lead::query()
            ->with($this->leadRelations())
            ->withCount('phoneCalls')
            ->search($request->input('search'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->input('source')))
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id');

        $leads = $query->paginate($perPage);

        return response()->json([
            'data' => LeadResource::collection($leads->getCollection()),
            'current_page' => $leads->currentPage(),
            'last_page' => $leads->lastPage(),
            'per_page' => $leads->perPage(),
            'total' => $leads->total(),
        ]);
    }

    public function show(Lead $lead): LeadResource
    {
        return new LeadResource($this->loadLeadDetails($lead));
    }

    public function update(Request $request, Lead $lead): LeadResource
    {
        $data = $request->validate([
            'status' => [
                'sometimes',
                'string',
                Rule::in([
                    Lead::STATUS_OPEN,
                    Lead::STATUS_IN_PROGRESS,
                    Lead::STATUS_WON,
                    Lead::STATUS_LOST,
                    Lead::STATUS_ARCHIVED,
                ]),
            ],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
        ]);

        if (isset($data['status']) && in_array($data['status'], [Lead::STATUS_WON, Lead::STATUS_LOST, Lead::STATUS_ARCHIVED], true)) {
            $data['closed_at'] = now();
        }

        if (isset($data['status']) && in_array($data['status'], [Lead::STATUS_OPEN, Lead::STATUS_IN_PROGRESS], true)) {
            $data['closed_at'] = null;
        }

        $lead->update($data);

        return new LeadResource($this->loadLeadDetails($lead));
    }

    protected function loadLeadDetails(Lead $lead): Lead
    {
        return $lead
            ->load([
                'telephone',
                'entity.units',
                'entity.buildings.city.region',
                'unit',
                'assignedUser',
                'firstPhoneCall',
                'lastPhoneCall',
                'phoneCalls' => fn ($query) => $query
                    ->with([
                        'telephone',
                        'entity.units',
                        'entity.buildings.city.region',
                        'unit',
                    ])
                    ->orderByDesc('started_at')
                    ->orderByDesc('id'),
            ])
            ->loadCount('phoneCalls');
    }

    protected function leadRelations(): array
    {
        return [
            'telephone',
            'entity.units',
            'entity.buildings.city.region',
            'unit',
        ];
    }
}
