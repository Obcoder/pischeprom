<?php

namespace Tests\Feature;

use App\Models\MailingCampaign;
use App\Models\MailingCampaignRecipient;
use App\Models\MailingContact;
use App\Models\MailingContactSet;
use App\Models\MailingOfferItem;
use App\Models\MailingSuppression;
use App\Models\MailingTemplate;
use App\Services\CommercialOffers\MailingCampaignService;
use App\Services\CommercialOffers\MailingRenderer;
use App\Services\CommercialOffers\ProductOfferBuilder;
use App\Services\CommercialOffers\RecipientSetService;
use App\Services\CommercialOffers\UnisenderGoClient;
use App\Services\CommercialOffers\UnisenderWebhookPayload;
use App\Services\CommercialOffers\UnisenderWebhookService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CommercialOffersUnisenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'inertia.ssr.enabled' => false,
            'queue.default' => 'sync',
            'app.url' => 'https://pischeprom.test',
            'services.email_provider' => 'unisender_go',
            'services.unisender_go.enabled' => true,
            'services.unisender_go.api_base' => 'https://go1.unisender.test/en/transactional/api/v1',
            'services.unisender_go.api_key' => 'test-api-key',
            'services.unisender_go.from_email' => 'sales@pischeprom.ru',
            'services.unisender_go.from_name' => 'Pischeprom',
            'services.unisender_go.reply_to' => 'sales@pischeprom.ru',
            'services.unisender_go.track_read' => true,
            'services.unisender_go.track_links' => true,
            'services.mailings.batch_size' => 500,
            'services.mailings.require_consent_for_mass' => true,
            'services.mailings.stop_on_spam_rate' => 0.002,
            'services.mailings.stop_on_hard_bounce_rate' => 0.05,
            'services.mailings.dry_run' => false,
        ]);

        DB::purge('sqlite');
        DB::connection()->getPdo();
        $migration = include base_path('database/migrations/2026_06_21_130000_create_commercial_offer_mailings_tables.php');
        $migration->up();

        DB::statement('create table regions (id integer primary key autoincrement, name varchar(255) null)');
        DB::statement('create table cities (id integer primary key autoincrement, name varchar(255) not null, region_id integer null)');
    }

    public function test_unisender_client_builds_send_payload_and_webhook_auth(): void
    {
        Http::fake([
            '*email/send.json*' => Http::response(['job_id' => 'job-1', 'failed_emails' => []]),
        ]);

        $client = app(UnisenderGoClient::class);
        $result = $client->sendEmail([
            'recipients' => [
                ['email' => 'a@example.test', 'metadata' => ['campaign_id' => 1, 'campaign_recipient_id' => 10]],
            ],
            'body' => ['html' => '<p>Hello</p>', 'plaintext' => 'Hello'],
            'subject' => 'Subject',
            'from_email' => 'sales@pischeprom.ru',
            'from_name' => 'Pischeprom',
            'reply_to' => 'sales@pischeprom.ru',
            'track_read' => 1,
            'track_links' => 1,
            'global_metadata' => ['campaign_id' => 1],
            'idempotence_key' => 'idem-1',
        ]);

        $this->assertSame('job-1', $result->jobId);
        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->hasHeader('X-API-KEY', 'test-api-key')
                && $request->hasHeader('X-Mailer', 'pischeprom-commercial-offers')
                && str_contains($request->url(), '/email/send.json')
                && str_contains($request->url(), 'platform=pischeprom.laravel')
                && $payload['message']['track_read'] === 1
                && $payload['message']['track_links'] === 1
                && $payload['message']['idempotence_key'] === 'idem-1'
            && $payload['message']['global_metadata']['campaign_id'] === '1'
            && $payload['message']['recipients'][0]['metadata']['campaign_recipient_id'] === '10';
        });

        $tooMany = array_fill(0, 501, ['email' => 'bulk@example.test']);
        $this->expectException(RuntimeException::class);
        $client->sendEmail(['recipients' => $tooMany, 'idempotence_key' => 'too-many']);
    }

    public function test_webhook_auth_valid_and_invalid(): void
    {
        $client = app(UnisenderGoClient::class);
        $template = '{"events_by_user":[],"auth":"placeholder"}';
        $bodyForHash = str_replace('"placeholder"', '"test-api-key"', $template);
        $valid = str_replace('placeholder', md5($bodyForHash), $template);

        $this->assertTrue($client->verifyWebhookRawBody($valid));
        $this->assertFalse($client->verifyWebhookRawBody(str_replace(md5($bodyForHash), 'bad', $valid)));
    }

    public function test_renderer_outputs_product_card_unsubscribe_and_plaintext(): void
    {
        $campaign = $this->campaign([
            'html_markup' => '<h1>{{campaign_name}}</h1><p>Здравствуйте, {{to_name}}.</p>{{offer_items_html}}<p><a href="{{unsubscribe_url}}">unsubscribe</a></p>',
            'plaintext' => 'Здравствуйте, {{to_name}}. {{offer_items_html}} {{unsubscribe_url}}',
        ]);
        $contact = MailingContact::query()->create(['email' => 'buyer@example.test', 'normalized_email' => 'buyer@example.test', 'consent_status' => 'confirmed']);
        $recipient = MailingCampaignRecipient::query()->create(['campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'email' => $contact->email, 'status' => 'pending']);
        $item = MailingOfferItem::query()->create([
            'campaign_id' => $campaign->id,
            'product_id' => 123,
            'item_type' => 'product',
            'title' => 'Насос пищевой',
            'sku' => 'PUMP-123',
            'thumbnail_url' => 'https://pischeprom.test/i/pump.jpg',
            'canonical_url' => 'https://pischeprom.test/g/pump',
            'original_price' => 1000,
            'offer_price' => 900,
            'currency' => 'RUB',
            'description' => 'Описание товара',
            'snapshot' => ['category_id' => 77, 'category' => 'Оборудование', 'category_url' => 'https://pischeprom.test/catalog/equipment', 'vat_rate' => 22],
        ]);

        $renderer = app(MailingRenderer::class);
        $html = $renderer->renderCampaignHtml($campaign, $contact, [$item], $recipient);
        $text = $renderer->renderPlaintext($campaign, $contact, [$item], $recipient);

        $this->assertStringContainsString('https://pischeprom.test/i/pump.jpg', $html);
        $this->assertStringContainsString('900,00 RUB', $html);
        $this->assertStringContainsString('Цены включают НДС. Доставка до адреса.', $html);
        $this->assertStringContainsString('>НДС</td>', $html);
        $this->assertStringContainsString('22%', $html);
        $this->assertStringContainsString('162,30 RUB', $html);
        $this->assertStringNotContainsString('Некоторые позиции каталога', $html);
        $this->assertStringContainsString('background:#8b1e1e', $html);
        $this->assertStringContainsString('width:52px', $html);
        $this->assertStringNotContainsString('SKU PUMP-123', $html);
        $this->assertStringContainsString('href="https://pischeprom.test/catalog/equipment?utm_source=unisender', $html);
        $this->assertStringContainsString('>Оборудование</a>', $html);
        $this->assertStringContainsString('Добрый день!', $html);
        $this->assertStringNotContainsString('buyer@example.test', $html);
        $this->assertStringContainsString('Открыть', $html);
        $this->assertStringNotContainsString('width:160px', $html);
        $this->assertStringContainsString('utm_source=unisender', $html);
        $this->assertStringContainsString($recipient->unsubscribe_token, $html);
        $this->assertStringContainsString('Добрый день!', $text);
        $this->assertStringContainsString('Насос пищевой', $text);

        $namedContact = MailingContact::query()->create(['email' => 'irina@example.test', 'normalized_email' => 'irina@example.test', 'first_name' => 'Ирина', 'consent_status' => 'confirmed']);
        $namedHtml = $renderer->renderCampaignHtml($campaign, $namedContact, []);
        $this->assertStringContainsString('Здравствуйте, Ирина.', $namedHtml);
    }

    public function test_renderer_blocks_embedded_media_video_tags_and_oversized_html(): void
    {
        $renderer = app(MailingRenderer::class);

        $this->assertNotContains('Missing unsubscribe link/block.', $renderer->validateEmailHtml('<p>Коммерческое предложение</p>'));

        $errors = $renderer->validateEmailHtml(
            '<p>unsubscribe</p><img src="data:image/png;base64,'.str_repeat('a', 100).'"><video src="https://pischeprom.test/v.mp4"></video>'
        );

        $this->assertContains('Embedded base64/data media is not allowed in email. Upload images and use public HTTPS URLs.', $errors);
        $this->assertContains('Video tags are not allowed in email. Use a regular HTTPS product page link instead.', $errors);

        $oversized = '<p>unsubscribe</p>'.str_repeat('x', MailingRenderer::MAX_MESSAGE_BYTES + 1);
        $this->assertNotEmpty(array_filter(
            $renderer->validateEmailHtml($oversized),
            fn (string $error) => str_contains($error, 'Email HTML is too large')
        ));
    }

    public function test_recipient_sets_exclude_blocked_and_unconfirmed_contacts(): void
    {
        $service = app(RecipientSetService::class);
        $set = MailingContactSet::query()->create(['name' => 'Mass set', 'type' => 'manual']);
        $ok = MailingContact::query()->create(['email' => 'ok@example.test', 'normalized_email' => 'ok@example.test', 'consent_status' => 'confirmed']);
        $unknown = MailingContact::query()->create(['email' => 'unknown@example.test', 'normalized_email' => 'unknown@example.test', 'consent_status' => 'unknown']);
        $bounced = MailingContact::query()->create(['email' => 'bounce@example.test', 'normalized_email' => 'bounce@example.test', 'consent_status' => 'confirmed', 'hard_bounced_at' => now()]);
        MailingSuppression::query()->create(['email' => 'blocked@example.test', 'normalized_email' => 'blocked@example.test', 'cause' => 'manual_block', 'source' => 'manual']);
        $blocked = MailingContact::query()->create(['email' => 'blocked@example.test', 'normalized_email' => 'blocked@example.test', 'consent_status' => 'confirmed']);
        $service->addContacts($set, [$ok->id, $unknown->id, $bounced->id, $blocked->id]);

        $eligible = $service->getEligibleRecipientsForCampaign($set, true, 500);

        $this->assertSame(['ok@example.test'], $eligible->pluck('email')->all());
    }

    public function test_campaign_send_chunks_recipients_and_records_failed_emails(): void
    {
        Http::fake([
            '*email/send.json*' => Http::response([
                'job_id' => 'job-42',
                'failed_emails' => [['email' => 'bad@example.test', 'reason' => 'invalid']],
            ]),
        ]);

        $set = MailingContactSet::query()->create(['name' => 'Set', 'type' => 'manual']);
        $ok = MailingContact::query()->create(['email' => 'ok@example.test', 'normalized_email' => 'ok@example.test', 'consent_status' => 'confirmed']);
        $bad = MailingContact::query()->create(['email' => 'bad@example.test', 'normalized_email' => 'bad@example.test', 'consent_status' => 'confirmed']);
        app(RecipientSetService::class)->addContacts($set, [$ok->id, $bad->id]);
        $campaign = $this->campaign(['contact_set_id' => $set->id]);

        app(MailingCampaignService::class)->startSending($campaign);

        $this->assertDatabaseHas('mailing_campaign_recipients', ['email' => 'ok@example.test', 'status' => 'accepted', 'unisender_job_id' => 'job-42']);
        $this->assertDatabaseHas('mailing_campaign_recipients', ['email' => 'bad@example.test', 'status' => 'failed', 'failure_reason' => 'invalid']);
        $this->assertDatabaseCount('mailing_messages', 2);
        Http::assertSent(function ($request) {
            $recipients = $request->data()['message']['recipients'];

            return count($recipients) <= 500
                && isset($request->data()['message']['idempotence_key'])
                && is_string($recipients[0]['metadata']['campaign_id'])
                && is_string($recipients[0]['metadata']['campaign_recipient_id'])
                && (int) $recipients[0]['metadata']['campaign_id'] > 0
                && (int) $recipients[0]['metadata']['campaign_recipient_id'] > 0;
        });
    }

    public function test_campaign_test_send_requires_valid_email_without_500(): void
    {
        config(['services.mailings.test_recipient' => null]);
        $campaign = $this->campaign(['contact_set_id' => null]);

        $this->post("/Ameise/commercial-offers/campaigns/{$campaign->id}/send-test", ['email' => ''])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Test email failed: Valid test recipient email is required.');
    }

    public function test_campaign_test_send_does_not_require_contact_set(): void
    {
        Http::fake([
            '*email/send.json*' => Http::response([
                'job_id' => 'test-job-1',
                'failed_emails' => [],
            ]),
        ]);

        $campaign = $this->campaign(['contact_set_id' => null]);

        $this->post("/Ameise/commercial-offers/campaigns/{$campaign->id}/send-test", [
            'email' => 'single@example.test',
        ])->assertOk()
            ->assertJsonPath('job_id', 'test-job-1');

        $this->assertDatabaseHas('mailing_campaign_recipients', [
            'campaign_id' => $campaign->id,
            'email' => 'single@example.test',
            'status' => 'accepted',
            'unisender_job_id' => 'test-job-1',
        ]);
        Http::assertSent(fn ($request) => $request->data()['message']['subject'] === '[TEST] Коммерческое предложение'
            && $request->data()['message']['recipients'][0]['email'] === 'single@example.test');
    }

    public function test_campaign_test_send_uses_first_campaign_recipient_when_email_is_empty(): void
    {
        config(['services.mailings.test_recipient' => null]);
        Http::fake([
            '*email/send.json*' => Http::response([
                'job_id' => 'test-job-from-recipient',
                'failed_emails' => [],
            ]),
        ]);

        $campaign = $this->campaign(['contact_set_id' => null]);
        MailingCampaignRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'email' => 'first-recipient@example.test',
            'status' => 'pending',
        ]);

        $this->post("/Ameise/commercial-offers/campaigns/{$campaign->id}/send-test", ['email' => ''])
            ->assertOk()
            ->assertJsonPath('job_id', 'test-job-from-recipient');

        Http::assertSent(fn ($request) => $request->data()['message']['recipients'][0]['email'] === 'first-recipient@example.test');
    }

    public function test_campaign_test_send_blocks_local_suppressed_recipient_before_provider_call(): void
    {
        Http::fake();
        $campaign = $this->campaign(['contact_set_id' => null]);
        MailingSuppression::query()->create([
            'email' => 'blocked@example.test',
            'normalized_email' => 'blocked@example.test',
            'cause' => 'permanent_unavailable',
            'source' => 'webhook',
            'note' => 'Hard bounce',
        ]);

        $this->post("/Ameise/commercial-offers/campaigns/{$campaign->id}/send-test", [
            'email' => 'blocked@example.test',
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Test email failed: Recipient blocked@example.test is blocked in local suppression list: cause=permanent_unavailable, source=webhook. Remove suppression only if this block is known to be wrong.']);

        Http::assertNothingSent();
    }

    public function test_suppression_can_be_removed_and_local_contact_unblocked(): void
    {
        $contact = MailingContact::query()->create([
            'email' => 'blocked@example.test',
            'normalized_email' => 'blocked@example.test',
            'consent_status' => 'confirmed',
            'do_not_email' => true,
            'hard_bounced_at' => now(),
        ]);
        $suppression = MailingSuppression::query()->create([
            'email' => 'blocked@example.test',
            'normalized_email' => 'blocked@example.test',
            'cause' => 'permanent_unavailable',
            'source' => 'webhook',
            'note' => 'Hard bounce',
        ]);

        $this->deleteJson("/Ameise/commercial-offers/suppression/{$suppression->id}", [
            'clear_contact_block' => true,
        ])
            ->assertOk()
            ->assertJson(['deleted' => true, 'contact_unblocked' => true]);

        $this->assertDatabaseMissing('mailing_suppression_list', [
            'email' => 'blocked@example.test',
        ]);
        $contact->refresh();
        $this->assertFalse($contact->do_not_email);
        $this->assertNull($contact->hard_bounced_at);
    }

    public function test_campaign_test_send_uses_configured_unisender_sender_not_stale_campaign_sender(): void
    {
        config([
            'services.unisender_go.from_email' => 'com@food-server.ru',
            'services.unisender_go.from_name' => 'Pischeprom',
            'services.unisender_go.reply_to' => 'com@food-server.ru',
        ]);

        Http::fake([
            '*email/send.json*' => Http::response([
                'job_id' => 'test-job-food-server',
                'failed_emails' => [],
            ]),
        ]);

        $campaign = $this->campaign([
            'contact_set_id' => null,
            'from_email' => 'office@180022.ru',
            'from_name' => 'Old sender',
            'reply_to' => 'office@180022.ru',
        ]);

        $this->post("/Ameise/commercial-offers/campaigns/{$campaign->id}/send-test", [
            'email' => 'single@example.test',
        ])->assertOk()
            ->assertJsonPath('job_id', 'test-job-food-server');

        Http::assertSent(fn ($request) => $request->data()['message']['from_email'] === 'com@food-server.ru'
            && $request->data()['message']['from_name'] === 'Pischeprom'
            && $request->data()['message']['reply_to'] === 'com@food-server.ru'
            && $request->data()['message']['global_metadata']['campaign_id'] === (string) $campaign->id
            && $request->data()['message']['recipients'][0]['metadata']['campaign_id'] === (string) $campaign->id);

        $this->assertDatabaseHas('mailing_campaigns', [
            'id' => $campaign->id,
            'from_email' => 'com@food-server.ru',
            'from_name' => 'Pischeprom',
            'reply_to' => 'com@food-server.ru',
        ]);
    }

    public function test_campaign_test_send_does_not_require_local_unsubscribe_block(): void
    {
        Http::fake([
            '*email/send.json*' => Http::response([
                'job_id' => 'test-no-local-unsubscribe',
                'failed_emails' => [],
            ]),
        ]);

        $campaign = $this->campaign([
            'html_markup' => '<h1>{{campaign_name}}</h1><p>{{greeting}}</p>{{offer_items_html}}',
            'plaintext' => '{{greeting}} Коммерческое предложение',
            'contact_set_id' => null,
        ]);

        $this->post("/Ameise/commercial-offers/campaigns/{$campaign->id}/send-test", [
            'email' => 'single@example.test',
        ])->assertOk()
            ->assertJsonPath('job_id', 'test-no-local-unsubscribe');

        Http::assertSent(function ($request) {
            $payload = $request->data()['message'];

            return ! str_contains($payload['body']['html'], 'unsubscribe')
                && ! str_contains($payload['body']['html'], 'Отпис')
                && isset($payload['options']['unsubscribe_url']);
        });
    }

    public function test_campaign_test_send_retries_without_tracking_when_tracking_domain_is_required(): void
    {
        Http::fake([
            '*email/send.json*' => Http::sequence()
                ->push(['message' => 'Error ID:test. Custom backend domain or tracking domain required for sending.'], 422)
                ->push(['job_id' => 'test-job-no-tracking', 'failed_emails' => []], 200),
        ]);

        $campaign = $this->campaign(['contact_set_id' => null]);

        $this->post("/Ameise/commercial-offers/campaigns/{$campaign->id}/send-test", [
            'email' => 'single@example.test',
        ])->assertOk()
            ->assertJsonPath('job_id', 'test-job-no-tracking')
            ->assertJsonPath('response.tracking_disabled_for_test', true);

        $requests = Http::recorded();
        $this->assertCount(2, $requests);
        $this->assertSame(1, $requests[0][0]->data()['message']['track_read']);
        $this->assertSame(1, $requests[0][0]->data()['message']['track_links']);
        $this->assertSame(0, $requests[1][0]->data()['message']['track_read']);
        $this->assertSame(0, $requests[1][0]->data()['message']['track_links']);
        $this->assertDatabaseHas('mailing_messages', [
            'email' => 'single@example.test',
            'unisender_job_id' => 'test-job-no-tracking',
        ]);
    }

    public function test_campaign_test_send_explains_unisender_free_tier_checked_recipient_limit(): void
    {
        Http::fake([
            '*email/send.json*' => Http::response([
                'message' => "Error ID:test. On the 'free_tier' tariff it is allowed to send letters only to the 'checked' domains or 'checked' emails. The request contains external domain(s) 'gmail.com'.",
            ], 422),
        ]);

        $campaign = $this->campaign(['contact_set_id' => null]);

        $this->post("/Ameise/commercial-offers/campaigns/{$campaign->id}/send-test", [
            'email' => 'buyer@gmail.com',
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Test email failed: Unisender free_tier отклонил получателя: можно отправлять только на checked emails/domains в Unisender. Домен получателя: gmail.com. Добавьте email получателя/домен в проверенные в кабинете Unisender, укажите MAILINGS_TEST_RECIPIENT на проверенный адрес или смените тариф.'
            );
    }

    public function test_campaign_test_send_explains_no_valid_recipients_provider_error(): void
    {
        config(['services.unisender_go.from_email' => 'com@food-server.ru']);
        Http::fake([
            '*email/send.json*' => Http::response([
                'message' => 'Error ID:test. No valid recipients',
            ], 422),
        ]);

        $campaign = $this->campaign(['contact_set_id' => null]);

        $this->post("/Ameise/commercial-offers/campaigns/{$campaign->id}/send-test", [
            'email' => 'buyer@gmail.com',
        ])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Test email failed: Error ID:test. No valid recipients Attempted recipient: buyer@gmail.com. Sender: com@food-server.ru. Check Unisender suppression/blocklist, free tier checked recipients/domains, recipient validity, and confirmed sender domain.',
            ]);

        $this->assertDatabaseHas('mailing_campaign_recipients', [
            'campaign_id' => $campaign->id,
            'email' => 'buyer@gmail.com',
            'status' => 'failed',
        ]);
    }

    public function test_webhook_updates_open_click_bounce_spam_and_is_idempotent(): void
    {
        Http::fake(['*suppression/set.json*' => Http::response(['ok' => true])]);
        $campaign = $this->campaign();
        $contact = MailingContact::query()->create(['email' => 'lead@example.test', 'normalized_email' => 'lead@example.test', 'consent_status' => 'confirmed']);
        $recipient = MailingCampaignRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'email' => $contact->email,
            'status' => 'accepted',
            'sent_at' => now(),
            'unisender_job_id' => 'job-99',
        ]);
        $service = app(UnisenderWebhookService::class);

        $service->handlePayload(new UnisenderWebhookPayload([], [$this->event('opened', $recipient, '2026-06-21T10:00:00+00:00')]));
        $service->handlePayload(new UnisenderWebhookPayload([], [$this->event('opened', $recipient, '2026-06-21T10:00:00+00:00')]));
        $recipient->refresh();
        $this->assertSame(1, $recipient->open_count);
        $this->assertDatabaseCount('mailing_events', 1);

        $service->handlePayload(new UnisenderWebhookPayload([], [$this->event('clicked', $recipient, '2026-06-21T10:01:00+00:00', 'https://pischeprom.test/g/pump')]));
        $recipient->refresh();
        $this->assertSame(1, $recipient->click_count);
        $this->assertSame('https://pischeprom.test/g/pump', $recipient->last_clicked_url);

        $service->handlePayload(new UnisenderWebhookPayload([], [$this->event('hard_bounced', $recipient, '2026-06-21T10:02:00+00:00')]));
        $this->assertDatabaseHas('mailing_contacts', ['email' => 'lead@example.test', 'do_not_email' => 1]);
        $this->assertDatabaseHas('mailing_suppression_list', ['email' => 'lead@example.test', 'cause' => 'permanent_unavailable']);
    }

    public function test_unsubscribe_route_blocks_future_sending(): void
    {
        Http::fake(['*suppression/set.json*' => Http::response(['ok' => true])]);
        $campaign = $this->campaign();
        $contact = MailingContact::query()->create(['email' => 'optout@example.test', 'normalized_email' => 'optout@example.test', 'consent_status' => 'confirmed']);
        $recipient = MailingCampaignRecipient::query()->create(['campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'email' => $contact->email, 'status' => 'accepted']);

        $this->get(route('mailings.unsubscribe.show', $recipient->unsubscribe_token))->assertOk();
        $this->post(route('mailings.unsubscribe.submit', $recipient->unsubscribe_token))->assertOk();

        $this->assertDatabaseHas('mailing_contacts', ['email' => 'optout@example.test', 'do_not_email' => 1]);
        $this->assertDatabaseHas('mailing_suppression_list', ['email' => 'optout@example.test', 'cause' => 'unsubscribed']);
    }

    public function test_ameise_commercial_offers_page_does_not_redirect_guest_to_login(): void
    {
        $this->get('/Ameise/commercial-offers')
            ->assertOk()
            ->assertHeaderMissing('Location');
    }

    public function test_unisender_test_api_reports_missing_key_without_server_error(): void
    {
        config(['services.unisender_go.api_key' => '']);

        $this->post('/Ameise/commercial-offers/settings/test-api')
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'UNISENDER_GO_API_KEY is not configured.',
            ]);
    }

    public function test_unisender_test_api_reports_rejected_account_as_configuration_error(): void
    {
        Http::fake([
            '*suppression/list.json*' => Http::response(['message' => "Error ID:CC8F8330-6DA8-11F1-BDEC-6E52618EE1EB. User with id '8252570' not found."], 401),
        ]);

        $this->post('/Ameise/commercial-offers/settings/test-api')
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonFragment(['message' => "Unisender Go rejected API key/account: Error ID:CC8F8330-6DA8-11F1-BDEC-6E52618EE1EB. User with id '8252570' not found.. Check that UNISENDER_GO_API_KEY belongs to Unisender Go Transactional API, the correct project/account is active, the key has no extra spaces, and UNISENDER_GO_API_BASE uses your Go host, for example https://go1.unisender.ru/en/transactional/api/v1. Current API base: https://go1.unisender.test/en/transactional/api/v1. After env changes run php artisan config:clear."]);
    }

    public function test_recipients_can_be_imported_from_existing_emails_table(): void
    {
        DB::statement('create table emails (id integer primary key autoincrement, address varchar(255) not null, name varchar(255) null, domain varchar(255) null, source varchar(255) null, is_active tinyint default 1, verified_at datetime null, last_seen_at datetime null, deleted_at datetime null, created_at datetime null, updated_at datetime null)');
        DB::table('emails')->insert([
            'address' => 'Buyer@Example.test',
            'name' => 'Buyer One',
            'domain' => 'example.test',
            'source' => 'crm',
            'is_active' => 1,
            'last_seen_at' => '2026-06-21 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $source = $this->get('/Ameise/commercial-offers/source-emails?q=buyer')
            ->assertOk()
            ->assertJsonPath('data.0.address', 'Buyer@Example.test')
            ->json('data.0');

        $this->post('/Ameise/commercial-offers/contacts/import-existing-emails', [
            'email_ids' => [$source['id']],
            'consent_status' => 'confirmed',
        ])
            ->assertOk()
            ->assertJson(['requested' => 1, 'imported' => 1]);

        $this->assertDatabaseHas('mailing_contacts', [
            'email' => 'buyer@example.test',
            'normalized_email' => 'buyer@example.test',
            'first_name' => 'Buyer One',
            'source_type' => 'pischeprom_db',
            'contact_source' => 'emails',
            'consent_status' => 'confirmed',
        ]);

        $this->get('/Ameise/commercial-offers/source-emails?q=buyer')
            ->assertOk()
            ->assertJsonPath('data.0.imported', true);
    }

    public function test_products_are_loaded_from_existing_goods_database(): void
    {
        $this->createCatalogTables();

        DB::table('currencies')->insert(['id' => 1, 'code' => 'RUB']);
        DB::table('price_types')->insert([
            ['id' => 1, 'name' => 'Розничная', 'code' => 'retail', 'currency_id' => 1, 'is_active' => 1, 'is_public' => 1, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Оптовая', 'code' => 'wholesale', 'currency_id' => 1, 'is_active' => 1, 'is_public' => 0, 'sort_order' => 20, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('vat_rates')->insert(['id' => 1, 'title' => 'НДС 22%', 'rate' => 22, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('categories')->insert(['id' => 1, 'name' => 'Эмульгаторы', 'slug' => 'emulgatory', 'local_code' => null, 'meta_title' => null, 'is_published' => 1]);
        DB::table('products')->insert(['id' => 1, 'category_id' => 1, 'rus' => 'Лецитин', 'eng' => 'Lecithin', 'is_published' => 1]);
        DB::table('goods')->insert(['id' => 7, 'name' => 'Лецитин подсолнечный', 'slug' => 'lecithin', 'ava_image' => null, 'ava_thumb' => null, 'description' => 'Пищевой лецитин', 'is_published' => 1, 'vat_rate_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('good_product')->insert(['good_id' => 7, 'product_id' => 1]);
        DB::table('good_media')->insert(['id' => 1, 'good_id' => 7, 'type' => 'image', 'url' => 'https://pischeprom.test/i/lecithin.jpg', 'thumb_url' => 'https://pischeprom.test/i/lecithin-thumb.jpg', 'is_published' => 1, 'is_ava' => 1, 'sort_order' => 1]);
        DB::table('good_price_type_values')->insert([
            ['id' => 1, 'good_id' => 7, 'price_type_id' => 1, 'currency_id' => 1, 'price_net' => 100, 'price_gross' => 120, 'vat_rate' => 20, 'is_published' => 1, 'updated_at' => now()->subDay()],
            ['id' => 2, 'good_id' => 7, 'price_type_id' => 2, 'currency_id' => 1, 'price_net' => 80, 'price_gross' => 96, 'vat_rate' => 20, 'is_published' => 1, 'updated_at' => now()],
        ]);

        $this->get('/Ameise/commercial-offers/price-types')
            ->assertOk()
            ->assertJsonPath('0.name', 'Розничная')
            ->assertJsonPath('1.name', 'Оптовая');

        $this->get('/Ameise/commercial-offers/products/search?q=lecithin&per_page=25')
            ->assertOk()
            ->assertJsonPath('products.current_page', 1)
            ->assertJsonPath('products.per_page', 25)
            ->assertJsonPath('products.data.0.id', 7)
            ->assertJsonPath('products.data.0.title', 'Лецитин подсолнечный')
            ->assertJsonPath('products.data.0.source_table', 'goods')
            ->assertJsonPath('products.data.0.price_formatted', '96,00 RUB')
            ->assertJsonPath('products.data.0.price_type_name', 'Оптовая')
            ->assertJsonPath('products.data.0.thumbnail_url', 'https://pischeprom.test/i/lecithin-thumb.jpg')
            ->assertJsonPath('products.data.0.vat_rate', 22);

        $this->get('/Ameise/commercial-offers/products/search?'.http_build_query([
            'q' => 'lecithin',
            'per_page' => 25,
            'price_type_id' => 1,
        ]))
            ->assertOk()
            ->assertJsonPath('products.data.0.price_formatted', '120,00 RUB')
            ->assertJsonPath('products.data.0.price_type_name', 'Розничная');

        $campaign = $this->campaign();
        $this->post("/Ameise/commercial-offers/campaigns/{$campaign->id}/offer-items", [
            'product_id' => 7,
            'item_type' => 'product',
            'price_type_id' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('offer_price', '120.0000')
            ->assertJsonPath('snapshot.price_type_id', 1)
            ->assertJsonPath('snapshot.price_type_name', 'Розничная');

        $this->get('/Ameise/commercial-offers/products/search?'.http_build_query([
            'type' => 'categories',
            'q' => 'Эмульгаторы',
        ]))
            ->assertOk()
            ->assertJsonPath('categories.current_page', 1)
            ->assertJsonPath('categories.data.0.id', 1)
            ->assertJsonPath('categories.data.0.name', 'Эмульгаторы');
    }

    public function test_campaign_offer_items_can_be_added_listed_and_deleted(): void
    {
        $this->createCatalogTables();

        DB::table('goods')->insert([
            'id' => 9,
            'name' => 'Прайс-лист позиция',
            'slug' => 'price-list-item',
            'ava_image' => null,
            'ava_thumb' => null,
            'description' => 'Тестовая позиция КП',
            'is_published' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $campaign = $this->campaign();

        $response = $this->post("/Ameise/commercial-offers/campaigns/{$campaign->id}/offer-items", [
            'product_id' => 9,
            'item_type' => 'product',
        ])
            ->assertCreated()
            ->assertJsonPath('campaign_id', $campaign->id)
            ->assertJsonPath('product_id', 9)
            ->assertJsonPath('title', 'Прайс-лист позиция');

        $this->get("/Ameise/commercial-offers/campaigns/{$campaign->id}")
            ->assertOk()
            ->assertJsonPath('offer_items.0.id', $response->json('id'))
            ->assertJsonPath('offer_items.0.title', 'Прайс-лист позиция');

        $this->delete('/Ameise/commercial-offers/offer-items/'.$response->json('id'))
            ->assertOk()
            ->assertJson(['deleted' => true]);

        $this->assertDatabaseMissing('mailing_offer_items', [
            'id' => $response->json('id'),
        ]);
    }

    public function test_campaign_offer_items_can_be_bulk_added_from_filtered_goods(): void
    {
        $this->createCatalogTables();

        DB::table('goods')->insert([
            ['id' => 20, 'name' => 'Лецитин А', 'slug' => 'lecithin-a', 'description' => 'Эмульгатор', 'is_published' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 21, 'name' => 'Лецитин Б', 'slug' => 'lecithin-b', 'description' => 'Эмульгатор', 'is_published' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 22, 'name' => 'Масло какао', 'slug' => 'cocoa-butter', 'description' => 'Жир', 'is_published' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $campaign = $this->campaign();
        app(ProductOfferBuilder::class)->addProductToCampaign($campaign->id, 20);

        $this->post("/Ameise/commercial-offers/campaigns/{$campaign->id}/offer-items/add-filtered", [
            'type' => 'goods',
            'q' => 'Лецитин',
            'published' => 'true',
        ])
            ->assertCreated()
            ->assertJsonPath('matched', 2)
            ->assertJsonPath('added', 1)
            ->assertJsonPath('skipped_existing', 1);

        $this->assertDatabaseCount('mailing_offer_items', 2);
        $this->assertDatabaseHas('mailing_offer_items', [
            'campaign_id' => $campaign->id,
            'product_id' => 20,
        ]);
        $this->assertDatabaseHas('mailing_offer_items', [
            'campaign_id' => $campaign->id,
            'product_id' => 21,
        ]);
        $this->assertDatabaseMissing('mailing_offer_items', [
            'campaign_id' => $campaign->id,
            'product_id' => 22,
        ]);

        $this->post("/Ameise/commercial-offers/campaigns/{$campaign->id}/offer-items/add-filtered", [
            'type' => 'goods',
            'q' => 'Лецитин',
            'published' => 'true',
        ])
            ->assertCreated()
            ->assertJsonPath('matched', 2)
            ->assertJsonPath('added', 0)
            ->assertJsonPath('skipped_existing', 2);

        $this->assertDatabaseCount('mailing_offer_items', 2);
    }

    public function test_product_video_media_is_not_used_as_commercial_offer_thumbnail(): void
    {
        $this->createCatalogTables();

        DB::table('currencies')->insert(['id' => 1, 'code' => 'RUB']);
        DB::table('price_types')->insert(['id' => 1, 'name' => 'Розничная', 'code' => 'retail', 'currency_id' => 1, 'is_active' => 1, 'is_public' => 1, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('goods')->insert([
            'id' => 8,
            'name' => 'Шар пробный',
            'slug' => 'test-ball',
            'ava_image' => 'https://pischeprom.test/video/test-ball.mp4',
            'ava_thumb' => null,
            'description' => 'Видео есть, картинки нет',
            'is_published' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('good_media')->insert([
            'id' => 2,
            'good_id' => 8,
            'type' => 'video',
            'url' => 'https://pischeprom.test/video/test-ball.mp4',
            'thumb_url' => null,
            'is_published' => 1,
            'is_ava' => 1,
            'sort_order' => 1,
        ]);
        DB::table('good_price_type_values')->insert(['id' => 2, 'good_id' => 8, 'price_type_id' => 1, 'currency_id' => 1, 'price_net' => 100, 'price_gross' => 120, 'is_published' => 1, 'updated_at' => now()]);
        $campaign = $this->campaign();

        $this->get('/Ameise/commercial-offers/products/search?q=шар')
            ->assertOk()
            ->assertJsonPath('products.data.0.thumbnail_url', null);

        $item = app(ProductOfferBuilder::class)->addProductToCampaign($campaign->id, 8);

        $this->assertNull($item->thumbnail_url);
        $html = app(MailingRenderer::class)->renderProductCard($item, $campaign);
        $this->assertStringNotContainsString('.mp4', $html);
        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringContainsString('width:52px;height:52px', $html);
    }

    public function test_campaign_recipients_can_be_managed_from_emails_and_units(): void
    {
        $this->createEmailUnitTables();

        DB::table('emails')->insert([
            ['id' => 1, 'address' => 'direct@example.test', 'name' => 'Direct Buyer', 'domain' => 'example.test', 'source' => 'crm', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'address' => 'unit@example.test', 'name' => 'Unit Buyer', 'domain' => 'example.test', 'source' => 'unit', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('units')->insert(['id' => 1, 'name' => 'Factory Unit', 'is_customer' => 1, 'is_supplier' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('email_unit')->insert(['email_id' => 2, 'unit_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $campaign = $this->campaign(['status' => 'draft', 'contact_set_id' => null]);

        $this->get("/Ameise/commercial-offers/campaigns/{$campaign->id}/recipient-picker/emails?q=direct")
            ->assertOk()
            ->assertJsonPath('data.0.address', 'direct@example.test')
            ->assertJsonPath('data.0.name', 'Direct Buyer')
            ->assertJsonPath('data.0.selected', false);

        $this->get("/Ameise/commercial-offers/campaigns/{$campaign->id}/recipient-picker/emails?q=unit")
            ->assertOk()
            ->assertJsonPath('data.0.address', 'unit@example.test')
            ->assertJsonPath('data.0.name', 'Unit Buyer')
            ->assertJsonPath('data.0.company_name', 'Factory Unit');

        $this->get("/Ameise/commercial-offers/campaigns/{$campaign->id}/recipient-picker/units?q=factory")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Factory Unit')
            ->assertJsonPath('data.0.emails.0.address', 'unit@example.test')
            ->assertJsonPath('data.0.emails.0.name', 'Unit Buyer')
            ->assertJsonPath('data.0.emails.0.company_name', 'Factory Unit')
            ->assertJsonPath('data.0.emails.0.source_unit_id', 1)
            ->assertJsonPath('data.0.emails.0.source_unit_name', 'Factory Unit');

        $response = $this->post("/Ameise/commercial-offers/campaigns/{$campaign->id}/recipients", [
            'replace' => true,
            'recipients' => [
                [
                    'email' => 'direct@example.test',
                    'name' => 'Direct Buyer',
                    'source' => 'email',
                    'source_email_id' => 1,
                ],
                [
                    'email' => 'unit@example.test',
                    'name' => 'Unit Buyer',
                    'company_name' => 'Factory Unit',
                    'source' => 'unit:Factory Unit',
                    'source_email_id' => 2,
                    'source_unit_id' => 1,
                    'source_unit_name' => 'Factory Unit',
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('recipients.0.name', 'Direct Buyer')
            ->assertJsonPath('recipients.1.name', 'Unit Buyer')
            ->assertJsonPath('recipients.1.company_name', 'Factory Unit')
            ->assertJsonPath('recipients.1.source_unit_name', 'Factory Unit');

        $this->assertDatabaseHas('mailing_campaign_recipients', ['campaign_id' => $campaign->id, 'email' => 'direct@example.test', 'status' => 'pending']);
        $this->assertDatabaseHas('mailing_campaign_recipients', ['campaign_id' => $campaign->id, 'email' => 'unit@example.test', 'status' => 'pending']);
        $this->assertDatabaseHas('mailing_contacts', ['email' => 'direct@example.test', 'contact_source' => 'emails']);
        $this->assertDatabaseHas('mailing_contacts', ['email' => 'unit@example.test', 'first_name' => 'Unit Buyer', 'company_name' => 'Factory Unit']);

        $this->get("/Ameise/commercial-offers/campaigns/{$campaign->id}/recipients")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.company_name', 'Factory Unit');

        $recipientId = $response->json('recipients.0.id');
        $this->delete("/Ameise/commercial-offers/campaigns/{$campaign->id}/recipients/{$recipientId}")
            ->assertOk()
            ->assertJson(['removed' => true]);
    }

    public function test_mass_campaign_can_send_to_manual_campaign_recipients_without_contact_set(): void
    {
        Http::fake([
            '*email/send.json*' => Http::response([
                'job_id' => 'manual-recipient-job',
                'failed_emails' => [],
            ]),
        ]);

        $campaign = $this->campaign(['type' => 'mass_offer', 'contact_set_id' => null]);

        $this->post("/Ameise/commercial-offers/campaigns/{$campaign->id}/recipients", [
            'replace' => true,
            'emails' => ['manual@example.test'],
        ])->assertOk()
            ->assertJsonPath('total', 1);

        app(MailingCampaignService::class)->startSending($campaign);

        $this->assertDatabaseHas('mailing_campaign_recipients', [
            'campaign_id' => $campaign->id,
            'email' => 'manual@example.test',
            'status' => 'accepted',
            'unisender_job_id' => 'manual-recipient-job',
        ]);
    }

    public function test_campaign_can_be_created_from_template_content(): void
    {
        $template = MailingTemplate::query()->create([
            'name' => 'Template offer',
            'slug' => 'template-offer',
            'type' => 'commercial_offer',
            'subject' => 'Template subject',
            'html_markup' => '<p>Здравствуйте, {{to_name}}</p><p><a href="{{unsubscribe_url}}">Отписаться</a></p>',
            'plaintext' => 'Здравствуйте, {{to_name}} {{unsubscribe_url}}',
        ]);

        $response = $this->post('/Ameise/commercial-offers/campaigns', [
            'name' => 'Campaign from template',
            'template_id' => $template->id,
        ])->assertCreated();

        $this->assertDatabaseHas('mailing_campaigns', [
            'id' => $response->json('id'),
            'template_id' => $template->id,
            'subject' => 'Template subject',
            'html_markup' => '<p>Здравствуйте, {{to_name}}</p><p><a href="{{unsubscribe_url}}">Отписаться</a></p>',
            'plaintext' => 'Здравствуйте, {{to_name}} {{unsubscribe_url}}',
        ]);
    }

    public function test_campaign_list_shows_template_and_template_can_be_changed(): void
    {
        $firstTemplate = MailingTemplate::query()->create([
            'name' => 'Old template',
            'slug' => 'old-template',
            'type' => 'commercial_offer',
            'subject' => 'Old subject',
            'html_markup' => '<p>old {{unsubscribe_url}}</p>',
            'plaintext' => 'old {{unsubscribe_url}}',
        ]);
        $secondTemplate = MailingTemplate::query()->create([
            'name' => 'New template',
            'slug' => 'new-template',
            'type' => 'commercial_offer',
            'subject' => 'New subject',
            'html_markup' => '<p>new {{greeting}} {{unsubscribe_url}}</p>',
            'plaintext' => 'new {{greeting}} {{unsubscribe_url}}',
        ]);
        $campaign = $this->campaign([
            'template_id' => $firstTemplate->id,
            'subject' => $firstTemplate->subject,
            'html_markup' => $firstTemplate->html_markup,
            'plaintext' => $firstTemplate->plaintext,
        ]);

        $this->get('/Ameise/commercial-offers/campaigns')
            ->assertOk()
            ->assertJsonPath('data.0.id', $campaign->id)
            ->assertJsonPath('data.0.template.id', $firstTemplate->id)
            ->assertJsonPath('data.0.template.name', 'Old template');

        $this->put("/Ameise/commercial-offers/campaigns/{$campaign->id}", [
            'template_id' => $secondTemplate->id,
            'subject' => $secondTemplate->subject,
            'html_markup' => $secondTemplate->html_markup,
            'plaintext' => $secondTemplate->plaintext,
        ])
            ->assertOk()
            ->assertJsonPath('template_id', $secondTemplate->id)
            ->assertJsonPath('subject', 'New subject')
            ->assertJsonPath('html_markup', '<p>new {{greeting}} {{unsubscribe_url}}</p>');

        $this->assertDatabaseHas('mailing_campaigns', [
            'id' => $campaign->id,
            'template_id' => $secondTemplate->id,
            'subject' => 'New subject',
            'html_markup' => '<p>new {{greeting}} {{unsubscribe_url}}</p>',
            'plaintext' => 'new {{greeting}} {{unsubscribe_url}}',
        ]);
    }

    public function test_commercial_offer_image_upload_returns_public_storage_url(): void
    {
        Storage::fake('public');

        $response = $this->post('/Ameise/commercial-offers/images', [
            'image' => UploadedFile::fake()->image('logo.png', 120, 40),
            'alt' => 'Pischeprom logo',
        ])->assertCreated();

        $path = $response->json('path');
        $url = $response->json('url');

        $this->assertStringStartsWith('commercial-offers/images/', $path);
        $this->assertStringContainsString('/storage/commercial-offers/images/', $url);
        $this->assertSame('Pischeprom logo', $response->json('alt'));
        Storage::disk('public')->assertExists($path);
    }

    public function test_invalid_campaign_id_when_adding_product_returns_validation_error(): void
    {
        $this->post('/Ameise/commercial-offers/campaigns/Lecithin/offer-items', [
            'product_id' => 7,
            'item_type' => 'product',
        ])
            ->assertStatus(422)
            ->assertJson(['message' => 'Select a valid campaign before adding products to КП.']);
    }

    private function campaign(array $overrides = []): MailingCampaign
    {
        return MailingCampaign::query()->create($overrides + [
            'name' => 'June offer',
            'type' => 'mass_offer',
            'status' => 'ready',
            'subject' => 'Коммерческое предложение',
            'html_markup' => '<h1>{{campaign_name}}</h1><p>{{first_name}}</p>{{offer_items_html}}<p><a href="{{unsubscribe_url}}">unsubscribe</a></p>',
            'plaintext' => 'Коммерческое предложение {{unsubscribe_url}}',
            'from_email' => 'sales@pischeprom.ru',
            'from_name' => 'Pischeprom',
            'reply_to' => 'sales@pischeprom.ru',
            'compliance_status' => 'approved',
        ]);
    }

    private function event(string $status, MailingCampaignRecipient $recipient, string $time, string $url = ''): array
    {
        return [
            'event_name' => 'transactional_email_status',
            'event_data' => [
                'job_id' => $recipient->unisender_job_id,
                'metadata' => [
                    'campaign_id' => $recipient->campaign_id,
                    'campaign_recipient_id' => $recipient->id,
                    'contact_id' => $recipient->contact_id,
                ],
                'email' => $recipient->email,
                'status' => $status,
                'event_time' => $time,
                'url' => $url,
                'delivery_info' => ['status' => $status, 'destination_response' => 'ok'],
            ],
        ];
    }

    private function createCatalogTables(): void
    {
        DB::statement('create table vat_rates (id integer primary key autoincrement, title varchar(255) not null, rate decimal(10,4) not null, created_at datetime null, updated_at datetime null)');
        DB::statement('create table goods (id integer primary key autoincrement, name varchar(255) not null, slug varchar(255) null, ava_image varchar(255) null, ava_thumb varchar(255) null, description text null, is_published tinyint default 1, vat_rate_id integer null, created_at datetime null, updated_at datetime null)');
        DB::statement('create table categories (id integer primary key autoincrement, name varchar(255) not null, slug varchar(255) null, local_code varchar(255) null, meta_title varchar(255) null, is_published tinyint default 1)');
        DB::statement('create table units (id integer primary key autoincrement, name varchar(255) null)');
        DB::statement('create table manufacturers (product_id integer not null, unit_id integer not null)');
        DB::statement('create table products (id integer primary key autoincrement, category_id integer null, rus varchar(255) null, eng varchar(255) null, zh varchar(255) null, es varchar(255) null, ar varchar(255) null, hi varchar(255) null, ur varchar(255) null, de varchar(255) null, fr varchar(255) null, po varchar(255) null, it varchar(255) null, nl varchar(255) null, tu varchar(255) null, fa varchar(255) null, vi varchar(255) null, ja varchar(255) null, ko varchar(255) null, he varchar(255) null, idn varchar(255) null, is_published tinyint default 1)');
        DB::statement('create table good_product (good_id integer not null, product_id integer not null)');
        DB::statement('create table good_media (id integer primary key autoincrement, good_id integer not null, type varchar(255) not null, url varchar(255) null, thumb_url varchar(255) null, is_published tinyint default 1, is_ava tinyint default 0, sort_order integer default 100)');
        DB::statement('create table currencies (id integer primary key autoincrement, code varchar(10) null)');
        DB::statement('create table price_types (id integer primary key autoincrement, name varchar(255) not null, code varchar(255) null, description text null, currency_id integer null, markup_percent decimal(10,4) null, target_margin_percent decimal(10,4) null, rounding_step decimal(12,4) null, is_active tinyint default 1, is_public tinyint default 0, sort_order integer default 100, created_at datetime null, updated_at datetime null)');
        DB::statement('create table good_price_type_values (id integer primary key autoincrement, good_id integer not null, price_type_id integer null, currency_id integer null, price_net decimal(16,4) null, price_gross decimal(16,4) null, vat_rate decimal(10,4) null, is_published tinyint default 1, updated_at datetime null)');
    }

    private function createEmailUnitTables(): void
    {
        DB::statement('create table emails (id integer primary key autoincrement, address varchar(255) not null, name varchar(255) null, domain varchar(255) null, source varchar(255) null, is_active tinyint default 1, verified_at datetime null, last_seen_at datetime null, deleted_at datetime null, created_at datetime null, updated_at datetime null)');
        DB::statement('create table units (id integer primary key autoincrement, name varchar(255) null, is_customer tinyint default 0, is_supplier tinyint default 0, created_at datetime null, updated_at datetime null)');
        DB::statement('create table email_unit (id integer primary key autoincrement, email_id integer not null, unit_id integer not null, created_at datetime null, updated_at datetime null)');
    }
}
