<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('building_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        DB::table('building_types')->insert(
            collect(['офис', 'склад', 'производство', 'цех', 'завод'])
                ->map(fn ($name) => [
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all()
        );

        Schema::table('buildings', function (Blueprint $table) {
            $table->foreignId('building_type_id')
                ->nullable()
                ->after('city_id')
                ->constrained('building_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('building_type_id');
        });

        Schema::dropIfExists('building_types');
    }
};
