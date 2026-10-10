<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CatalogLanding;
use App\Models\CatalogNode;
use App\Services\Catalog\CatalogLandingTemplates;
use App\Services\Catalog\PublicCatalogService;
use App\Services\Catalog\PublicClassPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class CatalogLandingPreviewController extends Controller
{
    public function show(Request $request, CatalogNode $node, PublicCatalogService $catalog, PublicClassPage $pages): Response
    {
        abort_if($node->entity_type === 'good', 404);
        $landing = CatalogLanding::where('catalog_node_id', $node->id)->first();
        $content = $landing?->draft_content ?? app(CatalogLandingTemplates::class)->legacyForNode($node)['content'] ?? null;
        $page = $catalog->previewPage($node->id);
        abort_unless($page && $content, 404);
        $classPage = $pages->fromContent($page, $content, preview: true);

        return Inertia::render('Catalog/Show', [
            ...$page,
            'classPage' => $classPage,
            'seo' => $classPage['seo'],
        ])->toResponse($request)->withHeaders([
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
