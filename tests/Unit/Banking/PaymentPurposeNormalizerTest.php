<?php

namespace Tests\Unit\Banking;

use App\Domain\Banking\Reconciliation\PaymentPurposeNormalizer;
use PHPUnit\Framework\TestCase;

class PaymentPurposeNormalizerTest extends TestCase
{
    private PaymentPurposeNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new PaymentPurposeNormalizer;
    }

    public function test_it_normalizes_russian_payment_purpose_and_extracts_bounded_references(): void
    {
        $purpose = "  ОПЛАТА   ПО СЧЁТУ № АБ-123/7;\nза товар  ";

        $this->assertSame(
            'оплата по счету № аб-123/7; за товар',
            $this->normalizer->normalize($purpose)
        );
        $this->assertSame(
            ['АБ-123/7'],
            $this->normalizer->extractReferences($purpose)->all()
        );
    }

    public function test_it_supports_sale_and_order_reference_contexts(): void
    {
        $references = $this->normalizer
            ->extractReferences('Заказ N 781, продажа # SALE-44.')
            ->all();

        $this->assertSame(['781', 'SALE-44'], $references);
    }

    public function test_it_does_not_treat_an_unbounded_number_as_an_invoice_reference(): void
    {
        $this->assertSame(
            [],
            $this->normalizer->extractReferences('Оплата за товар, договор 12345 от 01.06.2026')->all()
        );
    }
}
