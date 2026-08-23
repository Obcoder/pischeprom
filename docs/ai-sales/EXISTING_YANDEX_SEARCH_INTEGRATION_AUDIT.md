# Аудит существующей интеграции Yandex Search

Дата аудита: 17 августа 2026 г.

Git baseline: `033f8be6d504cadcc23694f25099d461bdf7c66c`

Результат security gate: `PASS`.

## Фактический путь Product page

```text
resources/js/Pages/Ameise/Product_02.vue
  → resources/js/Components/ProductYandexSearchCard.vue
  → /api/products/{product}/yandex-search*
  → App\Http\Controllers\API\ProductSearchController
  → App\Jobs\FetchYandexProductSearchJob
  → App\Services\YandexSearchService
  → POST https://searchapi.api.cloud.yandex.net/v2/web/search
```

| Аспект | Фактическая реализация | Решение Stage 09 |
|---|---|---|
| Product page UI | `Product_02.vue`, `ProductYandexSearchCard.vue` | reuse; JSON/UI contract сохранён |
| API | `POST .../yandex-search`, `GET .../latest`, `GET .../{searchRequest}`; `ProductSearchController` | extend security: `auth:sanctum`, verified, throttle и server-side `products.view`/admin |
| Queue | `FetchYandexProductSearchJob`, payload содержит только request ID и bound | reuse |
| HTTP/auth owner | `YandexSearchService` | единственный source of truth |
| Config | `config/services.php`, ветка `services.yandex_search` | reuse |
| Env names | `YANDEX_SEARCH_API_KEY`, `YANDEX_SEARCH_FOLDER_ID`, `YANDEX_SEARCH_REGION`, `YANDEX_SEARCH_HOST` | reuse names only; новых secret variables нет |
| Endpoint | `POST https://searchapi.api.cloud.yandex.net/v2/web/search` | exact allowlist сохранён |
| Auth mapping | server-side header `Authorization: Api-Key …`; `folderId` в JSON body | сохранён; значения не логируются и не возвращаются |
| Request format | JSON, `SEARCH_TYPE_RU`, `FORMAT_XML`, page, typo mode off | сохранён через code-owned profiles |
| Response | JSON envelope, `rawData` содержит base64 XML | parser reused and hardened |
| Parser | `YandexSearchService::parseXmlResults()` | bounded XML; DTD/entities blocked; `LIBXML_NONET`; normalized fields only |
| Product persistence | `product_search_requests`, `product_search_results` | reuse без schema/contract replacement |
| Prospecting persistence | Product tables привязаны к конкретному Product и не provider-neutral | отдельные additive execution/result/usage tables |
| Redirect/retry/TLS | Stage 09: redirects off, retries отсутствуют, TLS verification on | hardened and regression-tested |
| Limits | Product: до 100 результатов/10 страниц; profile timeout 30 s | profile remains bounded; response/XML caps added |
| Errors | ранее мог сохраняться exception message | теперь сохраняется только safe code |
| Tests | до Stage 09 специализированного regression suite не было | `ExistingYandexProductPageRegressionTest` |

## Security gate

Проверено без вывода значений secrets:

- `.env` исключён из Git и не изменялся;
- Yandex Search key отсутствует в tracked files;
- нет `VITE_*` переменных для Yandex Search;
- frontend не содержит API-key header, endpoint или secret env names;
- браузер обращается только к Laravel API, а не к Yandex;
- API Resources и safe exceptions не возвращают key, folder ID или raw provider body;
- generated client/SSR bundles проверяются отдельным credential scan;
- строка provider endpoint принадлежит единственному production-классу — `YandexSearchService`.

Исходная route group была без обязательной аутентификации. Это security defect, поэтому Stage 09 добавляет server-side authentication/authorization, сохраняя response shape и действия Product UI.

## Итог

Выбрано решение `EXTEND`: существующий service безопасно усилен и остаётся единственным владельцем transport/auth/config; prospecting использует тонкий adapter. `STOP_SECURITY` не требуется. Live HTTP в Stage 09 не выполнялся.
