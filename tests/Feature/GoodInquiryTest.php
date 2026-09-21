<?php

namespace Tests\Feature;

use App\Jobs\NotifyGoodInquiry;
use App\Mail\GoodInquiryReceivedMail;
use App\Models\Currency;
use App\Models\Email;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodInquiry;
use App\Models\GoodPriceTypeValue;
use App\Models\Order;
use App\Models\PriceType;
use App\Services\Goods\GoodInquiryNotificationService;
use App\Services\Goods\PublicGoodOffer;
use App\Services\Mail\MailboxRegistry;
use App\Services\MaxMessengerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class GoodInquiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::swap(new MailManager($this->app));
        Mail::fake();
        Http::preventStrayRequests();
        config(['services.max.manager_chat_ids' => [], 'services.yandex_mail.mailboxes' => [], 'services.yandex_mail.address' => null]);
    }

    public function test_guest_email_inquiry_is_persisted_and_notifies_fixed_manager_with_customer_reply_address(): void
    {
        $good = $this->good();
        $response = $this->postJson($this->url($good), $this->payload());

        $response->assertCreated()->assertJsonPath('inquiry.kind', 'email')->assertJsonPath('inquiry.order_number', null);
        $inquiry = GoodInquiry::query()->sole();
        $this->assertSame($good->name, $inquiry->good_name);
        $this->assertSame('buyer@example.com', $inquiry->customer_email);
        $this->assertNotNull($inquiry->consent_at);
        $this->assertSame(2, $inquiry->quantity);
        $this->assertDatabaseCount('orders', 0);
        Queue::assertPushed(NotifyGoodInquiry::class, fn ($job) => $job->inquiryId === $inquiry->id);
        app(GoodInquiryNotificationService::class)->deliver($inquiry->id);

        Mail::assertSent(GoodInquiryReceivedMail::class, function ($mail): bool {
            return $mail->hasTo('com@food-server.ru')
                && $mail->envelope()->replyTo[0]->address === 'buyer@example.com';
        });
        Mail::assertSentCount(1);
        $this->assertNotNull($inquiry->fresh()->email_notified_at);
        $this->assertNull($inquiry->fresh()->next_notification_at);
        $this->assertStringNotContainsString('buyer@example.com', $response->getContent());
    }

    public function test_bargain_requires_price_and_stores_price_per_kilogram_with_scenario(): void
    {
        $good = $this->good();
        $this->postJson($this->url($good), $this->payload(['kind' => 'bargain']))
            ->assertUnprocessable()->assertJsonValidationErrors('proposed_price');

        $this->postJson($this->url($good), $this->payload([
            'kind' => 'bargain', 'proposed_price' => 190.25, 'bargain_scenario' => 'repeat',
        ]))->assertCreated();

        $inquiry = GoodInquiry::query()->sole();
        $this->assertSame(190.25, $inquiry->proposed_price);
        $this->assertSame('kg', $inquiry->price_unit);
        $this->assertSame('repeat', $inquiry->bargain_scenario);
    }

    public function test_order_creates_crm_order_using_server_price_and_correct_package_weight(): void
    {
        $good = $this->good();
        $this->price($good, 240);
        $known = Entity::query()->create(['name' => 'Existing company']);
        $email = Email::query()->create(['address' => 'buyer@example.com']);
        $known->emails()->attach($email);

        $this->postJson($this->url($good), $this->payload([
            'kind' => 'order', 'listed_price' => 1, 'proposed_price' => 0.01,
            'delivery_city' => 'Москва', 'delivery_address' => 'Складская, 1',
        ]))->assertCreated()->assertJsonStructure(['inquiry' => ['order_number']]);

        $inquiry = GoodInquiry::query()->sole();
        $order = Order::query()->with(['items', 'entity', 'buildings'])->sole();
        $this->assertSame($order->id, $inquiry->order_id);
        $this->assertSame(240.0, $inquiry->listed_price);
        $this->assertNull($inquiry->proposed_price);
        $this->assertSame(2400.0, $order->total_amount);
        $this->assertSame(10.0, $order->total_weight);
        $this->assertSame(1200.0, $order->items->sole()->price_gross);
        $this->assertNotSame($known->id, $order->entity_id);
        $this->assertSame('Existing company', $known->fresh()->name);
        $this->assertSame('Москва, Складская, 1', $order->buildings->sole()->address);
        $this->assertStringContainsString('Требует подтверждения', $order->internal_comment);
    }

    public function test_order_without_current_public_price_does_not_invent_a_price(): void
    {
        $good = $this->good();
        $this->price($good, 25, ['is_public' => false, 'code' => 'purchase', 'name' => 'Закупочная']);

        $this->postJson($this->url($good), $this->payload(['kind' => 'order']))->assertCreated();

        $this->assertNull(Order::query()->sole()->items->sole()->price_gross);
        $this->assertNull(GoodInquiry::query()->sole()->listed_price);
    }

    public function test_retry_is_idempotent_and_reused_token_cannot_replace_payload(): void
    {
        $good = $this->good();
        $data = $this->payload(['kind' => 'order']);
        $first = $this->postJson($this->url($good), $data)->assertCreated();
        $second = $this->postJson($this->url($good), $data)->assertOk();
        $this->assertSame($first->json('inquiry'), $second->json('inquiry'));
        $this->postJson($this->url($good), [...$data, 'quantity' => 3])->assertConflict();
        $this->assertDatabaseCount('good_inquiries', 1);
        $this->assertDatabaseCount('orders', 1);
        Queue::assertPushed(NotifyGoodInquiry::class, 1);
    }

    public function test_invalid_contacts_quantity_consent_and_honeypot_do_not_create_records(): void
    {
        $this->postJson($this->url($this->good()), $this->payload([
            'quantity' => 1.2, 'customer_email' => 'invalid', 'customer_name' => '',
            'consent' => false, 'website' => 'spam', 'preferred_contact' => 'max',
        ]))->assertUnprocessable()->assertJsonValidationErrors([
            'quantity', 'customer_email', 'customer_name', 'consent', 'website', 'max_contact',
        ]);
        $this->assertDatabaseCount('good_inquiries', 0);
        Queue::assertNothingPushed();
    }

    public function test_unpublished_good_cannot_receive_an_inquiry(): void
    {
        $good = $this->good();
        $good->update(['is_published' => false]);
        $this->postJson($this->url($good), $this->payload())->assertNotFound();
        $this->assertDatabaseCount('good_inquiries', 0);
    }

    public function test_public_price_excludes_private_partner_inactive_expired_and_future_prices(): void
    {
        $good = $this->good();
        $this->price($good, 240);
        $this->price($good, 1, ['code' => 'partner', 'name' => 'Партнерская', 'is_public' => true, 'sort_order' => 0]);
        $this->price($good, 2, ['code' => 'purchase', 'is_public' => false]);
        $this->price($good, 3, ['code' => 'retail-disabled', 'is_active' => false]);
        $this->price($good, 4, ['code' => 'retail-old'], ['valid_to' => today()->subDay()]);
        $this->price($good, 5, ['code' => 'retail-future'], ['valid_from' => today()->addDay()]);
        $this->price($good, 6, ['code' => 'retail-hidden'], ['is_published' => false]);

        $offer = app(PublicGoodOffer::class)->for($good);
        $this->assertSame(240.0, $offer['price']);
        $this->assertSame(1200.0, $offer['package_price']);
        $this->assertSame('kg', $offer['price_unit']);
        $good->update(['denominator' => null]);
        $this->assertSame('package', app(PublicGoodOffer::class)->for($good)['price_unit']);
    }

    public function test_failed_mail_is_retried_and_successful_max_is_not_duplicated(): void
    {
        $this->postJson($this->url($this->good()), $this->payload())->assertCreated();
        $inquiry = GoodInquiry::query()->sole();
        config(['services.max.manager_chat_ids' => ['123']]);
        $this->mock(MaxMessengerService::class)->shouldReceive('sendToChat')->once()->with('123', \Mockery::type('string'))->andReturnTrue();
        Mail::shouldReceive('mailer')->once()->andThrow(new RuntimeException('SMTP unavailable'));
        app(GoodInquiryNotificationService::class)->deliver($inquiry->id);
        $this->assertNull($inquiry->fresh()->email_notified_at);
        $this->assertSame(['123'], $inquiry->fresh()->max_delivered_to);
        $this->assertNotNull($inquiry->fresh()->next_notification_at);

        Mail::swap(new MailManager($this->app));
        Mail::fake();
        $this->travel(3)->minutes();
        $this->artisan('goods:retry-inquiry-notifications')->assertSuccessful();
        Mail::assertSentCount(1);
        $this->assertNotNull($inquiry->fresh()->email_notified_at);
        $this->assertNull($inquiry->fresh()->next_notification_at);
        $this->artisan('goods:retry-inquiry-notifications')->assertSuccessful();
        Mail::assertSentCount(1);
    }

    public function test_failed_max_is_retried_without_resending_manager_email(): void
    {
        $this->postJson($this->url($this->good()), $this->payload())->assertCreated();
        $inquiry = GoodInquiry::query()->sole();
        config(['services.max.manager_chat_ids' => ['123']]);
        $this->mock(MaxMessengerService::class)->shouldReceive('sendToChat')->twice()->andReturn(false, true);
        app(GoodInquiryNotificationService::class)->deliver($inquiry->id);
        $this->assertNotNull($inquiry->fresh()->next_notification_at);
        $this->travel(3)->minutes();
        app(GoodInquiryNotificationService::class)->deliver($inquiry->id);
        Mail::assertSentCount(1);
        $this->assertNull($inquiry->fresh()->next_notification_at);
    }

    public function test_connected_mailbox_transport_is_used_and_html_is_escaped(): void
    {
        $this->postJson($this->url($this->good()), $this->payload([
            'comment' => '<script>alert("x")</script>',
        ]))->assertCreated();
        $registry = $this->mock(MailboxRegistry::class);
        $mailbox = ['address' => 'com@food-server.ru', 'from_name' => 'Пищепром'];
        $registry->shouldReceive('find')->with('com@food-server.ru')->once()->andReturn($mailbox);
        $registry->shouldReceive('registerMailer')->with($mailbox)->once()->andReturn('connected_mailbox');
        $inquiry = GoodInquiry::query()->sole();
        app(GoodInquiryNotificationService::class)->deliver($inquiry->id);
        Mail::assertSent(GoodInquiryReceivedMail::class, fn ($mail) => $mail->mailer === 'connected_mailbox' && $mail->from[0]['address'] === 'com@food-server.ru');
        $rendered = (new GoodInquiryReceivedMail($inquiry))->render();
        $this->assertStringContainsString('&lt;script&gt;', $rendered);
        $this->assertStringNotContainsString('<script>', $rendered);
    }

    public function test_public_endpoint_is_rate_limited(): void
    {
        $good = $this->good();
        for ($i = 0; $i < 6; $i++) {
            $this->postJson($this->url($good), $this->payload())->assertCreated();
        }
        $this->postJson($this->url($good), $this->payload())->assertStatus(429);
        $this->assertDatabaseCount('good_inquiries', 6);
    }

    public function test_queue_outage_preserves_order_and_recovery_record(): void
    {
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));

        $this->postJson($this->url($this->good()), $this->payload(['kind' => 'order']))->assertCreated();

        $this->assertDatabaseCount('orders', 1);
        $inquiry = GoodInquiry::query()->sole();
        $this->assertNotNull($inquiry->next_notification_at);
        $this->assertNull($inquiry->email_notified_at);
        $this->artisan('goods:retry-inquiry-notifications')->assertSuccessful();
        $this->assertNotNull($inquiry->fresh()->email_notified_at);
        $this->assertNotNull($inquiry->order->fresh()->notified_at);
    }

    private function good(): Good
    {
        return Good::query()->create(['name' => 'Арахис сырой, 5 кг', 'denominator' => 5, 'is_published' => true]);
    }

    private function url(Good $good): string
    {
        return '/g/'.$good->id.'/inquiries';
    }

    private function payload(array $overrides = []): array
    {
        return [...[
            'request_token' => (string) Str::uuid(),
            'kind' => 'email', 'quantity' => 2,
            'customer_name' => 'Ирина', 'customer_email' => 'Buyer@example.com',
            'preferred_contact' => 'email', 'consent' => true,
        ], ...$overrides];
    }

    private function price(Good $good, float $value, array $typeAttributes = [], array $attributes = []): void
    {
        $currency = Currency::query()->where('code', 'RUB')->first()
            ?: Currency::query()->forceCreate(['code' => 'RUB', 'name' => 'Рубль']);
        $type = PriceType::query()->create([
            'name' => 'Розничная', 'code' => 'retail', 'currency_id' => $currency->id,
            'is_public' => true, 'is_active' => true, 'sort_order' => 10,
            ...$typeAttributes,
        ]);
        GoodPriceTypeValue::query()->create([
            'good_id' => $good->id, 'price_type_id' => $type->id, 'currency_id' => $currency->id,
            'price_gross' => $value, 'is_published' => true, ...$attributes,
        ]);
    }
}
