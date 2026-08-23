<?php

namespace App\Domain\AiSales\Campaigns;

use App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionAutomationMode;
use App\Domain\AiSales\Enums\CandidateResolutionOutcome;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ProspectingCandidateStatus;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\ClientAcquisitionCampaign;
use App\Models\ProspectingCandidate;
use Illuminate\Support\Str;

final class AutonomousUnitCreationPolicy
{
    public const CODE = 'autonomous_unit_creation.v1';

    public const VERSION = '1';

    private const PERSONAL_MAIL_DOMAINS = [
        'gmail.com', 'googlemail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
        'mail.ru', 'inbox.ru', 'list.ru', 'bk.ru', 'yandex.ru', 'ya.ru', 'rambler.ru',
    ];

    public function __construct(
        private readonly ClientAcquisitionCampaignFeatureGuard $features,
        private readonly ClientAcquisitionCampaignHashes $hashes,
    ) {}

    public function assertEligible(ClientAcquisitionCampaign $campaign, ProspectingCandidate $candidate): void
    {
        $this->features->autoUnit();
        $candidate->loadMissing(['sources', 'channels', 'products']);
        if ($campaign->automation_mode !== ClientAcquisitionAutomationMode::AutonomousReviewed
            || ! $campaign->auto_unit_approved
            || $campaign->auto_unit_policy_code !== self::CODE
            || $campaign->auto_unit_policy_version !== self::VERSION
            || ! $this->hashes->isCurrent($campaign)) {
            throw new PolicyViolation('auto_unit_campaign_policy_blocked', 'Auto Unit creation lacks a current explicit campaign approval.');
        }
        if ($candidate->status !== ProspectingCandidateStatus::NewUnitReview
            || $candidate->resolution_outcome !== CandidateResolutionOutcome::NewUnitAllowed
            || (int) $candidate->prospecting_search_job_id < 1
            || ! $campaign->runLinks()->where('prospecting_search_job_id', $candidate->prospecting_search_job_id)->exists()) {
            throw new PolicyViolation('auto_unit_candidate_state_blocked', 'Candidate is not a new-unit decision inside this campaign.');
        }
        if ($candidate->lane->value !== 'sales' || $candidate->role_code->value !== 'prospective_customer') {
            throw new PolicyViolation('auto_unit_lane_blocked', 'Auto Unit creation is sales/prospective-customer only.');
        }
        $domain = Str::lower(trim((string) $candidate->normalized_domain));
        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $domain)
            || in_array($domain, self::PERSONAL_MAIL_DOMAINS, true)) {
            throw new PolicyViolation('auto_unit_corporate_domain_required', 'A valid corporate public domain is required.');
        }
        $allowedScopes = [UnitVisibilityScope::SharedPublic, UnitVisibilityScope::SalesLane];
        if ($candidate->sources->isEmpty()
            || $candidate->sources->contains(fn ($source) => $source->data_classification !== DataClassification::Public
                || ! in_array($source->visibility_scope, $allowedScopes, true))) {
            throw new PolicyViolation('auto_unit_source_classification_blocked', 'Candidate source classification or lane is not eligible.');
        }
        $primary = $candidate->sources->contains(function ($source) use ($domain): bool {
            $sourceDomain = Str::lower((string) ($source->source_domain
                ?: parse_url((string) $source->canonical_url, PHP_URL_HOST)));

            return $sourceDomain === $domain
                && in_array($source->source_type, ['public_search', 'public_fetch', 'corporate_website', 'company_website', 'synthetic_fixture'], true);
        });
        if (! $primary) {
            throw new PolicyViolation('auto_unit_primary_source_required', 'A primary corporate source is required.');
        }
        $families = $candidate->sources->map(function ($source): string {
            $domain = Str::lower((string) ($source->source_domain
                ?: parse_url((string) $source->canonical_url, PHP_URL_HOST)));

            return $domain !== '' ? 'domain:'.$domain : 'type:'.Str::lower((string) $source->source_type);
        })->unique()->count();
        if ($families < (int) config('ai-sales.campaigns.policies.auto_unit.minimum_independent_sources', 2)) {
            throw new PolicyViolation('auto_unit_independent_sources_required', 'Independent public-source threshold is not met.');
        }
        if ($candidate->channels->isNotEmpty()
            && ! $candidate->channels->contains(fn ($channel) => $channel->contact_role === 'business_general')) {
            throw new PolicyViolation('auto_unit_personal_only_contact_blocked', 'Personal-only contact evidence cannot authorize Unit creation.');
        }
        if ($candidate->channels->contains(fn ($channel) => ! in_array($channel->data_classification, [
            DataClassification::Public, DataClassification::PersonalData,
        ], true))) {
            throw new PolicyViolation('auto_unit_channel_classification_blocked', 'A Candidate channel is secret or unclassified.');
        }
        if (! $candidate->products->contains(fn ($product) => $product->status->value === 'approved')) {
            throw new PolicyViolation('auto_unit_product_scope_required', 'Approved Product scope is required.');
        }
        $campaignJobIds = $campaign->runLinks()->whereNotNull('prospecting_search_job_id')
            ->pluck('prospecting_search_job_id');
        $campaignCreated = ProspectingCandidate::query()->whereIn('prospecting_search_job_id', $campaignJobIds)
            ->where('status', ProspectingCandidateStatus::NewUnitCreated->value);
        $runCreated = ProspectingCandidate::query()
            ->where('prospecting_search_job_id', $candidate->prospecting_search_job_id)
            ->where('status', ProspectingCandidateStatus::NewUnitCreated->value)->count();
        $campaignDaily = (clone $campaignCreated)->where('reviewed_at', '>=', now()->startOfDay())->count();
        $campaignMonthly = (clone $campaignCreated)->where('reviewed_at', '>=', now()->startOfMonth())->count();
        $globalDaily = ProspectingCandidate::query()->where('status', ProspectingCandidateStatus::NewUnitCreated->value)
            ->where('reviewed_at', '>=', now()->startOfDay())->count();
        $globalMonthly = ProspectingCandidate::query()->where('status', ProspectingCandidateStatus::NewUnitCreated->value)
            ->where('reviewed_at', '>=', now()->startOfMonth())->count();
        if ($campaign->max_units_per_run < 1 || $campaign->max_units_per_day < 1 || $campaign->max_units_per_month < 1
            || $runCreated >= $campaign->max_units_per_run
            || $campaignDaily >= $campaign->max_units_per_day || $campaignMonthly >= $campaign->max_units_per_month
            || $globalDaily >= (int) config('ai-sales.campaigns.limits.global_units_per_day', 0)
            || $globalMonthly >= (int) config('ai-sales.campaigns.limits.global_units_per_month', 0)) {
            throw new PolicyViolation('auto_unit_cap_exhausted', 'Auto Unit campaign or global cap is exhausted.');
        }
    }
}
