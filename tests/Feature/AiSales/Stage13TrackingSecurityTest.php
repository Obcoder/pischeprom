<?php

namespace Tests\Feature\AiSales;

use App\Models\Email;
use App\Models\Sending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Stage13TrackingSecurityTest extends Stage13TestCase
{
    public function test_open_tracking_uses_opaque_token_without_ip_ua_or_permission_side_effects(): void
    {
        $sending = $this->sending();

        $this->get("/email/open/{$sending->tracking_token}")
            ->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->get("/email/open/{$sending->tracking_token}")->assertOk();

        $sending->refresh();
        $this->assertSame(1, $sending->opens_count);
        $this->assertNull($sending->last_open_ip);
        $this->assertNull($sending->last_open_ua);
        $this->assertDatabaseCount('communication_permissions', 0);
        $this->assertDatabaseCount('mailing_events', 1);
        $event = DB::table('mailing_events')->first();
        foreach (['payload', 'email', 'url', 'user_agent', 'ip', 'metadata'] as $column) {
            $this->assertNull($event->{$column});
        }
    }

    public function test_click_tracking_rejects_arbitrary_redirect_without_logging_token_or_url(): void
    {
        $sending = $this->sending();
        Log::spy();

        $this->get("/email/click/{$sending->tracking_token}?url=".urlencode('https://attacker.example/steal'))
            ->assertBadRequest();
        $this->get("/email/click/{$sending->tracking_token}?url=".urlencode('https://vk.com/arbitrary-profile'))
            ->assertBadRequest();

        $this->assertSame(0, $sending->fresh()->click_count);
        Log::shouldNotHaveReceived('error');
    }

    public function test_click_tracking_allows_only_code_owned_application_target(): void
    {
        $sending = $this->sending();
        config()->set('app.url', 'https://crm.example.test');
        $target = 'https://crm.example.test/catalog/item';

        $this->get("/email/click/{$sending->tracking_token}?url=".urlencode($target))
            ->assertRedirect($target);
        $this->get("/email/click/{$sending->tracking_token}?url=".urlencode($target))
            ->assertRedirect($target);

        $sending->refresh();
        $this->assertSame(1, $sending->click_count);
        $this->assertSame(1, $sending->clicks_count);
        $this->assertDatabaseCount('communication_permissions', 0);
        $this->assertDatabaseCount('mailing_events', 1);
    }

    public function test_invalid_tracking_token_is_fail_closed(): void
    {
        $this->get('/email/open/not-a-token')->assertNotFound();
        $this->get('/email/click/not-a-token?url='.urlencode('https://crm.example.test'))->assertNotFound();
    }

    private function sending(): Sending
    {
        $email = Email::query()->create(['address' => 'tracking-'.uniqid().'@example.test', 'source' => 'stage13_test']);

        return Sending::query()->create([
            'email_id' => $email->id, 'subject' => 'Synthetic tracking',
            'provider' => 'unisender_go', 'status' => 'sent',
        ]);
    }
}
