<?php

namespace App\Domain\AiSales\Web;

use App\Domain\AiSales\Search\SearchProviderException;
use DOMDocument;
use DOMElement;
use DOMXPath;

class PublicPageTextExtractor
{
    public function __construct(
        private readonly PublicUrlNormalizer $urls,
    ) {}

    public function extract(string $body, string $contentType, string $sourceUrl): PublicPageExtract
    {
        if (preg_match('/<!ENTITY/i', $body) === 1) {
            throw new SearchProviderException('page_parse', 'page_dtd_blocked');
        }
        $withoutHtmlDoctype = preg_replace('/<!DOCTYPE\s+html\s*>/i', '', $body);
        if ($withoutHtmlDoctype === null || preg_match('/<!DOCTYPE/i', $withoutHtmlDoctype) === 1) {
            throw new SearchProviderException('page_parse', 'page_dtd_blocked');
        }
        if (str_starts_with($contentType, 'text/plain')) {
            $text = $this->boundedText($body);
            $channels = $this->channels($text);
            $text = $this->redactChannels($text);

            return new PublicPageExtract(null, null, [], $text, [], $channels, hash('sha256', $text));
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8">'.$body,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $loaded) {
            throw new SearchProviderException('page_parse', 'page_html_invalid');
        }

        $xpath = new DOMXPath($document);
        foreach (['script', 'style', 'noscript', 'template', 'form', 'iframe', 'object', 'embed', 'svg'] as $tag) {
            foreach ($xpath->query('//'.$tag) ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
        $title = $this->boundedText((string) ($xpath->evaluate('string(//title[1])') ?: ''), 512) ?: null;
        $description = $this->boundedText((string) ($xpath->evaluate(
            'string(//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"]/@content)',
        ) ?: ''), 1000) ?: null;
        $headings = [];
        foreach ($xpath->query('//h1|//h2|//h3') ?: [] as $heading) {
            $value = $this->boundedText($heading->textContent, 300);
            if ($value !== '') {
                $headings[] = $value;
            }
            if (count($headings) >= 20) {
                break;
            }
        }
        $visibleText = $this->boundedText((string) ($xpath->evaluate('string(//body)') ?: ''));
        $links = $this->sameDomainLinks($xpath, $sourceUrl);
        $channels = $this->channels(implode(' ', array_filter([
            $title,
            $description,
            ...$headings,
            $visibleText,
        ])));
        $title = $title !== null ? $this->redactChannels($title) : null;
        $description = $description !== null ? $this->redactChannels($description) : null;
        $headings = array_map(fn (string $heading): string => $this->redactChannels($heading), $headings);
        $visibleText = $this->redactChannels($visibleText);

        return new PublicPageExtract(
            $title,
            $description,
            array_values(array_unique($headings)),
            $visibleText,
            $links,
            $channels,
            hash('sha256', implode('|', [$title, $description, implode('|', $headings), $visibleText])),
        );
    }

    /** @return list<string> */
    private function sameDomainLinks(DOMXPath $xpath, string $sourceUrl): array
    {
        $sourceHost = $this->urls->host($sourceUrl);
        $links = [];
        foreach ($xpath->query('//a[@href]') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $href = trim($node->getAttribute('href'));
            if (! preg_match('#^https?://#i', $href)) {
                continue;
            }
            try {
                $normalized = $this->urls->normalize($href);
                if (! hash_equals($sourceHost, $this->urls->host($normalized))) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }
            $links[] = $normalized;
            if (count($links) >= 20) {
                break;
            }
        }

        return array_values(array_unique($links));
    }

    /** @return list<array{kind: string, value: string, contact_role: string}> */
    private function channels(string $text): array
    {
        $channels = [];
        preg_match_all('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/iu', $text, $emailMatches);
        foreach (array_slice(array_values(array_unique($emailMatches[0] ?? [])), 0, 10) as $email) {
            $email = mb_strtolower($email);
            $local = strstr($email, '@', true) ?: '';
            $channels[] = [
                'kind' => 'email',
                'value' => $email,
                'contact_role' => in_array($local, ['info', 'sales', 'office', 'contact', 'zakaz', 'order'], true)
                    ? 'business_general' : 'person_specific',
            ];
        }
        preg_match_all('/(?<!\d)(?:\+7|8)[\s()\-]*\d{3}[\s()\-]*\d{3}[\s\-]*\d{2}[\s\-]*\d{2}(?!\d)/u', $text, $phoneMatches);
        foreach (array_slice(array_values(array_unique($phoneMatches[0] ?? [])), 0, 10) as $phone) {
            $channels[] = ['kind' => 'telephone', 'value' => $phone, 'contact_role' => 'business_general'];
        }

        return array_slice($channels, 0, 20);
    }

    private function boundedText(string $value, ?int $maxCharacters = null): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        $maxCharacters ??= (int) config('ai-sales.prospecting.limits.max_public_text_bytes', 24_576);

        return mb_substr($value, 0, $maxCharacters);
    }

    private function redactChannels(string $text): string
    {
        $text = preg_replace('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/iu', '[public-contact-redacted]', $text) ?? $text;

        return preg_replace(
            '/(?<!\d)(?:\+7|8)[\s()\-]*\d{3}[\s()\-]*\d{3}[\s\-]*\d{2}[\s\-]*\d{2}(?!\d)/u',
            '[public-contact-redacted]',
            $text,
        ) ?? $text;
    }
}
