<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CatalogNode;
use App\Services\Catalog\PublicCatalogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function show(CatalogNode $node, PublicCatalogService $catalog, ?string $slug = null): Response|RedirectResponse
    {
        $page = $catalog->page($node->id);
        abort_unless($page, 404);

        if ($slug !== $page['node']['slug']) {
            return redirect()->to($page['node']['public_url'], 301);
        }

        return Inertia::render('Catalog/Show', $page);
    }
}
