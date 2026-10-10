<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CatalogNode;
use App\Services\Catalog\CatalogLandingContent;
use App\Services\Catalog\CatalogLandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CatalogLandingController extends Controller
{
    public function __construct(private readonly CatalogLandingService $landings) {}

    public function show(CatalogNode $node): JsonResponse
    {
        return response()->json(['data' => $this->landings->editor($node)]);
    }

    public function update(Request $request, CatalogNode $node, CatalogLandingContent $content): JsonResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:0'], 'content' => ['required', 'array']]);
        $this->landings->save($node, $data['version'], $content->validate($data['content']), $request->user()?->id);

        return $this->show($node);
    }

    public function publish(Request $request, CatalogNode $node): JsonResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:0']]);
        $this->landings->publish($node, $data['version'], $request->user()?->id);

        return $this->show($node);
    }

    public function unpublish(Request $request, CatalogNode $node): JsonResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:0']]);
        $this->landings->unpublish($node, $data['version'], $request->user()?->id);

        return $this->show($node);
    }

    public function image(Request $request, CatalogNode $node): JsonResponse
    {
        $this->landings->ensureCatalogNode($node);
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120']]);
        $path = $request->file('image')->store('catalog-landings/'.$node->id, 'public');

        return response()->json(['url' => Storage::disk('public')->url($path)], 201);
    }
}
