<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiAgentDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'version' => $this->version,
            'display_name' => $this->display_name,
            'enabled' => $this->enabled,
            'allowed_purposes' => $this->allowed_purposes,
            'allowed_audiences' => $this->allowed_audiences,
            'allowed_lanes' => $this->allowed_lanes,
            'task_profile' => $this->default_task_profile->value,
            'model_profile' => $this->default_model_profile->value,
            'required_capabilities' => $this->required_capabilities,
            'allowed_contours' => $this->allowed_contours,
            'prompt_version' => $this->prompt_version,
            'prompt_hash' => $this->prompt_hash,
            'schema_version' => $this->schema_version,
            'schema_hash' => $this->schema_hash,
            'default_limits' => $this->default_limits,
        ];
    }
}
