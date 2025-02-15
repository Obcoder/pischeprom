<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class product_unit extends Pivot
{
    protected $table = 'product_unit';

    protected $with = ['action'];
    public function action()
    {
        return $this->belongsTo(Action::class, 'action_id')
            ->withDefault();
    }

    public function toArray()
    {
        $array = parent::toArray();
        $array['action'] = $this->action->name ?? null; // Заменяем action_id на action.name
        unset($array['action_id']); // Убираем action_id
        return $array;
    }
}
