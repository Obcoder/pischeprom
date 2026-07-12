<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\EntityClassification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CustomerProfileController extends Controller
{
    public function edit(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if (method_exists($user, 'city')) {
            $user->loadMissing('city.region');
        }

        $entity = method_exists($user, 'entities')
            ? $this->primaryEntity($user)
            : null;

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
                'max_chat_id' => $user->max_chat_id ?? null,
                'delivery_address' => $user->delivery_address ?? null,
                'account_type' => $user->account_type ?? 'individual',
                'profile_photo_url' => $user->profile_photo_url ?? null,
                'city' => $user->relationLoaded('city') && $user->city
                    ? [
                        'id' => $user->city->id,
                        'name' => $user->city->name,
                        'region' => $user->city->region?->name,
                        'label' => trim($user->city->name . ($user->city->region ? ', ' . $user->city->region->name : '')),
                    ]
                    : null,
            ],

            'entity' => $entity
                ? [
                    'id' => $entity->id,
                    'name' => $entity->name,
                    'full_name' => $entity->full_name ?? null,
                    'entity_classification_id' => $entity->entity_classification_id,
                    'entity_classification_name' => $entity->classification?->name,
                    'INN' => $entity->INN ?? null,
                    'KPP' => $entity->KPP ?? null,
                    'OGRN' => $entity->OGRN ?? null,
                    'legal_address' => $entity->legal_address ?? null,
                    'cities' => method_exists($entity, 'cities')
                        ? $entity->cities->map(fn ($city) => [
                            'id' => $city->id,
                            'name' => $city->name,
                        ])->values()
                        : [],
                ]
                : null,

            'entityClassifications' => EntityClassification::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $input = $this->normalizeInput($request->all());

        $validated = validator($input, [
            'account_type' => [
                'required',
                Rule::in(['individual', 'organization']),
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

            'phone' => [
                'nullable',
                'string',
                'max:32',
            ],

            'max_chat_id' => [
                'nullable',
                'string',
                'max:64',
            ],

            'delivery_address' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'city_id' => [
                'required',
                'integer',
                'exists:cities,id',
            ],

            'avatar' => [
                'nullable',
                'image',
                'max:4096',
            ],

            'organization_inn' => [
                Rule::requiredIf(fn () => $input['account_type'] === 'organization'),
                'nullable',
                'string',
                'regex:/^(?:\d{10}|\d{12})$/',
            ],

            'organization_kpp' => [
                'nullable',
                'string',
                'max:16',
            ],

            'organization_ogrn' => [
                'nullable',
                'string',
                'max:20',
            ],

            'organization_name' => [
                Rule::requiredIf(fn () => $input['account_type'] === 'organization'),
                'nullable',
                'string',
                'max:255',
            ],

            'organization_full_name' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'organization_legal_address' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'organization_opf' => [
                'nullable',
                'string',
                'max:64',
            ],

            'organization_dadata_raw' => [
                'nullable',
            ],

            'entity_classification_id' => [
                'nullable',
                'integer',
                'exists:entity_classifications,id',
            ],
        ])->validate();

        DB::transaction(function () use ($request, $user, $validated) {
            $this->updateUserProfile($request, $user, $validated);

            if ($validated['account_type'] === 'organization') {
                $this->createOrUpdatePrimaryEntity($user, $validated);
            } else {
                $this->deactivateUserEntities($user);
            }
        });

        return redirect()
            ->route('dashboard')
            ->with('success', 'Профиль обновлён.');
    }

    protected function updateUserProfile(Request $request, User $user, array $data): void
    {
        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'max_chat_id' => $data['max_chat_id'] ?? null,
            'delivery_address' => $data['delivery_address'] ?? null,
            'account_type' => $data['account_type'],
            'city_id' => $data['city_id'],
        ];

        if (
            Schema::hasColumn('users', 'email_verified_at')
            && $user->email !== $data['email']
        ) {
            $payload['email_verified_at'] = null;
        }

        $user->forceFill(
            $this->onlyExistingColumns('users', $payload)
        );

        $user->save();

        if ($request->hasFile('avatar')) {
            $avatar = $request->file('avatar');

            if ($avatar instanceof UploadedFile && method_exists($user, 'updateProfilePhoto')) {
                $user->updateProfilePhoto($avatar);
            } elseif ($avatar instanceof UploadedFile && Schema::hasColumn('users', 'profile_photo_path')) {
                $user->forceFill([
                                     'profile_photo_path' => $avatar->storePublicly('profile-photos', [
                                         'disk' => 'public',
                                     ]),
                                 ])->save();
            }
        }
    }

    protected function createOrUpdatePrimaryEntity(User $user, array $data): void
    {
        $inn = $this->digits($data['organization_inn'] ?? '');

        if ($inn === '') {
            return;
        }

        $classificationId = $data['entity_classification_id']
            ?: $this->resolveEntityClassificationId(
                inn: $inn,
                opf: $data['organization_opf'] ?? null,
            );

        $entityName = $this->firstFilled([
                                             $data['organization_name'] ?? null,
                                             $data['organization_full_name'] ?? null,
                                             'Организация ' . $inn,
                                         ]);

        $payload = [
            'name' => $entityName,
            'full_name' => $data['organization_full_name'] ?? null,
            'entity_classification_id' => $classificationId,
            'INN' => $inn,
            'KPP' => $data['organization_kpp'] ?? null,
            'OGRN' => $data['organization_ogrn'] ?? null,
            'legal_address' => $data['organization_legal_address'] ?? null,
            'dadata_raw' => $this->normalizeDadataRaw($data['organization_dadata_raw'] ?? null),
            'dadata_loaded_at' => ! empty($data['organization_dadata_raw']) ? now() : null,
        ];

        $entity = Entity::query()
            ->where('INN', $inn)
            ->first();

        if (! $entity) {
            $entity = new Entity();
        }

        $entity->forceFill(
            $this->onlyExistingColumns('entities', $this->withoutEmptyValues($payload))
        );

        $entity->save();

        if (! empty($data['city_id']) && method_exists($entity, 'cities')) {
            $entity->cities()->syncWithoutDetaching([
                                                        (int) $data['city_id'],
                                                    ]);
        }

        $this->attachUserToEntity($user, $entity);
    }

    protected function attachUserToEntity(User $user, Entity $entity): void
    {
        if (! method_exists($user, 'entities') || ! Schema::hasTable('entity_user')) {
            return;
        }

        $inactivePayload = $this->onlyExistingColumns('entity_user', [
            'is_primary' => false,
            'status' => 'inactive',
        ]);

        if (! empty($inactivePayload)) {
            $user->entities()
                ->newPivotStatement()
                ->where('user_id', $user->id)
                ->where('entity_id', '!=', $entity->id)
                ->update($inactivePayload);
        }

        $attachPayload = $this->onlyExistingColumns('entity_user', [
            'role' => 'owner',
            'status' => 'active',
            'is_primary' => true,
        ]);

        $user->entities()->syncWithoutDetaching([
                                                    $entity->id => $attachPayload,
                                                ]);
    }

    protected function deactivateUserEntities(User $user): void
    {
        if (! method_exists($user, 'entities') || ! Schema::hasTable('entity_user')) {
            return;
        }

        $payload = $this->onlyExistingColumns('entity_user', [
            'is_primary' => false,
            'status' => 'inactive',
        ]);

        if (empty($payload)) {
            return;
        }

        $user->entities()
            ->newPivotStatement()
            ->where('user_id', $user->id)
            ->update($payload);
    }

    protected function primaryEntity(User $user): ?Entity
    {
        if (! method_exists($user, 'entities') || ! Schema::hasTable('entity_user')) {
            return null;
        }

        $query = $user->entities()
            ->with(['classification', 'cities']);

        if (Schema::hasColumn('entity_user', 'is_primary')) {
            $primary = (clone $query)
                ->wherePivot('is_primary', true)
                ->first();

            if ($primary) {
                return $primary;
            }
        }

        return $query->first();
    }

    protected function resolveEntityClassificationId(string $inn, ?string $opf = null): ?int
    {
        $classificationName = $this->detectEntityClassificationName($inn, $opf);

        return EntityClassification::query()
            ->where('name', $classificationName)
            ->value('id');
    }

    protected function detectEntityClassificationName(string $inn, ?string $opf = null): string
    {
        $opf = mb_strtoupper(trim((string) $opf));

        if ($opf !== '') {
            if (str_contains($opf, 'ИП')) {
                return 'ИП';
            }

            if (str_contains($opf, 'АО')) {
                return 'АО';
            }

            if (str_contains($opf, 'ООО')) {
                return 'ООО';
            }
        }

        return mb_strlen($inn) === 12 ? 'ИП' : 'ООО';
    }

    protected function normalizeInput(array $input): array
    {
        $input['account_type'] = $input['account_type'] ?? 'individual';

        $input['phone'] = $this->nullableString($input['phone'] ?? null);
        $input['max_chat_id'] = $this->nullableString($input['max_chat_id'] ?? null);
        $input['delivery_address'] = $this->nullableString($input['delivery_address'] ?? null);

        $input['organization_inn'] = $this->digits(
            $input['organization_inn']
            ?? $input['organization_INN']
            ?? ''
        );

        $input['organization_kpp'] = $this->digits(
            $input['organization_kpp']
            ?? $input['organization_KPP']
            ?? ''
        );

        $input['organization_ogrn'] = $this->digits(
            $input['organization_ogrn']
            ?? $input['organization_OGRN']
            ?? ''
        );

        $input['organization_name'] = $this->nullableString(
            $input['organization_name']
            ?? $input['organization_short_name']
            ?? null
        );

        $input['organization_full_name'] = $this->nullableString(
            $input['organization_full_name'] ?? null
        );

        $input['organization_legal_address'] = $this->nullableString(
            $input['organization_legal_address'] ?? null
        );

        $input['organization_opf'] = $this->nullableString(
            $input['organization_opf'] ?? null
        );

        return $input;
    }

    protected function normalizeDadataRaw(mixed $raw): mixed
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return json_last_error() === JSON_ERROR_NONE
                ? $decoded
                : ['raw' => $raw];
        }

        return null;
    }

    protected function onlyExistingColumns(string $table, array $payload): array
    {
        return collect($payload)
            ->filter(fn ($value, string $column) => Schema::hasColumn($table, $column))
            ->all();
    }

    protected function withoutEmptyValues(array $payload): array
    {
        return collect($payload)
            ->filter(fn ($value) => ! is_null($value) && $value !== '')
            ->all();
    }

    protected function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === ''
            ? null
            : $value;
    }

    protected function firstFilled(array $values): string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return 'Без названия';
    }
}
