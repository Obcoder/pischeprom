<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CountryController extends Controller
{
    private const CODE_ISO = 'сodeISO';
    private const CODE_TELEFON = 'сodeTelefon';
    private const FLAG_ROOT = 'countries/flags';

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $hasFlag = $request->input('has_flag');
        $sortBy = $request->input('sort_by', 'name');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortColumns = [
            'id' => 'id',
            'name' => 'name',
            'codeISO' => self::CODE_ISO,
            'codeTelefon' => self::CODE_TELEFON,
            'population' => 'population',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];

        $countries = Country::query()
            ->withCount(['regions', 'goods', 'entities'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere(self::CODE_ISO, 'like', "%{$search}%")
                        ->orWhere(self::CODE_TELEFON, 'like', "%{$search}%")
                        ->orWhere('population', 'like', "%{$search}%");
                });
            })
            ->when($hasFlag === 'yes', fn ($query) => $query->whereNotNull('flag')->where('flag', '<>', ''))
            ->when($hasFlag === 'no', fn ($query) => $query->where(fn ($q) => $q->whereNull('flag')->orWhere('flag', '')))
            ->orderBy($sortColumns[$sortBy] ?? 'name', $sortDir)
            ->orderBy('id')
            ->get()
            ->map(fn (Country $country) => $this->countryPayload($country))
            ->values();

        return response()->json($countries);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        $country = Country::query()->create([
            'name' => $data['name'],
            self::CODE_ISO => $data['codeISO'],
            self::CODE_TELEFON => $data['codeTelefon'] ?? null,
            'population' => $data['population'] ?? null,
            'flag' => $data['flag_url'] ?? null,
        ]);

        if ($request->hasFile('flag_file')) {
            $country->forceFill([
                'flag' => $this->storeFlagFile($country, $request->file('flag_file')),
            ])->save();
        }

        return response()->json($this->countryPayload($country->fresh()->loadCount(['regions', 'goods', 'entities'])), 201);
    }

    public function show(Country $country): JsonResponse
    {
        return response()->json($this->countryPayload($country->loadCount(['regions', 'goods', 'entities'])));
    }

    public function update(Request $request, Country $country): JsonResponse
    {
        $data = $this->validatedData($request, $country);
        $oldFlag = $country->flag;

        $country->fill([
            'name' => $data['name'],
            self::CODE_ISO => $data['codeISO'],
            self::CODE_TELEFON => $data['codeTelefon'] ?? null,
            'population' => $data['population'] ?? null,
            'flag' => $request->boolean('remove_flag') ? null : ($data['flag_url'] ?? $country->flag),
        ]);

        if ($request->hasFile('flag_file')) {
            $country->flag = $this->storeFlagFile($country, $request->file('flag_file'));
        }

        $country->save();

        if ($oldFlag && $oldFlag !== $country->flag) {
            $this->deleteStoredFlag($oldFlag);
        }

        return response()->json($this->countryPayload($country->fresh()->loadCount(['regions', 'goods', 'entities'])));
    }

    public function destroy(Country $country): JsonResponse
    {
        $country->loadCount(['regions', 'goods', 'entities']);

        if ($country->regions_count || $country->goods_count || $country->entities_count) {
            return response()->json([
                'message' => 'Страна связана с регионами, товарами или Entity. Удаление заблокировано, чтобы не удалить связанные данные каскадом.',
                'relations' => [
                    'regions' => $country->regions_count,
                    'goods' => $country->goods_count,
                    'entities' => $country->entities_count,
                ],
            ], 422);
        }

        $flag = $country->flag;
        $country->delete();
        $this->deleteStoredFlag($flag);

        return response()->json(['message' => 'Country deleted']);
    }

    private function validatedData(Request $request, ?Country $country = null): array
    {
        $request->merge([
            'codeISO' => $request->input('codeISO', $request->input(self::CODE_ISO)),
            'codeTelefon' => $request->input('codeTelefon', $request->input(self::CODE_TELEFON)),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'codeISO' => [
                'required',
                'string',
                'size:2',
                Rule::unique('countries', self::CODE_ISO)->ignore($country?->id),
            ],
            'codeTelefon' => ['nullable', 'string', 'max:12'],
            'population' => ['nullable', 'integer', 'min:0'],
            'flag_url' => ['nullable', 'string', 'max:2048'],
            'flag_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:5120'],
            'remove_flag' => ['nullable', 'boolean'],
        ]);

        $data['codeISO'] = strtoupper($data['codeISO']);
        $data['codeTelefon'] = isset($data['codeTelefon']) ? trim((string) $data['codeTelefon']) : null;
        $data['population'] = isset($data['population']) ? (int) $data['population'] : null;

        return $data;
    }

    private function countryPayload(Country $country): array
    {
        return [
            'id' => $country->id,
            'name' => $country->name,
            'flag' => $country->flag,
            self::CODE_ISO => $country->{self::CODE_ISO},
            self::CODE_TELEFON => $country->{self::CODE_TELEFON},
            'codeISO' => $country->{self::CODE_ISO},
            'codeTelefon' => $country->{self::CODE_TELEFON},
            'population' => $country->population,
            'regions_count' => $country->regions_count ?? null,
            'goods_count' => $country->goods_count ?? null,
            'entities_count' => $country->entities_count ?? null,
            'created_at' => $country->created_at?->toISOString(),
            'updated_at' => $country->updated_at?->toISOString(),
        ];
    }

    private function storeFlagFile(Country $country, $file): string
    {
        $disk = Storage::disk('yandex');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        $name = Str::slug($country->name) ?: 'country';
        $path = sprintf(
            '%s/%d/%s-%s.%s',
            self::FLAG_ROOT,
            $country->id,
            $name,
            now()->format('YmdHis') . '-' . Str::random(6),
            $extension
        );

        $disk->put($path, file_get_contents($file->getRealPath()), [
            'visibility' => 'public',
            'ContentType' => $file->getMimeType() ?: $file->getClientMimeType(),
        ]);

        return $disk->url($path);
    }

    private function deleteStoredFlag(?string $url): void
    {
        if (! $url) {
            return;
        }

        $disk = Storage::disk('yandex');
        $baseUrl = rtrim((string) config('filesystems.disks.yandex.url'), '/');
        $path = null;

        if ($baseUrl && Str::startsWith($url, $baseUrl . '/')) {
            $path = Str::after($url, $baseUrl . '/');
        } else {
            $bucket = config('filesystems.disks.yandex.bucket');
            $fallbackPrefix = $bucket ? "https://storage.yandexcloud.net/{$bucket}/" : null;

            if ($fallbackPrefix && Str::startsWith($url, $fallbackPrefix)) {
                $path = Str::after($url, $fallbackPrefix);
            }
        }

        if ($path && Str::startsWith($path, self::FLAG_ROOT . '/')) {
            $disk->delete($path);
        }
    }
}
