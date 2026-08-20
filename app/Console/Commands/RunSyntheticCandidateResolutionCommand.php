<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Domain\AiSales\Services\ProspectingCandidateService;
use App\Domain\AiSales\Services\ProspectingSearchJobService;
use App\Domain\AiSales\Services\ProspectingSearchQueryService;
use App\Domain\AiSales\Services\ResolveProspectingCandidate;
use App\Domain\AiSales\Services\UnitBusinessContextService;
use App\Models\Entity;
use App\Models\Good;
use App\Models\Product;
use App\Models\ProspectingCandidate;
use App\Models\Unit;
use App\Models\UnitContactContextLink;
use App\Models\Uri;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Throwable;

class RunSyntheticCandidateResolutionCommand extends Command
{
    protected $signature = 'ai-sales:run-synthetic-candidate-resolution
        {--apply : Retain fictional fixtures in the isolated SQLite DB; default rolls back}
        {--yes : Confirm --apply}';

    protected $description = 'Run repository-owned deterministic Stage 08 candidate scenarios without AI, provider, or HTTP';

    public function handle(
        ProspectingSearchJobService $jobs,
        ProspectingSearchQueryService $queries,
        ProspectingCandidateService $candidates,
        ResolveProspectingCandidate $resolution,
        UnitBusinessContextService $contexts,
    ): int {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $database = (string) $connection->getDatabaseName();
        $this->line('APP_ENV='.app()->environment());
        $this->line('DB_DRIVER='.$driver);
        $this->line('DB_DATABASE='.($database === ':memory:' ? ':memory:' : basename($database)));
        if (! app()->environment(['local', 'testing']) || $driver !== 'sqlite') {
            $this->error('Blocked: command requires local/testing with isolated SQLite; default MySQL is never accepted.');

            return self::FAILURE;
        }
        if ((bool) $this->option('apply') && ! (bool) $this->option('yes')) {
            $this->error('Blocked: --apply requires --yes.');

            return self::FAILURE;
        }
        if (Unit::query()->without(['fields', 'labels', 'telephones', 'uris'])->exists() || Entity::query()->without(['buildings', 'classification', 'country'])->exists() || ProspectingCandidate::query()->exists()) {
            $this->error('Blocked: isolated fixture DB must not contain pre-existing Unit, Entity, or Candidate rows.');

            return self::FAILURE;
        }

        Http::preventStrayRequests();
        config()->set([
            'ai-sales.prospecting.dossier_enabled' => true,
            'ai-sales.prospecting.jobs_enabled' => true,
            'ai-sales.prospecting.candidate_import_enabled' => true,
            'ai-sales.prospecting.auto_create_unit' => false,
            'ai-sales.prospecting.live_search_enabled' => false,
            'ai-sales.enabled' => false,
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
        ]);
        $beforeEntities = Entity::query()->without(['buildings', 'classification', 'country'])->count();
        DB::beginTransaction();
        try {
            $actor = User::factory()->create(['name' => 'Stage08 Synthetic Reviewer', 'status' => 'active']);
            $permissions = [
                'ai_sales.view', 'ai_sales.sales.view', 'ai_sales.unit_roles.manage', 'ai_sales.contexts.manage',
                'ai_sales.prospecting.view', 'ai_sales.prospecting.jobs.manage', 'ai_sales.prospecting.review',
                'ai_sales.prospecting.resolve', 'ai_sales.good_matches.review', 'ai_sales.timeline.view',
                'ai_sales.product_matches.review',
            ];
            foreach ($permissions as $permission) {
                Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'crm']);
            }
            $actor->givePermissionTo($permissions);
            $product = Product::query()->without(['category', 'manufacturers'])->create([
                'rus' => 'Синтетический пищевой ингредиент',
                'eng' => 'Synthetic food ingredient',
                'is_published' => true,
            ]);
            $good = Good::query()->create([
                'name' => 'Synthetic stage08 offer',
                'is_published' => true,
            ]);
            $good->products()->attach($product->id);
            $job = $jobs->createDraft([
                'purpose' => 'buyer_discovery',
                'safe_objective' => 'Repository-owned fictional buyer resolution fixture.',
                'primary_product_id' => $product->id,
                'originating_good_ids' => [$good->id],
                'criteria' => ['segments' => ['synthetic-only']],
            ], $actor);
            $jobs->submit($job, $actor);
            $job = $jobs->approve($job->fresh(), $actor);
            $query = $queries->recordFixture($job, [
                'safe_display_query' => 'fictional food buyers synthetic fixture',
                'language' => 'en',
                'geography' => 'Synthetic Region',
                'industry_intent' => 'repository-test-only',
            ], true);

            [$existingUnit, $existingContext] = $this->syntheticUnit($contexts, $actor, 'Synthetic Existing Foods');
            $this->verifiedUri($existingUnit, $existingContext, 'https://synthetic-existing.example');
            $existing = $candidates->createFixture($job, $this->fixture('Synthetic Existing Foods', 'https://synthetic-existing.example', true), $actor, true, $query);
            $existingReplay = $candidates->createFixture($job, $this->fixture('Synthetic Existing Foods', 'https://synthetic-existing.example', true), $actor, true, $query);
            $existingDecision = $resolution->evaluate($existing, $actor);
            $resolution->enrichExisting($existing->fresh(), $existingUnit, $actor);

            $countryId = DB::table('countries')->insertGetId(['name' => 'Synthetic Country', 'сodeISO' => 'SX']);
            $regionId = DB::table('regions')->insertGetId(['name' => 'Synthetic Region', 'country_id' => $countryId]);
            $cityId = DB::table('cities')->insertGetId(['name' => 'Synthetic City', 'region_id' => $regionId]);
            [$ambiguousA] = $this->syntheticUnit($contexts, $actor, 'Synthetic Ambiguous');
            [$ambiguousB] = $this->syntheticUnit($contexts, $actor, 'Synthetic Ambiguous');
            $ambiguousA->cities()->attach($cityId);
            $ambiguousB->cities()->attach($cityId);
            $ambiguous = $candidates->createFixture($job, [
                ...$this->fixture('Synthetic Ambiguous', null, true),
                'city_id' => $cityId,
                'location_display' => 'Synthetic City',
            ], $actor, true, $query);
            $ambiguousDecision = $resolution->evaluate($ambiguous, $actor);

            $new = $candidates->createFixture($job, $this->fixture('Synthetic New Unit', 'https://synthetic-new.example', true), $actor, true, $query);
            $newDecision = $resolution->evaluate($new, $actor);
            $newUnit = $resolution->createNewUnit($new->fresh(), $actor, $new->working_name);

            $invalid = $candidates->createFixture($job, $this->fixture('Synthetic Invalid', null, false), $actor, true, $query);
            $invalidDecision = $resolution->evaluate($invalid, $actor);
            Http::assertNothingSent();
            if (Entity::query()->without(['buildings', 'classification', 'country'])->count() !== $beforeEntities) {
                throw new \RuntimeException('Entity boundary violated.');
            }
            $this->table(['scenario', 'outcome', 'safe proof'], [
                ['existing_by_domain', $existingDecision->outcome->value, $existing->id === $existingReplay->id ? 'idempotent' : 'failed'],
                ['ambiguous_by_name_and_city', $ambiguousDecision->outcome->value, count($ambiguousDecision->matchedUnitIds).' suggestions'],
                ['new_unit_review', $newDecision->outcome->value, 'Unit #'.$newUnit->id.'; Entity +0'],
                ['invalid_no_source', $invalidDecision->outcome->value, 'blocked'],
            ]);
            if ((bool) $this->option('apply')) {
                DB::commit();
                $this->warn('Synthetic fixtures retained only in the isolated SQLite DB.');
            } else {
                DB::rollBack();
                $this->info('Dry-run complete: all fictional rows rolled back. HTTP requests: 0; Entity mutations: 0.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->error('Synthetic probe failed safely: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function syntheticUnit(UnitBusinessContextService $contexts, User $actor, string $name): array
    {
        $unit = Unit::query()->create(['name' => $name, 'is_customer' => false, 'is_supplier' => false]);
        $context = $contexts->upsert($unit, [
            'lane' => 'sales', 'role_code' => 'prospective_customer', 'stage' => 'researching',
            'status' => 'active', 'source' => 'stage08-synthetic',
        ], $actor);

        return [$unit, $context];
    }

    private function verifiedUri(Unit $unit, $context, string $url): void
    {
        $uri = Uri::query()->firstOrCreate(['address' => $url]);
        $unit->uris()->syncWithoutDetaching([$uri->id]);
        UnitContactContextLink::query()->create([
            'unit_id' => $unit->id,
            'unit_business_context_id' => $context->id,
            'channel_type' => 'uri',
            'uri_id' => $uri->id,
            'channel_value_snapshot' => parse_url($url, PHP_URL_HOST),
            'normalized_hash' => hash('sha256', 'uri|'.$url),
            'contact_role' => 'business_general',
            'verification_status' => ObservationVerificationStatus::Verified,
            'data_classification' => DataClassification::Public,
            'visibility_scope' => UnitVisibilityScope::SalesLane,
            'communication_state' => 'review_required',
            'review_required' => true,
            'last_verified_at' => now(),
        ]);
    }

    private function fixture(string $name, ?string $url, bool $withSource): array
    {
        return [
            'working_name' => $name,
            'website' => $url,
            'public_activity_summary' => 'Fictional public activity for deterministic repository test.',
            'relevance_summary' => 'Fictional relevance for the synthetic Good only.',
            'confidence_components' => ['relevance' => 85, 'identity' => 80],
            'sources' => $withSource ? [[
                'type' => 'synthetic_fixture',
                'reference' => 'repository-fixture:stage08-v1',
                'url' => $url,
                'title' => 'Synthetic evidence',
                'excerpt' => 'Repository-owned fictional evidence; never fetched.',
            ]] : [],
        ];
    }
}
