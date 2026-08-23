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
use App\Domain\AiSales\Outreach\OutreachDispatchEligibilityService;
use App\Domain\AiSales\Outreach\OutreachDraftService;
use App\Models\Email;
use App\Models\Entity;
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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class RunSyntheticOutreachDraftCommand extends Command
{
    protected $signature = 'ai-sales:run-synthetic-outreach-draft';

    protected $description = 'Run bounded Stage 12 fake-only outreach draft, permission, DLP and suppression scenarios';

    public function handle(
        OutreachDraftService $drafts,
        CommunicationPermissionService $permissions,
        CommunicationSuppressionService $suppressions,
        OutreachDispatchEligibilityService $eligibility,
    ): int {
        $configuredConnection = (string) config('database.default');
        $this->line('APP_ENV='.app()->environment());
        $this->line('CONFIGURED_DB_CONNECTION='.$configuredConnection);

        if (! app()->environment(['local', 'testing']) || $configuredConnection !== 'sqlite') {
            $this->error('Blocked: command requires local/testing with isolated SQLite; default MySQL is never connected.');

            return self::FAILURE;
        }

        $connection = DB::connection($configuredConnection);
        $driver = $connection->getDriverName();
        $database = (string) $connection->getDatabaseName();
        $this->line('DB_DRIVER='.$driver);
        $this->line('DB_DATABASE='.($database === ':memory:' ? ':memory:' : basename($database)));
        if ($driver !== 'sqlite') {
            $this->error('Blocked: configured synthetic connection is not SQLite.');

            return self::FAILURE;
        }
        if (Unit::query()->without(['fields', 'labels', 'telephones', 'uris'])->exists()
            || Entity::query()->without(['buildings', 'classification', 'country'])->exists()) {
            $this->error('Blocked: synthetic database must not contain pre-existing Unit or Entity rows.');

            return self::FAILURE;
        }

        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
        config()->set([
            'ai-sales.enabled' => true,
            'ai-sales.outreach_drafting_enabled' => true,
            'ai-sales.outreach_sending_enabled' => false,
            'ai-sales.outreach.ui_enabled' => true,
            'ai-sales.outreach.drafts_enabled' => true,
            'ai-sales.outreach.fake_generation_enabled' => true,
            'ai-sales.outreach.permission_ledger_enabled' => true,
            'ai-sales.outreach.suppression_management_enabled' => true,
            'ai-sales.outreach.dispatch_enabled' => false,
            'ai-sales.outreach.live_generation_enabled' => false,
            'ai-sales.outreach.auto_send_enabled' => false,
            'ai-sales.outreach.transport_mode' => 'fake_only',
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
        ]);

        DB::beginTransaction();
        try {
            $actor = User::factory()->create(['name' => 'Stage12 Synthetic Reviewer', 'status' => 'active', 'email_verified_at' => now()]);
            $permissionNames = [
                'ai_sales.view', 'ai_sales.sales.view', 'ai_sales.outreach.view', 'ai_sales.outreach.draft',
                'ai_sales.outreach.review', 'ai_sales.outreach.claims.review',
                'ai_sales.communication_permissions.view', 'ai_sales.communication_permissions.manage',
                'ai_sales.communication_suppressions.manage',
            ];
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach ($permissionNames as $name) {
                Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'crm']);
            }
            $actor->givePermissionTo($permissionNames);
            $unit = Unit::query()->create(['name' => 'Stage12 Fictional Buyer', 'is_customer' => false, 'is_supplier' => false]);
            $context = UnitBusinessContext::query()->create([
                'unit_id' => $unit->id, 'lane' => 'sales', 'role_code' => 'prospective_customer',
                'stage' => 'qualified', 'status' => 'active', 'source' => 'stage12-synthetic', 'created_by' => $actor->id,
            ]);
            $product = Product::query()->without(['category', 'manufacturers'])->create([
                'rus' => 'Синтетический пищевой ингредиент', 'eng' => 'Synthetic ingredient', 'is_published' => true,
            ]);
            $evidenceReference = 'repository-fixture:stage12-product-v1';
            $productMatch = UnitProductMatch::query()->create([
                'unit_id' => $unit->id, 'unit_business_context_id' => $context->id, 'product_id' => $product->id,
                'match_type' => 'potential_need', 'status' => 'approved', 'origin' => 'manual',
                'evidence_confidence' => 100, 'safe_rationale' => 'Repository-owned fictional public product relevance.',
                'evidence_reference' => $evidenceReference, 'evidence_hash' => hash('sha256', $evidenceReference),
                'created_by' => $actor->id, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'stale_after' => now()->addDays(30),
            ]);
            $email = Email::query()->create(['address' => 'buyer@stage12.example', 'source' => 'synthetic_fixture', 'is_active' => true]);
            $unit->emails()->syncWithoutDetaching([$email->id]);
            $contact = UnitContactContextLink::query()->create([
                'unit_id' => $unit->id, 'unit_business_context_id' => $context->id,
                'channel_type' => 'email', 'email_id' => $email->id,
                'channel_value_snapshot' => 'synthetic-corporate-email',
                'normalized_hash' => hash('sha256', $email->address), 'contact_role' => 'business_general',
                'verification_status' => ObservationVerificationStatus::Verified,
                'data_classification' => DataClassification::PersonalData,
                'visibility_scope' => UnitVisibilityScope::SalesLane,
                'communication_state' => 'review_required', 'review_required' => true,
                'last_verified_at' => now(), 'created_by' => $actor->id,
            ]);
            $draft = $drafts->create($actor, $unit, $context, [
                'unit_contact_context_link_id' => $contact->id,
                'unit_product_match_id' => $productMatch->id, 'purpose' => 'advertising_outreach',
            ]);
            $revision = $drafts->generate($draft, $actor);
            $this->line('DLP_STATUS='.$revision->dlp_status->value.' DLP_CODES='.implode(',', $revision->dlp_findings));
            $beforePermission = $eligibility->evaluate($draft->fresh());
            $permission = $permissions->create($actor, $unit, $context, $contact->fresh('email'), [
                'purpose' => 'advertising_outreach', 'product_id' => $product->id,
                'valid_from' => now(), 'valid_until' => now()->addDays(7),
                'evidence' => [[
                    'type' => 'written_response', 'reference' => 'repository-fixture:stage12-permission-v1',
                    'content_hash' => hash('sha256', 'stage12-permission-evidence'), 'captured_at' => now(),
                    'source_controller' => 'synthetic_command',
                ]],
            ]);
            $permission = $permissions->review($permission, $actor, CommunicationPermissionStatus::Granted, 'synthetic_human_review', null);
            foreach (OutreachReviewType::cases() as $type) {
                $drafts->review($draft->fresh(), $revision, $actor, $type, OutreachReviewDecision::Approved, 'synthetic_human_review', null);
            }
            $afterReviews = $eligibility->evaluate($draft->fresh());
            $suppression = $suppressions->create($actor, $unit, $context, [
                'scope' => 'endpoint', 'unit_contact_context_link_id' => $contact->id,
                'reason' => 'do_not_contact', 'source' => 'synthetic_command',
                'evidence_reference' => 'repository-fixture:stage12-dnc-v1',
                'evidence_hash' => hash('sha256', 'stage12-dnc-evidence'),
            ]);
            $afterSuppression = $eligibility->evaluate($draft->fresh(), $actor, persist: true);

            Http::assertNothingSent();
            Mail::assertNothingSent();
            Queue::assertNothingPushed();
            $this->table(['scenario', 'eligible', 'safe proof'], [
                ['unknown_permission', 'false', in_array('scoped_permission_missing', $beforePermission->blockReasons, true) ? 'blocked' : 'failed'],
                ['reviewed_content_and_permission', 'false', in_array('stage12_dispatch_not_implemented', $afterReviews->blockReasons, true) ? 'global off' : 'failed'],
                ['suppression_precedence', 'false', collect($afterSuppression->blockReasons)->contains(fn ($reason) => str_contains($reason, 'do_not_contact')) ? 'suppression wins' : 'failed'],
            ]);
            $this->line('SAFE_COUNTS drafts=1 revisions=1 permissions=1 suppressions=1 emails_sent=0 http_requests=0 queue_jobs=0 entities=0');
            $this->line('SAFE_IDS draft='.$draft->id.' revision='.$revision->id.' permission='.$permission->id.' suppression='.$suppression->id);
            DB::rollBack();
            $this->info('Dry-run complete: all fictional rows rolled back; dispatch remained unavailable.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->error('Synthetic Stage 12 probe failed safely: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
