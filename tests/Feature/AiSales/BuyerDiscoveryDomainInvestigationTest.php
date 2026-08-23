<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Services\IngestProspectingSearchCandidate;
use App\Models\ProspectingPublicFetch;
use App\Models\ProspectingSearchResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuyerDiscoveryDomainInvestigationTest extends Stage09TestCase
{
    public function test_multiple_pages_from_one_domain_create_one_identity_named_candidate_with_aggregated_evidence(): void
    {
        $actor = $this->prospectingUser();
        $job = $this->approvedPlannedJob($actor);
        $result = $this->domainResult($job, 'https://buyer.synthetic.example/catalog/vegetables', 'Овощные смеси и готовые блюда', 1);
        $about = $this->domainResult($job, 'https://buyer.synthetic.example/about', 'Компания Северное производство | О компании', 2);
        $this->fetch($result, 'Овощные смеси и готовые блюда', 'Производитель готовых блюд и замороженных продуктов.');
        $this->fetch($about, 'Компания Северное производство | О компании', 'Пищевая фабрика и производство овощных полуфабрикатов.');
        $units = DB::table('units')->count();
        $entities = DB::table('entities')->count();

        $first = app(IngestProspectingSearchCandidate::class)->handle($result->fresh(), $actor);
        $replay = app(IngestProspectingSearchCandidate::class)->handle($about->fresh(), $actor);

        $this->assertSame($first->id, $replay->id);
        $this->assertSame('Компания Северное производство', $first->working_name);
        $this->assertNotSame($result->title, $first->working_name);
        $this->assertSame(2, $first->sources()->count());
        $this->assertSame(1, $job->candidates()->count());
        $this->assertSame(1, $job->searchResults()->whereNotNull('prospecting_candidate_id')->distinct()->count('prospecting_candidate_id'));
        $this->assertSame($units, DB::table('units')->count());
        $this->assertSame($entities, DB::table('entities')->count());
    }

    public function test_buyer_like_activity_without_public_company_identity_stays_review_required_before_candidate(): void
    {
        $actor = $this->prospectingUser();
        $job = $this->approvedPlannedJob($actor);
        $result = $this->domainResult($job, 'https://buyer.synthetic.example/catalog/ready-meals', 'Производство готовых блюд и овощных смесей', 1);
        $this->fetch($result, 'Производство готовых блюд и овощных смесей', 'Пищевая фабрика, производитель замороженных продуктов.');

        try {
            app(IngestProspectingSearchCandidate::class)->handle($result->fresh(), $actor);
            $this->fail('Identity-unresolved evidence created a Candidate.');
        } catch (ValidationException $exception) {
            $this->assertSame('identity_unresolved_review_required', $exception->errors()['search_result'][0]);
        }
        $this->assertDatabaseCount('prospecting_candidates', 0);
        $this->assertDatabaseCount('entity_unit', 0);
    }

    private function domainResult($job, string $url, string $title, int $rank): ProspectingSearchResult
    {
        $query = $job->queries()->where('plan_status', 'approved')->firstOrFail();
        $execution = \App\Models\ProspectingSearchExecution::query()->firstOrCreate([
            'prospecting_search_query_id' => $query->id,
            'request_hash' => hash('sha256', 'domain-investigation:'.$job->id),
        ], [
            'prospecting_search_job_id' => $job->id,
            'initiated_by' => $job->owner_user_id,
            'profile_code' => 'prospecting_b2b_discovery',
            'provider_code' => 'fake',
            'plan_hash' => $query->plan_hash,
            'status' => 'completed',
            'attempt' => 1,
            'request_count' => 0,
            'result_count' => 0,
        ]);
        $domain = 'buyer.synthetic.example';

        return ProspectingSearchResult::query()->create([
            'prospecting_search_execution_id' => $execution->id,
            'prospecting_search_job_id' => $job->id,
            'prospecting_search_query_id' => $query->id,
            'rank' => $rank,
            'title' => $title,
            'snippet' => 'Публичная производственная деятельность.',
            'url' => $url,
            'canonical_url' => $url,
            'url_hash' => hash('sha256', $url),
            'registrable_domain' => $domain,
            'domain_hash' => hash('sha256', $domain),
            'result_hash' => hash('sha256', $url.'|'.$title),
            'fetch_status' => 'completed',
        ]);
    }

    private function fetch(ProspectingSearchResult $result, string $title, string $description): void
    {
        ProspectingPublicFetch::query()->create([
            'prospecting_search_result_id' => $result->id,
            'status' => 'completed',
            'final_url' => $result->canonical_url,
            'final_url_hash' => hash('sha256', $result->canonical_url),
            'registrable_domain' => $result->registrable_domain,
            'content_type' => 'text/html',
            'page_title' => $title,
            'meta_description' => $description,
            'text_excerpt' => $description,
            'content_hash' => hash('sha256', $description),
            'fetched_at' => now(),
        ]);
    }
}
