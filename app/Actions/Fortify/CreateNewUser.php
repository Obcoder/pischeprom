<?php

namespace App\Actions\Fortify;

use App\Models\EntityClassification;
use App\Models\User;
use App\Services\Entities\UserEntityResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(
        private readonly UserEntityResolver $entityResolver
    ) {}

    public function create(array $input): User
    {
        $input = $this->normalizeInput($input);

        Validator::make($input, [
            'account_type' => ['required', Rule::in(['individual', 'organization'])],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'password' => $this->passwordRules(),
            'avatar' => ['nullable', 'image', 'max:2048'],
            'personal_data_consent' => ['accepted'],
            'marketing_consent' => ['nullable', 'boolean'],
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature()
                ? ['accepted', 'required']
                : ['nullable'],
            'organization_inn' => [
                Rule::requiredIf(fn () => $input['account_type'] === 'organization'),
                'nullable',
                'string',
                'regex:/^(?:\d{10}|\d{12})$/',
            ],
            'organization_kpp' => ['nullable', 'string', 'max:16'],
            'organization_ogrn' => ['nullable', 'string', 'max:20'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'organization_full_name' => ['nullable', 'string', 'max:1000'],
            'organization_legal_address' => ['nullable', 'string', 'max:2000'],
            'organization_opf' => ['nullable', 'string', 'max:64'],
            'organization_dadata_raw' => ['nullable'],
            'entity_classification_id' => ['nullable', 'integer', 'exists:entity_classifications,id'],
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::query()->create([
                'name' => $input['name'],
                'email' => $input['email'],
                'phone' => $this->entityResolver->normalizePhone($input['phone']),
                'password' => $input['password'],
                'type' => 'customer',
                'status' => 'active',
                'account_type' => $input['account_type'],
                'city_id' => $input['city_id'] ?? null,
                'profile_photo_path' => $this->storeAvatar($input['avatar'] ?? null),
                'personal_data_consent_at' => now(),
                'personal_data_consent_ip' => request()->ip(),
                'marketing_consent_at' => ! empty($input['marketing_consent'])
                    ? now()
                    : null,
            ]);

            if (
                $input['account_type'] === 'organization'
                && empty($input['entity_classification_id'])
            ) {
                $input['entity_classification_id'] = $this->resolveEntityClassificationId(
                    $input['organization_inn'],
                    $input['organization_opf']
                );
            }

            $this->entityResolver->resolve($user, $input);

            return $user;
        });
    }

    private function normalizeInput(array $input): array
    {
        $input['account_type'] = $input['account_type'] ?? 'individual';
        $input['phone'] = $this->nullableString($input['phone'] ?? null);
        $input['city_id'] = filled($input['city_id'] ?? null)
            ? (int) $input['city_id']
            : null;
        $input['organization_inn'] = $this->digits(
            $input['organization_inn'] ?? $input['organization_INN'] ?? ''
        );
        $input['organization_kpp'] = $this->digits(
            $input['organization_kpp'] ?? $input['organization_KPP'] ?? ''
        );
        $input['organization_ogrn'] = $this->digits(
            $input['organization_ogrn'] ?? $input['organization_OGRN'] ?? ''
        );
        $input['organization_name'] = $this->nullableString(
            $input['organization_name'] ?? $input['organization_short_name'] ?? null
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
        $input['organization_dadata_raw'] = $this->normalizeDadataRaw(
            $input['organization_dadata_raw'] ?? null
        );

        return $input;
    }

    private function resolveEntityClassificationId(string $inn, ?string $opf): ?int
    {
        $opf = mb_strtoupper(trim((string) $opf));
        $classificationName = match (true) {
            str_contains($opf, 'ИП'), strlen($inn) === 12 => 'ИП',
            str_contains($opf, 'АО') => 'АО',
            default => 'ООО',
        };

        return EntityClassification::query()
            ->where('name', $classificationName)
            ->value('id');
    }

    private function storeAvatar(mixed $avatar): ?string
    {
        return $avatar instanceof UploadedFile
            ? $avatar->storePublicly('profile-photos', ['disk' => 'public'])
            : null;
    }

    private function normalizeDadataRaw(mixed $raw): mixed
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

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }
}
