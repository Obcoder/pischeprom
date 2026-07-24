<?php

namespace Tests\Unit\Banking;

use App\Domain\Banking\Enums\BankTransactionDirection;
use App\Domain\Banking\Exceptions\BankMalformedResponseException;
use App\Domain\Banking\Providers\Sber\SberResponseNormalizer;
use Tests\TestCase;

class SberResponseNormalizerTest extends TestCase
{
    public function test_it_preserves_decimal_amount_as_a_string(): void
    {
        $transaction = (new SberResponseNormalizer)->transaction([
            'operationId' => 'operation-1',
            'operationDate' => '2026-07-24',
            'direction' => 'CREDIT',
            'amount' => '123456789012345.67',
            'currency' => 'RUB',
            'status' => 'POSTED',
        ]);

        $this->assertSame('123456789012345.67', $transaction->amount);
        $this->assertSame(BankTransactionDirection::Credit, $transaction->direction);
    }

    public function test_it_rejects_a_float_amount_from_untrusted_decoded_input(): void
    {
        $this->expectException(BankMalformedResponseException::class);

        (new SberResponseNormalizer)->transaction([
            'operationDate' => '2026-07-24',
            'amount' => 0.1,
        ]);
    }

    public function test_it_normalizes_documented_nested_statement_fields(): void
    {
        $page = (new SberResponseNormalizer)->statementPage([
            'closingBalance' => [
                'amount' => '1000004747.11',
                'currencyName' => 'RUR',
            ],
            'composedDateTime' => '2026-07-24T12:40:53+03:00',
            'transactions' => [[
                'operationId' => 'operation-nested',
                'operationDate' => '2026-07-24',
                'direction' => 'CREDIT',
                'amount' => [
                    'amount' => '1250.35',
                    'currencyName' => 'RUR',
                ],
                'paymentPurpose' => 'Оплата по счету № 42',
                'rurTransfer' => [
                    'payerName' => 'ООО Покупатель',
                    'payerInn' => '7701234567',
                    'payerKpp' => '770101001',
                    'payerAccount' => '40702810900000000001',
                    'payerBankName' => 'Банк плательщика',
                    'payerBankBic' => '044525225',
                    'payerBankCorrAccount' => '30101810400000000225',
                    'payeeName' => 'ООО Пищепром',
                    'payeeInn' => '7801234567',
                    'payeeKpp' => '780101001',
                    'payeeAccount' => '40702810900000000002',
                    'payeeBankName' => 'Банк получателя',
                    'payeeBankBic' => '044030653',
                    'payeeBankCorrAccount' => '30101810500000000653',
                ],
            ]],
            '_links' => [
                'next' => [
                    'href' => '?accountNumber=40702810900000000002&statementDate=2026-07-24&page=2',
                ],
            ],
        ], 'statement.daily');

        $transaction = $page->transactions->first();
        $balance = $page->balances->first();

        $this->assertSame('1250.35', $transaction->amount);
        $this->assertSame('RUB', $transaction->currency);
        $this->assertSame('ООО Покупатель', $transaction->payerName);
        $this->assertSame('7701234567', $transaction->payerInn);
        $this->assertSame('044525225', $transaction->payerBic);
        $this->assertSame('ООО Пищепром', $transaction->recipientName);
        $this->assertSame('044030653', $transaction->recipientBic);
        $this->assertSame('1000004747.11', $balance->amount);
        $this->assertSame('RUB', $balance->currency);
        $this->assertSame('2026-07-24 12:40:53', $balance->asOf->format('Y-m-d H:i:s'));
        $this->assertSame(
            '?accountNumber=40702810900000000002&statementDate=2026-07-24&page=2',
            $page->nextUrl
        );
    }

    public function test_it_rejects_missing_amount_and_malformed_statement_items(): void
    {
        $normalizer = new SberResponseNormalizer;

        foreach ([
            fn () => $normalizer->transaction([
                'operationDate' => '2026-07-24',
                'direction' => 'CREDIT',
            ]),
            fn () => $normalizer->transaction([
                'operationDate' => '2026-07-24',
                'amount' => '1.234',
            ]),
            fn () => $normalizer->transaction([
                'operationId' => str_repeat('x', 256),
                'operationDate' => '2026-07-24',
                'amount' => '1.00',
            ]),
            fn () => $normalizer->transaction([
                'operationDate' => '2026-07-24',
                'amount' => '1.00',
                'payerAccount' => str_repeat('4', 35),
            ]),
            fn () => $normalizer->statementPage([
                'transactions' => [['operationDate' => '2026-07-24', 'direction' => 'CREDIT', 'amount' => '1.00'], 'broken'],
            ], 'statement.daily'),
            fn () => $normalizer->statementPage([
                'transactions' => [],
                'links' => ['next' => ['href' => null]],
            ], 'statement.daily'),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Malformed Sber data must be rejected.');
            } catch (BankMalformedResponseException) {
                $this->assertTrue(true);
            }
        }
    }
}
