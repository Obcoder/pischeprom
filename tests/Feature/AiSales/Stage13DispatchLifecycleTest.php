<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Outreach\CommunicationPermissionService;
use App\Domain\AiSales\Outreach\OutreachDispatchService;
use App\Jobs\AiSales\SendOutreachDispatchJob;
use App\Models\AiControlSetting;
use App\Models\OutreachDispatch;
use App\Services\CommercialOffers\UnisenderRequestProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

class Stage13DispatchLifecycleTest extends Stage13TestCase
{
    public function test_prepare_is_atomic_idempotent_and_maps_exact_revision_to_existing_mail_records(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [$actor, $unit, , , , $email, , $draft] = $fixture;
        $key = (string) Str::uuid();
        $url = "/api/ai-sales/units/{$unit->id}/outreach/drafts/{$draft->id}/dispatches";

        $first = $this->actingAs($actor)->postJson($url, ['idempotency_key' => $key])->assertCreated()->json('data');
        $second = $this->postJson($url, ['idempotency_key' => (string) Str::uuid()])->assertCreated()->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertDatabaseCount('outreach_dispatches', 1);
        $this->assertDatabaseCount('mail_messages', 1);
        $this->assertDatabaseCount('sendings', 1);
        $dispatch = OutreachDispatch::query()->with('mailMessage', 'sending')->firstOrFail();
        $revision = $draft->currentRevision();
        $this->assertSame($revision->subject, $dispatch->mailMessage->subject);
        $this->assertStringContainsString('Отписаться', $dispatch->mailMessage->text);
        $this->assertSame([], $dispatch->mailMessage->to);
        $this->assertTrue($dispatch->mailMessage->emails()->whereKey($email->id)->exists());
        $this->assertSame($email->id, $dispatch->sending->email_id);
        $this->assertNull($dispatch->sending->html);
        $this->assertNull($dispatch->sending->text);
        $this->assertSame(UnisenderRequestProfile::OutreachZeroRetry->value, $dispatch->request_profile);
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_default_off_queue_is_server_blocked_and_no_controller_calls_provider(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [$actor, $unit] = $fixture;
        $dispatch = $this->prepareDispatch($fixture);

        $this->actingAs($actor)->postJson(
            "/api/ai-sales/units/{$unit->id}/outreach/dispatches/{$dispatch->id}/queue",
        )->assertUnprocessable()->assertJsonPath('code', 'outreach_feature_disabled');

        Queue::assertNothingPushed();
        Http::assertNothingSent();
        Mail::assertNothingSent();
        $this->assertSame('ready', $dispatch->fresh()->state->value);
    }

    public function test_prepare_replay_revalidates_permission_and_never_creates_a_second_outbox(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [$actor, $unit, , , , , , $draft] = $fixture;
        $dispatch = $this->prepareDispatch($fixture);
        app(CommunicationPermissionService::class)->revoke(
            $dispatch->permission,
            $actor,
            'revoked_before_prepare_replay',
            null,
        );

        $this->actingAs($actor)->postJson(
            "/api/ai-sales/units/{$unit->id}/outreach/drafts/{$draft->id}/dispatches",
            ['idempotency_key' => (string) Str::uuid()],
        )->assertConflict();

        $this->assertSame('blocked', $dispatch->fresh()->state->value);
        $this->assertDatabaseCount('outreach_dispatches', 1);
        $this->assertDatabaseCount('mail_messages', 1);
        $this->assertDatabaseCount('sendings', 1);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_queue_is_idempotent_and_worker_uses_zero_retry_profile(): void
    {
        $this->allowExpectedHttpRequests = true;
        $fixture = $this->approvedOutreachFixture();
        [$actor, $unit] = $fixture;
        $dispatch = $this->prepareDispatch($fixture);
        $this->enableProviderQueue();
        Http::fake([
            'https://go1.unisender.ru/*' => Http::response(['job_id' => 'safe-job-stage13'], 200, ['X-Request-ID' => 'safe-request-stage13']),
        ]);

        $url = "/api/ai-sales/units/{$unit->id}/outreach/dispatches/{$dispatch->id}/queue";
        $this->actingAs($actor)->postJson($url)->assertAccepted();
        $this->postJson($url)->assertAccepted();
        Queue::assertPushed(SendOutreachDispatchJob::class, 1);

        app(OutreachDispatchService::class)->deliver($dispatch->id);

        $dispatch->refresh();
        $this->assertSame('provider_accepted', $dispatch->state->value);
        $this->assertNotNull($dispatch->queued_at);
        $this->assertSame('safe-job-stage13', $dispatch->provider_job_id);
        $this->assertSame('outreach_zero_retry', $dispatch->sending->request_profile);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://go1.unisender.ru/en/transactional/api/v1/email/send.json')
            && data_get($request->data(), 'message.recipients.0.metadata.sending_id') === (string) $dispatch->sending_id
            && data_get($request->data(), 'message.recipients.0.metadata.mail_message_id') === (string) $dispatch->mail_message_id);
        Mail::assertNothingSent();
    }

    public function test_permission_revoked_after_queue_blocks_worker_before_http(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [$actor, $unit] = $fixture;
        $dispatch = $this->prepareDispatch($fixture);
        $this->enableProviderQueue();
        $this->actingAs($actor)->postJson(
            "/api/ai-sales/units/{$unit->id}/outreach/dispatches/{$dispatch->id}/queue",
        )->assertAccepted();
        app(CommunicationPermissionService::class)->revoke(
            $dispatch->permission,
            $actor,
            'revoked_after_queue',
            null,
        );

        app(OutreachDispatchService::class)->deliver($dispatch->id);

        $this->assertSame('blocked', $dispatch->fresh()->state->value);
        $this->assertSame('permission_scope_changed', $dispatch->fresh()->last_block_reason);
        $this->assertContains('scoped_permission_missing', $dispatch->decisions()->latest('id')->firstOrFail()->block_reasons);
        Http::assertNothingSent();
    }

    public function test_definitive_recipient_rejection_is_safe_failed_without_resend(): void
    {
        $this->allowExpectedHttpRequests = true;
        $fixture = $this->approvedOutreachFixture();
        [$actor, $unit, , , , $email] = $fixture;
        $dispatch = $this->prepareDispatch($fixture);
        $this->enableProviderQueue();
        Http::fake(['https://go1.unisender.ru/*' => Http::response([
            'job_id' => 'safe-rejected-job',
            'failed_emails' => [['email' => $email->address, 'message' => 'RAW PROVIDER DETAIL']],
        ], 200)]);
        $this->actingAs($actor)->postJson(
            "/api/ai-sales/units/{$unit->id}/outreach/dispatches/{$dispatch->id}/queue",
        )->assertAccepted();

        $service = app(OutreachDispatchService::class);
        $service->deliver($dispatch->id);
        $service->deliver($dispatch->id);

        $dispatch->refresh();
        $this->assertSame('failed', $dispatch->state->value);
        $this->assertSame('permission_denied', $dispatch->sending->safe_error_code);
        $this->assertSame('provider_recipient_rejected_safe', $dispatch->sending->safe_summary);
        $this->assertNull($dispatch->sending->error);
        Http::assertSentCount(1);
    }

    public function test_global_kill_switch_blocks_worker_before_http(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [$actor, $unit] = $fixture;
        $dispatch = $this->prepareDispatch($fixture);
        $this->enableProviderQueue();
        $this->actingAs($actor)->postJson(
            "/api/ai-sales/units/{$unit->id}/outreach/dispatches/{$dispatch->id}/queue",
        )->assertAccepted();
        AiControlSetting::query()->where('key', 'kill_switch.global')->update(['boolean_value' => true]);

        app(OutreachDispatchService::class)->deliver($dispatch->id);

        $this->assertSame('blocked', $dispatch->fresh()->state->value);
        Http::assertNothingSent();
    }

    public function test_superseded_revision_after_queue_blocks_worker(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [$actor, $unit, , , , , , $draft] = $fixture;
        $dispatch = $this->prepareDispatch($fixture);
        $this->enableProviderQueue();
        $this->actingAs($actor)->postJson(
            "/api/ai-sales/units/{$unit->id}/outreach/dispatches/{$dispatch->id}/queue",
        )->assertAccepted();
        $content = $draft->currentRevision()->structured_content;
        app(\App\Domain\AiSales\Outreach\OutreachDraftService::class)->revise($draft->fresh(), $actor, $content);

        app(OutreachDispatchService::class)->deliver($dispatch->id);

        $this->assertSame('blocked', $dispatch->fresh()->state->value);
        Http::assertNothingSent();
    }

    public function test_queue_job_payload_contains_only_dispatch_id_and_has_one_try(): void
    {
        $job = new SendOutreachDispatchJob(123);
        $serialized = serialize($job);

        $this->assertSame(123, $job->outreachDispatchId);
        $this->assertSame(1, $job->tries);
        $this->assertStringNotContainsString('recipient', $serialized);
        $this->assertStringNotContainsString('body', $serialized);
    }
}
