<?php

return [
    // Optional CDN base URL whose origin is the root of the existing Yandex bucket.
    // URL rewriting also works for already stored avatar/thumbnail URLs.
    'avatar_cdn_url' => env('GOODS_AVATAR_CDN_URL'),
];
