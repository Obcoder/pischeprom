<?php

namespace Tests\Feature;

use App\Models\MailingCampaign;
use App\Models\MailingCampaignRecipient;
use App\Models\MailingContact;
use App\Models\MailingContactSet;
use App\Models\MailingOfferItem;
use App\Models\MailingSuppression;
use App\Services\CommercialOffers\MailingCampaignService;
use App\Services\CommercialOffers\MailingRenderer;
use App\Services\CommercialOffers\RecipientSetService;
use App\Services\CommercialOffers\UnisenderGoClient;
use App\Services\CommercialOffers\UnisenderWebhookPayload;
use App\Services\CommercialOffers\UnisenderWebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
            'services.unisender_go.api_base' => 'https://goapi.unisender.test/ru/transactional/api/v1',
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
            '*email/send.json' => Http::response(['job_id' => 'job-1', 'failed_emails' => []]),
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
                && str_ends_with($request->url(), '/email/send.json')
                && $payload['message']['track_read'] === 1
                && $payload['message']['track_links'] === 1
                && $payload['message']['idempotence_key'] === 'idem-1'
                && $payload['message']['recipients'][0]['metadata']['campaign_recipient_id'] === 10;
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
            '*email/send.json' => Http::response([
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
                && $recipients[0]['metadata']['campaign_id'] > 0
                && $recipients[0]['metadata']['campaign_recipient_id'] > 0;
        });
    }

    public function test_webhook_updates_open_click_bounce_spam_and_is_idempotent(): void
    {
        Http::fake(['*suppression/set.json' => Http::response(['ok' => true])]);
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
        Http::fake(['*suppression/set.json' => Http::response(['ok' => true])]);
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
            '*suppression/list.json' => Http::response(['message' => 'user not found'], 401),
        ]);

        $this->post('/Ameise/commercial-offers/settings/test-api')
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonFragment(['message' => 'Unisender Go rejected API key/account: user not found. Check that UNISENDER_GO_API_KEY belongs to Unisender Go Transactional API, the correct project/account is active, the key has no extra spaces, and production config cache was cleared.']);
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
}
