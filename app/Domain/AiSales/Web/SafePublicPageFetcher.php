<?php

namespace App\Domain\AiSales\Web;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Prospecting\ProspectingResearchBudget;
use App\Domain\AiSales\Search\SearchProviderException;
use App\Domain\AiSales\Services\ProspectingAuthorizationService;
use App\Domain\AiSales\Services\ProspectingFeatureGuard;
use App\Domain\AiSales\Tools\AiToolDlpGuard;
use App\Models\ProspectingPublicFetch;
use App\Models\ProspectingSearchJob;
use App\Models\ProspectingSearchResult;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Throwable;

class SafePublicPageFetcher
{
    public function __construct(
        private readonly ProspectingFeatureGuard $features,
        private readonly ProspectingAuthorizationService $authorization,
        private readonly PublicFetchPolicy $policy,
        private readonly RobotsPolicyService $robots,
        private readonly PublicPageTextExtractor $extractor,
        private readonly AiToolDlpGuard $dlp,
        private readonly HttpFactory $http,
        private readonly ProspectingResearchBudget $budgets,
    ) {}

    public function fetch(ProspectingSearchResult $result, User $actor): ProspectingPublicFetch
    {
        $this->features->pageFetch();
        $result->loadMissing('job');
        $this->authorization->authorize($actor, ProspectingAuthorizationService::RESEARCH_SEARCH_RESULTS, $result->job->lane);
        if ($result->duplicate_of_id !== null) {
            throw new SearchProviderException('fetch_policy', 'duplicate_result_fetch_blocked');
        }
        if (! hash_equals((string) $result->url_hash, hash('sha256', (string) $result->canonical_url))
            || ! hash_equals((string) $result->domain_hash, hash('sha256', (string) $result->registrable_domain))
            || $result->trust_level !== 'untrusted'
            || $result->instruction_authority !== 'none') {
            throw new SearchProviderException('fetch_policy', 'persisted_search_result_integrity_blocked');
        }

        [$result, $fetch, $reserved] = DB::transaction(function () use ($result): array {
            $job = ProspectingSearchJob::query()->lockForUpdate()->findOrFail($result->prospecting_search_job_id);
            $lockedResult = ProspectingSearchResult::query()->with('publicFetch')
                ->lockForUpdate()->findOrFail($result->id);
            $lockedResult->setRelation('job', $job);
            $existing = $lockedResult->publicFetch;
            if ($existing?->status === 'completed') {
                return [$lockedResult, $existing, false];
            }
            if ($existing !== null) {
                throw new SearchProviderException('idempotency', 'public_fetch_replay_blocked');
            }
            $this->budgets->assertCanFetch($job, (string) $lockedResult->domain_hash);
            $fetch = $lockedResult->publicFetch()->create([
                'status' => 'processing',
                'trust_level' => 'untrusted',
                'instruction_authority' => 'none',
            ]);
            $lockedResult->update(['fetch_status' => 'processing']);

            return [$lockedResult, $fetch, true];
        }, 3);
        if (! $reserved) {
            return $fetch;
        }
        $startedAt = hrtime(true);

        try {
            $resolved = $this->policy->authorize($result->canonical_url, $result->registrable_domain);
            $this->policy->reserveDomainPage($resolved->registrableDomain);
            $this->policy->assertDnsStable($resolved);
            $robotsStatus = $this->robots->assertAllowed($resolved);
            [$response, $resolved] = $this->requestFollowingSafeRedirects($resolved);
            $this->policy->assertDnsStable($resolved);
            $contentType = mb_strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            if (! in_array($contentType, ['text/html', 'text/plain'], true)) {
                throw new SearchProviderException('fetch_policy', 'public_fetch_content_type_blocked');
            }
            $encoding = mb_strtolower(trim((string) $response->header('Content-Encoding')));
            if ($encoding !== '' && $encoding !== 'identity') {
                throw new SearchProviderException('fetch_policy', 'public_fetch_compression_blocked');
            }
            $maxBytes = (int) config('ai-sales.prospecting.limits.max_public_fetch_bytes', 524_288);
            $contentLength = trim((string) $response->header('Content-Length'));
            if ($contentLength !== '' && (! ctype_digit($contentLength) || (int) $contentLength > $maxBytes)) {
                throw new SearchProviderException('fetch_policy', 'public_fetch_body_too_large');
            }
            $body = $response->body();
            if (strlen($body) > $maxBytes) {
                throw new SearchProviderException('fetch_policy', 'public_fetch_body_too_large');
            }
            $extract = $this->extractor->extract($body, $contentType, $resolved->url);
            $this->dlp->assertPayloadSafe([
                'page_title' => $extract->title,
                'meta_description' => $extract->metaDescription,
                'headings' => $extract->headings,
                'text_excerpt' => $extract->visibleText,
            ], AiProcessingContour::ExternalSanitized, $result->job->lane);
            $durationMs = (int) ceil((hrtime(true) - $startedAt) / 1_000_000);
            $fetch->update([
                'status' => 'completed',
                'final_url' => $resolved->url,
                'final_url_hash' => hash('sha256', $resolved->url),
                'registrable_domain' => $resolved->registrableDomain,
                'content_type' => $contentType,
                'byte_count' => strlen($body),
                'duration_ms' => $durationMs,
                'page_title' => $extract->title,
                'meta_description' => $extract->metaDescription,
                'headings' => $extract->headings,
                'text_excerpt' => $extract->visibleText,
                'same_domain_links' => $extract->sameDomainLinks,
                'protected_channels' => $extract->channels,
                'channel_count' => count($extract->channels),
                'content_hash' => $extract->contentHash,
                'robots_status' => $robotsStatus,
                'fetched_at' => now(),
            ]);
            $result->update(['fetch_status' => 'completed']);

            return $fetch->fresh();
        } catch (PolicyViolation $exception) {
            $fetch->update([
                'status' => 'blocked',
                'duration_ms' => (int) ceil((hrtime(true) - $startedAt) / 1_000_000),
                'error_category' => 'policy',
                'error_code' => mb_substr($exception->errorCode, 0, 96),
                'fetched_at' => now(),
            ]);
            $result->update(['fetch_status' => 'blocked']);
            throw $exception;
        } catch (SearchProviderException $exception) {
            $fetch->update([
                'status' => 'blocked',
                'duration_ms' => (int) ceil((hrtime(true) - $startedAt) / 1_000_000),
                'error_category' => mb_substr($exception->category, 0, 64),
                'error_code' => mb_substr($exception->safeCode, 0, 96),
                'fetched_at' => now(),
            ]);
            $result->update(['fetch_status' => 'blocked']);
            throw $exception;
        } catch (Throwable) {
            $fetch->update([
                'status' => 'failed',
                'duration_ms' => (int) ceil((hrtime(true) - $startedAt) / 1_000_000),
                'error_category' => 'internal',
                'error_code' => 'public_fetch_failed_safely',
                'fetched_at' => now(),
            ]);
            $result->update(['fetch_status' => 'failed']);
            throw new SearchProviderException('internal', 'public_fetch_failed_safely');
        }
    }

    /** @return array{0: \Illuminate\Http\Client\Response, 1: ResolvedPublicUrl} */
    private function requestFollowingSafeRedirects(ResolvedPublicUrl $target): array
    {
        $maxRedirects = (int) config('ai-sales.prospecting.limits.max_public_fetch_redirects', 2);
        for ($redirect = 0; $redirect <= $maxRedirects; $redirect++) {
            $this->policy->assertDnsStable($target);
            try {
                $response = $this->http
                    ->withHeaders([
                        'User-Agent' => 'pischeprom-public-research-stage09/1.0',
                        'Accept' => 'text/html,text/plain;q=0.9',
                        'Accept-Encoding' => 'identity',
                    ])
                    ->connectTimeout((int) config('ai-sales.prospecting.limits.public_fetch_connect_timeout_seconds', 3))
                    ->timeout((int) config('ai-sales.prospecting.limits.public_fetch_timeout_seconds', 10))
                    ->withoutRedirecting()
                    ->withOptions($this->policy->pinnedTransportOptions($target))
                    ->get($target->url);
            } catch (ConnectionException) {
                throw new SearchProviderException('network', 'public_fetch_connection_failed');
            }
            $this->policy->assertDnsStable($target);
            if (! $response->redirect()) {
                if (! $response->successful()) {
                    throw new SearchProviderException('provider_rejected', 'public_fetch_http_'.$response->status());
                }

                return [$response, $target];
            }
            if ($redirect === $maxRedirects) {
                throw new SearchProviderException('fetch_policy', 'public_fetch_redirect_limit_blocked');
            }
            $location = $response->header('Location');
            if (! is_string($location) || ! preg_match('#^https?://#i', $location)) {
                throw new SearchProviderException('fetch_policy', 'public_fetch_relative_redirect_blocked');
            }
            $redirectTarget = $this->policy->authorize($location, $target->registrableDomain);
            if (! hash_equals($target->host, $redirectTarget->host)) {
                throw new SearchProviderException('fetch_policy', 'cross_domain_redirect_blocked');
            }
            $target = $redirectTarget;
        }

        throw new SearchProviderException('fetch_policy', 'public_fetch_redirect_limit_blocked');
    }
}
