<?php

namespace App\Services\Avito\AutoReply;

use App\Models\AvitoAutoReplyRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AvitoAutoReplySafetyGuard
{
    /**
     * Restricted business information stays with a human. Public sales questions
     * are allowed through to the classifier, which only receives approved facts.
     */
    public function blockedReason(string $text): ?string
    {
        $normalized = $this->normalize($text);
        $compact = preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?: '';
        $compact .= ' '.$this->latinSkeleton($compact);

        if ($normalized === '' || mb_strlen($normalized) > 4000) {
            return 'invalid_message';
        }

        if ($this->matchesPromptInjection($normalized, $compact)) {
            return 'blocked_prompt_injection';
        }

        if ($this->matchesSensitiveDataRequest($normalized, $compact)) {
            return 'blocked_sensitive_request';
        }

        if ($this->matchesRestrictedBusinessTopic($normalized, $compact)) {
            return 'blocked_restricted_topic';
        }

        if ($this->looksEncoded($normalized)) {
            return 'blocked_encoded_instruction';
        }

        return null;
    }

    /** @param Collection<int, AvitoAutoReplyRule> $rules Only the selected eligible rules. */
    public function responseBlockedReason(string $text, Collection $rules): ?string
    {
        $normalized = $this->normalize($text);
        $compact = preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?: '';
        $compact .= ' '.$this->latinSkeleton($compact);
        if ($normalized === '' || mb_strlen($normalized) > 2000) {
            return 'response_invalid';
        }
        if ($this->matchesPromptInjection($normalized, $compact) || $this->looksEncoded($normalized)) {
            return 'response_prompt_injection';
        }
        if ($this->matchesSensitiveDataRequest($normalized, $compact)
            || preg_match('/(?:поставщик|контрагент|чуж.{0,15}(?:клиент|заказ))/u', $normalized) === 1) {
            return 'response_sensitive';
        }
        if ($this->matchesRestrictedBusinessTopic($normalized, $compact)) {
            return 'response_restricted';
        }

        $facts = $this->normalize($rules->map(fn (AvitoAutoReplyRule $rule) => (string) $rule->response_text)->implode("\n"));

        // A model must not invent contacts, destinations, numeric prices or other
        // exact values. Only facts from the selected rules can authorize them.
        foreach ($this->exactDetails($normalized) as $detail) {
            if (! in_array($detail, $this->exactDetails($facts), true)) {
                return 'response_unapproved_details';
            }
        }

        // For non-numeric business claims retain the complete approved wording.
        // Conversational connective text and questions can still be generated.
        foreach (preg_split('/(?<=[.!?;])\s+|\n/u', $normalized) ?: [] as $sentence) {
            if ($this->needsApprovedWording($sentence)
                && ! str_contains($facts, rtrim($sentence, '.!?; '))
                && ! $this->isApprovedFreeDeliveryParaphrase($sentence, $facts)) {
                return 'response_unapproved_details';
            }
        }

        return null;
    }

    private function exactDetails(string $text): array
    {
        preg_match_all('/(?:https?:\/\/|www\.)[^\s<>]+|[\p{L}\p{N}._%+-]+@[\p{L}\p{N}.-]+\.[\p{L}]{2,}|(?<![\p{L}\p{N}])@[\p{L}\p{N}_]+|(?<![\p{L}\p{N}])[\p{L}\p{N}-]+(?:\.[\p{L}\p{N}-]+)*\.[\p{L}]{2,24}(?:\/[^\s<>]*)?|\+?\d[\d ()-]{7,}\d|\d+(?:[.,:]\d+)?(?:\s*(?:килограмм\p{L}*|грамм\p{L}*|кг|мл|г|л|шт\.?|штук\p{L}*|руб\p{L}*|₽|%)(?!\p{L}))?/u', $text, $matches);

        return array_values(array_unique(array_map(fn (string $detail) => rtrim($detail, '.,;!?)'), $matches[0])));
    }

    private function needsApprovedWording(string $sentence): bool
    {
        if (preg_match('/^(?:(?:уточните|подскажите|напишите|сообщите)[,:]?\s*(?:пожалуйста[,:]?\s*)?)?какой\s+способ\s+оплаты\s+(?:вам(?:\s+будет)?\s+удобен|вы\s+предпочитаете|вас\s+интересует)[?.!]*$/u', $sentence) === 1) {
            return false;
        }

        // Questions may ask for product, pack size, volume or buyer city, but
        // must not suggest unapproved payment/delivery terms or product facts.
        $patterns = [
            '/(?:оплат|оплач|платеж|платёж|наличн|безнал|рассроч|предоплат|постоплат|картой|карт[уы]\s|ндс)/u',
            '/(?:адрес\s*[:—-]|наш\s+адрес|находимся|расположен|улиц|проспект|переул|шоссе|набережн)/u',
            '/(?:работаем|открыт|закрыт|график|режим\s+работ|выходн|будн|круглосуточ)/u',
            '/(?:\d|бесплат|даром|цен[аы]|стоимост|скидк).{0,35}(?:руб|₽|р\.|процент|достав|привоз)|(?:цен[аы]|стоимост|скидк)\s*(?:состав|равн|—|:|от\s|\d)/u',
            '/(?:доставляем|отправляем|отгружаем|доставка\s+(?:есть|возможна|осуществляется|по|курьер|транспорт|бесплат)|можем\s+(?:достав|отправ|отгруз))/u',
            '/(?:сертифицирован|гарантируем|гарантия\s|соответству.{0,15}гост|производ(?:итель|ство)\s*[:—-]|состав\s*[:—-]|срок\s+годности|хранится|изготовлен|сделан\s+из|содержит|не\s+содержит)/u',
            '/(?:мы|менеджер|сотрудник|специалист).{0,35}(?:позвонит|свяжется|отправит|передаст|перезвонит|пришлет|пришлёт)/u',
        ];

        return collect($patterns)->contains(fn (string $pattern) => preg_match($pattern, $sentence) === 1);
    }

    private function isApprovedFreeDeliveryParaphrase(string $sentence, string $facts): bool
    {
        $wording = '(?:(?:мы(?:\s+сами)?\s+)?(?:доставляем\s+бесплатно|бесплатно\s+доставляем)|(?:у\s+нас\s+)?(?:доставка\s+бесплатная|бесплатная\s+доставка))';

        return preg_match('/(?:^|[.!?]\s+)'.$wording.'(?:[.!?](?:\s|$)|$)/u', $facts) === 1
            && preg_match('/^'.$wording.'[.!]*$/u', $sentence) === 1;
    }

    private function normalize(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_KC) ?: $text;
        }
        $text = preg_replace('/[\x{00AD}\x{034F}\x{061C}\x{180E}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FEFF}]/u', '', $text) ?: $text;
        $text = Str::lower($text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?: $text);
    }

    /**
     * Makes mixed-alphabet and basic leetspeak obfuscation searchable without
     * changing the original message that is passed to the classifier.
     */
    private function latinSkeleton(string $text): string
    {
        return strtr($text, [
            'а' => 'a', 'в' => 'b', 'е' => 'e', 'к' => 'k', 'м' => 'm', 'н' => 'h',
            'о' => 'o', 'р' => 'p', 'с' => 'c', 'т' => 't', 'у' => 'y', 'х' => 'x',
            '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't',
        ]);
    }

    private function matchesPromptInjection(string $text, string $compact): bool
    {
        $patterns = [
            '/(?:игнор(?:ируй|ировать)|забудь|отмени|обойди|нарушь).{0,60}(?:инструкц|правил|ограничен|политик|предыдущ|системн|prompt)/u',
            '/(?:покажи|раскрой|повтори|выведи|напиши).{0,60}(?:системн(?:ый|ые)? (?:промпт|запрос|инструкц)|developer prompt|system prompt)/u',
            '/(?:prompt\s*injection|jailbreak|developer\s*mode|режим\s*разработчика|дан\s*mode|\bdan\b)/u',
            '/(?:притворись|представь,?\s*что\s*ты|сыграй\s*роль).{0,50}(?:администратор|разработчик|система|system|developer)/u',
            '/(?:^|\s)(?:system|developer|assistant)\s*(?:role|message|prompt)?\s*:/u',
            '/(?:новая|следующая)\s+(?:роль|инструкция|задача).{0,40}(?:вместо|важнее|приоритет)/u',
            '/["\']?(?:intent|confidence|unsafe|mixed|approved_intents|response_format|json_schema)["\']?\s*[:=]/u',
            '/(?:верни|ответь|выведи).{0,30}(?:json|структурированн).{0,30}(?:intent|сценари|класс)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        foreach (['systemprompt', 'developerprompt', 'promptinjection', 'jsonschema', 'approvedintents', 'игнорируйвсеинструкции', 'забудьпредыдущиеинструкции'] as $needle) {
            if (str_contains($compact, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function matchesRestrictedBusinessTopic(string $text, string $compact): bool
    {
        $patterns = [
            '/(?:в\s+наличи|наличи[еяю]|остат(?:ок|ки|ков)|остал(?:ось|ись|ся|ась).{0,20}\d|есть\s+ли.{0,40}(?:товар|штук|кг|упаков|мешк))/u',
            '/(?:товар|позици|продукт).{0,20}(?:есть|имеется|доступен)|(?:есть|имеется|доступен).{0,20}(?:товар|позици|продукт)/u',
            '/(?:\d+\s*(?:штук|шт\.?|кг|килограмм|упаков|мешк|короб).{0,20}(?:есть|имеется|доступн)|(?:есть|имеется|доступн).{0,20}\d+\s*(?:штук|шт\.?|кг|килограмм|упаков|мешк|короб))/u',
            '/(?:сколько|что).{0,25}(?:на\s+складе|осталось)|(?:на\s+складе).{0,25}(?:есть|имеется|доступн|лежит|хранится|законч|нет\s+(?:товар|продукт))|(?:товар|продукт|партия).{0,25}(?:законч|распродан|остал)/u',
            '/(?:есть|имеется)\s+(?:сейчас|еще|ещё)\b|(?:^|\s)(?:продан[оа]?|распродан[оа]?|закончился|закончилась|закончились)(?:\s|[?!.]|$)/u',
            '/(?:\b(?:inventory|in\s+stock|out\s+of\s+stock|stock\s+levels?|available\s+quantity)\b)/u',
            '/(?:во\s+сколько|когда|какого\s+числа|в\s+какое\s+время|через\s+сколько|как\s+скоро).{0,55}(?:привез|достав|приед|получ|отправ|отгруз|будет\s+заказ)/u',
            '/(?:срок|врем[яе]|дата|интервал).{0,35}(?:достав|привоз|поставк|отгруз|отправ)|(?:достав|привез|поставк|отгруз|отправ).{0,35}(?:срок|врем[яе]|дата|интервал)/u',
            '/(?:достав|привез|отправ|отгруз|получ.{0,12}заказ|заказ.{0,12}(?:будет|приед)|курьер.{0,12}(?:будет|приед)).{0,45}(?:сегодня|завтра|послезавтра|вечер|утр[оу]|днем|днём|час|сут(?:ок|ки|к)|дн[яей]|недел|месяц|понедельник|вторник|сред[уы]|четверг|пятниц|суббот|воскресен|январ|феврал|март|апрел|ма[яйе]|июн|июл|август|сентябр|октябр|ноябр|декабр|\d)/u',
            '/(?:сегодня|завтра|послезавтра|вечер|утр[оу]|понедельник|вторник|сред[уы]|четверг|пятниц|суббот|воскресен|через\s+\S+\s+(?:час|сут|дн|недел)).{0,45}(?:достав|привез|отправ|отгруз|заказ|курьер)/u',
            '/(?:быстр|долго|срочн|оператив|скоро|успеете).{0,25}(?:достав|привез|отправ|отгруз)|(?:достав|привез|отправ|отгруз).{0,25}(?:быстр|долго|срочн|оператив|скоро|позже|успеем)|(?:delivery|shipping)\s+(?:time|date|deadline)|(?:deliver|ship).{0,20}(?:today|tomorrow)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        // Product names are open-ended ("Мука есть?"). Exclude ordinary public
        // service questions from this broad availability fallback.
        foreach (preg_split('/[?!;.]|\s+(?:и|а|но)\s+/u', $text) ?: [] as $clause) {
            if (preg_match('/(?:^|\s)(?:есть|имеется|имеются|доступен|доступна|доступно)(?:\s|[?!.]|$)/u', $clause) === 1
                && preg_match('/(?:доставк|самовывоз|прайс|каталог|сайт|сертификат|оплат|фасовк|упаковк|документ|вопрос|безнал|розниц|скидк)/u', $clause) !== 1) {
                return true;
            }
        }

        foreach (['вналичии', 'остатоктовара', 'остаткитовара', 'времядоставки', 'срокдоставки', 'датадоставки', 'instock', 'outofstock'] as $needle) {
            if (str_contains($compact, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function matchesSensitiveDataRequest(string $text, string $compact): bool
    {
        $patterns = [
            '/(?:парол|password|passwd|секрет|secret|токен|token|credential|api[\s_.-]*key|\.env|(?:ключ|код|логин|уч[ёе]тные\s+данные).{0,24}доступ)/u',
            '/(?:баз[аые]\s+данных|database|sql\s+(?:select|dump)|дамп\s+базы|таблиц[аы]\s+базы)/u',
            '/(?:список|перечень|база|базу|контакт|телефон|имен|назван|адрес|данные|данных|информац|сведен).{0,40}(?:поставщик(?:ов|а|и)|контрагент(?:ов|а|ы)|клиент(?:ов|а|ы))|(?:поставщик(?:ов|а|и)|контрагент(?:ов|а|ы)|клиент(?:ов|а|ы)).{0,40}(?:список|перечень|контакт|телефон|имен|назван|адрес|данные|данных)|кто.{0,40}(?:поставщик|контрагент|клиент)/u',
            '/(?:покажи|выведи|перечисли|назови|раскрой|пришли|напиши|расскажи)(?:те)?\s+(?:(?:мне|нам|пожалуйста|всех|все|ваших|вашего|ваши|ваш|своих|про|о)\s+){0,5}(?:поставщик|контрагент|клиент)/u',
            '/(?:чуж|других|остальных).{0,25}(?:заказ|клиент)|(?:кому|сколько).{0,20}(?:продаете|продаёте|продали)/u',
            '/(?:у\s+кого|где|откуда).{0,35}(?:закуп|покупаете|берете|берёте|получаете.{0,15}товар)/u',
            '/(?:закупочн|закупк|себестоим|марж|наценк|оптов.{0,12}закуп|purchase\s+price|cost\s+price|profit\s+margin)/u',
            '/(?:за\s+сколько|почем|почём|по\s+чем|по\s+чём).{0,30}(?:берете|берёте|покупаете|покупали|закуп)|(?:входящ|внутренн).{0,15}(?:цен[аыу]|прайс)/u',
            '/(?:объ[её]м\s+продаж|выручк|оборот\s+(?:компании|продаж)|прибыл|sales\s+volume|revenue)/u',
            '/(?:внутренн(?:ие|яя|юю)\s+(?:данные|статистик|информац|отч[её]т)|коммерческ(?:ая|ую)\s+тайн)/u',
            '/(?:покажи|выведи|перечисли|экспортируй|скачай|пришли).{0,50}(?:заказ(?:ы|ов)|продаж|пользовател|сотрудник)/u',
            '/(?:покажи|выведи|напиши|перечисли|пришли|раскрой).{0,35}(?:все|любые|внутренн).{0,35}(?:данн|информац|сведен)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        foreach ([
            'парол', 'password', 'passwd', 'apikey', 'credential', 'секрет', 'secret', 'токен', 'accesstoken',
            'списокпоставщик', 'supplierlist', 'customerlist', 'clientdatabase', 'объемпродаж', 'объёмпродаж', 'salesvolume', 'выручк',
            'закупочн', 'себестоим', 'марж', 'наценк',
            'базаданных', 'databasedump', 'коммерческаятайна',
        ] as $needle) {
            if (str_contains($compact, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function looksEncoded(string $text): bool
    {
        return preg_match('/[a-z0-9+\/=]{48,}/i', $text) === 1
            || preg_match('/(?:[0-9a-f]{2}[\s:-]?){24,}/i', $text) === 1
            || preg_match('/(?:%[0-9a-f]{2}){16,}/i', $text) === 1
            || preg_match('/(?:\\\\u[0-9a-f]{4}){12,}/i', $text) === 1;
    }
}
