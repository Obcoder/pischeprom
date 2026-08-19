<?php

namespace App\Domain\AiSales\Campaigns;

use App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionCampaignStatus;
use App\Models\ClientAcquisitionCampaign;
use DomainException;

final class ClientAcquisitionCampaignStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['review_required', 'cancelled'],
        'review_required' => ['approved', 'scheduled', 'cancelled'],
        'approved' => ['scheduled', 'running', 'review_required', 'cancelled'],
        'scheduled' => ['running', 'paused', 'review_required', 'cancelled'],
        'running' => ['review_required', 'paused', 'blocked', 'completed', 'cancelled'],
        'paused' => ['approved', 'scheduled', 'running', 'review_required', 'cancelled'],
        'blocked' => ['review_required', 'cancelled'],
        'completed' => ['scheduled', 'archived'],
        'cancelled' => ['archived'],
        'archived' => [],
    ];

    public function canTransition(ClientAcquisitionCampaignStatus $from, ClientAcquisitionCampaignStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function transition(ClientAcquisitionCampaign $campaign, ClientAcquisitionCampaignStatus $to, array $attributes = []): ClientAcquisitionCampaign
    {
        if ($campaign->status === $to) {
            return $campaign;
        }
        if (! $this->canTransition($campaign->status, $to)) {
            throw new DomainException("Campaign transition {$campaign->status->value} -> {$to->value} is forbidden.");
        }
        $updated = ClientAcquisitionCampaign::query()
            ->whereKey($campaign->id)
            ->where('status', $campaign->status->value)
            ->where('lock_version', $campaign->lock_version)
            ->update([
                'status' => $to->value,
                'lock_version' => $campaign->lock_version + 1,
                'updated_at' => now(),
                ...$attributes,
            ]);
        if ($updated !== 1) {
            throw new DomainException('Campaign state changed concurrently.');
        }

        return $campaign->fresh();
    }
}
