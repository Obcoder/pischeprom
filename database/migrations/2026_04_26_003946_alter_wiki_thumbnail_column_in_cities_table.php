<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE cities MODIFY wiki TEXT NULL');
        DB::statement('ALTER TABLE cities MODIFY wiki_thumbnail TEXT NULL');
        DB::statement('ALTER TABLE cities MODIFY yandexmapsgeo TEXT NULL');
        DB::statement('ALTER TABLE cities MODIFY twogis TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cities MODIFY wiki VARCHAR(255) NULL');
        DB::statement('ALTER TABLE cities MODIFY wiki_thumbnail VARCHAR(255) NULL');
        DB::statement('ALTER TABLE cities MODIFY yandexmapsgeo VARCHAR(255) NULL');
        DB::statement('ALTER TABLE cities MODIFY twogis VARCHAR(255) NULL');
    }
};
