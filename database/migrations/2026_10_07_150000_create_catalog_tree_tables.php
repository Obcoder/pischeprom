<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('entity_type', 20)->default('custom');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('catalog_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('level_id')->constrained('catalog_levels')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('catalog_nodes')->restrictOnDelete();
            $table->string('entity_type', 20)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('import_key', 120)->nullable()->unique();
            $table->boolean('is_manual')->default(false);
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('image', 2048)->nullable();
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('properties')->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('catalog_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('level_id')->constrained('catalog_levels')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('label');
            $table->string('type', 20)->default('text');
            $table->boolean('required')->default(false);
            $table->boolean('is_public')->default(false);
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['level_id', 'key']);
        });

        $now = now();
        $levels = [];
        foreach (['category' => 'Категория', 'product' => 'Продукт', 'good' => 'Товар'] as $type => $name) {
            $levels[$type] = DB::table('catalog_levels')->insertGetId([
                'name' => $name, 'entity_type' => $type, 'sort_order' => count($levels),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $insert = function (string $type, object $source, ?int $parent, string $key) use ($levels, $now): int {
            $name = $type === 'product' ? $source->rus : $source->name;

            return DB::table('catalog_nodes')->insertGetId([
                'level_id' => $levels[$type], 'parent_id' => $parent, 'entity_type' => $type,
                'entity_id' => $source->id, 'import_key' => $key, 'name' => $name,
                'slug' => $source->slug ?? Str::slug($name), 'is_published' => (bool) ($source->is_published ?? false),
                'is_featured' => $type === 'category' ? ($source->is_featured ?? false) : false,
                'sort_order' => $source->sort_order ?? 0, 'created_at' => $now, 'updated_at' => $now,
            ]);
        };

        $categories = [];
        foreach (DB::table('categories')->orderBy('id')->get() as $source) {
            $categories[$source->id] = $insert('category', $source, null, "category:{$source->id}");
        }
        $products = [];
        foreach (DB::table('products')->orderBy('id')->get() as $source) {
            $products[$source->id] = $insert('product', $source, $categories[$source->category_id] ?? null, "product:{$source->id}");
        }
        $links = DB::table('good_product')->get()->groupBy('good_id');
        foreach (DB::table('goods')->orderBy('id')->get() as $source) {
            $parents = ($links[$source->id] ?? collect())->pluck('product_id')->unique()->filter(fn ($id) => isset($products[$id]));
            if ($parents->isEmpty()) {
                $insert('good', $source, null, "good:{$source->id}:root");
            } else {
                foreach ($parents as $productId) {
                    $insert('good', $source, $products[$productId], "good:{$source->id}:product:{$productId}");
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_fields');
        Schema::dropIfExists('catalog_nodes');
        Schema::dropIfExists('catalog_levels');
    }
};
