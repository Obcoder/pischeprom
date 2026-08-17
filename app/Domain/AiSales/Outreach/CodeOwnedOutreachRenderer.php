<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Support\AiCanonicalJson;

class CodeOwnedOutreachRenderer
{
    public function render(array $content): array
    {
        $paragraphs = [
            $content['greeting'], $content['introduction'], $content['value_proposition'],
            ...$content['evidence_points'], $content['call_to_action'], $content['closing'],
        ];
        $paragraphs = collect($paragraphs)->map(fn ($value) => trim((string) $value))->filter()->values();

        if ($paragraphs->count() > (int) config('ai-sales.outreach.limits.paragraphs', 6) + 4) {
            throw new PolicyViolation('outreach_render_limit', 'Outreach paragraph limit exceeded.');
        }

        $plain = $paragraphs->implode("\n\n");
        $html = $paragraphs->map(fn (string $paragraph) => '<p>'.e($paragraph).'</p>')->implode('');
        if (strlen($plain) > (int) config('ai-sales.outreach.limits.plain_bytes', 12_000)
            || strlen($html) > (int) config('ai-sales.outreach.limits.html_bytes', 24_000)) {
            throw new PolicyViolation('outreach_render_limit', 'Rendered outreach content exceeds its byte limit.');
        }

        $version = (string) config('ai-sales.outreach.renderer_version', 'stage12-renderer-v1');

        return [
            'subject' => trim($content['subject']),
            'plaintext' => $plain,
            'html' => $html,
            'renderer_version' => $version,
            'renderer_hash' => AiCanonicalJson::hash(['version' => $version, 'profile' => 'paragraphs-escaped-no-links']),
        ];
    }
}
