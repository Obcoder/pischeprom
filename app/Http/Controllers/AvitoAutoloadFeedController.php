<?php

namespace App\Http\Controllers;

use App\Domain\Avito\Exceptions\AvitoException;
use App\Models\AvitoAutoloadFeed;
use App\Models\AvitoPublicationMedia;
use App\Models\AvitoPublicationRevision;
use App\Services\Avito\AvitoPublicationService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AvitoAutoloadFeedController extends Controller
{
    public function show(
        AvitoAutoloadFeed $feed,
        string $token,
        AvitoPublicationService $publications,
    ): Response {
        try {
            $publications->authenticateFeed($feed, $token);
        } catch (AvitoException) {
            abort(404);
        }

        return response($publications->feedXml($feed), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function media(
        AvitoAutoloadFeed $feed,
        string $token,
        AvitoPublicationRevision $revision,
        AvitoPublicationMedia $media,
        AvitoPublicationService $publications,
    ): StreamedResponse {
        try {
            $media = $publications->publicMedia($feed, $token, $revision, $media);
        } catch (AvitoException) {
            abort(404);
        }

        $disk = Storage::disk($media->disk);
        if (! $disk->exists($media->path)) {
            abort(404);
        }

        return $disk->response($media->path, $media->file_name, [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
