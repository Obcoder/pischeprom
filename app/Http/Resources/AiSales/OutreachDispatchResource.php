<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutreachDispatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'draft_id' => $this->outreach_draft_id,
            'revision_id' => $this->outreach_draft_revision_id,
            'context_id' => $this->unit_business_context_id,
            'contact_link_id' => $this->unit_contact_context_link_id,
            'product_match_id' => $this->unit_product_match_id,
            'good_match_id' => $this->unit_good_match_id,
            'mail_message_id' => $this->mail_message_id,
            'sending_id' => $this->sending_id,
            'purpose' => $this->purpose?->value,
            'state' => $this->state?->value,
            'request_profile' => $this->request_profile,
            'idempotency_protected' => true,
            'last_block_reason' => $this->last_block_reason,
            'safe_summary' => $this->safe_summary,
            'provider_job_id' => $this->provider_job_id,
            'last_revalidated_at' => $this->last_revalidated_at?->toISOString(),
            'prepared_at' => $this->prepared_at?->toISOString(),
            'queue_requested_at' => $this->queue_requested_at?->toISOString(),
            'provider_accepted_at' => $this->provider_accepted_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'replied_at' => $this->replied_at?->toISOString(),
            'ambiguous_acceptance_at' => $this->ambiguous_acceptance_at?->toISOString(),
            'auto_followup' => false,
        ];
    }
}
