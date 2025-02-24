<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'rus',
        'eng',
        'zh',
        'es',
    ];

    public function units()
    {
        return $this->belongsToMany(Unit::class)
            ->using(product_unit::class)
            ->withPivot(['action_id'])
            ->withTimestamps();
    }
    public function consumers()
    {
        return $this->belongsToMany(
            Unit::class, // Модель конечной таблицы
            'consumptions', // Промежуточная таблица
            'product_id', // Внешний ключ в `consumptions`, который ссылается на `products`
            'unit_id', // Внешний ключ в `units`, который связывается с `consumptions.unit_id`
            'id', // Локальный ключ в `products`
            'id' // Локальный ключ в `consumptions`
        )->using(Consumption::class)
            ->withPivot(['quantity', 'measure_id']);
    }
}
