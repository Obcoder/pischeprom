<?php

namespace App\Models;

use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\AiTaskProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiAgentDefinition extends Model
{
    protected $fillable = [
        'code', 'version', 'display_name', 'enabled', 'allowed_purposes', 'allowed_audiences',
        'allowed_lanes', 'default_purpose', 'default_audience', 'default_task_profile',
        'default_model_profile', 'required_capabilities', 'allowed_contours', 'prompt_version',
        'prompt_hash', 'schema_version', 'schema_hash', 'default_limits',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'allowed_purposes' => 'array',
            'allowed_audiences' => 'array',
            'allowed_lanes' => 'array',
            'default_purpose' => AiPurpose::class,
            'default_audience' => AiAudience::class,
            'default_task_profile' => AiTaskProfile::class,
            'default_model_profile' => AiModelProfile::class,
            'required_capabilities' => 'array',
            'allowed_contours' => 'array',
            'default_limits' => 'array',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AiAgentRun::class);
    }
}
