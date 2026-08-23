<?php

namespace Tests\Feature\AiSales;

use App\Console\Commands\SendOwnerControlledOutreachCanaryCommand;
use App\Domain\AiSales\Contracts\GitRepositoryStateInspectorInterface;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Outreach\OwnerCanary\OwnerControlledCanaryEnvironmentGuard;
use App\Domain\AiSales\Outreach\OwnerCanary\OwnerControlledCanaryRepositoryGuard;
use App\Domain\AiSales\Probes\GitRepositoryState;
use App\Infrastructure\AiSales\Probes\RealGitRepositoryStateInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AiSales\FakeGitRepositoryStateInspector;
use Tests\TestCase;

final class OwnerControlledOutreachCanaryGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->configureDefaultOff('testing');
    }

    /** @param array<string, mixed> $overrides */
    #[DataProvider('invalidRepositoryStates')]
    public function test_real_repository_guard_fails_closed_for_every_invalid_state(array $overrides, string $safeCode): void
    {
        $fake = new FakeGitRepositoryStateInspector($this->repositoryState($overrides));

        try {
            (new OwnerControlledCanaryRepositoryGuard($fake))->assertExpectedWorktree();
            $this->fail('Invalid repository state must be blocked.');
        } catch (PolicyViolation $exception) {
            $this->assertSame($safeCode, $exception->errorCode);
        }

        $this->assertSame(1, $fake->inspectCalls);
        Http::assertNothingSent();
    }

    public function test_clean_expected_committed_state_passes_and_runtime_binding_is_never_test_bypassed(): void
    {
        $fake = new FakeGitRepositoryStateInspector($this->repositoryState());
        $this->assertSame(
            'clean_committed_stage13b',
            (new OwnerControlledCanaryRepositoryGuard($fake))->assertExpectedWorktree(),
        );
        $this->assertInstanceOf(RealGitRepositoryStateInspector::class, app(GitRepositoryStateInspectorInterface::class));

        foreach ([
            app_path('Domain/AiSales/Outreach/OwnerCanary/OwnerControlledCanaryRepositoryGuard.php'),
            app_path('Infrastructure/AiSales/Probes/RealGitRepositoryStateInspector.php'),
        ] as $runtimeFile) {
            $source = file_get_contents($runtimeFile);
            $this->assertIsString($source);
            foreach (['runningUnitTests', 'APP_ENV', 'PHPUnit', 'class_exists', 'FakeGitRepositoryStateInspector'] as $blocked) {
                $this->assertStringNotContainsString($blocked, $source);
            }
        }
    }

    public function test_command_has_only_fixed_control_options_and_no_recipient_content_or_transport_overrides(): void
    {
        $definition = Artisan::all()['ai-sales:send-owner-controlled-outreach-canary']->getDefinition();
        $this->assertSame([], $definition->getArguments());
        foreach (['dry-run', 'live', 'yes', 'observe-seconds'] as $allowed) {
            $this->assertTrue($definition->hasOption($allowed));
        }
        foreach ([
            'recipient', 'email', 'name', 'unit', 'entity', 'product', 'good', 'subject', 'body',
            'url', 'provider', 'profile', 'retries', 'follow-up', 'skip-git-guard',
        ] as $blocked) {
            $this->assertFalse($definition->hasOption($blocked));
        }
    }

    public function test_default_mysql_is_blocked_before_a_connection_and_sqlite_has_the_hardened_schema(): void
    {
        $guard = app(OwnerControlledCanaryEnvironmentGuard::class);
        DB::purge('mysql');
        config()->set('database.default', 'mysql');

        $this->assertPolicyCode(
            fn () => $guard->assertDatabaseAndDeployment(false),
            'stage13b_isolated_sqlite_required',
        );
        $this->assertArrayNotHasKey('mysql', DB::getConnections());

        config()->set('database.default', 'sqlite');
        $state = $guard->assertDatabaseAndDeployment(false);
        $this->assertSame('sqlite', $state['driver']);
        $this->assertSame(OwnerControlledCanaryEnvironmentGuard::SECURITY_MIGRATION, $state['migration']);
    }

    public function test_live_gate_requires_fresh_security_evidence_exact_callback_and_async_queue(): void
    {
        $guard = app(OwnerControlledCanaryEnvironmentGuard::class);
        $this->app->detectEnvironment(fn (): string => 'staging');
        config()->set('ai-sales.outreach.owner_canary.environment', 'staging');
        config()->set('ai-sales.outreach.owner_canary.security_evidence_reference', null);

        $this->assertPolicyCode(fn () => $guard->configuration(true), 'stage13b_security_evidence_missing');

        config()->set([
            'ai-sales.outreach.owner_canary.security_evidence_reference' => 'owner-evidence:stage13b:hardened-smoke-v1',
            'ai-sales.outreach.owner_canary.security_evidence_sha256' => str_repeat('b', 64),
            'ai-sales.outreach.owner_canary.security_verified_at' => now()->subHours(25)->toIso8601String(),
        ]);
        $this->assertPolicyCode(fn () => $guard->configuration(true), 'stage13b_security_evidence_stale');

        config()->set([
            'ai-sales.outreach.owner_canary.security_verified_at' => now()->toIso8601String(),
            'services.unisender_go.webhook_queue_connection' => 'sync',
        ]);
        $this->assertPolicyCode(fn () => $guard->configuration(true), 'stage13b_webhook_queue_not_async');

        config()->set([
            'services.unisender_go.webhook_queue_connection' => 'database',
            'services.unisender_go.webhook_url' => 'https://another-stage13b.test/webhooks/unisender-go',
        ]);
        $this->assertPolicyCode(fn () => $guard->configuration(true), 'stage13b_callback_environment_mismatch');
        $this->app->detectEnvironment(fn (): string => 'testing');
        Http::assertNothingSent();
    }

    public function test_production_live_mode_requires_both_exact_non_echoed_owner_phrases_before_database_access(): void
    {
        $this->bindExpectedRepositoryState();
        $this->app->detectEnvironment(fn (): string => 'production');
        config()->set('ai-sales.outreach.owner_canary.environment', 'production');

        $this->artisan('ai-sales:send-owner-controlled-outreach-canary', [
            '--live' => true,
            '--yes' => true,
        ])
            ->expectsQuestion('Enter the exact Stage 13B owner authorization phrase', SendOwnerControlledOutreachCanaryCommand::OWNER_PHRASE)
            ->expectsQuestion('Enter the exact additional production authorization phrase', 'not-authorized')
            ->expectsOutputToContain('stage13b_production_authorization_missing')
            ->doesntExpectOutput(SendOwnerControlledOutreachCanaryCommand::OWNER_PHRASE)
            ->assertExitCode(1);

        $source = file_get_contents(app_path('Console/Commands/SendOwnerControlledOutreachCanaryCommand.php'));
        $this->assertIsString($source);
        $this->assertLessThan(
            strpos($source, 'assertDatabaseAndDeployment'),
            strpos($source, 'assertOwnerAuthorization'),
            'Owner authorization must occur before deployment database access.',
        );
        $this->app->detectEnvironment(fn (): string => 'testing');
        Http::assertNothingSent();
    }

    public function test_missing_recipient_and_provider_secret_never_appear_in_safe_failure_output(): void
    {
        $this->bindExpectedRepositoryState();
        $recipient = 'owner-canary-secret@example.test';
        $providerKey = 'stage13b-provider-secret-value';
        config()->set([
            'ai-sales.outreach.owner_canary.recipient' => '',
            'services.unisender_go.api_key' => $providerKey,
        ]);

        $this->artisan('ai-sales:send-owner-controlled-outreach-canary', ['--dry-run' => true])
            ->expectsOutputToContain('stage13b_recipient_missing_or_invalid')
            ->doesntExpectOutput($recipient)
            ->doesntExpectOutput($providerKey)
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidRepositoryStates(): iterable
    {
        yield 'wrong branch' => [['branch' => 'main'], 'stage13b_branch_mismatch'];
        yield 'base absent' => [['baseIsAncestor' => false, 'commitsAfterBase' => []], 'stage13b_stage13_not_ancestor'];
        yield 'staged' => [['stagedChanges' => 1], 'stage13b_staged_changes_blocked'];
        yield 'modified' => [['modifiedChanges' => 1], 'stage13b_modified_changes_blocked'];
        yield 'untracked' => [['untrackedChanges' => 1], 'stage13b_untracked_changes_blocked'];
        yield 'extra commit' => [['commitsAfterBase' => [
            ['hash' => str_repeat('a', 40), 'subject' => OwnerControlledCanaryRepositoryGuard::STAGE_13B_SUBJECT],
            ['hash' => str_repeat('b', 40), 'subject' => 'unexpected'],
        ]], 'stage13b_commit_count_invalid'];
        yield 'subject' => [['commitsAfterBase' => [[
            'hash' => str_repeat('a', 40), 'subject' => 'wrong subject',
        ]]], 'stage13b_commit_subject_invalid'];
        yield 'head' => [['head' => str_repeat('b', 40)], 'stage13b_head_not_canary_commit'];
    }

    /** @param array<string, mixed> $overrides */
    private function repositoryState(array $overrides = []): GitRepositoryState
    {
        $hash = str_repeat('a', 40);
        $state = [
            'branch' => OwnerControlledCanaryRepositoryGuard::EXPECTED_BRANCH,
            'head' => $hash,
            'baseIsAncestor' => true,
            'commitsAfterBase' => [[
                'hash' => $hash,
                'subject' => OwnerControlledCanaryRepositoryGuard::STAGE_13B_SUBJECT,
            ]],
            'stagedChanges' => 0,
            'modifiedChanges' => 0,
            'untrackedChanges' => 0,
            ...$overrides,
        ];

        return new GitRepositoryState(...$state);
    }

    private function bindExpectedRepositoryState(): void
    {
        app()->instance(
            GitRepositoryStateInspectorInterface::class,
            new FakeGitRepositoryStateInspector($this->repositoryState()),
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
            'ai-sales.outreach.limits.provider_retries' => 0,
            'ai-sales.outreach.limits.provider_failover' => false,
            'ai-sales.outreach.limits.max_follow_ups' => 0,
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
            'services.unisender_go.webhook_url' => 'https://stage13b.test/webhooks/unisender-go',
            'services.unisender_go.webhook_queue_connection' => 'database',
            'services.unisender_go.webhook_queue' => 'mailing-webhooks',
            'queue.default' => 'database',
        ]);
    }

    private function assertPolicyCode(callable $callback, string $safeCode): void
    {
        try {
            $callback();
            $this->fail('Expected policy violation was not raised.');
        } catch (PolicyViolation $exception) {
            $this->assertSame($safeCode, $exception->errorCode);
        }
    }
}
