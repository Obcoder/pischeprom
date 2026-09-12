<?php

namespace App\Services\Avito\AutoReply;

/**
 * The assistant may introduce a public service, but a buyer's actual order
 * details belong to a human. This guard deliberately needs no city dictionary.
 */
class AvitoAutoReplyHandoffGuard
{
    public function requestsCustomerDetails(string $reply): bool
    {
        $text = $this->normalize($reply);
        $detail = '(?:город\p{L}*|населенн\p{L}*\s+пункт\p{L}*|адрес\p{L}*|товар\p{L}*|продукт\p{L}*|позици\p{L}*|наименован\p{L}*|фасовк\p{L}*|упаков\p{L}*|объем\p{L}*|количеств\p{L}*|контакт\p{L}*|телефон\p{L}*|номер\p{L}*|имя|способ\p{L}*\s+оплат\p{L}*)';

        return $this->matches($text, [
            '/(?<!\p{L})(?:напишите|укажите|уточните|сообщите|назовите|подскажите|оставьте|пришлите|скажите|расскажите|поделитесь)[^.!?]{0,120}'.$detail.'/u',
            '/(?<!\p{L})(?:необходимо|нужно|просим|можете)[^.!?]{0,35}(?:уточнить|указать|сообщить|назвать|написать|оставить)[^.!?]{0,100}'.$detail.'/u',
            '/(?<!\p{L})(?:как(?:ой|ая|ое|ие|ую|их|ого|ому|им|ом|ими)|сколько)[^.!?]{0,70}'.$detail.'/u',
            '/'.$detail.'[^.!?]{0,70}(?:вам\s+(?:нуж\p{L}*|интерес\p{L}*|удоб\p{L}*)|вас\s+интерес\p{L}*|вы\s+(?:предпочита\p{L}*|выбра\p{L}*))/u',
            '/(?:нужен|нужна|нужны|потребуется|потребуются)[^.!?]{0,30}(?:ваш\p{L}*\s+)?'.$detail.'/u',
            '/(?<!\p{L})(?:куда|в\s+какой\s+населенный\s+пункт)[^.!?]{0,60}(?:достав\p{L}*|отправ\p{L}*|везти)/u',
            '/(?:как\s+(?:к\s+вам\s+обращаться|вас\s+зовут)|где\s+вы\s+(?:находитесь|живете))/u',
            '/(?<!\p{L})(?:ваш\p{L}*|нужн\p{L}*)\s+'.$detail.'[^.!?]{0,40}\?/u',
            '/(?:как|каким\s+способом)[^.!?]{0,40}(?:вам[^.!?]{0,20}удоб\p{L}*|вы[^.!?]{0,20}(?:хотите|предпочита\p{L}*))[^.!?]{0,35}(?:оплат\p{L}*|платить)/u',
        ]);
    }

    public function customerDetailsReason(string $text): ?string
    {
        $original = mb_substr($text, 0, 4000);
        $text = $this->normalize($text);
        if ($text === '') {
            return null;
        }

        // Check concrete values before public-question wording: "10 кг, как
        // заказать?" still contains an actual order that a human must handle.
        $unit = '(?:кг|г|гр|мг|т|л|мл|шт|килограмм\p{L}*|грамм\p{L}*|тонн\p{L}*|литр\p{L}*|штук\p{L}*|меш\p{L}*|упаков\p{L}*|короб\p{L}*|пач\p{L}*|паллет\p{L}*|поддон\p{L}*|руб\p{L}*|₽)';
        if ($this->matches($text, [
            '/(?<![\p{L}\p{N}])\+?\d[\d ()-]{7,35}\d(?!\d)/u',
            '/[\p{L}\p{N}._%+-]{1,64}@[\p{L}\p{N}.-]{1,190}\.[\p{L}]{2,24}|(?<![\p{L}\p{N}])@[\p{L}\p{N}_]{3,64}/u',
            '/(?<![\p{L}\p{N}])\d{1,12}(?:[.,]\d{1,4})?\s*'.$unit.'(?!\p{L})/u',
            '/(?<!\p{L})(?:один|одну|одна|два|две|три|четыре|пять|шесть|семь|восемь|девять|десять|сто|пару)\s+'.$unit.'(?!\p{L})/u',
            '/(?<!\p{L})(?:улиц[аыеу]|ул\.|проспект\p{L}*|пр-т|переул\p{L}*|шоссе|набережн\p{L}*)\s+[\p{L}\d][\p{L}\d-]{1,50}/u',
            '/(?<!\p{L})(?:мой\s+адрес|адрес\s*[:=—-]|заказ\s*(?:№|номер)|артикул\s*[:№=—-])\s*[\p{L}\d]/u',
            '/(?<!\p{L})(?:город|г\.|пгт\.?|поселок|поселке|деревня|деревне|село|селе|населенный\s+пункт)(?!\p{L})\s*(?!(?:доставк|отправ|назначен|нуж|интерес|указ|возмож|любо|како|это|наш|ваш)[\p{L}]*\b)[\p{L}][\p{L}-]{1,50}/u',
            '/(?<!\p{L})(?:я|мы)\s+(?:из|в|живу\s+в|живем\s+в|нахожусь\s+в|находимся\s+в)\s+(?!(?:ваш|наш|люб|любо|како|это|поис)[\p{L}]*\b)[\p{L}][\p{L}-]{1,50}/u',
            '/(?<!\p{L})(?:живу|живем|нахожусь|находимся|мне|нам)\s+в\s+(?!(?:ваш|наш|люб|любо|како|это|поис)[\p{L}]*\b)[\p{L}][\p{L}-]{1,50}/u',
            '/(?:достав\p{L}*|отправ\p{L}*|привез\p{L}*)\s+в\s+(?!(?:ваш|наш|люб|любо|како|это|обо|друг)[\p{L}]*\b)[\p{L}][\p{L}-]{1,50}/u',
            '/(?<!\p{L})(?:меня\s+зовут|мое\s+имя|мой\s+телефон|мой\s+номер)(?!\p{L})/u',
        ])) {
            return 'customer_details_provided';
        }

        // A question in one sentence cannot authorize details in another
        // sentence or a later message joined into the same incoming bundle.
        foreach (preg_split('/[.!?;\r\n]+/u', $original) ?: [] as $part) {
            $content = $this->normalize($part);
            // Strip social openings, so "Здравствуйте! Ижевск" is treated
            // like "Ижевск" while a greeting alone remains public.
            $content = preg_replace('/^(?:(?:здравствуйте|здравствуй|здравстуйте|здраствуйте|привет|добрый\s+(?:день|вечер)|доброе\s+утро|подскажите|скажите|уточните|пожалуйста)[\s,.!?:;—-]*){1,5}/u', '', $content) ?? $content;
            $content = trim($content, " \t\n\r\0\x0B.!?,;:");
            if ($content === '' || $this->isPublicRequest($content)
                || preg_match('/^(?:я\s+ваш\s+клиент|мы\s+ваши\s+клиенты|(?:понятно[, ]+)?(?:спасибо|благодарю)(?:\s+(?:большое|вам))?(?:\s+за\s+(?:ответ|помощь|информацию))?|до\s+свидания|всего\s+доброго|хорошего\s+(?:дня|вечера)|понятно|ясно)$/u', $content) === 1) {
                continue;
            }

            // Short answers are ambiguous outside the conversation. Never
            // guess that a place, name or product is a new greeting.
            if (mb_strlen($content) <= 180
                && preg_match('/\p{L}|\p{N}/u', $content) === 1
                && count(preg_split('/\s+/u', $content) ?: []) <= 18) {
                return 'customer_details_provided';
            }
        }

        return null;
    }

    private function isPublicRequest(string $text): bool
    {
        $topic = '(?:доставк\p{L}*|самовывоз\p{L}*|прайс\p{L}*|каталог\p{L}*|цен[аыуе]|стоимост\p{L}*|фасовк\p{L}*|упаковк\p{L}*|оплат\p{L}*|сертификат\p{L}*|документ\p{L}*|скидк\p{L}*|сайт\p{L}*|розниц\p{L}*|заказ\p{L}*)';

        return $this->matches($text, [
            '/(?<!\p{L})(?:как|какой|какая|какие|какое|какую|где|когда|сколько|почем|зачем|почему|чем)(?!\p{L})/u',
            '/'.$topic.'[^.!?]{0,90}(?:есть|можно|возмож\p{L}*|у\s+вас|нуж\p{L}*|интерес\p{L}*|\?)/u',
            '/(?:есть|можно|возмож\p{L}*|нуж\p{L}*|интерес\p{L}*|хочу|хотим|пришлите|покажите|напишите|подскажите|расскажите)[^.!?]{0,90}'.$topic.'/u',
            '/^(?:авито\s+)?'.$topic.'[?!.]*$/u',
            '/(?:пришлите|напишите|подскажите|сообщите)[^.!?]{0,35}(?:ваш\s+)?(?:адрес|телефон|контакты)/u',
            '/(?:можно|хочу)\s+(?:посмотреть\s+товар|забрать\s+(?:самостоятельно|самому)|(?:я\s+)?сам\s+заберу\s+товар)/u',
        ]);
    }

    private function normalize(string $text): string
    {
        // Bounding the input also bounds every regular expression in this
        // pure guard. The main safety guard rejects oversized buyer messages.
        $text = mb_substr($text, 0, 4000);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x{00AD}\x{034F}\x{061C}\x{180E}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FEFF}]/u', '', $text) ?? $text;
        $text = str_replace('ё', 'е', mb_strtolower($text));

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
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
