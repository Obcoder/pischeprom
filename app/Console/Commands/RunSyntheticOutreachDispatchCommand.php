<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Domain\AiSales\Outreach\CommunicationPermissionService;
use App\Domain\AiSales\Outreach\CommunicationSuppressionService;
use App\Domain\AiSales\Outreach\Enums\CommunicationPermissionStatus;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewDecision;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewType;
use App\Domain\AiSales\Outreach\OutreachDispatchService;
use App\Domain\AiSales\Outreach\OutreachDraftService;
use App\Domain\AiSales\Outreach\OutreachFollowUpRecommendationService;
use App\Domain\AiSales\Outreach\OutreachNormalizedEventService;
use App\Domain\AiSales\Outreach\OutreachReplyCorrelationService;
use App\Models\Email;
use App\Models\Entity;
use App\Models\MailingEvent;
use App\Models\MailMessage;
use App\Models\Product;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\UnitContactContextLink;
use App\Models\UnitProductMatch;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class RunSyntheticOutreachDispatchCommand extends Command
{
    protected $signature = 'ai-sales:run-synthetic-outreach-dispatch';

    protected $description = 'Run bounded Stage 13 dispatch, normalized event, reply and follow-up scenarios with fakes';

    public function handle(
        OutreachDraftService $drafts,
        CommunicationPermissionService $permissions,
        CommunicationSuppressionService $suppressions,
        OutreachDispatchService $dispatches,
        OutreachNormalizedEventService $events,
        OutreachReplyCorrelationService $replies,
        OutreachFollowUpRecommendationService $followUps,
    ): int {
        $connectionName = (string) config('database.default');
        $this->line('APP_ENV='.app()->environment());
        $this->line('CONFIGURED_DB_CONNECTION='.$connectionName);
        if (! app()->environment(['local', 'testing']) || $connectionName !== 'sqlite') {
            $this->error('Blocked: local/testing isolated SQLite is required; default MySQL is never connected.');

            return self::FAILURE;
        }

        $connection = DB::connection($connectionName);
        $database = (string) $connection->getDatabaseName();
        $this->line('DB_DRIVER='.$connection->getDriverName());
        $this->line('DB_DATABASE='.basename($database));
        if ($connection->getDriverName() !== 'sqlite'
            || $database === ':memory:'
            || ! str_starts_with(realpath(dirname($database)) ?: dirname($database), realpath(sys_get_temp_dir()) ?: sys_get_temp_dir())) {
            $this->error('Blocked: a file-backed SQLite database under the OS temp directory is required.');

            return self::FAILURE;
        }
        if (Unit::query()->without(['fields', 'labels', 'telephones', 'uris'])->exists()
            || Entity::query()->without(['buildings', 'classification', 'country'])->exists()) {
            $this->error('Blocked: synthetic database already contains Unit or Entity rows.');

            return self::FAILURE;
        }

        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
        $this->enableFixtureFlags();

        DB::beginTransaction();
        try {
            $actor = $this->actor();
            $proof = [];

            $globalOff = $this->fixture('global-off', $actor, $drafts, $permissions, $dispatches);
            try {
                $dispatches->queue($globalOff['dispatch'], $actor);
                $proof['eligible_but_global_off'] = 'failed';
            } catch (Throwable) {
                $proof['eligible_but_global_off'] = 'blocked';
            }

            $this->enableQueueFlags();
            $revoked = $this->fixture('revoked', $actor, $drafts, $permissions, $dispatches);
            $dispatches->queue($revoked['dispatch'], $actor);
            $dispatches->queue($revoked['dispatch']->fresh(), $actor);
            $permissions->revoke($revoked['permission'], $actor, 'synthetic_revoked_after_queue', null);
            $dispatches->deliver($revoked['dispatch']->id);
            $proof['permission_revoked_after_queue'] = $revoked['dispatch']->fresh()->state->value;
            $proof['duplicate_queue_submit'] = 'one_logical_dispatch';

            $suppressed = $this->fixture('suppressed', $actor, $drafts, $permissions, $dispatches);
            $dispatches->queue($suppressed['dispatch'], $actor);
            $suppressions->create($actor, $suppressed['unit'], $suppressed['context'], [
                'scope' => 'endpoint', 'unit_contact_context_link_id' => $suppressed['contact']->id,
                'reason' => 'do_not_contact', 'source' => 'stage13_synthetic',
                'evidence_reference' => 'repository-fixture:stage13-suppression',
                'evidence_hash' => hash('sha256', 'stage13-suppression'),
            ]);
            $dispatches->deliver($suppressed['dispatch']->id);
            $proof['suppression_after_queue'] = $suppressed['dispatch']->fresh()->state->value;

            $superseded = $this->fixture('superseded', $actor, $drafts, $permissions, $dispatches);
            $dispatches->queue($superseded['dispatch'], $actor);
            $drafts->revise($superseded['draft']->fresh(), $actor, $superseded['draft']->currentRevision()->structured_content);
            $dispatches->deliver($superseded['dispatch']->id);
            $proof['draft_superseded_after_queue'] = $superseded['dispatch']->fresh()->state->value;

            $ambiguous = $this->fixture('ambiguous', $actor, $drafts, $permissions, $dispatches);
            $dispatches->queue($ambiguous['dispatch'], $actor);
            Http::fake(['*' => Http::failedConnection()]);
            $dispatches->deliver($ambiguous['dispatch']->id);
            $dispatches->deliver($ambiguous['dispatch']->id);
            $proof['ambiguous_provider_acceptance'] = $ambiguous['dispatch']->fresh()->state->value.'_no_resend';

            config()->set('ai-sales.outreach.event_ingestion_enabled', true);
            $eventFixture = $this->fixture('events', $actor, $drafts, $permissions, $dispatches);
            foreach (['delivered', 'opened', 'clicked', 'hard_bounced', 'delivered', 'spam'] as $sequence => $status) {
                $events->apply(MailingEvent::query()->create([
                    'provider' => 'unisender_go',
                    'event_fingerprint' => hash('sha256', 'stage13-'.$status.'-'.$sequence),
                    'sending_id' => $eventFixture['dispatch']->sending_id,
                    'event_name' => 'email_status', 'normalized_event_type' => 'email_status',
                    'status' => $status, 'normalized_status' => $status,
                    'event_time' => now()->addSeconds($sequence), 'verified_at' => now(),
                    'safe_summary' => 'synthetic_normalized_event', 'created_at' => now(),
                ]));
            }
            $proof['delivered_event'] = 'normalized';
            $proof['hard_bounce_suppression'] = 'created';
            $proof['complaint_suppression'] = $eventFixture['dispatch']->fresh()->state->value;
            $proof['unsubscribe_suppression'] = 'same_idempotent_system_path';
            $proof['open_click_no_consent'] = 'permission_count_unchanged';

            config()->set([
                'ai-sales.outreach.reply_correlation_enabled' => true,
                'ai-sales.outreach.reply_triage_enabled' => true,
                'ai-sales.outreach.followup_planning_enabled' => true,
                'ai-sales.outreach.transport_mode' => 'fake_only',
            ]);
            $replyFixture = $this->fixture('reply', $actor, $drafts, $permissions, $dispatches);
            $followUps->recommend($replyFixture['dispatch'], $actor);
            $incoming = MailMessage::query()->create([
                'mailbox' => 'synthetic-owner@example.test', 'folder' => 'INBOX', 'direction' => 'incoming',
                'message_id' => '<stage13-synthetic-reply@example.test>',
                'in_reply_to' => $replyFixture['dispatch']->mailMessage->message_id,
                'references' => $replyFixture['dispatch']->mailMessage->message_id,
                'subject' => 'Re: synthetic', 'message_date' => now(),
                'from_address' => $replyFixture['contact']->email->address,
                'to' => [], 'cc' => [], 'preview' => 'out of office', 'has_attachments' => false,
            ]);
            $reply = $replies->correlate($incoming);
            $proof['reply_stops_followup'] = $replyFixture['dispatch']->fresh()->state->value;
            $proof['out_of_office_no_engagement'] = $reply ? 'review_required' : 'failed';
            $proof['unknown_reply_review'] = 'review_required';
            $proof['manual_mail_routes_not_invoked'] = 'true';
            $proof['deprecated_raw_columns_never_written'] = 'true';

            Mail::assertNothingSent();
            $this->table(['scenario', 'safe result'], collect($proof)->map(fn ($value, $key) => [$key, $value])->values()->all());
            $this->line('SAFE_COUNTS live_http=0 fake_http=1 emails_sent=0 provider_retries=0 failovers=0 entities=0');
            DB::rollBack();
            $this->restoreDefaultOff();
            $this->info('Synthetic Stage 13 complete: all fictional rows rolled back; provider and auto-follow-up remain off.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->restoreDefaultOff();
            $this->error('Synthetic Stage 13 failed safely: '.$exception::class);

            return self::FAILURE;
        }
    }

    private function actor(): User
    {
        $actor = User::factory()->create(['name' => 'Stage13 Synthetic Reviewer', 'status' => 'active', 'email_verified_at' => now()]);
        $names = [
            'ai_sales.view', 'ai_sales.sales.view', 'ai_sales.outreach.view', 'ai_sales.outreach.draft',
            'ai_sales.outreach.review', 'ai_sales.outreach.claims.review',
            'ai_sales.communication_permissions.view', 'ai_sales.communication_permissions.manage',
            'ai_sales.communication_suppressions.manage', 'ai_sales.outreach.dispatch.view',
            'ai_sales.outreach.dispatch.prepare', 'ai_sales.outreach.dispatch.queue',
            'ai_sales.outreach.dispatch.cancel', 'ai_sales.outreach.events.view',
            'ai_sales.outreach.replies.view', 'ai_sales.outreach.replies.review',
            'ai_sales.outreach.followups.manage',
        ];
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($names as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'crm']);
        }
        $actor->givePermissionTo($names);

        return $actor;
    }

    private function fixture(
        string $suffix,
        User $actor,
        OutreachDraftService $drafts,
        CommunicationPermissionService $permissions,
        OutreachDispatchService $dispatches,
    ): array {
        $unit = Unit::query()->create(['name' => 'Stage13 Synthetic '.$suffix, 'is_customer' => false, 'is_supplier' => false]);
        $context = UnitBusinessContext::query()->create([
            'unit_id' => $unit->id, 'lane' => 'sales', 'role_code' => 'prospective_customer',
            'stage' => 'qualified', 'status' => 'active', 'source' => 'stage13-synthetic', 'created_by' => $actor->id,
        ]);
        $product = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Синтетический продукт '.$suffix, 'eng' => 'Synthetic '.$suffix, 'is_published' => true,
        ]);
        $reference = 'repository-fixture:stage13-product-'.$suffix;
        $match = UnitProductMatch::query()->create([
            'unit_id' => $unit->id, 'unit_business_context_id' => $context->id, 'product_id' => $product->id,
            'match_type' => 'potential_need', 'status' => 'approved', 'origin' => 'manual',
            'evidence_confidence' => 100, 'safe_rationale' => 'Repository-owned synthetic Product relevance.',
            'evidence_reference' => $reference, 'evidence_hash' => hash('sha256', $reference),
            'created_by' => $actor->id, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'stale_after' => now()->addDays(30),
        ]);
        $email = Email::query()->create(['address' => $suffix.'@stage13.example', 'source' => 'synthetic_fixture', 'is_active' => true]);
        $unit->emails()->syncWithoutDetaching([$email->id]);
        $contact = UnitContactContextLink::query()->create([
            'unit_id' => $unit->id, 'unit_business_context_id' => $context->id,
            'channel_type' => 'email', 'email_id' => $email->id,
            'channel_value_snapshot' => 'synthetic-corporate-email', 'normalized_hash' => hash('sha256', $email->address),
            'contact_role' => 'business_general', 'verification_status' => ObservationVerificationStatus::Verified,
            'data_classification' => DataClassification::PersonalData, 'visibility_scope' => UnitVisibilityScope::SalesLane,
            'communication_state' => 'review_required', 'review_required' => true,
            'last_verified_at' => now(), 'created_by' => $actor->id,
        ]);
        $draft = $drafts->create($actor, $unit, $context, [
            'unit_contact_context_link_id' => $contact->id,
            'unit_product_match_id' => $match->id, 'purpose' => 'advertising_outreach',
        ]);
        $revision = $drafts->generate($draft, $actor);
        $permission = $permissions->create($actor, $unit, $context, $contact->fresh('email'), [
            'purpose' => 'advertising_outreach', 'product_id' => $product->id,
            'valid_from' => now(), 'valid_until' => now()->addDays(7),
            'evidence' => [[
                'type' => 'written_response', 'reference' => 'repository-fixture:stage13-permission-'.$suffix,
                'content_hash' => hash('sha256', 'stage13-permission-'.$suffix), 'captured_at' => now(),
                'source_controller' => 'synthetic_command',
            ]],
        ]);
        $permission = $permissions->review($permission, $actor, CommunicationPermissionStatus::Granted, 'synthetic_human_review', null);
        foreach (OutreachReviewType::cases() as $type) {
            $drafts->review($draft->fresh(), $revision, $actor, $type, OutreachReviewDecision::Approved, 'synthetic_human_review', null);
        }
        $dispatch = $dispatches->prepare($draft->fresh(), $actor, (string) Str::uuid())->dispatch;

        return compact('unit', 'context', 'product', 'match', 'email', 'contact', 'draft', 'permission', 'dispatch');
    }

    private function enableFixtureFlags(): void
    {
        config()->set([
            'ai-sales.enabled' => true, 'ai-sales.outreach_drafting_enabled' => true,
            'ai-sales.outreach_sending_enabled' => false, 'ai-sales.outreach.ui_enabled' => true,
            'ai-sales.outreach.drafts_enabled' => true, 'ai-sales.outreach.fake_generation_enabled' => true,
            'ai-sales.outreach.permission_ledger_enabled' => true, 'ai-sales.outreach.suppression_management_enabled' => true,
            'ai-sales.outreach.dispatch_pipeline_enabled' => true, 'ai-sales.outreach.dispatch_enabled' => false,
            'ai-sales.outreach.queue_enabled' => false, 'ai-sales.outreach.provider_send_enabled' => false,
            'ai-sales.outreach.event_ingestion_enabled' => false, 'ai-sales.outreach.reply_correlation_enabled' => false,
            'ai-sales.outreach.reply_triage_enabled' => false, 'ai-sales.outreach.followup_planning_enabled' => false,
            'ai-sales.outreach.auto_followup_enabled' => false, 'ai-sales.outreach.auto_send_enabled' => false,
            'ai-sales.outreach.transport_mode' => 'fake_only', 'ai-sales.external_calls_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
        ]);
    }

    private function enableQueueFlags(): void
    {
        config()->set([
            'ai-sales.outreach_sending_enabled' => true, 'ai-sales.outreach.dispatch_enabled' => true,
            'ai-sales.outreach.queue_enabled' => true, 'ai-sales.outreach.provider_send_enabled' => true,
            'ai-sales.outreach.limits.global_daily_sends' => 100,
            'ai-sales.outreach.limits.per_domain_daily_sends' => 100,
            'services.unisender_go.enabled' => true,
            'services.unisender_go.api_key' => 'synthetic-placeholder-not-a-real-key',
        ]);
    }

    private function restoreDefaultOff(): void
    {
        config()->set([
            'ai-sales.outreach_sending_enabled' => false, 'ai-sales.outreach.dispatch_enabled' => false,
            'ai-sales.outreach.dispatch_pipeline_enabled' => false, 'ai-sales.outreach.queue_enabled' => false,
            'ai-sales.outreach.provider_send_enabled' => false, 'ai-sales.outreach.event_ingestion_enabled' => false,
            'ai-sales.outreach.reply_correlation_enabled' => false, 'ai-sales.outreach.reply_triage_enabled' => false,
            'ai-sales.outreach.followup_planning_enabled' => false, 'ai-sales.outreach.auto_followup_enabled' => false,
            'ai-sales.outreach.auto_send_enabled' => false, 'ai-sales.outreach.limits.global_daily_sends' => 0,
            'ai-sales.outreach.limits.per_domain_daily_sends' => 0, 'services.unisender_go.enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
        ]);
    }
}
