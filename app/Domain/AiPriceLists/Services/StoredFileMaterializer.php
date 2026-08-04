<?php

namespace App\Domain\AiPriceLists\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StoredFileMaterializer
{
    /** @template T */
    public function using(string $disk, string $path, callable $callback): mixed
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'price-list-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Не удалось создать защищённый временный файл.');
        }

        $source = null;
        $target = null;

        try {
            $source = Storage::disk($disk)->readStream($path);
            $target = fopen($temporaryPath, 'wb');

            if (! is_resource($source) || ! is_resource($target)) {
                throw new RuntimeException('Исходный файл недоступен в хранилище.');
            }

            if (stream_copy_to_stream($source, $target) === false) {
                throw new RuntimeException('Не удалось получить исходный файл из хранилища.');
            }

            fclose($source);
            $source = null;
            fclose($target);
            $target = null;

            return $callback($temporaryPath);
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }

            if (is_resource($target)) {
                fclose($target);
            }

            try {
                if (is_file($temporaryPath)) {
                    unlink($temporaryPath);
                }
            } catch (Throwable) {
                // A temporary cleanup error must not mask the processing result.
            }
        }
    }
}
