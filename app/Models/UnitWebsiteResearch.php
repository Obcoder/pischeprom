<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitWebsiteResearch extends Model
{
    protected $table = 'unit_website_researches';

    protected $fillable = [
        'unit_id', 'source_mail_message_id', 'source_research_id', 'saved_by_user_id',
        'url', 'result', 'researched_at', 'saved_at',
    ];

    protected $casts = ['result' => 'array', 'researched_at' => 'datetime', 'saved_at' => 'datetime'];

    protected $hidden = ['saved_by_user_id', 'created_at', 'updated_at'];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withoutEagerLoads();
    }
}
