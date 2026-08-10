<?php

namespace App\Services\Avito;

use App\Domain\Avito\Catalog\AvitoApiCatalog;
use App\Domain\Avito\Exceptions\AvitoException;
use App\Models\AvitoConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AvitoListingService
{
    public const STATUSES = ['active', 'removed', 'old', 'blocked', 'rejected'];

    public const METRICS = [
        'views', 'contacts', 'contactsShowPhone', 'contactsMessenger',
        'contactsShowPhoneAndMessenger', 'contactsSbcDiscount',
        'viewsToContactsConversion', 'favorites', 'averageViewCost',
        'averageContactCost', 'impressions', 'impressionsToViewsConversion',
        'clickPackages', 'jobContacts', 'viewsToOrderedItemsConversion',
        'orderedItems', 'orderedItemsPrice', 'deliveredItems',
        'deliveredItemsPrice', 'bookingPlacedCount', 'bookingPlacedPrice',
        'bookingApprovedCount', 'bookingApprovedPrice', 'bookingAcceptedCount',
        'bookingAcceptedPrice', 'allSpending', 'spending', 'presenceSpending',
        'promoSpending', 'restSpending', 'commission', 'spendingBonus',
        'activeItems', 'newActiveItems', 'oldActiveItems',
    ];

    public function __construct(
        private readonly AvitoApiCatalog $catalog,
        private readonly AvitoApiExecutor $executor,
    ) {}

    public function context(?AvitoConnection $connection = null): array
    {
        $result = $this->execute('user', 'getUserInfoSelf', [], $connection);

        return [
            'account' => is_array($result['data'] ?? null) ? $result['data'] : [],
            'remote' => $this->remoteMeta($result),
        ];
    }

    public function listings(int $accountId, array $filters): array
    {
        $query = array_filter([
            'status' => ($filters['statuses'] ?? []) !== []
                ? implode(',', $filters['statuses'])
                : null,
            'category' => $filters['category'] ?? null,
            'updatedAtFrom' => $filters['updated_from'] ?? null,
            'per_page' => $filters['per_page'] ?? 50,
            'page' => $filters['page'] ?? 1,
        ], fn (mixed $value) => $value !== null && $value !== '');
        $result = $this->execute('avito-promo', 'coreItems', [
            'query' => $query,
            'headers' => ['X-AgencyClientId' => $accountId],
        ]);
        $payload = is_array($result['data'] ?? null) ? $result['data'] : [];
        $items = Arr::get($payload, 'resources', Arr::get($payload, 'result.resources', Arr::get($payload, 'items', [])));

        return [
            'items' => is_array($items) ? array_values($items) : [],
            'meta' => (array) Arr::get($payload, 'meta', Arr::get($payload, 'result.meta', [])),
            'remote' => $this->remoteMeta($result),
        ];
    }

    public function listing(int $accountId, int $itemId): array
    {
        $result = $this->execute('avito-promo', 'coreItem', [
            'path' => ['user_id' => $accountId, 'item_id' => $itemId],
            'headers' => ['X-AgencyClientId' => $accountId],
        ]);

        return [
            'item' => is_array($result['data'] ?? null) ? $result['data'] : [],
            'remote' => $this->remoteMeta($result),
        ];
    }

    public function statistics(int $accountId, array $input): array
    {
        $body = array_filter([
            'dateFrom' => $input['date_from'],
            'dateTo' => $input['date_to'],
            'grouping' => $input['grouping'],
            'metrics' => array_values($input['metrics']),
            'filter' => array_filter([
                'categoryIDs' => $input['category_ids'] ?? null,
                'employeeIDs' => $input['employee_ids'] ?? null,
            ]),
            'limit' => $input['limit'] ?? 1000,
            'offset' => $input['offset'] ?? 0,
            'sort' => isset($input['sort_key'], $input['sort_order'])
                ? ['key' => $input['sort_key'], 'order' => $input['sort_order']]
                : null,
        ], fn (mixed $value) => $value !== null && $value !== []);
        $result = $this->execute('avito-promo', 'statsAccountsItems', [
            'path' => ['user_id' => $accountId],
            'headers' => ['X-AgencyClientId' => $accountId],
            'body' => $body,
            'content_type' => 'application/json',
        ]);

        return [
            'statistics' => is_array($result['data'] ?? null) ? $result['data'] : [],
            'remote' => $this->remoteMeta($result),
        ];
    }

    public function itemStatistics(int $accountId, array $input): array
    {
        $result = $this->execute('item', 'itemStatsShallow', [
            'path' => ['user_id' => $accountId],
            'headers' => ['Content-Type' => 'application/json'],
            'body' => [
                'itemIds' => array_values($input['item_ids']),
                'dateFrom' => $input['date_from'],
                'dateTo' => $input['date_to'],
                'fields' => array_values($input['fields']),
                'periodGrouping' => $input['grouping'],
            ],
            'content_type' => 'application/json',
        ]);

        return [
            'statistics' => is_array($result['data'] ?? null) ? $result['data'] : [],
            'remote' => $this->remoteMeta($result),
        ];
    }

    public function spendings(int $accountId, array $input): array
    {
        $result = $this->execute('avito-promo', 'statsAccountsSpendings', [
            'path' => ['user_id' => $accountId],
            'headers' => ['X-AgencyClientId' => $accountId],
            'body' => [
                'dateFrom' => $input['date_from'],
                'dateTo' => $input['date_to'],
                'grouping' => $input['grouping'],
                'spendingTypes' => array_values($input['spending_types']),
                'filter' => array_filter([
                    'itemIDs' => $input['item_ids'] ?? null,
                    'categoryIDs' => $input['category_ids'] ?? null,
                ]),
            ],
            'content_type' => 'application/json',
        ]);

        return [
            'spendings' => is_array($result['data'] ?? null) ? $result['data'] : [],
            'remote' => $this->remoteMeta($result),
        ];
    }

    public function promotionInsights(
        int $accountId,
        array $itemIds,
        ?AvitoConnection $connection = null,
    ): array {
        $itemIds = array_values($itemIds);

        return [
            'active_services' => $this->capture(fn () => $this->execute(
                'promotion',
                'get_services_by_items_v1',
                ['body' => ['itemIds' => $itemIds], 'content_type' => 'application/json'],
                $connection,
            )),
            'available_services' => $this->capture(fn () => $this->execute(
                'item',
                'vasPrices',
                [
                    'path' => ['user_id' => $accountId],
                    'body' => ['itemIds' => $itemIds],
                    'content_type' => 'application/json',
                ],
                $connection,
            )),
            'cpx' => $this->capture(fn () => $this->execute(
                'cpxpromo',
                'getPromotionsByItemIds',
                ['body' => ['itemIDs' => $itemIds], 'content_type' => 'application/json'],
                $connection,
            )),
            'bbip_suggestions' => $this->capture(fn () => $this->execute(
                'promotion',
                'get_bbip_suggests_by_items_v1',
                ['body' => ['itemIds' => $itemIds], 'content_type' => 'application/json'],
                $connection,
            )),
        ];
    }

    public function performAction(
        int $accountId,
        int $itemId,
        string $action,
        array $input,
        ?AvitoConnection $connection = null,
    ): array {
        [$section, $operationId, $payload] = match ($action) {
            'update_price' => ['item', 'updatePrice', [
                'path' => ['item_id' => $itemId],
                'body' => ['price' => $input['price']],
                'content_type' => 'application/json',
            ]],
            'apply_vas' => ['item', 'putItemVas', [
                'path' => ['user_id' => $accountId, 'item_id' => $itemId],
                'body' => ['vas_id' => $input['slug']],
                'content_type' => 'application/json',
            ]],
            'apply_package' => ['item', 'putItemVasPackageV2', [
                'path' => ['user_id' => $accountId, 'item_id' => $itemId],
                'body' => ['package_id' => $input['package_id']],
                'content_type' => 'application/json',
            ]],
            'apply_services' => ['item', 'applyVas', [
                'path' => ['item_id' => $itemId],
                'body' => array_filter([
                    'slugs' => array_values($input['slugs']),
                    'stickers' => $input['stickers'] ?? null,
                ], fn (mixed $value) => $value !== null && $value !== []),
                'content_type' => 'application/json',
            ]],
            'stop_cpx' => ['cpxpromo', 'removePromotion', [
                'body' => ['itemID' => $itemId],
                'content_type' => 'application/json',
            ]],
            'create_bbip' => ['promotion', 'create_bbip_order_for_items_v1', [
                'body' => ['items' => [[
                    'itemId' => $itemId,
                    'duration' => $input['duration'],
                    'oldPrice' => $input['old_price'],
                    'price' => $input['promo_price'],
                ]]],
                'content_type' => 'application/json',
            ]],
            default => throw new AvitoException('Неизвестное действие с объявлением.', 'validation', 422),
        };
        $payload['confirmation'] = (string) config('avito.mutation_confirmation');
        $result = $this->execute($section, $operationId, $payload, $connection);

        return [
            'action' => $action,
            'result' => $result['data'] ?? null,
            'remote' => $this->remoteMeta($result),
        ];
    }

    private function execute(
        string $section,
        string $operationId,
        array $input,
        ?AvitoConnection $connection = null,
    ): array {
        $capability = $this->catalog->findOperation($section, $operationId);
        $result = $this->executor->execute($capability['id'], $input, $connection);

        if (! $result['ok']) {
            $message = (string) (Arr::get($result, 'data.error.message')
                ?: Arr::get($result, 'data.error_description')
                ?: Arr::get($result, 'data.message')
                ?: "Avito вернул HTTP {$result['status']} для {$operationId}.");

            throw new AvitoException(
                Str::limit(strip_tags($message), 1000),
                'listing_remote',
                502,
                true,
            );
        }

        return $result;
    }

    private function capture(callable $callback): array
    {
        try {
            $result = $callback();

            return [
                'ok' => true,
                'data' => $result['data'] ?? null,
                'remote' => $this->remoteMeta($result),
            ];
        } catch (\Throwable $exception) {
            if (! $exception instanceof AvitoException) {
                report($exception);
            }

            return [
                'ok' => false,
                'message' => Str::limit($exception->getMessage(), 500),
            ];
        }
    }

    private function remoteMeta(array $result): array
    {
        return Arr::only($result, ['request_id', 'status', 'duration_ms', 'headers']);
    }
}
