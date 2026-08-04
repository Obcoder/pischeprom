<?php

namespace App\Domain\AiPriceLists\Services;

use App\Domain\AiPriceLists\Contracts\FileScannerInterface;
use App\Domain\AiPriceLists\DTO\FileScanResult;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class ClamAvFileScanner implements FileScannerInterface
{
    public function scan(string $localPath): FileScanResult
    {
        $socketPath = trim((string) config('ai-price-lists.clamav_socket'));

        if ($socketPath === '') {
            throw new RuntimeException('ClamAV scanner selected without PRICE_LIST_CLAMAV_SOCKET.');
        }

        if (! is_file($localPath) || ! is_readable($localPath)) {
            throw new RuntimeException('The file cannot be opened for antivirus scanning.');
        }

        $binary = trim((string) config('ai-price-lists.clamdscan_binary', 'clamdscan'));
        $configPath = trim((string) config('ai-price-lists.clamd_config'));
        $timeout = max(1, (int) config('ai-price-lists.clamdscan_timeout_seconds', 120));

        if ($binary === '') {
            throw new RuntimeException('ClamAV client is not configured.');
        }

        $command = [$binary, '--stream', '--no-summary'];

        if ($configPath !== '') {
            $command[] = '--config-file='.$configPath;
        }

        $command[] = $localPath;
        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->disableOutput();

        try {
            $exitCode = $process->run();
        } catch (Throwable $exception) {
            throw new RuntimeException('ClamAV is unavailable.', previous: $exception);
        }

        return match ($exitCode) {
            0 => new FileScanResult(true, 'clamav'),
            1 => new FileScanResult(false, 'clamav', 'Обнаружено потенциально опасное содержимое.'),
            default => throw new RuntimeException('ClamAV returned an indeterminate result.'),
        };
    }
}
