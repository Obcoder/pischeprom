<?php

namespace App\Domain\AiSales\Prospecting;

use App\Domain\AiSales\Support\AiCanonicalJson;

final class BuyerArchetypeRegistry
{
    public const VERSION = 'buyer-archetypes-v2';

    /** @return list<BuyerArchetype> */
    public function all(): array
    {
        return [
            new BuyerArchetype('food_manufacturer', 'Производитель продуктов питания', 'food_production', 'Производство продуктов питания',
                ['пищевое производство', 'производитель продуктов питания'], ['производство', 'производитель', 'продукты питания', 'food manufacturer']),
            new BuyerArchetype('ready_meal_manufacturer', 'Производитель готовых блюд', 'food_production', 'Производство продуктов питания',
                ['производство готовых блюд', 'производитель готовой еды'], ['готовые блюда', 'готовая еда', 'кулинария', 'ready meal']),
            new BuyerArchetype('frozen_food_manufacturer', 'Производитель замороженных продуктов', 'food_production', 'Производство продуктов питания',
                ['замороженные продукты производство', 'производитель замороженных овощей'], ['замороженные продукты', 'замороженные овощи', 'frozen food']),
            new BuyerArchetype('vegetable_processor', 'Овощепереработчик', 'food_production', 'Производство продуктов питания',
                ['переработка овощей производство', 'овощные полуфабрикаты производство'], ['овощи', 'овощной', 'переработка', 'полуфабрикаты', 'vegetable']),
            new BuyerArchetype('catering_factory', 'Фабрика-кухня', 'institutional_food', 'Индустриальное питание',
                ['фабрика кухня', 'центральная производственная кухня'], ['фабрика кухня', 'фабрика-кухня', 'центральная кухня']),
            new BuyerArchetype('food_service_operator', 'Оператор общественного питания', 'food_service', 'Общественное питание',
                ['оператор общественного питания', 'корпоративное питание оператор'], ['общественное питание', 'food service', 'оператор питания']),
            new BuyerArchetype('institutional_catering', 'Комбинат питания', 'institutional_food', 'Индустриальное питание',
                ['комбинат питания', 'школьное питание производство'], ['комбинат питания', 'школьное питание', 'институциональное питание']),
            new BuyerArchetype('horeca_distributor', 'HoReCa-дистрибьютор', 'distribution', 'Дистрибуция для HoReCa',
                ['дистрибьютор продуктов horeca', 'поставки продуктов ресторанам'], ['horeca', 'дистрибьютор', 'поставки ресторанам']),
            new BuyerArchetype('bakery_confectionery', 'Хлебопекарное или кондитерское производство', 'food_production', 'Производство продуктов питания',
                ['хлебопекарное производство', 'кондитерское производство'], ['хлебопекар', 'кондитер', 'bakery', 'confectionery']),
            new BuyerArchetype('beverage_producer', 'Производитель напитков', 'food_production', 'Производство продуктов питания',
                ['производитель напитков', 'производство безалкогольных напитков'], ['напитки', 'beverage', 'соки', 'розлив']),
            new BuyerArchetype('cosmetics_manufacturer', 'Производитель косметики', 'personal_care', 'Косметика и средства ухода',
                ['косметическое производство', 'производитель косметики'], ['косметик', 'парфюм', 'средства ухода', 'personal care']),
            new BuyerArchetype('pharmaceutical_manufacturer', 'Фармацевтический производитель', 'pharmaceuticals', 'Фармацевтическое производство',
                ['фармацевтическое производство', 'производитель лекарственных средств'], ['фармацевт', 'фармация', 'лекарствен', 'pharmaceutical']),
            new BuyerArchetype('household_chemicals_manufacturer', 'Производитель бытовой химии', 'chemical_products', 'Бытовая химия',
                ['производство бытовой химии', 'производитель моющих средств'], ['бытовая химия', 'моющие средства', 'чистящие средства', 'household chemicals']),
            new BuyerArchetype('soap_detergent_manufacturer', 'Производитель мыла и моющих средств', 'chemical_products', 'Бытовая химия',
                ['производство мыла', 'завод моющих средств'], ['мыло', 'моющие средства', 'детергент', 'soap', 'detergent']),
            new BuyerArchetype('paint_coatings_manufacturer', 'Производитель лакокрасочных материалов', 'industrial_chemistry', 'Промышленная химия',
                ['производство лакокрасочных материалов', 'производитель красок и покрытий'], ['лакокрас', 'краски', 'покрытия', 'coatings']),
            new BuyerArchetype('polymer_resin_manufacturer', 'Производитель полимеров и смол', 'industrial_chemistry', 'Промышленная химия',
                ['производство полимеров и смол', 'производитель синтетических смол'], ['полимер', 'смолы', 'алкид', 'resin', 'polymer']),
        ];
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_map(fn (BuyerArchetype $archetype): string => $archetype->code, $this->all());
    }

    public function find(string $code): ?BuyerArchetype
    {
        return collect($this->all())->first(fn (BuyerArchetype $archetype): bool => $archetype->code === $code);
    }

    /** @return list<BuyerArchetype> */
    public function forSegment(string $segmentCode): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (BuyerArchetype $archetype): bool => $archetype->segmentCode === $segmentCode,
        ));
    }

    public function hash(): string
    {
        return AiCanonicalJson::hash([
            'version' => self::VERSION,
            'archetypes' => array_map(fn (BuyerArchetype $item): array => $item->hashPayload(), $this->all()),
        ]);
    }
}
