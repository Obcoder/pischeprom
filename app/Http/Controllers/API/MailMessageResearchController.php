<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\EntityLookupController;
use App\Models\MailMessage;
use App\Models\MailMessageResearch;
use App\Services\Mail\MailPublicWebsiteReader;
use App\Services\Mail\MailResearchException;
use App\Services\Mail\MailWebsiteCatalogAi;
use App\Services\Mail\MailWorkspaceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class MailMessageResearchController extends Controller
{
    public function __construct(private readonly MailWorkspaceAccess $access) {}

    public function index(Request $request, MailMessage $mailMessage, MailWebsiteCatalogAi $ai): JsonResponse
    {
        $this->access->authorize($request->user());

        return $this->json([
            'data' => MailMessageResearch::query()->where('mail_message_id', $mailMessage->id)->latest()->limit(30)->get(),
            'availability' => [
                'website' => $ai->availability(),
                'company' => ['available' => (bool) config('services.dadata.token'), 'message' => config('services.dadata.token') ? null : 'Поиск юрлиц через DaData не настроен.'],
            ],
        ]);
    }

    public function website(Request $request, MailMessage $mailMessage, MailPublicWebsiteReader $reader, MailWebsiteCatalogAi $ai): JsonResponse
    {
        $this->access->authorize($request->user());
        $data = $request->validate(['url' => ['required', 'string', 'max:2048']]);
        try {
            $url = $reader->normalize($data['url']);

            return $this->run($request, $mailMessage, 'website', $url, function () use ($reader, $ai, $url): array {
                if (! $ai->availability()['available']) {
                    throw new MailResearchException('AI-сканирование каталога не настроено.', 503);
                }
                $source = $reader->read($url);
                $result = $ai->extract($source['pages']);

                return [
                    ...$result,
                    'pages' => array_map(static fn (array $page): array => ['url' => $page['url'], 'title' => $page['title']], $source['pages']),
                    'warnings' => array_values(array_unique([...$source['warnings'], ...$result['warnings']])),
                    'partial' => true,
                ];
            });
        } catch (MailResearchException $exception) {
            return $this->json(['message' => $exception->getMessage()], $exception->status);
        }
    }

    public function company(Request $request, MailMessage $mailMessage, EntityLookupController $lookup): JsonResponse
    {
        $this->access->authorize($request->user());
        $data = $request->validate(['query' => ['required', 'string', 'min:2', 'max:255']]);
        $query = trim(preg_replace('/\s+/u', ' ', $data['query']));
        $request->merge(['query' => $query]);

        return $this->run($request, $mailMessage, 'company', $query, function () use ($request, $lookup, $query): array {
            if (! config('services.dadata.token')) {
                throw new MailResearchException('Поиск юрлиц через DaData не настроен.', 503);
            }
            // Reuse the existing registry mapping used by the Entity editor.
            $result = $lookup->lookup($request)->getData(true);

            return ['companies' => $result['data'] ?? [], 'source' => 'DaData', 'query' => $query];
        });
    }

    private function run(Request $request, MailMessage $message, string $kind, string $query, callable $research): JsonResponse
    {
        $identity = ['mail_message_id' => $message->id, 'kind' => $kind, 'input_hash' => hash('sha256', $kind === 'company' ? mb_strtolower($query) : $query)];
        $existing = MailMessageResearch::query()->where($identity)->first();
        if ($existing && $existing->updated_at->greaterThan(now()->subDay())) {
            return $this->json(['data' => $existing, 'cached' => true]);
        }
        $lock = Cache::lock('mail-research:'.$message->id.':'.$kind, 90);
        if (! $lock->get()) {
            return $this->json(['message' => 'Исследование уже выполняется. Подождите завершения.'], 429);
        }
        try {
            $existing = MailMessageResearch::query()->where($identity)->first();
            if ($existing && $existing->updated_at->greaterThan(now()->subDay())) {
                return $this->json(['data' => $existing, 'cached' => true]);
            }
            $record = MailMessageResearch::query()->updateOrCreate($identity, [
                'query' => $query, 'user_id' => $request->user()->id, 'result' => $research(),
            ]);

            return $this->json(['data' => $record, 'cached' => false]);
        } catch (MailResearchException $exception) {
            return $this->json(['message' => $exception->getMessage()], $exception->status);
        } catch (Throwable) {
            return $this->json(['message' => 'Не удалось получить данные. Повторите запрос позже.'], 503);
        } finally {
            $lock->release();
        }
    }

    private function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, ['Cache-Control' => 'private, no-store']);
    }
}
