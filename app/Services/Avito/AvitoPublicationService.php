<?php

namespace App\Services\Avito;

use App\Domain\Avito\Exceptions\AvitoException;
use App\Models\AvitoAutoloadFeed;
use App\Models\AvitoConnection;
use App\Models\AvitoPublication;
use App\Models\AvitoPublicationMedia;
use App\Models\AvitoPublicationRevision;
use App\Models\Good;
use App\Models\GoodMedia;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use XMLWriter;

class AvitoPublicationService
{
    public const STATUSES = [
        'draft', 'ready', 'publishing', 'published', 'warning', 'rejected', 'archived',
    ];

    public const SELECTABLE_FIELDS = ['title', 'description', 'price', 'images'];

    private const RESERVED_XML_FIELDS = [
        'Id', 'DateBegin', 'DateEnd', 'ListingFee', 'AdStatus', 'AllowEmail',
        'ManagerName', 'ContactPhone', 'Address', 'Category', 'Title',
        'Description', 'Price', 'Images',
    ];

    public function __construct(
        private readonly AvitoCrmOutboundService $goods,
        private readonly AvitoAutoloadApiService $autoload,
        private readonly AvitoListingGoodService $links,
    ) {}

    public function feedFor(int $accountId, ?AvitoConnection $connection = null): AvitoAutoloadFeed
    {
        $feed = AvitoAutoloadFeed::query()->firstOrCreate(
            ['avito_account_id' => $accountId],
            [
                'avito_connection_id' => $connection?->id,
                'name' => (string) config('avito.autoload.feed_name', 'ameise-goods'),
                'access_token' => Str::random(64),
                'profile_status' => 'not_checked',
                'defaults' => [],
            ],
        );

        if ($connection && $feed->avito_connection_id !== $connection->id) {
            $feed->update(['avito_connection_id' => $connection->id]);
        }

        return $feed->fresh();
    }

    public function feed(int $accountId): ?AvitoAutoloadFeed
    {
        return AvitoAutoloadFeed::query()->where('avito_account_id', $accountId)->first();
    }

    public function index(int $accountId, array $filters = []): array
    {
        $feed = $this->feed($accountId);
        $query = AvitoPublication::query()
            ->where('avito_account_id', $accountId)
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query
                ->where('status', $filters['status']))
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['search']);
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('external_id', 'like', "%{$search}%")
                        ->orWhere('category_name', 'like', "%{$search}%")
                        ->orWhereHas('good', fn (Builder $good) => $good->where('name', 'like', "%{$search}%"));
                });
            })
            ->with(['good:id,name,slug,is_published', 'currentRevision.media'])
            ->latest('updated_at');
        $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 50)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $items = $paginator->getCollection()
            ->map(fn (AvitoPublication $publication): array => $this->publicationSummary($publication))
            ->values()
            ->all();

        return [
            'items' => $items,
            'feed' => $feed ? $this->feedPayload($feed) : null,
            'status_counts' => AvitoPublication::query()
                ->where('avito_account_id', $accountId)
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(fn ($count): int => (int) $count)
                ->all(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    public function create(
        int $accountId,
        Good $good,
        ?AvitoConnection $connection = null,
    ): AvitoPublication {
        $feed = $this->feedFor($accountId, $connection);
        $good = $this->goods->prepareGood($good);
        $draft = $this->defaultDraft($good, $feed);
        $publication = AvitoPublication::query()->create([
            'avito_autoload_feed_id' => $feed->id,
            'good_id' => $good->id,
            'avito_connection_id' => $connection?->id,
            'avito_account_id' => $accountId,
            'external_id' => 'ameise-'.Str::lower((string) Str::ulid()),
            'status' => 'draft',
            'draft_dirty' => true,
            'draft_payload' => $draft,
        ]);

        return $publication->fresh();
    }

    public function update(AvitoPublication $publication, array $input): AvitoPublication
    {
        $this->assertEditable($publication);
        $draft = array_replace((array) $publication->draft_payload, Arr::only($input, [
            'selected_fields',
            'price_value_id',
            'media_ids',
            'include_facts',
            'title_override',
            'description_override',
            'price_override',
            'address',
            'contact_phone',
            'manager_name',
            'allow_email',
            'ad_type',
            'condition',
            'listing_fee',
            'category_fields',
            'category_schema',
        ]));
        $draft['selected_fields'] = collect((array) ($draft['selected_fields'] ?? []))
            ->filter(fn ($field): bool => in_array($field, self::SELECTABLE_FIELDS, true))
            ->unique()
            ->values()
            ->all();
        $draft['media_ids'] = collect((array) ($draft['media_ids'] ?? []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->take((int) config('avito.autoload.max_images', 10))
            ->values()
            ->all();
        $draft['category_fields'] = $this->sanitizeCategoryFields((array) ($draft['category_fields'] ?? []));

        $publication->update([
            'avito_connection_id' => array_key_exists('connection_id', $input)
                ? $input['connection_id']
                : $publication->avito_connection_id,
            'category_node_slug' => array_key_exists('category_node_slug', $input)
                ? $input['category_node_slug']
                : $publication->category_node_slug,
            'category_name' => array_key_exists('category_name', $input)
                ? $input['category_name']
                : $publication->category_name,
            'draft_payload' => $draft,
            'draft_dirty' => true,
            'validation_errors' => null,
            'last_error' => null,
        ]);

        return $publication->fresh();
    }

    public function show(AvitoPublication $publication): array
    {
        $publication->load([
            'good',
            'feed',
            'currentRevision.media',
            'revisions' => fn ($query) => $query->latest('version')->with('media'),
        ]);
        $preview = $this->buildPreview($publication);

        return [
            'publication' => $this->publicationPayload($publication),
            'preview' => $this->publicPreview($preview),
            'feed' => $this->feedPayload($publication->feed),
        ];
    }

    public function preview(AvitoPublication $publication): array
    {
        return $this->publicPreview($this->buildPreview($publication));
    }

    public function approve(AvitoPublication $publication): AvitoPublicationRevision
    {
        $this->assertEditable($publication);
        $preview = $this->buildPreview($publication);
        if ($preview['errors'] !== []) {
            $publication->update(['validation_errors' => $preview['errors']]);

            throw new AvitoException(
                'Черновик содержит ошибки. Исправьте обязательные поля перед фиксацией версии.',
                'autoload_validation',
                422,
            );
        }

        $written = [];
        try {
            $revision = DB::transaction(function () use ($publication, $preview, &$written): AvitoPublicationRevision {
                $publication->revisions()->where('is_current', true)->update(['is_current' => false]);
                $version = ((int) $publication->revisions()->max('version')) + 1;
                $revision = $publication->revisions()->create([
                    'version' => $version,
                    'status' => 'approved',
                    'is_current' => true,
                    'selected_fields' => $preview['selected_fields'],
                    'source_snapshot' => $preview['source_snapshot'],
                    'payload_snapshot' => $preview['payload'],
                    'approved_at' => now(),
                ]);

                foreach ($preview['media_models'] as $index => $media) {
                    $snapshot = $this->snapshotMedia($revision, $media, $index);
                    $written[] = [$snapshot->disk, $snapshot->path];
                }

                $publication->update([
                    'status' => 'ready',
                    'draft_dirty' => false,
                    'validation_errors' => null,
                    'last_error' => null,
                    'approved_at' => now(),
                ]);

                return $revision->fresh('media');
            });
        } catch (Throwable $exception) {
            foreach ($written as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }

            throw $exception;
        }

        return $revision;
    }

    public function updateFeed(
        AvitoAutoloadFeed $feed,
        array $input,
        ?AvitoConnection $connection = null,
    ): AvitoAutoloadFeed {
        $defaults = array_replace((array) $feed->defaults, Arr::only($input, [
            'address', 'contact_phone', 'manager_name', 'report_email',
        ]));
        $feed->update([
            'avito_connection_id' => array_key_exists('connection_id', $input)
                ? $connection?->id
                : $feed->avito_connection_id,
            'defaults' => $defaults,
        ]);

        return $feed->fresh();
    }

    public function checkProfile(AvitoAutoloadFeed $feed): array
    {
        $profile = $this->autoload->profile($feed->connection);
        $attached = $profile['exists'] && $this->profileContainsFeed(
            (array) ($profile['profile'] ?? []),
            $feed,
        );
        $feed->update([
            'profile_status' => $attached ? 'attached' : ($profile['exists'] ? 'feed_missing' : 'missing'),
            'profile_snapshot' => $profile,
            'last_error' => null,
            'profile_checked_at' => now(),
            'profile_attached_at' => $attached ? ($feed->profile_attached_at ?: now()) : null,
        ]);

        return ['attached' => $attached] + $profile + ['feed' => $this->feedPayload($feed->fresh())];
    }

    public function attachProfile(AvitoAutoloadFeed $feed, array $input): array
    {
        $result = $this->autoload->attachFeed(
            $feed->name,
            $this->feedUrl($feed),
            $input,
            $feed->connection,
        );
        $defaults = array_replace((array) $feed->defaults, [
            'report_email' => trim((string) $input['report_email']),
        ]);
        $feed->update([
            'defaults' => $defaults,
            'profile_status' => 'attached',
            'profile_snapshot' => $result,
            'profile_checked_at' => now(),
            'profile_attached_at' => now(),
            'last_error' => null,
        ]);

        return $result + ['feed' => $this->feedPayload($feed->fresh())];
    }

    public function requestUpload(AvitoAutoloadFeed $feed): array
    {
        $interval = (int) config('avito.autoload.upload_interval_minutes', 60);
        if ($feed->last_upload_requested_at?->gt(now()->subMinutes($interval))) {
            $availableAt = $feed->last_upload_requested_at->copy()->addMinutes($interval);
            throw new AvitoException(
                'Avito разрешает запуск не чаще одного раза в час. Следующий запуск после '.$availableAt->format('H:i'),
                'autoload_rate_limit',
                429,
                true,
            );
        }

        $profile = $this->checkProfile($feed);
        if (! $profile['attached']) {
            throw new AvitoException(
                'Сначала подключите защищённый feed Ameise к профилю Автозагрузки.',
                'autoload_feed_missing',
                422,
            );
        }
        $publicationQuery = $this->feedPublicationsQuery($feed);
        $count = (clone $publicationQuery)->count();
        if ($count === 0) {
            throw new AvitoException('В feed нет ни одной подтверждённой версии.', 'autoload_feed_empty', 422);
        }

        $result = $this->autoload->upload($feed->connection);
        DB::transaction(function () use ($feed, $result): void {
            $publications = $this->feedPublicationsQuery($feed)->with('currentRevision')->get();
            foreach ($publications as $publication) {
                $publication->update([
                    'status' => 'publishing',
                    'last_upload_requested_at' => now(),
                    'last_error' => null,
                ]);
                $publication->currentRevision?->update([
                    'status' => 'submitted',
                    'submitted_at' => now(),
                ]);
            }
            $feed->update([
                'last_upload_snapshot' => $result,
                'last_upload_requested_at' => now(),
                'last_error' => null,
            ]);
        });

        return $result + [
            'submitted_publications' => $count,
            'feed' => $this->feedPayload($feed->fresh()),
        ];
    }

    public function remoteStatus(AvitoAutoloadFeed $feed): array
    {
        $result = $this->autoload->currentUpload($feed->connection);
        $feed->update([
            'last_upload_snapshot' => $result,
            'last_synced_at' => now(),
            'last_error' => null,
        ]);

        return $result + ['feed' => $this->feedPayload($feed->fresh())];
    }

    public function sync(AvitoPublication $publication): array
    {
        $publication->loadMissing(['feed.connection', 'currentRevision', 'good']);
        if (! $publication->currentRevision) {
            throw new AvitoException('У публикации ещё нет подтверждённой версии.', 'autoload_revision_missing', 422);
        }

        $report = $this->autoload->itemReport($publication->external_id, $publication->feed->connection);
        $item = (array) ($report['item'] ?? []);
        $avitoItemId = $this->autoload->extractAvitoItemId($item, $publication->external_id);
        if (! $avitoItemId && $report['exists']) {
            try {
                $avitoItemId = $this->autoload->avitoItemId(
                    $publication->external_id,
                    $publication->feed->connection,
                );
            } catch (AvitoException $exception) {
                if (! in_array($exception->httpStatus, [404, 422], true)) {
                    throw $exception;
                }
            }
        }

        $messages = $this->reportMessages($item);
        $hasErrors = collect($messages)->contains(fn (array $message): bool => $message['level'] === 'error');
        $hasWarnings = collect($messages)->contains(fn (array $message): bool => $message['level'] === 'warning');
        $resolvedAvitoItemId = $avitoItemId ?: $publication->avito_item_id;
        $status = $hasErrors
            ? 'rejected'
            : ($resolvedAvitoItemId ? ($hasWarnings ? 'warning' : 'published') : 'publishing');
        $revisionStatus = match ($status) {
            'published', 'warning' => 'published',
            'rejected' => 'rejected',
            default => 'submitted',
        };

        DB::transaction(function () use ($publication, $report, $resolvedAvitoItemId, $status, $revisionStatus): void {
            $publication->update([
                'avito_item_id' => $resolvedAvitoItemId,
                'status' => $status,
                'last_remote_report' => $report,
                'last_error' => $status === 'rejected' ? 'Avito отклонил текущую версию.' : null,
                'published_at' => in_array($status, ['published', 'warning'], true)
                    ? ($publication->published_at ?: now())
                    : $publication->published_at,
                'last_synced_at' => now(),
            ]);
            $publication->currentRevision->update([
                'status' => $revisionStatus,
                'remote_report' => $report,
                'processed_at' => in_array($status, ['published', 'warning', 'rejected'], true) ? now() : null,
            ]);
            $publication->feed->update(['last_synced_at' => now()]);

            if ($resolvedAvitoItemId && $publication->good) {
                $this->links->link($publication->avito_account_id, $resolvedAvitoItemId, $publication->good);
            }
        });

        return [
            'status' => $status,
            'avito_item_id' => $resolvedAvitoItemId,
            'messages' => $messages,
            'report' => $report,
            'publication' => $this->publicationPayload($publication->fresh([
                'good', 'feed', 'currentRevision.media', 'revisions.media',
            ])),
        ];
    }

    public function archive(AvitoPublication $publication): AvitoPublication
    {
        $publication->update(['status' => 'archived']);

        return $publication->fresh();
    }

    public function feedXml(AvitoAutoloadFeed $feed): string
    {
        $publications = $this->feedPublicationsQuery($feed)
            ->with(['currentRevision.media'])
            ->orderBy('id')
            ->get();
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('Ads');
        $writer->writeAttribute('formatVersion', '3');
        $writer->writeAttribute('target', 'Avito.ru');
        foreach ($publications as $publication) {
            $this->writeAd($writer, $publication, $publication->currentRevision);
        }
        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    public function previewXml(AvitoPublication $publication): string
    {
        $preview = $this->buildPreview($publication);
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('Ads');
        $writer->writeAttribute('formatVersion', '3');
        $writer->writeAttribute('target', 'Avito.ru');
        $this->writePayload($writer, $publication->external_id, $preview['payload'], []);
        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    public function authenticateFeed(AvitoAutoloadFeed $feed, string $token): void
    {
        if ($token === '' || ! hash_equals((string) $feed->access_token, $token)) {
            throw new AvitoException('Ссылка feed недействительна.', 'autoload_feed_token', 404);
        }
    }

    public function publicMedia(
        AvitoAutoloadFeed $feed,
        string $token,
        AvitoPublicationRevision $revision,
        AvitoPublicationMedia $media,
    ): AvitoPublicationMedia {
        $this->authenticateFeed($feed, $token);
        $revision->loadMissing('publication');
        if ($revision->publication?->avito_autoload_feed_id !== $feed->id
            || $media->avito_publication_revision_id !== $revision->id) {
            throw new AvitoException('Фотография отсутствует в этом feed.', 'autoload_media', 404);
        }

        return $media;
    }

    public function feedPayload(AvitoAutoloadFeed $feed): array
    {
        $counts = $feed->relationLoaded('publications')
            ? $feed->publications->count()
            : $feed->publications()->count();
        $approvedCount = $this->feedPublicationsQuery($feed)->count();

        return [
            'id' => $feed->id,
            'avito_account_id' => $feed->avito_account_id,
            'avito_connection_id' => $feed->avito_connection_id,
            'name' => $feed->name,
            'url' => $this->feedUrl($feed),
            'profile_status' => $feed->profile_status,
            'defaults' => $feed->defaults ?: [],
            'publications_count' => $counts,
            'approved_publications_count' => $approvedCount,
            'profile_checked_at' => $feed->profile_checked_at?->toIso8601String(),
            'profile_attached_at' => $feed->profile_attached_at?->toIso8601String(),
            'last_upload_requested_at' => $feed->last_upload_requested_at?->toIso8601String(),
            'last_synced_at' => $feed->last_synced_at?->toIso8601String(),
            'next_upload_at' => $feed->last_upload_requested_at
                ?->copy()->addMinutes((int) config('avito.autoload.upload_interval_minutes', 60))->toIso8601String(),
            'last_error' => $feed->last_error,
        ];
    }

    private function defaultDraft(Good $good, AvitoAutoloadFeed $feed): array
    {
        $payload = $this->goods->goodPayload($good);
        $price = collect($payload['prices'] ?? [])->firstWhere('is_public', true)
            ?: collect($payload['prices'] ?? [])->first();
        $defaults = (array) $feed->defaults;

        return [
            'selected_fields' => ['title', 'description', 'price', 'images'],
            'price_value_id' => $price['id'] ?? null,
            'media_ids' => collect($payload['media'] ?? [])
                ->take((int) config('avito.autoload.max_images', 10))
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
            'include_facts' => true,
            'title_override' => null,
            'description_override' => null,
            'price_override' => null,
            'address' => $defaults['address'] ?? '',
            'contact_phone' => $defaults['contact_phone'] ?? '',
            'manager_name' => $defaults['manager_name'] ?? '',
            'allow_email' => false,
            'ad_type' => 'Товар приобретен на продажу',
            'condition' => 'Новое',
            'listing_fee' => null,
            'category_fields' => [],
            'category_schema' => [],
        ];
    }

    private function buildPreview(AvitoPublication $publication): array
    {
        $publication->loadMissing('good');
        if (! $publication->good) {
            return [
                'selected_fields' => [],
                'source_snapshot' => [],
                'payload' => [],
                'media_models' => collect(),
                'errors' => ['good' => ['Good удалён или недоступен.']],
                'warnings' => [],
            ];
        }

        $good = $this->goods->prepareGood($publication->good->fresh());
        $source = $this->goods->goodPayload($good);
        $draft = (array) $publication->draft_payload;
        $selected = collect((array) ($draft['selected_fields'] ?? []))
            ->filter(fn ($field): bool => in_array($field, self::SELECTABLE_FIELDS, true))
            ->unique()
            ->values()
            ->all();
        $title = trim((string) ($draft['title_override'] ?? ''));
        if ($title === '' && in_array('title', $selected, true)) {
            $title = trim((string) $source['name']);
        }
        $description = trim((string) ($draft['description_override'] ?? ''));
        if ($description === '' && in_array('description', $selected, true)) {
            $description = $this->description($source, (bool) ($draft['include_facts'] ?? true));
        }
        $price = $this->price($source, $draft, $selected);
        $mediaIds = in_array('images', $selected, true)
            ? collect((array) ($draft['media_ids'] ?? []))->map(fn ($id): int => (int) $id)->filter()->unique()->values()
            : collect();
        $mediaModels = GoodMedia::query()
            ->where('good_id', $good->id)
            ->where('type', 'image')
            ->where('is_published', true)
            ->whereIn('id', $mediaIds)
            ->get()
            ->sortBy(fn (GoodMedia $media) => $mediaIds->search($media->id))
            ->values();
        $categoryFields = $this->sanitizeCategoryFields((array) ($draft['category_fields'] ?? []));
        if (filled($draft['ad_type'] ?? null)) {
            $categoryFields['AdType'] = trim((string) $draft['ad_type']);
        }
        if (filled($draft['condition'] ?? null)) {
            $categoryFields['Condition'] = trim((string) $draft['condition']);
        }
        $payload = [
            'category_node_slug' => $publication->category_node_slug,
            'category' => trim((string) $publication->category_name),
            'title' => $title,
            'description' => $description,
            'price' => $price,
            'address' => trim((string) ($draft['address'] ?? '')),
            'contact_phone' => trim((string) ($draft['contact_phone'] ?? '')),
            'manager_name' => trim((string) ($draft['manager_name'] ?? '')),
            'allow_email' => (bool) ($draft['allow_email'] ?? false),
            'listing_fee' => filled($draft['listing_fee'] ?? null) ? trim((string) $draft['listing_fee']) : null,
            'category_fields' => $categoryFields,
            'images' => $mediaModels->map(fn (GoodMedia $media): array => [
                'good_media_id' => $media->id,
                'title' => $media->title ?: $media->original_name ?: $good->name,
                'mime_type' => $media->mime_type,
            ])->all(),
        ];
        [$errors, $warnings] = $this->validatePreview($publication, $good, $draft, $payload, $mediaIds, $mediaModels);

        return [
            'selected_fields' => $selected,
            'source_snapshot' => [
                'source_of_truth' => 'good',
                'good' => Arr::only($source, [
                    'id', 'name', 'slug', 'description', 'denominator', 'is_published',
                    'country', 'availability', 'public_url', 'prices', 'media',
                ]),
                'captured_at' => now()->toIso8601String(),
            ],
            'payload' => $payload,
            'media_models' => $mediaModels,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function validatePreview(
        AvitoPublication $publication,
        Good $good,
        array $draft,
        array $payload,
        $mediaIds,
        $mediaModels,
    ): array {
        $errors = [];
        $warnings = [];
        $add = function (string $field, string $message) use (&$errors): void {
            $errors[$field][] = $message;
        };

        if (! $good->is_published) {
            $warnings[] = 'Good не опубликован в приложении; публикация возможна только после осознанного подтверждения версии.';
        }
        if (! filled($publication->category_node_slug) || ! filled($payload['category'])) {
            $add('category', 'Выберите конечную категорию Avito.');
        }
        if (mb_strlen($payload['title']) < 3 || mb_strlen($payload['title']) > 100) {
            $add('title', 'Название должно содержать от 3 до 100 символов.');
        }
        if (mb_strlen($payload['description']) < 10 || mb_strlen($payload['description']) > 7500) {
            $add('description', 'Описание должно содержать от 10 до 7500 символов.');
        }
        if ($payload['price'] === null || $payload['price'] < 0 || $payload['price'] > 999999999999) {
            $add('price', 'Выберите действующую цену Good в RUB или задайте допустимое значение.');
        }
        if ($payload['address'] === '' || mb_strlen($payload['address']) > 500) {
            $add('address', 'Укажите адрес размещения длиной до 500 символов.');
        }
        $phoneDigits = preg_replace('/\D+/', '', $payload['contact_phone']) ?? '';
        if (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
            $add('contact_phone', 'Укажите корректный контактный телефон.');
        }
        if ($mediaIds->count() !== $mediaModels->count()) {
            $add('images', 'Одна из фотографий не принадлежит Good или не опубликована.');
        }
        if ($mediaModels->isEmpty()) {
            $warnings[] = 'Фотографии не выбраны; большинство товарных категорий Avito требуют хотя бы одно изображение.';
        }
        foreach ($mediaModels as $media) {
            $disk = Storage::disk($media->disk ?: 'yandex');
            if (! $media->path || ! $disk->exists($media->path)) {
                $add('images', "Исходный файл фотографии #{$media->id} отсутствует в хранилище.");
            }
        }
        foreach ((array) ($draft['category_schema'] ?? []) as $definition) {
            if (! is_array($definition) || ! ($definition['required'] ?? false)) {
                continue;
            }
            $key = (string) ($definition['key'] ?? '');
            $value = match ($key) {
                'Id' => $publication->external_id,
                'Category' => $payload['category'],
                'Title' => $payload['title'],
                'Description' => $payload['description'],
                'Price' => $payload['price'],
                'Images' => $payload['images'],
                'Address' => $payload['address'],
                'ContactPhone' => $payload['contact_phone'],
                'ManagerName' => $payload['manager_name'],
                'AllowEmail' => $payload['allow_email'],
                'ListingFee' => $payload['listing_fee'],
                default => $payload['category_fields'][$key] ?? null,
            };
            if ($key !== '' && ! $this->hasXmlValue($value)) {
                $add('category_fields.'.$key, 'Заполните обязательное поле «'.($definition['label'] ?? $key).'».');
            }
        }
        if (filled($payload['listing_fee'])) {
            $warnings[] = 'Режим оплаты размещения будет передан как ListingFee: проверьте тариф кабинета перед запуском.';
        }

        return [$errors, array_values(array_unique($warnings))];
    }

    private function publicPreview(array $preview): array
    {
        return Arr::except($preview, ['media_models']) + [
            'valid' => $preview['errors'] === [],
        ];
    }

    private function price(array $source, array $draft, array $selected): ?int
    {
        if (filled($draft['price_override'] ?? null) && is_numeric($draft['price_override'])) {
            return (int) round((float) $draft['price_override']);
        }
        if (! in_array('price', $selected, true)) {
            return null;
        }
        $price = collect($source['prices'] ?? [])->firstWhere('id', (int) ($draft['price_value_id'] ?? 0));
        if (! $price || strtoupper((string) ($price['currency_code'] ?? '')) !== 'RUB') {
            return null;
        }
        if (($price['valid_from'] ?? null)
            && CarbonImmutable::today()->lt(CarbonImmutable::parse($price['valid_from'])->startOfDay())) {
            return null;
        }
        if (($price['valid_to'] ?? null)
            && CarbonImmutable::today()->gt(CarbonImmutable::parse($price['valid_to'])->startOfDay())) {
            return null;
        }

        return is_numeric($price['amount'] ?? null) ? (int) round((float) $price['amount']) : null;
    }

    private function description(array $good, bool $includeFacts): string
    {
        $parts = array_values(array_filter([
            trim((string) ($good['description'] ?? '')),
        ], fn (string $value): bool => $value !== ''));
        if ($includeFacts) {
            $facts = [];
            if (is_numeric($good['denominator'] ?? null)) {
                $facts[] = 'Фасовка: '.$this->number((float) $good['denominator']).' кг';
            }
            if (filled(Arr::get($good, 'country.name'))) {
                $facts[] = 'Страна: '.Arr::get($good, 'country.name');
            }
            $facts[] = 'Наличие: '.match (Arr::get($good, 'availability.status')) {
                'in_stock' => 'в наличии',
                'out_of_stock' => 'нет в наличии',
                default => 'под заказ',
            };
            if (filled($good['public_url'] ?? null)) {
                $facts[] = (string) $good['public_url'];
            }
            $parts[] = implode("\n", $facts);
        }

        return trim(implode("\n\n", array_filter($parts)));
    }

    private function snapshotMedia(
        AvitoPublicationRevision $revision,
        GoodMedia $media,
        int $sortOrder,
    ): AvitoPublicationMedia {
        $source = Storage::disk($media->disk ?: 'yandex');
        if (! $media->path || ! $source->exists($media->path)) {
            throw new AvitoException("Файл фотографии #{$media->id} отсутствует.", 'autoload_media_missing', 422);
        }
        $extension = preg_replace('/[^a-z0-9]/', '', Str::lower(
            $media->extension ?: pathinfo($media->path, PATHINFO_EXTENSION)
        )) ?: 'jpg';
        $fileName = 'image-'.($sortOrder + 1).'.'.$extension;
        $targetDisk = (string) config('avito.autoload.media_disk', 'avito');
        $targetPath = "publications/{$revision->avito_publication_id}/revisions/{$revision->version}/"
            .Str::uuid().'.'.$extension;
        $stream = $source->readStream($media->path);
        if (! is_resource($stream)) {
            throw new RuntimeException("Не удалось прочитать фотографию #{$media->id}.");
        }

        try {
            $written = Storage::disk($targetDisk)->writeStream($targetPath, $stream);
        } finally {
            fclose($stream);
        }
        if (! $written) {
            throw new RuntimeException("Не удалось сохранить снимок фотографии #{$media->id}.");
        }
        $target = Storage::disk($targetDisk);

        try {
            return $revision->media()->create([
                'good_media_id' => $media->id,
                'disk' => $targetDisk,
                'path' => $targetPath,
                'file_name' => $fileName,
                'mime_type' => $media->mime_type ?: $target->mimeType($targetPath),
                'size' => $target->size($targetPath),
                'sha256' => hash('sha256', $target->get($targetPath)),
                'title' => $media->title ?: $media->original_name,
                'sort_order' => $sortOrder,
            ]);
        } catch (Throwable $exception) {
            $target->delete($targetPath);

            throw $exception;
        }
    }

    private function writeAd(
        XMLWriter $writer,
        AvitoPublication $publication,
        AvitoPublicationRevision $revision,
    ): void {
        $this->writePayload(
            $writer,
            $publication->external_id,
            (array) $revision->payload_snapshot,
            $revision->media->all(),
            $publication->feed,
            $revision,
        );
    }

    private function writePayload(
        XMLWriter $writer,
        string $externalId,
        array $payload,
        array $media,
        ?AvitoAutoloadFeed $feed = null,
        ?AvitoPublicationRevision $revision = null,
    ): void {
        $writer->startElement('Ad');
        $this->element($writer, 'Id', $externalId);
        if (filled($payload['listing_fee'] ?? null)) {
            $this->element($writer, 'ListingFee', $payload['listing_fee']);
        }
        $this->element($writer, 'AllowEmail', ($payload['allow_email'] ?? false) ? 'Да' : 'Нет');
        if (filled($payload['manager_name'] ?? null)) {
            $this->element($writer, 'ManagerName', $payload['manager_name']);
        }
        $this->element($writer, 'ContactPhone', $payload['contact_phone'] ?? '');
        $this->element($writer, 'Address', $payload['address'] ?? '');
        $this->element($writer, 'Category', $payload['category'] ?? '');
        foreach ((array) ($payload['category_fields'] ?? []) as $name => $value) {
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_.-]{0,119}$/', (string) $name)
                || in_array($name, self::RESERVED_XML_FIELDS, true)
                || blank($value)) {
                continue;
            }
            foreach (Arr::wrap($value) as $item) {
                $this->element($writer, (string) $name, $item);
            }
        }
        $this->element($writer, 'Title', $payload['title'] ?? '');
        $this->element($writer, 'Description', $payload['description'] ?? '');
        if (($payload['price'] ?? null) !== null) {
            $this->element($writer, 'Price', $payload['price']);
        }
        if ($media !== [] && $feed && $revision) {
            $writer->startElement('Images');
            foreach ($media as $image) {
                $writer->startElement('Image');
                $writer->writeAttribute('url', route('avito.autoload.media', [
                    'feed' => $feed->id,
                    'token' => $feed->access_token,
                    'revision' => $revision->id,
                    'media' => $image->id,
                ]));
                $writer->endElement();
            }
            $writer->endElement();
        }
        $writer->endElement();
    }

    private function element(XMLWriter $writer, string $name, mixed $value): void
    {
        $text = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        $text = preg_replace(
            '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $text,
        ) ?? '';
        $writer->startElement($name);
        $writer->text($text);
        $writer->endElement();
    }

    private function publicationSummary(AvitoPublication $publication): array
    {
        return [
            'id' => $publication->id,
            'external_id' => $publication->external_id,
            'status' => $publication->status,
            'draft_dirty' => $publication->draft_dirty,
            'good_id' => $publication->good_id,
            'good_name' => $publication->good?->name ?: 'Good удалён',
            'good_is_published' => (bool) $publication->good?->is_published,
            'category_name' => $publication->category_name,
            'avito_item_id' => $publication->avito_item_id,
            'revision' => $publication->currentRevision?->version,
            'images_count' => $publication->currentRevision?->media->count() ?: 0,
            'approved_at' => $publication->approved_at?->toIso8601String(),
            'published_at' => $publication->published_at?->toIso8601String(),
            'updated_at' => $publication->updated_at?->toIso8601String(),
        ];
    }

    private function publicationPayload(AvitoPublication $publication): array
    {
        $publication->loadMissing(['good', 'currentRevision.media', 'revisions.media']);
        $goodPayload = $publication->good ? $this->goods->goodPayload($publication->good) : null;

        return $this->publicationSummary($publication) + [
            'avito_account_id' => $publication->avito_account_id,
            'avito_connection_id' => $publication->avito_connection_id,
            'category_node_slug' => $publication->category_node_slug,
            'draft' => $publication->draft_payload ?: [],
            'validation_errors' => $publication->validation_errors ?: [],
            'last_error' => $publication->last_error,
            'last_upload_requested_at' => $publication->last_upload_requested_at?->toIso8601String(),
            'last_synced_at' => $publication->last_synced_at?->toIso8601String(),
            'good' => $goodPayload,
            'current_revision' => $publication->currentRevision
                ? $this->revisionPayload($publication->currentRevision)
                : null,
            'revisions' => $publication->revisions
                ->sortByDesc('version')
                ->map(fn (AvitoPublicationRevision $revision): array => $this->revisionPayload($revision))
                ->values()
                ->all(),
        ];
    }

    private function revisionPayload(AvitoPublicationRevision $revision): array
    {
        return [
            'id' => $revision->id,
            'version' => $revision->version,
            'status' => $revision->status,
            'is_current' => $revision->is_current,
            'selected_fields' => $revision->selected_fields ?: [],
            'payload' => $revision->payload_snapshot,
            'images' => $revision->media->map(fn (AvitoPublicationMedia $media): array => [
                'id' => $media->id,
                'source_good_media_id' => $media->good_media_id,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'title' => $media->title,
            ])->values()->all(),
            'report_messages' => $revision->remote_report
                ? $this->reportMessages((array) $revision->remote_report)
                : [],
            'approved_at' => $revision->approved_at?->toIso8601String(),
            'submitted_at' => $revision->submitted_at?->toIso8601String(),
            'processed_at' => $revision->processed_at?->toIso8601String(),
        ];
    }

    private function reportMessages(array $item): array
    {
        $messages = [];
        $walk = function (mixed $value, ?string $parent = null) use (&$walk, &$messages): void {
            if (! is_array($value)) {
                return;
            }
            if (! array_is_list($value)) {
                $text = $value['description'] ?? $value['message'] ?? $value['text'] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    $rawLevel = Str::lower((string) ($value['level'] ?? $value['type'] ?? $value['status'] ?? $parent ?? 'info'));
                    $level = Str::contains($rawLevel, ['error', 'reject', 'fatal', 'ошиб'])
                        ? 'error'
                        : (Str::contains($rawLevel, ['warn', 'предуп']) ? 'warning' : 'info');
                    $messages[] = [
                        'level' => $level,
                        'code' => $value['code'] ?? null,
                        'message' => trim(strip_tags($text)),
                    ];
                }
            }
            foreach ($value as $key => $child) {
                if (is_array($child)) {
                    $walk($child, (string) $key);
                }
            }
        };
        $walk($item);

        return collect($messages)->unique(fn (array $message): string => implode('|', [
            $message['level'], $message['code'], $message['message'],
        ]))->values()->all();
    }

    private function feedPublicationsQuery(AvitoAutoloadFeed $feed): Builder
    {
        return AvitoPublication::query()
            ->where('avito_autoload_feed_id', $feed->id)
            ->where('status', '!=', 'archived')
            ->whereHas('currentRevision', fn (Builder $query) => $query->where('is_current', true));
    }

    private function profileContainsFeed(array $profile, AvitoAutoloadFeed $feed): bool
    {
        $expected = rtrim($this->feedUrl($feed), '/');

        return collect((array) ($profile['feeds_data'] ?? []))->contains(
            fn ($item): bool => is_array($item)
                && (($item['feed_name'] ?? null) === $feed->name
                    || rtrim((string) ($item['feed_url'] ?? ''), '/') === $expected)
        );
    }

    private function feedUrl(AvitoAutoloadFeed $feed): string
    {
        return route('avito.autoload.feed', [
            'feed' => $feed->id,
            'token' => $feed->access_token,
        ]);
    }

    private function sanitizeCategoryFields(array $fields): array
    {
        $sanitized = [];
        foreach ($fields as $name => $value) {
            if (! is_string($name)
                || ! preg_match('/^[A-Za-z_][A-Za-z0-9_.-]{0,119}$/', $name)
                || in_array($name, self::RESERVED_XML_FIELDS, true)) {
                continue;
            }
            $values = collect(Arr::wrap($value))
                ->filter(fn ($item): bool => is_scalar($item))
                ->map(fn ($item): string => Str::limit(trim((string) $item), 2000, ''))
                ->filter(fn (string $item): bool => $item !== '')
                ->values()
                ->all();
            if ($values !== []) {
                $sanitized[$name] = count($values) === 1 ? $values[0] : $values;
            }
        }

        return $sanitized;
    }

    private function assertEditable(AvitoPublication $publication): void
    {
        if ($publication->status === 'archived') {
            throw new AvitoException('Архивный черновик нельзя изменять.', 'autoload_archived', 422);
        }
        if (! $publication->good_id) {
            throw new AvitoException('Связанный Good удалён.', 'autoload_good_missing', 422);
        }
    }

    private function hasXmlValue(mixed $value): bool
    {
        if (is_array($value)) {
            return collect($value)->contains(fn ($item): bool => $this->hasXmlValue($item));
        }

        return $value !== null && (! is_string($value) || trim($value) !== '');
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ' '), '0'), '.');
    }
}
