<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HomeBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HomeBannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $published = $request->input('published');

        $query = HomeBanner::query()
            ->with($this->relations())
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($published !== null && $published !== '', function ($query) use ($published): void {
                $query->where('is_published', filter_var($published, FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('sort_order')
            ->orderByDesc('id');

        $perPage = min(max((int) $request->integer('per_page', 100), 1), 300);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $banner = HomeBanner::query()->create($this->validated($request));

        return response()->json([
            'data' => $banner->fresh($this->relations()),
        ], 201);
    }

    public function show(HomeBanner $homeBanner): JsonResponse
    {
        return response()->json([
            'data' => $homeBanner->load($this->relations()),
        ]);
    }

    public function update(Request $request, HomeBanner $homeBanner): JsonResponse
    {
        $homeBanner->update($this->validated($request));

        return response()->json([
            'data' => $homeBanner->fresh($this->relations()),
        ]);
    }

    public function destroy(HomeBanner $homeBanner): JsonResponse
    {
        $homeBanner->delete();

        return response()->json([
            'message' => 'Banner deleted',
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'mobile_image_url' => ['nullable', 'string', 'max:2048'],
            'cta_label' => ['nullable', 'string', 'max:255'],
            'cta_url' => ['nullable', 'string', 'max:2048'],
            'good_id' => ['nullable', 'integer', 'exists:goods,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'size' => ['required', 'string', Rule::in(HomeBanner::SIZES)],
            'is_published' => ['nullable', 'boolean'],
            'show_on_desktop' => ['nullable', 'boolean'],
            'show_on_mobile' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'background_color' => ['nullable', 'string', 'max:32'],
            'text_color' => ['nullable', 'string', 'max:32'],
            'accent_color' => ['nullable', 'string', 'max:32'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        foreach (['is_published', 'show_on_desktop', 'show_on_mobile'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->boolean($field);
            }
        }

        foreach (['good_id', 'product_id', 'category_id'] as $field) {
            if (empty($data[$field])) {
                $data[$field] = null;
            }
        }

        $data['sort_order'] = $data['sort_order'] ?? 500;

        return $data;
    }

    private function relations(): array
    {
        return [
            'good:id,name,slug,ava_thumb,ava_image',
            'product:id,rus,eng,category_id',
            'product.category:id,name,slug',
            'category:id,name,slug',
        ];
    }
}
