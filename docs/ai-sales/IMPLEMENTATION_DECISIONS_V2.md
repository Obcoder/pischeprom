# Implementation decisions V2

Статус: архитектурный handoff для Stage 03–16. Runtime на Stage 02 не реализуется.

## Precedence и supersession

Этот документ заменяет только противоречащие будущие решения [IMPLEMENTATION_DECISIONS.md](IMPLEMENTATION_DECISIONS.md).

| V1 | V2 |
|---|---|
| `AiGateway` | `AiProviderInterface` adapters + `AiProviderRouter` |
| `AiRequest` | `AiProviderRequest` |
| `AiStructuredResult` | `AiProviderResponse` + ToolCall/Citation/Usage/Error |
| обязательный foreign gateway | ProxyAPI primary, AITUNNEL fallback, direct gateway optional |
| provider continuity допустима неявно | local sanitized state — единственный source of truth |
| context_key sales/procurement only | `lane` + `role_code`; sales/procurement обязательны, остальные безопасные lanes сохраняются |

Unit-first, Entity boundary, App\Models convention, typed read queries, permissions, mail approval и Unit UI entry из V1 сохраняются.

## Жёсткие границы

- Laravel Core находится в российском контуре.
- Provider не получает DB/Redis/filesystem/shell/inbound access.
- Provider-specific JSON не выходит из adapter.
- AI не получает generic query/CRUD tools.
- External request невозможен при default config.
- ProxyAPI/AITUNNEL keys существуют только как RU server secrets.
- Local DLP обязателен до transport.
- Raw prompt/tool/provider payload не сохраняется по умолчанию.
- Entity create/link и mail send требуют отдельной human boundary.
- Новый Lead-domain запрещён.

## Директории и namespaces

```text
app/Domain/AiSales/
  Contracts/
    AiProviderInterface.php
    DlpScannerInterface.php
    ToolHandlerInterface.php
  DTO/
    Providers/
      AiProviderRequest.php
      AiProviderResponse.php
      AiProviderToolCall.php
      AiProviderCitation.php
      AiProviderUsage.php
      AiProviderError.php
      ProviderCapabilityProfile.php
    Runs/
    Tools/
    Units/
  Enums/
    AiModelProfile.php
    AiProviderErrorCategory.php
    AiRunStatus.php
    AiStepStatus.php
    BusinessLane.php
    UnitRoleCode.php
    DataClassification.php
    UnitVisibilityScope.php
  Exceptions/
  Policies/
    AiDataPolicyEngine.php
    AiExportPolicy.php
  Providers/
    AiProviderRegistry.php
    AiProviderRouter.php
    ProviderFailoverPolicy.php
  Runs/
    AgentRunStateMachine.php
    AgentRunContextBuilder.php
    AgentResultValidator.php
  Tools/
    ToolRegistry.php
    ToolExecutor.php
    ToolOutputRedactor.php
  Units/
    UnitIdentityResolver.php
    UnitDossierQuery.php
    UnitSalesQuery.php
    UnitPurchasesQuery.php
    UnitContactCandidateService.php
    EntityCreationProposalService.php

app/Infrastructure/AiSales/
  Dlp/
    LocalDlpScanner.php
  Providers/
    FakeProvider.php
    ProxyApiProvider.php
    AiTunnelProvider.php
    DirectForeignGatewayProvider.php
  Transport/
    ResponsesCompatibleTransport.php

app/Models/
  UnitBusinessContext.php
  UnitAlias.php
  UnitSource.php
  UnitObservation.php
  UnitContact.php
  EntityCreationProposal.php
  AiAgentDefinition.php
  AiAgentRun.php
  AiAgentRunStep.php
  AiToolCall.php
  AiPolicyDecision.php
  AiDataAccessLog.php
  AiRedactionEvent.php
  AiUsageRecord.php

app/Jobs/AiSales/
app/Http/Controllers/API/AiSales/
app/Http/Requests/AiSales/
app/Http/Resources/AiSales/
app/Policies/AiSales/
app/Providers/AiSalesServiceProvider.php
config/ai-sales.php
resources/js/Components/Unit/AiSales/
resources/js/Composables/aiSales/
tests/Unit/AiSales/
tests/Feature/AiSales/
```

Provider adapters находятся в Infrastructure, потому что HTTP/wire JSON — внешний detail. Models остаются в `App\Models` по фактической convention проекта. `AiSalesServiceProvider` регистрируется в `bootstrap/providers.php` только на этапе runtime implementation.

## Provider contracts

Логический contract:

```php
interface AiProviderInterface
{
    public function name(): string;

    public function capabilities(): ProviderCapabilityProfile;

    public function send(AiProviderRequest $request): AiProviderResponse;
}
```

Domain services зависят от `AiProviderRouter`, не от конкретного adapter. Registry принимает заранее зарегистрированные providers из container/config; runtime-модель не может добавить driver.

### AiProviderRequest

Только provider-neutral поля:

- run/step correlation UUID;
- logical model profile;
- sanitized structured messages/steps;
- versioned response schema;
- allowlisted tool schemas;
- hosted web search option;
- `store=false` requirement;
- timeout/token/step budget;
- idempotency key;
- policy/schema snapshot hashes.

Запрещены Eloquent objects, closures, credentials, arbitrary URL/headers и raw database payload.

### AiProviderResponse

Содержит:

- normalized status;
- structured output;
- typed tool calls;
- normalized citations;
- usage;
- provider/model/request IDs;
- safe error;
- capability/profile metadata.

Raw provider body остаётся transient внутри adapter и уничтожается после validation, если отдельная incident policy не разрешила quarantined capture.

## Router

`AiProviderRouter`:

1. проверяет global/external/provider feature flags;
2. выбирает primary из config;
3. проверяет capability profile;
4. вызывает adapter;
5. нормализует error;
6. передаёт решение `ProviderFailoverPolicy`;
7. при разрешении один раз вызывает fallback;
8. возвращает normalized response;
9. пишет safe provider transition/usage metadata.

Allowlisted transient categories:

- connect timeout;
- read timeout до получения результата;
- provider unavailable;
- HTTP 429 с разрешённым retry budget;
- HTTP 502/503/504;
- временный capability endpoint failure только при cached compatible profile.

Не transient: policy/DLP/auth/schema/400/401/403, invalid tool, subject mismatch, budget, application exception и любое post-side-effect состояние.

## Config и feature flags

Будущий `config/ai-sales.php`:

```text
enabled=false
external_requests_enabled=false
primary_provider=proxyapi
fallback_provider=aitunnel
fallback_enabled=false
hosted_web_search_enabled=false
outreach_mode=approval_required
queue_connection=<explicit>
queue=ai-sales
limits.*
model_profiles.*
providers.proxyapi.*
providers.aitunnel.*
providers.fake.*
dlp.local_required=true
dlp.provider_block_required=true
logging.store_raw_payloads=false
```

Env names можно подготовить без значений:

```text
AI_SALES_ENABLED
AI_SALES_EXTERNAL_REQUESTS_ENABLED
AI_SALES_PRIMARY_PROVIDER
AI_SALES_FALLBACK_PROVIDER
AI_SALES_FALLBACK_ENABLED
PROXYAPI_BASE_URL
PROXYAPI_API_KEY
AITUNNEL_BASE_URL
AITUNNEL_API_KEY
```

Base URLs не должны иметь production default, который случайно включает egress. Реальные значения не коммитятся. Staging/prod keys и budgets различаются.

## Model profiles

`AiModelProfile`:

- `HIGH_VOLUME_EXTRACTION`;
- `STANDARD_RESEARCH`;
- `COMPLEX_RESEARCH`;
- `VALIDATION`;
- `OUTREACH_DRAFTING`;
- `REPLY_TRIAGE`.

Config mapping задаёт provider-specific model ID и capabilities. Run сохраняет logical profile, actual provider/model и mapping version.

## Local state

### Control-plane models

| Model/table | Назначение |
|---|---|
| `AiAgentDefinition / ai_agent_definitions` | versioned agent purpose/tools/policy |
| `AiAgentRun / ai_agent_runs` | actor, Unit/context, purpose, status, budget |
| `AiAgentRunStep / ai_agent_run_steps` | local sanitized state transition |
| `AiToolCall / ai_tool_calls` | typed tool input/result summary/idempotency |
| `AiPolicyDecision / ai_policy_decisions` | allow/deny reason и snapshots |
| `AiDataAccessLog / ai_data_access_logs` | какие classified DTO fields были read/exported |
| `AiRedactionEvent / ai_redaction_events` | detector/category/count/hash без raw value |
| `AiUsageRecord / ai_usage_records` | tokens/search/tool/cost/provider/model |

Ни одна таблица не хранит `previous_response_id` как каноническую связь. Допустим provider request ID только для support correlation.

### Status

Run:

```text
queued, preparing, policy_check, sent, requires_action, processing,
completed, failed, cancelled, budget_exceeded, blocked_by_policy
```

Transitions выполняет `AgentRunStateMachine` с optimistic lock и immutable step history.

## DB conventions

- snake_case plural tables;
- unsigned bigint local PK как в существующем проекте;
- отдельный UUID/correlation ID для provider-facing references;
- explicit foreign keys/indexes;
- PHP backed enums + varchar columns, не MySQL enum;
- decimal для confidence/cost/money, не float;
- JSON только для versioned sanitized structures;
- timestamps и actor IDs;
- unique idempotency keys;
- audit rows не cascade-delete вместе с Unit/Entity;
- nullable FK + preserved snapshot для deleted actor, где необходимо;
- никакой generic Eloquent morph relation для AI tools;
- migrations additive, reversible и capability-checked;
- raw provider payload/prompt columns не создавать.

## UnitBusinessContext V2

Будущая `unit_business_contexts`:

| Поле | Решение |
|---|---|
| `id` | PK |
| `unit_id` | FK/index |
| `lane` | sales, procurement, logistics, service, internal |
| `role_code` | customer, supplier, prospective_customer, prospective_supplier, manufacturer, carrier, service_provider, other |
| `stage` | new, researching, qualified, review_required, approved_for_contact, contacted, engaged, dormant, rejected, do_not_contact, archived |
| `status` | active, paused, closed, archived |
| `confidence` | nullable decimal |
| `owner_user_id/reviewer_user_id` | nullable FKs |
| `source` | short internal provenance code |
| `first_activity_at/last_activity_at` | nullable datetimes |
| timestamps | required |

Начальный logical unique — `(unit_id, lane, role_code)`. Перед DB unique нужен production duplicate report. Context не hard-delete-ится.

Compatibility:

- `is_customer=true` → sales/customer or prospective_customer candidate, exact role требует review;
- `is_supplier=true` → procurement/supplier or prospective_supplier candidate;
- оба flags → минимум два lane candidates;
- flags false → context не угадывается.

V1 automatic mapping flags прямо в customer/supplier уточнён: flag не различает действующую и prospective роль, поэтому backfill создаёт reviewable context, а не притворяется точным.

## Unit dossier tables

### unit_aliases

- unit_id;
- alias и normalized_alias;
- alias_type;
- unit_source_id;
- verified_at/reviewer;
- classification/scope;
- timestamps;
- unique `(unit_id, normalized_alias)` после duplicate report.

### unit_sources

- unit_id;
- nullable context_id для shared source;
- source type/provider/external ID;
- URL/canonical URL;
- unique source_key/hash;
- accessed_at;
- classification/visibility scope;
- citation metadata;
- timestamps.

Credential/provider token никогда не является source metadata.

### unit_observations

- required UnitSource;
- Unit и nullable context для shared_public;
- claim key + versioned value JSON;
- status `unverified/verified/contradicted/stale/rejected/superseded`;
- confidence;
- classification/scope;
- observed/accessed dates;
- reviewer/model/rules versions;
- timestamps.

Fact without source не получает verified.

### unit_contacts

Не дублирует channel value. Строка ссылается ровно на один existing `email_id`, `telephone_id` или `uri_id` и хранит:

- Unit/context;
- UnitSource;
- channel role;
- verification/review state;
- classification/scope;
- first/last seen;
- reviewer;
- timestamps.

До review используется candidate state. Контакт не создаёт Entity.

### unit_events

Append-only context timeline с event type, safe summary, occurred_at, actor/source, correlation и allowlisted source kind/id. Eloquent generic morph access отсутствует; resolver поддерживает только зарегистрированные source kinds. Raw mail/call/provider payload не копируется.

## Entity context и approval

Existing `entity_unit` сохраняется. Поздний additive `entity_unit_business_context` ссылается на pivot и context и хранит relation role, primary, validity, source и reviewer.

`EntityCreationProposalService`:

- принимает Unit/context + evidence;
- ищет duplicates;
- сохраняет proposal;
- не создаёт Entity.

`ApproveEntityCreationProposal` требует human actor, permission, optimistic lock, повторный duplicate check и transaction/audit. AI jobs не получают approval permission.

## Query services и DTO

- `UnitDossierQuery` — только allowlisted shared/context profile;
- `UnitSalesQuery` — sales context, distinct Sale IDs;
- `UnitPurchasesQuery` — procurement context, distinct Purchase IDs;
- `UnitIdentityResolver` — source/domain/contact/name-location evidence;
- `EntityDuplicateQuery` — verified candidate summary, no mutation.

DTO classes находятся в `DTO/Units`. Они не используют `Model::toArray()`. Classification registry перечисляет каждое поле; unknown field блокируется.

## Local Policy/DLP/tools

### Policy inputs

- actor/service identity;
- purpose/audience;
- Unit/context;
- tool/version;
- requested fields;
- classifications/scopes;
- run/step budget;
- provider capabilities.

### Initial tool allowlist

- `catalog.search_goods`;
- `catalog.get_good_public_summary`;
- `pricing.get_customer_offer_summary`;
- `units.find_duplicates`;
- `units.get_sanitized_dossier_profile`;
- `units.get_shared_public_observations`;
- `units.get_context_summary`;
- `entities.find_verified_duplicates`;
- `entities.get_sanitized_legal_summary`;
- `sales.get_aggregated_demand_patterns`;
- `purchases.get_aggregated_supply_capabilities`;
- `geo.get_supported_regions`.

Tool handlers получают local IDs from validated schema. Tools не создают Entity, не отправляют mail и не возвращают raw rows противоположного lane.

## Authorization

New route group:

```php
Route::middleware(['auth:sanctum', 'throttle:ai-sales'])
    ->prefix('ai-sales')
    ->name('api.ai-sales.')
    ->group(/* explicit routes */);
```

Permissions сохраняют V1 basis:

- `ai_sales.view`;
- `ai_sales.sales.view`;
- `ai_sales.procurement.view`;
- `ai_sales.research.run`;
- `ai_sales.observation.review`;
- `ai_sales.entity.propose`;
- `ai_sales.entity.approve`;
- `ai_sales.mail.draft`;
- `ai_sales.mail.approve`;
- `ai_sales.mail.send`;
- `ai_sales.runs.cancel`;
- `ai_sales.audit.view`.

Admin bypass должен fail closed при exception и проверять active user. Frontend capabilities только отображают server decision.

## Routes

Initial local API surface:

```text
POST   /api/ai-sales/runs
GET    /api/ai-sales/runs/{run}
POST   /api/ai-sales/runs/{run}/cancel
GET    /api/ai-sales/units/{unit}/contexts
GET    /api/ai-sales/units/{unit}/dossier
POST   /api/ai-sales/units/{unit}/observations/{observation}/review
POST   /api/ai-sales/units/{unit}/entity-proposals
POST   /api/ai-sales/entity-proposals/{proposal}/approve
POST   /api/ai-sales/entity-proposals/{proposal}/reject
```

Нет provider webhook/callback, generic tool endpoint, SQL endpoint и direct mail send route. Internal tool execution вызывается orchestration service, не публичным provider request.

## UI entry points

Основная страница: `resources/js/Pages/Ameise/Unit.vue`.

Future components:

- `AiSalesContextSelector.vue`;
- `AiSalesContextSummary.vue`;
- `AiSalesSourcesPanel.vue`;
- `AiSalesObservationsPanel.vue`;
- `AiSalesTimeline.vue`;
- `AiSalesRunPanel.vue`;
- `AiSalesEntityProposalDialog.vue`;
- `AiSalesMailDraftPanel.vue`.

Provider/fallback/policy state показывается safe badges без request body. Раздел `Продажи → AI-поиск покупателей` может быть отдельной entry page, но результат всегда открывает Unit.

## Mail

AI создаёт только `outreach_draft` с UnitBusinessContext. Direct `UnitMailController::send` не является AI tool.

Перед approved send:

- communication permission/legal basis;
- recipient/context ownership;
- suppression/unsubscribe/bounce/complaint;
- DLP/lane;
- campaign/domain limits;
- approval actor;
- idempotency;
- kill switch.

MailMessage attribution к Unit/context добавляется additive; existing email inference остаётся fallback.

## Queue

Очередь `ai-sales` и connection задаются config. Job получает run/step IDs, повторно проверяет feature flags/policy и не сериализует dossier graph.

Обязательны:

- ShouldBeUnique/idempotency;
- retry caps/backoff;
- timeout;
- budget reservation;
- cancellation;
- provider failover максимум один;
- no retry after side effect;
- redacted logs.

Worker/scheduler не запускается AI model и не создаётся на Stage 02.

## Tests

### Unit

- normalized DTO serialization excludes unknown/raw fields;
- Policy Engine cross-lane deny;
- local DLP secret/PII/unclassified block;
- ToolRegistry rejects unknown/version/schema;
- Router primary success;
- Router allowlisted transient fallback;
- Router refuses policy/auth/schema/non-idempotent fallback;
- local state rebuild ignores `previous_response_id`;
- Entity proposal never writes Entity.

### Feature

- unauthenticated 401;
- missing permission 403;
- wrong Unit/context 403;
- sales cannot read procurement and vice versa;
- FakeProvider only with external requests disabled;
- no outbound HTTP assertion;
- run budget/kill switch;
- human Entity approval audit;
- mail remains draft.

### Contract

Adapters use Laravel HTTP fakes and fixture JSON. Tests assert `store=false`, sanitized body, header redaction, usage/citation/error normalization and no payload logging.

## Точный handoff Stage 03

Рекомендуемый Stage 03 scope:

1. Начать от отдельного Stage 02 commit и clean tree.
2. Добавить characterization tests, не менять legacy Unit/Entity behavior.
3. Создать `config/ai-sales.php` с всеми flags default false и без secrets.
4. Реализовать provider-neutral DTO/contracts, Registry, Router и FakeProvider.
5. Реализовать local Policy/DLP/Tool interfaces с fail-closed stubs.
6. Тестировать только FakeProvider/HTTP fakes; запретить real egress.
7. Если Stage 03 явно разрешает migrations — только additive control-plane/context subset с dry-run/backfill plan; иначе migrations не создавать.
8. Не реализовывать outreach send, Entity auto-create, generic tools, legacy Lead migration или production provider activation.
9. Зафиксировать отдельный commit и остановиться до Stage 04.

Фактическая инструкция Stage 03 имеет приоритет по точному объёму, но не может нарушать ADR-001–003 и addendum.
