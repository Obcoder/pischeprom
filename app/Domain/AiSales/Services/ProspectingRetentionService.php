<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\ProspectingCandidateStatus;
use App\Models\ProspectingCandidate;
use App\Models\ProspectingCandidateChannel;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProspectingRetentionService
{
    public function prune(bool $apply = false, int $chunk = 100): array
    {
        if ($apply && app()->environment('production')) {
            throw new RuntimeException('Production prospecting pruning is disabled until rollout policy exists.');
        }
        $query = ProspectingCandidate::query()->whereNull('anonymized_at')->where('expires_at', '<=', now());
        $personalCutoff = now()->subDays((int) config('ai-sales.prospecting.retention.personal_channel_days', 7));
        $personalQuery = ProspectingCandidateChannel::query()
            ->where('data_classification', 'personal_data')
            ->where('created_at', '<=', $personalCutoff);
        $counts = [
            'eligible_candidates' => (clone $query)->count(),
            'eligible_personal_channels' => (clone $personalQuery)->count(),
            'anonymized_candidates' => 0,
            'deleted_channels' => 0,
            'sanitized_sources' => 0,
            'dry_run' => ! $apply,
        ];
        if (! $apply) {
            return $counts;
        }

        $personalQuery->orderBy('id')->chunkById(max(1, min(500, $chunk)), function ($channels) use (&$counts): void {
            $ids = $channels->pluck('id');
            $counts['deleted_channels'] += ProspectingCandidateChannel::query()->whereIn('id', $ids)->delete();
        });

        $query->orderBy('id')->chunkById(max(1, min(500, $chunk)), function ($candidates) use (&$counts): void {
            foreach ($candidates as $candidate) {
                DB::transaction(function () use ($candidate, &$counts): void {
                    $locked = ProspectingCandidate::query()->lockForUpdate()->whereNull('anonymized_at')->find($candidate->id);
                    if (! $locked || $locked->expires_at->isFuture()) {
                        return;
                    }
                    $counts['deleted_channels'] += $locked->channels()->delete();
                    $counts['sanitized_sources'] += $locked->sources()->update([
                        'canonical_url' => null,
                        'source_reference' => null,
                        'title' => null,
                        'source_domain' => null,
                        'bounded_excerpt' => null,
                        'updated_at' => now(),
                    ]);
                    $locked->update([
                        'working_name' => 'anonymized-'.substr($locked->fingerprint_hash, 0, 12),
                        'normalized_name' => '',
                        'normalized_domain' => null,
                        'canonical_website' => null,
                        'location_display' => null,
                        'public_activity_summary' => null,
                        'relevance_summary' => null,
                        'confidence_components' => null,
                        'normalized_payload_hash' => hash('sha256', 'anonymized|'.$locked->fingerprint_hash),
                        'status' => ProspectingCandidateStatus::Anonymized,
                        'anonymized_at' => now(),
                        'lock_version' => $locked->lock_version + 1,
                    ]);
                    $counts['anonymized_candidates']++;
                }, 3);
            }
        });

        return $counts;
    }
}
