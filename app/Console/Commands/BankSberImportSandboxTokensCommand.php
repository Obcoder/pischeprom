<?php

namespace App\Console\Commands;

use App\Domain\Banking\Exceptions\BankingException;
use App\Domain\Banking\Providers\Sber\SberSandboxTokenImporter;
use App\Models\Entity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class BankSberImportSandboxTokensCommand extends Command
{
    protected $signature = 'bank:sber:import-sandbox-tokens
        {--owner-entity= : Entity ID of the organization that owns the bank accounts}
        {--connected-by= : User ID of the CRM administrator performing the import}
        {--access-token-file= : Protected plaintext access-token file outside the repository}
        {--refresh-token-file= : Protected plaintext refresh-token file outside the repository}
        {--access-expires-at= : Access-token expiry in ISO 8601 format}
        {--refresh-expires-at= : Refresh-token expiry in ISO 8601 format}
        {--replace : Explicitly replace an existing sandbox connection}';

    protected $description = 'Import Sber personal-area sandbox tokens from protected files into encrypted storage.';

    public function handle(SberSandboxTokenImporter $importer): int
    {
        $ownerId = $this->positiveId('owner-entity');
        $actorId = $this->positiveId('connected-by');

        if ($ownerId === null || $actorId === null) {
            return self::INVALID;
        }

        $accessTokenFile = $this->protectedSourceFile('access-token-file');
        $refreshTokenFile = $this->protectedSourceFile('refresh-token-file');

        if ($accessTokenFile === null || $refreshTokenFile === null) {
            return self::INVALID;
        }

        if (hash_equals($accessTokenFile, $refreshTokenFile)) {
            $this->error('Access and refresh tokens must use different source files.');

            return self::INVALID;
        }

        $accessExpiresAt = $this->expiry('access-expires-at');
        $refreshExpiresAt = $this->expiry('refresh-expires-at');

        if ($accessExpiresAt === null || $refreshExpiresAt === null) {
            return self::INVALID;
        }

        $owner = Entity::query()->find($ownerId);
        $actor = User::query()->find($actorId);

        if (! $owner || ! $actor) {
            $this->error('The owner organization or administrator was not found.');

            return self::FAILURE;
        }

        try {
            $connection = $importer->import(
                owner: $owner,
                actor: $actor,
                accessTokenFile: $accessTokenFile,
                refreshTokenFile: $refreshTokenFile,
                accessTokenExpiresAt: $accessExpiresAt,
                refreshTokenExpiresAt: $refreshExpiresAt,
                replace: (bool) $this->option('replace'),
            );
        } catch (BankingException $exception) {
            $this->error($exception->getMessage());
            $this->line('No network request was made. Token values were not printed.');

            return self::FAILURE;
        } catch (Throwable $exception) {
            Log::channel('banking')->error('Sandbox token import failed unexpectedly.', [
                'exception' => $exception::class,
            ]);
            $this->error('Sandbox token import failed unexpectedly.');
            $this->line('No network request was made. Token values were not printed.');

            return self::FAILURE;
        }

        $removed = $this->removePlaintextSources([
            $accessTokenFile,
            $refreshTokenFile,
        ]);

        $this->info(
            "Sandbox token pair was imported into encrypted storage for connection {$connection->id}."
        );
        $this->line('No network request was made. Token values were not printed.');

        if (! $removed) {
            $this->error('Encrypted import succeeded, but at least one plaintext source file could not be removed.');

            return self::FAILURE;
        }

        $this->info('Plaintext token source files were removed.');

        return self::SUCCESS;
    }

    private function positiveId(string $option): ?int
    {
        $value = filter_var($this->option($option), FILTER_VALIDATE_INT);

        if ($value === false || $value < 1) {
            $this->error("--{$option} must be a positive ID.");

            return null;
        }

        return $value;
    }

    private function protectedSourceFile(string $option): ?string
    {
        $value = $this->option($option);

        if (! is_string($value) || trim($value) === '') {
            $this->error("--{$option} must point to a protected token file.");

            return null;
        }

        if (is_link($value)) {
            $this->error("--{$option} must not be a symbolic link.");

            return null;
        }

        $resolved = realpath($value);

        if (
            $resolved === false
            || ! is_file($resolved)
            || ! is_readable($resolved)
            || str_starts_with($resolved, base_path().DIRECTORY_SEPARATOR)
        ) {
            $this->error("--{$option} must be a readable file outside the repository.");

            return null;
        }

        return $resolved;
    }

    private function expiry(string $option): ?CarbonImmutable
    {
        $value = $this->option($option);

        if (! is_string($value) || trim($value) === '') {
            $this->error("--{$option} is required.");

            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            $this->error("--{$option} must use an unambiguous ISO 8601 date or timestamp.");

            return null;
        }
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function removePlaintextSources(array $paths): bool
    {
        $removed = true;

        foreach ($paths as $path) {
            if (is_file($path) && ! @unlink($path)) {
                $removed = false;
            }
        }

        return $removed;
    }
}
