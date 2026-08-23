<?php

namespace App\Domain\AiSales\Probes;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Search\SearchProviderException;
use App\Domain\AiSales\Services\ApproveProspectingQueryPlan;
use App\Domain\AiSales\Services\ExecuteProspectingSearchQuery;
use App\Domain\AiSales\Services\IngestProspectingSearchCandidate;
use App\Domain\AiSales\Services\PlanProspectingQueries;
use App\Domain\AiSales\Services\ProspectingSearchJobService;
use App\Domain\AiSales\Web\SafePublicPageFetcher;
use App\Models\City;
use App\Models\Country;
use App\Models\Entity;
use App\Models\Product;
use App\Models\ProspectingCandidate;
use App\Models\ProspectingSearchQuery;
use App\Models\ProspectingSearchResult;
use App\Models\Region;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final class ExistingYandexDiscoveryProbe
{
    public const SCENARIO = 'buyer_broccoli_spb_v1';

    public const MAX_RESULTS = 10;

    public const MAX_FETCH_DOMAINS = 3;

    public const MAX_SUCCESSFUL_PAGES = 1;

    public const MAX_CANDIDATES = 1;

    public function __construct(
        private readonly ProspectingSearchJobService $jobs,
        private readonly PlanProspectingQueries $planner,
        private readonly ApproveProspectingQueryPlan $approver,
        private readonly ExecuteProspectingSearchQuery $executor,
        private readonly SafePublicPageFetcher $fetcher,
        private readonly IngestProspectingSearchCandidate $candidateIngestor,
    ) {}

    /** @return array<string, mixed> */
    public function run(bool $live, ExistingYandexProbeHttpGuard $httpGuard): array
    {
        $unitCountBefore = Unit::query()->count();
        $entityCountBefore = Entity::query()->count();
        if ($unitCountBefore !== 0 || $entityCountBefore !== 0) {
            throw new SearchProviderException('probe_policy', 'stage09b_non_synthetic_domain_rows_present');
        }

        [$actor, $product, $city] = $this->createSyntheticFixtures();
        $job = $this->jobs->createDraft([
            'purpose' => 'buyer_discovery',
            'safe_objective' => 'Repository-owned Stage 09B buyer discovery acceptance fixture.',
            'primary_product_id' => $product->id,
            'city_id' => $city->id,
            'locale' => 'ru-RU',
            'max_queries' => 1,
            'max_candidates' => 1,
            'max_results_per_query' => self::MAX_RESULTS,
            'criteria' => [],
        ], $actor);
        $this->jobs->submit($job, $actor);
        $job = $this->jobs->approve($job->fresh(), $actor);
        $queries = $this->planner->handle($job, $actor);
        $queries = $this->approver->handle($job, $actor);

        if ($queries->count() !== 1) {
            throw new SearchProviderException('probe_policy', 'stage09b_exactly_one_query_required');
        }

        /** @var ProspectingSearchQuery $query */
        $query = $queries->firstOrFail();
        $baseReport = [
            'scenario' => self::SCENARIO,
            'purpose' => 'buyer_discovery',
            'product_fixture_count' => 1,
            'primary_product_count' => 1,
            'originating_good_count' => 0,
            'geography' => 'Санкт-Петербург',
            'planned_query_count' => 1,
            'executed_query_count' => $live ? 1 : 0,
            'generated_query' => $query->safe_display_query,
            'template_code' => $query->template_code,
            'template_hash' => $query->template_hash,
            'product_scope_hash' => $query->product_scope_hash,
            'plan_hash' => $query->plan_hash,
            'normalized_results' => 0,
            'registrable_domains' => 0,
            'duplicate_results' => 0,
            'blocked_results' => 0,
            'result_set_hash' => null,
            'fetch_attempts' => [],
            'successful_pages' => 0,
            'evidence' => $this->emptyEvidence(),
            'candidate_count' => 0,
            'candidate_status' => null,
            'candidate_source_count' => 0,
            'candidate_protected_channel_count' => 0,
            'unit_changes' => 0,
            'entity_changes' => 0,
            'research_records' => 0,
            'email_sent' => false,
            'consent_created' => false,
            'provider_retries' => 0,
            'provider_failovers' => 0,
        ];

        if (! $live) {
            return ['status' => 'dry_run_ready', ...$baseReport];
        }

        $execution = $this->executor->handle($query, $actor);
        if ($execution->request_count > 1 || $execution->result_count > self::MAX_RESULTS) {
            throw new SearchProviderException('probe_budget', 'stage09b_search_budget_exceeded');
        }

        $results = $execution->results()->orderBy('rank')->get();
        $eligible = $results
            ->filter(fn (ProspectingSearchResult $result): bool => $result->duplicate_of_id === null)
            ->unique('registrable_domain')
            ->take(self::MAX_FETCH_DOMAINS)
            ->values();
        $hosts = $eligible->map(fn (ProspectingSearchResult $result): string => mb_strtolower(
            (string) parse_url($result->canonical_url, PHP_URL_HOST),
        ))->filter()->values()->all();
        $httpGuard->allowPublicHosts($hosts);

        $successfulFetch = null;
        $successfulResult = null;
        $fetchAttempts = [];
        foreach ($eligible as $index => $result) {
            if (! $httpGuard->canAttemptPage()) {
                $fetchAttempts[] = [
                    'attempt' => $index + 1,
                    'status' => 'budget_blocked',
                    'safe_code' => 'stage09b_remaining_http_budget_insufficient',
                ];
                break;
            }

            try {
                $fetch = $this->fetcher->fetch($result, $actor);
                $fetchAttempts[] = [
                    'attempt' => $index + 1,
                    'status' => 'fetched',
                    'safe_code' => null,
                ];
                $successfulFetch = $fetch;
                $successfulResult = $result;
                break;
            } catch (PolicyViolation $exception) {
                $fetchAttempts[] = [
                    'attempt' => $index + 1,
                    'status' => $this->fetchOutcome($exception->errorCode),
                    'safe_code' => $exception->errorCode,
                ];
            } catch (SearchProviderException $exception) {
                $fetchAttempts[] = [
                    'attempt' => $index + 1,
                    'status' => $this->fetchOutcome($exception->safeCode),
                    'safe_code' => $exception->safeCode,
                ];
            }
        }

        $candidate = null;
        $candidateSafeCode = null;
        if ($successfulFetch !== null && $successfulResult !== null) {
            try {
                $candidate = $this->candidateIngestor->handle($successfulResult, $actor);
            } catch (ValidationException) {
                $candidateSafeCode = 'stage09b_candidate_minimum_evidence_not_met';
            } catch (Throwable) {
                throw new SearchProviderException('internal', 'stage09b_candidate_ingestion_failed_safely');
            }
        }

        $candidateCount = ProspectingCandidate::query()->count();
        if ($candidateCount > self::MAX_CANDIDATES) {
            throw new SearchProviderException('probe_budget', 'stage09b_candidate_budget_exceeded');
        }
        $unitChanges = Unit::query()->count() - $unitCountBefore;
        $entityChanges = Entity::query()->count() - $entityCountBefore;
        if ($unitChanges !== 0 || $entityChanges !== 0) {
            throw new SearchProviderException('probe_policy', 'stage09b_unit_entity_change_blocked');
        }

        $resultHashes = $results->pluck('result_hash')->sort()->values()->implode('|');
        $safeRequestId = $execution->safe_request_id;
        $evidence = $successfulFetch ? [
            'title_fields' => $successfulFetch->page_title ? 1 : 0,
            'description_fields' => $successfulFetch->meta_description ? 1 : 0,
            'heading_count' => count($successfulFetch->headings ?? []),
            'text_bytes' => strlen((string) $successfulFetch->text_excerpt),
            'same_domain_link_count' => count($successfulFetch->same_domain_links ?? []),
            'protected_channel_count' => (int) $successfulFetch->channel_count,
            'content_hash' => $successfulFetch->content_hash,
            'trust_level' => $successfulFetch->trust_level,
            'instruction_authority' => $successfulFetch->instruction_authority,
        ] : $this->emptyEvidence();

        return [
            'status' => $results->isEmpty() ? 'no_results' : 'completed',
            ...$baseReport,
            'normalized_results' => $results->count(),
            'registrable_domains' => $results->pluck('registrable_domain')->filter()->unique()->count(),
            'duplicate_results' => (int) $execution->duplicate_count,
            'blocked_results' => (int) $execution->blocked_result_count,
            'result_set_hash' => $resultHashes !== '' ? hash('sha256', $resultHashes) : null,
            'safe_request_id_hash' => $safeRequestId ? hash('sha256', $safeRequestId) : null,
            'fetch_attempts' => $fetchAttempts,
            'successful_pages' => $successfulFetch ? 1 : 0,
            'evidence' => $evidence,
            'candidate_count' => $candidateCount,
            'candidate_status' => $candidate?->status?->value,
            'candidate_safe_code' => $candidateSafeCode,
            'candidate_source_count' => $candidate?->sources()->count() ?? 0,
            'candidate_protected_channel_count' => $candidate?->channels()->count() ?? 0,
            'unit_changes' => $unitChanges,
            'entity_changes' => $entityChanges,
        ];
    }

    /** @return array{0: User, 1: Product, 2: City} */
    private function createSyntheticFixtures(): array
    {
        $permissions = [
            'ai_sales.view',
            'ai_sales.sales.view',
            'ai_sales.prospecting.view',
            'ai_sales.prospecting.jobs.manage',
            'ai_sales.prospecting.review',
            'ai_sales.search.plan',
            'ai_sales.search.review',
            'ai_sales.search.execute',
            'ai_sales.search.research',
        ];
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'crm']);
        }

        $actor = User::query()->create([
            'name' => 'Repository Stage 09B Synthetic Operator',
            'email' => 'stage09b-operator@synthetic.invalid',
            'password' => Hash::make(Str::random(64)),
            'type' => 'employee',
            'status' => 'active',
            'account_type' => 'individual',
        ]);
        $actor->forceFill(['email_verified_at' => now()])->save();
        $actor->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $country = Country::query()->create([
            'name' => 'Россия',
            'сodeISO' => 'RU',
        ]);
        $region = Region::query()->create([
            'name' => 'Санкт-Петербург',
            'country_id' => $country->id,
        ]);
        $city = City::query()->create([
            'name' => 'Санкт-Петербург',
            'region_id' => $region->id,
        ]);
        $product = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Брокколи',
            'eng' => 'Broccoli',
            'is_published' => true,
        ]);

        if (! $product->is_published) {
            throw new LogicException('Repository-owned Stage 09B Product fixture must be published.');
        }

        return [$actor, $product, $city];
    }

    /** @return array<string, mixed> */
    private function emptyEvidence(): array
    {
        return [
            'title_fields' => 0,
            'description_fields' => 0,
            'heading_count' => 0,
            'text_bytes' => 0,
            'same_domain_link_count' => 0,
            'protected_channel_count' => 0,
            'content_hash' => null,
            'trust_level' => 'untrusted',
            'instruction_authority' => 'none',
        ];
    }

    private function fetchOutcome(string $safeCode): string
    {
        return match (true) {
            str_starts_with($safeCode, 'robots_') => 'robots_blocked',
            str_contains($safeCode, 'dns'), str_contains($safeCode, 'private'), str_contains($safeCode, 'address') => 'unsafe_address',
            str_contains($safeCode, 'content_type') => 'unsupported_content_type',
            str_contains($safeCode, 'timeout'), str_contains($safeCode, 'connection') => 'timeout',
            str_contains($safeCode, 'too_large') => 'too_large',
            str_contains($safeCode, 'redirect') => 'redirect_blocked',
            default => 'provider_error',
        };
    }
}
