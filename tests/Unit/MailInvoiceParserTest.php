<?php

namespace Tests\Unit;

use App\Services\Mail\MailInvoiceParser;
use PHPUnit\Framework\TestCase;

class MailInvoiceParserTest extends TestCase
{
    public function test_it_extracts_invoice_fields_and_supplier_instead_of_buyer(): void
    {
        $result = (new MailInvoiceParser)->parse(<<<'TEXT'
            АО «Банк» БИК 044525225
            Сч. № 40702810000000000123
            Счёт на оплату № ПП-0042/26 от 11.09.2026 г.
            Покупатель: ООО «Наш завод», ИНН 7700000001
            Поставщик (Исполнитель): ООО «Пищевые ингредиенты», ИНН 7800000002, КПП 780001001
            Адрес: 190000, г. Санкт-Петербург
            № Наименование Количество Цена Сумма
            TEXT);

        $this->assertSame([
            'is_invoice' => true,
            'number' => 'ПП-0042/26',
            'date' => '2026-09-11',
            'counterparty' => 'ООО «Пищевые ингредиенты»',
            'heading' => 'Счёт на оплату № ПП-0042/26 от 11.09.2026 г.',
        ], $result);
    }

    public function test_it_supports_wrapped_headings_names_and_russian_months(): void
    {
        $result = (new MailInvoiceParser)->parse(<<<'TEXT'
            СЧЕТ НА
            ОПЛАТУ №
            А-17/2 от «5»
            сентября 2026 года
            Исполнитель:
            Общество с ограниченной ответственностью
            «Торговый дом Север»
            ИНН 7701234567
            Заказчик: ООО «Покупатель»
            TEXT);

        $this->assertTrue($result['is_invoice']);
        $this->assertSame('А-17/2', $result['number']);
        $this->assertSame('2026-09-05', $result['date']);
        $this->assertSame('Общество с ограниченной ответственностью «Торговый дом Север»', $result['counterparty']);
        $this->assertSame('СЧЕТ НА ОПЛАТУ № А-17/2 от «5» сентября 2026 года', $result['heading']);
    }

    public function test_it_supports_short_invoice_headings_and_nonbreaking_spaces(): void
    {
        $result = (new MailInvoiceParser)->parse("Счет\u{00A0}N 123-AB от 29/02/2024\nПродавец ООО «Мука», 125000, г. Москва\nПокупатель: ИП Иванов");

        $this->assertSame('123-AB', $result['number']);
        $this->assertSame('2024-02-29', $result['date']);
        $this->assertSame('ООО «Мука»', $result['counterparty']);
    }

    public function test_it_supports_letter_spacing_and_an_invoice_without_number(): void
    {
        $result = (new MailInvoiceParser)->parse("С ч ё т н а о п л а т у № б/н от 2 мая 2026\nПоставщик: ИП Сидорова Анна Петровна\nИНН 123456789012");

        $this->assertTrue($result['is_invoice']);
        $this->assertSame('б/н', $result['number']);
        $this->assertSame('2026-05-02', $result['date']);
        $this->assertSame('ИП Сидорова Анна Петровна', $result['counterparty']);
    }

    public function test_it_keeps_unknown_fields_empty_and_does_not_borrow_other_dates(): void
    {
        $result = (new MailInvoiceParser)->parse("Договор от 01.01.2026\nСчет на оплату № 42\nПокупатель: ООО «Не поставщик»\nСрок оплаты 20.09.2026");

        $this->assertTrue($result['is_invoice']);
        $this->assertSame('42', $result['number']);
        $this->assertNull($result['date']);
        $this->assertNull($result['counterparty']);
        $this->assertSame('Счет на оплату № 42', $result['heading']);

        $incomplete = (new MailInvoiceParser)->parse('Счет на оплату от 11.09.2026');
        $this->assertTrue($incomplete['is_invoice']);
        $this->assertNull($incomplete['number']);
        $this->assertSame('2026-09-11', $incomplete['date']);
    }

    public function test_it_rejects_invalid_dates_without_replacing_them(): void
    {
        foreach (['29.02.2025', '31 апреля 2026', '00.12.2026', '01.13.2026'] as $date) {
            $result = (new MailInvoiceParser)->parse("Счет на оплату № 42 от {$date}\nДата доставки 01.05.2026");
            $this->assertNull($result['date'], $date);
            $this->assertSame("Счет на оплату № 42 от {$date}", $result['heading']);
        }
    }

    public function test_it_does_not_classify_bank_details_tax_invoices_or_other_documents_as_payment_invoices(): void
    {
        foreach ([
            '',
            'Счет-фактура № 47 от 11.09.2026. Продавец: ООО «Завод»',
            'СЧЕТ — ФАКТУРА № 47 от 11.09.2026',
            "Банк получателя\nСч. № 40702810000000000123\nПолучатель: ООО «Завод»",
            'Счет № 40702810000000000123',
            'Расчётный счет № 12345',
            'Лицевой счет № 12345',
            'Договор № 42 от 11.09.2026. Поставщик: ООО «Завод»',
            'Оплата по счету № 123 от 11.09.2026',
        ] as $text) {
            $this->assertSame([
                'is_invoice' => false, 'number' => null, 'date' => null, 'counterparty' => null, 'heading' => null,
            ], (new MailInvoiceParser)->parse($text), $text);
        }
    }

    public function test_it_skips_bank_account_before_real_invoice_and_stops_at_address(): void
    {
        $result = (new MailInvoiceParser)->parse("Счет № 40702810000000000123\nСчет на оплату № 17 от 1 января 2026\nПоставщик:\nАО «Мельница»\n125000, Москва, ул. Лесная, 1\nПокупатель: ИП Петров");

        $this->assertSame('17', $result['number']);
        $this->assertSame('2026-01-01', $result['date']);
        $this->assertSame('АО «Мельница»', $result['counterparty']);
    }

    public function test_it_preserves_company_names_containing_role_words_and_normalizes_number_spacing(): void
    {
        $result = (new MailInvoiceParser)->parse("Счет на оплату № АБ-12 / 26 от 11.09.2026\nПоставщик: ООО «Банк Продуктов»\nПокупатель ООО «Договор»\nИНН 1234567890");

        $this->assertSame('АБ-12/26', $result['number']);
        $this->assertSame('2026-09-11', $result['date']);
        $this->assertSame('ООО «Банк Продуктов»', $result['counterparty']);
        $this->assertSame('Счет на оплату № АБ-12 / 26 от 11.09.2026', $result['heading']);
    }
}
