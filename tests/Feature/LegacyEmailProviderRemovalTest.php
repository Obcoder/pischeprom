<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegacyEmailProviderRemovalTest extends TestCase
{
    public function test_legacy_provider_runtime_artifacts_are_removed(): void
    {
        $legacy = 'Mail'.'gun';
        $legacyLower = 'mail'.'gun';
        $legacyEnv = 'MAIL'.'GUN_';

        $this->assertFileDoesNotExist(base_path('app/Services/Mail/'.$legacy.'EmailService.php'));
        $this->assertFileDoesNotExist(base_path('app/Http/Controllers/API/'.$legacy.'WebhookController.php'));
        $this->assertFileDoesNotExist(base_path('docs/'.$legacyLower.'.md'));

        $this->assertStringNotContainsString($legacyEnv, file_get_contents(base_path('.env.example')));
        $this->assertStringNotContainsString($legacyLower.'/'.$legacyLower.'-php', file_get_contents(base_path('composer.json')));
        $this->assertStringNotContainsString($legacyLower, strtolower(file_get_contents(base_path('config/services.php'))));
        $this->assertStringNotContainsString($legacyLower, strtolower(file_get_contents(base_path('routes/api.php'))));
    }
}
