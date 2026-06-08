<?php

namespace App\Services\Telephony;

use App\Models\Entity;
use App\Models\Lead;
use App\Models\PhoneCall;
use App\Models\Telephone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class BeelinePbxService
{
    public function handle(array $payload): array
    {
        $cmd = Str::lower((string) Arr::get($payload, 'cmd'));

        if ($cmd === '' && $this->looksLikePortalEvent($payload)) {
            $cmd = 'event';
        }

        if ($cmd === '') {
            return [
                'status' => 200,
                'data' => ['ok' => true],
            ];
        }

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
        $payload = $this->normalizeIncomingPayload($payload);
        $data = $this->normalizeCallPayload($payload);

        return $this->persistCall($payload, $data);
    }

    public function rebuildStoredCall(PhoneCall $call): PhoneCall
    {
        $payload = $call->raw_payload ?: [];

        if ($payload === []) {
            return $call;
        }

        $payload = $this->normalizeIncomingPayload($payload);
        $data = $this->normalizeCallPayload($payload);

        return $this->persistCall($payload, $data, $call, true);
    }

    public function cleanupLikelyServiceClients(): array
    {
        return DB::transaction(function () {
            $numbers = Telephone::query()
                ->where('number', 'like', '20%')
                ->get(['id', 'number'])
                ->filter(fn (Telephone $telephone) => $this->isLikelyServiceNumber($telephone->number))
                ->values();

            if ($numbers->isEmpty()) {
                return [
                    'phone_calls_cleared' => 0,
                    'leads_deleted' => 0,
                    'telephones_deleted' => 0,
                    'entities_deleted' => 0,
                ];
            }

            $phoneNumbers = $numbers->pluck('number')->all();
            $telephoneIds = $numbers->pluck('id')->all();
            $entityNames = array_map(fn (string $phone) => 'Клиент +' . $phone, $phoneNumbers);

            $phoneCallsCleared = PhoneCall::query()
                ->where('provider', 'beeline')
                ->whereIn('client_phone', $phoneNumbers)
                ->update([
                    'client_phone' => null,
                    'telephone_id' => null,
                    'entity_id' => null,
                    'unit_id' => null,
                    'lead_id' => null,
                ]);

            $leadsDeleted = Lead::query()
                ->where('source', 'beeline')
                ->whereIn('client_phone', $phoneNumbers)
                ->delete();

            $entitiesDeleted = 0;

            Entity::query()
                ->whereIn('name', $entityNames)
                ->withCount(['phoneCalls', 'leads', 'units'])
                ->get()
                ->each(function (Entity $entity) use (&$entitiesDeleted) {
                    if ($entity->phone_calls_count || $entity->leads_count || $entity->units_count) {
                        return;
                    }

                    $entity->telephones()->detach();
                    $entity->delete();
                    $entitiesDeleted++;
                });

            $telephonesDeleted = 0;

            Telephone::query()
                ->whereIn('id', $telephoneIds)
                ->withCount(['phoneCalls', 'leads', 'units'])
                ->get()
                ->each(function (Telephone $telephone) use (&$telephonesDeleted) {
                    if ($telephone->phone_calls_count || $telephone->leads_count || $telephone->units_count) {
                        return;
                    }

                    $telephone->entities()->detach();
                    $telephone->delete();
                    $telephonesDeleted++;
                });

            return [
                'phone_calls_cleared' => $phoneCallsCleared,
                'leads_deleted' => $leadsDeleted,
                'telephones_deleted' => $telephonesDeleted,
                'entities_deleted' => $entitiesDeleted,
            ];
        });
    }

    protected function persistCall(array $payload, array $data, ?PhoneCall $call = null, bool $replaceExisting = false): PhoneCall
    {
        return DB::transaction(function () use ($data, $payload, $call, $replaceExisting) {
            $attributes = $data['provider_call_id']
                ? ['provider' => 'beeline', 'provider_call_id' => $data['provider_call_id']]
                : [];

            $call = $call ?: ($attributes ? PhoneCall::query()->where($attributes)->first() : null);
            $data = $this->mergeCallData($call, $data, $replaceExisting);
            $data = $this->completeTiming($data);
            $context = $this->resolveCrmContext($data['client_phone'], true);
            $lead = $this->resolveLead($context['telephone'], $context['entity'], $context['unit'], $data);

            $values = [
                'provider_call_id' => $data['provider_call_id'],
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
                'answered_at' => $data['answered_at'],
                'ended_at' => $data['ended_at'],
                'duration_seconds' => $data['duration_seconds'],
                'wait_seconds' => $data['wait_seconds'],
                'recording_url' => $data['recording_url'],
                'telephone_id' => $context['telephone']?->id,
                'entity_id' => $context['entity']?->id,
                'unit_id' => $context['unit']?->id,
                'lead_id' => $lead?->id,
                'raw_payload' => $payload,
            ];

            if ($call) {
                $call->forceFill($values)->save();
            } else {
                $call = PhoneCall::query()->create(['provider' => 'beeline', ...$attributes, ...$values]);
            }

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

    public function callFromAbonent(string $abonentPhone, string $clientPhone): array
    {
        $apiUrl = rtrim(trim((string) config('services.beeline_pbx.api_url')), '/');
        $token = trim((string) config('services.beeline_pbx.api_token'));
        $abonent = $this->normalizePhone($abonentPhone);
        $client = $this->normalizePhone($clientPhone);

        if ($apiUrl === '') {
            throw new RuntimeException('BEELINE_PBX_API_URL is not configured.');
        }

        if ($token === '') {
            throw new RuntimeException('BEELINE_PBX_API_TOKEN is not configured.');
        }

        if (!$abonent || !$client) {
            throw new RuntimeException('Both abonent and client phone numbers are required.');
        }

        $response = $this->sendCallFromAbonentRequest($apiUrl, $token, 'v2/abonents/' . rawurlencode($abonent) . '/call', $client);

        if (in_array($response->status(), [404, 405], true)) {
            $response = $this->sendCallFromAbonentRequest($apiUrl, $token, 'abonents/' . rawurlencode($abonent) . '/call', $client);
        }

        $body = trim($response->body());

        if ($response->failed()) {
            throw new RuntimeException(
                'Beeline PBX click-to-call failed: HTTP ' . $response->status() . ' ' . Str::limit($body, 500)
            );
        }

        return [
            'status' => $response->status(),
            'response' => $response->json() ?: $body,
        ];
    }

    protected function sendCallFromAbonentRequest(string $apiUrl, string $token, string $endpoint, string $clientPhone): \Illuminate\Http\Client\Response
    {
        return Http::withHeaders([
            'X-MPBX-API-AUTH-TOKEN' => $token,
        ])
            ->acceptJson()
            ->timeout(15)
            ->post($apiUrl . '/' . ltrim($endpoint, '/') . '?' . http_build_query([
                'phoneNumber' => '+' . $clientPhone,
            ]));
    }

    public function syncHistory(array $filters = [], bool $dryRun = false): array
    {
        $rows = $this->fetchHistoryRows($filters);
        $stored = 0;
        $skipped = 0;
        $sample = [];

        foreach ($rows as $row) {
            $payload = $this->historyRowToPayload($row);

            if (!$payload['phone'] || !$payload['callid']) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $sample[] = $payload;
                continue;
            }

            $this->registerCall($payload);
            $stored++;
        }

        return [
            'fetched' => count($rows),
            'stored' => $dryRun ? 0 : $stored,
            'skipped' => $skipped,
            'sample' => array_slice($sample, 0, 5),
        ];
    }

    public function fetchHistory(array $filters = []): string
    {
        $response = $this->requestHistory($filters);

        return trim($response->body());
    }

    public function fetchHistoryRows(array $filters = []): array
    {
        $response = $this->requestHistory($filters);
        $body = trim($response->body());
        $decoded = $response->json();

        if ($this->looksLikeHtml($body)) {
            return $this->fetchPortalStatisticsRows($filters);
        }

        if (is_array($decoded)) {
            if (array_is_list($decoded)) {
                return $decoded;
            }

            foreach (['data', 'items', 'history', 'calls', 'result'] as $key) {
                if (isset($decoded[$key]) && is_array($decoded[$key])) {
                    return $decoded[$key];
                }
            }
        }

        return $this->parseHistoryCsv($body);
    }

    protected function requestHistory(array $filters = []): \Illuminate\Http\Client\Response
    {
        $apiUrl = trim((string) (($filters['api_url'] ?? null) ?: config('services.beeline_pbx.history_url')));
        $token = trim((string) config('services.beeline_pbx.api_token'));

        if ($apiUrl === '') {
            throw new RuntimeException('BEELINE_PBX_HISTORY_URL is not configured for history polling.');
        }

        if ($token === '') {
            throw new RuntimeException('BEELINE_PBX_API_TOKEN is not configured.');
        }

        $payload = array_filter([
            'cmd' => 'history',
            'token' => $token,
            'period' => $filters['period'] ?? 'today',
            'start' => $filters['start'] ?? null,
            'end' => $filters['end'] ?? null,
            'type' => $filters['type'] ?? 'all',
            'limit' => $filters['limit'] ?? 500,
            'user' => $filters['user'] ?? null,
            'diversion' => $filters['diversion'] ?? null,
            'client' => $filters['client'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        if (!empty($payload['start']) || !empty($payload['end'])) {
            unset($payload['period']);
        }

        $response = Http::withHeaders([
            'X-API-KEY' => $token,
        ])
            ->acceptJson()
            ->timeout(30)
            ->get($apiUrl, Arr::except($payload, ['cmd', 'token']));

        if ($response->status() === 405) {
            $response = Http::withHeaders([
                'X-API-KEY' => $token,
            ])
                ->acceptJson()
                ->asForm()
                ->timeout(30)
                ->post($apiUrl, $payload);
        }

        $body = trim($response->body());

        if ($response->failed()) {
            throw new RuntimeException(
                'Beeline PBX history request failed: HTTP ' . $response->status() . ' ' . Str::limit($body, 500)
            );
        }

        if (Str::startsWith($body, ['{', '['])) {
            $decoded = json_decode($body, true);

            if (is_array($decoded) && isset($decoded['error'])) {
                throw new RuntimeException('Beeline PBX history request failed: ' . $decoded['error']);
            }

            if (is_array($decoded) && isset($decoded['errorCode'])) {
                throw new RuntimeException(
                    'Beeline PBX history request failed: ' . $decoded['errorCode']
                );
            }
        }

        return $response;
    }

    protected function fetchPortalStatisticsRows(array $filters = []): array
    {
        $apiUrl = rtrim(trim((string) config('services.beeline_pbx.api_url')), '/');
        $token = trim((string) config('services.beeline_pbx.api_token'));

        if ($apiUrl === '' || $token === '') {
            return [];
        }

        $abonentsResponse = Http::withHeaders([
            'X-MPBX-API-AUTH-TOKEN' => $token,
        ])
            ->acceptJson()
            ->timeout(30)
            ->get($apiUrl . '/abonents');

        if ($abonentsResponse->failed() || !is_array($abonentsResponse->json())) {
            return [];
        }

        [$dateFrom, $dateTo] = $this->portalStatisticsPeriod($filters);
        $limit = max((int) ($filters['limit'] ?? 500), 1);
        $pageSize = min(max($limit, 10), 100);
        $rows = [];

        foreach ($abonentsResponse->json() as $abonent) {
            $userId = Arr::get($abonent, 'userId');

            if (!$userId) {
                continue;
            }

            for ($page = 0; count($rows) < $limit; $page++) {
                $response = Http::withHeaders([
                    'X-MPBX-API-AUTH-TOKEN' => $token,
                ])
                    ->acceptJson()
                    ->timeout(30)
                    ->get($apiUrl . '/v2/statistics', [
                        'userId' => $userId,
                        'dateFrom' => $dateFrom,
                        'dateTo' => $dateTo,
                        'page' => $page,
                        'pageSize' => $pageSize,
                    ]);

                $pageRows = $response->json();

                if ($response->failed() || !is_array($pageRows) || $pageRows === []) {
                    break;
                }

                foreach ($pageRows as $row) {
                    $rows[] = [
                        ...$row,
                        'abonent' => Arr::get($row, 'abonent', $abonent),
                        'raw_portal_statistics_row' => $row,
                    ];

                    if (count($rows) >= $limit) {
                        break 2;
                    }
                }

                if (count($pageRows) < $pageSize) {
                    break;
                }
            }
        }

        return $rows;
    }

    protected function portalStatisticsPeriod(array $filters = []): array
    {
        $timezone = config('app.timezone');
        $now = CarbonImmutable::now($timezone);
        $period = (string) ($filters['period'] ?? 'today');

        $start = match ($period) {
            'yesterday' => $now->subDay()->startOfDay(),
            'this_week' => $now->startOfWeek(),
            'last_week' => $now->subWeek()->startOfWeek(),
            'this_month' => $now->startOfMonth(),
            'last_month' => $now->subMonth()->startOfMonth(),
            default => $now->startOfDay(),
        };

        $end = match ($period) {
            'yesterday' => $now->subDay()->endOfDay(),
            'last_week' => $now->subWeek()->endOfWeek(),
            'last_month' => $now->subMonth()->endOfMonth(),
            default => $now,
        };

        if (!empty($filters['start'])) {
            $start = $this->parseBeelineDate($filters['start']) ?: $start;
        }

        if (!empty($filters['end'])) {
            $end = $this->parseBeelineDate($filters['end']) ?: $end;
        }

        return [
            $start->utc()->format('Y-m-d\TH:i:s\Z'),
            $end->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    public function subscribeEvents(array $options = [], bool $dryRun = false): array
    {
        $apiUrl = rtrim(trim((string) config('services.beeline_pbx.api_url')), '/');
        $token = trim((string) config('services.beeline_pbx.api_token'));
        $endpoint = $apiUrl . '/subscription';

        if ($apiUrl === '') {
            throw new RuntimeException('BEELINE_PBX_API_URL is not configured.');
        }

        if ($token === '') {
            throw new RuntimeException('BEELINE_PBX_API_TOKEN is not configured.');
        }

        $callbackUrl = $this->subscriptionCallbackUrl($options['url'] ?? null);
        $payload = array_filter([
            'expires' => max((int) ($options['expires'] ?? 3600), 300),
            'subscriptionType' => 'BASIC_CALL',
            'url' => $callbackUrl,
            'pattern' => $options['pattern'] ?: config('services.beeline_pbx.subscription_pattern'),
        ], fn ($value) => $value !== null && $value !== '');

        if ($dryRun) {
            return [
                'endpoint' => $endpoint,
                'payload' => $payload,
            ];
        }

        $response = Http::withHeaders([
            'X-MPBX-API-AUTH-TOKEN' => $token,
        ])
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->put($endpoint, $payload);

        $body = trim($response->body());

        if ($response->failed()) {
            throw new RuntimeException(
                'Beeline PBX subscription failed: HTTP ' . $response->status() . ' ' . Str::limit($body, 500)
            );
        }

        return [
            'endpoint' => $endpoint,
            'payload' => $payload,
            'response' => $response->json() ?: $body,
        ];
    }

    public function parseHistoryCsv(string $csv): array
    {
        $csv = trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv));

        if ($csv === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $csv) ?: [];
        $header = null;
        $rows = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $columns = str_getcsv($line, ',', '"', '\\');

            if ($header === null) {
                $header = array_map(fn ($value) => trim((string) $value), $columns);
                continue;
            }

            if (count($columns) < count($header)) {
                $columns = array_pad($columns, count($header), null);
            }

            $rows[] = array_combine($header, array_slice($columns, 0, count($header))) ?: [];
        }

        return $rows;
    }

    public function normalizePhone(mixed $value): ?string
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $phone = $this->normalizePhone($item);

                if ($phone !== null) {
                    return $phone;
                }
            }

            return null;
        }

        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || Str::startsWith(Str::upper($value), 'MPBX_SYS_')) {
            return null;
        }

        $candidate = $value;

        if (preg_match('/(?:sip:|tel:)?\+?([78]?\d{9,15})(?=@|[;>\s]|$)/i', $value, $matches)) {
            $candidate = $matches[1];
        } elseif (Str::contains($value, '@')) {
            $candidate = Str::before(Str::after($value, ':'), '@');
        }

        $digits = preg_replace('/\D+/', '', $candidate);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '7' . $digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }

        if (strlen($digits) < 5) {
            return null;
        }

        if (strlen($digits) > 16) {
            $digits = substr($digits, -16);
        }

        return $digits;
    }

    protected function normalizeClientPhone(mixed $value): ?string
    {
        $phone = $this->normalizePhone($value);

        if (!$phone || $this->isLikelyServiceNumber($phone)) {
            return null;
        }

        // Beeline PBX integration is configured for the Russian phone network.
        return preg_match('/^7\d{10}$/', $phone) ? $phone : null;
    }

    protected function isLikelyServiceNumber(mixed $phone): bool
    {
        $phone = $this->normalizePhone($phone);

        if (!$phone) {
            return false;
        }

        return strlen($phone) >= 13 && str_starts_with($phone, '20');
    }

    protected function normalizeCallPayload(array $payload): array
    {
        $cmd = Str::lower((string) Arr::get($payload, 'cmd'));
        $eventType = $this->normalizeEventType($cmd, Arr::get($payload, 'type'), Arr::get($payload, 'status'));

        $status = $this->normalizeStatus(
            Arr::get($payload, 'status'),
            $eventType,
            Arr::get($payload, 'direction', Arr::get($payload, 'type'))
        );
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
            'client_phone' => $this->normalizeClientPhone(Arr::get($payload, 'phone', Arr::get($payload, 'client'))),
            'employee_user' => $this->normalizeEmployeeUser(Arr::get($payload, 'user', Arr::get($payload, 'account'))),
            'employee_extension' => $this->nullableString(Arr::get($payload, 'ext')),
            'employee_phone' => $this->normalizePhone(Arr::get($payload, 'telnum') ?: Arr::get($payload, 'local_phone')),
            'group_name' => $this->nullableString(Arr::get($payload, 'groupRealName')),
            'diversion_phone' => $this->normalizePhone(Arr::get($payload, 'diversion', Arr::get($payload, 'via'))),
            'started_at' => $this->parseBeelineDate(Arr::get($payload, 'start')),
            'answered_at' => $this->parseBeelineDate(Arr::get($payload, 'answered_at')),
            'ended_at' => $this->parseBeelineDate(Arr::get($payload, 'end')),
            'event_at' => $this->parseBeelineDate(Arr::get($payload, 'event_time')),
            'duration_seconds' => $this->normalizeDurationSeconds(Arr::get($payload, 'duration')),
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

    protected function mergeCallData(?PhoneCall $call, array $data, bool $replaceExisting = false): array
    {
        if (!$call) {
            return $data;
        }

        if ($replaceExisting) {
            $existingClientPhone = $this->normalizeClientPhone($call->client_phone);

            if (!$data['client_phone'] && $existingClientPhone) {
                $data['client_phone'] = $existingClientPhone;
            }

            foreach ([
                'provider_call_id',
                'employee_user',
                'employee_extension',
                'employee_phone',
                'group_name',
                'diversion_phone',
                'started_at',
                'answered_at',
                'ended_at',
                'duration_seconds',
                'wait_seconds',
                'recording_url',
            ] as $field) {
                if ($data[$field] === null || $data[$field] === '') {
                    $data[$field] = $call->{$field};
                }
            }

            if (($data['direction'] ?? PhoneCall::DIRECTION_UNKNOWN) === PhoneCall::DIRECTION_UNKNOWN) {
                $data['direction'] = $call->direction ?: PhoneCall::DIRECTION_UNKNOWN;
            }

            if (($data['status'] ?? null) === null) {
                $data['status'] = $call->status;
            }

            if (($data['event_type'] ?? null) === null) {
                $data['event_type'] = $call->event_type;
            }

            return $data;
        }

        foreach ([
            'provider_call_id',
            'client_phone',
            'employee_user',
            'employee_extension',
            'employee_phone',
            'group_name',
            'diversion_phone',
            'started_at',
            'answered_at',
            'ended_at',
            'duration_seconds',
            'wait_seconds',
            'recording_url',
        ] as $field) {
            if ($data[$field] === null || $data[$field] === '') {
                $data[$field] = $call->{$field};
            }
        }

        if (($data['direction'] ?? PhoneCall::DIRECTION_UNKNOWN) === PhoneCall::DIRECTION_UNKNOWN) {
            $data['direction'] = $call->direction ?: PhoneCall::DIRECTION_UNKNOWN;
        }

        if (($data['status'] ?? null) === null) {
            $data['status'] = $call->status;
        }

        if (($data['event_type'] ?? null) === null) {
            $data['event_type'] = $call->event_type;
        }

        return $data;
    }

    protected function completeTiming(array $data): array
    {
        $eventAt = $data['event_at'] ?? null;

        if (!$data['answered_at'] && $eventAt && in_array($data['event_type'], ['accepted', 'answered'], true)) {
            $data['answered_at'] = $eventAt;
        }

        if (!$data['ended_at'] && $eventAt && in_array($data['event_type'], ['released', 'completed', 'cancelled'], true)) {
            $data['ended_at'] = $eventAt;
        }

        if (!$data['duration_seconds'] && $data['started_at'] && $data['ended_at']) {
            $seconds = $data['ended_at']->diffInSeconds($data['started_at'], true);
            $data['duration_seconds'] = $seconds > 0 ? (int) round($seconds) : null;
        }

        return $data;
    }

    protected function normalizeEventType(string $cmd, mixed $type, mixed $status = null): ?string
    {
        if ($cmd === 'history') {
            return 'history';
        }

        $value = $this->normalizeEventName($type) ?: $this->normalizeEventName($status);

        if ($value === null) {
            return $cmd === 'event' ? 'event' : null;
        }

        if (Str::contains($value, 'released')) {
            return 'released';
        }

        if (Str::contains($value, ['answered', 'accepted', 'connected', 'active'])) {
            return 'accepted';
        }

        if (Str::contains($value, ['cancel', 'failed', 'busy', 'notavailable', 'notfound'])) {
            return 'cancelled';
        }

        if (Str::contains($value, ['originated', 'outgoing', 'placed'])) {
            return 'outgoing';
        }

        if (Str::contains($value, ['received', 'incoming', 'delivered', 'alerting'])) {
            return 'incoming';
        }

        return Str::limit($value, 32, '');
    }

    protected function normalizeDirection(mixed $value, ?string $status, ?string $eventType): string
    {
        $value = $this->normalizeEventName($value) ?: '';

        if ($value === 'missed') {
            return PhoneCall::DIRECTION_MISSED;
        }

        if (in_array($value, ['in', 'incoming', 'inbound', 'terminator'], true)
            || Str::contains($value, ['received', 'incoming', 'terminator'])) {
            return PhoneCall::DIRECTION_IN;
        }

        if (in_array($value, ['out', 'outgoing', 'outbound', 'originator'], true)
            || Str::contains($value, ['originated', 'outgoing', 'originator', 'placed'])) {
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

    protected function normalizeStatus(mixed $status, ?string $eventType, mixed $direction = null): ?string
    {
        $status = $this->normalizeEventName($status) ?: '';

        if ($status === '') {
            if ($eventType === 'history') {
                return Str::lower((string) $direction) === 'missed' ? 'missed' : 'success';
            }

            return match ($eventType) {
                'accepted' => 'success',
                'released', 'completed' => 'completed',
                'cancelled' => 'cancelled',
                default => null,
            };
        }

        if (Str::contains($status, 'released')) {
            return 'completed';
        }

        return match ($status) {
            'success', 'successful', 'answered', 'connected', 'active', 'recieved', 'received', 'placed' => 'success',
            'completed' => 'completed',
            'released' => 'completed',
            'ringing', 'alerting', 'delivered' => 'ringing',
            'missed' => 'missed',
            'cancel', 'failed' => 'cancelled',
            'busy' => 'busy',
            'notavailable' => 'not_available',
            'notallowed' => 'not_allowed',
            'notfound' => 'not_found',
            default => $status,
        };
    }

    protected function normalizeEventName(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        $value = Str::lower(Str::afterLast($value, ':'));
        $value = str_replace(['call', 'event', '_', '-', ' '], '', $value);

        return $value === '' ? null : $value;
    }

    protected function normalizeEmployeeUser(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        $value = preg_replace('/^(sip|tel):/i', '', $value);

        return Str::contains($value, '@') ? Str::before($value, '@') : $value;
    }

    protected function parseBeelineDate(mixed $value): ?CarbonImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{13}$/', $value)) {
                return CarbonImmutable::createFromTimestampMs((int) $value)
                    ->setTimezone(config('app.timezone'));
            }

            if (preg_match('/^\d{10}$/', $value)) {
                return CarbonImmutable::createFromTimestamp((int) $value)
                    ->setTimezone(config('app.timezone'));
            }

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
        if (!is_scalar($value)) {
            return null;
        }

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

    protected function normalizeDurationSeconds(mixed $value): ?int
    {
        $duration = $this->nullableInt($value);

        if ($duration === null) {
            return null;
        }

        if ($duration > 86400) {
            return (int) round($duration / 1000);
        }

        return $duration;
    }

    protected function portalDurationSeconds(mixed $value): ?int
    {
        $duration = $this->nullableInt($value);

        if ($duration === null) {
            return null;
        }

        return (int) round($duration / 1000);
    }

    protected function historyRowToPayload(array $row): array
    {
        if (Arr::has($row, 'startDate') && Arr::has($row, 'abonent')) {
            return $this->portalStatisticsRowToPayload($row);
        }

        $type = Str::lower((string) Arr::get($row, 'type', 'all'));
        $status = Arr::get($row, 'status');
        $client = Arr::get($row, 'client');
        $account = Arr::get($row, 'account', Arr::get($row, 'user'));
        $diversion = Arr::get($row, 'via', Arr::get($row, 'diversion'));
        $callId = Arr::get($row, 'UID', Arr::get($row, 'uid'));

        return [
            'cmd' => 'history',
            'type' => $type,
            'status' => $status ?: ($type === 'missed' ? 'Missed' : 'Success'),
            'phone' => $client,
            'user' => $account,
            'user_name' => Arr::get($row, 'user_name'),
            'destination' => Arr::get($row, 'destination'),
            'groupRealName' => Arr::get($row, 'group_name', Arr::get($row, 'groupRealName')),
            'diversion' => $diversion,
            'start' => Arr::get($row, 'start'),
            'wait' => Arr::get($row, 'wait'),
            'duration' => Arr::get($row, 'duration'),
            'link' => Arr::get($row, 'record'),
            'callid' => $callId,
            'raw_history_row' => $row,
        ];
    }

    protected function portalStatisticsRowToPayload(array $row): array
    {
        $direction = Str::lower((string) Arr::get($row, 'direction'));
        $type = Str::contains($direction, 'out') ? 'out' : (Str::contains($direction, 'in') ? 'in' : 'all');
        $phoneFrom = $this->normalizeClientPhone(Arr::get($row, 'phone_from'));
        $phoneTo = $this->normalizeClientPhone(Arr::get($row, 'phone_to'));
        $legacyPhone = $this->normalizeClientPhone(Arr::get($row, 'phone'));
        $clientPhone = $type === 'out'
            ? ($phoneTo ?: $legacyPhone ?: $phoneFrom)
            : ($phoneFrom ?: $legacyPhone ?: $phoneTo);
        $callId = implode(':', [
            'portal',
            sha1(json_encode([
                Arr::get($row, 'abonent.userId'),
                Arr::get($row, 'startDate'),
                Arr::get($row, 'direction'),
                Arr::get($row, 'phone_from'),
                Arr::get($row, 'phone_to'),
                Arr::get($row, 'phone'),
                Arr::get($row, 'duration'),
                Arr::get($row, 'status'),
            ])),
        ]);

        return [
            'cmd' => 'history',
            'type' => $type,
            'status' => Arr::get($row, 'status'),
            'phone' => $clientPhone,
            'user' => Arr::get($row, 'abonent.userId'),
            'user_name' => trim(implode(' ', array_filter([
                Arr::get($row, 'abonent.firstName'),
                Arr::get($row, 'abonent.lastName'),
            ]))),
            'diversion' => $type === 'out' ? Arr::get($row, 'phone_from') : Arr::get($row, 'phone_to'),
            'start' => Arr::get($row, 'startDate'),
            'duration' => $this->portalDurationSeconds(Arr::get($row, 'duration')),
            'callid' => $callId,
            'raw_history_row' => $row,
        ];
    }

    protected function normalizeIncomingPayload(array $payload): array
    {
        $eventData = Arr::get($payload, 'eventData');

        if (is_array($eventData)) {
            $payload = array_merge($payload, $eventData);
        }

        $eventName = $this->payloadValue($payload, [
            'type',
            'eventType',
            'event',
            'eventData.type',
            'eventData.eventType',
        ]) ?: $this->findEventName($payload);

        $payload['cmd'] = Arr::get($payload, 'cmd')
            ?: (Arr::has($payload, 'subscriptionId') || Arr::has($payload, 'eventType') ? 'event' : 'history');

        $payload['callid'] = Arr::get($payload, 'callid')
            ?: Arr::get($payload, 'callId')
            ?: Arr::get($payload, 'call_id')
            ?: Arr::get($payload, 'call.callId')
            ?: Arr::get($payload, 'eventData.call.callId')
            ?: Arr::get($payload, 'eventID')
            ?: Arr::get($payload, 'eventId')
            ?: Arr::get($payload, 'extTrackingId')
            ?: Arr::get($payload, 'externalTrackingId')
            ?: Arr::get($payload, 'trackingId')
            ?: Arr::get($payload, 'UID');

        $payload['direction'] = Arr::get($payload, 'direction')
            ?: $this->payloadValue($payload, [
                'callDirection',
                'direction',
                'personality',
                'call.personality',
            ]);

        $payload['local_phone'] = $this->normalizePhone($this->payloadValue($payload, [
            'localParty.address',
            'localParty.phoneNumber',
            'endpoint.address',
            'telnum',
        ]));

        $payload['phone'] = $this->normalizeClientPhone(Arr::get($payload, 'phone'))
            ?: $this->normalizeClientPhone(Arr::get($payload, 'client'))
            ?: $this->clientPhoneFromPayload($payload, Arr::get($payload, 'direction'));

        $payload['user'] = Arr::get($payload, 'user')
            ?: Arr::get($payload, 'account')
            ?: $this->payloadValue($payload, [
                'targetId',
                'userId',
                'localParty.address',
                'endpoint.address',
                'abonent',
                'extension',
            ]);

        $payload['type'] = Arr::get($payload, 'type')
            ?: Arr::get($payload, 'direction')
            ?: Arr::get($payload, 'callDirection')
            ?: Arr::get($payload, 'eventType')
            ?: Arr::get($payload, 'event')
            ?: $eventName;

        $payload['status'] = Arr::get($payload, 'status')
            ?: Arr::get($payload, 'callStatus')
            ?: Arr::get($payload, 'callState')
            ?: Arr::get($payload, 'state')
            ?: $eventName;

        $payload['start'] = Arr::get($payload, 'start')
            ?: $this->payloadValue($payload, [
                'startedAt',
                'startTime',
                'startDate',
                'call.startTime',
            ]);

        $payload['answered_at'] = Arr::get($payload, 'answered_at')
            ?: $this->payloadValue($payload, [
                'answeredAt',
                'answerTime',
                'connectTime',
                'call.answerTime',
            ]);

        $payload['event_time'] = Arr::get($payload, 'event_time')
            ?: $this->payloadValue($payload, [
                'eventTime',
                'timeStamp',
                'timestamp',
                'time',
                'dateTime',
            ]);

        $payload['end'] = Arr::get($payload, 'end')
            ?: $this->payloadValue($payload, [
                'endedAt',
                'endTime',
                'releaseTime',
                'disconnectTime',
                'call.releaseTime',
            ]);

        if (!$payload['end'] && in_array($this->normalizeEventType((string) $payload['cmd'], $payload['type'], $payload['status']), ['released', 'completed', 'cancelled'], true)) {
            $payload['end'] = $payload['event_time'];
        }

        $payload['duration'] = Arr::get($payload, 'duration')
            ?: $this->payloadValue($payload, [
                'durationSeconds',
                'durationSec',
                'billsec',
                'talkTime',
                'conversationTime',
            ]);

        return $payload;
    }

    protected function payloadValue(array $payload, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = Arr::get($payload, $path);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function looksLikeHtml(string $body): bool
    {
        $body = Str::lower(ltrim($body));

        return Str::startsWith($body, ['<!doctype html', '<html']);
    }

    protected function phoneFromPayloadPaths(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $phone = $this->normalizeClientPhone(Arr::get($payload, $path));

            if ($phone !== null) {
                return $phone;
            }
        }

        return null;
    }

    protected function clientPhoneFromPayload(array $payload, mixed $direction): ?string
    {
        $localPhone = $this->normalizePhone(Arr::get($payload, 'local_phone')) ?: $this->phoneFromPayloadPaths($payload, [
            'localParty.address',
            'endpoint.address',
            'telnum',
        ]);
        $remote = $this->phoneFromPayloadPaths($payload, [
            'remoteNumber',
            'remotePartyAddress',
            'remoteParty.address',
            'remoteParty.phoneNumber',
            'call.remoteParty.address',
            'call.remoteParty.phoneNumber',
        ]);
        $calling = $this->phoneFromPayloadPaths($payload, [
            'callingNumber',
            'caller',
            'from',
            'callingParty.address',
            'callingParty.phoneNumber',
            'callingPartyInfo.address',
            'callingPartyInfo.phoneNumber',
            'call.callingParty.address',
            'call.callingParty.phoneNumber',
        ]);
        $called = $this->phoneFromPayloadPaths($payload, [
            'calledNumber',
            'callee',
            'to',
            'calledParty.address',
            'calledParty.phoneNumber',
            'calledPartyInfo.address',
            'calledPartyInfo.phoneNumber',
            'call.calledParty.address',
            'call.calledParty.phoneNumber',
        ]);

        $direction = $this->normalizeDirection($direction, null, null);
        $preferred = match ($direction) {
            PhoneCall::DIRECTION_IN, PhoneCall::DIRECTION_MISSED => [$calling, $remote, $called],
            PhoneCall::DIRECTION_OUT => [$called, $remote, $calling],
            default => [$remote, $calling, $called],
        };

        foreach ($preferred as $phone) {
            if ($phone && $phone !== $localPhone && !$this->isOwnNumber($phone)) {
                return $phone;
            }
        }

        return null;
    }

    protected function isOwnNumber(string $phone): bool
    {
        $ownNumbers = config('services.beeline_pbx.own_numbers', []);

        if (!is_array($ownNumbers)) {
            $ownNumbers = explode(',', (string) $ownNumbers);
        }

        $ownNumbers = array_filter(array_map(fn ($value) => $this->normalizePhone($value), $ownNumbers));

        return in_array($phone, $ownNumbers, true);
    }

    protected function findEventName(array $payload): ?string
    {
        foreach ($payload as $key => $value) {
            if (is_string($key) && preg_match('/Event$/', $key)) {
                return $key;
            }

            if (is_array($value)) {
                $eventName = $this->findEventName($value);

                if ($eventName !== null) {
                    return $eventName;
                }
            }
        }

        return null;
    }

    protected function subscriptionCallbackUrl(?string $override = null): string
    {
        $url = trim((string) ($override ?: config('services.beeline_pbx.webhook_url')));

        if ($url === '') {
            $url = rtrim((string) config('app.url'), '/') . '/api/telephony/beeline';
        }

        $token = trim((string) (config('services.beeline_pbx.crm_token') ?: config('services.beeline_pbx.api_token')));

        if ($token === '') {
            return $url;
        }

        return $url . (Str::contains($url, '?') ? '&' : '?') . 'crm_token=' . urlencode($token);
    }

    protected function looksLikePortalEvent(array $payload): bool
    {
        return Arr::has($payload, 'subscriptionId')
            || Arr::has($payload, 'eventType')
            || Arr::has($payload, 'eventData')
            || Arr::has($payload, 'callId')
            || Arr::has($payload, 'extTrackingId')
            || Arr::has($payload, 'trackingId');
    }
}
