<?php

namespace App\Domain\AiSales\Campaigns;

use App\Domain\AiSales\Support\AiCanonicalJson;
use DomainException;

final class ClientAcquisitionCampaignWorkflowRegistry
{
    public const CODE = 'buyer_acquisition_campaign.v1';

    public const VERSION = '1';

    /** @var list<array{sequence: int, code: string, label: string}> */
    private const STAGES = [
        ['sequence' => 1, 'code' => 'validate_campaign', 'label' => 'Validate campaign'],
        ['sequence' => 2, 'code' => 'create_or_reuse_product_search_job', 'label' => 'Create or reuse Product-first Search Job'],
        ['sequence' => 3, 'code' => 'plan_queries', 'label' => 'Plan server-owned queries'],
        ['sequence' => 4, 'code' => 'execute_approved_yandex_searches', 'label' => 'Execute approved bounded Yandex searches'],
        ['sequence' => 5, 'code' => 'normalize_and_dedupe_results', 'label' => 'Normalize and deduplicate results'],
        ['sequence' => 6, 'code' => 'safe_public_fetch_and_research', 'label' => 'Safe public fetch and research'],
        ['sequence' => 7, 'code' => 'ingest_candidates', 'label' => 'Ingest Candidates'],
        ['sequence' => 8, 'code' => 'deterministic_unit_resolution', 'label' => 'Deterministic Unit resolution'],
        ['sequence' => 9, 'code' => 'unit_creation_or_review', 'label' => 'Auto-create Unit or project review'],
        ['sequence' => 10, 'code' => 'product_match_or_review', 'label' => 'Create or review UnitProductMatch'],
        ['sequence' => 11, 'code' => 'deterministic_scoring', 'label' => 'Deterministic scoring'],
        ['sequence' => 12, 'code' => 'outreach_draft_or_review', 'label' => 'Generate outreach draft or project review'],
        ['sequence' => 13, 'code' => 'update_progress_digest', 'label' => 'Update progress and review digest'],
        ['sequence' => 14, 'code' => 'stop', 'label' => 'Stop'],
    ];

    /** @return list<array{sequence: int, code: string, label: string}> */
    public function stages(string $code = self::CODE): array
    {
        if ($code !== self::CODE) {
            throw new DomainException('Unknown client-acquisition campaign workflow.');
        }

        return self::STAGES;
    }

    /** @return array{sequence: int, code: string, label: string} */
    public function stage(int $sequence): array
    {
        return collect(self::STAGES)->firstWhere('sequence', $sequence)
            ?? throw new DomainException('Unknown client-acquisition campaign stage.');
    }

    public function hash(): string
    {
        return AiCanonicalJson::hash([
            'code' => self::CODE,
            'version' => self::VERSION,
            'stages' => self::STAGES,
            'ordering' => 'server_owned_fixed',
            'provider_native_tools' => false,
            'pseudo_tools' => false,
            'retries' => 0,
            'failovers' => 0,
        ]);
    }
}
