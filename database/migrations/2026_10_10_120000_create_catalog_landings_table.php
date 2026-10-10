<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_landings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_node_id')->unique()->constrained('catalog_nodes')->cascadeOnDelete();
            $table->json('draft_content')->nullable();
            $table->json('published_content')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        foreach (config('product-pages.pages', []) as $productId => $settings) {
            if (! is_array($settings)) {
                continue;
            }
            $path = resource_path('landings/'.basename((string) ($settings['guide'] ?? '')).'.json');
            if (! is_file($path)) {
                continue;
            }
            // The configured node is an identity hint, never permission to attach
            // a legacy product landing to an unrelated record with the same ID.
            $query = DB::table('catalog_nodes')->where('entity_type', 'product')->where('entity_id', $productId);
            $node = (clone $query)->where('id', $settings['catalog_node_id'] ?? 0)->first()
                ?? $query->orderBy('id')->first();
            if (! $node) {
                continue;
            }
            $content = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $content['catalog']['source_product_ids'] = array_values($settings['source_product_ids'] ?? []);
            $content['catalog']['inline_good_ids'] = array_values($settings['inline_good_ids'] ?? []);
            $encoded = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            DB::table('catalog_landings')->insert([
                'catalog_node_id' => $node->id, 'draft_content' => $encoded, 'published_content' => $encoded,
                'version' => 1, 'published_at' => now(), 'activated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $seo = [];
            foreach (['meta_title' => 'title', 'meta_description' => 'description'] as $field => $setting) {
                if (blank($node->{$field}) && filled($settings[$setting] ?? null)) {
                    $seo[$field] = $settings[$setting];
                }
            }
            if ($seo !== []) {
                DB::table('catalog_nodes')->where('id', $node->id)->update($seo);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_landings');
    }
};
