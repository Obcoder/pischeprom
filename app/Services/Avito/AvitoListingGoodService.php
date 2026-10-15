<?php

namespace App\Services\Avito;

use App\Domain\Avito\Exceptions\AvitoException;
use App\Models\AvitoConnection;
use App\Models\AvitoListingGoodLink;
use App\Models\AvitoPublication;
use App\Models\Good;
use App\Models\GoodMedia;
use App\Models\GoodPriceTypeValue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AvitoListingGoodService
{
    public const FIELDS = ['title', 'description', 'price', 'images'];

    public const DIRECT_FIELDS = ['price'];

    public const MANUAL_FIELDS = ['title', 'description', 'images'];

    public function __construct(
        private readonly AvitoCrmOutboundService $goods,
        private readonly AvitoListingService $listings,
        private readonly AvitoPriceBasis $priceBasis,
    ) {}

    public function searchGoods(?string $search): array
    {
        $search = trim((string) $search);
        $goods = Good::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->with($this->goods->goodRelations())
            ->withExists('stockMovements')
            ->orderByDesc('is_published')
            ->orderBy('name')
            ->limit(30)
            ->get();

        return [
            'items' => $goods
                ->map(fn (Good $good): array => $this->goodPayload($good))
                ->values()
                ->all(),
        ];
    }

    public function find(int $accountId, int $itemId): ?AvitoListingGoodLink
    {
        return AvitoListingGoodLink::query()
            ->where('avito_account_id', $accountId)
            ->where('avito_item_id', $itemId)
            ->first();
    }

    /** Local context stays available even while the remote API is unavailable. */
    public function forGood(Good $good): array
    {
        $payload = $this->goodPayload($good);

        return [
            'good_id' => $good->id,
            'good' => $payload,
            'measurement' => $payload['measurement'],
            'links' => $good->avitoListingLinks()->orderBy('id')->get()
                ->map(function (AvitoListingGoodLink $link): array {
                    $context = $this->payload($link);

                    return $context['link'] + ['history' => $context['history']];
                })->values()->all(),
            'publications' => $good->avitoPublications()->latest('id')->get()->map(fn ($publication): array => [
                'id' => $publication->id,
                'avito_account_id' => $publication->avito_account_id,
                'avito_item_id' => $publication->avito_item_id,
                'external_id' => $publication->external_id,
                'category_name' => $publication->category_name,
                'status' => $publication->status,
                'draft_dirty' => $publication->draft_dirty,
                'last_synced_at' => $publication->last_synced_at?->toIso8601String(),
                'updated_at' => $publication->updated_at?->toIso8601String(),
            ])->values()->all(),
            'source_of_truth' => 'good',
            'field_capabilities' => $this->fieldCapabilities(),
        ];
    }

    public function verifiedListing(int $accountId, int $itemId): array
    {
        $context = $this->listings->context();
        if ((int) ($context['account']['id'] ?? 0) !== $accountId) {
            throw new AvitoException(
                'Объявление должно принадлежать подключённому аккаунту Avito. Обновите подключение.',
                'good_link_account_mismatch',
                409,
            );
        }
        $listing = $this->listings->listing($accountId, $itemId);
        // This endpoint normally omits id, title and price. Status is mandatory;
        // an empty/malformed success must never establish a verified link.
        if (! in_array($listing['item']['status'] ?? null, AvitoListingService::STATUSES, true)
            || (isset($listing['item']['id']) && (int) $listing['item']['id'] !== $itemId)) {
            throw new AvitoException('Avito вернул некорректную карточку объявления.', 'listing_invalid_response', 502);
        }

        return $listing;
    }

    public function requireLink(int $accountId, int $itemId): AvitoListingGoodLink
    {
        return $this->find($accountId, $itemId)
            ?? throw new AvitoException(
                'Сначала привяжите объявление Avito к Good.',
                'good_link_missing',
                404,
            );
    }

    public function link(int $accountId, int $itemId, Good $good): AvitoListingGoodLink
    {
        $link = AvitoListingGoodLink::query()->firstOrNew([
            'avito_account_id' => $accountId,
            'avito_item_id' => $itemId,
        ]);

        if ($link->exists && $link->good_id !== $good->id) {
            throw new AvitoException('Объявление уже связано с другим товаром. Сначала удалите прежнюю связь.', 'good_link_conflict', 409);
        }

        if (! $link->exists || $link->good_id !== $good->id) {
            $link->fill([
                'good_id' => $good->id,
                'last_price_value_id' => null,
                'price_basis' => null,
                'last_selected_fields' => null,
                'last_media_ids' => null,
                'include_facts' => true,
                'last_prepared_at' => null,
                'last_applied_at' => null,
            ]);
        }

        $link->save();

        return $link->fresh();
    }

    public function unlink(AvitoListingGoodLink $link): void
    {
        if (AvitoPublication::query()->where('avito_account_id', $link->avito_account_id)
            ->where('avito_item_id', $link->avito_item_id)->where('status', '!=', 'archived')->exists()) {
            throw new AvitoException(
                'Объявлением управляет автозагрузка. Сначала архивируйте его публикацию во вкладке «Автозагрузка».',
                'good_link_managed_publication',
                409,
            );
        }
        $link->delete();
    }

    public function payload(?AvitoListingGoodLink $link): array
    {
        if (! $link) {
            return [
                'link' => null,
                'source_of_truth' => 'good',
                'field_capabilities' => $this->fieldCapabilities(),
            ];
        }

        $link->loadMissing('good');
        $history = $link->transfers()
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(fn ($transfer): array => [
                'id' => $transfer->id,
                'mode' => $transfer->mode,
                'status' => $transfer->status,
                'selected_fields' => $transfer->selected_fields ?: [],
                'applied_fields' => $transfer->applied_fields ?: [],
                'manual_fields' => $transfer->manual_fields ?: [],
                'price' => Arr::only((array) Arr::get($transfer->source_snapshot, 'price', []), ['good_value', 'avito_value', 'price_basis', 'formula']),
                'created_at' => $transfer->created_at?->toIso8601String(),
            ])
            ->values();

        return [
            'link' => [
                'id' => $link->id,
                'avito_account_id' => $link->avito_account_id,
                'avito_item_id' => $link->avito_item_id,
                'good_id' => $link->good_id,
                'good' => $this->goodPayload($link->good, $link),
                'last_price_value_id' => $link->last_price_value_id,
                'price_basis' => $link->price_basis,
                'last_selected_fields' => $link->last_selected_fields ?: [],
                'last_media_ids' => $link->last_media_ids ?: [],
                'include_facts' => $link->include_facts,
                'last_prepared_at' => $link->last_prepared_at?->toIso8601String(),
                'last_applied_at' => $link->last_applied_at?->toIso8601String(),
                'created_at' => $link->created_at?->toIso8601String(),
                'updated_at' => $link->updated_at?->toIso8601String(),
            ],
            'history' => $history,
            'source_of_truth' => 'good',
            'field_capabilities' => $this->fieldCapabilities(),
        ];
    }

    public function preview(AvitoListingGoodLink $link, array $input): array
    {
        $preview = $this->buildPreview($link, $input);
        $selectedFields = array_values($preview['selected_fields']);

        $link->update([
            'last_price_value_id' => $preview['price']['price_value_id'] ?? null,
            'last_selected_fields' => $selectedFields,
            'last_media_ids' => collect($preview['images'] ?? [])->pluck('id')->values()->all(),
            'include_facts' => (bool) ($input['include_facts'] ?? true),
            'last_prepared_at' => now(),
        ]);
        $link->transfers()->create([
            'mode' => 'preview',
            'status' => 'prepared',
            'selected_fields' => $selectedFields,
            'applied_fields' => [],
            'manual_fields' => array_values(array_intersect($selectedFields, self::MANUAL_FIELDS)),
            'source_snapshot' => $this->sourceSnapshot($preview),
        ]);

        return $preview + [
            'prepared_at' => $link->fresh()->last_prepared_at?->toIso8601String(),
        ];
    }

    public function applyPrice(
        AvitoListingGoodLink $link,
        array $input,
        ?AvitoConnection $connection = null,
    ): array {
        $preview = $this->buildPreview($link, $input);
        $selectedFields = array_values($preview['selected_fields']);

        if (isset($input['expected_fingerprint'])
            && ! hash_equals($preview['source_fingerprint'], (string) $input['expected_fingerprint'])) {
            throw new AvitoException(
                'Цена, единица товара или связь с объявлением изменились. Повторите предпросмотр.',
                'good_transfer_stale',
                409,
            );
        }

        if (! in_array('price', $selectedFields, true)) {
            throw new AvitoException(
                'Для прямого применения выберите поле «Цена».',
                'good_transfer_price_missing',
                422,
            );
        }

        if (! ($preview['price']['can_apply'] ?? false)) {
            throw new AvitoException(
                (string) ($preview['price']['block_reason'] ?? 'Выбранную цену нельзя передать в Avito.'),
                'good_transfer_price_invalid',
                422,
            );
        }

        $mutationStarted = false;
        try {
            $listing = $this->verifiedListing($link->avito_account_id, $link->avito_item_id);
            if (filled($listing['item']['autoload_item_id'] ?? null)) {
                throw new AvitoException(
                    'Объявление управляется XML-автозагрузкой. Измените цену в источнике автозагрузки: прямое обновление будет перезаписано.',
                    'good_transfer_managed_publication',
                    409,
                );
            }
            if ($connection && (int) ($this->listings->context($connection)['account']['id'] ?? 0) !== $link->avito_account_id) {
                throw new AvitoException('OAuth-подключение относится к другому аккаунту Avito.', 'good_link_account_mismatch', 409);
            }
            $result = $this->listings->performAction(
                $link->avito_account_id,
                $link->avito_item_id,
                'update_price',
                ['price' => $preview['price']['avito_value']],
                $connection,
                function () use ($link, $preview, $input, &$mutationStarted): void {
                    // Account checks and token refresh can take network round
                    // trips. Revalidate after them, immediately before POST.
                    $freshLink = $link->fresh();
                    if (! $freshLink || $freshLink->good_id !== $link->good_id
                        || ! hash_equals($preview['source_fingerprint'], $this->buildPreview($freshLink, $input)['source_fingerprint'])) {
                        throw new AvitoException('Товар или связь изменились во время проверки Avito. Повторите предпросмотр.', 'good_transfer_stale', 409);
                    }
                    $mutationStarted = true;
                },
            );
        } catch (Throwable $exception) {
            $unknown = $mutationStarted && $exception instanceof AvitoException && $exception->category === 'network';
            if (AvitoListingGoodLink::query()->whereKey($link->id)->exists()) {
                $link->transfers()->create([
                    'mode' => 'apply',
                    'status' => $unknown ? 'unknown' : 'failed',
                    'selected_fields' => $selectedFields,
                    'applied_fields' => [],
                    'manual_fields' => array_values(array_intersect($selectedFields, self::MANUAL_FIELDS)),
                    'source_snapshot' => $this->sourceSnapshot($preview),
                    'remote_meta' => [
                        'message' => Str::limit($exception->getMessage(), 500),
                        'category' => $exception instanceof AvitoException ? $exception->category : 'unexpected',
                    ],
                ]);
            }

            if ($unknown) {
                throw new AvitoException(
                    'Ответ на изменение цены не получен. Проверьте цену на Avito перед повторной отправкой.',
                    'good_transfer_unknown',
                    502,
                );
            }

            throw $exception;
        }

        $manualFields = array_values(array_intersect($selectedFields, self::MANUAL_FIELDS));
        $status = $manualFields === [] ? 'applied' : 'price_applied_manual_ready';
        $appliedAt = now();
        $linkChanged = DB::transaction(function () use ($link, $preview, $selectedFields, $input, $appliedAt, $status, $manualFields, $result): bool {
            $current = AvitoListingGoodLink::query()->whereKey($link->id)->lockForUpdate()->first();
            if (! $current || $current->good_id !== $link->good_id
                || $current->avito_account_id !== $link->avito_account_id
                || $current->avito_item_id !== $link->avito_item_id) {
                // The remote write succeeded. Do not turn it into a misleading
                // failure if another operator removed the link while it ran.
                return true;
            }
            $current->update([
                'last_price_value_id' => $preview['price']['price_value_id'],
                'price_basis' => $preview['price']['price_basis'],
                'last_selected_fields' => $selectedFields,
                'last_media_ids' => collect($preview['images'] ?? [])->pluck('id')->values()->all(),
                'include_facts' => (bool) ($input['include_facts'] ?? true),
                'last_prepared_at' => $appliedAt,
                'last_applied_at' => $appliedAt,
            ]);
            $current->transfers()->create([
                'mode' => 'apply',
                'status' => $status,
                'selected_fields' => $selectedFields,
                'applied_fields' => ['price'],
                'manual_fields' => $manualFields,
                'source_snapshot' => $this->sourceSnapshot($preview),
                'remote_meta' => Arr::only($result['remote'] ?? [], ['request_id', 'status', 'duration_ms']),
            ]);

            return false;
        });

        return [
            'status' => $status,
            'applied_fields' => ['price'],
            'manual_fields' => $manualFields,
            'price' => $preview['price'],
            'preview' => $preview,
            'remote' => $result['remote'] ?? null,
            'applied_at' => $appliedAt->toIso8601String(),
            'link_changed' => $linkChanged,
        ];
    }

    public function linkedMedia(AvitoListingGoodLink $link, GoodMedia $media): GoodMedia
    {
        if ($media->good_id !== $link->good_id || $media->type !== 'image' || ! $media->is_published) {
            throw new AvitoException(
                'Фотография не принадлежит привязанному Good или не опубликована.',
                'good_transfer_media',
                404,
            );
        }

        return $media;
    }

    private function buildPreview(AvitoListingGoodLink $link, array $input): array
    {
        $link->load('good');
        if (! $link->good) {
            throw new AvitoException('Связанный товар удалён. Обновите данные объявления.', 'good_transfer_stale', 409);
        }
        $good = $this->goodPayload($link->good, $link);
        $basis = $input['price_basis'] ?? $link->price_basis ?? ['mode' => 'unit'];
        $selectedFields = collect($input['fields'] ?? [])
            ->filter(fn ($field): bool => in_array($field, self::FIELDS, true))
            ->unique()
            ->values();

        if ($selectedFields->isEmpty()) {
            throw new AvitoException('Выберите хотя бы одно поле Good.', 'good_transfer_fields', 422);
        }

        $warnings = [];
        if (! $good['is_published']) {
            $warnings[] = 'Good не опубликован, но его данные можно подготовить вручную.';
        }

        $title = $selectedFields->contains('title') ? trim((string) $good['name']) : null;
        if ($title !== null && mb_strlen($title) > 50) {
            $warnings[] = 'Название длиннее 50 символов: проверьте лимит категории Avito перед копированием.';
        }

        $description = $selectedFields->contains('description')
            ? $this->description($good, (bool) ($input['include_facts'] ?? true), $basis)
            : null;
        if ($selectedFields->contains('description') && $description === '') {
            $warnings[] = 'В Good нет описания или дополнительных фактов.';
        }

        $price = $selectedFields->contains('price')
            ? $this->price($good, (int) ($input['price_value_id'] ?? 0), $warnings, $basis)
            : null;
        if ($price !== null) {
            $publication = AvitoPublication::query()
                ->where('avito_account_id', $link->avito_account_id)
                ->where('avito_item_id', $link->avito_item_id)
                ->where('status', '!=', 'archived')->whereHas('currentRevision')->first();
            if ($publication) {
                $price['can_apply'] = false;
                $price['managed_publication_id'] = $publication->id;
                $price['block_reason'] = 'Ценой управляет автозагрузка. Выберите цену и зафиксируйте новую версию во вкладке «Автозагрузка», иначе feed вернёт прежнюю цену.';
                $warnings[] = $price['block_reason'];
            }
        }
        $images = $selectedFields->contains('images')
            ? $this->images($good, (array) ($input['media_ids'] ?? []))
            : [];
        if ($selectedFields->contains('images') && $images === []) {
            $warnings[] = 'Не выбрано ни одной опубликованной фотографии Good.';
        }

        $manualFields = array_values(array_intersect($selectedFields->all(), self::MANUAL_FIELDS));
        if ($manualFields !== []) {
            $warnings[] = 'Название, описание и фото подготовлены для ручного переноса: универсального API редактирования этих полей у Avito нет.';
        }

        $result = [
            'source_of_truth' => 'good',
            'good' => Arr::only($good, [
                'id', 'name', 'slug', 'is_published', 'admin_url', 'public_url',
            ]),
            'selected_fields' => $selectedFields->all(),
            'direct_fields' => array_values(array_intersect($selectedFields->all(), self::DIRECT_FIELDS)),
            'manual_fields' => $manualFields,
            'title' => $title === null ? null : [
                'good_value' => $title,
                'avito_value' => Arr::get($input, 'avito.title'),
                'different' => trim((string) Arr::get($input, 'avito.title')) !== $title,
                'mode' => 'manual',
            ],
            'description' => $description === null ? null : [
                'good_value' => $description,
                'mode' => 'manual',
                'includes_facts' => (bool) ($input['include_facts'] ?? true),
            ],
            'price' => $price === null ? null : $price + [
                'avito_current_value' => is_numeric(Arr::get($input, 'avito.price'))
                    ? (float) Arr::get($input, 'avito.price')
                    : null,
                'different' => is_numeric(Arr::get($input, 'avito.price'))
                    ? (float) Arr::get($input, 'avito.price') !== (float) $price['avito_value']
                    : null,
                'mode' => 'api',
            ],
            'images' => $images,
            'warnings' => array_values(array_unique($warnings)),
            'field_capabilities' => $this->fieldCapabilities(),
        ];

        $result['source_fingerprint'] = hash('sha256', json_encode([
            'link' => [$link->id, $link->good_id, $link->avito_account_id, $link->avito_item_id],
            'saved_price_basis' => $link->price_basis,
            'fields' => $selectedFields->all(),
            'title' => $title,
            'description' => $description,
            'price' => $price,
            'images' => $images,
        ], JSON_THROW_ON_ERROR));

        return $result;
    }

    private function price(array $good, int $priceValueId, array &$warnings, array $basis): array
    {
        $price = collect($good['prices'] ?? [])->firstWhere('id', $priceValueId);
        if (! $price) {
            throw new AvitoException(
                'Выберите сохранённую общедоступную цену привязанного товара.',
                'good_transfer_price',
                422,
            );
        }

        $amount = $price['amount'] ?? null;
        $currency = strtoupper((string) ($price['currency_code'] ?? ''));
        $measurement = $good['measurement'];
        $canApply = is_numeric($amount) && (float) $amount >= 0 && $currency === 'RUB';
        $blockReason = null;

        if (! is_numeric($amount)) {
            $blockReason = 'У выбранного типа цены нет числового значения.';
        } elseif ($currency !== 'RUB') {
            $blockReason = 'Avito принимает цену объявления в рублях; автоматическая конвертация отключена.';
        } elseif (! ($measurement['measure_id'] ?? null) || blank($measurement['unit_label'] ?? null)) {
            $canApply = false;
            $blockReason = 'Укажите единицу цены в карточке товара перед передачей в Avito.';
        } elseif (($price['valid_from'] ?? null)
            && CarbonImmutable::parse($price['valid_from'])->startOfDay()->gt(CarbonImmutable::today())) {
            $canApply = false;
            $blockReason = 'Срок действия выбранной цены ещё не начался.';
        } elseif (($price['valid_to'] ?? null)
            && CarbonImmutable::parse($price['valid_to'])->startOfDay()->lt(CarbonImmutable::today())) {
            $canApply = false;
            $blockReason = 'Срок действия выбранной цены завершён.';
        }

        $calculated = $this->priceBasis->calculate($good, $amount, $basis);
        $canApply = $canApply && $calculated['can_apply'];
        $blockReason ??= $calculated['block_reason'];
        $avitoValue = $calculated['avito_value'];
        if ($canApply && abs($calculated['total_unrounded'] - (float) $avitoValue) > 0.00001) {
            $warnings[] = "Avito принимает целую цену: итог {$calculated['total_unrounded']} RUB будет округлён до {$avitoValue} RUB.";
        }
        if ($blockReason) {
            $warnings[] = $blockReason;
        }

        return [
            'price_value_id' => (int) $price['id'],
            'revision' => $price['revision'],
            'price_type' => $price['name'] ?? 'Цена',
            'price_type_code' => $price['code'] ?? null,
            'good_value' => $amount,
            'currency_code' => $currency,
            'avito_value' => $avitoValue,
            'can_apply' => $canApply,
            'block_reason' => $blockReason,
            'measurement' => $measurement,
            'unit_label' => $measurement['unit_label'],
            'basis' => 'good_unit',
            'valid_from' => $price['valid_from'] ?? null,
            'valid_to' => $price['valid_to'] ?? null,
        ] + Arr::except($calculated, ['can_apply', 'block_reason', 'avito_value']);
    }

    private function images(array $good, array $mediaIds): array
    {
        $ids = collect($mediaIds)->map(fn ($id): int => (int) $id)->filter()->unique()->take(10)->values();
        $available = collect($good['media'] ?? [])->keyBy('id');

        if ($ids->contains(fn (int $id): bool => ! $available->has($id))) {
            throw new AvitoException(
                'Одна из фотографий не принадлежит Good или не опубликована.',
                'good_transfer_media',
                422,
            );
        }

        return $ids->map(function (int $id) use ($available): array {
            $media = (array) $available->get($id);

            return Arr::only($media, [
                'id', 'title', 'url', 'full_url', 'mime_type', 'is_ava', 'download_url',
            ]);
        })->values()->all();
    }

    private function description(array $good, bool $includeFacts, array $basis): string
    {
        $parts = array_values(array_filter([
            trim((string) ($good['description'] ?? '')),
        ], fn (string $value): bool => $value !== ''));

        if ($includeFacts) {
            $facts = [];
            if (filled(Arr::get($good, 'measurement.unit_label'))) {
                $facts[] = 'Единица учёта товара: '.Arr::get($good, 'measurement.unit_label');
            }
            if ($priceFact = $this->priceBasis->descriptionFact($good, $basis)) {
                $facts[] = $priceFact;
            }
            if (is_numeric($good['denominator'] ?? null)) {
                $facts[] = 'Фасовка: '.$this->number((float) $good['denominator']).' кг';
            }
            if (filled(Arr::get($good, 'country.name'))) {
                $facts[] = 'Страна: '.Arr::get($good, 'country.name');
            }
            $facts[] = 'Наличие: '.match (Arr::get($good, 'availability.status')) {
                'in_stock' => 'в наличии',
                'out_of_stock' => 'нет в наличии',
                default => 'под заказ',
            };
            if (filled($good['public_url'] ?? null)) {
                $facts[] = (string) $good['public_url'];
            }
            if ($facts !== []) {
                $parts[] = implode("\n", $facts);
            }
        }

        return trim(implode("\n\n", $parts));
    }

    public function goodPayload(Good $good, ?AvitoListingGoodLink $link = null): array
    {
        $payload = $this->goods->goodPayload($good);
        $payload['avito_price_basis'] = $this->priceBasis->options($payload);
        // The public listing must never inherit wholesale/private price types or
        // an assumed RUB currency from the CRM messaging payload.
        $payload['prices'] = $good->priceTypeValues()
            ->where('is_published', true)
            ->whereHas('priceType', fn ($query) => $query->where('is_active', true)->where('is_public', true))
            ->with(['priceType.currency', 'currency'])
            ->orderBy('id')->get()->map(fn (GoodPriceTypeValue $price): array => [
                'id' => $price->id,
                'price_type_id' => $price->price_type_id,
                'name' => $price->priceType->name,
                'code' => $price->priceType->code,
                'is_public' => true,
                'revision' => $price->revision,
                'amount' => $price->price_gross,
                'currency_code' => strtoupper((string) ($price->currency?->code ?: $price->priceType->currency?->code)),
                'valid_from' => $price->valid_from?->format('Y-m-d'),
                'valid_to' => $price->valid_to?->format('Y-m-d'),
            ])->all();
        $payload['admin_url'] = route('Ameise.good.show', [
            'id' => $good->id,
            'slug' => $good->slug,
        ], false);

        if ($link) {
            $payload['media'] = collect($payload['media'] ?? [])->map(function (array $media) use ($link): array {
                $media['download_url'] = route('api.avito.listings.good-transfer.media', [
                    'item' => $link->avito_item_id,
                    'media' => $media['id'],
                    'account_id' => $link->avito_account_id,
                ], false);

                return $media;
            })->values()->all();
        }

        return $payload;
    }

    private function fieldCapabilities(): array
    {
        return [
            'title' => ['mode' => 'manual', 'label' => 'Название'],
            'description' => ['mode' => 'manual', 'label' => 'Описание'],
            'price' => ['mode' => 'api', 'label' => 'Цена'],
            'images' => ['mode' => 'manual', 'label' => 'Фотографии'],
        ];
    }

    private function sourceSnapshot(array $preview): array
    {
        return Arr::only($preview, [
            'source_of_truth', 'good', 'selected_fields', 'title', 'description', 'price', 'images', 'warnings',
        ]);
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ' '), '0'), '.');
    }
}
