<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class Uri extends Model
{
    use HasFactory;

    protected $fillable = [
        'address',
        'is_valid',
        'follow',
        'has_brilliant_foremost_design',
    ];

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class);
    }

    /**
     * Текстовый поиск
     */
    public function scopeSearch($query, $search)
    {
        $search = trim($search);

        if (empty($search)) {
            return $query;
        }

        // Разбиваем строку поиска на слова
        $terms = preg_split('/\s+/', $search);

        return $query->where(function ($q) use ($terms, $search) {

            foreach ($terms as $term) {

                $q->where(function ($sub) use ($term) {

                    // Поиск по address
                    $sub->where('address', 'like', "%{$term}%");

                    // Если это число — ищем по id
                    if (is_numeric($term)) {
                        $sub->orWhere('id', $term);
                    }

                    // Поиск по связанным units
                    $sub->orWhereHas('units', function ($u) use ($term) {
                        $u->where('name', 'like', "%{$term}%");
                    });

                });

            }

        });
    }

}
