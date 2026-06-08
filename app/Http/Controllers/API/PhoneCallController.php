<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PhoneCallResource;
use App\Models\Lead;
use App\Models\PhoneCall;
use App\Models\Telephone;
use App\Services\Telephony\BeelinePbxService;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PhoneCallController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source' => ['nullable', 'string', 'max:64'],
            'client_phone' => ['nullable', 'string', 'max:32'],
            'target_phone' => ['nullable', 'string', 'max:32'],
            'good_id' => ['nullable', 'integer'],
            'good_name' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'category_name' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
        ]);

        $call = DB::transaction(function () use ($data, $request): PhoneCall {
            $clientPhone = $this->normalizePhone($data['client_phone'] ?? null);
            $targetPhone = $this->normalizePhone($data['target_phone'] ?? null) ?: '79650160001';
            $telephone = $clientPhone
                ? Telephone::query()->firstOrCreate(['number' => $clientPhone])
                : null;
            $source = $data['source'] ?? 'website';
            $startedAt = now();
            $description = $this->leadDescription($data, $targetPhone);

            $lead = Lead::query()->create([
                'source' => $source,
                'status' => Lead::STATUS_OPEN,
                'title' => $this->leadTitle($data),
                'description' => $description,
                'client_phone' => $clientPhone,
                'telephone_id' => $telephone?->id,
                'last_activity_at' => $startedAt,
            ]);

            $call = PhoneCall::query()->create([
                'provider' => 'website',
                'provider_call_id' => (string) Str::uuid(),
                'event_type' => 'phone_click',
                'direction' => PhoneCall::DIRECTION_IN,
                'status' => 'clicked',
                'client_phone' => $clientPhone,
                'employee_phone' => $targetPhone,
                'started_at' => $startedAt,
                'telephone_id' => $telephone?->id,
                'lead_id' => $lead->id,
                'raw_payload' => [
                    ...$data,
                    'ip' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                ],
            ]);

            $lead->forceFill([
                'first_phone_call_id' => $call->id,
                'last_phone_call_id' => $call->id,
            ])->save();

            return $call;
        });

        return (new PhoneCallResource($call->load(['telephone', 'entity', 'unit', 'lead'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

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
            ->when($request->boolean('hide_unresolved', true), function ($q) {
                $q->where(function ($sq) {
                    $sq->whereNot(function ($hidden) {
                        $hidden->where('provider', 'beeline')
                            ->whereNull('client_phone')
                            ->where(function ($unresolved) {
                                $unresolved
                                    ->whereNull('provider_call_id')
                                    ->orWhere('provider_call_id', 'not like', 'portal:%');
                            });
                    });
                });
            })
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

    public function syncBeeline(Request $request, BeelinePbxService $service): JsonResponse
    {
        $data = $request->validate([
            'period' => ['nullable', 'string', 'in:today,yesterday,this_week,last_week,this_month,last_month'],
            'limit' => ['nullable', 'integer', 'min:10', 'max:500'],
        ]);

        $result = $service->syncHistory([
            'period' => $data['period'] ?? 'today',
            'limit' => $data['limit'] ?? 100,
        ]);

        $cleanup = $service->cleanupLikelyServiceClients();

        return response()->json([
            ...$result,
            'cleanup' => $cleanup,
        ]);
    }

    public function dial(Request $request, BeelinePbxService $service): JsonResponse
    {
        $data = $request->validate([
            'client_phone' => ['required', 'string', 'max:32'],
            'employee_phone' => ['nullable', 'string', 'max:32'],
        ]);

        $clientPhone = $this->normalizePhone($data['client_phone']);
        $employeePhone = $this->normalizePhone($data['employee_phone'] ?? null) ?: '79650160001';

        if (!$clientPhone) {
            return response()->json([
                'message' => 'Client phone is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'ok' => true,
            'client_phone' => $clientPhone,
            'employee_phone' => $employeePhone,
            'beeline' => $service->callFromAbonent($employeePhone, $clientPhone),
        ]);
    }

    public function createEntity(Request $request, PhoneCall $phoneCall, BeelinePbxService $service): PhoneCallResource
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $service->createEntityForCall($phoneCall->load('lead'), $data['name'] ?? null);

        return new PhoneCallResource($phoneCall->refresh()->load(['telephone', 'entity', 'unit', 'lead']));
    }

    protected function leadTitle(array $data): string
    {
        if (!empty($data['good_name'])) {
            return 'Клик по телефону: ' . $data['good_name'];
        }

        if (!empty($data['category_name'])) {
            return 'Клик по телефону: ' . $data['category_name'];
        }

        return 'Клик по телефону с сайта';
    }

    protected function leadDescription(array $data, string $targetPhone): string
    {
        return collect([
            'Целевой номер: +' . $targetPhone,
            !empty($data['source']) ? 'Источник: ' . $data['source'] : null,
            !empty($data['good_id']) ? 'Good ID: ' . $data['good_id'] : null,
            !empty($data['category_id']) ? 'Category ID: ' . $data['category_id'] : null,
            !empty($data['search']) ? 'Поиск: ' . $data['search'] : null,
            !empty($data['url']) ? 'URL: ' . $data['url'] : null,
        ])->filter()->implode("\n");
    }

    protected function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }

        return Str::limit($digits, 16, '');
    }
}
