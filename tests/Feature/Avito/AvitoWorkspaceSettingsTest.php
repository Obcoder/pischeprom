<?php

namespace Tests\Feature\Avito;

use App\Models\AvitoAutoloadFeed;
use App\Models\AvitoConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AvitoWorkspaceSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
        config([
            'cache.default' => 'array',
            'avito.enabled' => true,
            'avito.client_id' => 'workspace-client-id',
            'avito.client_secret' => 'workspace-client-secret',
            'avito.api_base_url' => 'https://api.avito.ru',
            'avito.token_url' => 'https://api.avito.ru/token',
            'avito.allowed_hosts' => ['api.avito.ru', 'pro.autoteka.ru'],
        ]);
    }

    public function test_server_account_is_detected_and_saved_outside_the_publication_workspace(): void
    {
        Http::fake([
            'https://api.avito.ru/token' => Http::response([
                'access_token' => 'workspace-access-token',
                'expires_in' => 86400,
            ]),
            'https://api.avito.ru/core/v1/accounts/self' => Http::response([
                'id' => 224345476,
                'name' => 'Пищепром-Сервер',
            ]),
        ]);

        $this->getJson('/api/avito/workspace-settings')
            ->assertOk()
            ->assertJsonPath('workspace.ready', true)
            ->assertJsonPath('workspace.authorization_ready', true)
            ->assertJsonPath('workspace.account_id', 224345476)
            ->assertJsonPath('workspace.account_name', 'Пищепром-Сервер')
            ->assertJsonPath('workspace.connection_id', null)
            ->assertJsonPath('workspace.selected', 'server')
            ->assertJsonPath('workspace.options.0.subtitle', 'ID 224345476 · серверные ключи');

        $this->assertDatabaseHas('avito_workspace_settings', [
            'id' => 1,
            'auth_mode' => 'server',
            'default_account_id' => 224345476,
            'default_connection_id' => null,
            'server_account_id' => 224345476,
            'server_account_name' => 'Пищепром-Сервер',
        ]);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.avito.ru/core/v1/accounts/self'
            && $request->hasHeader('Authorization', 'Bearer workspace-access-token'));

        $workspace = (string) file_get_contents(resource_path('js/Components/Avito/AvitoPublications.vue'));
        $settingsPage = (string) file_get_contents(resource_path('js/Pages/Ameise/Avito.vue'));
        $this->assertStringNotContainsString('label="Кабинет Avito / ID"', $workspace);
        $this->assertStringNotContainsString('label="Авторизация"', $workspace);
        $this->assertStringContainsString('Регистрационные данные находятся отдельно', $workspace);
        $this->assertStringContainsString('Кабинет для объявлений', $settingsPage);
        $this->assertStringContainsString('/api/avito/workspace-settings', $settingsPage);
    }

    public function test_oauth_workspace_is_selected_only_in_central_settings(): void
    {
        config([
            'avito.client_id' => null,
            'avito.client_secret' => null,
        ]);
        $connection = AvitoConnection::query()->create([
            'name' => 'Основной OAuth',
            'auth_mode' => 'authorization_code',
            'external_user_id' => '778899',
            'access_token' => 'oauth-token',
            'token_expires_at' => now()->addDay(),
            'scopes' => ['autoload:reports'],
            'status' => 'active',
            'is_active' => true,
        ]);
        $feed = AvitoAutoloadFeed::query()->create([
            'avito_account_id' => 778899,
            'name' => 'ameise-goods',
            'access_token' => str_repeat('a', 64),
            'defaults' => [],
        ]);

        $this->putJson('/api/avito/workspace-settings', [
            'selection' => "connection:{$connection->id}",
        ])->assertOk()
            ->assertJsonPath('workspace.ready', true)
            ->assertJsonPath('workspace.authorization_ready', true)
            ->assertJsonPath('workspace.account_id', 778899)
            ->assertJsonPath('workspace.connection_id', $connection->id)
            ->assertJsonPath('workspace.auth_mode', 'oauth')
            ->assertJsonPath('workspace.selected', "connection:{$connection->id}");

        $this->assertDatabaseHas('avito_workspace_settings', [
            'id' => 1,
            'auth_mode' => 'oauth',
            'default_account_id' => 778899,
            'default_connection_id' => $connection->id,
        ]);
        $this->assertSame($connection->id, $feed->fresh()->avito_connection_id);
    }
}
