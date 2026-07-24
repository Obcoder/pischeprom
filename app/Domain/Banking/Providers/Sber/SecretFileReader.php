<?php

namespace App\Domain\Banking\Providers\Sber;

use App\Domain\Banking\Exceptions\BankConfigurationException;

class SecretFileReader
{
    public function read(?string $path, string $label, bool $mustBeOutsideRepository = true): string
    {
        if (! is_string($path) || trim($path) === '') {
            throw new BankConfigurationException("{$label} file is not configured.");
        }

        $resolved = realpath($path);

        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved)) {
            throw new BankConfigurationException("{$label} file is unavailable.");
        }

        if (
            $mustBeOutsideRepository
            && str_starts_with($resolved, base_path().DIRECTORY_SEPARATOR)
        ) {
            throw new BankConfigurationException("{$label} file must be outside the repository.");
        }

        if ($mustBeOutsideRepository) {
            $permissions = fileperms($resolved);

            if ($permissions !== false && (($permissions & 0777) & 0077) !== 0) {
                throw new BankConfigurationException("{$label} file permissions must be 0600 or stricter.");
            }
        }

        $value = trim((string) file_get_contents($resolved));

        if ($value === '') {
            throw new BankConfigurationException("{$label} file is empty.");
        }

        return $value;
    }
}
