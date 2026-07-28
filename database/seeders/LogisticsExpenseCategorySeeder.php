<?php

namespace Database\Seeders;

use App\Models\LogisticsExpenseCategory;
use Illuminate\Database\Seeder;

class LogisticsExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'fuel', 'name' => 'Топливо'],
            ['code' => 'toll_road', 'name' => 'Платные дороги'],
            ['code' => 'accommodation', 'name' => 'Проживание/ночлег'],
            ['code' => 'parking', 'name' => 'Парковка'],
            ['code' => 'ferry', 'name' => 'Паром'],
            ['code' => 'loading_unloading', 'name' => 'Погрузка/разгрузка'],
            ['code' => 'repair', 'name' => 'Ремонт в пути'],
            ['code' => 'per_diem', 'name' => 'Суточные'],
            ['code' => 'other', 'name' => 'Прочее'],
        ];

        foreach ($categories as $index => $category) {
            LogisticsExpenseCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ]
            );
        }
    }
}
