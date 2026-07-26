<?php

namespace App\Console\Commands;

use App\Jobs\SendIncomingMailMaxNotificationJob;
use App\Services\Mail\MailboxRegistry;
use App\Services\MaxMessengerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CheckMaxMailNotificationsCommand extends Command
{
    protected $signature = 'max:mail-notifications:health
        {--remote : Also verify the MAX token with GET /me}';

    protected $description = 'Check configuration required for incoming mail notifications through MAX.';

    public function handle(
        MailboxRegistry $mailboxes,
        MaxMessengerService $max,
    ): int {
        if (! (bool) config('services.max.mail_notifications.enabled', false)) {
            $this->info('MAX mail notifications are disabled.');

            return self::SUCCESS;
        }

        $errors = [];
        $chatIds = config('services.max.mail_notifications.chat_ids', []);
        $userIds = config('services.max.mail_notifications.user_ids', []);
        $configuredMailboxes = config('services.max.mail_notifications.mailboxes', []);
        $queue = trim((string) config('services.max.mail_notifications.queue'));

        if (! $max->configured()) {
            $errors[] = 'MAX_ACCESS_TOKEN/MAX_BOT_TOKEN is not configured.';
        }

        if (empty($chatIds) && empty($userIds)) {
            $errors[] = 'Add MAX_MAIL_NOTIFICATION_CHAT_IDS or MAX_MAIL_NOTIFICATION_USER_IDS.';
        }

        if (empty($configuredMailboxes)) {
            $errors[] = 'MAX_MAIL_NOTIFICATION_MAILBOXES is empty.';
        }

        foreach ($configuredMailboxes as $mailbox) {
            if (! $mailboxes->find((string) $mailbox)) {
                $errors[] = "Mailbox is not configured in Ameise: {$mailbox}.";
            }
        }

        if ($queue === '') {
            $errors[] = 'MAX_MAIL_NOTIFICATION_QUEUE is empty.';
        }

        if (! Schema::hasTable('mail_message_max_deliveries')) {
            $errors[] = 'Migration for mail_message_max_deliveries has not been applied.';
        }

        $this->checkQueueRetryAfter($errors);

        if ($this->option('remote') && $max->configured()) {
            $result = $max->getMe();

            if (! $result['ok']) {
                $errors[] = 'MAX GET /me failed: '.($result['error'] ?: 'unknown error');
            } else {
                $this->line('MAX bot: reachable.');
            }
        }

        if (! empty($errors)) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info('MAX mail notifications are ready.');
        $this->line('Mailboxes: '.count($configuredMailboxes));
        $this->line('Targets: '.(count($chatIds) + count($userIds)));
        $this->line("Queue: {$queue}");

        return self::SUCCESS;
    }

    private function checkQueueRetryAfter(array &$errors): void
    {
        $connection = (string) config('queue.default');
        $driver = (string) config("queue.connections.{$connection}.driver");

        if ($driver !== 'database') {
            return;
        }

        $retryAfter = (int) config("queue.connections.{$connection}.retry_after", 90);
        $minimum = (new SendIncomingMailMaxNotificationJob(0))->timeout + 60;

        if ($retryAfter <= $minimum) {
            $errors[] = "DB_QUEUE_RETRY_AFTER must be greater than {$minimum} seconds "
                ."for MAX mail notifications; current value is {$retryAfter}.";
        }
    }
}
