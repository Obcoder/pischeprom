<?php

namespace App\Console\Commands;

use App\Models\MaxSubscription;
use App\Services\MaxMessengerService;
use Illuminate\Console\Command;

class EnsureMaxWebhookSubscriptionCommand extends Command
{
    protected $signature = 'max:webhook:ensure
        {--url= : Public HTTPS webhook URL (defaults to the named MAX webhook route)}';

    protected $description = 'Verify the MAX bot deep link and ensure the production webhook subscription.';

    public function handle(MaxMessengerService $max): int
    {
        if (! $max->configured()) {
            $this->error('MAX API is not configured. Set MAX_ACCESS_TOKEN.');

            return self::FAILURE;
        }

        $webhookUrl = trim((string) ($this->option('url') ?: route('api.max.webhook')));

        if (! str_starts_with($webhookUrl, 'https://')) {
            $this->error("MAX webhook URL must use HTTPS: {$webhookUrl}");

            return self::FAILURE;
        }

        $deepLink = $max->botDeepLink('stock_healthcheck');

        if (! $deepLink) {
            $this->error(
                'MAX bot deep link is unavailable. Set MAX_BOT_URL/MAX_BOT_USERNAME '
                .'or verify that GET /me returns the bot username.'
            );

            return self::FAILURE;
        }

        $updateTypes = collect(config('services.max.webhook_update_types', []))
            ->merge([
                'bot_started',
                'message_callback',
            ])
            ->filter(fn ($type) => is_string($type) && trim($type) !== '')
            ->map(fn (string $type) => trim($type))
            ->unique()
            ->values()
            ->all();
        $secret = trim((string) config('services.max.webhook_secret'));
        $result = $max->createSubscription(
            $webhookUrl,
            $updateTypes,
            $secret !== '' ? $secret : null,
        );

        $subscription = MaxSubscription::query()->updateOrCreate([
            'url' => $webhookUrl,
        ], [
            'secret' => $secret !== '' ? $secret : null,
            'update_types' => $updateTypes,
            'is_active' => $result['ok'],
            'provider_response' => $result,
            'subscribed_at' => $result['ok'] ? now() : null,
            'unsubscribed_at' => null,
        ]);

        if (! $result['ok']) {
            $this->error(
                'MAX rejected the webhook subscription: '
                .($result['error'] ?: 'unknown provider error')
            );

            return self::FAILURE;
        }

        $botHost = parse_url($deepLink, PHP_URL_HOST);
        $botPath = parse_url($deepLink, PHP_URL_PATH);

        $this->info("MAX webhook is active: {$subscription->url}");
        $this->line('Required events: bot_started, message_callback');
        $this->line('Webhook secret: '.($secret !== '' ? 'configured' : 'not configured'));
        $this->line("Bot deep link: {$botHost}{$botPath}");

        return self::SUCCESS;
    }
}
