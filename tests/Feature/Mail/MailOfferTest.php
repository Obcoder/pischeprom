<?php

namespace Tests\Feature\Mail;

use App\Models\Category;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Good;
use App\Models\GoodPriceTypeValue;
use App\Models\MailMessage;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\User;
use App\Services\Mail\AuthorizedMailDispatchService;
use App\Services\Mail\MailboxRegistry;
use App\Services\Mail\MailOfferCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class MailOfferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
        config()->set(['app.url' => 'https://pischeprom.test', 'mail.default' => 'array']);

        $mailbox = ['address' => 'server@example.test', 'from_name' => 'Pischeprom'];
        $registry = Mockery::mock(MailboxRegistry::class);
        $registry->shouldReceive('findOrDefault')->with(null)->andReturn($mailbox);
        $registry->shouldReceive('find')->with('server@example.test')->andReturn($mailbox);
        $registry->shouldReceive('registerMailer')->andReturn('array');
        $this->app->instance(MailboxRegistry::class, $registry);
    }

    public function test_catalog_and_preview_require_verified_active_mail_permission(): void
    {
        $this->getJson('/api/mail-offers/goods')->assertUnauthorized();
        $this->postJson('/api/mail-offers/preview')->assertUnauthorized();

        foreach ([
            $this->user(verified: false),
            $this->user(permission: false),
            $this->user(status: 'blocked'),
        ] as $user) {
            $this->actingAs($user)->getJson('/api/mail-offers/goods')->assertForbidden();
            $this->postJson('/api/mail-offers/preview')->assertForbidden();
        }

        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_search_exposes_only_published_goods_and_current_public_offer_fields(): void
    {
        $good = $this->good();
        $country = Country::query()->create(['name' => 'Россия', 'сodeISO' => 'RU']);
        $good->update(['country_id' => $country->id]);
        $category = Category::query()->create(['name' => 'Эмульгаторы']);
        $product = Product::query()->create(['rus' => 'Лецитин', 'eng' => 'Lecithin', 'category_id' => $category->id]);
        $good->products()->attach($product);
        $this->good('Hidden lecithin', ['is_published' => false]);
        $withoutLink = $this->good('Lecithin without public URL');
        Good::query()->whereKey($withoutLink->id)->update(['slug' => null]);
        $this->price($good, 240);
        $this->price($good, 81111.11, ['code' => 'purchase', 'is_public' => false]);
        $this->price($good, 82222.22, ['code' => 'partner']);
        $this->price($good, 83333.33, ['code' => 'retail-inactive', 'is_active' => false]);
        $this->price($good, 84444.44, ['code' => 'retail-expired'], ['valid_to' => today()->subDay()]);
        $this->price($good, 85555.55, ['code' => 'retail-future'], ['valid_from' => today()->addDay()]);
        $this->price($good, 86666.66, ['code' => 'retail-hidden'], ['is_published' => false]);
        $good->media()->create(['type' => 'image', 'path' => 'images/good.jpg', 'url' => '/images/good.jpg', 'is_published' => true, 'is_ava' => true]);
        $good->media()->create(['type' => 'image', 'path' => 'images/private-image.jpg', 'url' => '/images/private-image.jpg', 'is_published' => false, 'is_ava' => true]);

        $response = $this->actingAs($this->user())->getJson('/api/mail-offers/goods?search=Lecithin')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $good->id)
            ->assertJsonPath('data.0.price', 240)
            ->assertJsonPath('data.0.price_unit_label', 'кг')
            ->assertJsonPath('data.0.package_weight', 25)
            ->assertJsonPath('data.0.includes_vat', true)
            ->assertJsonPath('data.0.currency_code', 'RUB')
            ->assertJsonPath('data.0.image_url', 'https://pischeprom.test/images/good.jpg')
            ->assertJsonPath('data.0.url', route('public.goods.show', ['good' => $good->slug]))
            ->assertJsonFragment(['label' => 'Страна происхождения', 'value' => 'Россия'])
            ->assertJsonMissingPath('data.0.price_type_values');

        foreach (['81111.11', '82222.22', '83333.33', '84444.44', '85555.55', '86666.66', 'private-image.jpg'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
        $this->getJson('/api/mail-offers/goods?search='.urlencode('Эмульгаторы'))->assertJsonPath('data.0.id', $good->id);
        Http::assertNothingSent();
    }

    public function test_catalog_caps_page_size_and_does_not_invent_prices_or_embed_video(): void
    {
        for ($i = 1; $i <= 21; $i++) {
            $this->good('Offer '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), ['ava_image' => 'https://example.test/movie.mp4']);
        }

        $this->actingAs($this->user())->getJson('/api/mail-offers/goods?per_page=999')
            ->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('data.0.price', null)->assertJsonPath('data.0.includes_vat', null)
            ->assertJsonPath('data.0.image_url', null);
        $this->getJson('/api/mail-offers/goods?page=2')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_preview_renders_real_goods_manual_terms_and_quote_without_sending(): void
    {
        $good = $this->good();
        $this->price($good, 240);
        $payload = $this->payload($good);
        $payload['offer']['items'][0]['specifications'] = [['label' => 'Чистота', 'value' => '99%']];
        $payload['offer']['items'][0]['price_override'] = 180;

        $response = $this->actingAs($this->user())->postJson('/api/mail-offers/preview', $payload)
            ->assertOk()->assertJsonStructure(['html', 'text']);
        $html = $response->json('html');
        $text = $response->json('text');
        foreach ([$html, $text] as $content) {
            $this->assertStringContainsString($good->name, $content);
            $this->assertStringContainsString('Чистота', $content);
            $this->assertStringContainsString('Фасовка', $content);
            $this->assertStringContainsString('Курск', $content);
            $this->assertStringContainsString('180', $content);
            $this->assertStringContainsString('Предыдущее письмо', $content);
        }
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertGreaterThan(strpos($html, $good->name), strpos($html, 'Предыдущее письмо'));
        $this->assertDatabaseCount('mail_messages', 0);
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 0);
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_preview_rejects_unknown_nested_keys_invalid_limits_and_unpublished_goods(): void
    {
        $good = $this->good();
        $hidden = $this->good('Hidden', ['is_published' => false]);
        $base = $this->payload($good);
        $invalid = [];
        $invalid['offer.items.0'] = $base;
        $invalid['offer.items.0']['offer']['items'][0]['url'] = 'https://arbitrary.test';
        $invalid['offer.items.0.good_id'] = $base;
        $invalid['offer.items.0.good_id']['offer']['items'][0]['good_id'] = $hidden->id;
        $invalid['offer.items'] = $base;
        $invalid['offer.items']['offer']['items'] = array_fill(0, 11, $base['offer']['items'][0]);
        $invalid['offer.logistics.options'] = $base;
        $invalid['offer.logistics.options']['offer']['logistics']['options'] = array_fill(0, 5, $base['offer']['logistics']['options'][0]);
        $invalid['offer.items.0.quantity'] = $base;
        $invalid['offer.items.0.quantity']['offer']['items'][0]['quantity'] = -1;
        $invalid['offer.items.0.price_override'] = $base;
        $invalid['offer.items.0.price_override']['offer']['items'][0]['price_override'] = -1;
        $invalid['offer'] = [...$base, 'offer' => '{malformed json'];
        $invalid['html'] = [...$base, 'html' => '<table>Client-owned HTML</table>'];
        $this->actingAs($this->user());

        foreach ($invalid as $field => $payload) {
            $this->postJson('/api/mail-offers/preview', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        Mail::assertNothingSent();
    }

    public function test_json_encoded_multipart_offer_supports_logistics_without_products(): void
    {
        $payload = $this->payload($this->good());
        $payload['offer']['items'] = [];
        $payload['offer'] = json_encode($payload['offer'], JSON_THROW_ON_ERROR);

        $response = $this->actingAs($this->user())->post('/api/mail-offers/preview', $payload, ['Accept' => 'application/json'])->assertOk();
        $this->assertStringContainsString('Курск', $response->json('text'));
        Mail::assertNothingSent();
    }

    public function test_send_and_reply_use_exact_preview_html_and_plaintext_with_reply_headers(): void
    {
        $good = $this->good();
        $this->price($good, 240);
        $reply = MailMessage::query()->create([
            'mailbox' => 'server@example.test', 'folder' => 'INBOX', 'direction' => 'incoming',
            'message_id' => '<original@example.test>', 'subject' => 'Запрос',
        ]);
        $payload = [...$this->payload($good), 'reply_to_mail_message_id' => $reply->id];
        $actor = $this->user();
        $preview = $this->actingAs($actor)->postJson('/api/mail-offers/preview', $payload)->assertOk()->json();
        $transport = Mockery::mock();
        $transport->shouldReceive('html')->once()->andReturnUsing(function (string $html, callable $callback) use ($preview): void {
            $message = new Message(new Email);
            $callback($message);
            $this->assertSame($preview['html'], $html);
            $this->assertSame($preview['text'], $message->getSymfonyMessage()->getTextBody());
            $this->assertSame('<original@example.test>', $message->getHeaders()->get('In-Reply-To')->getBodyAsString());
        });
        Mail::shouldReceive('mailer')->once()->with('array')->andReturn($transport);

        $payload['offer'] = json_encode($payload['offer'], JSON_THROW_ON_ERROR);
        $response = $this->post('/api/mail-messages/send', $payload, ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('duplicate', false);
        $sent = MailMessage::query()->findOrFail($response->json('mail_message.id'));
        $this->assertSame($preview['html'], $sent->html);
        $this->assertSame($preview['text'], $sent->text);
        $this->assertSame($reply->id, (int) $sent->reply_to_mail_message_id);
        $this->post('/api/mail-messages/send', $payload, ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('duplicate', true);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_idempotency_detects_changes_to_offer_and_quoted_body(): void
    {
        $payload = $this->payload($this->good());
        $this->actingAs($this->user())->postJson('/api/mail-messages/send', $payload)->assertOk();
        $changedPrice = $payload;
        $changedPrice['offer']['items'][0]['price_override'] = 123;
        $this->postJson('/api/mail-messages/send', $changedPrice)
            ->assertStatus(409)->assertJsonPath('code', 'idempotency_conflict');
        $payload['quoted_body'] = 'Изменённая цитата';
        $this->postJson('/api/mail-messages/send', $payload)
            ->assertStatus(409)->assertJsonPath('code', 'idempotency_conflict');
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 1);
        $this->assertDatabaseCount('mail_messages', 1);
    }

    public function test_service_rechecks_publication_before_send_after_a_preview(): void
    {
        $good = $this->good();
        $actor = $this->user();
        $payload = $this->payload($good);
        $this->actingAs($actor)->postJson('/api/mail-offers/preview', $payload)->assertOk();
        $good->update(['is_published' => false]);

        try {
            app(AuthorizedMailDispatchService::class)->dispatchMessage($actor, $payload, 'mail-messages.send');
            $this->fail('Unpublished good must be rejected immediately before dispatch.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('offer.items.0.good_id', $exception->errors());
        }

        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 0);
        Mail::assertNothingSent();
    }

    public function test_catalog_resolver_keeps_database_facts_and_appends_manual_specifications(): void
    {
        $good = $this->good();
        $items = app(MailOfferCatalog::class)->resolve([[
            'good_id' => $good->id,
            'price_override' => 150,
            'specifications' => [['label' => 'Чистота', 'value' => '99%']],
        ]]);
        $this->assertSame(150.0, $items[0]['price']);
        $this->assertNull($items[0]['includes_vat']);
        $this->assertSame(['Фасовка', 'Чистота'], array_column($items[0]['specifications'], 'label'));
    }

    private function payload(Good $good): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(),
            'to' => ['buyer@example.test'],
            'subject' => 'Коммерческое предложение',
            'body' => 'Добрый день! <script>alert(1)</script>',
            'quoted_body' => 'Предыдущее письмо: пришлите предложение.',
            'offer' => [
                'items' => [[
                    'good_id' => $good->id, 'quantity' => 50, 'price_override' => null,
                    'include_description' => true, 'include_specifications' => true, 'include_image' => true,
                ]],
                'logistics' => [
                    'origin' => 'Санкт-Петербург', 'destination' => 'Курск', 'note' => 'До склада',
                    'options' => [['name' => 'Сборный груз', 'price' => 8500, 'currency_code' => 'RUB', 'duration' => '3–5 дней', 'note' => 'По согласованию']],
                ],
            ],
        ];
    }

    private function user(bool $verified = true, bool $permission = true, string $status = 'active'): User
    {
        $user = User::factory()->create(['email_verified_at' => $verified ? now() : null, 'status' => $status]);
        Permission::findOrCreate('mail.send', 'crm');
        if ($permission) {
            $user->givePermissionTo('mail.send');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function good(string $name = 'Лецитин подсолнечный', array $attributes = []): Good
    {
        return Good::query()->create([
            'name' => $name, 'denominator' => 25, 'description' => 'Пищевой эмульгатор', 'is_published' => true,
            ...$attributes,
        ]);
    }

    private function price(Good $good, float $value, array $typeAttributes = [], array $attributes = []): void
    {
        $currency = Currency::query()->where('code', 'RUB')->first()
            ?: Currency::query()->forceCreate(['code' => 'RUB', 'name' => 'Рубль']);
        $type = PriceType::query()->create([
            'name' => 'Розничная', 'code' => 'retail', 'currency_id' => $currency->id,
            'is_public' => true, 'is_active' => true, 'sort_order' => 10, ...$typeAttributes,
        ]);
        GoodPriceTypeValue::query()->create([
            'good_id' => $good->id, 'price_type_id' => $type->id, 'currency_id' => $currency->id,
            'price_gross' => $value, 'is_published' => true, ...$attributes,
        ]);
    }
}
