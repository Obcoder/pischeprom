<?php

namespace Tests\Feature\AiSales;

use App\Console\Commands\SendOwnerControlledOutreachCanaryCommand;
use App\Domain\AiSales\Contracts\GitRepositoryStateInspectorInterface;
use App\Domain\AiSales\Outreach\Enums\CommunicationPermissionStatus;
use App\Domain\AiSales\Outreach\OwnerCanary\OwnerControlledCanaryContract;
use App\Domain\AiSales\Outreach\OwnerCanary\OwnerControlledCanaryRepositoryGuard;
use App\Domain\AiSales\Probes\GitRepositoryState;
use App\Models\CommunicationPermission;
use App\Models\Entity;
use App\Models\Good;
use App\Models\MailMessage;
use App\Models\OutreachDispatch;
use App\Models\OutreachDraft;
use App\Models\OutreachFollowUpPlan;
use App\Models\Product;
use App\Models\Sending;
use App\Models\Unit;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\AiSales\FakeGitRepositoryStateInspector;
use Tests\TestCase;

final class OwnerControlledOutreachCanaryCommandTest extends TestCase
{
    private ?string $databasePath = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateIsolatedDatabase();
        $this->configureDefaultOff('testing');
        $this->bindExpectedRepositoryState();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        $this->app->detectEnvironment(fn (): string => 'testing');
        DB::disconnect('sqlite');
        DB::purge('sqlite');
        if ($this->databasePath !== null) {
            foreach ([$this->databasePath, $this->databasePath.'-wal', $this->databasePath.'-shm'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        parent::tearDown();
    }

    public function test_default_dry_run_uses_real_services_rolls_back_and_never_calls_http_or_mail(): void
    {
        $recipient = (string) config('ai-sales.outreach.owner_canary.recipient');
        $providerKey = (string) config('services.unisender_go.api_key');

        $exitCode = Artisan::call('ai-sales:send-owner-controlled-outreach-canary');
        $output = Artisan::output();
        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('clean_committed_stage13b', $output);
        $this->assertStringContainsString(OwnerControlledCanaryContract::SCENARIO, $output);
        $this->assertStringContainsString('"provider_called": false', $output);
        $this->assertStringContainsString('"rolled_back": true', $output);
        $this->assertStringNotContainsString($recipient, $output);
        $this->assertStringNotContainsString($providerKey, $output);

        Http::assertNothingSent();
        Mail::assertNothingSent();
        $this->assertDatabaseCount('units', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('emails', 0);
        $this->assertDatabaseCount('outreach_drafts', 0);
        $this->assertDatabaseCount('outreach_dispatches', 0);
        $this->assertDatabaseCount('communication_permissions', 0);
        $this->assertArrayNotHasKey('mysql', DB::getConnections());
        $this->assertDefaultOff();
    }

    public function test_fake_live_command_uses_one_recipient_one_request_and_blocks_every_second_attempt(): void
    {
        $this->app->detectEnvironment(fn (): string => 'staging');
        config()->set('ai-sales.outreach.owner_canary.environment', 'staging');
        $recipient = (string) config('ai-sales.outreach.owner_canary.recipient');
        $providerKey = (string) config('services.unisender_go.api_key');
        Http::fake([
            'https://go1.unisender.ru/*' => Http::response(
                ['job_id' => 'safe-stage13b-provider-job'],
                200,
                ['X-Request-ID' => 'safe-stage13b-request-id'],
            ),
        ]);

        [$exitCode, $output] = $this->runLiveCommand([SendOwnerControlledOutreachCanaryCommand::OWNER_PHRASE]);
        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('"provider_send_requests": 1', $output);
        $this->assertStringContainsString('"emails_addressed": 1', $output);
        $this->assertStringContainsString('"dispatch_state": "provider_accepted"', $output);
        $this->assertStringContainsString('"request_profile": "outreach_zero_retry"', $output);
        $this->assertStringContainsString('"permission_status_after": "revoked"', $output);
        $this->assertStringNotContainsString($recipient, $output);
        $this->assertStringNotContainsString($providerKey, $output);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($recipient, $providerKey): bool {
            $message = $request->data()['message'] ?? [];

            return $request->method() === 'POST'
                && str_starts_with($request->url(), 'https://go1.unisender.ru/en/transactional/api/v1/email/send.json')
                && $request->hasHeader('X-API-KEY', $providerKey)
                && count($message['recipients'] ?? []) === 1
                && data_get($message, 'recipients.0.email') === $recipient
                && ! array_key_exists('cc', $message)
                && ! array_key_exists('bcc', $message)
                && ! array_key_exists('attachments', $message)
                && data_get($message, 'track_read') === 0
                && data_get($message, 'track_links') === 0;
        });
        Mail::assertNothingSent();

        $this->assertSame(1, Unit::query()->where('name', OwnerControlledCanaryContract::UNIT_NAME)->count());
        $this->assertSame(1, Product::query()->without(['category', 'manufacturers'])->where('rus', OwnerControlledCanaryContract::PRODUCT_NAME)->count());
        $this->assertSame(0, Entity::query()->without(['buildings', 'classification', 'country'])->count());
        $this->assertSame(0, Good::query()->count());
        $this->assertSame(1, OutreachDraft::query()->count());
        $this->assertSame(1, OutreachDispatch::query()->count());
        $this->assertSame(1, Sending::query()->count());
        $this->assertSame(1, MailMessage::query()->count());
        $this->assertSame(0, OutreachFollowUpPlan::query()->count());
        $this->assertSame(CommunicationPermissionStatus::Revoked, CommunicationPermission::query()->sole()->status);

        $permission = CommunicationPermission::query()->sole();
        $this->assertLessThanOrEqual(24, $permission->created_at->diffInHours($permission->valid_until));
        $dispatch = OutreachDispatch::query()->with(['sending', 'decisions'])->sole();
        $this->assertSame('outreach_zero_retry', $dispatch->request_profile);
        $this->assertEqualsCanonicalizing(
            ['prepare', 'queue', 'worker'],
            $dispatch->decisions->pluck('checkpoint')->all(),
        );
        $this->assertNull($dispatch->sending->request_payload);
        $this->assertNull($dispatch->sending->response_payload);
        $this->assertNull($dispatch->sending->failed_emails);
        $this->assertNull($dispatch->sending->error_message);
        $this->assertDefaultOff();
        $this->assertArrayNotHasKey('mysql', DB::getConnections());

        [$secondExitCode, $secondOutput] = $this->runLiveCommand([SendOwnerControlledOutreachCanaryCommand::OWNER_PHRASE]);
        $this->assertSame(1, $secondExitCode, $secondOutput);
        $this->assertStringContainsString('stage13b_previous_canary_exists', $secondOutput);

        Http::assertSentCount(1);
        $this->assertSame(1, OutreachDispatch::query()->count());
        $this->assertSame(1, Sending::query()->count());
        $this->assertDefaultOff();
    }

    public function test_provider_ambiguity_stops_without_resend_and_revokes_permission(): void
    {
        $this->app->detectEnvironment(fn (): string => 'staging');
        config()->set('ai-sales.outreach.owner_canary.environment', 'staging');
        Http::fake(['*' => Http::failedConnection('synthetic connection ambiguity')]);

        [$exitCode, $output] = $this->runLiveCommand([SendOwnerControlledOutreachCanaryCommand::OWNER_PHRASE]);
        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('"provider_send_requests": 1', $output);
        $this->assertStringContainsString('"dispatch_state": "ambiguous_acceptance"', $output);
        $this->assertStringContainsString('"permission_status_after": "revoked"', $output);

        Http::assertSentCount(1);
        $dispatch = OutreachDispatch::query()->with('sending')->sole();
        $this->assertSame('ambiguous_acceptance', $dispatch->state->value);
        $this->assertSame('ambiguous_acceptance', $dispatch->sending->safe_error_code);
        $this->assertSame('operator_review_required_no_resend', $dispatch->sending->safe_summary);
        $this->assertSame(CommunicationPermissionStatus::Revoked, CommunicationPermission::query()->sole()->status);
        $this->assertDefaultOff();
    }

    private function migrateIsolatedDatabase(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pischeprom-stage13b-test-');
        $this->assertNotFalse($path);
        $this->databasePath = $path;
        chmod($path, 0600);
        config()->set([
            'database.default' => 'sqlite',
            'database.connections.sqlite.driver' => 'sqlite',
            'database.connections.sqlite.database' => $path,
            'cache.default' => 'array',
            'queue.default' => 'database',
        ]);
        DB::purge('sqlite');

        $status = Artisan::call('migrate', [
            '--database' => 'sqlite',
            '--force' => true,
            '--no-interaction' => true,
        ]);
        $this->assertSame(0, $status, 'Full migrations must apply only to isolated Stage 13B SQLite.');
        $this->assertArrayNotHasKey('mysql', DB::getConnections());
    }

    private function bindExpectedRepositoryState(): void
    {
        $hash = str_repeat('c', 40);
        app()->instance(
            GitRepositoryStateInspectorInterface::class,
            new FakeGitRepositoryStateInspector(new GitRepositoryState(
                branch: OwnerControlledCanaryRepositoryGuard::EXPECTED_BRANCH,
                head: $hash,
                baseIsAncestor: true,
                commitsAfterBase: [[
                    'hash' => $hash,
                    'subject' => OwnerControlledCanaryRepositoryGuard::STAGE_13B_SUBJECT,
                ]],
                stagedChanges: 0,
                modifiedChanges: 0,
                untrackedChanges: 0,
            )),
        );
    }

    private function configureDefaultOff(string $environment): void
    {
        config()->set([
            'app.key' => 'base64:stage13b-test-hmac-key',
            'app.url' => 'https://stage13b.test',
            'ai-sales.enabled' => false,
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.local_ru_calls_enabled' => false,
            'ai-sales.external_sanitized_calls_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
            'ai-sales.web_search_enabled' => false,
            'ai-sales.outreach_drafting_enabled' => false,
            'ai-sales.outreach_sending_enabled' => false,
            'ai-sales.autonomous_campaigns_enabled' => false,
            'ai-sales.provider_native_tools_enabled' => false,
            'ai-sales.live_business_workflows_enabled' => false,
            'ai-sales.transport_mode' => 'fake_only',
            'ai-sales.outreach.ui_enabled' => false,
            'ai-sales.outreach.drafts_enabled' => false,
            'ai-sales.outreach.fake_generation_enabled' => false,
            'ai-sales.outreach.permission_ledger_enabled' => false,
            'ai-sales.outreach.suppression_management_enabled' => false,
            'ai-sales.outreach.dispatch_enabled' => false,
            'ai-sales.outreach.dispatch_pipeline_enabled' => false,
            'ai-sales.outreach.queue_enabled' => false,
            'ai-sales.outreach.provider_send_enabled' => false,
            'ai-sales.outreach.event_ingestion_enabled' => false,
            'ai-sales.outreach.reply_correlation_enabled' => false,
            'ai-sales.outreach.reply_triage_enabled' => false,
            'ai-sales.outreach.followup_planning_enabled' => false,
            'ai-sales.outreach.auto_followup_enabled' => false,
            'ai-sales.outreach.live_generation_enabled' => false,
            'ai-sales.outreach.live_synthetic_canary_enabled' => false,
            'ai-sales.outreach.auto_send_enabled' => false,
            'ai-sales.outreach.transport_mode' => 'fake_only',
            'ai-sales.outreach.limits.global_daily_sends' => 0,
            'ai-sales.outreach.limits.per_domain_daily_sends' => 0,
            'ai-sales.outreach.limits.max_follow_ups' => 0,
            'ai-sales.outreach.limits.provider_retries' => 0,
            'ai-sales.outreach.limits.provider_failover' => false,
            'ai-sales.outreach.owner_canary.environment' => $environment,
            'ai-sales.outreach.owner_canary.recipient' => 'owner-canary-secret@example.test',
            'ai-sales.outreach.owner_canary.permission_evidence_reference' => 'owner-evidence:stage13b:permission-v1',
            'ai-sales.outreach.owner_canary.permission_evidence_sha256' => str_repeat('a', 64),
            'ai-sales.outreach.owner_canary.security_evidence_reference' => 'owner-evidence:stage13b:hardened-smoke-v1',
            'ai-sales.outreach.owner_canary.security_evidence_sha256' => str_repeat('b', 64),
            'ai-sales.outreach.owner_canary.security_verified_at' => now()->toIso8601String(),
            'services.unisender_go.enabled' => true,
            'services.unisender_go.api_base' => 'https://go1.unisender.ru/en/transactional/api/v1',
            'services.unisender_go.api_key' => 'stage13b-provider-secret-value',
            'services.unisender_go.from_email' => 'canary-sender@example.test',
            'services.unisender_go.from_name' => 'Stage 13B Sender',
            'services.unisender_go.reply_to' => 'canary-reply@example.test',
            'services.unisender_go.track_read' => true,
            'services.unisender_go.track_links' => true,
            'services.unisender_go.webhook_url' => 'https://stage13b.test/webhooks/unisender-go',
            'services.unisender_go.webhook_queue_connection' => 'database',
            'services.unisender_go.webhook_queue' => 'mailing-webhooks',
        ]);
    }

    private function assertDefaultOff(): void
    {
        $this->assertFalse((bool) config('ai-sales.enabled'));
        $this->assertFalse((bool) config('ai-sales.outreach.dispatch_enabled'));
        $this->assertFalse((bool) config('ai-sales.outreach.queue_enabled'));
        $this->assertFalse((bool) config('ai-sales.outreach.provider_send_enabled'));
        $this->assertFalse((bool) config('ai-sales.outreach.event_ingestion_enabled'));
        $this->assertFalse((bool) config('ai-sales.outreach.auto_send_enabled'));
        $this->assertFalse((bool) config('ai-sales.outreach.auto_followup_enabled'));
        $this->assertSame('fake_only', config('ai-sales.transport_mode'));
        $this->assertSame(0, (int) config('ai-sales.outreach.limits.provider_retries'));
        $this->assertFalse((bool) config('ai-sales.outreach.limits.provider_failover'));
        $this->assertSame(0, (int) config('ai-sales.outreach.limits.max_follow_ups'));
    }

    /**
     * @param  list<string>  $inputs
     * @return array{int, string}
     */
    private function runLiveCommand(array $inputs): array
    {
        $tester = new CommandTester(Artisan::all()['ai-sales:send-owner-controlled-outreach-canary']);
        $tester->setInputs($inputs);
        $exitCode = $tester->execute([
            '--live' => true,
            '--yes' => true,
            '--observe-seconds' => '0',
        ]);

        return [$exitCode, $tester->getDisplay()];
    }
}
