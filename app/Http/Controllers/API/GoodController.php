<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoodRequest;
use App\Http\Requests\UpdateGoodRequest;
use App\Models\Category;
use App\Models\Country;
use App\Models\EntityClassification;
use App\Models\Field;
use App\Models\Good;
use App\Models\Industry;
use App\Models\Product;
use App\Models\VatRate;
use App\Services\Goods\GoodAvatarImages;
use App\Services\Goods\GoodTradeCodes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GoodController extends Controller
{
    public function index(Request $request)
    {
        if ($request->input('view') === 'filters') {
            return $this->filterMetadata();
        }

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

        if (! in_array($sortBy, $allowedSorts, true)) {
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

        if (! is_null($isPublished) && $isPublished !== '') {
            $query->where('is_published', filter_var($isPublished, FILTER_VALIDATE_BOOLEAN));
        }

        $this->applyFilters($query, $request);
        $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc')->orderBy('goods.id');

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

    public function store(StoreGoodRequest $request)
    {
        $validated = $request->validated();

        $good = Good::create([
            'name' => $validated['name'],
            'incoming_code' => $validated['incoming_code'] ?? null,
            'slug' => $validated['slug'] ?? null,
            'denominator' => $validated['denominator'] ?? null,
            'description' => $validated['description'] ?? null,
            'vat_rate_id' => $validated['vat_rate_id'] ?? null,
            'country_id' => $validated['country_id'] ?? null,
            'is_published' => $validated['is_published'] ?? true,
            ...GoodTradeCodes::normalize($validated),
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
        } else {
            $this->storeAvatarUrls($good, $validated);
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

    public function update(UpdateGoodRequest $request, Good $good)
    {
        $validated = $request->validated();

        $dataToUpdate = collect($validated)
            ->except([
                'ava_image',
                'avatar_source_url',
                'avatar_thumb_source_url',
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

        if (! empty($validated['remove_ava'])) {
            $this->deleteAvatarFiles($good);

            $good->update([
                'ava_image' => null,
                'ava_thumb' => null,
            ]);
        }

        if ($request->hasFile('ava_image')) {
            $this->deleteAvatarFiles($good);
            $this->storeAvatarFiles($good, $request->file('ava_image'));
        } else {
            $this->storeAvatarUrls($good, $validated);
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

    private function filterMetadata()
    {
        return response()->json([
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->without(['manufacturers', 'category'])->orderBy('rus')->get(['id', 'rus', 'category_id']),
            'countries' => Country::query()->orderBy('name')->get(['id', 'name', 'flag']),
            'fields' => Field::query()->orderBy('title')->get(['id', 'title']),
            'industries' => Industry::query()->orderBy('title')->get(['id', 'code', 'title']),
            'entity_classifications' => EntityClassification::query()->orderBy('name')->get(['id', 'name']),
            'vat_rates' => VatRate::query()->orderBy('rate')->get(['id', 'title', 'rate']),
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $idRule = ['nullable', 'regex:/^(?:[1-9][0-9]*|none)$/D'];
        $filters = $request->validate([
            'category_id' => $idRule,
            'product_id' => $idRule,
            'country_id' => $idRule,
            'field_id' => $idRule,
            'industry_id' => $idRule,
            'entity_classification_id' => $idRule,
            'vat_rate_id' => $idRule,
            'has_avatar' => ['nullable', Rule::in(['true', 'false', '1', '0'])],
            'has_trade_codes' => ['nullable', Rule::in(['true', 'false', '1', '0'])],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('created_from') ? ['after_or_equal:created_from'] : [])],
        ]);

        foreach (['country_id', 'vat_rate_id'] as $column) {
            $value = $filters[$column] ?? null;
            if ($value === 'none') {
                $query->whereNull($column);
            } elseif ($value !== null && $value !== '') {
                $query->where($column, $value);
            }
        }

        foreach ([
            'category_id' => 'products.category',
            'product_id' => 'products',
            'field_id' => 'fields',
            'industry_id' => 'industries',
            'entity_classification_id' => 'entityClassifications',
        ] as $filter => $relation) {
            $value = $filters[$filter] ?? null;
            if ($value === 'none') {
                $query->whereDoesntHave($relation);
            } elseif ($value !== null && $value !== '') {
                $query->whereHas($relation, fn (Builder $related) => $related->whereKey($value));
            }
        }

        foreach (['has_avatar' => ['ava_image', 'ava_thumb'], 'has_trade_codes' => GoodTradeCodes::FIELDS] as $filter => $columns) {
            if (! isset($filters[$filter]) || $filters[$filter] === '') {
                continue;
            }

            $hasValue = filter_var($filters[$filter], FILTER_VALIDATE_BOOLEAN);
            $query->where(function (Builder $group) use ($hasValue, $columns): void {
                foreach ($columns as $column) {
                    if ($hasValue) {
                        $group->orWhere(fn (Builder $q) => $q->whereNotNull($column)->where($column, '!=', ''));
                    } else {
                        $group->where(fn (Builder $q) => $q->whereNull($column)->orWhere($column, ''));
                    }
                }
            });
        }

        if (! empty($filters['created_from'])) {
            $query->where('goods.created_at', '>=', $filters['created_from'].' 00:00:00');
        }
        if (! empty($filters['created_to'])) {
            $query->where('goods.created_at', '<', date('Y-m-d', strtotime($filters['created_to'].' +1 day')).' 00:00:00');
        }
    }

    /** Save supplied URLs as metadata only; never download arbitrary remote images. */
    private function storeAvatarUrls(Good $good, array $validated): void
    {
        $source = $validated['avatar_source_url'] ?? null;
        $thumbnail = $validated['avatar_thumb_source_url'] ?? null;
        if (! $source && ! $thumbnail) {
            return;
        }

        $image = $source ?: ($good->ava_image ?: $thumbnail);
        if (! array_key_exists('avatar_thumb_source_url', $validated) && $image === $good->ava_image) {
            $thumbnail = $good->ava_thumb;
        }

        $this->deleteAvatarFiles($good, [$image, $thumbnail]);
        $good->update(['ava_image' => $image, 'ava_thumb' => $thumbnail]);
    }

    private function storeAvatarFiles(Good $good, $file): void
    {
        $disk = Storage::disk('yandex');
        $images = app(GoodAvatarImages::class);

        $ext = strtolower($file->getClientOriginalExtension());
        $baseName = 'avatar-'.time().'-'.Str::random(6);

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

    private function deleteAvatarFiles(Good $good, array $preserveUrls = []): void
    {
        $disk = Storage::disk('yandex');
        $images = app(GoodAvatarImages::class);
        $preservePaths = array_filter(array_map(
            fn (?string $url): ?string => $url ? $images->storagePath($url) : null,
            $preserveUrls,
        ));

        foreach (['ava_image', 'ava_thumb'] as $field) {
            if (! $good->{$field} || in_array($good->{$field}, $preserveUrls, true)) {
                continue;
            }

            $path = $images->storagePath($good->{$field});
            if (! $path || ! str_starts_with($path, "goods/{$good->id}/") || in_array($path, $preservePaths, true)) {
                continue;
            }

            $decodedPath = rawurldecode($path);
            if (str_contains($decodedPath, '..') || str_contains($decodedPath, '\\') || str_contains($decodedPath, "\0")) {
                continue;
            }

            // An avatar selected from the media library still belongs to that library.
            if ($good->media()->where(fn (Builder $query) => $query->where('path', $path)->orWhere('thumb_path', $path))->exists()) {
                continue;
            }

            $disk->delete($path);
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
