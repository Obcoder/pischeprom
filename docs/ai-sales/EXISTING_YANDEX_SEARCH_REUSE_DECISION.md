# Решение о переиспользовании Yandex Search

Статус: accepted for Stage 09.

Решение: `EXTEND`, не `CREATE_PARALLEL_INTEGRATION`.

## Граница ответственности

`App\Services\YandexSearchService` остаётся единственным классом, который:

- читает `services.yandex_search.*`;
- формирует Yandex request и auth header;
- владеет exact endpoint;
- выполняет HTTP;
- проверяет response envelope;
- разбирает и нормализует XML.

`App\Infrastructure\AiSales\Search\ExistingYandexSearchProviderAdapter` не копирует transport. Он переводит provider-neutral `SearchProviderRequest` в вызовы существующего service и преобразует нормализованный результат в `SearchProviderResponse`.

## Server-owned profiles

`App\Services\Yandex\YandexSearchProfileRegistry` владеет двумя профилями:

- `product_page_search` — текущий Product page contract, до 100 результатов и 10 страниц;
- `prospecting_b2b_discovery` — только approved Product-first query, до 50 результатов и 5 страниц.

Профиль нельзя выбрать из browser request. Adapter принимает только `prospecting_b2b_discovery`; Product job использует default `product_page_search`.

## Persistence decision

Существующие `product_search_requests` и `product_search_results` сохраняются как source of truth Product-card feature. Их нельзя использовать для prospecting provenance: обе таблицы привязаны к Product-page request и не содержат Job/query plan/execution/usage bindings.

Поэтому Stage 09 добавляет provider-neutral таблицы:

- `prospecting_search_executions`;
- `prospecting_search_results`;
- `prospecting_search_usage_records`.

Это не второй Yandex cache и не второй transport. Таблицы фиксируют approved plan, execution provenance, bounded normalized results и usage для prospecting domain.

## Что намеренно не создано

- новый Yandex key или service account;
- второй base URL/host setting;
- новые Yandex secret env variables;
- отдельный Yandex HTTP client;
- provider fallback;
- browser-selectable provider/profile/query;
- live scheduler или autonomous search;
- Yandex/Timeweb live tests.

Stage 09B сможет использовать существующее подключение только после owner review по `STAGE_09_LIVE_GATE.md`.
