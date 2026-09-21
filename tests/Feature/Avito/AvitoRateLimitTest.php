<?php

namespace Tests\Feature\Avito;

use App\Http\Controllers\AvitoCrmController;
use App\Http\Controllers\AvitoMessageTemplateController;
use App\Http\Controllers\AvitoMessengerController;
use App\Models\AvitoChat;
use App\Models\AvitoMessageTemplate;
use App\Models\Good;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AvitoRateLimitTest extends TestCase
{
    private const TEXT_URL = '/api/avito/messenger/chats/1/messages';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
        Http::preventStrayRequests();
        $this->freezeTime();

        // Keep the production routes and middleware, replacing only the work
        // after throttling so these requests cannot send anything to Avito.
        Route::bind('chat', fn () => new AvitoChat);
        Route::bind('template', fn () => new AvitoMessageTemplate);
        Route::bind('good', fn () => new Good);

        $messenger = $this->partialMock(AvitoMessengerController::class);
        foreach (['overview', 'chats', 'chat', 'markRead'] as $method) {
            $messenger->shouldReceive($method)->andReturnUsing(fn () => response()->json(['ok' => true]));
        }
        foreach (['sendText', 'sendImage'] as $method) {
            $messenger->shouldReceive($method)->andReturnUsing(fn () => response()->json(['sent' => true], 201));
        }
        $this->partialMock(AvitoMessageTemplateController::class)
            ->shouldReceive('send')->andReturnUsing(fn () => response()->json(['sent' => true], 201));
        $this->partialMock(AvitoCrmController::class)
            ->shouldReceive('sendGood')->andReturnUsing(fn () => response()->json(['sent' => true], 201));
    }

    public function test_background_reads_do_not_block_any_message_type(): void
    {
        $this->readBackgroundRequests(35);

        $this->assertAllMessageTypesCanSend();
        Http::assertNothingSent();
    }

    public function test_exhausting_the_read_budget_does_not_block_sending(): void
    {
        $this->readBackgroundRequests(120);
        $this->getJson('/api/avito/messenger/chats')->assertStatus(429);

        $this->assertAllMessageTypesCanSend();
    }

    public function test_text_messages_have_the_full_quota_and_can_resume_after_retry_after(): void
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->postJson(self::TEXT_URL)->assertCreated();
        }

        $response = $this->postJson(self::TEXT_URL)
            ->assertStatus(429)
            ->assertHeader('Retry-After', '60')
            ->assertHeader('X-RateLimit-Limit', '30')
            ->assertHeader('X-RateLimit-Remaining', '0')
            ->assertJsonPath('category', 'local_rate_limit')
            ->assertJsonPath('retryable', true)
            ->assertJsonPath('retry_after', 60);

        $this->assertMatchesRegularExpression('/[А-Яа-я]/u', $response->json('message'));
        $this->assertStringContainsString('60', $response->json('message'));

        $this->travel(61)->seconds();
        $this->postJson(self::TEXT_URL)->assertCreated();
    }

    public function test_message_types_have_independent_operation_quotas(): void
    {
        foreach ($this->messageUrls() as $url => $limit) {
            for ($attempt = 0; $attempt < $limit; $attempt++) {
                $this->postJson($url)->assertCreated();
            }

            $this->postJson($url)->assertStatus(429)
                ->assertHeader('X-RateLimit-Limit', (string) $limit);
        }

        $this->readBackgroundRequests(120);
        $this->getJson('/api/avito/messenger/chats')->assertStatus(429);
    }

    public function test_the_shared_write_budget_still_limits_other_writes_independently_of_reads(): void
    {
        for ($attempt = 0; $attempt < 120; $attempt++) {
            $this->postJson('/api/avito/messenger/chats/1/read')->assertOk();
        }

        $this->postJson(self::TEXT_URL)->assertStatus(429)
            ->assertHeader('X-RateLimit-Limit', '120')
            ->assertJsonPath('category', 'local_rate_limit');

        $this->getJson('/api/avito/messenger/chats')->assertOk();
    }

    public function test_an_unrelated_numeric_throttle_does_not_consume_avito_quotas(): void
    {
        Route::get('/api/test-generic-throttle', fn () => response()->json(['ok' => true]))
            ->middleware('throttle:120,1');

        for ($attempt = 0; $attempt < 120; $attempt++) {
            $this->getJson('/api/test-generic-throttle')->assertOk();
        }
        $this->getJson('/api/test-generic-throttle')->assertStatus(429)
            ->assertJsonMissingPath('category');

        $this->getJson('/api/avito/messenger/chats')->assertOk();
        $this->assertAllMessageTypesCanSend();
    }

    public function test_another_ip_has_an_independent_message_quota(): void
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->postJson(self::TEXT_URL)->assertCreated();
        }
        $this->postJson(self::TEXT_URL)->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.25'])
            ->postJson(self::TEXT_URL)->assertCreated();
    }

    public function test_users_on_the_same_ip_have_independent_read_and_message_quotas(): void
    {
        $firstUser = new User;
        $firstUser->id = 101;
        $this->actingAs($firstUser);

        $this->readBackgroundRequests(120);
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->postJson(self::TEXT_URL)->assertCreated();
        }
        $this->getJson('/api/avito/messenger/chats')->assertStatus(429);
        $this->postJson(self::TEXT_URL)->assertStatus(429);

        $secondUser = new User;
        $secondUser->id = 102;
        $this->actingAs($secondUser);

        $this->getJson('/api/avito/messenger/chats')->assertOk();
        $this->postJson(self::TEXT_URL)->assertCreated();
    }

    private function readBackgroundRequests(int $count): void
    {
        $urls = [
            '/api/avito/messenger/overview',
            '/api/avito/messenger/chats',
            '/api/avito/messenger/chats/1',
        ];

        for ($attempt = 0; $attempt < $count; $attempt++) {
            $this->getJson($urls[$attempt % count($urls)])->assertOk();
        }
    }

    private function assertAllMessageTypesCanSend(): void
    {
        foreach ($this->messageUrls() as $url => $limit) {
            $this->postJson($url)->assertCreated();
        }
    }

    /** @return array<string, int> */
    private function messageUrls(): array
    {
        return [
            self::TEXT_URL => 30,
            self::TEXT_URL.'/image' => 20,
            '/api/avito/messenger/chats/1/message-templates/1/send' => 30,
            '/api/avito/messenger/chats/1/crm/goods/1/send' => 20,
        ];
    }
}
