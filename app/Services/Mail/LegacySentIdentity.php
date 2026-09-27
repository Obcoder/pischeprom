<?php

namespace App\Services\Mail;

/** The two historical local-only IDs generated after SMTP, never sent as headers. */
class LegacySentIdentity
{
    public static function matches(?string $messageId): bool
    {
        $value = trim((string) $messageId);
        if (str_starts_with($value, '<') && str_ends_with($value, '>')) {
            $value = substr($value, 1, -1);
        }
        if (! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}@([^<>\s]+)\z/i', $value, $matches)) {
            return false;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return in_array(strtolower($matches[1]), array_filter(['local.pischeprom', strtolower((string) $host)]), true);
    }
}
