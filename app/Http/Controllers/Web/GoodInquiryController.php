<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoodInquiryRequest;
use App\Jobs\NotifyGoodInquiry;
use App\Models\Good;
use App\Models\GoodInquiry;
use App\Services\Goods\GoodInquiryOrderWriter;
use App\Services\Goods\PublicGoodOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GoodInquiryController extends Controller
{
    public function store(
        StoreGoodInquiryRequest $request,
        Good $good,
        PublicGoodOffer $offers,
        GoodInquiryOrderWriter $orders,
    ): JsonResponse {
        abort_unless($good->is_published, 404);

        $data = $request->validated();
        $attributes = Arr::except($data, ['request_token', 'consent', 'website']);
        ksort($attributes);
        $requestHash = hash('sha256', json_encode([$good->id, $attributes], JSON_THROW_ON_ERROR));

        $inquiry = DB::transaction(function () use ($data, $attributes, $requestHash, $good, $request, $offers, $orders): GoodInquiry {
            $offer = $offers->for($good);
            $good->loadMissing('seo');
            $slug = $good->seo?->is_active && filled($good->seo?->slug_override)
                ? $good->seo->slug_override : $good->slug;
            $inquiry = GoodInquiry::query()->firstOrCreate(['request_token' => $data['request_token']], [
                ...$attributes,
                'request_hash' => $requestHash,
                'number' => 'WEB-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                'good_id' => $good->id,
                'user_id' => $request->user()?->id,
                'good_name' => $good->name,
                'good_url' => route('public.goods.show', ['good' => $slug]),
                'package_weight' => $offer['package_weight'],
                'listed_price' => $offer['price'],
                'price_unit' => $offer['price_unit'],
                'currency_code' => $offer['currency_code'],
                'consent_at' => now(),
                'next_notification_at' => now(),
            ]);

            abort_unless(hash_equals($inquiry->request_hash, $requestHash), 409, 'Эта форма уже отправлена. Откройте новую заявку.');

            if ($inquiry->wasRecentlyCreated && $inquiry->kind === 'order') {
                $inquiry->order()->associate($orders->create($inquiry));
                $inquiry->save();
            }

            return $inquiry;
        });

        if ($inquiry->wasRecentlyCreated) {
            try {
                NotifyGoodInquiry::dispatch($inquiry->id);
            } catch (Throwable $exception) {
                // The durable record and scheduler retry survive queue outages.
                Log::warning('Good inquiry notification dispatch failed.', [
                    'inquiry_id' => $inquiry->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        return response()->json([
            'inquiry' => [
                'number' => $inquiry->number,
                'kind' => $inquiry->kind,
                'order_number' => $inquiry->order?->number,
            ],
            'message' => $inquiry->kind === 'bargain'
                ? 'Предложение сохранено. Менеджер рассмотрит вашу цену и свяжется с вами для согласования условий.'
                : 'Заявка сохранена. Менеджер свяжется с вами для подтверждения цены, наличия и доставки.',
        ], $inquiry->wasRecentlyCreated ? 201 : 200);
    }
}
