<?php

namespace App\Services\Suppliers;

use App\Models\SupplierProfile;
use App\Models\SupplierProfileEvent;
use App\Models\Unit;
use App\Models\User;
use App\Services\Auth\StaffAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupplierProfileWriter
{
    public const TIMEZONE = 'Europe/Moscow';

    public function today(): string
    {
        return now(self::TIMEZONE)->toDateString();
    }

    public function data(?SupplierProfile $profile, int $unitId): array
    {
        if (! $profile) {
            return [
                'exists' => false, 'unit_id' => $unitId, 'revision' => 0, 'status' => 'active', 'priority' => 'normal',
                'is_watched' => false, 'owner_user_id' => null, 'owner' => null, 'review_interval_days' => 30,
                'next_review_on' => null, 'last_reviewed_at' => null, 'notes' => '', 'review_state' => 'off', 'updated_at' => null,
            ];
        }
        $profile->loadMissing('owner:id,name');
        $next = $profile->next_review_on?->toDateString();
        $today = $this->today();
        $state = ! $profile->is_watched || in_array($profile->status, ['paused', 'archived'], true)
            ? 'off' : ($next === null ? 'unscheduled' : ($next < $today ? 'overdue' : ($next === $today ? 'today' : 'scheduled')));

        return [
            'exists' => true, 'unit_id' => $profile->unit_id, 'revision' => $profile->revision,
            'status' => $profile->status, 'priority' => $profile->priority, 'is_watched' => $profile->is_watched,
            'owner_user_id' => $profile->owner_user_id, 'owner' => $profile->owner ? ['id' => $profile->owner->id, 'name' => $profile->owner->name] : null,
            'review_interval_days' => $profile->review_interval_days, 'next_review_on' => $next,
            'last_reviewed_at' => $profile->last_reviewed_at?->toISOString(), 'notes' => $profile->notes ?? '',
            'review_state' => $state, 'updated_at' => $profile->updated_at?->toISOString(),
        ];
    }

    /** Only an explicit add action creates a profile; reads never call this. */
    public function create(array $input, User $actor): array
    {
        $identity = Validator::make($input, [
            'create_key' => ['required', 'uuid'],
            'unit_id' => ['required_without:name', 'nullable', 'integer', 'min:1', 'prohibits:name'],
            'name' => ['required_without:unit_id', 'nullable', 'string', 'max:255', 'regex:/\S/u', 'prohibits:unit_id'],
        ])->validate();
        $identity['create_key'] = strtolower($identity['create_key']);
        $fields = $this->validated($input, true);
        $hash = hash('sha256', json_encode([
            'unit_id' => isset($identity['unit_id']) ? (int) $identity['unit_id'] : null,
            'name' => isset($identity['name']) ? trim($identity['name']) : null,
            ...$fields,
            // A retry after midnight must reuse the original creation, rather
            // than treating the changing default business day as new input.
            'next_review_on' => array_key_exists('next_review_on', $input) ? $fields['next_review_on'] : 'default_today',
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($identity, $fields, $hash, $actor): array {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $retry = SupplierProfile::where('created_by', $actor->id)->where('create_key', $identity['create_key'])->first();
            if ($retry) {
                abort_unless(hash_equals((string) $retry->create_payload_hash, $hash), 409,
                    'Этот запрос уже добавил другого поставщика. Для нового добавления обновите форму.');

                return ['unit' => $retry->unit, 'profile' => $retry, 'created' => false];
            }
            $this->assertOwner($fields['owner_user_id']);
            if (! empty($identity['unit_id'])) {
                $unit = Unit::without(['fields', 'labels', 'telephones', 'uris'])->lockForUpdate()->find($identity['unit_id']);
                if (! $unit) {
                    throw ValidationException::withMessages(['unit_id' => 'Выберите существующий Unit.']);
                }
            } else {
                $unit = Unit::create(['name' => trim($identity['name']), 'is_supplier' => true]);
            }
            abort_if(SupplierProfile::where('unit_id', $unit->id)->exists(), 409, 'Поставщик уже добавлен в реестр. Откройте его профиль для изменения.');
            $profile = new SupplierProfile([
                'unit_id' => $unit->id, 'created_by' => $actor->id,
                'create_key' => $identity['create_key'], 'create_payload_hash' => $hash,
            ]);
            $this->persist($profile, $fields, $actor, 'created', 'Поставщик добавлен в реестр.');
            if (! $unit->is_supplier) {
                $unit->update(['is_supplier' => true]);
            }

            return ['unit' => $unit, 'profile' => $profile, 'created' => true];
        });
    }

    public function update(Unit $unit, array $input, User $actor): SupplierProfile
    {
        $revision = Validator::make($input, ['revision' => ['required', 'integer', 'min:0']])->validate()['revision'];
        $fields = $this->validated($input);

        return DB::transaction(function () use ($unit, $revision, $fields, $actor): SupplierProfile {
            $unit = Unit::without(['fields', 'labels', 'telephones', 'uris'])->lockForUpdate()->findOrFail($unit->id);
            $profile = SupplierProfile::where('unit_id', $unit->id)->lockForUpdate()->first();
            $this->assertRevision($profile, (int) $revision);
            $this->assertOwner($fields['owner_user_id']);
            $created = ! $profile;
            $profile ??= new SupplierProfile(['unit_id' => $unit->id, 'created_by' => $actor->id]);
            $this->persist($profile, $fields, $actor, $created ? 'created' : 'updated',
                $created ? 'Поставщик добавлен в реестр.' : 'Настройки контроля поставщика изменены.');
            if ($created && ! $unit->is_supplier) {
                $unit->update(['is_supplier' => true]);
            }

            return $profile;
        });
    }

    public function review(Unit $unit, array $input, User $actor): SupplierProfile
    {
        return $this->activity($unit, $input, $actor, true);
    }

    public function note(Unit $unit, array $input, User $actor): SupplierProfile
    {
        return $this->activity($unit, $input, $actor, false);
    }

    private function activity(Unit $unit, array $input, User $actor, bool $review): SupplierProfile
    {
        $input = Validator::make($input, [
            'revision' => ['required', 'integer', 'min:1'],
            'note' => ['required', 'string', 'max:10000', 'regex:/\S/u'],
            'next_review_on' => ['nullable', 'date_format:Y-m-d'],
        ])->validate();

        return DB::transaction(function () use ($unit, $input, $actor, $review): SupplierProfile {
            Unit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $profile = SupplierProfile::where('unit_id', $unit->id)->lockForUpdate()->firstOrFail();
            $this->assertRevision($profile, (int) $input['revision']);
            $fields = $review ? [
                'last_reviewed_at' => now(),
                'next_review_on' => $input['next_review_on'] ?? now(self::TIMEZONE)->addDays($profile->review_interval_days)->toDateString(),
            ] : [];
            $this->persist($profile, $fields, $actor, $review ? 'reviewed' : 'note', trim($input['note']));

            return $profile;
        });
    }

    private function persist(SupplierProfile $profile, array $fields, User $actor, string $type, string $body): void
    {
        $before = $profile->exists ? $this->snapshot($profile) : [];
        if ($profile->exists) {
            $profile->revision++;
        }
        $profile->fill([...$fields, 'updated_by' => $actor->id]);
        $profile->save();
        $profile->unsetRelation('owner');
        $after = $this->snapshot($profile);
        $changes = [];
        foreach ($after as $field => $value) {
            if (! array_key_exists($field, $before) || $before[$field] !== $value) {
                $changes[$field] = ['before' => $before[$field] ?? null, 'after' => $value];
            }
        }
        SupplierProfileEvent::create([
            'supplier_profile_id' => $profile->id, 'actor_id' => $actor->id, 'actor_name' => $actor->name,
            'type' => $type, 'body' => $body, 'changes' => $changes, 'created_at' => now(),
        ]);
    }

    private function snapshot(SupplierProfile $profile): array
    {
        $profile->loadMissing('owner:id,name');

        return [
            'status' => $profile->status, 'priority' => $profile->priority, 'is_watched' => $profile->is_watched,
            'owner_user_id' => $profile->owner_user_id, 'owner_name' => $profile->owner?->name,
            'review_interval_days' => $profile->review_interval_days,
            'next_review_on' => $profile->next_review_on?->toDateString(),
            'last_reviewed_at' => $profile->last_reviewed_at?->toISOString(), 'notes' => $profile->notes ?? '',
        ];
    }

    private function validated(array $input, bool $creating = false): array
    {
        $required = $creating ? 'sometimes' : 'required';
        $data = Validator::make($input, [
            'status' => [$required, Rule::in(array_keys(SupplierProfile::STATUSES))],
            'priority' => [$required, Rule::in(array_keys(SupplierProfile::PRIORITIES))],
            'is_watched' => [$required, 'boolean'], 'owner_user_id' => ['nullable', 'integer', 'min:1'],
            'review_interval_days' => [$required, 'integer', 'between:1,365'],
            'next_review_on' => ['nullable', 'date_format:Y-m-d'], 'notes' => ['nullable', 'string', 'max:10000'],
        ])->validate();

        return [
            'status' => $data['status'] ?? 'active', 'priority' => $data['priority'] ?? 'normal',
            'is_watched' => (bool) ($data['is_watched'] ?? true),
            'owner_user_id' => isset($data['owner_user_id']) ? (int) $data['owner_user_id'] : null,
            'review_interval_days' => (int) ($data['review_interval_days'] ?? 30),
            'next_review_on' => array_key_exists('next_review_on', $data) ? $data['next_review_on'] : ($creating ? $this->today() : null),
            'notes' => trim($data['notes'] ?? ''),
        ];
    }

    private function assertOwner(?int $id): void
    {
        if ($id !== null && ! app(StaffAccess::class)->allows(User::find($id))) {
            throw ValidationException::withMessages(['owner_user_id' => 'Ответственным может быть только действующий сотрудник.']);
        }
    }

    private function assertRevision(?SupplierProfile $profile, int $revision): void
    {
        abort_unless(($profile?->revision ?? 0) === $revision, 409,
            'Профиль поставщика уже изменён. Обновите данные перед сохранением.');
    }
}
