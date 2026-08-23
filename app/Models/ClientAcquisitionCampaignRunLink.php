<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAcquisitionCampaignRunLink extends Model
{
    protected $table = 'ai_sales_campaign_run_links';

    protected $fillable = [
        'ai_sales_campaign_id', 'ai_agent_run_id', 'prospecting_search_job_id',
        'approval_snapshot_hash', 'idempotency_key', 'scheduled_for',
    ];

    protected function casts(): array
    {
        return ['scheduled_for' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ClientAcquisitionCampaign::class, 'ai_sales_campaign_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiAgentRun::class, 'ai_agent_run_id');
    }

    public function searchJob(): BelongsTo
    {
        return $this->belongsTo(ProspectingSearchJob::class, 'prospecting_search_job_id');
    }
}
