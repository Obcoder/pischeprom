<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Good;
use App\Models\VatRate;
use App\Services\Goods\GoodAvatarImages;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GoodController extends Controller
{
    public function index(Request $request)
    {
        $tableView = $request->input('view') === 'table';
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, (int) $request->input('per_page', $tableView ? 50 : 9999));
        if ($tableView) {
            $perPage = min($perPage, 100);
        }
        $search = trim((string) $request->input('search', ''));
        $isPublished = $request->input('is_published');
        $sortBy = (string) $request->input('sort_by', 'name');
        $sortDesc = filter_var($request->input('sort_desc', false), FILTER_VALIDATE_BOOLEAN);

        $allowedSorts = [
            'name',
            'is_published',
            'created_at',
        ];

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'name';
        }

        $query = Good::query()->with($tableView ? [
            'products' => fn ($query) => $query->without('manufacturers')
                ->select('products.id', 'products.rus', 'products.category_id'),
            'products.category:id,name',
            'country:id,name,flag',
            'vatRate',
            'fields:id,title',
        ] : [
            'products.category',
            'country:id,name,flag',
            'vatRate',
            'seo',
            'media',
            'entityClassifications',
            'industries',
            'fields',
            'priceTypeValues.priceType.currency',
            'priceTypeValues.currency',
        ]);

        if ($search !== '') {
            $like = '%'.strtr(mb_strtolower($search, 'UTF-8'), [
                '!' => '!!',
                '%' => '!%',
                '_' => '!_',
            ]).'%';

            $query->where(function ($q) use ($like) {
                $q->whereRaw("LOWER(goods.name) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereHas('products.category', function ($categoryQuery) use ($like) {
                        $categoryQuery->whereRaw("LOWER(categories.name) LIKE ? ESCAPE '!'", [$like]);
                    });
            });
        }

        if (!is_null($isPublished) && $isPublished !== '') {
            $query->where('is_published', filter_var($isPublished, FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');

        $paginator = $query->paginate(
            perPage: $perPage,
            columns: ['*'],
            pageName: 'page',
            page: $page
        );

        if ($tableView) {
            $images = app(GoodAvatarImages::class);
            $paginator->getCollection()->each(function (Good $good) use ($images): void {
                $good->setAttribute('avatar_url', $images->url($good));
            });
        }

        return response()->json([
                                    'data' => $paginator->items(),
                                    'total' => $paginator->total(),
                                    'page' => $paginator->currentPage(),
                                    'per_page' => $paginator->perPage(),
                                    'last_page' => $paginator->lastPage(),
                                ]);
    }

    public function indexPublished()
    {
        return Good::query()
            ->with([
                       'vatRate',
                       'country:id,name,flag',
                       'products.category',
                       'priceTypeValues.priceType.currency',
                       'priceTypeValues.currency',
                       'industries',
                       'fields',
                   ])
            ->published(true)
            ->inRandomOrder()
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
                                            'name' => ['required', 'string', 'max:255'],
                                            'slug' => ['nullable', 'string', 'max:255', 'unique:goods,slug'],
                                            'denominator' => ['nullable', 'numeric'],
                                            'description' => ['nullable', 'string'],
                                            'vat_rate_id' => ['nullable', 'integer', 'exists:vat_rates,id'],
                                            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
                                            'is_published' => ['nullable', 'boolean'],

                                            'ava_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],

                                            'products' => ['nullable', 'array'],
                                            'products.*' => ['integer', 'exists:products,id'],
                                            'industry_ids' => ['nullable', 'array'],
                                            'industry_ids.*' => ['integer', 'exists:industries,id'],
                                            'fields' => ['nullable', 'array'],
                                            'fields.*' => ['integer', 'exists:fields,id'],
                                            'entity_classification_ids' => ['nullable', 'array'],
                                            'entity_classification_ids.*' => ['integer', 'exists:entity_classifications,id'],
                                        ]);

        $good = Good::create([
                                 'name' => $validated['name'],
                                 'slug' => $validated['slug'] ?? null,
                                 'denominator' => $validated['denominator'] ?? null,
                                 'description' => $validated['description'] ?? null,
                                 'vat_rate_id' => $validated['vat_rate_id'] ?? null,
                                 'country_id' => $validated['country_id'] ?? null,
                                 'is_published' => $validated['is_published'] ?? true,
                             ]);

        if (array_key_exists('products', $validated)) {
            $good->products()->sync($validated['products'] ?? []);
        }

        if (array_key_exists('industry_ids', $validated)) {
            $good->industries()->sync($validated['industry_ids'] ?? []);
        }

        if (array_key_exists('fields', $validated)) {
            $good->fields()->sync($validated['fields'] ?? []);
        }

        if (array_key_exists('entity_classification_ids', $validated)) {
            $good->entityClassifications()->sync($validated['entity_classification_ids'] ?? []);
        }

        if ($request->hasFile('ava_image')) {
            $this->storeAvatarFiles($good, $request->file('ava_image'));
        }

        $this->syncGoodJson($good);

        return response()->json(
            $good->fresh([
                             'products.category',
                             'country:id,name,flag',
                             'vatRate',
                             'seo',
                             'media',
                             'entityClassifications',
                             'industries',
                             'fields',
                         ]),
            201
        );
    }

    public function show(string $id, $slug = null)
    {
        $good = Good::with([
                               'products.category',
                               'country:id,name,flag',

                               'quotations.unit',
                               'quotations.measure',

                               'purchases.entity',

                               'sales.entity',

                               'vatRate',

                               'media.folder',
                               'mediaFolders',

                               'seo',

                               'priceFormulas.priceType',

                               'priceCalculations.currency',
                               'priceCalculations.priceType',
                               'priceCalculations.quotation.unit',
                               'priceCalculations.quotation.measure',
                               'priceCalculations.purchase.entity',

                               'priceTypeValues.priceType',
                               'priceTypeValues.currency',
                               'entityClassifications',
                               'industries',
                               'fields',
                               'industries.units' => function ($query): void {
                                   $query
                                       ->without(['fields', 'labels', 'telephones', 'uris'])
                                       ->select('units.id', 'units.name');
                               },
                               'industries.units.entities' => function ($query): void {
                                   $query->select('entities.id', 'entities.name', 'entities.full_name');
                               },
                           ])->findOrFail($id);

        $expectedSlug = $good->slug ?: Str::slug($good->name);

        if ($slug && $slug !== $expectedSlug) {
            return redirect()->route('good.fetch', [
                'id' => $good->id,
                'slug' => $expectedSlug,
            ], 301);
        }

        return response()->json($good);
    }

    public function update(Request $request, Good $good)
    {
        $validated = $request->validate([
                                            'name' => ['sometimes', 'required', 'string', 'max:255'],
                                            'slug' => [
                                                'nullable',
                                                'string',
                                                'max:255',
                                                Rule::unique('goods', 'slug')->ignore($good->id),
                                            ],
                                            'denominator' => ['nullable', 'numeric'],
                                            'description' => ['nullable', 'string'],
                                            'vat_rate_id' => ['nullable', 'integer', 'exists:vat_rates,id'],
                                            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
                                            'is_published' => ['nullable', 'boolean'],

                                            'ava_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],

                                            'products' => ['nullable', 'array'],
                                            'products.*' => ['integer', 'exists:products,id'],
                                            'industry_ids' => ['nullable', 'array'],
                                            'industry_ids.*' => ['integer', 'exists:industries,id'],
                                            'fields' => ['nullable', 'array'],
                                            'fields.*' => ['integer', 'exists:fields,id'],
                                            'entity_classification_ids' => ['nullable', 'array'],
                                            'entity_classification_ids.*' => ['integer', 'exists:entity_classifications,id'],

                                            'remove_ava' => ['nullable', 'boolean'],
                                        ]);

        $dataToUpdate = collect($validated)
            ->except([
                         'ava_image',
                         'products',
                         'industry_ids',
                         'fields',
                         'entity_classification_ids',
                         'remove_ava',
                     ])
            ->toArray();

        $good->update($dataToUpdate);

        if (array_key_exists('products', $validated)) {
            $good->products()->sync($validated['products'] ?? []);
        }

        if (array_key_exists('industry_ids', $validated)) {
            $good->industries()->sync($validated['industry_ids'] ?? []);
        }

        if (array_key_exists('fields', $validated)) {
            $good->fields()->sync($validated['fields'] ?? []);
        }

        if (array_key_exists('entity_classification_ids', $validated)) {
            $good->entityClassifications()->sync($validated['entity_classification_ids'] ?? []);
        }

        if (!empty($validated['remove_ava'])) {
            $this->deleteAvatarFiles($good);

            $good->update([
                              'ava_image' => null,
                              'ava_thumb' => null,
                          ]);
        }

        if ($request->hasFile('ava_image')) {
            $this->deleteAvatarFiles($good);
            $this->storeAvatarFiles($good, $request->file('ava_image'));
        }

        $this->syncGoodJson($good);

        return response()->json(
            $good->fresh([
                             'products.category',
                             'country:id,name,flag',
                             'vatRate',
                             'seo',
                             'media',
                             'entityClassifications',
                             'industries',
                             'fields',
                         ])
        );
    }

    public function destroy(Good $good)
    {
        DB::transaction(function () use ($good): void {
            $good = Good::query()->whereKey($good->id)->lockForUpdate()->firstOrFail();
            abort_if($good->stockMovements()->exists(), 422,
                'Нельзя удалить товар со складской историей. Снимите товар с публикации.');

            $good->delete();
        }, 3);

        Storage::disk('yandex')->deleteDirectory("goods/{$good->id}");

        return response()->json(null, 204);
    }

    public function togglePublish(Request $request, Good $good)
    {
        $validated = $request->validate([
                                            'is_published' => ['required', 'boolean'],
                                        ]);

        $good->update([
                          'is_published' => $validated['is_published'],
                      ]);

        $this->syncGoodJson($good);

        return response()->json([
                                    'id' => $good->id,
                                    'is_published' => $good->is_published,
                                ]);
    }

    public function vatRates()
    {
        return response()->json(
            VatRate::query()
                ->orderBy('rate')
                ->orderBy('title')
                ->get([
                          'id',
                          'title',
                          'rate',
                      ])
        );
    }

    private function storeAvatarFiles(Good $good, $file): void
    {
        $disk = Storage::disk('yandex');
        $images = app(GoodAvatarImages::class);

        $ext = strtolower($file->getClientOriginalExtension());
        $baseName = 'avatar-' . time() . '-' . Str::random(6);

        $originalPath = "goods/{$good->id}/{$baseName}.{$ext}";
        $bytes = file_get_contents($file->getRealPath());
        $disk->put($originalPath, $bytes, [
            'ContentType' => $file->getMimeType(),
            'CacheControl' => GoodAvatarImages::CACHE_CONTROL,
        ]);

        $good->update([
                          'ava_image' => $images->publicUrl($originalPath),
                          'ava_thumb' => $images->storeThumbnail($good->id, $bytes),
                      ]);
    }

    private function deleteAvatarFiles(Good $good): void
    {
        $disk = Storage::disk('yandex');
        $images = app(GoodAvatarImages::class);

        foreach (['ava_image', 'ava_thumb'] as $field) {
            if (!$good->{$field}) {
                continue;
            }

            $path = $images->storagePath($good->{$field});

            if ($path) {
                $disk->delete($path);
            }
        }
    }

    private function syncGoodJson(Good $good): void
    {
        Storage::disk('yandex')->put(
            "goods/{$good->id}/good.json",
            $good->fresh([
                             'products.category',
                             'country:id,name,flag',
                             'vatRate',
                             'seo',
                             'publishedMedia',
                             'priceTypeValues.priceType',
                             'priceTypeValues.currency',
                             'entityClassifications',
                             'industries',
                             'fields',
                         ])->toJson(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }
}
