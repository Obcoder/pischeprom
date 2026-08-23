<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class OutreachReplyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'dispatch_id' => $this->outreach_dispatch_id,
            'mail_message_id' => $this->incoming_mail_message_id,
            'correlation_method' => $this->correlation_method,
            'triage_profile' => $this->triage_profile,
            'triage_status' => $this->triage_status?->value,
            'triage_class' => $this->triage_class?->value,
            'safe_reason_code' => $this->safe_reason_code,
            'restricted_preview' => $this->whenLoaded('incomingMessage', fn () => Str::limit((string) $this->incomingMessage->preview, 500)),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
