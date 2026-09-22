<?php

namespace Tests\Feature\Mail;

use App\Models\Lead;
use App\Models\MailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LeadDeletedMailLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Queue::fake();
    }

    public function test_index_hides_deleted_mail_links_and_loads_mail_in_one_query(): void
    {
        [$activeMail, $deletedMail, $activeLead, $deletedLead] = $this->linkedLeads();

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $response = $this->getJson('/api/leads')->assertOk()->assertJsonCount(2, 'data');
            $mailQueries = collect(DB::getQueryLog())
                ->filter(fn (array $query) => str_contains($query['query'], 'from "mail_messages"'));
        } finally {
            DB::disableQueryLog();
        }

        $leads = collect($response->json('data'))->keyBy('id');
        $this->assertSame($activeMail->id, $leads[$activeLead->id]['mail_message_id']);
        $this->assertNull($leads[$deletedLead->id]['mail_message_id']);
        $this->assertCount(1, $mailQueries);
        $this->assertDatabaseHas('leads', ['id' => $activeLead->id, 'mail_message_id' => $activeMail->id]);
        $this->assertDatabaseHas('leads', ['id' => $deletedLead->id, 'mail_message_id' => $deletedMail->id]);
        $this->assertSoftDeleted($deletedMail);
    }

    public function test_show_keeps_the_lead_and_its_foreign_key_after_source_mail_is_deleted(): void
    {
        [$activeMail, $deletedMail, $activeLead, $deletedLead] = $this->linkedLeads();

        $this->getJson('/api/leads/'.$deletedLead->id)
            ->assertOk()
            ->assertJsonPath('data.id', $deletedLead->id)
            ->assertJsonPath('data.title', $deletedLead->title)
            ->assertJsonPath('data.mail_message_id', null);

        $this->getJson('/api/leads/'.$activeLead->id)
            ->assertOk()
            ->assertJsonPath('data.mail_message_id', $activeMail->id);

        $this->assertSame($deletedMail->id, $deletedLead->fresh()->mail_message_id);
        $this->assertSame($activeMail->id, $activeLead->fresh()->mail_message_id);
        $this->assertSame($deletedMail->message_id, MailMessage::withTrashed()->findOrFail($deletedMail->id)->message_id);
    }

    public function test_dashboard_keeps_both_leads_but_only_links_to_available_mail(): void
    {
        [$activeMail, $deletedMail, $activeLead, $deletedLead] = $this->linkedLeads();

        $this->get('/Ameise/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ameise/Verwalter')
                ->has('activeLeads', 2)
                ->where('activeLeads', function ($leads) use ($activeMail, $activeLead, $deletedLead): bool {
                    $byId = collect($leads)->keyBy('id');

                    return $byId[$activeLead->id]['mail_message_id'] === $activeMail->id
                        && $byId[$deletedLead->id]['mail_message_id'] === null;
                }));

        $this->assertSame($deletedMail->id, $deletedLead->fresh()->mail_message_id);
    }

    private function linkedLeads(): array
    {
        $activeMail = MailMessage::create([
            'mailbox' => 'office@example.test',
            'folder' => 'INBOX',
            'direction' => 'incoming',
            'imap_uid' => 41,
            'message_id' => '<active-lead@example.test>',
            'subject' => 'Active source mail',
        ]);
        $deletedMail = MailMessage::create([
            'mailbox' => 'office@example.test',
            'folder' => 'INBOX',
            'direction' => 'incoming',
            'imap_uid' => 42,
            'message_id' => '<deleted-lead@example.test>',
            'subject' => 'Deleted source mail',
        ]);
        $activeLead = Lead::create([
            'source' => 'mail',
            'status' => Lead::STATUS_OPEN,
            'title' => 'Lead with available source mail',
            'mail_message_id' => $activeMail->id,
        ]);
        $deletedLead = Lead::create([
            'source' => 'mail',
            'status' => Lead::STATUS_OPEN,
            'title' => 'Lead retained after source mail deletion',
            'mail_message_id' => $deletedMail->id,
        ]);
        $deletedMail->delete();

        return [$activeMail, $deletedMail, $activeLead, $deletedLead];
    }
}
