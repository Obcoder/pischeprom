<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_levels', function (Blueprint $table): void {
            $table->string('display_mode', 20)->default('tree');
            $table->boolean('is_domain')->default(false);
        });
        Schema::table('catalog_nodes', function (Blueprint $table): void {
            $table->dropForeign(['level_id']);
            $table->unsignedBigInteger('level_id')->nullable()->change();
            $table->foreign('level_id')->references('id')->on('catalog_levels')->nullOnDelete();
            $table->json('properties_by_level')->nullable();
        });

        DB::table('catalog_levels')->where('entity_type', 'category')->update(['display_mode' => 'tabs']);
        DB::table('catalog_levels')->where('entity_type', 'good')->update(['display_mode' => 'list']);
        $nestedLevelIds = DB::table('catalog_nodes')->whereNotNull('parent_id')->distinct()->pluck('level_id')->all();
        $domains = DB::table('catalog_levels')->get()->filter(
            fn (object $level): bool => in_array(mb_strtolower(trim($level->name)), ['домен', 'домены'], true)
                && ! in_array($level->id, $nestedLevelIds, true),
        );
        if ($domains->isNotEmpty()) {
            DB::table('catalog_levels')->whereIn('id', $domains->pluck('id'))->update(['is_domain' => true, 'display_mode' => 'tabs']);
        } else {
            DB::table('catalog_levels')->insert([
                'name' => 'Домены', 'entity_type' => 'custom', 'sort_order' => 0,
                'display_mode' => 'tabs', 'is_domain' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // The old schema cannot represent unclassified entries. Refuse to drop
        // their classification data or archived properties during a rollback.
        if (DB::table('catalog_nodes')->whereNull('level_id')->exists()
            || DB::table('catalog_nodes')->whereNotNull('properties_by_level')->exists()) {
            throw new RuntimeException('Сначала сохраните резервную копию и назначьте уровни всем элементам каталога; откат удалит архив свойств.');
        }
        Schema::table('catalog_nodes', function (Blueprint $table): void {
            $table->dropForeign(['level_id']);
            $table->unsignedBigInteger('level_id')->nullable(false)->change();
            $table->foreign('level_id')->references('id')->on('catalog_levels')->restrictOnDelete();
            $table->dropColumn('properties_by_level');
        });
        Schema::table('catalog_levels', function (Blueprint $table): void {
            $table->dropColumn(['display_mode', 'is_domain']);
        });
    }
};
