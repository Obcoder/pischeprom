<?php

namespace App\Services\Mail;

/** Server-rendered content only; never construct this from request HTML. */
final readonly class PreparedMailContent
{
    public function __construct(public string $html, public string $text) {}
}
