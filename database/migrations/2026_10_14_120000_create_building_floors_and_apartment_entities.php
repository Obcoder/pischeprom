<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('building_floors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('number');
            $table->string('name', 80)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['building_id', 'number']);
        });
        if (DB::getDriverName() === 'sqlite') {
            // SQLite supports an inline nullable FK without rebuilding a table
            // that is already referenced by delivery-address pivots.
            DB::statement('ALTER TABLE apartments ADD COLUMN building_floor_id INTEGER REFERENCES building_floors(id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE apartments ADD COLUMN notes TEXT NULL');
        } else {
            Schema::table('apartments', function (Blueprint $table): void {
                $table->foreignId('building_floor_id')->nullable()->constrained('building_floors')->restrictOnDelete();
                $table->text('notes')->nullable();
            });
        }
        Schema::create('apartment_entity', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->restrictOnDelete();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['apartment_id', 'entity_id']);
            $table->index(['entity_id', 'apartment_id']);
        });
        // Copy only explicit, valid room selections. Never guess a room or floor.
        DB::table('building_entities as be')->join('apartments as a', 'a.id', '=', 'be.apartment_id')
            ->whereColumn('a.building_id', 'be.building_id')->select(['be.id', 'be.apartment_id', 'be.entity_id'])
            ->orderBy('be.id')->chunkById(500, function ($rows): void {
                DB::table('apartment_entity')->insertOrIgnore($rows->map(fn ($row) => [
                    'apartment_id' => $row->apartment_id, 'entity_id' => $row->entity_id,
                    'created_at' => now(), 'updated_at' => now(),
                ])->all());
            }, 'be.id', 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('apartment_entity');
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE apartments DROP COLUMN building_floor_id');
            DB::statement('ALTER TABLE apartments DROP COLUMN notes');
        } else {
            Schema::table('apartments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('building_floor_id');
                $table->dropColumn('notes');
            });
        }
        Schema::dropIfExists('building_floors');
    }
};
