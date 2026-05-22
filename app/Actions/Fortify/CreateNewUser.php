<?php

namespace App\Actions\Fortify;

use App\Models\Entity;
use App\Models\EntityClassification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     */
    public function create(array $input): User
    {
        $input = $this->normalizeInput($input);

        Validator::make($input, [
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
                'unique:users,email',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:32',
            ],

            'city_id' => [
                'required',
                'integer',
                'exists:cities,id',
            ],

            'password' => $this->passwordRules(),

            'avatar' => [
                'nullable',
                'image',
                'max:2048',
            ],

            'personal_data_consent' => [
                'accepted',
            ],

            'marketing_consent' => [
                'nullable',
                'boolean',
            ],

            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature()
                ? ['accepted', 'required']
                : ['nullable'],

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
                'nullable',
                'string',
                'max:255',
            ],

            'organization_full_name' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'organization_legal_address' => [
                'nullable',
                'string',
                'max:2000',
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

        return DB::transaction(function () use ($input) {
            $user = $this->createUser($input);

            if ($input['account_type'] === 'organization') {
                $this->createOrAttachEntityForUser($user, $input);
            }

            return $user;
        });
    }

    protected function createUser(array $input): User
    {
        $profilePhotoPath = $this->storeAvatar($input['avatar'] ?? null);

        $payload = [
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'] ?? null,

            /**
             * Пароль в User.php уже кастуется как hashed.
             * Если у тебя в User.php есть:
             * 'password' => 'hashed',
             * то Hash::make здесь не нужен.
             */
            'password' => $input['password'],

            /**
             * type — системный тип пользователя.
             * customer — клиент сайта.
             * employee — сотрудник CRM/ERP.
             */
            'type' => 'customer',

            'status' => 'active',

            /**
             * account_type — тип покупателя.
             * individual — физлицо.
             * organization — организация / ИП / ООО / АО.
             */
            'account_type' => $input['account_type'],

            'city_id' => $input['city_id'],

            'profile_photo_path' => $profilePhotoPath,

            'personal_data_consent_at' => now(),
            'personal_data_consent_ip' => request()->ip(),

            'marketing_consent_at' => ! empty($input['marketing_consent'])
                ? now()
                : null,
        ];

        $user = new User();

        $user->forceFill(
            $this->onlyExistingColumns('users', $payload)
        );

        $user->save();

        return $user;
    }

    protected function createOrAttachEntityForUser(User $user, array $input): void
    {
        $inn = $this->digits($input['organization_inn'] ?? '');

        if ($inn === '') {
            return;
        }

        $classificationId = $input['entity_classification_id']
            ?: $this->resolveEntityClassificationId(
                inn: $inn,
                opf: $input['organization_opf'] ?? null,
            );

        $entityName = $this->firstFilled([
                                             $input['organization_name'] ?? null,
                                             $input['organization_full_name'] ?? null,
                                             'Организация ' . $inn,
                                         ]);

        $payload = [
            'name' => $entityName,

            'full_name' => $input['organization_full_name'] ?? null,

            'entity_classification_id' => $classificationId,

            /**
             * В твоей модели Entity уже используются INN и OGRN.
             * KPP лучше добавить отдельной миграцией.
             */
            'INN' => $inn,
            'KPP' => $input['organization_kpp'] ?? null,
            'OGRN' => $input['organization_ogrn'] ?? null,

            'legal_address' => $input['organization_legal_address'] ?? null,

            'dadata_raw' => $this->normalizeDadataRaw($input['organization_dadata_raw'] ?? null),
            'dadata_loaded_at' => ! empty($input['organization_dadata_raw'])
                ? now()
                : null,
        ];

        $entity = Entity::query()
            ->where('INN', $inn)
            ->first();

        if (! $entity) {
            $entity = new Entity();
        }

        /**
         * Если организация уже есть, не затираем хорошее существующее name пустым значением.
         */
        $payload = array_filter(
            $payload,
            fn ($value) => ! is_null($value) && $value !== ''
        );

        $entity->forceFill(
            $this->onlyExistingColumns('entities', $payload)
        );

        $entity->save();

        /**
         * Привязываем город к Entity через существующую связь cities().
         * У тебя уже есть pivot city_entity.
         */
        if (! empty($input['city_id']) && method_exists($entity, 'cities')) {
            $entity->cities()->syncWithoutDetaching([
                                                        (int) $input['city_id'],
                                                    ]);
        }

        /**
         * Привязываем пользователя к Entity через новую таблицу entity_user.
         * Для этого в User.php нужна связь entities().
         */
        if (method_exists($user, 'entities')) {
            $user->entities()->syncWithoutDetaching([
                                                        $entity->id => [
                                                            'role' => 'owner',
                                                            'status' => 'active',
                                                            'is_primary' => true,
                                                        ],
                                                    ]);
        }
    }

    protected function storeAvatar(mixed $avatar): ?string
    {
        if (! $avatar instanceof UploadedFile) {
            return null;
        }

        return $avatar->storePublicly('profile-photos', [
            'disk' => 'public',
        ]);
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

        /**
         * ИП обычно имеет ИНН из 12 цифр.
         * Юрлица обычно имеют ИНН из 10 цифр.
         */
        if (mb_strlen($inn) === 12) {
            return 'ИП';
        }

        return 'ООО';
    }

    protected function normalizeInput(array $input): array
    {
        /**
         * Поддерживаем оба варианта имён:
         * organization_INN / organization_inn.
         * Во Vue лучше использовать snake_case:
         * organization_inn, organization_kpp, organization_ogrn.
         */
        $input['account_type'] = $input['account_type'] ?? 'individual';

        $input['phone'] = $this->nullableString($input['phone'] ?? null);

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
            ->filter(function ($value, string $column) use ($table) {
                return Schema::hasColumn($table, $column);
            })
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
