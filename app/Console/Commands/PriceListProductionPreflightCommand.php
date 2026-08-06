<?php

namespace App\Console\Commands;

use App\Domain\AiPriceLists\Contracts\FileScannerInterface;
use App\Domain\AiPriceLists\Contracts\OcrProviderInterface;
use App\Domain\AiPriceLists\Contracts\StructuredTextModelProviderInterface;
use App\Domain\AiPriceLists\DTO\OcrRequest;
use App\Domain\AiPriceLists\DTO\StructuredModelRequest;
use App\Services\MaxMessengerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PriceListProductionPreflightCommand extends Command
{
    protected $signature = 'price-lists:production-preflight
        {--all : Run every local and external check}
        {--schema : Verify the database schema}
        {--redis : Verify the configured Redis connection}
        {--storage : Write, read and remove a private synthetic object}
        {--scanner : Scan a harmless synthetic file}
        {--ai : Send a minimal structured-output request to AI Studio}
        {--vision : Send a synthetic PNG to Vision OCR}
        {--max : Run a read-only MAX subscription probe}';

    protected $description = 'Run sanitized production readiness checks for supplier price lists.';

    /** @var list<array{check:string,status:string}> */
    private array $results = [];

    public function handle(
        StructuredTextModelProviderInterface $ai,
        OcrProviderInterface $ocr,
        FileScannerInterface $scanner,
        MaxMessengerService $max,
    ): int {
        $all = (bool) $this->option('all');

        $this->check('configuration', fn () => $this->assertConfiguration($all));

        if ($all || $this->option('schema')) {
            $this->check('database_schema', fn () => $this->assertSchema());
        }

        if ($all || $this->option('redis')) {
            $this->check('redis', fn () => $this->assertRedis());
        }

        if ($all || $this->option('storage')) {
            $this->check('private_storage', fn () => $this->assertStorage());
        }

        if ($all || $this->option('scanner')) {
            $this->check('file_scanner', fn () => $this->assertScanner($scanner));
        }

        if ($all || $this->option('ai')) {
            $this->check('yandex_ai_studio', fn () => $this->assertAi($ai));
        }

        if ($all || $this->option('vision')) {
            $this->check('yandex_vision_ocr', fn () => $this->assertVision($ocr));
        }

        if ($all || $this->option('max')) {
            $this->check('max_api', fn () => $this->assertMax($max));
        }

        $failed = collect($this->results)->contains('status', 'failed');
        $this->newLine();
        $this->table(['Check', 'Status'], $this->results);

        if ($failed) {
            $this->error('AI price-list production preflight failed. No secrets were printed.');

            return self::FAILURE;
        }

        $this->info('AI price-list production preflight passed.');

        return self::SUCCESS;
    }

    private function assertConfiguration(bool $all): void
    {
        if (config('ai-price-lists.auto_apply') !== false) {
            throw new RuntimeException('Automatic apply must remain disabled.');
        }

        if ($all && ! config('ai-price-lists.enabled')) {
            throw new RuntimeException('The module is not enabled.');
        }

        if ($all && config('ai-price-lists.queue_connection') !== 'redis') {
            throw new RuntimeException('The dedicated queue must use Redis.');
        }

        $mailQueueConnection = (string) config('ai-price-lists.mail_ingestion.queue_connection');

        if ($all && ($mailQueueConnection === '' || ! is_array(config("queue.connections.{$mailQueueConnection}")))) {
            throw new RuntimeException('The email-ingestion queue connection is unavailable.');
        }

        if ($all && blank(config('ai-price-lists.mail_ingestion.queue'))) {
            throw new RuntimeException('The email-ingestion queue name is missing.');
        }

        if ($all && app()->isProduction() && config('ai-price-lists.scanner') !== 'clamav') {
            throw new RuntimeException('Production scanner must be ClamAV.');
        }

        if ($all && app()->isProduction() && config('ai-price-lists.ai.provider') !== 'yandex') {
            throw new RuntimeException('Production AI provider must be Yandex.');
        }
    }

    private function assertSchema(): void
    {
        $tables = [
            'mail_messages',
            'mail_message_attachments',
            'price_list_imports',
            'price_list_import_items',
            'price_list_item_candidates',
            'supplier_product_aliases',
            'price_list_events',
            'ai_usage_records',
            'supplier_good_prices',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Missing table: {$table}");
            }
        }

        if (! Schema::hasColumn('max_webhook_events', 'deduplication_key')) {
            throw new RuntimeException('MAX deduplication column is missing.');
        }
    }

    private function assertRedis(): void
    {
        $queueConnection = (string) config('ai-price-lists.queue_connection');
        $redisConnection = (string) config("queue.connections.{$queueConnection}.connection", 'default');
        $response = Redis::connection($redisConnection)->ping();

        if (! in_array($response, [true, 'PONG', '+PONG'], true)) {
            throw new RuntimeException('Redis did not return PONG.');
        }
    }

    private function assertStorage(): void
    {
        $diskName = (string) config('ai-price-lists.storage_disk');

        if ($diskName === '') {
            throw new RuntimeException('Private storage disk is missing.');
        }

        $disk = Storage::disk($diskName);
        $prefix = trim((string) config('ai-price-lists.storage_prefix'), '/');
        $path = ($prefix !== '' ? $prefix.'/' : '').'preflight/'.Str::uuid().'.txt';
        $content = 'pischeprom-price-list-preflight:'.Str::random(48);

        try {
            if (! $disk->put($path, $content, ['visibility' => 'private'])) {
                throw new RuntimeException('Synthetic object could not be written.');
            }

            if (! hash_equals(hash('sha256', $content), hash('sha256', $disk->get($path)))) {
                throw new RuntimeException('Synthetic object content mismatch.');
            }
        } finally {
            try {
                $disk->delete($path);
            } catch (Throwable) {
                // The original failure remains more useful; the randomized path is logged nowhere.
            }
        }

        if ($disk->exists($path)) {
            throw new RuntimeException('Synthetic object cleanup failed.');
        }
    }

    private function assertScanner(FileScannerInterface $scanner): void
    {
        $path = tempnam(sys_get_temp_dir(), 'price-list-preflight-');

        if ($path === false) {
            throw new RuntimeException('Temporary scanner input could not be created.');
        }

        try {
            chmod($path, 0600);
            file_put_contents($path, "Harmless AI price-list preflight file.\n");
            $result = $scanner->scan($path);

            if (! $result->clean) {
                throw new RuntimeException('Harmless scanner input was rejected.');
            }
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function assertAi(StructuredTextModelProviderInterface $ai): void
    {
        if (! $ai->configured()) {
            throw new RuntimeException('AI Studio provider is not configured.');
        }

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['ok'],
            'properties' => [
                'ok' => ['type' => 'boolean', 'enum' => [true]],
            ],
        ];
        $response = $ai->generate(new StructuredModelRequest(
            instructions: 'Return JSON with ok=true. Do not add any other fields.',
            data: json_encode(['synthetic' => true], JSON_THROW_ON_ERROR),
            schema: $schema,
            schemaName: 'price_list_production_preflight_v1',
            promptVersion: 'production-preflight-v1',
            schemaVersion: 'production-preflight-v1',
            safetyIdentifier: hash('sha256', 'pischeprom-price-list-production-preflight'),
        ));

        if (($response->data['ok'] ?? null) !== true || blank($response->externalRequestId)) {
            throw new RuntimeException('AI Studio returned an invalid preflight response.');
        }
    }

    private function assertVision(OcrProviderInterface $ocr): void
    {
        if (! $ocr->configured()) {
            throw new RuntimeException('Vision OCR provider is not configured.');
        }

        $image = imagecreatetruecolor(800, 240);

        if ($image === false) {
            throw new RuntimeException('Synthetic OCR image could not be created.');
        }

        try {
            $white = imagecolorallocate($image, 255, 255, 255);
            $black = imagecolorallocate($image, 0, 0, 0);
            imagefilledrectangle($image, 0, 0, 799, 239, $white);
            imagestring($image, 5, 40, 55, 'PRICE LIST', $black);
            imagestring($image, 5, 40, 115, 'MILK 125 RUB', $black);
            ob_start();
            imagepng($image);
            $content = ob_get_clean();
        } finally {
            imagedestroy($image);
        }

        if (! is_string($content) || $content === '') {
            throw new RuntimeException('Synthetic OCR PNG is empty.');
        }

        $response = $ocr->recognize(new OcrRequest($content, 'image/png', 'production-preflight.png'));

        if ($response->pages < 1 || blank($response->externalRequestId)) {
            throw new RuntimeException('Vision OCR returned an invalid preflight response.');
        }
    }

    private function assertMax(MaxMessengerService $max): void
    {
        if (! $max->configured() || blank(config('services.max.webhook_secret'))) {
            throw new RuntimeException('MAX API or webhook secret is not configured.');
        }

        $response = $max->getSubscriptions();

        if (! ($response['ok'] ?? false)) {
            throw new RuntimeException('MAX subscription probe failed.');
        }

        if (! in_array('message_created', config('services.max.webhook_update_types', []), true)) {
            throw new RuntimeException('MAX message_created subscription is not configured.');
        }
    }

    private function check(string $name, callable $callback): void
    {
        try {
            $callback();
            $this->results[] = ['check' => $name, 'status' => 'passed'];
            $this->line("[passed] {$name}");
        } catch (Throwable $exception) {
            report($exception);
            $this->results[] = ['check' => $name, 'status' => 'failed'];
            $this->error("[failed] {$name}: ".$this->safeMessage($exception));
        }
    }

    private function safeMessage(Throwable $exception): string
    {
        $message = preg_replace('/[\r\n]+/', ' ', trim($exception->getMessage())) ?: 'check failed';

        return mb_substr($message, 0, 300);
    }
}
