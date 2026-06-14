<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\UpdateYandexDirectAdRequest;
use App\Models\YandexDirectAd;
use App\Models\YandexDirectKeyword;
use App\Services\Yandex\GoodDirectDraftService;
use App\Services\Yandex\YandexDirectCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class YandexDirectAdController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 50), 1), 200);
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');

        $query = YandexDirectAd::query()
            ->with(['good.products.category', 'seo', 'adGroup.campaign.account', 'adGroup.keywords'])
            ->latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title_1', 'like', "%{$search}%")
                    ->orWhere('title_2', 'like', "%{$search}%")
                    ->orWhere('text', 'like', "%{$search}%")
                    ->orWhereHas('good', fn ($good) => $good->where('name', 'like', "%{$search}%"));
            });
        }

        if (filled($status)) {
            $query->where('status', $status);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function show(YandexDirectAd $ad): JsonResponse
    {
        return response()->json($ad->load(['good.products.category', 'seo', 'adGroup.campaign.account', 'adGroup.keywords']));
    }

    public function update(UpdateYandexDirectAdRequest $request, YandexDirectAd $ad, GoodDirectDraftService $draftService): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($ad, $validated, $draftService) {
            $ad->update(collect($validated)->only([
                'title_1',
                'title_2',
                'text',
                'href',
                'utm_template',
                'image_url',
                'status',
            ])->toArray());

            $ad->loadMissing('adGroup.campaign');

            if (array_key_exists('external_ad_group_id', $validated)) {
                $ad->adGroup->update([
                    'external_ad_group_id' => $validated['external_ad_group_id'],
                ]);
            }

            if (array_key_exists('external_campaign_id', $validated)) {
                $ad->adGroup->campaign->update([
                    'external_campaign_id' => $validated['external_campaign_id'],
                ]);
            }

            if (array_key_exists('keywords', $validated)) {
                $ad->adGroup->keywords()->delete();

                foreach ($validated['keywords'] ?? [] as $keyword) {
                    YandexDirectKeyword::query()->create([
                        'yandex_direct_ad_group_id' => $ad->adGroup->id,
                        'good_id' => $ad->good_id,
                        'phrase' => $keyword['phrase'],
                        'bid' => $keyword['bid'] ?? null,
                        'context_bid' => $keyword['context_bid'] ?? null,
                        'is_negative' => $keyword['is_negative'] ?? false,
                        'status' => YandexDirectAd::STATUS_DRAFT,
                    ]);
                }
            }

            $draftService->validateAndPersist($ad->fresh());
        });

        return response()->json($ad->fresh(['good.products.category', 'seo', 'adGroup.campaign.account', 'adGroup.keywords']));
    }

    public function validateAd(YandexDirectAd $ad, GoodDirectDraftService $draftService): JsonResponse
    {
        return response()->json([
            'errors' => $draftService->validateAndPersist($ad),
            'ad' => $ad->fresh(['adGroup.keywords']),
        ]);
    }

    public function send(YandexDirectAd $ad, YandexDirectCampaignService $service): JsonResponse
    {
        try {
            return response()->json($service->sendAd($ad));
        } catch (Throwable $e) {
            $freshAd = $ad->fresh(['adGroup.campaign.account', 'adGroup.keywords']);

            return response()->json([
                'message' => $e->getMessage(),
                'ad' => $freshAd,
                'validation_errors' => $freshAd?->validation_errors ?: [],
            ], 422);
        }
    }

    public function suspend(YandexDirectAd $ad, YandexDirectCampaignService $service): JsonResponse
    {
        return response()->json($service->suspendAd($ad));
    }

    public function resume(YandexDirectAd $ad, YandexDirectCampaignService $service): JsonResponse
    {
        return response()->json($service->resumeAd($ad));
    }
}
