<?php

namespace Tests\Unit\AiPriceLists;

use App\Domain\AiPriceLists\Enums\VatMode;
use App\Domain\AiPriceLists\Normalization\CurrencyNormalizer;
use App\Domain\AiPriceLists\Normalization\LocalizedDecimalParser;
use App\Domain\AiPriceLists\Normalization\PackagingNormalizer;
use App\Domain\AiPriceLists\Normalization\VatNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NormalizationTest extends TestCase
{
    #[DataProvider('decimalValues')]
    public function test_localized_decimals_are_normalized_without_float_math(mixed $raw, ?string $expected): void
    {
        $this->assertSame($expected, (new LocalizedDecimalParser)->parse($raw));
    }

    public static function decimalValues(): array
    {
        return [
            'russian' => ['1 250,50', '1250.50'],
            'us' => ['1,250.50', '1250.50'],
            'mixed-eu' => ['1.250,50', '1250.50'],
            'nbsp' => ["12\u{00A0}500,05 ₽", '12500.05'],
            'thousands' => ['1,250', '1250'],
            'small fraction' => ['0,125', '0.125'],
            'request' => ['по запросу', null],
            'negative' => ['(15,20)', '-15.20'],
        ];
    }

    public function test_currency_vat_and_packaging_are_deterministic(): void
    {
        $decimal = new LocalizedDecimalParser;
        $currency = new CurrencyNormalizer;
        $vat = new VatNormalizer($decimal);
        $packaging = new PackagingNormalizer($decimal);

        $this->assertSame('RUB', $currency->normalize('1 250 руб.'));
        $this->assertSame('USD', $currency->normalize('$ 10.00'));
        $this->assertSame('CNY', $currency->normalize('цена, юань'));
        $this->assertNull($currency->normalize('валюта неизвестна'));

        $this->assertSame(['mode' => VatMode::Included, 'rate' => '20'], $vat->normalize('с НДС 20%'));
        $this->assertSame(VatMode::Excluded, $vat->normalize('без НДС')['mode']);
        $this->assertSame(VatMode::Unknown, $vat->normalize('')['mode']);

        $this->assertSame('20', $packaging->normalize('20×500 г')['units_per_package']);
        $this->assertSame('500', $packaging->normalize('20×500 г')['net_quantity']);
        $this->assertSame('g', $packaging->normalize('20×500 г')['net_quantity_unit']);
        $this->assertSame('12', $packaging->normalize('кор. 12 шт.')['units_per_package']);
        $this->assertSame('6', $packaging->normalize('6/1 кг')['units_per_package']);
    }
}
