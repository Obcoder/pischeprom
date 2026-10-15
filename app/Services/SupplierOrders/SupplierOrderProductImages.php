<?php

namespace App\Services\SupplierOrders;

use App\Models\Good;
use App\Services\Goods\GoodAvatarImages;

class SupplierOrderProductImages
{
    /** Use the product's existing thumbnail metadata without contacting storage. */
    public function url(Good $good): ?string
    {
        return $this->safeUrl(app(GoodAvatarImages::class)->url($good));
    }

    /** Email images must work outside the authenticated application. */
    public function safeUrl(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $value)
            || ! filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($value);
        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)) {
            return null;
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        $address = trim($host, '[]');
        if (filter_var($address, FILTER_VALIDATE_IP)) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        } elseif (! str_contains($host, '.') || preg_match('/\.(?:localhost|local|internal|lan)$/i', $host)) {
            return null;
        }

        // Stored goods avatars are raster files; exclude videos, documents and
        // application endpoints from messages even if legacy metadata is bad.
        if (! preg_match('/\.(?:jpe?g|png|gif|webp|avif)$/i', rawurldecode($parts['path'] ?? ''))) {
            return null;
        }

        return $value;
    }
}
