<?php

namespace App\Services\Telephony;

use App\Models\Entity;
use App\Models\Lead;
use App\Models\PhoneCall;
use App\Models\Telephone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BeelinePbxService
{
    public function handle(array $payload): array
    {
        $cmd = Str::lower((string) Arr::get($payload, 'cmd'));

        return match ($cmd) {
            'contact' => $this->handleContact($payload),
            'event', 'history' => [
                'status' => 200,
                'data' => [
                    'ok' => true,
                    'call_id' => $this->registerCall($payload)->id,
                ],
            ],
            default => [
                'status' => 400,
                'data' => ['error' => 'Invalid parameters'],
            ],
        };
    }

    public function handleContact(array $payload): array
    {
        $phone = $this->normalizePhone(Arr::get($payload, 'phone'));

        if ($phone === null) {
            return [
                'status' => 400,
                'data' => ['error' => 'Invalid parameters'],
            ];
        }

        $context = $this->resolveCrmContext($phone, true);
        $name = $context['entity']?->name
            ?: $context['unit']?->name
            ?: ('Клиент +' . $phone);

        return [
            'status' => 200,
            'data' => [
                'contact_name' => $name,
                'responsible' => '',
            ],
        ];
    }

    public function registerCall(array $payload): PhoneCall
    {
        $data = $this->normalizeCallPayload($payload);

        return DB::transaction(function () use ($data, $payload) {
            $context = $this->resolveCrmContext($data['client_phone'], true);
            $lead = $this->resolveLead($context['telephone'], $context['entity'], $context['unit'], $data);

            $attributes = array_filter([
                'provider' => 'beeline',
                'provider_call_id' => $data['provider_call_id'],
            ], fn ($value) => $value !== null && $value !== '');

            $values = [
                'event_type' => $data['event_type'],
                'direction' => $data['direction'],
                'status' => $data['status'],
                'client_phone' => $data['client_phone'],
                'employee_user' => $data['employee_user'],
                'employee_extension' => $data['employee_extension'],
                'employee_phone' => $data['employee_phone'],
                'group_name' => $data['group_name'],
                'diversion_phone' => $data['diversion_phone'],
                'started_at' => $data['started_at'],
                'duration_seconds' => $data['duration_seconds'],
                'wait_seconds' => $data['wait_seconds'],
                'recording_url' => $data['recording_url'],
                'telephone_id' => $context['telephone']?->id,
                'entity_id' => $context['entity']?->id,
                'unit_id' => $context['unit']?->id,
                'lead_id' => $lead?->id,
                'raw_payload' => $payload,
            ];

            if ($data['event_type'] === 'accepted') {
                $values['answered_at'] = now();
            }

            if (in_array($data['event_type'], ['completed', 'cancelled'], true)) {
                $values['ended_at'] = now();
            }

            $call = $attributes
                ? PhoneCall::query()->updateOrCreate($attributes, $values)
                : PhoneCall::query()->create(['provider' => 'beeline', ...$values]);

            if ($lead) {
                $lead->forceFill([
                    'first_phone_call_id' => $lead->first_phone_call_id ?: $call->id,
                    'last_phone_call_id' => $call->id,
                    'last_activity_at' => $data['started_at'] ?: now(),
                ])->save();
            }

            return $call->load(['telephone', 'entity', 'unit', 'lead']);
        });
    }

    public function createEntityForCall(PhoneCall $call, ?string $name = null): Entity
    {
        return DB::transaction(function () use ($call, $name) {
            $phone = $this->normalizePhone($call->client_phone);
            $telephone = $phone ? Telephone::query()->firstOrCreate(['number' => $phone]) : null;
            $entity = $this->createPlaceholderEntity($phone, $name);

            if ($telephone) {
                $telephone->entities()->syncWithoutDetaching([$entity->id]);
            }

            $call->forceFill([
                'telephone_id' => $telephone?->id,
                'entity_id' => $entity->id,
            ])->save();

            if ($call->lead) {
                $call->lead->forceFill([
                    'telephone_id' => $telephone?->id,
                    'entity_id' => $entity->id,
                ])->save();
            }

            return $entity->load(['telephones:id,number']);
        });
    }

    public function normalizePhone(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }

        if (strlen($digits) > 16) {
            $digits = substr($digits, -16);
        }

        return $digits;
    }

    protected function normalizeCallPayload(array $payload): array
    {
        $cmd = Str::lower((string) Arr::get($payload, 'cmd'));
        $eventType = $cmd === 'event'
            ? Str::lower((string) Arr::get($payload, 'type'))
            : 'history';

        $status = $this->normalizeStatus(Arr::get($payload, 'status'), $eventType);
        $direction = $this->normalizeDirection(
            Arr::get($payload, 'direction', Arr::get($payload, 'type')),
            $status,
            $eventType
        );

        return [
            'provider_call_id' => $this->nullableString(Arr::get($payload, 'callid', Arr::get($payload, 'UID'))),
            'event_type' => $eventType ?: null,
            'direction' => $direction,
            'status' => $status,
            'client_phone' => $this->normalizePhone(Arr::get($payload, 'phone', Arr::get($payload, 'client'))),
            'employee_user' => $this->nullableString(Arr::get($payload, 'user', Arr::get($payload, 'account'))),
            'employee_extension' => $this->nullableString(Arr::get($payload, 'ext')),
            'employee_phone' => $this->normalizePhone(Arr::get($payload, 'telnum')),
            'group_name' => $this->nullableString(Arr::get($payload, 'groupRealName')),
            'diversion_phone' => $this->normalizePhone(Arr::get($payload, 'diversion', Arr::get($payload, 'via'))),
            'started_at' => $this->parseBeelineDate(Arr::get($payload, 'start')),
            'duration_seconds' => $this->nullableInt(Arr::get($payload, 'duration')),
            'wait_seconds' => $this->nullableInt(Arr::get($payload, 'wait')),
            'recording_url' => $this->nullableString(Arr::get($payload, 'link', Arr::get($payload, 'record'))),
        ];
    }

    protected function resolveCrmContext(?string $phone, bool $createMissing): array
    {
        if ($phone === null) {
            return ['telephone' => null, 'entity' => null, 'unit' => null];
        }

        $telephone = Telephone::query()
            ->with(['entities.units', 'units'])
            ->firstWhere('number', $phone);

        if (!$telephone && $createMissing) {
            $telephone = Telephone::query()->create(['number' => $phone]);
            $entity = $this->createPlaceholderEntity($phone);
            $telephone->entities()->syncWithoutDetaching([$entity->id]);
            $telephone->load(['entities.units', 'units']);
        }

        $entity = $telephone?->entities->first();
        $unit = $telephone?->units->first() ?: $entity?->units->first();

        return compact('telephone', 'entity', 'unit');
    }

    protected function resolveLead(?Telephone $telephone, ?Entity $entity, $unit, array $callData): ?Lead
    {
        if (!$telephone && !$callData['client_phone']) {
            return null;
        }

        $lead = Lead::query()
            ->open()
            ->when($telephone, fn ($q) => $q->where('telephone_id', $telephone->id))
            ->when(!$telephone && $callData['client_phone'], fn ($q) => $q->where('client_phone', $callData['client_phone']))
            ->latest('last_activity_at')
            ->first();

        if ($lead) {
            return $lead;
        }

        return Lead::query()->create([
            'source' => 'beeline',
            'status' => Lead::STATUS_OPEN,
            'title' => 'Звонок +' . ($callData['client_phone'] ?: 'без номера'),
            'description' => 'Автоматически создано из звонка Билайн АТС.',
            'client_phone' => $callData['client_phone'],
            'telephone_id' => $telephone?->id,
            'entity_id' => $entity?->id,
            'unit_id' => $unit?->id,
            'last_activity_at' => $callData['started_at'] ?: now(),
        ]);
    }

    protected function createPlaceholderEntity(?string $phone, ?string $preferredName = null): Entity
    {
        $baseName = trim((string) $preferredName) ?: 'Клиент +' . ($phone ?: 'без номера');
        $name = $baseName;
        $suffix = 2;

        while (Entity::query()->where('name', $name)->exists()) {
            $name = $baseName . ' #' . $suffix;
            $suffix++;
        }

        return Entity::query()->create(['name' => $name]);
    }

    protected function normalizeDirection(mixed $value, ?string $status, ?string $eventType): string
    {
        $value = Str::lower((string) $value);

        if (in_array($value, ['in', 'incoming'], true)) {
            return PhoneCall::DIRECTION_IN;
        }

        if (in_array($value, ['out', 'outgoing'], true)) {
            return PhoneCall::DIRECTION_OUT;
        }

        if ($status === 'missed') {
            return PhoneCall::DIRECTION_MISSED;
        }

        return match ($eventType) {
            'incoming' => PhoneCall::DIRECTION_IN,
            'outgoing' => PhoneCall::DIRECTION_OUT,
            default => PhoneCall::DIRECTION_UNKNOWN,
        };
    }

    protected function normalizeStatus(mixed $status, ?string $eventType): ?string
    {
        $status = Str::lower((string) $status);

        if ($status === '') {
            return match ($eventType) {
                'accepted', 'completed' => 'success',
                'cancelled' => 'cancelled',
                default => null,
            };
        }

        return match ($status) {
            'success' => 'success',
            'missed' => 'missed',
            'cancel' => 'cancelled',
            'busy' => 'busy',
            'notavailable' => 'not_available',
            'notallowed' => 'not_allowed',
            'notfound' => 'not_found',
            default => $status,
        };
    }

    protected function parseBeelineDate(mixed $value): ?CarbonImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{8}T\d{6}Z$/', $value)) {
                return CarbonImmutable::createFromFormat('Ymd\THis\Z', $value, 'UTC')
                    ->setTimezone(config('app.timezone'));
            }

            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max((int) $value, 0);
    }
}
