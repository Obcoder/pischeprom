# ADR-003: Local tools and provider-neutral state

- Статус: принято
- Дата: 2026-08-15
- Область: AI orchestration / state / security

## Контекст

AI Sales требует multi-step research и tool calling. ProxyAPI и AITUNNEL могут иметь разные state/capabilities. Provider response IDs не переносимы, а прямой доступ модели к ORM/SQL нарушает trust boundary.

## Решение

Run state и tools принадлежат российскому Laravel.

```text
local AgentRun/RunStep
→ ContextBuilder
→ PolicyEngine + DLP
→ AiProviderRouter
→ normalized provider response/tool call
→ local ToolRegistry + ToolExecutor
→ output minimization + DLP
→ next local sanitized step
```

Provider request является воспроизводимой функцией local sanitized state.

## Local state

Канонически сохраняются:

- run purpose, audience, actor, Unit/context;
- status, current step и idempotency/correlation keys;
- sanitized structured step inputs/results;
- tool name/version/schema hash;
- policy/model/config snapshots;
- citations;
- usage/cost;
- provider/request ID;
- safe error category;
- redaction counts/events без raw marker.

Не каноничны и по умолчанию не сохраняются:

- `previous_response_id`;
- provider Conversation/Thread;
- raw prompt;
- raw tool output;
- raw provider response;
- полный web page/email;
- secrets/PII originals.

`store=false` используется, когда поддерживается. Background provider mode не используется в первом релизе.

## Tool boundary

ToolRegistry содержит фиксированный allowlist name/version/schema/purpose/audience/classification. Модель не регистрирует tools и не выбирает PHP class, SQL, filesystem path, URL или morph type.

Каждый call повторно проверяет:

- authenticated actor/service identity;
- run purpose/audience;
- Unit и UnitBusinessContext;
- permission и object policy;
- input JSON schema;
- classification и lane;
- budget/rate/idempotency;
- subject ownership.

ToolExecutor вызывает локальный query/application service. Output проходит minimization, classification и DLP до возврата provider.

Запрещены generic:

- SQL;
- Eloquent/`Model::toArray()`;
- shell;
- filesystem;
- arbitrary outbound HTTP;
- secret/config reader;
- Entity CRUD;
- mail send.

## Write actions

Provider tool call может создать только безопасный local proposal/draft. Entity create/link, merge, communication permission, suppression change, mail send и transaction write находятся за human/legal approval boundary.

Необратимое действие не участвует в provider failover/retry.

## DLP

Layer 1 локально:

- field allowlist/classification;
- lane and purpose enforcement;
- secret/PII detectors;
- redaction/block;
- structured output validation.

Layer 2 у provider:

- дополнительный block/masking;
- не заменяет Layer 1;
- отсутствие обязательной capability → fail closed.

Raw external content помечается untrusted и никогда не становится instruction.

## Web search

Первый transport использует hosted `web_search`. Citation сохраняется как UnitSource/evidence после normalizing URL. Российский Core не загружает произвольный page content и не следует командам страницы.

## State transitions

Базовые run states:

```text
queued
→ preparing
→ policy_check
→ sent
→ requires_action | processing
→ completed

terminal:
failed | cancelled | budget_exceeded | blocked_by_policy
```

Transition выполняет local state machine с optimistic lock/idempotency. Provider text не меняет state напрямую.

## Provider switch

Router записывает:

- from/to provider;
- normalized transient reason;
- capability snapshot;
- budget decision;
- retry-safe decision;
- request correlation.

Новый request строится заново из local sanitized steps. Нельзя передавать fallback provider чужой response ID как контекст.

## Audit и retention

Audit отделён от business state и не содержит raw secret/PII. Retention охватывает local prompts/cache/traces. Support correlation использует provider request ID и hashes, а не body dump.

## Последствия

Положительные:

- provider portability;
- deterministic replay на FakeProvider;
- enforceable local security;
- контролируемый failover;
- отсутствие inbound access к Core.

Стоимость:

- нужна собственная state machine;
- structured step schemas версионируются;
- требуется tool/output classification;
- debugging строится на safe metadata, а не raw dumps.

## Отклонённые альтернативы

### Provider Threads/Conversations как база

Отклонены: lock-in, непрозрачное retention и невозможность fallback.

### previous_response_id как source of truth

Отклонён: не переносим между providers.

### Remote tool execution

Отклонено: агрегатор не получает доступ к Core.

### Raw ORM context

Отклонён из-за over-export и отсутствия field/lane controls.

## Контроль

Stage 03 tests должны использовать FakeProvider и запрещать реальную сеть. Обязательны contract, policy-denial, DLP, retry/failover и no-raw-payload assertions.
