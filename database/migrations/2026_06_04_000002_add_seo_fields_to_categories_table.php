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
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'slug')) {
                $table->string('slug')->nullable()->after('name')->unique();
            }

            if (!Schema::hasColumn('categories', 'h1')) {
                $table->string('h1')->nullable()->after('slug');
            }

            if (!Schema::hasColumn('categories', 'short_description')) {
                $table->text('short_description')->nullable()->after('h1');
            }

            if (!Schema::hasColumn('categories', 'description')) {
                $table->longText('description')->nullable()->after('short_description');
            }

            if (!Schema::hasColumn('categories', 'seo_text')) {
                $table->longText('seo_text')->nullable()->after('description');
            }

            if (!Schema::hasColumn('categories', 'meta_title')) {
                $table->string('meta_title')->nullable()->after('seo_text');
            }

            if (!Schema::hasColumn('categories', 'meta_description')) {
                $table->text('meta_description')->nullable()->after('meta_title');
            }

            if (!Schema::hasColumn('categories', 'keywords')) {
                $table->json('keywords')->nullable()->after('meta_description');
            }

            if (!Schema::hasColumn('categories', 'robots')) {
                $table->string('robots', 50)->default('index,follow')->after('keywords');
            }

            if (!Schema::hasColumn('categories', 'canonical_url')) {
                $table->string('canonical_url', 2048)->nullable()->after('robots');
            }

            if (!Schema::hasColumn('categories', 'og_title')) {
                $table->string('og_title')->nullable()->after('canonical_url');
            }

            if (!Schema::hasColumn('categories', 'og_description')) {
                $table->text('og_description')->nullable()->after('og_title');
            }

            if (!Schema::hasColumn('categories', 'og_image')) {
                $table->string('og_image', 2048)->nullable()->after('og_description');
            }

            if (!Schema::hasColumn('categories', 'image_alt')) {
                $table->string('image_alt')->nullable()->after('image');
            }

            if (!Schema::hasColumn('categories', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('is_published')->index();
            }

            if (!Schema::hasColumn('categories', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(500)->after('is_featured')->index();
            }

            if (!Schema::hasColumn('categories', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('sort_order')->index();
            }
        });

        $used = [];

        DB::table('categories')
            ->select(['id', 'name', 'slug'])
            ->orderBy('id')
            ->get()
            ->each(function ($category) use (&$used): void {
                $current = trim((string) $category->slug);
                $base = Str::slug($current !== '' ? $current : $category->name);

                if ($base === '') {
                    $base = 'category-' . $category->id;
                }

                $slug = $base;
                $index = 2;

                while (isset($used[$slug])) {
                    $slug = "{$base}-{$index}";
                    $index++;
                }

                $used[$slug] = true;

                DB::table('categories')
                    ->where('id', $category->id)
                    ->update([
                        'slug' => $slug,
                        'h1' => DB::raw('COALESCE(h1, name)'),
                        'meta_title' => DB::raw("COALESCE(meta_title, CONCAT(name, ' - товары и предложения пищевой промышленности'))"),
                        'robots' => DB::raw("COALESCE(robots, 'index,follow')"),
                        'published_at' => DB::raw('COALESCE(published_at, created_at)'),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            foreach ([
                'published_at',
                'sort_order',
                'is_featured',
                'image_alt',
                'og_image',
                'og_description',
                'og_title',
                'canonical_url',
                'robots',
                'keywords',
                'meta_description',
                'meta_title',
                'seo_text',
                'description',
                'short_description',
                'h1',
                'slug',
            ] as $column) {
                if (Schema::hasColumn('categories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
