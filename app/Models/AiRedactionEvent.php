<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AiRedactionEvent extends Model
{
    protected $fillable = [
        'ai_agent_run_id', 'ai_agent_run_step_id', 'detector', 'rule_code', 'finding_type',
        'action', 'path_hash', 'occurrences',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('AI redaction events are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('AI redaction events are append-only.');
        });
    }
}
