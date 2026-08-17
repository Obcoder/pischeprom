<?php

namespace App\Domain\AiSales\FindBuyers;

final class FindBuyersCriteriaRegistry
{
    private const ACTIVITIES = [
        'food_manufacturing' => 'Пищевое производство',
        'ingredient_processing' => 'Переработка ингредиентов',
        'food_service' => 'Общественное питание',
        'wholesale_distribution' => 'Оптовая дистрибуция',
        'retail' => 'Розничная торговля',
    ];

    private const COMPANY_TYPES = [
        'manufacturer' => 'Производитель',
        'processor' => 'Переработчик',
        'distributor' => 'Дистрибьютор',
        'wholesaler' => 'Оптовый покупатель',
        'retailer' => 'Розничная сеть',
        'food_service_operator' => 'Оператор общественного питания',
    ];

    /** @return list<string> */
    public function activityCodes(): array
    {
        return array_keys(self::ACTIVITIES);
    }

    /** @return list<string> */
    public function companyTypeCodes(): array
    {
        return array_keys(self::COMPANY_TYPES);
    }

    /** @param list<string> $codes
     * @return list<string>
     */
    public function activityLabels(array $codes): array
    {
        return collect($codes)->unique()->map(fn (string $code): ?string => self::ACTIVITIES[$code] ?? null)
            ->filter()->values()->all();
    }

    public function companyTypeLabel(?string $code): ?string
    {
        return $code === null ? null : (self::COMPANY_TYPES[$code] ?? null);
    }

    /** @return array<string, list<array{code: string, label: string}>> */
    public function options(): array
    {
        $format = static fn (array $values): array => collect($values)
            ->map(fn (string $label, string $code): array => ['code' => $code, 'label' => $label])
            ->values()->all();

        return [
            'activities' => $format(self::ACTIVITIES),
            'company_types' => $format(self::COMPANY_TYPES),
        ];
    }
}
