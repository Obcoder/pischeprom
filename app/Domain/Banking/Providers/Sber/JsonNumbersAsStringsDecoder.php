<?php

namespace App\Domain\Banking\Providers\Sber;

use App\Domain\Banking\Exceptions\BankMalformedResponseException;
use JsonException;

/**
 * PHP normally turns JSON decimals into binary floats. Banking payloads are
 * decoded with numeric literals quoted first, so monetary values remain exact
 * decimal strings throughout the application.
 */
class JsonNumbersAsStringsDecoder
{
    public function decode(string $json, string $endpointAlias): array
    {
        $length = strlen($json);
        $converted = '';
        $inString = false;
        $escaped = false;

        for ($index = 0; $index < $length; $index++) {
            $character = $json[$index];

            if ($inString) {
                $converted .= $character;

                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($character === '"') {
                $inString = true;
                $converted .= $character;

                continue;
            }

            if ($character === '-' || ctype_digit($character)) {
                $start = $index;
                $index++;

                while ($index < $length && preg_match('/[0-9eE+.\-]/', $json[$index]) === 1) {
                    $index++;
                }

                $number = substr($json, $start, $index - $start);
                $index--;

                if (preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+\-]?\d+)?$/', $number) !== 1) {
                    throw new BankMalformedResponseException('Sber response contains an invalid JSON number.', $endpointAlias);
                }

                $converted .= '"'.$number.'"';

                continue;
            }

            $converted .= $character;
        }

        try {
            $decoded = json_decode($converted, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BankMalformedResponseException(endpoint: $endpointAlias);
        }

        if (! is_array($decoded)) {
            throw new BankMalformedResponseException(endpoint: $endpointAlias);
        }

        return $decoded;
    }
}
