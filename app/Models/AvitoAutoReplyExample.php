<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvitoAutoReplyExample extends Model
{
    public const KINDS = ['positive', 'negative'];

    protected $fillable = [
        'avito_auto_reply_rule_id',
        'kind',
        'text',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'text' => 'encrypted',
            'sort_order' => 'integer',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AvitoAutoReplyRule::class, 'avito_auto_reply_rule_id');
    }
}
