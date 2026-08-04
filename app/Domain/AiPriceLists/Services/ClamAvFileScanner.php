<?php

namespace App\Domain\AiPriceLists\Services;

use App\Domain\AiPriceLists\Contracts\FileScannerInterface;
use App\Domain\AiPriceLists\DTO\FileScanResult;
use RuntimeException;

class ClamAvFileScanner implements FileScannerInterface
{
    public function scan(string $localPath): FileScanResult
    {
        $socketPath = trim((string) config('ai-price-lists.clamav_socket'));

        if ($socketPath === '') {
            throw new RuntimeException('ClamAV scanner selected without PRICE_LIST_CLAMAV_SOCKET.');
        }

        $socket = @stream_socket_client(
            str_starts_with($socketPath, 'tcp://') ? $socketPath : 'unix://'.$socketPath,
            $errorNumber,
            $errorMessage,
            5,
        );

        if (! is_resource($socket)) {
            throw new RuntimeException('ClamAV is unavailable.');
        }

        $input = fopen($localPath, 'rb');

        if (! is_resource($input)) {
            fclose($socket);
            throw new RuntimeException('The file cannot be opened for antivirus scanning.');
        }

        try {
            fwrite($socket, "zINSTREAM\0");

            while (! feof($input)) {
                $chunk = fread($input, 8192);

                if ($chunk === false) {
                    throw new RuntimeException('The file cannot be read for antivirus scanning.');
                }

                if ($chunk === '') {
                    break;
                }

                fwrite($socket, pack('N', strlen($chunk)).$chunk);
            }

            fwrite($socket, pack('N', 0));
            stream_set_timeout($socket, 30);
            $response = trim((string) stream_get_contents($socket));

            if (str_contains($response, 'FOUND')) {
                return new FileScanResult(false, 'clamav', 'Обнаружено потенциально опасное содержимое.');
            }

            if (! str_contains($response, 'OK')) {
                throw new RuntimeException('ClamAV returned an indeterminate result.');
            }

            return new FileScanResult(true, 'clamav');
        } finally {
            fclose($input);
            fclose($socket);
        }
    }
}
