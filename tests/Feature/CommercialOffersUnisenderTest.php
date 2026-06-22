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
        $campaign = $this->campaign(['plaintext' => null]);
        $contact = MailingContact::query()->create(['email' => 'buyer@example.test', 'normalized_email' => 'buyer@example.test', 'consent_status' => 'confirmed']);
        $recipient = MailingCampaignRecipient::query()->create(['campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'email' => $contact->email, 'status' => 'pending']);
        $item = MailingOfferItem::query()->create([
            'campaign_id' => $campaign->id,
            'product_id' => 123,
            'item_type' => 'product',
            'title' => 'Насос пищевой',
            'thumbnail_url' => 'https://pischeprom.test/i/pump.jpg',
            'canonical_url' => 'https://pischeprom.test/g/pump',
            'original_price' => 1000,
            'offer_price' => 900,
            'currency' => 'RUB',
            'description' => 'Описание товара',
        ]);

        $renderer = app(MailingRenderer::class);
        $html = $renderer->renderCampaignHtml($campaign, $contact, [$item], $recipient);
        $text = $renderer->renderPlaintext($campaign, $contact, [$item], $recipient);

        $this->assertStringContainsString('https://pischeprom.test/i/pump.jpg', $html);
        $this->assertStringContainsString('900,00 RUB', $html);
        $this->assertStringContainsString('utm_source=unisender', $html);
        $this->assertStringContainsString($recipient->unsubscribe_token, $html);
        $this->assertStringContainsString('Насос пищевой', $text);
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
        DB::table('price_types')->insert(['id' => 1, 'currency_id' => 1]);
        DB::table('categories')->insert(['id' => 1, 'name' => 'Эмульгаторы', 'slug' => 'emulgatory', 'local_code' => null, 'meta_title' => null, 'is_published' => 1]);
        DB::table('products')->insert(['id' => 1, 'category_id' => 1, 'rus' => 'Лецитин', 'eng' => 'Lecithin', 'is_published' => 1]);
        DB::table('goods')->insert(['id' => 7, 'name' => 'Лецитин подсолнечный', 'slug' => 'lecithin', 'ava_image' => null, 'ava_thumb' => null, 'description' => 'Пищевой лецитин', 'is_published' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('good_product')->insert(['good_id' => 7, 'product_id' => 1]);
        DB::table('good_media')->insert(['id' => 1, 'good_id' => 7, 'type' => 'image', 'url' => 'https://pischeprom.test/i/lecithin.jpg', 'thumb_url' => 'https://pischeprom.test/i/lecithin-thumb.jpg', 'is_published' => 1, 'is_ava' => 1, 'sort_order' => 1]);
        DB::table('good_price_type_values')->insert(['id' => 1, 'good_id' => 7, 'price_type_id' => 1, 'currency_id' => 1, 'price_net' => 100, 'price_gross' => 120, 'is_published' => 1, 'updated_at' => now()]);

        $this->get('/Ameise/commercial-offers/products/search?q=lecithin')
            ->assertOk()
            ->assertJsonPath('products.0.id', 7)
            ->assertJsonPath('products.0.title', 'Лецитин подсолнечный')
            ->assertJsonPath('products.0.source_table', 'goods')
            ->assertJsonPath('products.0.price_formatted', '120,00 RUB')
            ->assertJsonPath('products.0.thumbnail_url', 'https://pischeprom.test/i/lecithin-thumb.jpg');
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
        DB::statement('create table goods (id integer primary key autoincrement, name varchar(255) not null, slug varchar(255) null, ava_image varchar(255) null, ava_thumb varchar(255) null, description text null, is_published tinyint default 1, created_at datetime null, updated_at datetime null)');
        DB::statement('create table categories (id integer primary key autoincrement, name varchar(255) not null, slug varchar(255) null, local_code varchar(255) null, meta_title varchar(255) null, is_published tinyint default 1)');
        DB::statement('create table units (id integer primary key autoincrement, name varchar(255) null)');
        DB::statement('create table manufacturers (product_id integer not null, unit_id integer not null)');
        DB::statement('create table products (id integer primary key autoincrement, category_id integer null, rus varchar(255) null, eng varchar(255) null, zh varchar(255) null, es varchar(255) null, ar varchar(255) null, hi varchar(255) null, ur varchar(255) null, de varchar(255) null, fr varchar(255) null, po varchar(255) null, it varchar(255) null, nl varchar(255) null, tu varchar(255) null, fa varchar(255) null, vi varchar(255) null, ja varchar(255) null, ko varchar(255) null, he varchar(255) null, idn varchar(255) null, is_published tinyint default 1)');
        DB::statement('create table good_product (good_id integer not null, product_id integer not null)');
        DB::statement('create table good_media (id integer primary key autoincrement, good_id integer not null, type varchar(255) not null, url varchar(255) null, thumb_url varchar(255) null, is_published tinyint default 1, is_ava tinyint default 0, sort_order integer default 100)');
        DB::statement('create table currencies (id integer primary key autoincrement, code varchar(10) null)');
        DB::statement('create table price_types (id integer primary key autoincrement, currency_id integer null)');
        DB::statement('create table good_price_type_values (id integer primary key autoincrement, good_id integer not null, price_type_id integer null, currency_id integer null, price_net decimal(16,4) null, price_gross decimal(16,4) null, is_published tinyint default 1, updated_at datetime null)');
    }
}
