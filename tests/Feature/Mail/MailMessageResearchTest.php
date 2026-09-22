<?php

namespace Tests\Feature\Mail;

use App\Domain\AiSales\Web\PublicDnsResolver;
use App\Models\MailMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MailMessageResearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
        config()->set([
            'mail-research.enabled' => true,
            'mail-research.api_key' => 'synthetic-key',
            'mail-research.model' => 'synthetic-model',
            'mail-research.token_parameter' => 'max_tokens',
            'services.dadata.token' => 'synthetic-directory-key',
        ]);
        $this->app->instance(PublicDnsResolver::class, new PublicDnsResolver([
            'catalog.example.com' => ['93.184.216.34'],
            'private.example.com' => ['127.0.0.1'],
            'mapped.example.com' => ['::ffff:127.0.0.1'],
            'nat64.example.com' => ['64:ff9b::7f00:1'],
            'shared.example.com' => ['100.64.0.1'],
        ]));
    }

    public function test_research_requires_active_verified_staff_and_get_never_starts_work(): void
    {
        $mail = $this->mail();
        $base = '/api/mail-messages/'.$mail->id.'/research';
        $this->getJson($base)->assertUnauthorized();
        foreach ([
            ['type' => 'customer'],
            ['type' => 'employee', 'status' => 'blocked'],
            ['type' => 'employee', 'email_verified_at' => null],
        ] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            $this->getJson($base)->assertForbidden();
            $this->postJson($base.'/website', ['url' => 'https://catalog.example.com'])->assertForbidden();
            $this->postJson($base.'/company', ['query' => 'ООО Пример'])->assertForbidden();
        }
        $this->staff();
        $this->getJson($base)->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('availability.website.available', true);
        Http::assertNothingSent();
        $this->assertDatabaseCount('mail_message_researches', 0);
    }

    public function test_manual_scan_follows_relative_catalog_links_and_saves_sources_without_private_mail(): void
    {
        $this->staff();
        $mail = $this->mail();
        $this->website();
        $response = $this->postJson('/api/mail-messages/'.$mail->id.'/research/website', ['url' => 'https://catalog.example.com']);
        $response->assertOk()->assertJsonPath('cached', false)
            ->assertJsonPath('data.result.products.0.name', 'Пектин цитрусовый')
            ->assertJsonPath('data.result.products.0.source_url', 'https://catalog.example.com/catalog')
            ->assertJsonCount(2, 'data.result.pages')
            ->assertJsonPath('data.result.partial', true);
        $this->assertDatabaseCount('mail_message_researches', 1);
        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://api.timeweb.ai/v1/chat/completions') {
                return false;
            }
            $payload = $request->body();
            $this->assertStringNotContainsString('private-buyer@example.test', $payload);
            $this->assertStringNotContainsString('private-mail-secret', $payload);
            $this->assertStringNotContainsString('sales@catalog.example.com', $payload);
            $this->assertStringNotContainsString('+7 900 123-45-67', $payload);

            return true;
        });
        $count = count(Http::recorded());
        $this->postJson('/api/mail-messages/'.$mail->id.'/research/website', ['url' => 'https://catalog.example.com/'])
            ->assertOk()->assertJsonPath('cached', true);
        $this->getJson('/api/mail-messages/'.$mail->id.'/research')->assertOk()->assertJsonCount(1, 'data');
        $this->assertCount($count, Http::recorded());
    }

    public function test_private_and_credential_urls_are_blocked_before_network(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->staff();
        $mail = $this->mail();
        foreach (['http://127.0.0.1', 'file:///etc/passwd', 'https://user:pass@catalog.example.com/', 'http://private.example.com/', 'http://mapped.example.com/', 'http://nat64.example.com/', 'http://shared.example.com/'] as $url) {
            $this->postJson('/api/mail-messages/'.$mail->id.'/research/website', compact('url'))->assertUnprocessable();
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('mail_message_researches', 0);
    }

    public function test_redirect_to_private_host_does_not_reach_ai(): void
    {
        $this->staff();
        $mail = $this->mail();
        Http::fake([
            'https://catalog.example.com/robots.txt' => Http::response('', 404),
            'https://catalog.example.com/' => Http::response('', 302, ['Location' => 'http://private.example.com/']),
        ]);
        $this->postJson('/api/mail-messages/'.$mail->id.'/research/website', ['url' => 'https://catalog.example.com/'])->assertUnprocessable();
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'timeweb.ai') || str_contains($request->url(), 'private.example.com'));
    }

    public function test_robots_disallow_does_not_reach_page_or_ai(): void
    {
        $this->staff();
        $mail = $this->mail();
        Http::fake(['https://catalog.example.com/robots.txt' => Http::response("User-agent: *\nDisallow: /", 200)]);
        $this->postJson('/api/mail-messages/'.$mail->id.'/research/website', ['url' => 'https://catalog.example.com/'])->assertUnprocessable();
        Http::assertSentCount(1);
    }

    public function test_oversized_page_is_not_saved_or_sent_to_ai(): void
    {
        $this->staff();
        $mail = $this->mail();
        Http::fake([
            'https://catalog.example.com/robots.txt' => Http::response('', 404),
            'https://catalog.example.com/' => Http::response(str_repeat('x', 524289), 200, ['Content-Type' => 'text/html']),
        ]);
        $endpoint = '/api/mail-messages/'.$mail->id.'/research/website';
        $this->postJson($endpoint, ['url' => 'https://catalog.example.com'])->assertUnprocessable();
        Http::assertSentCount(2);
        $this->assertDatabaseCount('mail_message_researches', 0);
    }

    public function test_invalid_ai_output_is_not_saved(): void
    {
        $this->staff();
        $mail = $this->mail();
        $this->website(content: '{"summary":"no products"}');
        $this->postJson('/api/mail-messages/'.$mail->id.'/research/website', ['url' => 'https://catalog.example.com'])->assertStatus(502);
        $this->assertDatabaseCount('mail_message_researches', 0);
    }

    public function test_unsubstantiated_products_are_discarded(): void
    {
        $this->staff();
        $mail = $this->mail();
        $this->website(products: [
            ['name' => 'Пектин цитрусовый', 'description' => '', 'page' => 1, 'evidence' => 'Пектин цитрусовый'],
            ['name' => 'Выдуманный товар', 'description' => '', 'page' => 1, 'evidence' => 'Выдуманный товар'],
        ]);
        $this->postJson('/api/mail-messages/'.$mail->id.'/research/website', ['url' => 'https://catalog.example.com/'])
            ->assertOk()->assertJsonCount(1, 'data.result.products')->assertJsonCount(1, 'data.result.warnings');
    }

    public function test_unconfigured_ai_and_duplicate_running_requests_do_not_fetch_site(): void
    {
        $this->staff();
        $mail = $this->mail();
        config()->set('mail-research.enabled', false);
        $endpoint = '/api/mail-messages/'.$mail->id.'/research/website';
        $this->postJson($endpoint, ['url' => 'https://catalog.example.com/'])->assertStatus(503);
        config()->set('mail-research.enabled', true);
        $lock = Cache::lock('mail-research:'.$mail->id.':website', 90);
        $this->assertTrue($lock->get());
        try {
            $this->postJson($endpoint, ['url' => 'https://catalog.example.com/'])->assertStatus(429);
        } finally {
            $lock->release();
        }
        Http::assertNothingSent();
    }

    public function test_ai_cannot_add_unsubstantiated_prices_or_delivery_claims(): void
    {
        $this->staff();
        $mail = $this->mail();
        $this->website(content: json_encode([
            'summary' => 'Бесплатная доставка и цена 1 рубль',
            'products' => [['name' => 'Пектин цитрусовый', 'description' => 'Бесплатная доставка и цена 1 рубль', 'page' => 1, 'evidence' => 'Пектин цитрусовый']],
        ], JSON_UNESCAPED_UNICODE));
        $this->postJson('/api/mail-messages/'.$mail->id.'/research/website', ['url' => 'https://catalog.example.com/'])
            ->assertOk()->assertJsonPath('data.result.products.0.description', '')
            ->assertJsonPath('data.result.summary', 'Найдено позиций: 1. Проверено страниц: 2.');
    }

    public function test_company_lookup_uses_registry_and_preserves_candidates_without_creating_entity(): void
    {
        $this->staff();
        $mail = $this->mail();
        Http::fake(['suggestions.dadata.ru/*' => Http::response(['suggestions' => [[
            'value' => 'ООО ТЕСТ',
            'data' => ['inn' => '7701234567', 'kpp' => '770101001', 'ogrn' => '1234567890123', 'type' => 'LEGAL',
                'name' => ['short_with_opf' => 'ООО ТЕСТ', 'full_with_opf' => 'Общество ТЕСТ'],
                'management' => ['name' => 'Тестовый Директор'], 'state' => ['status' => 'ACTIVE']],
        ]]])]);
        $this->postJson('/api/mail-messages/'.$mail->id.'/research/company', ['query' => '7701234567'])
            ->assertOk()->assertJsonPath('data.result.source', 'DaData')
            ->assertJsonPath('data.result.companies.0.entity.INN', '7701234567')
            ->assertJsonPath('data.result.companies.0.entity.director_name', 'Тестовый Директор');
        $this->assertDatabaseCount('entities', 0);
        $this->assertDatabaseCount('mail_message_researches', 1);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/findById/party') && $request['query'] === '7701234567');
        $this->postJson('/api/mail-messages/'.$mail->id.'/research/company', ['query' => '7701234567'])
            ->assertOk()->assertJsonPath('cached', true);
        Http::assertSentCount(1);
    }

    private function staff(): void
    {
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active', 'email_verified_at' => now()]));
    }

    private function mail(): MailMessage
    {
        return MailMessage::query()->create([
            'mailbox' => 'sales@example.test', 'folder' => 'INBOX', 'direction' => 'incoming',
            'from_address' => 'private-buyer@example.test', 'subject' => 'Запрос', 'text' => 'private-mail-secret',
        ]);
    }

    private function website(?array $products = null, ?string $content = null): void
    {
        $products ??= [['name' => 'Пектин цитрусовый', 'description' => 'Пищевой продукт', 'page' => 1, 'evidence' => 'Пектин цитрусовый']];
        Http::fake([
            'https://catalog.example.com/robots.txt' => Http::response('', 404),
            'https://catalog.example.com/' => Http::response('<html><body><h1>Компания</h1><a href="/catalog">Каталог продукции</a>sales@catalog.example.com +7 900 123-45-67</body></html>', 200, ['Content-Type' => 'text/html']),
            'https://catalog.example.com/catalog' => Http::response('<html><title>Продукция</title><body><h1>Пектин цитрусовый</h1><p>Пищевой продукт</p></body></html>', 200, ['Content-Type' => 'text/html']),
            'https://api.timeweb.ai/v1/chat/completions' => Http::response(['choices' => [[
                'finish_reason' => 'stop', 'message' => ['content' => $content ?? json_encode(['summary' => 'Каталог продукции', 'products' => $products], JSON_UNESCAPED_UNICODE)],
            ]]], 200, ['Content-Type' => 'application/json']),
        ]);
    }
}
