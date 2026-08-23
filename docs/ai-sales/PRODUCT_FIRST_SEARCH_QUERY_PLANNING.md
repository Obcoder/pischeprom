# Product-first Search Query Planning

## Source of truth

Query plan строится `ProspectingQueryPlanner` только для human-approved `ProspectingSearchJob`. Semantic scope задают связи `prospecting_search_job_products` с ролями `primary`, `additional`, `exclude`.

Good остаётся optional commercial offer/origin. `good_product` проверяется через существующий `GoodProductMappingResolver`; duplicate pivot rows сводятся к distinct Product IDs. Packaging, origin, size и price из Good не попадают в query semantics.

## Разрешённые входы

- опубликованные `Product.rus` и `Product.eng`;
- опубликованная Product category;
- purpose `buyer_discovery` или `supplier_discovery`;
- locale и выбранная Job geography;
- reviewed criteria: segments, industries, categories;
- explicit excluded Products.

Supplier-specific `supplier_product_aliases` не являются общим approved Product vocabulary и не экспортируются. Отдельной глобальной approved Product-alias taxonomy в фактической модели нет.

## Code-owned templates

Registry: `ProspectingQueryTemplateRegistry`, version `stage09-v1`.

| Purpose | Template codes |
|---|---|
| buyer | `buyer.product_consumers_ru`, `buyer.product_users_bilingual` |
| supplier | `supplier.product_producers_ru`, `supplier.product_distributors_bilingual` |

Template code, version и canonical payload имеют SHA-256. Каждый query имеет `query_hash`; Product scope — `product_scope_hash`; полный ordered plan — `plan_hash`.

## Lifecycle

```text
approved Job
  → POST search-plan
  → review_required query rows
  → POST search-plan/approve
  → approved_by + approved_at
  → execution-time deterministic rebuild/hash comparison
  → fixed provider/profile execution
```

Изменение Product scope, template registry или query content после review блокирует execution и требует нового плана.

## Guards и bounds

- max 20 queries per Job;
- max 512 characters per query;
- only distinct, published Product rows;
- обязательный primary Product;
- DLP до persistence/execution;
- customer/supplier identities, prices, margins, PII и secrets не используются;
- `safe_objective` и free-form notes не превращаются в executable query;
- browser не передаёт raw query, provider, profile, URL, model, prompt, tool или result limit;
- human plan approval обязателен;
- retries/failovers — `0`.
