<?php

namespace App\Models;

use App\Domain\AiSales\Enums\AiProviderEndpointProfile;
use Illuminate\Database\Eloquent\Model;

class AiProviderModel extends Model
{
    protected $fillable = [
        'provider_code', 'provider_route', 'model_id', 'display_label', 'endpoint_profile',
        'active_in_inventory', 'first_seen_at', 'last_seen_at', 'safe_metadata',
        'source_reference', 'metadata_hash', 'created_by_reference', 'updated_by_reference',
    ];

    protected function casts(): array
    {
        return [
            'endpoint_profile' => AiProviderEndpointProfile::class,
            'active_in_inventory' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'safe_metadata' => 'array',
        ];
    }
}
