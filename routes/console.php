<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Spatie\Sitemap\SitemapGenerator;
use App\Jobs\SyncYandexMailboxJob;
use Illuminate\Support\Facades\Schedule;

//Artisan::command('sitemap:generate', function () {
//    SitemapGenerator::create('https://пищепром-сервер.рф')
//        ->writeToFile(public_path('sitemap.xml'));
//
//    $this->info('Sitemap generated successfully!');
//});


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::job(new SyncYandexMailboxJob(50), 'mail-sync')
    ->name('sync-yandex-mailbox')
    ->everyMinute()
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
