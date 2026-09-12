<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class AvitoDataChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use InteractsWithBroadcasting;

    public const TOPICS = ['avito_messages', 'avito_auto_replies'];

    public string $connection;

    public string $queue;

    public int $tries = 5;

    public int $timeout = 10;

    public array $backoff = [1, 5, 15, 30, 60];

    public readonly string $eventId;

    public readonly array $topics;

    public function __construct(array $topics)
    {
        if ($topics === [] || count($topics) > count(self::TOPICS)) {
            throw new InvalidArgumentException('Invalid Avito topics.');
        }

        foreach ($topics as $topic) {
            if (! is_string($topic) || ! in_array($topic, self::TOPICS, true)) {
                throw new InvalidArgumentException('Invalid Avito topics.');
            }
        }

        $this->topics = array_values(array_unique($topics));
        $this->eventId = (string) Str::uuid();
        $this->connection = (string) config('realtime.queue_connection', 'database');
        $this->queue = (string) config('realtime.queue', 'realtime');
        $this->broadcastVia('reverb');
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('avito.updates')];
    }

    public function broadcastAs(): string
    {
        return 'avito.changed';
    }

    public function broadcastWith(): array
    {
        return ['topics' => $this->topics, 'event_id' => $this->eventId];
    }

    public function failed(?Throwable $exception = null): void
    {
        self::warnUnavailable();
    }

    public static function warnUnavailable(): void
    {
        try {
            Log::warning('avito_realtime_unavailable');
        } catch (Throwable) {
            // Notifications must never interrupt a committed message or stop request.
        }
    }
}
