<?php

namespace Tests\Unit\AiSales;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Prospecting\DomainInvestigationPlanner;
use App\Domain\AiSales\Prospecting\PublicCompanyIdentityResolver;
use App\Domain\AiSales\Prospecting\ResultBusinessRoleClassifier;
use App\Models\ProspectingPublicFetch;
use App\Models\ProspectingSearchResult;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class BuyerDiscoveryQualityBenchmarkTest extends TestCase
{
    public function test_repository_owned_twenty_result_benchmark_collapses_domains_and_rejects_non_buyers(): void
    {
        $fixtures = json_decode(file_get_contents(__DIR__.'/../../Fixtures/AiSales/buyer_discovery_quality_benchmark.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertGreaterThanOrEqual(20, count($fixtures));
        $classifier = new ResultBusinessRoleClassifier;
        $domains = collect($fixtures)->groupBy('domain');
        $decisions = $domains->map(fn (Collection $rows, string $domain) => $classifier->classifyEvidence(
            $rows->map(fn (array $row): string => $row['title'].' '.$row['snippet'])->implode(' '),
            $domain,
            BusinessLane::Sales,
        ));
        foreach ($domains as $domain => $rows) {
            $this->assertSame($rows->first()['expected'], $decisions[$domain]->role->value, $domain);
        }

        $this->assertLessThan(count($fixtures), $domains->count());
        $this->assertSame('supplier_or_competitor', $decisions['broccoli-wholesale.example']->role->value);
        $this->assertSame('marketplace', $decisions['marketplace.example']->role->value);
        $this->assertSame('directory', $decisions['business-directory.example']->role->value);
        $this->assertFalse($decisions['recipe-journal.example']->candidateEligible);
        $candidates = $decisions->filter->candidateEligible;
        $buyerLike = $candidates->filter(fn ($decision, string $domain): bool => in_array(
            $domains[$domain]->first()['expected'],
            ['potential_buyer', 'possible_buyer'],
            true,
        ));
        $precision = $candidates->isEmpty() ? 0 : (int) round(($buyerLike->count() / $candidates->count()) * 100);
        $this->assertCount(17, $domains);
        $this->assertCount(8, $candidates);
        $this->assertSame(100, $precision);
        $this->assertGreaterThanOrEqual(80, $precision);
    }

    public function test_identity_resolver_uses_company_identity_and_never_seo_sales_title(): void
    {
        $resolved = $this->fixtureResult('quality.example', 'Пищевой завод Нева | О компании', 'https://quality.example/about');
        $seo = $this->fixtureResult('quality.example', 'Брокколи оптом — купить с доставкой', 'https://quality.example/catalog/broccoli');
        $identity = (new PublicCompanyIdentityResolver)->resolve(collect([$seo, $resolved]));

        $this->assertTrue($identity->resolved());
        $this->assertSame('Пищевой завод Нева', $identity->workingName);
        $this->assertNotSame($seo->title, $identity->workingName);

        $unresolved = (new PublicCompanyIdentityResolver)->resolve(collect([$seo]));
        $this->assertFalse($unresolved->resolved());
        $this->assertNull($unresolved->workingName);
        $this->assertSame('identity_unresolved', $unresolved->evidenceStatus);
    }

    public function test_domain_investigation_collapses_domains_and_orders_company_pages_before_product_pages(): void
    {
        $aProduct = $this->fixtureResult('a.example', 'A product', 'https://a.example/catalog/item');
        $aAbout = $this->fixtureResult('a.example', 'A company', 'https://a.example/about');
        $aHome = $this->fixtureResult('a.example', 'A home', 'https://a.example');
        $bContact = $this->fixtureResult('b.example', 'B contacts', 'https://b.example/contacts');
        $bHome = $this->fixtureResult('b.example', 'B home', 'https://b.example/');
        foreach ([$aProduct, $aAbout, $aHome, $bContact, $bHome] as $index => $result) {
            $result->domain_hash = hash('sha256', $result->registrable_domain);
            $result->rank = $index + 1;
        }

        $selected = (new DomainInvestigationPlanner)->select(
            collect([$aProduct, $aAbout, $aHome, $bContact, $bHome]),
            2,
            4,
        );

        $this->assertSame([
            'https://a.example',
            'https://b.example/',
            'https://a.example/about',
            'https://b.example/contacts',
        ], $selected->pluck('canonical_url')->all());
    }

    private function fixtureResult(string $domain, string $title, string $url): ProspectingSearchResult
    {
        $result = new ProspectingSearchResult([
            'title' => $title,
            'canonical_url' => $url,
            'registrable_domain' => $domain,
            'result_hash' => hash('sha256', $url),
            'rank' => 1,
        ]);
        $fetch = new ProspectingPublicFetch(['status' => 'completed', 'page_title' => $title, 'final_url' => $url]);
        $result->setRelation('publicFetch', $fetch);
        $result->setRelation('research', null);
        $result->setRelation('searchQuery', null);

        return $result;
    }
}
