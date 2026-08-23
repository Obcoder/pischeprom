<?php

namespace App\Http\Controllers;

use App\Models\MailingEvent;
use App\Models\MailingLink;
use App\Models\Sending;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EmailTrackingController extends Controller
{
    public function open(Request $request, string $token)
    {
        abort_unless(Str::isUuid($token), 404);
        DB::transaction(function () use ($token): void {
            $sending = Sending::query()->where('tracking_token', $token)->lockForUpdate()->firstOrFail();
            $event = MailingEvent::query()->firstOrCreate(
                ['event_fingerprint' => hash('sha256', 'local_tracking|open|'.$sending->id.'|'.$token)],
                [
                    'provider' => 'local_tracking', 'sending_id' => $sending->id,
                    'mail_message_id' => $sending->mail_message_id, 'event_name' => 'open',
                    'normalized_event_type' => 'open', 'status' => 'opened', 'normalized_status' => 'opened',
                    'event_time' => now(), 'verified_at' => now(), 'processed_at' => now(),
                    'safe_summary' => 'local_tracking_open', 'created_at' => now(),
                ],
            );
            if ($event->wasRecentlyCreated) {
                $sending->forceFill([
                    'opened_at' => $sending->opened_at ?: now(),
                    'opens_count' => $sending->opens_count + 1,
                ])->save();
            }
        });

        $gif = base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==');

        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => strlen($gif),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function click(Request $request, string $token)
    {
        abort_unless(Str::isUuid($token), 404);
        $url = trim((string) $request->query('url', ''));
        abort_unless($this->targetAllowed($url), 400, 'Invalid tracking target.');

        DB::transaction(function () use ($token, $url): void {
            $sending = Sending::query()->where('tracking_token', $token)->lockForUpdate()->firstOrFail();
            $event = MailingEvent::query()->firstOrCreate(
                ['event_fingerprint' => hash('sha256', 'local_tracking|click|'.$sending->id.'|'.$token.'|'.hash('sha256', $url))],
                [
                    'provider' => 'local_tracking', 'sending_id' => $sending->id,
                    'mail_message_id' => $sending->mail_message_id, 'event_name' => 'click',
                    'normalized_event_type' => 'click', 'status' => 'clicked', 'normalized_status' => 'clicked',
                    'event_time' => now(), 'verified_at' => now(), 'processed_at' => now(),
                    'safe_summary' => 'local_tracking_click', 'created_at' => now(),
                ],
            );
            if ($event->wasRecentlyCreated) {
                $sending->forceFill([
                    'clicked_at' => $sending->clicked_at ?: now(),
                    'click_count' => $sending->click_count + 1,
                    'clicks_count' => $sending->clicks_count + 1,
                    'last_clicked_at' => now(),
                ])->save();
            }
        });

        return redirect()->away($url);
    }

    private function targetAllowed(string $url): bool
    {
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = mb_strtolower((string) ($parts['host'] ?? ''));
        $applicationHost = mb_strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $port = (int) ($parts['port'] ?? 443);
        $applicationPort = (int) (parse_url((string) config('app.url'), PHP_URL_PORT) ?: 443);
        if ($host !== '' && $host === $applicationHost && $port === $applicationPort) {
            return true;
        }
        if ($url === 'https://vk.com/market-231868854?screen=group') {
            return true;
        }

        return Schema::hasTable('mailing_links') && MailingLink::query()->where(function ($query) use ($url): void {
            $query->where('original_url', $url)->orWhere('canonical_url', $url)->orWhere('utm_url', $url);
        })->exists();
    }
}
