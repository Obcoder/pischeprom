<?php

declare(strict_types=1);

use App\Services\Mail\MailWebsiteCatalogAi;
use App\Services\Mail\WordAttachmentPreviewer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

// Read-only application checks and synthetic documents; no IMAP, directory or AI requests.
if ($argc !== 2) {
    fwrite(STDERR, "Expected TARGET_DIR.\n");
    exit(2);
}

$temporary = null;
$success = false;
try {
    $targetDir = rtrim($argv[1], '/');
    require $targetDir.'/vendor/autoload.php';
    $app = require $targetDir.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    if (! Schema::hasTable('mail_message_researches')) {
        throw new RuntimeException('research_migration_missing');
    }
    foreach (['mail-messages.research.website', 'mail-messages.research.company', 'mail-messages.attachments.word-preview', 'mail-messages.lead.store'] as $name) {
        $route = $app['router']->getRoutes()->getByName($name);
        if (! $route || $route->methods() !== ['POST'] || array_diff(['auth:sanctum', 'verified'], $route->gatherMiddleware()) !== []) {
            throw new RuntimeException('mail_route_unavailable');
        }
    }

    $previewer = $app->make(WordAttachmentPreviewer::class);
    $doc = $previewer->preview(file_get_contents($targetDir.'/tests/Fixtures/Mail/word-preview-cyrillic.doc'), 'synthetic.doc');
    if (! str_contains($doc['text'], 'Желатин пищевой')) {
        throw new RuntimeException('legacy_word_failed');
    }

    $temporary = tempnam(sys_get_temp_dir(), 'mail-word-health-');
    $zip = new ZipArchive;
    if ($temporary === false || $zip->open($temporary, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('docx_fixture_failed');
    }
    $zip->addFromString('[Content_Types].xml', '<Types/>');
    $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Проверка Word</w:t></w:r></w:p></w:body></w:document>');
    $zip->close();
    $docx = $previewer->preview(file_get_contents($temporary), 'synthetic.docx');
    if ($docx['text'] !== 'Проверка Word') {
        throw new RuntimeException('docx_preview_failed');
    }
    $ai = $app->make(MailWebsiteCatalogAi::class)->availability()['available'] ? 'configured' : 'unavailable';
    $company = config('services.dadata.token') ? 'configured' : 'unavailable';
    fwrite(STDOUT, "Mail workspace check passed: Word=DOC,DOCX; catalog_AI={$ai}; company_lookup={$company}.\n");
    $success = true;
} catch (Throwable) {
    fwrite(STDERR, "Mail workspace check failed; inspect PHP CLI, Word preview and mail route configuration.\n");
} finally {
    if (is_string($temporary) && is_file($temporary)) {
        unlink($temporary);
    }
}

exit($success ? 0 : 1);
