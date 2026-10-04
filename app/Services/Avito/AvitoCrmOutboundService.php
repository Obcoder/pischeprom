<?php

namespace App\Services\Avito;

use App\Domain\Avito\Exceptions\AvitoException;
use App\Models\AvitoChat;
use App\Models\Good;
use App\Models\GoodMedia;
use App\Models\GoodPriceTypeValue;
use App\Models\Order;
use App\Services\Goods\GoodStockService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Throwable;

class AvitoCrmOutboundService
{
    private const MESSAGE_LIMIT = 1000;

    private const MAX_GOOD_IMAGES = 5;

    public function __construct(
        private readonly AvitoMessengerService $messenger,
        private readonly GoodStockService $stock,
        private readonly AvitoOrderTableRenderer $orderTable,
    ) {}

    public function prepareGood(Good $good): Good
    {
        $good->loadMissing($this->goodRelations());

        if (! array_key_exists('stock_movements_exists', $good->getAttributes())) {
            $good->loadExists('stockMovements');
        }

        return $good;
    }

    public function goodRelations(): array
    {
        return [
            'country:id,name,flag',
            'seo',
            'stockAvailability',
            'priceTypeValues' => fn ($query) => $query
                ->where('is_published', true)
                ->whereHas('priceType', fn ($priceType) => $priceType->where('is_active', true))
                ->with(['priceType.currency', 'currency'])
                ->orderByDesc('updated_at'),
            'publishedMedia' => fn ($query) => $query
                ->where('type', 'image')
                ->where('is_published', true)
                ->orderByDesc('is_ava')
                ->orderBy('sort_order')
                ->orderBy('id'),
        ];
    }

    public function goodPayload(Good $good): array
    {
        $good = $this->prepareGood($good);
        $availability = $this->stock->availabilityPayload($good);

        return [
            'id' => $good->id,
            'name' => $good->name,
            'slug' => $good->slug,
            'description' => $this->plainText($good->description),
            'denominator' => $good->denominator,
            'is_published' => (bool) $good->is_published,
            'country' => $good->country ? [
                'id' => $good->country->id,
                'name' => $good->country->name,
                'flag' => $good->country->flag,
            ] : null,
            'availability' => $availability,
            'public_url' => $good->is_published && filled($good->slug)
                ? route('public.goods.show', ['good' => $good->slug], true)
                : null,
            'prices' => $good->priceTypeValues->map(function (GoodPriceTypeValue $price): array {
                $amount = $price->price_gross ?? $price->price_net;
                $currency = $price->currency?->code ?: $price->priceType?->currency?->code ?: 'RUB';

                return [
                    'id' => $price->id,
                    'price_type_id' => $price->price_type_id,
                    'name' => $price->priceType?->name ?: 'Цена',
                    'code' => $price->priceType?->code,
                    'is_public' => (bool) $price->priceType?->is_public,
                    'amount' => is_numeric($amount) ? (float) $amount : null,
                    'currency_code' => strtoupper((string) $currency),
                    'valid_from' => $price->valid_from?->format('Y-m-d'),
                    'valid_to' => $price->valid_to?->format('Y-m-d'),
                ];
            })->values(),
            'media' => $good->publishedMedia->map(fn (GoodMedia $media) => [
                'id' => $media->id,
                'title' => $media->title ?: $media->original_name ?: $good->name,
                'url' => $media->thumb_url ?: $media->url,
                'full_url' => $media->url ?: $media->thumb_url,
                'mime_type' => $media->mime_type,
                'is_ava' => (bool) $media->is_ava,
            ])->values(),
        ];
    }

    /**
     * @return array{sent: int, warnings: array<int, string>}
     */
    public function sendGood(AvitoChat $chat, Good $good, array $data): array
    {
        $good = $this->prepareGood($good);
        $price = filled($data['price_value_id'] ?? null)
            ? $good->priceTypeValues->firstWhere('id', (int) $data['price_value_id'])
            : null;

        if (filled($data['price_value_id'] ?? null) && ! $price) {
            throw new AvitoException('Выбранная цена не принадлежит товару или не опубликована.', 'good_price', 422);
        }

        $sent = 0;
        foreach ($this->textChunks($this->goodText($good, $price, $data)) as $chunk) {
            $this->messenger->sendText($chat, $chunk);
            $sent++;
        }

        $mediaIds = collect($data['media_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->take(self::MAX_GOOD_IMAGES)
            ->values();
        $media = $good->publishedMedia->whereIn('id', $mediaIds)->keyBy('id');
        $warnings = [];

        foreach ($mediaIds as $mediaId) {
            $item = $media->get($mediaId);
            if (! $item) {
                $warnings[] = "Фото #{$mediaId} не принадлежит товару или не опубликовано.";

                continue;
            }

            try {
                $this->sendGoodImage($chat, $item, $good);
                $sent++;
            } catch (Throwable $exception) {
                report($exception);
                $label = $item->title ?: $item->original_name ?: $good->name;
                $warnings[] = "Не удалось отправить фото «{$label}».";
            }
        }

        return compact('sent', 'warnings');
    }

    /**
     * @return array{sent: int, warnings: array<int, string>}
     */
    public function sendOrderConfirmation(AvitoChat $chat, Order $order): array
    {
        $order->loadMissing(['items', 'status', 'buildings.city', 'buildings.apartments', 'contactTelephone']);
        $sent = 0;

        try {
            foreach ($this->textChunks($this->orderText($order)) as $chunk) {
                $this->messenger->sendText($chat, $chunk);
                $sent++;
            }
        } catch (Throwable $exception) {
            report($exception);

            return [
                'sent' => $sent,
                'warnings' => [$sent > 0
                    ? 'В Avito отправлена только часть информации о заказе. Повторная отправка может продублировать сообщения.'
                    : 'Не удалось отправить информацию о заказе в Avito.'],
            ];
        }

        $images = [];
        try {
            $images = $this->orderTable->render($order);
            foreach ($images as $index => $path) {
                $this->messenger->sendImage($chat, new UploadedFile(
                    $path,
                    Str::slug($order->number).'-'.($index + 1).'.jpg',
                    'image/jpeg',
                    UPLOAD_ERR_OK,
                    true,
                ));
                $sent++;
            }

            return ['sent' => $sent, 'warnings' => []];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'sent' => $sent,
                'warnings' => ['Текст заказа отправлен в Avito, но таблица отправлена не полностью. Повторная отправка может продублировать сообщения.'],
            ];
        } finally {
            foreach ($images as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    private function goodText(Good $good, ?GoodPriceTypeValue $price, array $data): string
    {
        $lines = [];
        if (filled($data['intro'] ?? null)) {
            $lines[] = trim((string) $data['intro']);
        }
        $lines[] = $good->name;

        if (($data['include_description'] ?? true) && filled($good->description)) {
            $lines[] = Str::limit($this->plainText($good->description), 430, '…');
        }
        if (filled($good->denominator)) {
            $lines[] = 'Фасовка: '.$this->number((float) $good->denominator).' кг';
        }
        if ($good->country?->name) {
            $lines[] = 'Страна: '.$good->country->name;
        }

        $quantity = filled($data['quantity'] ?? null) ? (float) $data['quantity'] : null;
        if (($data['include_price'] ?? true) && $price) {
            $amount = $price->price_gross ?? $price->price_net;
            $currency = strtoupper((string) ($price->currency?->code ?: $price->priceType?->currency?->code ?: 'RUB'));
            if (is_numeric($amount)) {
                $lines[] = sprintf(
                    'Цена%s: %s %s%s',
                    $price->priceType?->name ? ' · '.$price->priceType->name : '',
                    $this->number((float) $amount),
                    $currency,
                    $quantity ? ' × '.$this->number($quantity).' = '.$this->number((float) $amount * $quantity).' '.$currency : '',
                );
            }
        }
        if (($data['include_stock'] ?? true)) {
            $availability = $this->stock->availabilityPayload($good);
            $lines[] = 'Наличие: '.match ($availability['status']) {
                'in_stock' => 'в наличии',
                'out_of_stock' => 'нет в наличии',
                default => 'под заказ',
            };
        }
        if (($data['include_link'] ?? true) && $good->is_published && filled($good->slug)) {
            $lines[] = route('public.goods.show', ['good' => $good->slug], true);
        }

        return trim(implode("\n", array_filter($lines, fn ($line) => filled($line))));
    }

    private function orderText(Order $order): string
    {
        $lines = ['Заказ '.$this->plainText($order->number)];
        if ($order->status?->name) {
            $lines[] = 'Статус: '.$this->plainText($order->status->name);
        }
        $lines[] = '';
        $lines[] = 'СОСТАВ ЗАКАЗА';
        $lines[] = 'Кол-во │ Цена │ Сумма';

        foreach ($order->items->values() as $index => $item) {
            $lines[] = '──────────────';
            $lines[] = ($index + 1).' │ '.$this->plainText($item->good_name);
            $currency = $this->plainText($item->currency_code ?: $order->currency_code);
            $lines[] = implode(' │ ', [
                $this->orderNumber((float) $item->quantity, 3),
                $item->price_gross !== null ? $this->orderNumber((float) $item->price_gross, 4).' '.$currency : 'Уточняется',
                $item->line_total !== null ? $this->orderNumber((float) $item->line_total, 2).' '.$currency : 'Уточняется',
            ]);
            if ($item->denominator !== null) {
                $lines[] = 'Фасовка: '.$this->orderNumber((float) $item->denominator, 4).' кг';
            }
        }

        $lines[] = '──────────────';
        if ($order->total_amount !== null) {
            $label = $order->items->contains(fn ($item) => $item->line_total === null)
                ? 'Итого по указанным ценам: '
                : 'Итого: ';
            $lines[] = $label.$this->orderNumber((float) $order->total_amount, 2).' '.$this->plainText($order->currency_code);
        }
        if ($order->total_weight !== null) {
            $lines[] = 'Общий вес: '.$this->orderNumber((float) $order->total_weight, 4).' кг';
        }
        if ($order->buildings->isNotEmpty()) {
            $building = $order->buildings->first();
            $lines[] = 'Доставка: '.$this->plainText(implode(', ', array_filter([
                $building->city?->name,
                $building->address_with_apartment,
            ])));
        }
        if ($order->delivery_date) {
            $lines[] = 'Дата доставки: '.$order->delivery_date->format('d.m.Y');
        }
        if (filled($order->preferred_delivery_time)) {
            $lines[] = 'Желаемое время: '.$this->plainText($order->preferred_delivery_time);
        }
        if (filled($order->contactTelephone?->number)) {
            $lines[] = 'Телефон: '.$this->plainText($order->contactTelephone->number);
        }

        return implode("\n", $lines);
    }

    private function orderNumber(float $value, int $precision): string
    {
        return rtrim(rtrim(number_format($value, $precision, ',', ' '), '0'), ',');
    }

    private function sendGoodImage(AvitoChat $chat, GoodMedia $media, Good $good): void
    {
        $disk = Storage::disk($media->disk ?: 'yandex');
        $path = $media->path ?: $media->thumb_path;

        if (! $path || ! $disk->exists($path)) {
            throw new AvitoException('Исходный файл фотографии товара отсутствует в хранилище.', 'good_media_missing', 422);
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'avito-good-');
        if ($temporaryPath === false) {
            throw new AvitoException('Не удалось подготовить временный файл фотографии.', 'good_media_temp', 500, true);
        }

        try {
            $manager = new ImageManager(new Driver);
            $image = $manager->read($disk->get($path));
            $image->scaleDown(width: 6000, height: 6000);
            $encoded = (string) $image->encode(new JpegEncoder(quality: 88));

            if (strlen($encoded) > 24 * 1024 * 1024) {
                $image->scaleDown(width: 3600, height: 3600);
                $encoded = (string) $image->encode(new JpegEncoder(quality: 80));
            }

            if (file_put_contents($temporaryPath, $encoded) === false) {
                throw new AvitoException('Не удалось записать подготовленную фотографию.', 'good_media_temp', 500, true);
            }

            $upload = new UploadedFile(
                $temporaryPath,
                Str::slug($good->name).'-'.$media->id.'.jpg',
                'image/jpeg',
                UPLOAD_ERR_OK,
                true,
            );
            $this->messenger->sendImage($chat, $upload);
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    /** @return array<int, string> */
    private function textChunks(string $text): array
    {
        $text = trim($text);
        $chunks = [];

        while (mb_strlen($text) > self::MESSAGE_LIMIT) {
            $head = mb_substr($text, 0, self::MESSAGE_LIMIT);
            $position = mb_strrpos($head, "\n");
            if ($position === false || $position < 400) {
                $position = mb_strrpos($head, ' ');
            }
            if ($position === false || $position < 400) {
                $position = self::MESSAGE_LIMIT;
            }

            $chunks[] = trim(mb_substr($text, 0, $position));
            $text = trim(mb_substr($text, $position));
        }

        if ($text !== '') {
            $chunks[] = $text;
        }

        return $chunks;
    }

    private function plainText(?string $value): string
    {
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?: $value);
    }

    private function number(float $value): string
    {
        $precision = abs($value - round($value)) < 0.000001 ? 0 : 2;

        return number_format($value, $precision, ',', ' ');
    }
}
