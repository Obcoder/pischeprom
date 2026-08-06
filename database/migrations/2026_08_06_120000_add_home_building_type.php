<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existingId = DB::table('building_types')
            ->where('name', 'Домашний')
            ->value('id');

        if ($existingId) {
            DB::table('building_types')
                ->where('id', $existingId)
                ->update([
                    'name' => 'Домашний',
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('building_types')->insert([
            'name' => 'Домашний',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $typeId = DB::table('building_types')
            ->where('name', 'Домашний')
            ->value('id');

        if ($typeId && ! DB::table('buildings')->where('building_type_id', $typeId)->exists()) {
            DB::table('building_types')->where('id', $typeId)->delete();
        }
    }
};
