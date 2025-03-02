<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Spatie\Sitemap\SitemapGenerator;

Artisan::command('sitemap:generate', function () {
    SitemapGenerator::create('https://пищепром-сервер.рф')
        ->writeToFile(public_path('sitemap.xml'));

    $this->info('Sitemap generated successfully!');
});


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();
