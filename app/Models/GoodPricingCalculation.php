<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class GoodPricingCalculation extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'measurement' => 'array',
        'input' => 'array',
        'result' => 'array',
        'price_net' => 'float',
        'price_gross' => 'float',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Names and notes may be corrected; the financial evidence must remain intact.
        static::updating(function (self $calculation): void {
            if (array_diff(array_keys($calculation->getDirty()), ['name', 'comment'])) {
                throw new LogicException('Saved calculation snapshots are immutable.');
            }
        });
    }

    public function workspaceData(): array
    {
        return [
            'id' => $this->id,
            'archive_source' => 'workspace',
            'scope' => $this->scope,
            'entity_id' => $this->entity_id,
            'target_name' => $this->target_name,
            'name' => $this->name,
            'comment' => $this->comment,
            'currency_id' => $this->currency_id,
            'currency_code' => $this->currency_code,
            'measurement' => $this->measurement,
            'input' => $this->input,
            'result' => $this->result,
            'price_net' => $this->price_net,
            'price_gross' => $this->price_gross,
            'created_at' => $this->created_at?->toISOString(),
            'created_by_name' => $this->created_by_name,
        ];
    }
}
