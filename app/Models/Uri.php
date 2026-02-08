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
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (!$search) {
            return $query;
        }

        // Нормализация строки поиска
        $search = trim(mb_strtolower($search));

        // Разбиваем по пробелам
        $tokens = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY);

        return $query->where(function (Builder $q) use ($tokens) {

            foreach ($tokens as $token) {

                // Экранируем для LIKE
                $like = '%' . addcslashes($token, '%_') . '%';

                $q->where(function (Builder $sub) use ($like, $token) {

                    // обычный LIKE
                    $sub->where('address', 'like', $like)

                        // без http/https/www
                        ->orWhereRaw(
                            "REGEXP_REPLACE(address, '^(https?://)?(www\\.)?', '') LIKE ?",
                            [$like]
                        );

                    // булевы поля (yes / no / 1 / 0 / true / false)
                    if (in_array($token, ['1','0','yes','no','true','false'], true)) {
                        $bool = in_array($token, ['1','yes','true'], true);

                        $sub->orWhere('is_valid', $bool)
                            ->orWhere('follow', $bool);
                    }
                });
            }
        });
    }
}
