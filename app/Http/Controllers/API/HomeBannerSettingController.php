<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HomeBannerSetting;
use App\Services\HomeBanners\HomeBannerFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HomeBannerSettingController extends Controller
{
    public function show(HomeBannerFeedService $feed): JsonResponse
    {
        HomeBannerSetting::singleton();

        return $this->response($feed);
    }

    public function update(Request $request, HomeBannerFeedService $feed): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'required', 'boolean'],
            'desktop_height' => ['sometimes', 'required', 'integer', 'between:60,160'],
            'gap' => ['sometimes', 'required', 'integer', 'between:4,20'],
            'mobile_enabled' => ['sometimes', 'required', 'boolean'],
            'mobile_layout' => ['sometimes', 'required', Rule::in(['scroll', 'grid'])],
            'mobile_height' => ['sometimes', 'required', 'integer', 'between:60,160'],
            'mobile_columns' => ['sometimes', 'required', 'integer', Rule::in([1, 2])],
            'mobile_hide_empty' => ['sometimes', 'required', 'boolean'],
            'mobile_order' => ['sometimes', 'required', 'array', 'size:6'],
            'mobile_order.*' => ['required', 'integer', 'between:1,6', 'distinct'],
        ]);

        if (isset($data['mobile_order'])) {
            $data['mobile_order'] = array_values(array_map('intval', $data['mobile_order']));
        }

        DB::transaction(function () use ($data): void {
            HomeBannerSetting::singleton();
            HomeBannerSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail()->update($data);
        });

        return $this->response($feed);
    }

    private function response(HomeBannerFeedService $feed): JsonResponse
    {
        $payload = $feed->build();

        return response()->json([
            'data' => $payload['settings'],
            'specification' => HomeBannerFeedService::specification(),
            'feed' => ['desktop' => $payload['desktop'], 'mobile' => $payload['mobile']],
        ]);
    }
}
