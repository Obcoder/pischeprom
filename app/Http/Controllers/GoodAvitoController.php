<?php

namespace App\Http\Controllers;

use App\Domain\Avito\Exceptions\AvitoException;
use App\Models\AvitoListingGoodLink;
use App\Models\Good;
use App\Services\Avito\AvitoListingGoodService;
use App\Services\Avito\AvitoPriceBasis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GoodAvitoController extends Controller
{
    public function show(Good $good, AvitoListingGoodService $transfers): JsonResponse
    {
        return response()->json($transfers->forGood($good));
    }

    public function store(Request $request, Good $good, AvitoListingGoodService $transfers): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'min:1'],
            'item_id' => ['required', 'integer', 'min:1'],
        ]);
        $accountId = (int) $validated['account_id'];
        $itemId = (int) $validated['item_id'];
        $listing = $transfers->verifiedListing($accountId, $itemId);
        $link = DB::transaction(function () use ($good, $transfers, $accountId, $itemId): AvitoListingGoodLink {
            Good::query()->whereKey($good->id)->lockForUpdate()->firstOrFail();
            $existing = AvitoListingGoodLink::query()
                ->where('avito_account_id', $accountId)->where('avito_item_id', $itemId)
                ->lockForUpdate()->first();
            if ($existing && $existing->good_id !== $good->id) {
                throw new AvitoException(
                    'Это объявление уже связано с другим товаром. Сначала удалите прежнюю связь.',
                    'good_link_conflict',
                    409,
                );
            }

            return $existing ?: $transfers->link($accountId, $itemId, $good);
        });

        return response()->json($transfers->payload($link) + ['listing' => $listing['item']]);
    }

    public function destroy(Good $good, AvitoListingGoodLink $link, AvitoListingGoodService $transfers): JsonResponse
    {
        $this->assertGood($good, $link);
        $transfers->unlink($link);

        return response()->json(['link' => null, 'good_id' => $good->id]);
    }

    public function listing(Good $good, AvitoListingGoodLink $link, AvitoListingGoodService $transfers): JsonResponse
    {
        $this->assertGood($good, $link);

        return response()->json($transfers->verifiedListing($link->avito_account_id, $link->avito_item_id));
    }

    public function preview(Request $request, Good $good, AvitoListingGoodLink $link, AvitoListingGoodService $transfers): JsonResponse
    {
        $this->assertGood($good, $link);
        $validated = $request->validate($this->transferRules());

        return response()->json([
            'preview' => $transfers->preview($link, $validated),
        ] + $transfers->payload($link->fresh()));
    }

    public function apply(Request $request, Good $good, AvitoListingGoodLink $link, AvitoListingGoodService $transfers): JsonResponse
    {
        $this->assertGood($good, $link);
        $validated = $request->validate($this->transferRules() + [
            'confirmed' => ['required', 'accepted'],
            'expected_fingerprint' => ['required', 'string', 'size:64'],
        ]);
        $transfer = $transfers->applyPrice($link, $validated);

        return response()->json(['transfer' => $transfer] + $transfers->payload($link->fresh()));
    }

    private function assertGood(Good $good, AvitoListingGoodLink $link): void
    {
        abort_unless($link->good_id === $good->id, 404);
    }

    private function transferRules(): array
    {
        return AvitoPriceBasis::rules() + [
            'fields' => ['required', 'array', 'min:1', 'max:4'],
            'fields.*' => ['required', 'string', Rule::in(AvitoListingGoodService::FIELDS), 'distinct'],
            'price_value_id' => ['nullable', 'integer', 'exists:good_price_type_values,id'],
            'media_ids' => ['sometimes', 'array', 'max:10'],
            'media_ids.*' => ['required', 'integer', 'exists:good_media,id', 'distinct'],
            'include_facts' => ['sometimes', 'boolean'],
            'avito' => ['sometimes', 'array'],
            'avito.title' => ['nullable', 'string', 'max:500'],
            'avito.price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
