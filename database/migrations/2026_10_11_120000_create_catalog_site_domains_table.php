<?php

use App\Services\Catalog\CatalogHost;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_site_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_node_id')->constrained('catalog_nodes')->cascadeOnDelete();
            $table->string('hostname', 253)->unique();
            $table->timestamps();
        });

        // Never guess another site's owner. Bootstrap only an unambiguous food domain.
        $foodDomains = DB::table('catalog_nodes')->join('catalog_levels', 'catalog_levels.id', '=', 'catalog_nodes.level_id')
            ->where('catalog_levels.is_domain', true)->whereNull('catalog_nodes.parent_id')
            ->get(['catalog_nodes.id', 'catalog_nodes.name', 'catalog_nodes.slug'])
            ->filter(fn (object $node): bool => in_array(mb_strtolower(trim($node->name)), [
                'продукты пищевые', 'пищевые продукты', 'продукты питания', 'продукты', 'пищепром', 'пищепром-сервер',
            ], true) || in_array($node->slug, ['produkty-pishhevye', 'produkty-pishchevye', 'pishhevye-produkty', 'food'], true));
        if ($foodDomains->count() !== 1) {
            return;
        }
        $hosts = [parse_url((string) config('app.url'), PHP_URL_HOST), 'пищепром-сервер.рф', 'www.пищепром-сервер.рф'];
        foreach (array_unique(array_filter(array_map(fn ($host): ?string => CatalogHost::normalize((string) $host), $hosts))) as $host) {
            DB::table('catalog_site_domains')->insert([
                'catalog_node_id' => $foodDomains->first()->id, 'hostname' => $host,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_site_domains');
    }
};
