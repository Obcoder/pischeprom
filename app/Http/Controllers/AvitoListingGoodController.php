<?php

namespace App\Http\Controllers;

use App\Domain\Avito\Exceptions\AvitoException;
use App\Models\AvitoConnection;
use App\Models\Good;
use App\Models\GoodMedia;
use App\Services\Avito\AvitoListingGoodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AvitoListingGoodController extends Controller
{
    public function goods(Request $request, AvitoListingGoodService $transfers): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
        ]);

        return response()->json($transfers->searchGoods($validated['search'] ?? null));
    }

    public function show(
        Request $request,
        int $item,
        AvitoListingGoodService $transfers,
    ): JsonResponse {
        $validated = $request->validate($this->accountRules());

        return response()->json($transfers->payload(
            $transfers->find((int) $validated['account_id'], $item)
        ));
    }

    public function store(
        Request $request,
        int $item,
        AvitoListingGoodService $transfers,
    ): JsonResponse {
        $validated = $request->validate($this->accountRules() + [
            'good_id' => ['required', 'integer', 'exists:goods,id'],
        ]);
        $link = $transfers->link(
            (int) $validated['account_id'],
            $item,
            Good::query()->findOrFail($validated['good_id']),
        );

        return response()->json($transfers->payload($link));
    }

    public function destroy(
        Request $request,
        int $item,
        AvitoListingGoodService $transfers,
    ): JsonResponse {
        $validated = $request->validate($this->accountRules());
        $transfers->requireLink((int) $validated['account_id'], $item)->delete();

        return response()->json([
            'link' => null,
            'source_of_truth' => 'good',
        ]);
    }

    public function preview(
        Request $request,
        int $item,
        AvitoListingGoodService $transfers,
    ): JsonResponse {
        $validated = $request->validate($this->transferRules());
        $link = $transfers->requireLink((int) $validated['account_id'], $item);
        $preview = $transfers->preview($link, $validated);
        $context = $transfers->payload($link->fresh());

        return response()->json(['preview' => $preview] + $context);
    }

    public function apply(
        Request $request,
        int $item,
        AvitoListingGoodService $transfers,
    ): JsonResponse {
        $validated = $request->validate($this->transferRules() + [
            'confirmed' => ['accepted'],
            'connection_id' => ['nullable', 'integer', 'exists:avito_connections,id'],
        ]);
        $link = $transfers->requireLink((int) $validated['account_id'], $item);
        $connection = isset($validated['connection_id'])
            ? AvitoConnection::query()->findOrFail($validated['connection_id'])
            : null;
        $transfer = $transfers->applyPrice($link, $validated, $connection);
        $context = $transfers->payload($link->fresh());

        return response()->json(['transfer' => $transfer] + $context);
    }

    public function media(
        Request $request,
        int $item,
        GoodMedia $media,
        AvitoListingGoodService $transfers,
    ): StreamedResponse {
        $validated = $request->validate($this->accountRules());
        $link = $transfers->requireLink((int) $validated['account_id'], $item);
        $media = $transfers->linkedMedia($link, $media);
        $disk = Storage::disk($media->disk ?: 'yandex');

        if (! $media->path || ! $disk->exists($media->path)) {
            throw new AvitoException(
                'Исходный файл фотографии Good отсутствует в хранилище.',
                'good_transfer_media_missing',
                404,
            );
        }

        $extension = preg_replace(
            '/[^a-z0-9]/',
            '',
            Str::lower($media->extension ?: pathinfo($media->path, PATHINFO_EXTENSION))
        ) ?: 'jpg';
        $filename = (Str::slug($media->good?->name ?: 'good') ?: 'good')
            .'-'.$media->id.'.'.$extension;

        return $disk->download($media->path, $filename);
    }

    private function accountRules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function transferRules(): array
    {
        return $this->accountRules() + [
            'fields' => ['required', 'array', 'min:1', 'max:4'],
            'fields.*' => [
                'required',
                'string',
                Rule::in(AvitoListingGoodService::FIELDS),
                'distinct',
            ],
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
