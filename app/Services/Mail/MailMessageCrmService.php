<?php

namespace App\Services\Mail;

use App\Models\Building;
use App\Models\Email;
use App\Models\Entity;
use App\Models\MailMessage;
use App\Models\Telephone;
use App\Models\Unit;
use App\Models\Uri;
use App\Models\User;
use App\Services\Telephones\TelephoneIdentityService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MailMessageCrmService
{
    public function __construct(
        private readonly MailContactExtractor $extractor,
        private readonly TelephoneIdentityService $telephones,
        private readonly MailWorkspaceAccess $access,
    ) {}

    public function context(MailMessage $message): array
    {
        $candidates = $this->extractor->extract($message);
        $emails = Email::query()->whereIn('address', $candidates['emails'])->with([
            'entities' => fn ($query) => $query->withoutEagerLoads()->select('entities.id', 'entities.name', 'entities.INN'),
            'entities.units' => fn ($query) => $query->withoutEagerLoads()->select('units.id', 'units.name'),
            'units' => fn ($query) => $query->withoutEagerLoads()->select('units.id', 'units.name'),
        ])->get();
        $entities = $emails->flatMap(fn ($email) => $email->entities)->unique('id')->values();
        $units = $emails->flatMap(fn ($email) => $email->units)
            ->merge($entities->flatMap(fn ($entity) => $entity->units))->unique('id')->values();

        return [
            'candidates' => $candidates,
            'linked' => [
                'emails' => $emails->map->only(['id', 'address'])->values()->all(),
                'entities' => $entities->map->only(['id', 'name', 'INN'])->all(),
                'units' => $units->map->only(['id', 'name'])->all(),
                'leads' => $message->leads()->orderBy('id')->get(['id', 'title', 'status'])->toArray(),
            ],
        ];
    }

    public function save(MailMessage $message, User $actor, string $kind, array $data): array
    {
        $this->access->authorize($actor);
        if ($message->direction !== 'incoming') {
            throw ValidationException::withMessages(['mail_message' => 'Эти инструменты доступны для входящего письма.']);
        }

        return DB::transaction(function () use ($message, $kind, $data): array {
            // Repeated clicks on this message serialize all contact/link operations.
            $message = MailMessage::query()->whereKey($message->id)->lockForUpdate()->firstOrFail();
            $record = match ($kind) {
                'entity' => $this->entity($message, $data),
                'unit' => $this->unit($message, $data),
                'telephone' => $this->telephone($data),
                'email' => $this->email($message, $data),
                'website' => $this->website($data),
                'building' => $this->building($data),
            };

            return [
                'created' => $record->wasRecentlyCreated,
                'record' => $this->recordPayload($record),
                'crm' => $this->context($message),
            ];
        }, 3);
    }

    private function entity(MailMessage $message, array $data): Entity
    {
        $entity = ! empty($data['entity_id']) ? Entity::query()->findOrFail($data['entity_id']) : null;
        if (! $entity && filled($data['INN'] ?? null)) {
            $entity = Entity::query()->where('INN', trim($data['INN']))->orderBy('id')->first();
        }
        if (! $entity) {
            $attributes = Arr::only($data, [
                'full_name', 'INN', 'KPP', 'OGRN', 'legal_address', 'country_id', 'entity_classification_id', 'dadata_raw',
            ]);
            if (! empty($attributes['dadata_raw'])) {
                $attributes['dadata_loaded_at'] = now();
            }
            $entity = Entity::query()->firstOrCreate(['name' => trim($data['name'])], $attributes);
            // A reused unique name must not silently bind a different legal identifier.
            if (filled($data['INN'] ?? null) && filled($entity->INN) && $entity->INN !== $data['INN']) {
                throw ValidationException::withMessages(['name' => 'Контрагент с этим названием уже существует с другим ИНН. Выберите его явно или уточните название.']);
            }
        }
        if (empty($data['entity_id'])) {
            foreach (['full_name', 'INN', 'KPP', 'OGRN', 'legal_address', 'country_id', 'entity_classification_id'] as $field) {
                if (blank($entity->{$field}) && filled($data[$field] ?? null)) {
                    $entity->{$field} = $data[$field];
                }
            }
            if (! empty($data['dadata_raw'])) {
                $entity->dadata_raw = $data['dadata_raw'];
                $entity->dadata_loaded_at = now();
            }
            if ($entity->isDirty()) {
                $entity->save();
            }
        }
        $email = $this->resolveEmail($message, $data['email_address'] ?? null);
        $email->entities()->syncWithoutDetaching([$entity->id]);
        if (! empty($data['unit_id'])) {
            $entity->units()->syncWithoutDetaching([$data['unit_id']]);
        }

        return $entity;
    }

    private function unit(MailMessage $message, array $data): Unit
    {
        $unit = ! empty($data['unit_id'])
            ? Unit::query()->findOrFail($data['unit_id'])
            : Unit::query()->firstOrCreate(['name' => trim($data['name'])], [
                'is_customer' => $data['is_customer'] ?? true,
                'is_supplier' => $data['is_supplier'] ?? false,
            ]);
        $email = $this->resolveEmail($message, $data['email_address'] ?? null);
        $email->units()->syncWithoutDetaching([$unit->id]);
        if (! empty($data['entity_id'])) {
            $unit->entities()->syncWithoutDetaching([$data['entity_id']]);
        }

        return $unit;
    }

    private function telephone(array $data): Telephone
    {
        $number = $this->telephones->normalize($data['number']);
        if (! $number || ! preg_match('/^\+?[\d\s().-]+$/u', $data['number'])) {
            throw ValidationException::withMessages(['number' => 'Укажите российский номер телефона, например +7 999 123-45-67.']);
        }
        // Reuse legacy formats without merging or deleting their CRM records.
        $telephone = $this->telephones->find($number) ?: Telephone::query()->firstOrCreate(['number' => $number]);
        if (! empty($data['entity_id'])) {
            $telephone->entities()->syncWithoutDetaching([$data['entity_id']]);
        }
        if (! empty($data['unit_id'])) {
            $telephone->units()->syncWithoutDetaching([$data['unit_id']]);
        }

        return $telephone;
    }

    private function email(MailMessage $message, array $data): Email
    {
        $email = $this->resolveEmail($message, $data['address'] ?? null);
        if (! empty($data['entity_id'])) {
            $email->entities()->syncWithoutDetaching([$data['entity_id']]);
        }
        if (! empty($data['unit_id'])) {
            $email->units()->syncWithoutDetaching([$data['unit_id']]);
        }

        return $email;
    }

    private function resolveEmail(MailMessage $message, ?string $address): Email
    {
        $address = $this->extractor->normalizeEmail((string) ($address ?: $message->from_address));
        if (! $address) {
            throw ValidationException::withMessages(['email_address' => 'Укажите корректный email отправителя.']);
        }
        $email = Email::withTrashed()->whereRaw('LOWER(address) = ?', [$address])->orderBy('id')->first()
            ?: Email::query()->firstOrCreate(['address' => $address], ['source' => 'mail_crm', 'is_active' => true]);
        if ($email->trashed()) {
            $email->restore();
        }
        // A signature email is a CRM contact, not an invented envelope sender/recipient.
        if ($address === Str::lower(trim((string) $message->from_address))) {
            if (! $message->emails()->whereKey($email->id)->wherePivot('role', 'from')->exists()) {
                $message->emails()->attach($email->id, ['role' => 'from']);
            }
        }

        return $email;
    }

    private function website(array $data): Uri
    {
        $address = $this->extractor->normalizeWebsite($data['address']);
        if (! $address) {
            throw ValidationException::withMessages(['address' => 'Укажите корректный адрес сайта HTTP или HTTPS.']);
        }
        $variants = array_unique([$address, $address.'/', preg_replace('~^https?://~', '', $address)]);
        $website = Uri::query()->whereIn('address', $variants)->orderBy('id')->first()
            ?: Uri::query()->create(['address' => $address]);
        $website->units()->syncWithoutDetaching([$data['unit_id']]);

        return $website;
    }

    private function building(array $data): Building
    {
        $building = Building::query()->firstOrCreate([
            'city_id' => $data['city_id'], 'address' => trim($data['address']),
        ], Arr::only($data, ['building_type_id', 'postcode']));
        foreach (['building_type_id', 'postcode'] as $field) {
            if (blank($building->{$field}) && filled($data[$field] ?? null)) {
                $building->{$field} = $data[$field];
            }
        }
        if ($building->isDirty()) {
            $building->save();
        }
        if (! empty($data['entity_id'])) {
            $building->entities()->syncWithoutDetaching([$data['entity_id']]);
        }
        if (! empty($data['unit_id'])) {
            $building->units()->syncWithoutDetaching([$data['unit_id']]);
        }

        return $building;
    }

    private function recordPayload(Model $record): array
    {
        return match (true) {
            $record instanceof Entity => $record->only(['id', 'name', 'full_name', 'INN', 'KPP', 'OGRN', 'legal_address']),
            $record instanceof Unit => $record->only(['id', 'name', 'is_customer', 'is_supplier']),
            $record instanceof Telephone => $record->only(['id', 'number']),
            $record instanceof Building => $record->only(['id', 'address', 'city_id', 'postcode', 'building_type_id']),
            default => $record->only(['id', 'address']),
        };
    }
}
