<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Good;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodSeoController extends Controller
{
    public function show(Good $good): JsonResponse
    {
        return response()->json(
            $good->seo()->firstOrCreate([
                                            'good_id' => $good->id,
                                        ])
        );
    }

    public function upsert(Request $request, Good $good): JsonResponse
    {
        $validated = $request->validate([
                                            'meta_title' => ['nullable', 'string', 'max:255'],
                                            'meta_description' => ['nullable', 'string'],
                                            'h1' => ['nullable', 'string', 'max:255'],
                                            'slug_override' => ['nullable', 'string', 'max:255'],
                                            'canonical_url' => ['nullable', 'string', 'max:255'],
                                            'robots' => ['nullable', 'string', 'max:255'],

                                            'og_title' => ['nullable', 'string', 'max:255'],
                                            'og_description' => ['nullable', 'string'],
                                            'og_image' => ['nullable', 'string', 'max:255'],

                                            'twitter_title' => ['nullable', 'string', 'max:255'],
                                            'twitter_description' => ['nullable', 'string'],
                                            'twitter_image' => ['nullable', 'string', 'max:255'],

                                            'short_seo_text' => ['nullable', 'string'],
                                            'seo_text' => ['nullable', 'string'],

                                            'semantic_core' => ['nullable', 'array'],
                                            'keywords' => ['nullable', 'array'],
                                            'search_queries' => ['nullable', 'array'],
                                            'structured_data' => ['nullable', 'array'],

                                            'focus_keyword' => ['nullable', 'string', 'max:255'],
                                            'breadcrumbs_title' => ['nullable', 'string', 'max:255'],

                                            'is_active' => ['nullable', 'boolean'],
                                        ]);

        $seo = $good->seo()->updateOrCreate(
            ['good_id' => $good->id],
            $validated
        );

        return response()->json($seo);
    }
}
