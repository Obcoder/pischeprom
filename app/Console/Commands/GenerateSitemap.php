<?php

namespace App\Console\Commands;

use App\Services\Seo\SitemapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateSitemap extends Command
{
    protected $signature = 'app:generate-sitemap';

    protected $description = 'Generate a private sitemap snapshot; the public sitemap stays live.';

    public function handle(SitemapService $sitemap): int
    {
        $path = storage_path('app/private/seo/sitemap.xml');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $sitemap->xml());
        $this->info('Sitemap snapshot saved in storage/app/private/seo/sitemap.xml. /sitemap.xml is served dynamically.');

        return self::SUCCESS;
    }
}
