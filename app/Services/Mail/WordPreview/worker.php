<?php

// Standalone, constrained CLI process: no Laravel bootstrap, secrets, network or Office execution.
require dirname(__DIR__, 4).'/vendor/autoload.php';

use App\Services\Mail\WordPreview\WordDocumentExtractor;
use App\Services\Mail\WordPreviewException;

if (PHP_SAPI !== 'cli' || count($argv) !== 3) {
    exit(1);
}

set_error_handler(static function (int $severity, string $message): never {
    throw new ErrorException('Word parser error', 0, $severity);
});

try {
    $result = (new WordDocumentExtractor)->extract($argv[1], $argv[2]);
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (WordPreviewException $exception) {
    echo json_encode(['error' => $exception->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
    exit(2);
} catch (Throwable) {
    echo json_encode(['error' => 'Не удалось прочитать Word-документ. Возможно, файл повреждён или защищён паролем.']);
    exit(2);
}
