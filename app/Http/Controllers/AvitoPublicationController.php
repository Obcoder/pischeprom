<?php

namespace App\Http\Controllers;

use App\Domain\Avito\Exceptions\AvitoException;
use App\Models\AvitoAutoloadFeed;
use App\Models\AvitoConnection;
use App\Models\AvitoPublication;
use App\Models\Good;
use App\Services\Avito\AvitoAutoloadApiService;
use App\Services\Avito\AvitoPublicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AvitoPublicationController extends Controller
{
    public function index(Request $request, AvitoPublicationService $publications): JsonResponse
    {
        $validated = $request->validate($this->accountRules() + [
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', Rule::in(AvitoPublicationService::STATUSES)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        return response()->json($publications->index((int) $validated['account_id'], $validated));
    }

    public function feed(Request $request, AvitoPublicationService $publications): JsonResponse
    {
        $validated = $request->validate($this->accountRules());
        $feed = $publications->feed((int) $validated['account_id']);

        return response()->json([
            'feed' => $feed ? $publications->feedPayload($feed) : null,
        ]);
    }

    public function updateFeed(Request $request, AvitoPublicationService $publications): JsonResponse
    {
        $validated = $request->validate($this->accountRules() + [
            'connection_id' => ['nullable', 'integer', 'exists:avito_connections,id'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'manager_name' => ['nullable', 'string', 'max:100'],
            'report_email' => ['nullable', 'email:rfc', 'max:255'],
        ]);
        $accountId = (int) $validated['account_id'];
        $connection = $this->connection($validated['connection_id'] ?? null, $accountId);
        $feed = $publications->feedFor($accountId, $connection);

        return response()->json([
            'feed' => $publications->feedPayload(
                $publications->updateFeed($feed, $validated, $connection)
            ),
        ]);
    }

    public function categories(
        Request $request,
        AvitoAutoloadApiService $autoload,
    ): JsonResponse {
        $validated = $request->validate($this->accountRules() + [
            'connection_id' => ['nullable', 'integer', 'exists:avito_connections,id'],
        ]);

        return response()->json($autoload->categories(
            $this->connection($validated['connection_id'] ?? null, (int) $validated['account_id'])
        ));
    }

    public function categoryFields(
        Request $request,
        string $nodeSlug,
        AvitoAutoloadApiService $autoload,
    ): JsonResponse {
        $validated = $request->validate($this->accountRules() + [
            'connection_id' => ['nullable', 'integer', 'exists:avito_connections,id'],
        ]);

        return response()->json($autoload->categoryFields(
            $nodeSlug,
            $this->connection($validated['connection_id'] ?? null, (int) $validated['account_id'])
        ));
    }

    public function store(Request $request, AvitoPublicationService $publications): JsonResponse
    {
        $validated = $request->validate($this->accountRules() + [
            'good_id' => ['required', 'integer', 'exists:goods,id'],
            'connection_id' => ['nullable', 'integer', 'exists:avito_connections,id'],
        ]);
        $accountId = (int) $validated['account_id'];
        $publication = $publications->create(
            $accountId,
            Good::query()->findOrFail($validated['good_id']),
            $this->connection($validated['connection_id'] ?? null, $accountId),
        );

        return response()->json($publications->show($publication), 201);
    }

    public function show(
        Request $request,
        AvitoPublication $publication,
        AvitoPublicationService $publications,
    ): JsonResponse {
        $validated = $request->validate($this->accountRules());
        $this->assertPublicationAccount($publication, (int) $validated['account_id']);

        return response()->json($publications->show($publication));
    }

    public function update(
        Request $request,
        AvitoPublication $publication,
        AvitoPublicationService $publications,
    ): JsonResponse {
        $validated = $request->validate($this->draftRules());
        $this->assertPublicationAccount($publication, (int) $validated['account_id']);
        $this->assertCategoryFields((array) ($validated['category_fields'] ?? []));
        $connection = null;
        if (array_key_exists('connection_id', $validated)) {
            $connection = $this->connection($validated['connection_id'], (int) $validated['account_id']);
        }
        $publication = $publications->update($publication, $validated);
        if (array_key_exists('connection_id', $validated)) {
            $publications->updateFeed($publication->feed, [
                'connection_id' => $validated['connection_id'],
            ], $connection);
        }

        return response()->json($publications->show($publication));
    }

    public function preview(
        Request $request,
        AvitoPublication $publication,
        AvitoPublicationService $publications,
    ): JsonResponse {
        $validated = $request->validate($this->accountRules());
        $this->assertPublicationAccount($publication, (int) $validated['account_id']);

        return response()->json([
            'preview' => $publications->preview($publication),
            'xml' => $publications->previewXml($publication),
        ]);
    }

    public function approve(
        Request $request,
        AvitoPublication $publication,
        AvitoPublicationService $publications,
    ): JsonResponse {
        $validated = $request->validate($this->accountRules() + [
            'confirmed' => ['accepted'],
        ]);
        $this->assertPublicationAccount($publication, (int) $validated['account_id']);
        $revision = $publications->approve($publication);

        return response()->json([
            'message' => "Версия {$revision->version} зафиксирована и включена в feed.",
        ] + $publications->show($publication->fresh()));
    }

    public function checkProfile(Request $request, AvitoPublicationService $publications): JsonResponse
    {
        $validated = $request->validate($this->accountRules());

        return response()->json($publications->checkProfile(
            $this->requireFeed($publications, (int) $validated['account_id'])
        ));
    }

    public function attachProfile(Request $request, AvitoPublicationService $publications): JsonResponse
    {
        $validated = $request->validate($this->accountRules() + [
            'confirmed' => ['accepted'],
            'report_email' => ['required', 'email:rfc', 'max:255'],
            'autoload_enabled' => ['required', 'boolean'],
            'agreement' => ['sometimes', 'boolean'],
            'schedule' => ['present', 'array', 'max:24'],
            'schedule.*.rate' => ['required', 'integer', 'min:1', 'max:100000'],
            'schedule.*.weekdays' => ['required', 'array', 'min:1', 'max:7'],
            'schedule.*.weekdays.*' => ['required', 'integer', 'between:0,6', 'distinct'],
            'schedule.*.time_slots' => ['required', 'array', 'min:1', 'max:24'],
            'schedule.*.time_slots.*' => ['required', 'integer', 'between:0,23', 'distinct'],
        ]);

        return response()->json($publications->attachProfile(
            $this->requireFeed($publications, (int) $validated['account_id']),
            $validated,
        ));
    }

    public function upload(Request $request, AvitoPublicationService $publications): JsonResponse
    {
        $validated = $request->validate($this->accountRules() + [
            'confirmed' => ['accepted'],
        ]);

        return response()->json($publications->requestUpload(
            $this->requireFeed($publications, (int) $validated['account_id'])
        ));
    }

    public function uploadStatus(Request $request, AvitoPublicationService $publications): JsonResponse
    {
        $validated = $request->validate($this->accountRules());

        return response()->json($publications->remoteStatus(
            $this->requireFeed($publications, (int) $validated['account_id'])
        ));
    }

    public function sync(
        Request $request,
        AvitoPublication $publication,
        AvitoPublicationService $publications,
    ): JsonResponse {
        $validated = $request->validate($this->accountRules());
        $this->assertPublicationAccount($publication, (int) $validated['account_id']);

        return response()->json($publications->sync($publication));
    }

    public function archive(
        Request $request,
        AvitoPublication $publication,
        AvitoPublicationService $publications,
    ): JsonResponse {
        $validated = $request->validate($this->accountRules() + [
            'confirmed' => ['accepted'],
        ]);
        $this->assertPublicationAccount($publication, (int) $validated['account_id']);
        $publications->archive($publication);

        return response()->json([
            'message' => 'Публикация исключена из следующих feed. Это не снимает объявление на Avito.',
        ] + $publications->show($publication->fresh()));
    }

    private function draftRules(): array
    {
        return $this->accountRules() + [
            'connection_id' => ['nullable', 'integer', 'exists:avito_connections,id'],
            'category_node_slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9][a-z0-9_-]*$/i'],
            'category_name' => ['nullable', 'string', 'max:255'],
            'selected_fields' => ['sometimes', 'array', 'max:4'],
            'selected_fields.*' => [
                'required', 'string', Rule::in(AvitoPublicationService::SELECTABLE_FIELDS), 'distinct',
            ],
            'price_value_id' => ['nullable', 'integer', 'exists:good_price_type_values,id'],
            'media_ids' => ['sometimes', 'array', 'max:10'],
            'media_ids.*' => ['required', 'integer', 'exists:good_media,id', 'distinct'],
            'include_facts' => ['sometimes', 'boolean'],
            'title_override' => ['nullable', 'string', 'max:100'],
            'description_override' => ['nullable', 'string', 'max:7500'],
            'price_override' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'manager_name' => ['nullable', 'string', 'max:100'],
            'allow_email' => ['sometimes', 'boolean'],
            'ad_type' => ['nullable', 'string', 'max:200'],
            'condition' => ['nullable', 'string', 'max:200'],
            'listing_fee' => ['nullable', 'string', 'max:100'],
            'category_fields' => ['sometimes', 'array', 'max:120'],
            'category_schema' => ['sometimes', 'array', 'max:160'],
            'category_schema.*.key' => ['required', 'string', 'max:120'],
            'category_schema.*.label' => ['nullable', 'string', 'max:255'],
            'category_schema.*.description' => ['nullable', 'string', 'max:1000'],
            'category_schema.*.type' => ['nullable', 'string', 'max:80'],
            'category_schema.*.required' => ['sometimes', 'boolean'],
            'category_schema.*.multiple' => ['sometimes', 'boolean'],
            'category_schema.*.options' => ['sometimes', 'array', 'max:500'],
        ];
    }

    private function accountRules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'min:1'],
        ];
    }

    private function connection(?int $connectionId, int $accountId): ?AvitoConnection
    {
        if (! $connectionId) {
            return null;
        }
        $connection = AvitoConnection::query()->findOrFail($connectionId);
        if (filled($connection->external_user_id)
            && ctype_digit((string) $connection->external_user_id)
            && (int) $connection->external_user_id !== $accountId) {
            throw new AvitoException(
                'OAuth-подключение принадлежит другому аккаунту Avito.',
                'autoload_account_mismatch',
                422,
            );
        }

        return $connection;
    }

    private function requireFeed(
        AvitoPublicationService $publications,
        int $accountId,
    ): AvitoAutoloadFeed {
        return $publications->feed($accountId)
            ?? throw new AvitoException(
                'Сначала создайте черновик объявления или сохраните настройки feed.',
                'autoload_feed_missing',
                404,
            );
    }

    private function assertPublicationAccount(AvitoPublication $publication, int $accountId): void
    {
        if ($publication->avito_account_id !== $accountId) {
            throw new AvitoException('Публикация не найдена.', 'autoload_publication_missing', 404);
        }
    }

    private function assertCategoryFields(array $fields): void
    {
        foreach ($fields as $key => $value) {
            $validKey = is_string($key) && preg_match('/^[A-Za-z_][A-Za-z0-9_.-]{0,119}$/', $key);
            $values = is_array($value) ? $value : [$value];
            $validValue = count($values) <= 100
                && collect($values)->every(fn ($item): bool => ($item === null || is_scalar($item))
                    && mb_strlen((string) $item) <= 2000);
            if (! $validKey || ! $validValue) {
                throw ValidationException::withMessages([
                    'category_fields' => 'Дополнительные поля категории содержат некорректный XML-тег или значение.',
                ]);
            }
        }
    }
}
