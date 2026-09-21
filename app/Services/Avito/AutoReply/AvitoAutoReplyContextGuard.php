<?php

namespace App\Services\Avito\AutoReply;

/**
 * A final check on both fixed and generated replies. The complete local archive
 * supplies evidence that a detail was given; this guard never invents its value.
 */
class AvitoAutoReplyContextGuard
{
    private const DETAILS = [
        'city' => '(?:город\p{L}*|населенн\p{L}*\s+пункт\p{L}*)',
        'address' => 'адрес\p{L}*',
        'phone' => '(?:телефон\p{L}*|номер\s+(?:для\s+связи|контакт\p{L}*))',
        'name' => '(?:имя|фио|фамили\p{L}*)',
        'product' => '(?:товар\p{L}*|продукт\p{L}*|позици\p{L}*|наименован\p{L}*|состав\s+заказ\p{L}*)',
        'quantity' => '(?:объем\p{L}*|количеств\p{L}*|вес\p{L}*|сколько\s+(?:кг|килограмм\p{L}*|упаков\p{L}*|штук\p{L}*|меш\p{L}*))',
        'order_number' => '(?:номер\s+заказ\p{L}*|заказ\p{L}*\s+номер)',
    ];

    /** @param array<int, array{direction?: string, text?: string}> $conversation Chronological, without a recency limit. */
    public function responseBlockedReason(string $response, array $conversation, string $currentMessage = ''): ?string
    {
        $requested = $this->requestedDetails($this->normalize($response));
        if ($requested === []) {
            return null;
        }

        $known = [];
        $previousRequest = [];
        foreach ($conversation as $message) {
            if (! is_array($message) || ! is_string($message['text'] ?? null)) {
                continue;
            }

            $text = $this->speakerText($message['text']);
            if (($message['direction'] ?? null) === 'out') {
                $previousRequest = $this->requestedDetails($text);
                $known += $this->confirmedOrderDetails($text);
            } elseif (($message['direction'] ?? null) === 'in') {
                foreach ($this->explicitlyChangedDetails($text) as $field) {
                    unset($known[$field]);
                }
                $known += $this->customerDetails($text);
                if (count($previousRequest) === 1) {
                    $field = array_key_first($previousRequest);
                    if ($this->isDirectAnswer($text, $field)) {
                        $known[$field] = true;
                    }
                }
                // A later unrelated short message must not become the answer
                // to an old question simply because no new question followed.
                $previousRequest = [];
            }
        }

        $current = $this->speakerText($currentMessage);
        foreach ($this->explicitlyChangedDetails($current) as $field) {
            unset($known[$field]);
        }
        $known += $this->customerDetails($current);

        // An address is already a delivery destination. If it lacks a clear
        // city, let a human clarify it using the address rather than restarting
        // the generic city questionnaire. This does not infer the city's value.
        if (isset($requested['city'], $known['address'])) {
            return 'customer_details_already_known';
        }

        return array_intersect_key($requested, $known) === [] ? null : 'customer_details_already_known';
    }

    /** @return array<string, true> */
    private function requestedDetails(string $text): array
    {
        $requested = [];
        foreach (preg_split('/(?<=[.!?;])\s+|\n/u', $text) ?: [] as $sentence) {
            // Confirming that an earlier value still applies is useful, in
            // particular when a buyer returns after a long absence.
            if (preg_match('/(?:прежн\p{L}*|ранее\s+указан\p{L}*|тот\s+же|та\s+же|не\s+изменил\p{L}*|остал\p{L}*\s+(?:тем|таким)\s+же)/u', $sentence) === 1) {
                continue;
            }

            foreach (self::DETAILS as $field => $detail) {
                if ($this->matches($sentence, [
                    '/(?<!\p{L})(?:напишите|укажите|уточните|сообщите|назовите|подскажите|оставьте|пришлите|скажите|расскажите)[^.!?]{0,120}'.$detail.'/u',
                    '/(?:нужно|необходимо|просим|можете)[^.!?]{0,35}(?:указать|уточнить|написать|сообщить|назвать|оставить)[^.!?]{0,100}'.$detail.'/u',
                    '/(?<!\p{L})(?:како[йеюя]|какие|сколько)[^.!?]{0,70}'.$detail.'/u',
                    '/'.$detail.'[^.!?]{0,70}(?:вам\s+(?:нуж\p{L}*|интерес\p{L}*)|вас\s+интерес\p{L}*)/u',
                    '/(?:нужен|нужна|нужны|потребуется|потребуются)[^.!?]{0,30}(?:ваш\p{L}*\s+)?'.$detail.'/u',
                    '/(?<!\p{L})ваш\p{L}*\s+'.$detail.'[^.!?]{0,40}\?/u',
                ])) {
                    $requested[$field] = true;
                }
            }

            if (preg_match('/(?:как\s+(?:вас\s+зовут|к\s+вам\s+обращаться))/u', $sentence) === 1) {
                $requested['name'] = true;
            }
            if (preg_match('/(?:куда[^.!?]{0,50}(?:достав\p{L}*|отправ\p{L}*|везти)|где\s+вы\s+(?:живете|находитесь))/u', $sentence) === 1) {
                $requested['city'] = true;
                $requested['address'] = true;
            }
            if (preg_match('/сколько\s+(?:кг|килограмм\p{L}*|упаков\p{L}*|штук\p{L}*|меш\p{L}*)/u', $sentence) === 1) {
                $requested['quantity'] = true;
            }
        }

        return $requested;
    }

    /** @return array<string, true> */
    private function customerDetails(string $text): array
    {
        $known = [];
        $patterns = [
            'phone' => '/(?<![\p{L}\p{N}])\+?\d[\d ()-]{8,30}\d(?!\d)/u',
            'name' => '/(?:меня\s+зовут|мое\s+имя|имя\s*[:=—-])\s*[\p{L}][\p{L}-]{1,40}/u',
            'address' => '/(?:мой\s+адрес\s*[:=—-]?|адрес\s*[:=—-]|(?:улиц[аыеу]|ул\.|проспект\p{L}*|пр-т|переул\p{L}*|шоссе|набережн\p{L}*)\s+)\s*[\p{L}\d][\p{L}\d .,-]{1,100}\d/u',
            'city' => '/(?:я\s+из|мы\s+из|живу\s+в|живем\s+в|нахожусь\s+в|город\s*[:=—-]|г\.)\s*(?!(?:како|любо|ваш|наш|не\s|друг))[\p{L}][\p{L}-]{1,50}/u',
            'quantity' => '/(?<![\p{L}\p{N}])\d+(?:[.,]\d+)?\s*(?:кг|килограмм\p{L}*|грамм\p{L}*|тонн\p{L}*|литр\p{L}*|штук\p{L}*|шт|меш\p{L}*|упаков\p{L}*|короб\p{L}*)(?!\p{L})/u',
            'product' => '/(?:хочу\s+заказать|заказываю|мне\s+нуж\p{L}*|нам\s+нуж\p{L}*|нуж\p{L}*|беру|возьму)\s+(?:\d+(?:[.,]\d+)?\s*(?:кг|килограмм\p{L}*|упаков\p{L}*|меш\p{L}*)\s+)?(?!(?:доставк|самовывоз|прайс|каталог|заказ|помощ|адрес|номер|телефон|нов\p{L}*\s+адрес|информац|узнать|уточнить|сколько|совет|\d))[\p{L}][\p{L}-]{2,40}/u',
            'order_number' => '/заказ\s*(?:№|номер|#)\s*[\p{L}\d-]+/u',
        ];

        foreach ($patterns as $field => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $known[$field] = true;
            }
        }

        foreach (self::DETAILS as $field => $detail) {
            if ($this->matches($text, [
                '/'.$detail.'[^.!?]{0,45}(?:уже\s+(?:писал|написал|сообщал|указывал)|указан\p{L}*\s+(?:выше|в\s+заказе|у\s+вас)|есть\s+в\s+заказе)/u',
                '/(?:уже\s+)?(?:написал|писал|сообщал|указывал)\p{L}*[^.!?]{0,25}'.$detail.'/u',
            ])) {
                $known[$field] = true;
            }
        }

        return $known;
    }

    /** @return array<string, true> */
    private function confirmedOrderDetails(string $text): array
    {
        $known = [];
        // Public company facts and outgoing questions do not establish buyer
        // details. Only explicit confirmation fields are used as evidence.
        if (preg_match('/(?:^|\s)доставка\s*[:=—-]\s*(?!(?:есть|возможна|бесплат|платная|по\s|в\s|уточн|город|адрес|любой|любые|все|другие))[\p{L}][\p{L}-]{1,50}/u', $text) === 1) {
            $known['city'] = true;
            if (preg_match('/доставка\s*[:=—-][^.!?]{0,160}\d/u', $text) === 1) {
                $known['address'] = true;
            }
        }
        if (preg_match('/(?:заказ\s*(?:№|номер|#)\s*[\p{L}\d-]+|заказ\s+подтвержден)/u', $text) === 1) {
            $known['order_number'] = true;
            if (preg_match('/\d+(?:[.,]\d+)?\s*(?:кг|килограмм\p{L}*|штук\p{L}*|шт|упаков\p{L}*|меш\p{L}*)(?!\p{L})/u', $text) === 1) {
                $known['product'] = true;
                $known['quantity'] = true;
            }
        }

        return $known;
    }

    private function isDirectAnswer(string $text, string $field): bool
    {
        if (! in_array($field, ['city', 'name'], true) || mb_strlen($text) > 80
            || preg_match('/[?]|(?:^|\s)(?:не|нет|да|здравствуйте|привет|спасибо|пока|потом|почему|зачем|как|какой|какая|где|когда|ваш|наш|любой)(?:\s|[.!?,]|$)/u', $text) === 1) {
            return false;
        }

        return preg_match('/^[\p{L}][\p{L} .\'-]+$/u', $text) === 1
            && count(preg_split('/\s+/u', $text) ?: []) <= 4;
    }

    /** @return list<string> */
    private function explicitlyChangedDetails(string $text): array
    {
        $changed = [];
        foreach (['address' => ['address', 'city'], 'city' => ['address', 'city'], 'phone' => ['phone'], 'name' => ['name']] as $field => $affected) {
            if (preg_match('/(?:нов\p{L}*|друг\p{L}*|изменил\p{L}*|поменял\p{L}*)\s+'.self::DETAILS[$field].'/u', $text) === 1) {
                $changed = array_merge($changed, $affected);
            }
        }
        if (preg_match('/(?:нов\p{L}*|друг\p{L}*)\s+заказ\p{L}*/u', $text) === 1) {
            $changed = array_merge($changed, ['product', 'quantity', 'order_number']);
        }

        return $changed;
    }

    private function speakerText(string $text): string
    {
        $text = $this->normalize($text);

        // ConversationContext appends quotes after this marker. The quoted
        // speaker may be someone else, so neither facts nor questions in that
        // suffix can establish what this message's author supplied or asked.
        return trim(preg_split('/(?:^|\n)\[цитата в сообщении\]:/u', $text, 2)[0] ?? $text);
    }

    private function normalize(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x{00AD}\x{034F}\x{061C}\x{180E}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FEFF}]/u', '', $text) ?? $text;
        $text = str_replace('ё', 'е', mb_strtolower($text));

        return trim(preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text);
    }

    /** @param list<string> $patterns */
    private function matches(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
