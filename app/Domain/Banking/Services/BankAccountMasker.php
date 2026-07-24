<?php

namespace App\Domain\Banking\Services;

final class BankAccountMasker
{
    public static function mask(?string $account): ?string
    {
        if ($account === null || $account === '') {
            return null;
        }

        $length = mb_strlen($account);

        if ($length <= 8) {
            return str_repeat('•', max(0, $length - 4)).mb_substr($account, -4);
        }

        return mb_substr($account, 0, 4)
            .str_repeat('•', max(4, $length - 8))
            .mb_substr($account, -4);
    }
}
