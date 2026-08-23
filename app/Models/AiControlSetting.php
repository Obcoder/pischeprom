<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiControlSetting extends Model
{
    protected $fillable = ['key', 'boolean_value', 'updated_by'];

    protected function casts(): array
    {
        return ['boolean_value' => 'boolean'];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
