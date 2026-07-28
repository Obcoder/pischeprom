<?php

use App\Jobs\Banking\CheckSberCredentialsExpiryJob;
use App\Jobs\Banking\RefreshSberTokenJob;
use App\Jobs\Banking\SyncSberAccountsJob;
use App\Jobs\Banking\SyncSberStatementsJob;
use App\Jobs\SyncYandexMailboxJob;
use App\Models\BankConnection;
use App\Models\GoodStockAlert;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\Sitemap\SitemapGenerator;

// Artisan::command('sitemap:generate', function () {
//    SitemapGenerator::create('https://пищепром-сервер.рф')
//        ->writeToFile(public_path('sitemap.xml'));
//
//    $this->info('Sitemap generated successfully!');
// });

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::job(new SyncYandexMailboxJob(50), 'mail-sync')
    ->name('sync-yandex-mailbox')
    ->everyMinute()
    ->withoutOverlapping(10);

Schedule::call(function (): void {
    GoodStockAlert::query()
        ->where('status', GoodStockAlert::STATUS_PENDING)
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->update([
            'status' => GoodStockAlert::STATUS_EXPIRED,
        ]);
})
    ->name('expire-pending-good-stock-alerts')
    ->hourly()
    ->withoutOverlapping(10);

Schedule::command('beeline:sync-calls --period=today --limit=500')
    ->name('sync-beeline-calls')
    ->everyFiveMinutes()
    ->when(fn () => filled(config('services.beeline_pbx.history_url')))
    ->withoutOverlapping(10);

Schedule::command('beeline:subscribe --expires=3600')
    ->name('subscribe-beeline-events')
    ->everyThirtyMinutes()
    ->when(fn () => filled(config('services.beeline_pbx.api_url')) && filled(config('services.beeline_pbx.api_token')))
    ->withoutOverlapping(10);

Schedule::command('yandex:direct:sync-stats')
    ->name('sync-yandex-direct-stats')
    ->dailyAt('04:20')
    ->withoutOverlapping(30);

Schedule::command('yandex:direct:sync-statuses')
    ->name('sync-yandex-direct-statuses')
    ->everyFourHours()
    ->withoutOverlapping(30);

Schedule::command('yandex:direct:check-accounts')
    ->name('check-yandex-direct-accounts')
    ->dailyAt('05:10')
    ->withoutOverlapping(30);

Schedule::command('yandex:direct:sync-geo-regions')
    ->name('sync-yandex-direct-geo-regions')
    ->weeklyOn(1, '05:40')
    ->withoutOverlapping(30);

Schedule::command('yandex:direct:ai:autopilot')
    ->name('yandex-direct-ai-autopilot')
    ->hourly()
    ->when(fn () => (bool) config('yandex.direct.ai_autopilot.enabled', true))
    ->withoutOverlapping(60);

$bankSyncMinutes = min(59, max(1, (int) config('banking.sber.sync_interval_minutes', 15)));

Schedule::call(function (): void {
    BankConnection::query()
        ->where('provider', 'sber')
        ->where('status', 'active')
        ->pluck('id')
        ->each(fn (int $id) => SyncSberStatementsJob::dispatch($id, 'incremental'));
})
    ->name('sber-incremental-statements')
    ->cron("*/{$bankSyncMinutes} * * * *")
    ->when(fn () => (bool) config('banking.enabled') && (bool) config('banking.sber.enabled'))
    ->onOneServer()
    ->withoutOverlapping(30);

Schedule::call(function (): void {
    BankConnection::query()
        ->where('provider', 'sber')
        ->where('status', 'active')
        ->pluck('id')
        ->each(fn (int $id) => SyncSberStatementsJob::dispatch($id, 'control'));
})
    ->name('sber-control-statements')
    ->dailyAt('03:15')
    ->when(fn () => (bool) config('banking.enabled') && (bool) config('banking.sber.enabled'))
    ->onOneServer()
    ->withoutOverlapping(120);

Schedule::call(function (): void {
    BankConnection::query()
        ->where('provider', 'sber')
        ->where('status', 'active')
        ->pluck('id')
        ->each(fn (int $id) => SyncSberAccountsJob::dispatch($id));
})
    ->name('sber-accounts-daily')
    ->dailyAt('02:45')
    ->when(fn () => (bool) config('banking.enabled') && (bool) config('banking.sber.enabled'))
    ->onOneServer()
    ->withoutOverlapping(60);

Schedule::call(function (): void {
    BankConnection::query()
        ->where('provider', 'sber')
        ->where('status', 'active')
        ->whereNotNull('access_token_expires_at')
        ->where('access_token_expires_at', '<=', now()->addMinutes(10))
        ->pluck('id')
        ->each(fn (int $id) => RefreshSberTokenJob::dispatch($id));
})
    ->name('sber-token-refresh')
    ->everyThirtyMinutes()
    ->when(fn () => (bool) config('banking.enabled') && (bool) config('banking.sber.enabled'))
    ->onOneServer()
    ->withoutOverlapping(20);

Schedule::job(new CheckSberCredentialsExpiryJob, (string) config('banking.queue', 'banking'))
    ->name('sber-credential-expiry')
    ->dailyAt('08:00')
    ->when(fn () => (bool) config('banking.enabled'))
    ->onOneServer()
    ->withoutOverlapping(60);

Schedule::command('logistics:routing-mark-stale --expired-only')
    ->name('logistics-mark-expired-matrix-stale')
    ->dailyAt('01:35')
    ->onOneServer()
    ->withoutOverlapping(30);
