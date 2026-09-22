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
use App\Services\Mail\UnitWebsiteResearchService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

class MailMessageResearchController extends Controller
{
    public function __construct(
        private readonly MailWorkspaceAccess $access,
        private readonly UnitWebsiteResearchService $unitResearch,
    ) {}

    public function index(Request $request, MailMessage $mailMessage, MailWebsiteCatalogAi $ai): JsonResponse
    {
        $this->access->authorize($request->user());

        return $this->json([
            'data' => MailMessageResearch::query()->where('mail_message_id', $mailMessage->id)
                ->with('unitCopies.unit')->latest()->limit(30)->get()->map(fn ($record) => $this->unitResearch->researchPayload($record)),
            'linked_units' => $this->unitResearch->linkedUnits($mailMessage),
            'availability' => [
                'website' => $ai->availability(),
                'company' => ['available' => (bool) config('services.dadata.token'), 'message' => config('services.dadata.token') ? null : 'Поиск юрлиц через DaData не настроен.'],
            ],
        ]);
    }

    public function website(Request $request, MailMessage $mailMessage, MailPublicWebsiteReader $reader, MailWebsiteCatalogAi $ai): JsonResponse
    {
        $this->access->authorize($request->user());
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
        ]);
        $unitId = isset($data['unit_id']) ? (int) $data['unit_id'] : null;
        if ($unitId !== null) {
            $this->unitResearch->requireLinkedUnit($mailMessage, $unitId);
        }
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
            }, $unitId);
        } catch (MailResearchException $exception) {
            return $this->json(['message' => $exception->getMessage()], $exception->status);
        }
    }

    public function saveToUnit(Request $request, MailMessage $mailMessage, MailMessageResearch $research): JsonResponse
    {
        $this->access->authorize($request->user());
        abort_unless((int) $research->mail_message_id === (int) $mailMessage->id, 404);
        $data = $request->validate(['unit_id' => ['required', 'integer', 'exists:units,id']]);

        return $this->researchResponse($request, $mailMessage, $research, true, (int) $data['unit_id']);
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

    private function run(Request $request, MailMessage $message, string $kind, string $query, callable $research, ?int $unitId = null): JsonResponse
    {
        $identity = ['mail_message_id' => $message->id, 'kind' => $kind, 'input_hash' => hash('sha256', $kind === 'company' ? mb_strtolower($query) : $query)];
        $existing = MailMessageResearch::query()->where($identity)->first();
        if ($existing && $existing->updated_at->greaterThan(now()->subDay())) {
            return $this->researchResponse($request, $message, $existing, true, $unitId);
        }
        $lock = Cache::lock('mail-research:'.$message->id.':'.$kind, 90);
        if (! $lock->get()) {
            return $this->json(['message' => 'Исследование уже выполняется. Подождите завершения.'], 429);
        }
        try {
            $existing = MailMessageResearch::query()->where($identity)->first();
            if ($existing && $existing->updated_at->greaterThan(now()->subDay())) {
                return $this->researchResponse($request, $message, $existing, true, $unitId);
            }
            $record = MailMessageResearch::query()->updateOrCreate($identity, [
                'query' => $query, 'user_id' => $request->user()->id, 'result' => $research(),
            ]);

            return $this->researchResponse($request, $message, $record, false, $unitId);
        } catch (MailResearchException $exception) {
            return $this->json(['message' => $exception->getMessage()], $exception->status);
        } catch (ValidationException|AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable) {
            return $this->json(['message' => 'Не удалось получить данные. Повторите запрос позже.'], 503);
        } finally {
            $lock->release();
        }
    }

    private function researchResponse(Request $request, MailMessage $message, MailMessageResearch $research, bool $cached, ?int $unitId): JsonResponse
    {
        $saved = [];
        if ($unitId !== null) {
            $copy = $this->unitResearch->save($message, $research, $unitId, $request->user());
            $saved = [
                'saved_to_unit' => $this->unitResearch->unitPayload($copy->unit->id, $copy->unit->name),
                'unit_research_id' => $copy->id,
            ];
            $research->unsetRelation('unitCopies');
        }

        return $this->json(['data' => $this->unitResearch->researchPayload($research), 'cached' => $cached, ...$saved]);
    }

    private function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, ['Cache-Control' => 'private, no-store']);
    }
}
