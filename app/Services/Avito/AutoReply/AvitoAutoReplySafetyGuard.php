<?php

namespace App\Services\Avito\AutoReply;

use Illuminate\Support\Str;

class AvitoAutoReplySafetyGuard
{
    /**
     * The guard deliberately blocks whole topics, not just imperative phrases.
     * A false positive only leaves the message to a human; a false negative is
     * still unable to expose data because the classifier has no tools and its
     * generated prose is never sent to Avito.
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
            '/(?:в\s+наличи|наличи[еяю]|остат(?:ок|ки|ков)|есть\s+ли.{0,40}(?:товар|штук|кг|упаков|мешк)|сколько\s+(?:штук|кг|упаков|мешк))/u',
            '/(?:товар|позици|продукт).{0,20}(?:есть|имеется|доступен)|(?:есть|имеется|доступен).{0,20}(?:товар|позици|продукт)/u',
            '/\d+(?:[.,]\d+)?\s*(?:штук|шт\.?|кг|килограмм|упаков|мешк|короб)/u',
            '/(?:во\s+сколько|когда|какого\s+числа|в\s+какое\s+время|через\s+сколько).{0,55}(?:привез|достав|приед|будет\s+заказ)/u',
            '/(?:срок|время|дата|интервал).{0,35}(?:достав|привоз)|(?:достав|привез).{0,35}(?:срок|время|дата|интервал)/u',
            '/(?:сколько\s+стоит|какая\s+цена|\bцен[аыеу]\b|стоимост|прайс|скидк)/u',
            '/(?:сколько.{0,20}стоит|по\s*ч[её]м|ценник)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        foreach (['вналичии', 'остатоктовара', 'остаткитовара', 'времядоставки', 'срокдоставки', 'датадоставки', 'сколькостоит'] as $needle) {
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
            '/(?:список\s+)?(?:всех\s+)?(?:поставщик|контрагент|клиент)(?:ов|ы|а)?/u',
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
            'поставщик', 'supplierlist', 'объемпродаж', 'объёмпродаж', 'salesvolume', 'выручк',
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
