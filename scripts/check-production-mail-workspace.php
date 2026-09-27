<?php

declare(strict_types=1);

use App\Http\Middleware\RequireStaffAuthentication;
use App\Services\Auth\StaffRouteAccess;
use App\Services\Mail\MailWebsiteCatalogAi;
use App\Services\Mail\WordAttachmentPreviewer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
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
    $app->make(HttpKernel::class); // Install the same middleware groups as HTTP requests.
    $boundary = $app->make(StaffRouteAccess::class);
    $staffRoute = static function ($route) use ($app, $boundary): bool {
        if (! $route || ! in_array(RequireStaffAuthentication::class, $app['router']->gatherRouteMiddleware($route), true)) {
            return false;
        }

        foreach ($route->methods() as $method) {
            if (! $boundary->requiresStaff($route, $method)) {
                return false;
            }
        }

        return true;
    };

    foreach (['web', 'api'] as $group) {
        if (! in_array(RequireStaffAuthentication::class, $app['router']->getMiddlewareGroups()[$group] ?? [], true)) {
            throw new RuntimeException('shared_staff_boundary_missing');
        }
    }
    if (! $boundary->requiresStaff(new \Illuminate\Routing\Route('GET', 'api/future-internal-endpoint', static fn () => null), 'GET')) {
        throw new RuntimeException('internal_routes_not_private_by_default');
    }
    foreach (['Ameise', 'Ameise.mail', 'mailboxes.index', 'mailboxes.store', 'mailboxes.show', 'mailboxes.update', 'mailboxes.destroy', 'mail-messages.index', 'mail-messages.show', 'mail-messages.send'] as $name) {
        if (! $staffRoute($app['router']->getRoutes()->getByName($name))) {
            throw new RuntimeException('mail_staff_boundary_unavailable');
        }
    }

    if (! Schema::hasTable('mail_message_researches') || ! Schema::hasTable('unit_website_researches')) {
        throw new RuntimeException('research_migration_missing');
    }
    if (! Schema::hasColumn('mail_messages', 'deleted_at')) {
        throw new RuntimeException('mail_deletion_migration_missing');
    }
    foreach (['delivery_status', 'sent_copy_status', 'sent_copy_error', 'sent_mime_path', 'smtp_accepted_at', 'is_reconstructed'] as $column) {
        if (! Schema::hasColumn('mail_messages', $column)) {
            throw new RuntimeException('mail_sent_state_migration_missing');
        }
    }
    if (! Schema::hasColumn('authorized_mail_dispatch_attempts', 'mail_message_id')) {
        throw new RuntimeException('mail_dispatch_link_migration_missing');
    }
    foreach (['ai.enabled', 'matching.ai_reranking_enabled', 'notifications_enabled', 'max.send_acknowledgement', 'auto_apply'] as $flag) {
        if (config('ai-price-lists.'.$flag) !== false) {
            throw new RuntimeException('price_list_external_actions_not_disabled');
        }
    }
    if (config('ai-price-lists.authorization_enabled') !== true) {
        throw new RuntimeException('price_list_domain_authorization_not_enabled');
    }
    $deleteRoute = $app['router']->getRoutes()->getByName('mail-messages.destroy');
    if (! $staffRoute($deleteRoute) || $deleteRoute->methods() !== ['DELETE'] || ! in_array('verified', $deleteRoute->gatherMiddleware(), true)) {
        throw new RuntimeException('mail_deletion_route_unavailable');
    }
    foreach (['mail-messages.research.website', 'mail-messages.research.company', 'mail-messages.research.unit', 'mail-messages.attachments.word-preview', 'mail-messages.lead.store'] as $name) {
        $route = $app['router']->getRoutes()->getByName($name);
        if (! $staffRoute($route) || $route->methods() !== ['POST'] || ! in_array('verified', $route->gatherMiddleware(), true)) {
            throw new RuntimeException('mail_route_unavailable');
        }
    }
    $unitRoute = $app['router']->getRoutes()->getByName('api.units.website-research.index');
    if (! $staffRoute($unitRoute) || ! in_array('GET', $unitRoute->methods(), true) || ! in_array('verified', $unitRoute->gatherMiddleware(), true)) {
        throw new RuntimeException('unit_website_research_route_unavailable');
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
    fwrite(STDOUT, "Mail workspace check passed: Word=DOC,DOCX; catalog_AI={$ai}; company_lookup={$company}; unit_website_research=ready; server_mail_deletion=ready; sent_state=ready; staff_boundary=ready; price_external_actions=disabled.\n");
    $success = true;
} catch (Throwable) {
    fwrite(STDERR, "Mail workspace check failed; inspect PHP CLI, Word preview and mail route configuration.\n");
} finally {
    if (is_string($temporary) && is_file($temporary)) {
        unlink($temporary);
    }
}

exit($success ? 0 : 1);
