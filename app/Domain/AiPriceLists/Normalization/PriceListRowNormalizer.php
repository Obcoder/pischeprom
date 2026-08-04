<?php

namespace App\Domain\AiPriceLists\Normalization;

use App\Domain\AiPriceLists\Enums\ItemDecisionStatus;
use App\Domain\AiPriceLists\Enums\MatchClass;
use App\Domain\AiPriceLists\Enums\VatMode;
use App\Models\PriceListImport;
use App\Models\PriceListImportItem;

class PriceListRowNormalizer
{
    private const HEADERS = [
        'name' => ['наименование', 'название', 'товар', 'продукция', 'номенклатура', 'description', 'product', 'name'],
        'price' => ['цена', 'стоимость', 'прайс', 'price', 'cost'],
        'supplier_sku' => ['артикул', 'код товара', 'код поставщика', 'sku', 'арт.'],
        'manufacturer_sku' => ['артикул производителя', 'арт производителя'],
        'barcode' => ['штрихкод', 'ean', 'barcode'],
        'manufacturer' => ['производитель', 'изготовитель', 'manufacturer'],
        'brand' => ['бренд', 'brand', 'торговая марка'],
        'country_of_origin' => ['страна', 'происхождение', 'country'],
        'package' => ['упаковка', 'фасовка', 'тара', 'вес', 'объем', 'объём'],
        'unit' => ['единица', 'ед.', 'ед изм', 'unit'],
        'currency' => ['валюта', 'currency'],
        'vat' => ['ндс', 'vat'],
        'availability' => ['наличие', 'остаток', 'склад'],
        'minimum' => ['мин партия', 'минимальная партия', 'минимум', 'moq'],
        'notes' => ['примечание', 'комментарий', 'notes'],
    ];

    public function __construct(
        private readonly TextNormalizer $text,
        private readonly LocalizedDecimalParser $decimals,
        private readonly CurrencyNormalizer $currencies,
        private readonly VatNormalizer $vat,
        private readonly PackagingNormalizer $packaging,
    ) {}

    public function normalize(PriceListImport $import): array
    {
        $items = $import->items()->orderBy('position')->get();
        $groups = $items->groupBy(fn (PriceListImportItem $item) => implode('|', [
            $item->source_sheet ?: '',
            $item->source_page ?: '',
            $item->source_table ?: '',
        ]));
        $productRows = 0;
        $invalidRows = 0;
        $processedGroups = 0;
        $groupCount = max(1, $groups->count());

        foreach ($groups as $group) {
            $mapping = null;

            foreach ($group->take(30) as $candidate) {
                $candidateMapping = $this->headerMapping($candidate->raw_cells ?: []);

                if (isset($candidateMapping['name'], $candidateMapping['price'])) {
                    $mapping = $candidateMapping;
                    break;
                }
            }

            foreach ($group as $item) {
                $cells = array_map(fn ($value) => trim((string) $value), $item->raw_cells ?: []);
                $rowMapping = $this->headerMapping($cells);

                if (isset($rowMapping['name'], $rowMapping['price'])) {
                    $mapping = $rowMapping;
                    $this->markIgnored($item, 'Строка заголовков');

                    continue;
                }

                if ($this->isFooterOrNote($item->raw_text)) {
                    $this->markIgnored($item, 'Итоговая или служебная строка');

                    continue;
                }

                $normalized = $mapping
                    ? $this->fromMapping($cells, $mapping, $import)
                    : $this->fromHeuristics($cells, $import);

                if ($normalized === null || ! $normalized['raw_name'] || ! $normalized['price']) {
                    $item->forceFill([
                        'decision_status' => ItemDecisionStatus::Invalid,
                        'match_class' => MatchClass::Invalid,
                        'review_reason' => 'Не удалось надёжно определить название и цену.',
                        'warnings' => ['missing_required_fields'],
                    ])->save();
                    $invalidRows++;

                    continue;
                }

                $item->forceFill([
                    ...$normalized,
                    'decision_status' => ItemDecisionStatus::Unreviewed,
                    'match_class' => MatchClass::None,
                    'review_reason' => null,
                ])->save();
                $productRows++;
            }

            $processedGroups++;
            $import->forceFill([
                'stage_heartbeat_at' => now(),
                'progress' => min(74, 60 + (int) floor($processedGroups / $groupCount * 14)),
            ])->save();
        }

        return ['product_rows' => $productRows, 'invalid_rows' => $invalidRows];
    }

    private function headerMapping(array $cells): array
    {
        $mapping = [];

        foreach ($cells as $column => $value) {
            $value = $this->text->search((string) $value) ?: '';

            foreach (self::HEADERS as $field => $aliases) {
                if (collect($aliases)->contains(fn (string $alias) => $value === $this->text->search($alias) || str_contains($value, $this->text->search($alias)))) {
                    $mapping[$field] ??= (string) $column;
                }
            }
        }

        return $mapping;
    }

    private function fromMapping(array $cells, array $mapping, PriceListImport $import): ?array
    {
        $value = fn (string $field): ?string => isset($mapping[$field], $cells[$mapping[$field]]) ? $cells[$mapping[$field]] : null;
        $name = $this->text->display($value('name'));
        $priceRaw = $value('price');

        return $this->normalizedPayload(
            import: $import,
            name: $name,
            priceRaw: $priceRaw,
            supplierSku: $value('supplier_sku'),
            manufacturerSku: $value('manufacturer_sku'),
            barcode: $value('barcode'),
            manufacturer: $value('manufacturer'),
            brand: $value('brand'),
            country: $value('country_of_origin'),
            package: $value('package') ?: $value('unit'),
            currency: $value('currency') ?: $priceRaw,
            vat: $value('vat') ?: $priceRaw,
            availability: $value('availability'),
            minimum: $value('minimum'),
            notes: $value('notes'),
        );
    }

    private function fromHeuristics(array $cells, PriceListImport $import): ?array
    {
        if (count($cells) < 2) {
            return null;
        }

        $name = null;
        $priceRaw = null;

        foreach ($cells as $value) {
            if ($name === null && preg_match('/\p{L}/u', $value) && ! preg_match('/^(?:руб|rur|rub|usd|eur)$/iu', trim($value))) {
                $name = $value;
            }

            if ($this->decimals->parse($value) !== null) {
                $priceRaw = $value;
            }
        }

        return $this->normalizedPayload($import, $name, $priceRaw, package: implode(' ', $cells), currency: implode(' ', $cells), vat: implode(' ', $cells));
    }

    private function normalizedPayload(
        PriceListImport $import,
        ?string $name,
        ?string $priceRaw,
        ?string $supplierSku = null,
        ?string $manufacturerSku = null,
        ?string $barcode = null,
        ?string $manufacturer = null,
        ?string $brand = null,
        ?string $country = null,
        ?string $package = null,
        ?string $currency = null,
        ?string $vat = null,
        ?string $availability = null,
        ?string $minimum = null,
        ?string $notes = null,
    ): array {
        $packaging = $this->packaging->normalize($package);
        $vatResult = $this->vat->normalize($vat);
        $defaults = $import->document_defaults ?: [];

        return [
            'raw_name' => $this->text->display($name),
            'normalized_name' => $this->text->search($name),
            'supplier_sku' => $this->identifier($supplierSku),
            'manufacturer_sku' => $this->identifier($manufacturerSku),
            'barcode' => $this->identifier($barcode, 64),
            'manufacturer' => $this->text->display($manufacturer),
            'brand' => $this->text->display($brand),
            'country_of_origin' => $this->text->display($country),
            'package_description' => $packaging['description'],
            'units_per_package' => $packaging['units_per_package'],
            'net_quantity' => $packaging['net_quantity'],
            'net_quantity_unit' => $packaging['net_quantity_unit'],
            'price_basis_quantity' => $packaging['price_basis_quantity'],
            'price_basis_unit' => $packaging['price_basis_unit'],
            'minimum_order_quantity' => $this->decimals->parse($minimum),
            'price' => $this->decimals->parse($priceRaw),
            'currency_code' => $this->currencies->normalize($currency) ?: ($defaults['currency'] ?? null),
            'vat_mode' => $vatResult['mode'] !== VatMode::Unknown ? $vatResult['mode'] : ($defaults['vat_mode'] ?? VatMode::Unknown),
            'vat_rate' => $vatResult['rate'] ?: ($defaults['vat_rate'] ?? null),
            'availability' => $this->text->display($availability),
            'notes' => $notes ? mb_substr(trim($notes), 0, 2000) : null,
            'field_evidence' => [
                'name' => ['source' => 'document'],
                'price' => ['source' => 'document', 'raw' => mb_substr((string) $priceRaw, 0, 100)],
            ],
            'warnings' => [],
        ];
    }

    private function identifier(?string $value, int $limit = 191): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }

    private function markIgnored(PriceListImportItem $item, string $reason): void
    {
        $item->forceFill([
            'decision_status' => ItemDecisionStatus::Ignored,
            'match_class' => MatchClass::Ignored,
            'review_reason' => $reason,
        ])->save();
    }

    private function isFooterOrNote(?string $text): bool
    {
        return preg_match('/^(?:итого|всего|итог|примечание|условия|цены действительны)/iu', trim((string) $text)) === 1;
    }
}
