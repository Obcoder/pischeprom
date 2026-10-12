<?php

namespace App\Services\Goods;

use App\Models\Entity;
use App\Models\GoodEntityPrice;
use App\Models\User;
use App\Services\Catalog\CatalogSiteContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EntityCabinetPrices
{
    public function allowedEntities(User $user): Builder
    {
        return Entity::query()->whereRaw($user->status === 'active' ? '1 = 1' : '1 = 0')->whereHas('users', fn ($query) => $query->where('users.id', $user->id)->where('entity_user.status', 'active'))
            ->where(function ($query) use ($user) {
                $query->where('customer_created_by_user_id', $user->id)
                    ->orWhereExists(fn ($access) => $access->selectRaw('1')->from('entity_price_access')
                        ->whereColumn('entity_price_access.entity_id', 'entities.id')->where('entity_price_access.user_id', $user->id));
            });
    }

    public function available(User $user): Builder
    {
        return GoodEntityPrice::query()->whereRaw($user->status === 'active' ? '1 = 1' : '1 = 0')->whereIn('entity_id', $this->allowedEntities($user)->select('entities.id'))
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', today()))
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', today()))
            ->whereHas('good', fn ($query) => app(CatalogSiteContext::class)->scopeGoods($query)->where('is_published', true));
    }

    public function forUser(User $user): Collection
    {
        $prices = $this->available($user)->with(['good:id,name,measure_id,unit_weight_kg', 'good.measure:id,name', 'entity:id,name', 'currency:id,code'])
            ->orderBy('entity_id')->orderBy('good_id')->get();

        return $prices->map(fn (GoodEntityPrice $price) => [
            'id' => $price->id, 'revision' => $price->revision, 'good_id' => $price->good_id, 'good_name' => $price->good->name,
            'entity_id' => $price->entity_id, 'entity_name' => $price->entity->name,
            'price_gross' => $price->price_gross, 'price_net' => $price->price_net, 'vat_rate' => $price->vat_rate,
            'currency_code' => $price->currency->code, 'measurement' => $price->good->measurement(),
            'valid_from' => $price->valid_from?->toDateString(), 'valid_to' => $price->valid_to?->toDateString(),
        ]);
    }
}
