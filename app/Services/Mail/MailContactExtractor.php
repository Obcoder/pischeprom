<?php

namespace App\Services\Mail;

use App\Models\MailMessage;
use App\Services\Avito\AvitoContactDetector;
use DOMDocument;
use Illuminate\Support\Str;

class MailContactExtractor
{
    public function __construct(private readonly AvitoContactDetector $phones) {}

    /** Local, read-only suggestions: never save contacts or execute instructions from a message. */
    public function extract(MailMessage $message): array
    {
        $html = mb_substr((string) $message->html, 0, 500000);
        $links = $this->links($html);
        $text = trim((string) ($message->text ?: $this->plainText($html) ?: $message->preview));
        $text = mb_substr($text, 0, 200000);
        $lines = preg_split('/\R+/u', $text) ?: [];
        $emails = [(string) $message->from_address];
        $websites = [];
        $phoneLinks = [];

        foreach ($links as $link) {
            if (str_starts_with(strtolower($link), 'mailto:')) {
                $emails[] = rawurldecode(explode('?', substr($link, 7))[0]);
            } elseif (str_starts_with(strtolower($link), 'tel:')) {
                $phoneLinks[] = rawurldecode(substr($link, 4));
            } elseif ($url = $this->normalizeWebsite($link)) {
                $websites[] = $url;
            }
        }

        preg_match_all('/[a-z0-9.!#$%&\x27*+\/=\?^_`{|}~-]+@[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\.[a-z]{2,24}/iu', $text, $matches);
        $emails = [...$emails, ...($matches[0] ?? [])];
        preg_match_all('~https?://[^\s<>"\x27]+|(?<![@\pL\pN.-])(?:www\.)?[\pL\pN](?:[\pL\pN-]*\.)+[\pL]{2,24}(?:/[^\s<>"\x27]*)?~iu', $text, $matches);
        foreach ($matches[0] ?? [] as $value) {
            if ($url = $this->normalizeWebsite($value)) {
                $websites[] = $url;
            }
        }

        // Tax identifiers and long bank details must not become phone candidates.
        $phoneText = preg_replace('/(?:ИНН|КПП|ОГРН(?:ИП)?|р\/?с|к\/?с|БИК)\s*[:№]?\s*[\d -]+/iu', '', $text) ?? $text;
        $phoneText = preg_replace('~https?://\S+~iu', '', $phoneText) ?? $phoneText;
        $phones = array_column($this->phones->phones($phoneText."\n".implode("\n", $phoneLinks)), 'normalized');
        preg_match_all('/\bИНН\s*[:№]?\s*(\d{10}|\d{12})(?!\d)/iu', $text, $taxMatches);
        $addresses = [];
        $companies = [];
        foreach ([...$lines, (string) $message->from_name] as $line) {
            $line = trim($line);
            if (preg_match('/(?:^|[\s,])(?:адрес(?:ом|у|а)?|ул(?:ица)?\.?|просп(?:ект)?\.?|пр[\s-]?т\.?|пер(?:еулок)?\.?|шоссе|наб(?:ережная)?\.?|бульвар|бул\.?|проезд|пл(?:ощадь)?\.?)(?:[\s,:]|$)/iu', $line)
                && preg_match('/\d/u', $line)) {
                $addresses[] = Str::limit($line, 255, '');
            }
            if (preg_match('/(?:^|\s)((?:ООО|АО|ПАО|ОАО|ЗАО|ИП|Общество с ограниченной ответственностью)\s+[^\r\n;]{2,200})/iu', $line, $company)) {
                $companies[] = trim($company[1]);
            }
        }

        return [
            'phones' => $this->unique($phones),
            'emails' => $this->unique(array_filter(array_map(fn ($email) => $this->normalizeEmail($email), $emails))),
            'websites' => $this->unique($websites),
            'addresses' => $this->unique($addresses),
            'companies' => $this->unique($companies),
            'tax_ids' => $this->unique($taxMatches[1] ?? []),
        ];
    }

    public function normalizeEmail(string $value): ?string
    {
        $value = Str::lower(trim($value));

        return strlen($value) <= 254 && filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    public function normalizeWebsite(string $value): ?string
    {
        $value = rtrim(trim($value), '.,;:!?)>]}');
        if ($value === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $value)) {
            return null;
        }
        if (! preg_match('~^[a-z][a-z0-9+.-]*:~i', $value)) {
            $value = 'https://'.ltrim($value, '/');
        }
        $parts = parse_url($value);
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $host = Str::lower($parts['host']);
        if (! str_contains($host, '.') || ! preg_match('/^[\pL\pN.-]+$/u', $host)) {
            return null;
        }
        $url = strtolower($parts['scheme']).'://'.$host
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .rtrim($parts['path'] ?? '', '/')
            .(isset($parts['query']) ? '?'.$parts['query'] : '');

        return mb_strlen($url) <= 255 ? $url : null;
    }

    private function links(string $html): array
    {
        if ($html === '') {
            return [];
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $links = [];
            foreach ($document->getElementsByTagName('a') as $anchor) {
                $links[] = $anchor->getAttribute('href');
                if (count($links) >= 100) {
                    break;
                }
            }

            return $links;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function plainText(string $html): string
    {
        $html = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $html) ?? $html;
        $html = preg_replace('~<(?:br\s*/?|/p|/div|/tr|/li)>~i', "\n", $html) ?? $html;

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function unique(array $values): array
    {
        return collect($values)->filter()->unique(fn ($value) => Str::lower($value))->take(20)->values()->all();
    }
}
