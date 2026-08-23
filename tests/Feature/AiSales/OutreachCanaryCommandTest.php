<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Contracts\GitRepositoryStateInspectorInterface;
use App\Domain\AiSales\Outreach\Canary\OutreachCanaryContract;
use App\Domain\AiSales\Outreach\Canary\OutreachCanaryRepositoryGuard;
use App\Domain\AiSales\Probes\GitRepositoryState;
use App\Models\AuthorizedMailDispatchAttempt;
use App\Models\CommunicationPermission;
use App\Models\CommunicationSuppression;
use App\Models\Email;
use App\Models\Entity;
use App\Models\Good;
use App\Models\OutreachDispatchDecision;
use App\Models\OutreachDraft;
use App\Models\OutreachDraftClaim;
use App\Models\OutreachDraftRevision;
use App\Models\ProspectingCandidate;
use App\Models\Sending;
use App\Models\Unit;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\AiSales\FakeGitRepositoryStateInspector;
use Tests\TestCase;

final class OutreachCanaryCommandTest extends TestCase
{
    private ?string $databasePath = null;

    /** @var array<string, mixed> */
    private array $originalDatabaseConfig = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->originalDatabaseConfig = [
            'default' => config('database.default'),
            'sqlite_database' => config('database.connections.sqlite.database'),
        ];
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        config()->set([
            'database.default' => $this->originalDatabaseConfig['default'],
            'database.connections.sqlite.database' => $this->originalDatabaseConfig['sqlite_database'],
        ]);
        if ($this->databasePath !== null) {
            foreach ([$this->databasePath, $this->databasePath.'-wal', $this->databasePath.'-shm'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        parent::tearDown();
    }

    public function test_dry_run_uses_actual_safe_dto_local_pipeline_and_no_http_or_mail(): void
    {
        $this->migrateIsolatedDatabase();
        $repository = $this->bindExpectedRepositoryState();
        [$localKey, $externalKey] = $this->configureDefaultOffTimeweb();

        $exitCode = Artisan::call('ai-sales:run-live-synthetic-outreach-draft', [
            '--dry-run' => true,
            '--retain-db' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('clean_committed_stage12b', $output);
        $this->assertStringContainsString('outreach_food_factory_broccoli_ru_v1', $output);
        $this->assertStringContainsString('openai/gpt-5.6-luna', $output);
        $this->assertStringContainsString('outreach_email_ru_b2b.v1', $output);
        $this->assertStringContainsString('generated_valid', $output);
        $this->assertStringContainsString('"provider_called": false', $output);
        $this->assertStringContainsString('"timeweb_requests": 0', $output);
        $this->assertStringContainsString('"dispatch_eligible": false', $output);
        $this->assertStringContainsString('"mail_sent": 0', $output);
        $this->assertStringNotContainsString($localKey, $output);
        $this->assertStringNotContainsString($externalKey, $output);
        $recipientMarker = (string) Email::query()->value('address');
        $this->assertNotSame('', $recipientMarker);
        $this->assertStringEndsWith('.invalid', $recipientMarker);
        $this->assertStringNotContainsString($recipientMarker, $output);

        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
        $this->assertSame(1, $repository->inspectCalls);
        $this->assertSyntheticPersistenceCounts();
        $this->assertDefaultOffAfterCommand();
        $this->assertArrayNotHasKey('mysql', DB::getConnections());
    }

    public function test_fake_live_path_sends_one_exact_responses_request_without_recipient_or_dispatch(): void
    {
        $this->migrateIsolatedDatabase();
        $this->bindExpectedRepositoryState();
        [$localKey, $externalKey] = $this->configureDefaultOffTimeweb();
        $providerPayload = $this->validProviderPayload();

        Http::fake(function (Request $request) use ($providerPayload) {
            return Http::response([
                'id' => 'resp_stage12b_safe',
                'object' => 'response',
                'status' => 'completed',
                'model' => OutreachCanaryContract::MODEL_ID,
                'output' => [[
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode($providerPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    ]],
                ]],
                'usage' => [
                    'input_tokens' => 420,
                    'output_tokens' => 180,
                    'output_tokens_details' => ['reasoning_tokens' => 0],
                ],
            ], 200, [
                'Content-Type' => 'application/json',
                'X-Request-ID' => 'stage12b-safe-live-request',
            ]);
        });

        $exitCode = Artisan::call('ai-sales:run-live-synthetic-outreach-draft', [
            '--live' => true,
            '--yes' => true,
            '--retain-db' => true,
        ]);
        $output = Artisan::output();
        $recipientMarker = (string) Email::query()->value('address');

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('"provider_called": true', $output);
        $this->assertStringContainsString('"timeweb_requests": 1', $output);
        $this->assertStringContainsString('"yandex_requests": 0', $output);
        $this->assertStringContainsString('"other_live_http": 0', $output);
        $this->assertStringContainsString('"store": false', $output);
        $this->assertStringContainsString('"native_tools": false', $output);
        $this->assertStringContainsString('"previous_response_id": false', $output);
        $this->assertStringContainsString('"dispatch_eligible": false', $output);
        $this->assertStringContainsString('"manual_mail_service_calls": 0', $output);
        $this->assertStringContainsString('"unisender_calls": 0', $output);
        $this->assertStringNotContainsString($localKey, $output);
        $this->assertStringNotContainsString($externalKey, $output);
        $this->assertStringNotContainsString($recipientMarker, $output);
        $this->assertStringNotContainsString(json_encode($providerPayload, JSON_UNESCAPED_UNICODE), $output);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($externalKey, $recipientMarker): bool {
            $body = json_encode($request->data(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            return $request->method() === 'POST'
                && $request->url() === 'https://api.timeweb.ai/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer '.$externalKey)
                && data_get($request->data(), 'model') === OutreachCanaryContract::MODEL_ID
                && data_get($request->data(), 'store') === false
                && data_get($request->data(), 'text.format.strict') === true
                && data_get($request->data(), 'text.format.name') === OutreachCanaryContract::WIRE_SCHEMA_NAME
                && ! array_key_exists('tools', $request->data())
                && ! array_key_exists('previous_response_id', $request->data())
                && ! str_contains($body, $recipientMarker)
                && ! str_contains($body, $externalKey);
        });
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
        $this->assertSyntheticPersistenceCounts();
        $this->assertDefaultOffAfterCommand();
        $this->assertArrayNotHasKey('mysql', DB::getConnections());
    }

    public function test_live_provider_error_is_redacted_and_does_not_retry_or_persist_revision(): void
    {
        $this->migrateIsolatedDatabase();
        $this->bindExpectedRepositoryState();
        [, $externalKey] = $this->configureDefaultOffTimeweb();
        $unsafeBody = 'provider-error-'.Str::random(48);
        Http::fake(fn () => Http::response($unsafeBody, 422, ['Content-Type' => 'text/plain']));

        $exitCode = Artisan::call('ai-sales:run-live-synthetic-outreach-draft', [
            '--live' => true,
            '--yes' => true,
            '--retain-db' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode, $output);
        $this->assertStringContainsString('stage12b_timeweb_request_unsupported', $output);
        $this->assertStringContainsString('"secret_printed": false', $output);
        $this->assertStringNotContainsString($unsafeBody, $output);
        $this->assertStringNotContainsString($externalKey, $output);
        $this->assertStringContainsString('"timeweb_requests": 1', $output);
        $this->assertStringContainsString('"retries": 0', $output);
        $this->assertStringContainsString('"failovers": 0', $output);
        Http::assertSentCount(1);
        $this->assertSame(0, OutreachDraftRevision::query()->count());
        $this->assertSame(0, Sending::query()->count());
        $this->assertSame(0, AuthorizedMailDispatchAttempt::query()->count());
        $this->assertDefaultOffAfterCommand();
    }

    public function test_missing_external_key_blocks_before_evidence_fixture_and_http(): void
    {
        $this->migrateIsolatedDatabase();
        $this->bindExpectedRepositoryState();
        $this->configureDefaultOffTimeweb();
        config()->set('ai-sales.providers.timeweb.routes.external_sanitized.api_key', '');

        $exitCode = Artisan::call('ai-sales:run-live-synthetic-outreach-draft', [
            '--dry-run' => true,
            '--retain-db' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode, $output);
        $this->assertStringContainsString('stage12b_external_key_missing', $output);
        $this->assertStringContainsString('"timeweb_requests": 0', $output);
        Http::assertNothingSent();
        $this->assertSame(0, Unit::query()->count());
        $this->assertSame(0, OutreachDraft::query()->count());
        $this->assertArrayNotHasKey('mysql', DB::getConnections());
        $this->assertDefaultOffAfterCommand();
    }

    private function migrateIsolatedDatabase(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pischeprom-stage12b-test-');
        $this->assertNotFalse($path);
        $this->databasePath = $path;
        chmod($path, 0600);
        config()->set([
            'database.default' => 'sqlite',
            'database.connections.sqlite.driver' => 'sqlite',
            'database.connections.sqlite.database' => $path,
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');

        $status = Artisan::call('migrate', [
            '--database' => 'sqlite',
            '--force' => true,
            '--no-interaction' => true,
        ]);
        $this->assertSame(0, $status, 'Full migrations must apply only to isolated Stage 12B SQLite.');
        $this->assertArrayNotHasKey('mysql', DB::getConnections());
    }

    private function bindExpectedRepositoryState(): FakeGitRepositoryStateInspector
    {
        $hash = str_repeat('d', 40);
        $fake = new FakeGitRepositoryStateInspector(new GitRepositoryState(
            branch: OutreachCanaryRepositoryGuard::EXPECTED_BRANCH,
            head: $hash,
            baseIsAncestor: true,
            commitsAfterBase: [[
                'hash' => $hash,
                'subject' => OutreachCanaryRepositoryGuard::STAGE_12B_SUBJECT,
            ]],
            stagedChanges: 0,
            modifiedChanges: 0,
            untrackedChanges: 0,
        ));
        app()->instance(GitRepositoryStateInspectorInterface::class, $fake);

        return $fake;
    }

    /** @return array{string, string} */
    private function configureDefaultOffTimeweb(): array
    {
        $localKey = 'stage12b-local-'.Str::random(48);
        $externalKey = 'stage12b-external-'.Str::random(48);
        config()->set([
            'app.key' => 'base64:stage12b-test-app-key',
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
            'ai-sales.limits.max_retries' => 0,
            'ai-sales.outreach.ui_enabled' => false,
            'ai-sales.outreach.drafts_enabled' => false,
            'ai-sales.outreach.fake_generation_enabled' => false,
            'ai-sales.outreach.permission_ledger_enabled' => false,
            'ai-sales.outreach.suppression_management_enabled' => false,
            'ai-sales.outreach.dispatch_enabled' => false,
            'ai-sales.outreach.live_generation_enabled' => false,
            'ai-sales.outreach.live_synthetic_canary_enabled' => false,
            'ai-sales.outreach.auto_send_enabled' => false,
            'ai-sales.outreach.transport_mode' => 'fake_only',
            'ai-sales.providers.timeweb.enabled' => false,
            'ai-sales.providers.timeweb.base_url' => 'https://api.timeweb.ai/v1',
            'ai-sales.providers.timeweb.connect_timeout_seconds' => 3,
            'ai-sales.providers.timeweb.timeout_seconds' => 10,
            'ai-sales.providers.timeweb.max_response_bytes' => 1_048_576,
            'ai-sales.providers.timeweb.routes.local_ru.enabled' => false,
            'ai-sales.providers.timeweb.routes.local_ru.api_key' => $localKey,
            'ai-sales.providers.timeweb.routes.local_ru.model_ids' => ['timeweb/gpt-oss-120b'],
            'ai-sales.providers.timeweb.routes.external_sanitized.enabled' => false,
            'ai-sales.providers.timeweb.routes.external_sanitized.api_key' => $externalKey,
            'ai-sales.providers.timeweb.routes.external_sanitized.models' => [
                'luna' => '',
                'terra' => 'openai/gpt-5.6-terra',
                'sol' => 'openai/gpt-5.6-sol',
            ],
            'ai-sales.providers.timeweb.probe.enabled' => false,
            'ai-sales.providers.timeweb.probe.synthetic_only' => true,
            'ai-sales.providers.timeweb.probe.max_rub' => '100.0000',
            'ai-sales.providers.timeweb.probe.max_input_tokens' => 30_000,
            'ai-sales.providers.timeweb.probe.max_output_tokens' => 8_000,
            'ai-sales.providers.timeweb.probe.max_requests' => 15,
            'ai-sales.providers.timeweb.probe.max_wall_clock_seconds' => 120,
            'ai-sales.prospecting.live_search_enabled' => false,
            'ai-sales.prospecting.live_probe_enabled' => false,
            'ai-sales.prospecting.search_execution_enabled' => false,
            'ai-sales.prospecting.existing_yandex_provider_enabled' => false,
            'ai-sales.prospecting.page_fetch_enabled' => false,
            'ai-sales.prospecting.public_research_enabled' => false,
            'ai-sales.find_buyers.live_execution_enabled' => false,
        ]);

        return [$localKey, $externalKey];
    }

    /** @return array<string, mixed> */
    private function validProviderPayload(): array
    {
        return [
            'subject' => 'Брокколи для производства овощных смесей',
            'salutation_style' => 'neutral_business',
            'opening' => 'Предлагаем рассмотреть продукт для выпуска замороженных овощных смесей.',
            'relevance_statement' => 'Брокколи соответствует заявленному направлению синтетического производства.',
            'offer_items' => ['Продукт может быть рассмотрен технологом в рамках плановой оценки сырья.'],
            'call_to_action' => 'Если направление актуально, предлагаем обсудить требования к продукту.',
            'closing' => 'С уважением, команда поставщика.',
            'claims' => [[
                'type' => 'product_relevance',
                'text' => 'Брокколи релевантна производству замороженных овощных смесей.',
                'evidence_key' => 'product_relevance',
            ]],
        ];
    }

    private function assertSyntheticPersistenceCounts(): void
    {
        $this->assertSame(1, Unit::query()->count());
        $this->assertSame(0, Entity::query()->without(['buildings', 'classification', 'country'])->count());
        $this->assertSame(0, Good::query()->count());
        $this->assertSame(1, OutreachDraft::query()->count());
        $this->assertSame(1, OutreachDraftRevision::query()->count());
        $this->assertSame(1, OutreachDraftClaim::query()->count());
        $this->assertSame(1, OutreachDispatchDecision::query()->count());
        $this->assertSame(0, CommunicationPermission::query()->count());
        $this->assertSame(0, CommunicationSuppression::query()->count());
        $this->assertSame(0, Sending::query()->count());
        $this->assertSame(0, AuthorizedMailDispatchAttempt::query()->count());
        $this->assertSame(0, ProspectingCandidate::query()->count());
    }

    private function assertDefaultOffAfterCommand(): void
    {
        $this->assertFalse((bool) config('ai-sales.enabled'));
        $this->assertFalse((bool) config('ai-sales.external_calls_enabled'));
        $this->assertFalse((bool) config('ai-sales.external_sanitized_calls_enabled'));
        $this->assertFalse((bool) config('ai-sales.outreach_drafting_enabled'));
        $this->assertFalse((bool) config('ai-sales.outreach.live_generation_enabled'));
        $this->assertFalse((bool) config('ai-sales.outreach.live_synthetic_canary_enabled'));
        $this->assertFalse((bool) config('ai-sales.outreach.dispatch_enabled'));
        $this->assertFalse((bool) config('ai-sales.outreach_sending_enabled'));
        $this->assertFalse((bool) config('ai-sales.providers.timeweb.enabled'));
        $this->assertFalse((bool) config('ai-sales.providers.timeweb.routes.external_sanitized.enabled'));
        $this->assertFalse((bool) config('ai-sales.provider_failover_enabled'));
        $this->assertSame(0, (int) config('ai-sales.limits.max_retries'));
        $this->assertSame('fake_only', config('ai-sales.transport_mode'));
        $this->assertSame('fake_only', config('ai-sales.outreach.transport_mode'));
    }
}
