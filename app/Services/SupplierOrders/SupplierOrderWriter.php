<?php

namespace App\Services\SupplierOrders;

use App\Models\Currency;
use App\Models\Good;
use App\Models\Measure;
use App\Models\OrderItem;
use App\Models\Quotation;
use App\Models\SupplierOrder;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Goods\GoodMeasurement;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierOrderWriter
{
    public function save(?SupplierOrder $order, array $input, User $actor): SupplierOrder
    {
        $validated = $this->validated($input);
        $control = Validator::make($input, $order
            ? ['revision' => ['required', 'integer', 'min:1']]
            : ['create_key' => ['required', 'uuid']])->validate();
        $fingerprint = $this->fingerprint($validated);
        $legacyFingerprint = array_key_exists('auto_subject', $validated) ? null : $this->fingerprint($validated, true);

        return DB::transaction(function () use ($order, $actor, $validated, $control, $fingerprint, $legacyFingerprint): SupplierOrder {
            if ($order) {
                $order = SupplierOrder::lockForUpdate()->findOrFail($order->id);
                $this->assertRevision($order, (int) $control['revision']);
                abort_unless($order->status === 'draft', 409, 'Изменять можно только черновик заказа поставщику.');
                if ((int) $order->unit_id !== (int) $validated['unit_id']) {
                    $this->invalid('unit_id', 'Поставщика сохранённого заказа менять нельзя. Создайте новый заказ для другого поставщика.');
                }
            } else {
                // Serializes retried creation even before a row with this key exists.
                User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $existing = SupplierOrder::where('created_by', $actor->id)->where('create_key', $control['create_key'])->first();
                if ($existing) {
                    abort_unless(hash_equals($existing->create_payload_hash, $fingerprint)
                        || ($legacyFingerprint !== null && hash_equals($existing->create_payload_hash, $legacyFingerprint)), 409,
                        'С этим ключом уже создан другой заказ. Обновите данные перед созданием нового.');

                    return $existing->load(['items', 'unit', 'warehouse']);
                }
            }
            $normalized = $this->normalizeValidated($validated, true, $order);
            $new = ! $order;
            $order ??= new SupplierOrder([
                'created_by' => $actor->id, 'create_key' => $control['create_key'], 'create_payload_hash' => $fingerprint,
            ]);
            $order->fill(collect($normalized)->except(['items', 'unit'])->all());
            if ($order->supplier_sequence === null) {
                $order->supplier_sequence = app(SupplierOrderDefaults::class)->reserve($order->unit_id);
                $order->subject_date = ($order->created_at ?? now())->copy()->setTimezone('Europe/Moscow')->toDateString();
            }
            if ($order->auto_subject) {
                $order->subject = app(SupplierOrderDefaults::class)->subject($order->subject_date->toDateString(), $order->supplier_sequence);
            }
            $order->updated_by = $actor->id;
            if (! $new) {
                $order->revision++;
            }
            $order->save();
            // All document changes happen while the draft row is locked. Items
            // are snapshots and never resolve their price from customer pricing.
            $order->items()->delete();
            foreach ($normalized['items'] as $position => $item) {
                $order->items()->create([
                    ...collect($item)->except(['measurement', 'line_total', 'avatar_url', 'create_good'])->all(), 'position' => $position,
                    // PDO stringifies float bindings with PHP's precision setting.
                    // Bind fixed-scale decimals explicitly to retain all six/four digits.
                    'quantity' => (string) $this->decimal($item['quantity'], 6),
                    'unit_price' => $item['unit_price'] === null ? null : (string) $this->decimal($item['unit_price'], 4),
                    'kilograms_per_unit' => $item['kilograms_per_unit'] === null ? null : (string) $this->decimal($item['kilograms_per_unit'], 6),
                ]);
            }

            return $order->load(['items', 'unit', 'warehouse']);
        });
    }

    /** A read-only draft normalization shared by the editor and mail preview. */
    public function normalize(array $input): array
    {
        $validated = $this->validated($input);
        $order = null;
        if (! empty($input['supplier_order_id'])) {
            $reference = Validator::make($input, ['supplier_order_id' => ['required', 'integer', 'min:1'],
                'revision' => ['required', 'integer', 'min:1']])->validate();
            $order = SupplierOrder::findOrFail($reference['supplier_order_id']);
            $this->assertRevision($order, (int) $reference['revision']);
            abort_unless($order->status === 'draft' && (int) $order->unit_id === (int) $validated['unit_id'], 409,
                'Проверьте поставщика и состояние черновика.');
        }

        return $this->normalizeValidated($validated, false, $order);
    }

    public function changeStatus(SupplierOrder $order, string $status, int $revision, User $actor): SupplierOrder
    {
        return DB::transaction(function () use ($order, $status, $revision, $actor): SupplierOrder {
            $order = SupplierOrder::lockForUpdate()->findOrFail($order->id);
            $this->assertRevision($order, $revision);
            $allowed = match ($order->status) {
                'draft' => ['cancelled'], 'sent' => ['confirmed', 'cancelled'],
                'confirmed' => ['closed', 'cancelled'], default => [],
            };
            if (! in_array($status, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'Этот переход статуса недоступен. Отправка выполняется отдельной командой.']);
            }
            $order->fill(['status' => $status, 'revision' => $order->revision + 1, 'updated_by' => $actor->id])->save();

            return $order->load(['items', 'unit', 'warehouse']);
        });
    }

    public function delete(SupplierOrder $order, int $revision): void
    {
        DB::transaction(function () use ($order, $revision): void {
            $order = SupplierOrder::lockForUpdate()->findOrFail($order->id);
            $this->assertRevision($order, $revision);
            abort_unless($order->status === 'draft', 409, 'Удалить можно только черновик заказа поставщику.');
            $order->delete();
        });
    }

    public function data(SupplierOrder $order): array
    {
        $order->loadMissing(['items', 'unit', 'warehouse', 'items.good' => fn ($query) => $query->withoutEagerLoads()->select(['id', 'ava_thumb', 'ava_image'])]);
        $totals = [];
        $items = $order->items->map(function ($item) use (&$totals): array {
            $total = $item->unit_price === null ? null : $this->lineTotal($item->getRawOriginal('quantity'), $item->getRawOriginal('unit_price'));
            if ($total !== null) {
                $totals[$item->currency_code] = ($totals[$item->currency_code] ?? BigDecimal::zero())->plus($total);
            }

            return [
                'id' => $item->id, 'row_key' => $item->row_key, 'good_id' => $item->good_id, 'name' => $item->name,
                'create_good' => false,
                'avatar_url' => $item->good ? app(SupplierOrderProductImages::class)->url($item->good) : null,
                'measure_id' => $item->measure_id, 'unit_label' => $item->unit_label,
                'kilograms_per_unit' => $item->kilograms_per_unit, 'measurement' => $item->measurement(),
                'quantity' => $item->quantity, 'unit_price' => $item->unit_price,
                'currency_id' => $item->currency_id, 'currency_code' => $item->currency_code,
                'line_total' => $total?->toFloat(),
                'note' => $item->note, 'source_order_item_ids' => $item->source_order_item_ids,
                'quotation_id' => $item->quotation_id, 'quotation_snapshot' => $item->quotation_snapshot,
            ];
        })->all();
        $totals = array_map(fn (BigDecimal $total) => $total->toFloat(), $totals);

        return [
            'id' => $order->id, 'number' => $order->number, 'status' => $order->status,
            'status_label' => SupplierOrder::STATUS_LABELS[$order->status] ?? $order->status,
            'editable' => $order->status === 'draft', 'revision' => $order->revision,
            'unit_id' => $order->unit_id, 'unit' => $order->unit ? ['id' => $order->unit->id, 'name' => $order->unit->name] : null,
            'warehouse_id' => $order->warehouse_id, 'warehouse' => $order->warehouse ? ['id' => $order->warehouse->id, 'name' => $order->warehouse->name] : null,
            'delivery_date' => $order->delivery_date?->toDateString(),
            'subject' => $order->subject, 'body' => $order->body ?? '', 'mailbox' => $order->mailbox,
            'auto_subject' => $order->auto_subject, 'supplier_sequence' => $order->supplier_sequence,
            'subject_date' => $order->subject_date?->toDateString(),
            'to' => $order->to ?? [], 'cc' => $order->cc ?? [], 'include_prices' => $order->include_prices,
            'internal_note' => $order->internal_note, 'items' => $items, 'items_count' => count($items),
            'totals_by_currency' => $totals, 'has_unpriced_items' => collect($items)->contains(fn ($item) => $item['unit_price'] === null),
            'created_by' => $order->created_by, 'updated_by' => $order->updated_by,
            'created_at' => $order->created_at?->toISOString(), 'updated_at' => $order->updated_at?->toISOString(),
            'sent_at' => $order->sent_at?->toISOString(), 'mail_message_id' => $order->mail_message_id,
            'sending_started_at' => $order->sending_started_at?->toISOString(), 'send_error_code' => $order->send_error_code,
        ];
    }

    /** Preserve database decimal strings for rendered documents and SMTP snapshots. */
    public function mailData(SupplierOrder $order): array
    {
        $data = $this->data($order);
        foreach ($order->items as $index => $item) {
            $data['items'][$index]['quantity'] = (string) $this->decimal($item->getRawOriginal('quantity'), 6);
            $data['items'][$index]['unit_price'] = $item->getRawOriginal('unit_price') === null
                ? null : (string) $this->decimal($item->getRawOriginal('unit_price'), 4);
        }

        return $data;
    }

    private function assertRevision(SupplierOrder $order, int $revision): void
    {
        abort_unless($order->revision === $revision, 409, 'Заказ уже изменён другим сотрудником. Обновите данные.');
    }

    private function validated(array $input): array
    {
        return Validator::make($input, [
            'unit_id' => ['required', 'integer', 'min:1'],
            'warehouse_id' => ['nullable', 'integer', 'min:1'], 'delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'subject' => ['nullable', 'string', 'max:255', 'not_regex:/[\r\n]/'],
            'auto_subject' => ['sometimes', 'boolean'],
            'body' => ['nullable', 'string', 'max:30000'], 'mailbox' => ['nullable', 'email:rfc', 'max:254'],
            'to' => ['sometimes', 'array', 'max:5'], 'to.*' => ['required', 'email:rfc', 'max:254'],
            'cc' => ['sometimes', 'array', 'max:5'], 'cc.*' => ['required', 'email:rfc', 'max:254'],
            'include_prices' => ['sometimes', 'boolean'], 'internal_note' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.good_id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'items.*.row_key' => ['nullable', 'uuid', 'distinct:ignore_case'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.create_good' => ['sometimes', 'boolean'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,6'],
            'items.*.measurement' => ['required', 'array:measure_id,unit_label,kilograms_per_unit'],
            'items.*.measurement.measure_id' => ['nullable', 'integer', 'min:1'],
            'items.*.measurement.unit_label' => ['nullable', 'string', 'max:255'],
            'items.*.measurement.kilograms_per_unit' => ['nullable', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'between:0,999999999999', 'decimal:0,4'],
            'items.*.currency_id' => ['nullable', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string', 'max:2000'],
            'items.*.quotation_id' => ['nullable', 'integer', 'min:1'],
            'items.*.source_order_item_ids' => ['sometimes', 'array', 'max:200'],
            'items.*.source_order_item_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ])->validate();
    }

    private function normalizeValidated(array $input, bool $lockGoods = false, ?SupplierOrder $order = null): array
    {
        $unit = Unit::without(['fields', 'labels', 'telephones', 'uris'])->find($input['unit_id'], ['id', 'name']);
        if (! $unit) {
            $this->invalid('unit_id', 'Выберите существующего поставщика.');
        }
        if (! empty($input['warehouse_id']) && ! Warehouse::whereKey($input['warehouse_id'])->exists()) {
            $this->invalid('warehouse_id', 'Склад не найден.');
        }
        $ids = array_filter(array_column($input['items'], 'good_id'));
        $query = Good::whereIn('id', $ids)->with('measure')->orderBy('id');
        $goods = ($lockGoods ? $query->lockForUpdate() : $query)->get()->keyBy('id');
        $currencies = Currency::whereIn('id', array_filter(array_column($input['items'], 'currency_id')))->get()->keyBy('id');
        $sourceIds = collect($input['items'])->flatMap(fn ($item) => $item['source_order_item_ids'] ?? [])->unique();
        $sourceItems = OrderItem::whereIn('id', $sourceIds)->get(['id', 'good_id'])->keyBy('id');
        $quotationQuery = Quotation::without(['good', 'unit', 'currency', 'measure'])
            ->with(['measure', 'currency'])->whereIn('id', array_filter(array_column($input['items'], 'quotation_id')));
        $quotations = ($lockGoods ? $quotationQuery->lockForUpdate() : $quotationQuery)->get()->keyBy('id');
        $units = app(GoodMeasurement::class);
        $storedRows = $order && (int) $order->unit_id === (int) $unit->id ? $order->items()->get() : collect();
        $storedItems = $storedRows->whereNotNull('good_id')->keyBy('good_id');
        $storedKeys = $storedRows->whereNotNull('row_key')->keyBy('row_key');
        $measures = Measure::whereIn('id', collect($input['items'])->pluck('measurement.measure_id')->filter())->get()->keyBy('id');
        $items = [];
        $seenGoods = [];
        $seenRowKeys = [];
        foreach ($input['items'] as $index => $item) {
            $good = null;
            $registerGood = false;
            $rowKey = isset($item['row_key']) ? strtolower($item['row_key']) : null;
            if (empty($item['good_id'])) {
                if (! $rowKey) {
                    $this->invalid("items.{$index}.row_key", 'Для новой строки нужен постоянный ключ. Добавьте строку заново.');
                }
                if (! empty($item['quotation_id']) || ! empty($item['source_order_item_ids'])) {
                    $this->invalid("items.{$index}.good_id", 'Свободная строка не может ссылаться на предложение или заказ товара из каталога.');
                }
                $name = $this->nullableText($item['name'] ?? null);
                if (! $name) {
                    $this->invalid("items.{$index}.name", 'Укажите название товара.');
                }
                $measurement = $this->customMeasurement($item, $measures, $index);
                $registerGood = (bool) ($item['create_good'] ?? false);
                // A response can be lost after creating the Good. A subsequent
                // edit of this same row must reuse its registered product.
                $stored = $storedKeys->get($rowKey);
                if ($stored?->good_id) {
                    if (! $registerGood) {
                        $this->invalid("items.{$index}.good_id", 'Эта строка уже связана с товаром. Обновите заказ.');
                    }
                    $query = Good::whereKey($stored->good_id)->with('measure');
                    $good = ($lockGoods ? $query->lockForUpdate() : $query)->first();
                    if (! $good || $good->name !== $name || $good->measurement() !== $measurement) {
                        $this->invalid("items.{$index}.good_id", 'Товар этой строки уже создан. Обновите заказ перед изменением его реквизитов.');
                    }
                    $registerGood = false;
                } elseif ($registerGood && $lockGoods) {
                    $good = Good::create(['name' => $name, 'measure_id' => $measurement['measure_id'],
                        'unit_weight_kg' => null, 'is_published' => false]);
                    $registerGood = false;
                }
            } else {
                $good = $goods->get((int) $item['good_id']);
                if (! $good) {
                    $this->invalid("items.{$index}.good_id", 'Товар не найден.');
                }
                if ($item['create_good'] ?? false) {
                    $this->invalid("items.{$index}.create_good", 'Для существующего товара создание новой карточки недоступно.');
                }
                $linkedGood = $rowKey ? $storedKeys->get($rowKey)?->good_id : null;
                if ($linkedGood && (int) $linkedGood !== (int) $good->id) {
                    $this->invalid("items.{$index}.row_key", 'Эта строка уже связана с другим товаром. Добавьте товар отдельной строкой.');
                }
                $measurement = $units->assertConfigured($good, "items.{$index}.measurement");
                $units->assertSnapshot($good, $item['measurement'], "items.{$index}.measurement");
                $name = $good->name;
                $rowKey ??= $storedItems->get($good->id)?->row_key ?? ($lockGoods ? (string) Str::uuid() : null);
            }
            if ($rowKey) {
                if (in_array($rowKey, $seenRowKeys, true)) {
                    $this->invalid("items.{$index}.row_key", 'Ключ строки уже используется в этом заказе. Добавьте позицию заново.');
                }
                $seenRowKeys[] = $rowKey;
            }
            if ($good) {
                if (in_array($good->id, $seenGoods, true)) {
                    $this->invalid("items.{$index}.good_id", 'Товар уже добавлен в заказ. Измените количество существующей строки.');
                }
                $seenGoods[] = $good->id;
            }
            $price = isset($item['unit_price']) ? $this->decimal($item['unit_price'], 4) : null;
            $currency = isset($item['currency_id']) ? $currencies->get((int) $item['currency_id']) : null;
            if (isset($item['currency_id']) && ! $currency) {
                $this->invalid("items.{$index}.currency_id", 'Валюта не найдена.');
            }
            $quotationSnapshot = null;
            if (! empty($item['quotation_id'])) {
                $stored = $storedItems->get($good->id);
                // A saved draft keeps its accepted offer. Editing a quotation must
                // not silently reprice an already composed supplier order.
                $sameSnapshot = $stored && $stored->quotation_snapshot
                    && (int) $stored->quotation_id === (int) $item['quotation_id']
                    && (int) $stored->measure_id === (int) $measurement['measure_id']
                    && (int) $stored->currency_id === (int) $currency?->id
                    && $price !== null && $price->isEqualTo($this->decimal($stored->unit_price, 4))
                    && $stored->measurement() === $measurement;
                if ($sameSnapshot) {
                    $quotationSnapshot = $stored->quotation_snapshot;
                } else {
                    $quotation = $quotations->get((int) $item['quotation_id']);
                    if (! $quotation || (int) $quotation->good_id !== $good->id || (int) $quotation->unit_id !== $unit->id) {
                        $this->invalid("items.{$index}.quotation_id", 'Предложение должно принадлежать выбранному поставщику и товару.');
                    }
                    $resolved = app(SupplierQuotationPricing::class)->resolve($quotation, $measurement);
                    if ($resolved['quotation_warning']) {
                        $this->invalid("items.{$index}.quotation_id", $resolved['quotation_warning']);
                    }
                    $quotedPrice = $this->decimal($resolved['unit_price'], 4);
                    if (($price !== null && ! $price->isEqualTo($quotedPrice))
                        || ($currency && (int) $currency->id !== (int) $resolved['currency_id'])) {
                        $this->invalid("items.{$index}.quotation_id", 'Цена предложения изменилась. Обновите ассортимент и добавьте товар заново либо задайте цену вручную.');
                    }
                    $price = $quotedPrice;
                    $currency = $quotation->currency;
                    $quotationSnapshot = $resolved['quotation_snapshot'];
                }
            }
            if ($price !== null && ! $currency) {
                $this->invalid("items.{$index}.currency_id", 'Для цены укажите валюту.');
            }
            $quantity = $this->decimal($item['quantity'], 6);
            $total = $price === null ? null : $quantity->multipliedBy($price)->toScale(4, RoundingMode::HalfUp);
            if ($price !== null && ($price->isGreaterThanOrEqualTo('1000000000000') || $total->isGreaterThanOrEqualTo('1000000000000'))) {
                $this->invalid("items.{$index}.unit_price", 'Цена и сумма позиции должны быть меньше 1 трлн.');
            }
            $source = array_values(array_unique(array_map('intval', $item['source_order_item_ids'] ?? [])));
            foreach ($source as $sourceId) {
                if ((int) $sourceItems->get($sourceId)?->good_id !== (int) $good->id) {
                    $this->invalid("items.{$index}.source_order_item_ids", 'Связанные позиции заказов должны существовать и относиться к этому товару.');
                }
            }
            $items[] = [
                'row_key' => $rowKey, 'good_id' => $good?->id, 'name' => $name, ...$measurement, 'measurement' => $measurement,
                'create_good' => $registerGood,
                'avatar_url' => $good ? app(SupplierOrderProductImages::class)->url($good) : null,
                'quantity' => (string) $quantity, 'unit_price' => $price === null ? null : (string) $price, 'currency_id' => $currency?->id, 'currency_code' => $currency?->code,
                'line_total' => $total?->toFloat(), 'note' => $this->nullableText($item['note'] ?? null), 'source_order_item_ids' => $source,
                'quotation_id' => $item['quotation_id'] ?? null, 'quotation_snapshot' => $quotationSnapshot,
            ];
        }

        $header = $this->header($input);
        if ($header['auto_subject']) {
            $defaults = app(SupplierOrderDefaults::class)->forUnit($unit->id);
            $sequence = $order?->supplier_sequence ?? $defaults['supplier_sequence'];
            $date = $order?->subject_date?->toDateString() ?? $defaults['subject_date'];
            $header['subject'] = app(SupplierOrderDefaults::class)->subject($date, $sequence);
        }
        if (! $order && ! array_key_exists('body', $input)) {
            $header['body'] = SupplierOrderDefaults::BODY;
        }

        return [...$header, 'unit' => ['id' => $unit->id, 'name' => $unit->name], 'items' => $items];
    }

    private function header(array $input): array
    {
        $addresses = fn ($values) => array_values(array_unique(array_map(fn ($email) => mb_strtolower(trim($email)), $values)));

        return [
            'unit_id' => (int) $input['unit_id'], 'warehouse_id' => isset($input['warehouse_id']) ? (int) $input['warehouse_id'] : null,
            'delivery_date' => $input['delivery_date'] ?? null, 'subject' => trim($input['subject'] ?? ''),
            'auto_subject' => (bool) ($input['auto_subject'] ?? (trim($input['subject'] ?? '') === '')),
            'body' => trim($input['body'] ?? ''), 'mailbox' => isset($input['mailbox']) ? mb_strtolower(trim($input['mailbox'])) : null,
            'to' => $addresses($input['to'] ?? []), 'cc' => $addresses($input['cc'] ?? []),
            'include_prices' => true, 'internal_note' => $this->nullableText($input['internal_note'] ?? null),
        ];
    }

    private function customMeasurement(array $item, Collection $measures, int $index): array
    {
        $measureId = $item['measurement']['measure_id'] ?? null;
        $measure = $measureId ? $measures->get((int) $measureId) : null;
        if ($measureId && ! $measure) {
            $this->invalid("items.{$index}.measurement.measure_id", 'Единица измерения не найдена.');
        }
        if (($item['create_good'] ?? false) && ! $measure) {
            $this->invalid("items.{$index}.measurement.measure_id", 'Для создания товара выберите единицу учёта из справочника.');
        }
        $label = $measure?->name ?? $this->nullableText($item['measurement']['unit_label'] ?? null);
        if (! $label) {
            $this->invalid("items.{$index}.measurement.unit_label", 'Укажите единицу измерения свободной строки.');
        }

        // A free-form row never supplies a conversion into stock quantities.
        // Only known mass units receive their canonical kilograms factor.
        return ['measure_id' => $measure?->id, 'unit_label' => $label,
            'kilograms_per_unit' => $measure ? app(GoodMeasurement::class)->kilograms($measure->name) : null];
    }

    private function fingerprint(array $input, bool $legacy = false): string
    {
        $items = array_map(fn ($item) => [
            'good_id' => (int) ($item['good_id'] ?? 0), 'quantity' => (string) $this->decimal($item['quantity'], 6),
            'measurement' => ['measure_id' => (int) ($item['measurement']['measure_id'] ?? 0),
                'kilograms_per_unit' => isset($item['measurement']['kilograms_per_unit']) ? (float) $item['measurement']['kilograms_per_unit'] : null],
            'unit_price' => isset($item['unit_price']) ? (string) $this->decimal($item['unit_price'], 4) : null,
            'currency_id' => isset($item['currency_id']) ? (int) $item['currency_id'] : null,
            'quotation_id' => isset($item['quotation_id']) ? (int) $item['quotation_id'] : null,
            'note' => $this->nullableText($item['note'] ?? null),
            'source_order_item_ids' => array_map('intval', $item['source_order_item_ids'] ?? []),
            // Existing catalog fingerprints intentionally retain their original
            // shape so an old client can retry a pre-deploy creation safely.
            ...(empty($item['good_id']) ? ['custom' => [
                'row_key' => isset($item['row_key']) ? strtolower($item['row_key']) : null,
                'name' => $this->nullableText($item['name'] ?? null),
                'unit_label' => $this->nullableText($item['measurement']['unit_label'] ?? null),
                'create_good' => (bool) ($item['create_good'] ?? false),
            ]] : []),
        ], $input['items']);

        $header = $this->header($input);
        if ($legacy) {
            // Old clients can retry a draft saved before automatic subjects were
            // introduced. Match their exact original header, including its subject.
            unset($header['auto_subject']);
        } elseif ($header['auto_subject']) {
            // A preliminary sequence may change before the first save, and a
            // retry may happen on another day. Neither creates a different order.
            $header['subject'] = '';
        }

        return hash('sha256', json_encode([...$header, 'items' => $items], JSON_THROW_ON_ERROR));
    }

    private function nullableText(?string $value): ?string
    {
        return trim($value ?? '') === '' ? null : trim($value);
    }

    private function decimal(mixed $value, int $scale): BigDecimal
    {
        return BigDecimal::of(is_float($value) ? number_format($value, $scale, '.', '') : (string) $value)
            ->toScale($scale, RoundingMode::HalfUp);
    }

    private function lineTotal(mixed $quantity, mixed $price): BigDecimal
    {
        return $this->decimal($quantity, 6)->multipliedBy($this->decimal($price, 4))->toScale(4, RoundingMode::HalfUp);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
