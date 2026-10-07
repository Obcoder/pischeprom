<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Good;
use App\Services\Seo\GoodSeoAiService;
use App\Services\Seo\GoodSeoService;
use App\Services\Seo\GoodStructuredDataService;
use App\Services\Seo\IndexNowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GoodSeoController extends Controller
{
    public function show(Good $good, GoodSeoAiService $ai): JsonResponse
    {
        $seo = $good->seo()->firstOrCreate([
            'good_id' => $good->id,
        ], [
            'meta_title' => "{$good->name} купить оптом для пищевой промышленности",
            'meta_description' => $good->description,
            'h1' => $good->name,
            'robots' => 'index,follow',
            'focus_keyword' => $good->name,
            'breadcrumbs_title' => $good->name,
            'is_active' => true,
            'include_in_sitemap' => true,
            'include_in_yandex_feed' => true,
            'availability_status' => 'on_request',
        ]);

        return response()->json([...$seo->toArray(), 'ai_generation' => $ai->availability()]);
    }

    public function upsert(
        Request $request,
        Good $good,
        IndexNowService $indexNowService,
        GoodSeoService $seoService,
    ): JsonResponse {
        $existing = $good->seo;
        $sameAlias = $request->input('slug_override') === $existing?->slug_override;
        $activating = $request->boolean('is_active') && ! $existing?->is_active;
        $aliasRules = ['nullable', 'string', 'max:255'];
        if (! $sameAlias) {
            $aliasRules[] = 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
        }
        if (! $sameAlias || $activating) {
            $aliasRules[] = Rule::unique('goods', 'slug')->ignore($good->id);
            $aliasRules[] = Rule::unique('good_seos', 'slug_override')->ignore($good->id, 'good_id');
        }
        $canonicalRules = ['nullable', 'string', 'max:255'];
        if ($request->input('canonical_url') !== $existing?->canonical_url) {
            $canonicalRules[] = 'url:http,https';
        }
        $validated = $request->validate([
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'h1' => ['nullable', 'string', 'max:255'],
            'slug_override' => $aliasRules,
            'canonical_url' => $canonicalRules,
            'robots' => ['required', 'string', 'max:50'],

            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string'],
            'og_image' => ['nullable', 'string', 'max:255'],

            'twitter_title' => ['nullable', 'string', 'max:255'],
            'twitter_description' => ['nullable', 'string'],
            'twitter_image' => ['nullable', 'string', 'max:255'],

            'short_seo_text' => ['nullable', 'string'],
            'seo_text' => ['nullable', 'string'],

            'semantic_core' => ['nullable', 'array', 'max:2000'],
            'semantic_core.*' => ['required', 'string', 'max:1000'],
            'semantic_core_rows' => ['sometimes', 'array', 'list', 'max:2000'],
            'semantic_core_rows.*' => ['required', 'array:group,phrase'],
            'semantic_core_rows.*.group' => ['nullable', 'string', 'max:255'],
            'semantic_core_rows.*.phrase' => ['required', 'string', 'max:1000'],
            'keywords' => ['nullable', 'array'],
            'search_queries' => ['nullable', 'array'],
            'structured_data' => ['nullable', 'array'],

            'focus_keyword' => ['nullable', 'string', 'max:255'],
            'breadcrumbs_title' => ['nullable', 'string', 'max:255'],

            'is_active' => ['required', 'boolean'],
            'include_in_sitemap' => ['nullable', 'boolean'],
            'include_in_yandex_feed' => ['nullable', 'boolean'],

            'yandex_direct_title_1' => ['nullable', 'string', 'max:255'],
            'yandex_direct_title_2' => ['nullable', 'string', 'max:255'],
            'yandex_direct_text' => ['nullable', 'string'],
            'utm_template' => ['nullable', 'string'],

            'availability_status' => ['nullable', 'string', 'max:50'],
            'min_order' => ['nullable', 'string', 'max:255'],
            'delivery_note' => ['nullable', 'string'],
            'payment_note' => ['nullable', 'string'],
            'faq' => ['nullable', 'array'],
        ]);

        $validated = $this->synchronizeSemanticCore($validated, $existing?->semantic_core_rows ?? []);

        $seo = $good->seo()->updateOrCreate(
            ['good_id' => $good->id],
            [
                ...$validated,
                'include_in_sitemap' => $validated['include_in_sitemap'] ?? true,
                'include_in_yandex_feed' => $validated['include_in_yandex_feed'] ?? true,
                'availability_status' => $validated['availability_status'] ?? 'on_request',
            ]
        );

        if ($good->is_published && $seo->is_active && str_starts_with($seo->robots, 'index')) {
            if ($indexNowService->submit([$seoService->publicUrl($good->fresh('seo'))])) {
                $seo->update([
                    'index_now_sent_at' => now(),
                ]);
            }
        }

        return response()->json($seo->fresh());
    }

    private function synchronizeSemanticCore(array $attributes, array $existingRows): array
    {
        if (array_key_exists('semantic_core_rows', $attributes)) {
            $rows = collect($attributes['semantic_core_rows'])->map(fn (array $row): array => [
                'group' => trim($row['group'] ?? ''),
                'phrase' => trim($row['phrase']),
            ])->uniqueStrict(fn (array $row): string => json_encode([mb_strtolower($row['group']), mb_strtolower($row['phrase'])]))->values()->all();
            $attributes['semantic_core_rows'] = $rows;
            $attributes['semantic_core'] = collect($rows)->pluck('phrase')->uniqueStrict(fn (string $phrase): string => mb_strtolower($phrase))->values()->all();
        } elseif (array_key_exists('semantic_core', $attributes)) {
            $phrases = collect($attributes['semantic_core'] ?? [])->map(fn (string $phrase): string => trim($phrase))
                ->uniqueStrict(fn (string $phrase): string => mb_strtolower($phrase))->values();
            $rows = $phrases->flatMap(function (string $phrase) use ($existingRows): array {
                $retained = collect($existingRows)->filter(fn (array $row): bool => mb_strtolower($row['phrase']) === mb_strtolower($phrase))
                    ->map(fn (array $row): array => ['group' => $row['group'], 'phrase' => $phrase])->values()->all();

                return $retained ?: [['group' => '', 'phrase' => $phrase]];
            })->values()->all();

            if (count($rows) > 2000) {
                throw ValidationException::withMessages(['semantic_core' => 'Сохранённые группы и новые фразы превышают ограничение в 2000 строк.']);
            }

            $attributes['semantic_core_rows'] = $rows;
            if ($attributes['semantic_core'] !== null) {
                $attributes['semantic_core'] = $phrases->all();
            }
        }

        return $attributes;
    }

    public function generateStructuredData(
        Good $good,
        GoodStructuredDataService $structuredDataService,
    ): JsonResponse {
        $good->load([
            'seo',
            'products.category',
            'vatRate',
            'publishedMedia',
            'priceTypeValues.priceType.currency',
            'priceTypeValues.currency',
        ]);

        $seo = $good->seo()->firstOrCreate([
            'good_id' => $good->id,
        ]);

        $seo->update([
            'structured_data' => $structuredDataService->make($good, true),
            'last_generated_at' => now(),
        ]);

        return response()->json($seo->fresh());
    }
}
