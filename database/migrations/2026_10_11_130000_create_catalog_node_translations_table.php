<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_node_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('node_id')->constrained('catalog_nodes')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name');
            $table->timestamps();
            $table->unique(['node_id', 'locale']);
        });

        // Keep this mapping local so future application changes cannot alter this backfill.
        $columns = [
            'en' => 'eng', 'zh' => 'zh', 'es' => 'es', 'ar' => 'ar', 'hi' => 'hi',
            'ur' => 'ur', 'de' => 'de', 'fr' => 'fr', 'pt' => 'po', 'it' => 'it',
            'nl' => 'nl', 'tr' => 'tu', 'fa' => 'fa', 'vi' => 'vi', 'ja' => 'ja',
            'ko' => 'ko', 'he' => 'he', 'id' => 'idn',
        ];
        DB::table('catalog_nodes')->join('products', 'products.id', '=', 'catalog_nodes.entity_id')
            ->where('catalog_nodes.entity_type', 'product')
            ->select(['catalog_nodes.id as node_id', ...array_map(fn ($column) => 'products.'.$column, array_values($columns))])
            ->orderBy('catalog_nodes.id')->chunkById(250, function ($nodes) use ($columns): void {
                $rows = [];
                $now = now();
                foreach ($nodes as $node) {
                    foreach ($columns as $locale => $column) {
                        $name = trim((string) $node->{$column});
                        if ($name !== '') {
                            $rows[] = ['node_id' => $node->node_id, 'locale' => $locale, 'name' => $name, 'created_at' => $now, 'updated_at' => $now];
                        }
                    }
                }
                foreach (array_chunk($rows, 250) as $chunk) {
                    DB::table('catalog_node_translations')->insert($chunk);
                }
            }, 'catalog_nodes.id', 'node_id');
    }

    public function down(): void
    {
        // Product keeps a compatible copy. Other entity types have nowhere to
        // retain their names in the previous schema; refuse a destructive rollback.
        $hasUnrepresentableNames = DB::table('catalog_node_translations')
            ->join('catalog_nodes', 'catalog_nodes.id', '=', 'catalog_node_translations.node_id')
            ->leftJoin('products', function ($join): void {
                $join->on('products.id', '=', 'catalog_nodes.entity_id')->where('catalog_nodes.entity_type', 'product');
            })->whereNull('products.id')->exists();
        if ($hasUnrepresentableNames) {
            throw new RuntimeException('Cannot roll back catalog translations while non-product entries contain translated names. Export or migrate those names first.');
        }
        Schema::dropIfExists('catalog_node_translations');
    }
};
