<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CatalogNode;
use App\Services\Catalog\PublicCatalogService;
use App\Services\Catalog\PublicClassPage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function show(CatalogNode $node, PublicCatalogService $catalog, ?string $slug = null): Response|RedirectResponse
    {
        $page = $catalog->page($node->id);
        abort_unless($page, 404);

        if (! $page['node']['offer_url'] || $slug !== $page['node']['slug']) {
            return redirect()->to($page['node']['public_url'], 301);
        }

        return Inertia::render('Catalog/Show', $page);
    }

    public function path(string $path, PublicCatalogService $catalog, PublicClassPage $classes): Response|RedirectResponse
    {
        $page = $catalog->pageByPath($path);
        abort_unless($page, 404);

        if ($page['node']['offer_url']) {
            return redirect()->to($page['node']['offer_url'], 301);
        }

        $classPage = $classes->forCatalogPage($page);

        return Inertia::render('Catalog/Show', [
            ...$page,
            'classPage' => $classPage,
            'seo' => $classPage['seo'] ?? $page['seo'],
        ]);
    }
}
