<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HomeBanner;
use App\Rules\HomeBannerUrl;
use App\Services\HomeBanners\HomeBannerFeedService;
use App\Services\HomeBanners\HomeBannerPublicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function store(Request $request, HomeBannerPublicationService $publication): JsonResponse
    {
        $banner = $publication->save($this->validated($request));

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

    public function update(Request $request, HomeBanner $homeBanner, HomeBannerPublicationService $publication): JsonResponse
    {
        $homeBanner = $publication->save($this->validated($request, updating: true), $homeBanner);

        return response()->json([
            'data' => $homeBanner->fresh($this->relations()),
        ]);
    }

    public function destroy(HomeBanner $homeBanner, HomeBannerPublicationService $publication): JsonResponse
    {
        $publication->delete($homeBanner);

        return response()->json([
            'message' => 'Banner deleted',
        ]);
    }

    private function validated(Request $request, bool $updating = false): array
    {
        $data = $request->validate([
            'title' => [$updating ? 'sometimes' : 'required', 'required', 'string', 'max:255'],
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048', new HomeBannerUrl],
            'mobile_image_url' => ['nullable', 'string', 'max:2048', new HomeBannerUrl],
            'cta_label' => ['nullable', 'string', 'max:255'],
            'cta_url' => ['nullable', 'string', 'max:2048', new HomeBannerUrl],
            'good_id' => ['nullable', 'integer', 'exists:goods,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'size' => ['sometimes', 'required', 'string', Rule::in(HomeBanner::SIZES)],
            'is_published' => ['sometimes', 'required', 'boolean'],
            'show_on_desktop' => ['sometimes', 'required', 'boolean'],
            'show_on_mobile' => ['sometimes', 'required', 'boolean'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:999999'],
            'background_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'text_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'slot_number' => ['nullable', 'integer', 'between:1,6'],
            'content_mode' => ['sometimes', 'required', Rule::in(['image', 'overlay', 'text'])],
            'image_fit' => ['sometimes', 'required', Rule::in(['contain', 'cover'])],
            'mobile_image_fit' => ['sometimes', 'required', Rule::in(['contain', 'cover'])],
            'image_position' => ['sometimes', 'required', Rule::in(HomeBanner::POSITIONS)],
            'mobile_image_position' => ['sometimes', 'required', Rule::in(HomeBanner::POSITIONS)],
            'text_align' => ['sometimes', 'required', Rule::in(['left', 'center', 'right'])],
            'vertical_align' => ['sometimes', 'required', Rule::in(['top', 'center', 'bottom'])],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'open_in_new_tab' => ['sometimes', 'required', 'boolean'],
        ]);

        foreach (['is_published', 'show_on_desktop', 'show_on_mobile', 'open_in_new_tab'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $request->boolean($field);
            }
        }

        foreach (['good_id', 'product_id', 'category_id'] as $field) {
            if (array_key_exists($field, $data) && empty($data[$field])) {
                $data[$field] = null;
            }
        }

        foreach (['starts_at', 'ends_at'] as $field) {
            if (isset($data[$field])) {
                // The editor uses Moscow time; explicit ISO offsets remain authoritative.
                // Store in the application's timezone to preserve existing Eloquent dates.
                $data[$field] = Carbon::parse($data[$field], HomeBannerFeedService::TIMEZONE)
                    ->setTimezone(config('app.timezone'));
            }
        }

        return $data;
    }

    private function relations(): array
    {
        return HomeBannerFeedService::relations();
    }
}
