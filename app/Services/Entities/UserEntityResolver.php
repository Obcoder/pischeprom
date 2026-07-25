<?php

namespace App\Services\Entities;

use App\Models\Email;
use App\Models\Entity;
use App\Models\Telephone;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class UserEntityResolver
{
    public function resolve(User $user, array $profile = []): Entity
    {
        $email = Str::lower(trim((string) $user->email));
        $phone = $this->normalizePhone($profile['phone'] ?? $user->phone);
        $inn = $this->digits($profile['organization_inn'] ?? '');

        $entity = $this->findByInn($inn)
            ?? $this->findByEmail($email)
            ?? $this->findByPhone($phone)
            ?? new Entity;

        $this->fillEntity($entity, $user, $profile, $inn);
        $entity->save();

        $this->attachUser($entity, $user);
        $this->attachEmail($entity, $email);
        $this->attachPhone($entity, $phone);

        if ($user->city_id) {
            $entity->cities()->syncWithoutDetaching([$user->city_id]);
        }

        return $entity->fresh();
    }

    public function attachPhone(Entity $entity, ?string $phone): ?Telephone
    {
        $normalized = $this->normalizePhone($phone);

        if (! $normalized) {
            return null;
        }

        $telephone = $this->findTelephoneByNormalizedNumber($normalized)
            ?? Telephone::query()->create(['number' => $normalized]);

        $entity->telephones()->syncWithoutDetaching([$telephone->id]);

        return $telephone;
    }

    public function normalizePhone(?string $phone): ?string
    {
        $digits = $this->digits($phone);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = '7'.$digits;
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7'.substr($digits, 1);
        }

        return '+'.substr($digits, 0, 15);
    }

    private function findByInn(string $inn): ?Entity
    {
        if ($inn === '') {
            return null;
        }

        return Entity::query()->where('INN', $inn)->first();
    }

    private function findByEmail(string $email): ?Entity
    {
        if ($email === '') {
            return null;
        }

        return Entity::query()
            ->whereHas(
                'emails',
                fn ($query) => $query->whereRaw('LOWER(address) = ?', [$email])
            )
            ->orderBy('id')
            ->first();
    }

    private function findByPhone(?string $phone): ?Entity
    {
        if (! $phone) {
            return null;
        }

        $telephone = $this->findTelephoneByNormalizedNumber($phone);

        if (! $telephone) {
            return null;
        }

        return Entity::query()
            ->whereHas('telephones', fn ($query) => $query->whereKey($telephone->id))
            ->orderBy('id')
            ->first();
    }

    private function findTelephoneByNormalizedNumber(string $phone): ?Telephone
    {
        $match = null;

        Telephone::query()
            ->select(['id', 'number'])
            ->orderBy('id')
            ->chunkById(500, function ($telephones) use (&$match, $phone): bool {
                $match = $telephones->first(
                    fn (Telephone $telephone) => $this->normalizePhone($telephone->number) === $phone
                );

                return $match === null;
            });

        return $match;
    }

    private function fillEntity(Entity $entity, User $user, array $profile, string $inn): void
    {
        $organization = ($profile['account_type'] ?? $user->account_type) === 'organization';
        $name = $this->firstFilled([
            $profile['organization_name'] ?? null,
            $profile['organization_full_name'] ?? null,
            $user->name,
            $organization && $inn !== '' ? 'Организация '.$inn : null,
        ]);

        $values = [
            'name' => $name,
            'full_name' => $profile['organization_full_name'] ?? null,
            'entity_classification_id' => $profile['entity_classification_id'] ?? null,
            'INN' => $inn !== '' ? $inn : null,
            'KPP' => $this->nullableDigits($profile['organization_kpp'] ?? null),
            'OGRN' => $this->nullableDigits($profile['organization_ogrn'] ?? null),
            'legal_address' => $profile['organization_legal_address'] ?? null,
            'dadata_raw' => $profile['organization_dadata_raw'] ?? null,
            'dadata_loaded_at' => filled($profile['organization_dadata_raw'] ?? null)
                ? now()
                : null,
        ];

        if (! $entity->exists) {
            $entity->fill(Arr::where($values, fn ($value) => filled($value)));

            return;
        }

        foreach ($values as $attribute => $value) {
            if (blank($entity->getAttribute($attribute)) && filled($value)) {
                $entity->setAttribute($attribute, $value);
            }
        }
    }

    private function attachUser(Entity $entity, User $user): void
    {
        $existing = $user->entities()
            ->whereKey($entity->id)
            ->first();
        $hasPrimary = $user->entities()
            ->wherePivot('is_primary', true)
            ->exists();

        $user->entities()->syncWithoutDetaching([
            $entity->id => [
                'role' => 'owner',
                'status' => 'active',
                'is_primary' => (bool) ($existing?->pivot?->is_primary) || ! $hasPrimary,
            ],
        ]);
    }

    private function attachEmail(Entity $entity, string $email): void
    {
        if ($email === '') {
            return;
        }

        $emailModel = Email::withTrashed()
            ->whereRaw('LOWER(address) = ?', [$email])
            ->first();

        if (! $emailModel) {
            $emailModel = Email::query()->create([
                'address' => $email,
                'source' => 'site-registration',
                'is_active' => true,
            ]);
        } elseif ($emailModel->trashed()) {
            $emailModel->restore();
        }

        $entity->emails()->syncWithoutDetaching([$emailModel->id]);
    }

    private function firstFilled(array $values): string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return trim((string) $value);
            }
        }

        return 'Клиент сайта';
    }

    private function nullableDigits(?string $value): ?string
    {
        $digits = $this->digits($value);

        return $digits === '' ? null : $digits;
    }

    private function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }
}
