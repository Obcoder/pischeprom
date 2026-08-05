<?php

namespace Tests\Feature\Avito;

use App\Domain\Avito\Catalog\AvitoApiCatalog;
use App\Models\AvitoApiCall;
use App\Models\AvitoCapabilitySetting;
use App\Models\AvitoConnection;
use App\Models\AvitoWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AvitoIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
        config([
            'cache.default' => 'array',
            'avito.enabled' => true,
            'avito.client_id' => 'test-client-id',
            'avito.client_secret' => 'test-client-secret',
            'avito.api_base_url' => 'https://api.avito.ru',
            'avito.autoteka_base_url' => 'https://pro.autoteka.ru',
            'avito.token_url' => 'https://api.avito.ru/token',
            'avito.allowed_hosts' => ['api.avito.ru', 'pro.autoteka.ru'],
            'avito.mutations_enabled' => false,
            'avito.webhook_secret' => 'webhook-test-secret',
        ]);
    }

    public function test_page_is_available_without_separate_auth_and_linked_from_header(): void
    {
        $this->get('/Ameise/avito')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Ameise/Avito'));

        $layout = (string) file_get_contents(resource_path('js/Layouts/VerwalterLayout.vue'));
        $page = (string) file_get_contents(resource_path('js/Pages/Ameise/Avito.vue'));

        $this->assertStringContainsString("route('Ameise.avito')", $layout);
        $this->assertStringContainsString('mdi-storefront-outline', $layout);
        $this->assertStringContainsString('class="excel-table"', $page);
        $this->assertStringNotContainsString('localStorage', $page);
    }

    public function test_committed_catalog_contains_every_current_official_capability(): void
    {
        $snapshot = app(AvitoApiCatalog::class)->snapshot();

        $this->assertCount(25, $snapshot['sections']);
        $this->assertCount(245, $snapshot['capabilities']);
        $this->assertSame([
            'DELETE' => 3,
            'GET' => 75,
            'POST' => 158,
            'PUT' => 9,
        ], $snapshot['counts']['methods']);
        $this->assertSame(
            ['api.avito.ru', 'pro.autoteka.ru'],
            collect($snapshot['capabilities'])
                ->map(fn (array $item) => parse_url($item['server'], PHP_URL_HOST))
                ->unique()
                ->sort()
                ->values()
                ->all()
        );
        $this->assertCount(3, collect($snapshot['capabilities'])->where('path', '/token'));
        $this->assertTrue(collect($snapshot['capabilities'])->every(fn (array $item) => isset(
            $item['id'],
            $item['method'],
            $item['path'],
            $item['security'],
            $item['parameters'],
            $item['documentation_url']
        )));
    }

    public function test_status_and_catalog_do_not_expose_credentials(): void
    {
        $response = $this->getJson('/api/avito/status')
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('catalog.counts.capabilities', 245)
            ->assertJsonPath('catalog.counts.sections', 25);

        $response->assertDontSee('test-client-secret');
        $response->assertDontSee('test-client-id');

        $this->getJson('/api/avito/capabilities')
            ->assertOk()
            ->assertJsonCount(245, 'items')
            ->assertJsonPath('catalog_total', 245);
    }

    public function test_read_preflight_uses_server_token_and_records_redacted_audit(): void
    {
        Http::fake([
            'https://api.avito.ru/token' => Http::response([
                'access_token' => 'server-only-access-token',
                'expires_in' => 86400,
                'token_type' => 'Bearer',
            ]),
            'https://api.avito.ru/core/v1/accounts/self' => Http::response([
                'id' => 12345,
                'name' => 'Тестовый профиль',
            ]),
        ]);

        $response = $this->postJson('/api/avito/preflight')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('result.data.id', 12345);

        $response->assertDontSee('server-only-access-token');
        $this->assertDatabaseHas('avito_api_calls', [
            'capability_id' => 'user.getuserinfoself.4f59f9b2ea',
            'status' => 'success',
            'http_status' => 200,
        ]);
        $raw = (string) DB::table('avito_api_calls')->value('response_meta');
        $this->assertStringNotContainsString('Тестовый профиль', $raw);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.avito.ru/token'
            && $request['client_secret'] === 'test-client-secret');
        Http::assertSent(fn ($request) => $request->url() === 'https://api.avito.ru/core/v1/accounts/self'
            && $request->hasHeader('Authorization', 'Bearer server-only-access-token'));
    }

    public function test_legacy_token_endpoint_environment_value_is_normalized_to_api_origin(): void
    {
        $previousEnvironment = $_ENV['AVITO_API_URL'] ?? null;
        $previousServer = $_SERVER['AVITO_API_URL'] ?? null;
        $_ENV['AVITO_API_URL'] = 'https://api.avito.ru/token';
        $_SERVER['AVITO_API_URL'] = 'https://api.avito.ru/token';

        try {
            $config = require config_path('avito.php');
        } finally {
            if ($previousEnvironment === null) {
                unset($_ENV['AVITO_API_URL']);
            } else {
                $_ENV['AVITO_API_URL'] = $previousEnvironment;
            }

            if ($previousServer === null) {
                unset($_SERVER['AVITO_API_URL']);
            } else {
                $_SERVER['AVITO_API_URL'] = $previousServer;
            }
        }

        $this->assertSame('https://api.avito.ru', $config['api_base_url']);
        $this->assertSame('https://api.avito.ru/token', $config['token_url']);
    }

    public function test_unknown_parameters_and_remote_mutations_are_blocked_before_http(): void
    {
        Http::fake();

        $this->postJson('/api/avito/capabilities/user.getuserinfoself.4f59f9b2ea/execute', [
            'query' => ['arbitrary_url' => 'https://evil.example'],
        ])->assertStatus(422)->assertJsonPath('category', 'validation');

        $mutation = collect(app(AvitoApiCatalog::class)->capabilities())
            ->first(fn (array $item) => $item['access'] === 'mutation' && ! $item['deprecated']);

        $this->postJson('/api/avito/capabilities/'.rawurlencode($mutation['id']).'/execute', [
            'confirmation' => 'AVITO',
        ])->assertForbidden()->assertJsonPath('category', 'mutations_disabled');

        $this->postJson('/api/avito/capabilities/not-in-catalog/execute')
            ->assertNotFound()
            ->assertJsonPath('category', 'capability_not_found');

        Http::assertNothingSent();
    }

    public function test_executor_supports_json_multipart_and_binary_responses(): void
    {
        config(['avito.mutations_enabled' => true]);
        Http::fake([
            'https://api.avito.ru/token' => Http::response([
                'access_token' => 'all-formats-token',
                'expires_in' => 86400,
            ]),
            'https://api.avito.ru/autostrategy/v1/budget' => Http::response([
                'budget' => 1500,
            ]),
            'https://api.avito.ru/messenger/v1/accounts/777/uploadImages' => Http::response([
                'id' => 'image-id',
            ]),
            'https://api.avito.ru/messenger/v1/accounts/777/getVoiceFiles*' => Http::response([
                'voices' => [],
            ]),
            'https://api.avito.ru/order-management/1/orders/labels/task-42/download' => Http::response(
                '%PDF-test-binary',
                200,
                ['Content-Type' => 'application/pdf']
            ),
        ]);

        $this->postJson('/api/avito/capabilities/autostrategy.getautostrategybudget.2012b95942/execute', [
            'body' => ['campaignType' => 'AS'],
            'content_type' => 'application/json',
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.budget', 1500);

        $this->post('/api/avito/capabilities/messenger.uploadimages.e5c18d3e22/execute', [
            'path' => ['user_id' => 777],
            'body' => '{}',
            'content_type' => 'multipart/form-data',
            'confirmation' => 'AVITO',
            'files' => [UploadedFile::fake()->create('photo.png', 10, 'image/png')],
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.id', 'image-id');

        $this->postJson('/api/avito/capabilities/order-management.downloadlabel.4236dde707/execute', [
            'path' => ['taskID' => 'task-42'],
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('binary', true)
            ->assertJsonPath('encoding', 'base64')
            ->assertJsonPath('data', base64_encode('%PDF-test-binary'));

        $this->postJson('/api/avito/capabilities/messenger.getvoicefiles.54f8985147/execute', [
            'path' => ['user_id' => 777],
            'query' => ['voice_ids' => ['voice-one', 'voice-two']],
        ])->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.avito.ru/autostrategy/v1/budget'
            && $request->data()['campaignType'] === 'AS');
        Http::assertSent(fn ($request) => $request->url() === 'https://api.avito.ru/messenger/v1/accounts/777/uploadImages'
            && str_contains($request->body(), 'uploadfile[]')
            && str_contains($request->body(), 'photo.png'));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.avito.ru/messenger/v1/accounts/777/getVoiceFiles?voice_ids=voice-one&voice_ids=voice-two');
    }

    public function test_capabilities_can_be_managed_individually_and_in_bulk(): void
    {
        $catalog = app(AvitoApiCatalog::class)->capabilities();
        $first = $catalog[0]['id'];
        $second = $catalog[1]['id'];

        $this->patchJson('/api/avito/capabilities/'.rawurlencode($first), [
            'enabled' => false,
            'notes' => 'Отключено для теста',
        ])->assertOk()->assertJsonPath('setting.enabled', false);

        $this->patchJson('/api/avito/capabilities', [
            'ids' => [$first, $second],
            'enabled' => true,
        ])->assertOk()->assertJsonPath('updated', 2);

        $this->assertDatabaseHas('avito_capability_settings', ['capability_id' => $first, 'enabled' => true]);
        $this->assertDatabaseHas('avito_capability_settings', ['capability_id' => $second, 'enabled' => true]);
        $this->assertSame(2, AvitoCapabilitySetting::query()->count());
    }

    public function test_oauth_callback_validates_state_and_encrypts_tokens(): void
    {
        config(['avito.redirect_uri' => 'https://app.example.test/api/avito/oauth/callback']);
        Http::fake([
            'https://api.avito.ru/token' => Http::response([
                'access_token' => 'oauth-access-token',
                'refresh_token' => 'oauth-refresh-token',
                'expires_in' => 86400,
                'scope' => 'user:read messenger:read',
            ]),
            'https://api.avito.ru/core/v1/accounts/self' => Http::response([
                'id' => 9876,
                'name' => 'OAuth профиль',
            ]),
        ]);

        $this->withSession(['avito_oauth_state' => 'valid-state'])
            ->get('/api/avito/oauth/callback?code=authorization-code&state=valid-state')
            ->assertRedirect(route('Ameise.avito', ['oauth' => 'success']));

        $connection = AvitoConnection::query()->firstOrFail();
        $this->assertSame('oauth-access-token', $connection->access_token);
        $this->assertSame('oauth-refresh-token', $connection->refresh_token);
        $this->assertSame('9876', $connection->external_user_id);
        $this->assertNotSame('oauth-access-token', DB::table('avito_connections')->value('access_token'));
        $this->assertNotSame('oauth-refresh-token', DB::table('avito_connections')->value('refresh_token'));

        $this->withSession(['avito_oauth_state' => 'expected'])
            ->get('/api/avito/oauth/callback?code=authorization-code&state=wrong')
            ->assertStatus(419);
    }

    public function test_webhook_requires_secret_deduplicates_and_encrypts_payload(): void
    {
        $payload = [
            'id' => 'event-1',
            'type' => 'message',
            'payload' => ['value' => ['text' => 'Секретный текст сообщения']],
        ];

        $this->postJson('/api/avito/webhook', $payload)
            ->assertUnauthorized();

        config(['avito.webhook_secret' => null]);
        $this->postJson('/api/avito/webhook', $payload)
            ->assertStatus(503);
        config(['avito.webhook_secret' => 'webhook-test-secret']);

        $headers = ['X-Secret' => 'webhook-test-secret'];
        $this->postJson('/api/avito/webhook', $payload, $headers)
            ->assertStatus(202)
            ->assertJsonPath('duplicate', false);
        $this->postJson('/api/avito/webhook', $payload, $headers)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertSame(1, AvitoWebhookEvent::query()->count());
        $this->assertSame($payload, AvitoWebhookEvent::query()->firstOrFail()->payload);
        $this->assertStringNotContainsString(
            'Секретный текст сообщения',
            (string) DB::table('avito_webhook_events')->value('payload')
        );
    }

    public function test_api_call_details_are_available_without_returning_model_tokens(): void
    {
        $connection = AvitoConnection::query()->create([
            'name' => 'Encrypted connection',
            'access_token' => 'hidden-access',
            'refresh_token' => 'hidden-refresh',
            'status' => 'active',
        ]);
        $call = AvitoApiCall::query()->create([
            'avito_connection_id' => $connection->id,
            'request_id' => '97d29ba2-bce8-4ff7-bd81-8834bc9ad846',
            'capability_id' => 'test.capability',
            'method' => 'GET',
            'endpoint' => 'https://api.avito.ru/test',
            'status' => 'success',
            'request_meta' => ['query' => ['page' => 1]],
            'response_meta' => ['body' => ['ok' => true]],
        ]);

        $response = $this->getJson("/api/avito/calls/{$call->id}")
            ->assertOk()
            ->assertJsonPath('call.request_meta.query.page', 1)
            ->assertJsonPath('call.response_meta.body.ok', true);

        $response->assertDontSee('hidden-access')->assertDontSee('hidden-refresh');
    }
}
