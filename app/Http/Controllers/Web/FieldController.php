<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Field;
use App\Services\Catalog\CatalogSiteContext;
use App\Services\Catalog\PublicGoodsCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FieldController extends Controller
{
    public function show(
        Request $request,
        string $field,
        PublicGoodsCatalog $catalog,
        CatalogSiteContext $site,
    ): Response|RedirectResponse {
        $fieldModel = Field::query()
            ->published()
            ->where(function ($query) use ($field): void {
                $query->where('slug', $field);

                if (ctype_digit($field)) {
                    $query->orWhere('id', (int) $field);
                }
            })
            ->when($site->goodIds() !== null, fn ($query) => $query->whereHas('publishedGoods',
                fn ($goods) => $site->scopeGoods($goods)))
            ->firstOrFail();

        if ($fieldModel->slug && $field !== $fieldModel->slug) {
            return redirect()->route('public.fields.show', [
                ...$request->query(),
                'field' => $fieldModel->slug,
            ], 301);
        }

        return Inertia::render('Goods', [
            ...$catalog->page($request, $fieldModel),
            'field' => [
                'id' => $fieldModel->id,
                'title' => $fieldModel->title,
                'name' => $fieldModel->name,
                'slug' => $fieldModel->slug,
                'description' => $fieldModel->description,
                'goods_count' => $fieldModel->publishedGoods()->tap(fn ($query) => $site->scopeGoods($query))->count(),
                'public_url' => route('public.fields.show', $fieldModel->slug),
            ],
        ]);
    }
}
