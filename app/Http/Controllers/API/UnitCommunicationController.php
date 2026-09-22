<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Models\Entity;
use App\Models\Telephone;
use App\Models\Unit;
use App\Models\Uri;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UnitCommunicationController extends Controller
{
    private const TYPES = [
        'uris' => Uri::class,
        'telephones' => Telephone::class,
        'emails' => Email::class,
    ];

    public function index(Unit $unit): JsonResponse
    {
        $unit->load(['uris', 'telephones', 'emails', 'entities.uris', 'entities.telephones', 'entities.emails']);
        $data = [];

        foreach (array_keys(self::TYPES) as $type) {
            $contacts = [];

            foreach (collect([$unit])->concat($unit->entities) as $owner) {
                foreach ($owner->{$type} as $contact) {
                    $contacts[$contact->id] ??= [...$this->serialize($contact), 'owners' => []];
                    $contacts[$contact->id]['owners'][] = [
                        'type' => $owner instanceof Unit ? 'unit' : 'entity',
                        'id' => $owner->id,
                        'name' => $owner->name,
                    ];
                }
            }

            $data[$type] = array_values($contacts);
        }

        return response()->json(['data' => $data]);
    }

    public function options(Request $request, Unit $unit, string $type): JsonResponse
    {
        $model = $this->model($type);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $search = trim($data['search'] ?? '');
        $field = $type === 'telephones' ? 'number' : 'address';
        $contacts = $model::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search, $field, $type): void {
                $query->where($field, 'like', "%{$search}%");

                if ($type === 'emails') {
                    $query->orWhere('name', 'like', "%{$search}%");
                }
            }))
            ->orderBy($field)
            ->limit(25)
            ->get()
            ->map(fn (Model $contact): array => $this->serialize($contact));

        return response()->json(['data' => $contacts]);
    }

    public function store(Request $request, Unit $unit, string $type): JsonResponse
    {
        $model = $this->model($type);
        $owner = $this->owner($request, $unit);
        $data = $this->validateContact($request, $type);

        [$contact, $attached] = DB::transaction(function () use ($model, $type, $owner, $data): array {
            if (! empty($data['contact_id'])) {
                $contact = $model::query()->lockForUpdate()->findOrFail($data['contact_id']);
            } else {
                $field = $type === 'telephones' ? 'number' : 'address';
                $query = $type === 'emails' ? $model::withTrashed() : $model::query();
                $contact = $query->where($field, $data[$field])->lockForUpdate()->first();

                // Older telephone records may be stored without the country prefix's plus sign.
                if (! $contact && $type === 'telephones') {
                    $contact = Telephone::query()
                        ->whereIn('number', PhoneNumber::russianStorageVariants($data[$field]))
                        ->lockForUpdate()->first();
                }

                if (! $contact) {
                    $attributes = [$field => $data[$field]];

                    if ($type === 'emails') {
                        $attributes += ['name' => $data['name'] ?? null, 'source' => 'unit_manual', 'is_active' => true];
                    }

                    $contact = $model::query()->create($attributes);
                } elseif ($contact instanceof Email && $contact->trashed()) {
                    $contact->restore();
                    $contact->update(['is_active' => true]);
                }
            }

            $changes = $owner->{$type}()->syncWithoutDetaching([$contact->id]);

            return [$contact, $changes['attached'] !== []];
        });

        return response()->json([
            'data' => $this->serialize($contact),
            'created' => $contact->wasRecentlyCreated,
            'attached' => $attached,
        ], $contact->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, Unit $unit, string $type, int $contact): JsonResponse
    {
        $this->model($type);
        $owner = $this->owner($request, $unit);
        $record = $owner->{$type}()->whereKey($contact)->firstOrFail();
        $data = $this->validateContact($request, $type, $record);
        $record->update($data);

        return response()->json(['data' => $this->serialize($record)]);
    }

    public function destroy(Request $request, Unit $unit, string $type, int $contact): JsonResponse
    {
        $this->model($type);
        $owner = $this->owner($request, $unit);
        $request->validate(['delete_record' => ['nullable', 'boolean']]);

        DB::transaction(function () use ($request, $type, $owner, $contact): void {
            $record = $owner->{$type}()->whereKey($contact)->lockForUpdate()->firstOrFail();

            if ($request->boolean('delete_record')) {
                $owners = $record->units()->count() + $record->entities()->count();

                if ($owners > 1) {
                    throw ValidationException::withMessages([
                        'delete_record' => 'Контакт используется другими владельцами. Можно удалить только выбранную связь.',
                    ]);
                }

                if (! $record instanceof Email && DB::table('unit_contact_context_links')
                    ->where($record instanceof Telephone ? 'telephone_id' : 'uri_id', $record->id)->exists()) {
                    throw ValidationException::withMessages([
                        'delete_record' => 'Контакт используется в AI Sales. Можно удалить только выбранную связь.',
                    ]);
                }

                if ($record instanceof Telephone && (
                    $record->phoneCalls()->exists()
                    || $record->leads()->exists()
                    || DB::table('orders')->where('contact_telephone_id', $record->id)->exists()
                )) {
                    throw ValidationException::withMessages([
                        'delete_record' => 'Телефон связан со звонками, лидами или заказами. Удалите связь, чтобы сохранить историю.',
                    ]);
                }

                $owner->{$type}()->detach($record->id);
                $record->delete();
            } else {
                $owner->{$type}()->detach($record->id);
            }
        });

        return response()->json(['message' => $request->boolean('delete_record') ? 'Контакт удалён.' : 'Связь удалена.']);
    }

    private function model(string $type): string
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }

    private function owner(Request $request, Unit $unit): Unit|Entity
    {
        $data = $request->validate(['entity_id' => ['nullable', 'integer', 'min:1']]);

        return ! empty($data['entity_id'])
            ? $unit->entities()->whereKey($data['entity_id'])->firstOrFail()
            : $unit;
    }

    private function validateContact(Request $request, string $type, ?Model $record = null): array
    {
        $field = $type === 'telephones' ? 'number' : 'address';
        $value = $request->input($field);

        if (is_string($value)) {
            $value = trim($value);

            if ($type === 'emails') {
                $value = Str::lower($value);
            } elseif ($type === 'telephones' && preg_match('/^[+\d\s().-]+$/', $value)) {
                $digits = preg_replace('/\D/', '', $value);
                $value = PhoneNumber::russian($value) ?? '+'.$digits;
            } elseif ($type === 'uris' && $value !== '' && ! preg_match('/^[a-z][a-z\d+.-]*:/i', $value)) {
                $value = 'https://'.$value;
            }

            $request->merge([$field => $value]);
        }

        $rules = [
            $field => [
                'bail',
                $record ? 'required' : 'required_without:contact_id',
                'nullable',
                'string',
                $type === 'telephones' ? 'max:16' : 'max:255',
                ...match ($type) {
                    'emails' => ['email:rfc'],
                    'uris' => ['url:http,https'],
                    'telephones' => ['regex:/^\+[0-9]{7,15}$/'],
                },
            ],
        ];

        if ($record) {
            $rules[$field][] = Rule::unique($type, $field)->ignore($record->id);

            if ($type === 'telephones') {
                $duplicate = Telephone::query()->whereKeyNot($record->id)
                    ->whereIn('number', PhoneNumber::russianStorageVariants($value))->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages(['number' => 'Этот телефон уже есть в базе.']);
                }
            }
        } else {
            $rules['contact_id'] = [
                'nullable', 'integer', 'required_without:'.$field,
                Rule::prohibitedIf(fn () => $request->filled($field)),
                $type === 'emails'
                    ? Rule::exists($type, 'id')->whereNull('deleted_at')
                    : Rule::exists($type, 'id'),
            ];
        }

        if ($type === 'emails') {
            $rules['name'] = ['nullable', 'string', 'max:255'];
        }

        return $request->validate($rules);
    }

    private function serialize(Model $contact): array
    {
        return $contact instanceof Telephone
            ? $contact->only(['id', 'number'])
            : $contact->only($contact instanceof Email ? ['id', 'address', 'name', 'is_active'] : ['id', 'address']);
    }
}
