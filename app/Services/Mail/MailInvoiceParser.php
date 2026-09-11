<?php

namespace App\Services\Mail;

/** Extracts invoice fields from PDF text without guessing missing values. */
class MailInvoiceParser
{
    private const NUMBER_MARKER = '(?:№|N(?:[oоº])?\.?|#)';

    private const MONTHS = [
        'января' => 1, 'февраля' => 2, 'марта' => 3, 'апреля' => 4,
        'мая' => 5, 'июня' => 6, 'июля' => 7, 'августа' => 8,
        'сентября' => 9, 'октября' => 10, 'ноября' => 11, 'декабря' => 12,
    ];

    /** @return array{is_invoice: bool, number: ?string, date: ?string, counterparty: ?string, heading: ?string} */
    public function parse(string $text): array
    {
        $result = ['is_invoice' => false, 'number' => null, 'date' => null, 'counterparty' => null, 'heading' => null];
        // Keep extraction work bounded even when a document contains a large text layer.
        $text = mb_substr($text, 0, 250000);
        $text = str_replace(["\r\n", "\r", "\u{00A0}", "\u{202F}"], ["\n", "\n", ' ', ' '], $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0E-\x1F\x7F]/u', '', $text) ?? '';

        $titlePattern = '/(?<![\p{L}])с\h*ч\h*[её]\h*т(?!\h*[-–—]\h*фактура)(?:\s+н\h*а\s+о\h*п\h*л\h*а\h*т\h*у\b|(?=\s*'.self::NUMBER_MARKER.'))/iu';
        preg_match_all($titlePattern, $text, $titles, PREG_OFFSET_CAPTURE);

        foreach ($titles[0] as [$title, $offset]) {
            $preceding = mb_strtolower(mb_substr(substr($text, 0, $offset), -50));
            if (preg_match('/(?:лицевой|расч[её]тный|банковский|корреспондентский)\s*$/u', $preceding)) {
                continue;
            }

            $remaining = mb_substr(substr($text, $offset + strlen($title)), 0, 500);
            $heading = $title;
            $number = null;
            $hasPaymentTitle = (bool) preg_match('/о\h*п\h*л\h*а\h*т\h*у/iu', $title);
            $marker = '';

            if (preg_match('/^\s*('.self::NUMBER_MARKER.')\s*/iu', $remaining, $match)) {
                $marker = $match[1];
                $heading .= $match[0];
                $remaining = substr($remaining, strlen($match[0]));
            }

            if (($marker !== '' || $hasPaymentTitle) && preg_match('/^\s*([\p{L}\p{N}][\p{L}\p{N}\/_.–—-]{0,79}(?:\h+[\/–—-]\h*[\p{L}\p{N}]+){0,4})(?![\p{L}\p{N}\/_.–—-])/u', $remaining, $match)) {
                $candidate = preg_replace('/\h+/u', '', rtrim($match[1], '.'));
                if (preg_match('/\d/u', $candidate) || preg_match('/^б[\/-]н$/iu', $candidate)) {
                    $number = $candidate;
                    $heading .= $match[0];
                    $remaining = substr($remaining, strlen($match[0]));
                }
            }

            // A long bank account labelled "Счет №" is not an invoice heading.
            if (! $hasPaymentTitle && ($number === null || preg_match('/^\d{18,}$/', $number))) {
                continue;
            }

            [$date, $dateText] = $this->extractDate($remaining);
            $heading .= $dateText;

            return [
                'is_invoice' => true,
                'number' => $number,
                'date' => $date,
                'counterparty' => $this->extractCounterparty($text),
                'heading' => $this->compact($heading),
            ];
        }

        return $result;
    }

    /** @return array{?string, string} */
    private function extractDate(string $text): array
    {
        $prefix = '^\s*(?:от\s*)?[«"“]?(?<day>\d{1,2})[»"”]?';
        $suffix = '(?!\d)(?:\h*г(?:од(?:а)?)?\.?)?';
        $numeric = '/'.$prefix.'\h*[.\/-]\h*(?<month>\d{1,2})\h*[.\/-]\h*(?<year>\d{4})'.$suffix.'/iu';
        $written = '/'.$prefix.'\s+(?<month>'.implode('|', array_keys(self::MONTHS)).')\s+(?<year>\d{4})'.$suffix.'/iu';

        if (preg_match($numeric, $text, $match)) {
            $month = (int) $match['month'];
        } elseif (preg_match($written, $text, $match)) {
            $month = self::MONTHS[mb_strtolower($match['month'])];
        } else {
            return [null, ''];
        }

        $day = (int) $match['day'];
        $year = (int) $match['year'];
        $date = checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;

        return [$date, $match[0]];
    }

    private function extractCounterparty(string $text): ?string
    {
        $label = '(?:Поставщик(?:\s*\(\s*Исполнитель\s*\))?|Исполнитель|Продавец)';
        $separator = '(?:\s*[:—-]\s*|\h*\n\s*|\h+(?=(?:ООО|АО|ПАО|ЗАО|ОАО|ИП|Общество|Индивидуальный)\b))';
        if (! preg_match('/(?<![\p{L}])'.$label.$separator.'/iu', $text, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $value = mb_substr(substr($text, $match[0][1] + strlen($match[0][0])), 0, 1500);
        $fields = '(?:Покупатель|Заказчик|Плательщик|Получатель|Грузополучатель|Грузоотправитель|Основание|Договор|Адрес|Телефон|Тел\.|Банк|Расч[её]тный\s+сч[её]т|Корреспондентский\s+сч[её]т)';
        $boundary = '/(?:\b(?:ИНН|КПП|ОГРНИП|ОГРН|БИК|ОКПО)\b|'.$fields.'\h*:|^\h*'.$fields.'\b|Сч[её]т\s+на\s+оплату|\b[РК]\s*[\/.]\s*[СC]\b|,\s*(?:\d{6}\b|г\.\s))/ium';
        $value = preg_split($boundary, $value, 2)[0] ?? '';
        $lines = preg_split('/\n/u', trim($value)) ?: [];
        $name = [];

        foreach (array_slice($lines, 0, 4) as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^(?:\d{6}\b|г\.\s|ул\.\s|Россия\b|РФ\b|№|Наименование\b|Товары\b|Услуги\b|Итого\b)/iu', $line)) {
                break;
            }
            $name[] = $line;
        }

        $name = trim($this->compact(implode(' ', $name)), " \t\n\r\0\x0B,;:");

        return $name !== '' && mb_strlen($name) <= 250 ? $name : null;
    }

    private function compact(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
