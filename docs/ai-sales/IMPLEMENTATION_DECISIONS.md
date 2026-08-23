# Решения для последующих этапов

Этот документ фиксирует design decisions, но не реализует их. Stage 01 не добавляет модели, миграции, routes, SDK или runtime behavior.

## Неподвижные инварианты

1. `Unit` / `units` — единственный aggregate root досье и холодной работы.
2. `Entity` / `entities` — точный юридический/физический transaction owner.
3. Unit не конвертируется в Entity и не удаляется после квалификации.
4. Новый `Lead` model/table/domain не создаётся.
5. Existing `Lead` — legacy compatibility source, но не основа AI workflow.
6. `Sale`, `Purchase`, `Order`, `Check` и banking остаются на Entity.
7. Sales и procurement — разные security compartments даже внутри одного Unit.
8. AI пишет observations/proposals/drafts; юридические и внешние действия подтверждает человек.
9. Raw Eloquent models, secrets и generic polymorphic tools не передаются AI.
10. Изменения схемы выполняются additive expand/backfill/validate/switch; destructive cleanup — отдельный поздний этап.

## Ответы на обязательные вопросы

| Вопрос | Решение |
|---|---|
| Фактическая Unit table/model? | `units` / `App\Models\Unit` |
| Что уже принадлежит Unit? | name/role flags, прямые contacts/URI, locations, labels/fields/industries/classifications/stages, consumptions, quotations, manufactures, supplier pipeline, files и ссылки на communications |
| Как Entity связана с Unit? | M:N через `entity_unit` |
| Может ли Unit иметь несколько Entity? | да; обратное тоже возможно |
| Где source of truth Sales/Purchases? | `sales` + `good_sale` и `purchases` + `good_purchase`, каждая операция принадлежит Entity |
| Как агрегировать без дублей? | typed read queries через distinct Entity IDs и distinct transaction IDs, без записи копий в Unit/context |
| Какие contacts/mail reuse? | `emails`, `telephones` и существующие Unit/Entity pivots; `MailMessage`/`Sending` сохраняются, добавляется context attribution |
| Где роли сегодня? | customer/supplier flags и pipeline/stages у Unit; classifications/logistics roles у Entity; semantics различаются |
| Минимум для UnitBusinessContext? | additive table с unique Unit+sales/procurement, status/owner/dates; compatibility projection старых flags |
| Как web discoveries dedupe? | сначала UnitSource/domain/contact/name+location resolver, затем human candidate review; Entity ищется после выбора Unit |
| Как Entity остаётся human-controlled? | AI только создаёт proposal; отдельный permissioned approval service выполняет create/attach с duplicate preview и audit |

## Namespace и директории

Новый bounded context:

```text
app/Domain/AiSales/
  Actions/
  Contracts/
  DTO/
  Enums/
  Events/
  Exceptions/
  Policies/
  Queries/
  Services/
app/Jobs/AiSales/
app/Models/UnitBusinessContext.php
app/Models/UnitSource.php
app/Models/UnitObservation.php
app/Models/UnitContactCandidate.php
app/Models/EntityCreationProposal.php
app/Models/AiSalesRun.php
app/Models/AiSalesEvent.php
app/Models/AiSalesMailDraft.php
app/Http/Controllers/API/AiSales/
app/Http/Requests/AiSales/
app/Http/Resources/AiSales/
app/Policies/AiSales/
config/ai-sales.php
resources/js/Components/Unit/AiSales/
resources/js/Composables/aiSales/
tests/Feature/AiSales/
tests/Unit/AiSales/
```

Модели остаются в принятом проектом `App\Models`. Domain services не должны зависеть от HTTP, Inertia или конкретного AI SDK.

## Минимальная схема UnitBusinessContext

Планируемая `unit_business_contexts`:

| Поле | Решение |
|---|---|
| `id` | unsigned bigint primary key |
| `unit_id` | FK к `units.id`, indexed |
| `context_key` | varchar; первоначально `sales` или `procurement` |
| `status` | varchar; `new`, `researching`, `qualified`, `active`, `paused`, `closed`, `archived` |
| `owner_user_id` | nullable FK к users |
| `last_activity_at` | nullable datetime |
| `next_action_at` | nullable datetime/index |
| `created_by`, `updated_by` | nullable user FKs |
| timestamps | обязательны |

Ограничение `unique(unit_id, context_key)`. DB enum не использовать: PHP backed enum и validation дают совместимую эволюцию. Context не hard-delete-ится; используется `archived`. Critical fields не прятать в JSON.

На первом expand этапе существующие flags остаются. Идемпотентный backfill:

- `is_customer=true` → sales context;
- `is_supplier=true` → procurement context;
- оба true → две строки;
- оба false → context не угадывается.

Пока read path не переключён и не проверен, запись context синхронно поддерживает старый flag как compatibility projection. Удалять flags на ранних этапах нельзя.

## Context-specific Entity

Существующий `entity_unit` сохраняется. Чтобы отметить, какая Entity действует в lane, добавить позже additive pivot `entity_unit_business_context`:

- `entity_unit_id`;
- `unit_business_context_id`;
- `role`, первоначально `transaction_owner` или `contact_only`;
- `is_primary`;
- `valid_from`/`valid_to`;
- `source` и `approved_by`;
- timestamps;
- unique на активную смысловую связь.

Application service проверяет, что `entity_unit.unit_id` совпадает с `unit_business_context.unit_id`. До backfill отсутствие context link означает legacy/ambiguous, а не доступ ко всем данным.

## Sources, observations и contacts

### UnitSource

`unit_sources` хранит `unit_id`, `unit_business_context_id`, provider, canonical URL, external ID, fetched_at, sanitized metadata и уникальный `source_key`. `source_key` — стабильный hash нормализованного provider/external identity, а не секретный credential.

### UnitObservation

`unit_observations` хранит source/context, `field_key`, typed `value_json`, normalized value hash, confidence, evidence excerpt, observed_at, state `proposed/accepted/rejected/superseded` и reviewer. Raw page/content хранится отдельно с retention/classification и не используется как команда.

### UnitContactCandidate

Найденные email/phone сначала попадают в `unit_contact_candidates` с type, normalized value, source, context, confidence и review state. Только reviewer или детерминированное подтверждённое правило вызывает service, который reuses/attaches существующий `Email`/`Telephone`. Непроверенный контакт не создаёт Entity.

## Unit-first identity resolver

Точный service contract:

```text
App\Domain\AiSales\Services\UnitIdentityResolver
  resolve(UnitDiscoveryIdentity $identity): UnitMatchResult

UnitMatchResult.status = matched | ambiguous | new_candidate
UnitMatchResult.candidates[] = unit_id + score + evidence[]
```

Порядок evidence:

1. `UnitSource.source_key` exact;
2. registrable domain exact;
3. verified normalized email/phone;
4. normalized name + city/building/industry;
5. fuzzy score только для ranking.

Resolver не создаёт и не merge-ит. `AttachObservationToUnit` принимает только `matched`; `ambiguous` создаёт review task. New Unit creation также идёт через idempotent proposal/approval, если будущий stage не задаст более узкое доверенное правило.

Entity candidate lookup выполняется после Unit resolution:

1. exact normalized INN/OGRN для допустимого person type;
2. verified contact;
3. name/address only as weak evidence.

Совпадение показывает candidates, но не attach/merge автоматически.

## Human-controlled Entity service

`EntityCreationProposal` содержит Unit/context, proposed requisites, source/evidence, duplicate candidates, state, proposer, reviewer и decision timestamps.

Только:

```text
App\Domain\AiSales\Actions\ApproveEntityCreationProposal
```

может вызвать общий Entity writer. Preconditions:

- authenticated human;
- permission `ai_sales.entity.approve`;
- proposal unchanged/version check;
- validated person type/requisites;
- повторный duplicate search в той же transaction;
- явный выбор create или attach existing;
- actor/evidence/before-after audit.

AI gateway и queue jobs не получают это permission. Legacy Beeline placeholder service не переиспользуется.

## Read models транзакций

Точные query services:

```text
App\Domain\AiSales\Queries\UnitSalesQuery
App\Domain\AiSales\Queries\UnitPurchasesQuery
App\Domain\AiSales\Queries\UnitOrdersQuery
```

Каждый принимает authenticated actor и `UnitBusinessContext`, проверяет lane/policy, выбирает разрешённые context Entity links и применяет unique IDs. DTO по умолчанию возвращает агрегаты; строки/цены выдаются только отдельным permission. Banking не включается ни в один общий dossier summary.

## Mail, drafts и events

Не добавлять единственный mandatory `unit_id` в существующий `mail_messages`. Additive attribution table `mail_message_unit_business_context`:

- mail message FK;
- UnitBusinessContext FK;
- `relation_type`: sender/recipient/thread/reference;
- `attribution_source`: explicit/inferred/reviewed;
- confidence;
- linked_by/reviewed_by;
- timestamps;
- composite unique для одной смысловой attribution.

`AiSalesMailDraft` всегда имеет Unit/context, recipient candidate, template/version, rendered body hash, approval state, approver и idempotency key. После отправки связывается с фактическим MailMessage/Sending. Ни draft creation, ни LLM tool не вызывает SMTP.

`AiSalesEvent` — append-only timeline с Unit/context, actor type/id, event type, subject allowlist, correlation/idempotency key и sanitized payload. Generic morph relation не использовать; допустимые subject types выражаются явными nullable FKs или enum+validated resolver.

Legacy mail attribution через shared emails остаётся compatibility fallback с `inferred` и не даёт права на cross-lane prompt.

## Authorization

Новые routes помещаются под `auth:sanctum` и throttling. Минимальные permissions:

- `ai_sales.view`;
- `ai_sales.sales.view`;
- `ai_sales.procurement.view`;
- `ai_sales.research.run`;
- `ai_sales.observation.review`;
- `ai_sales.entity.propose`;
- `ai_sales.entity.approve`;
- `ai_sales.mail.draft`;
- `ai_sales.mail.approve`;
- `ai_sales.mail.send`.

Policies проверяют и permission, и конкретный context. Новые controllers используют FormRequests и Resources/DTO; raw model response запрещён. Route tests должны доказывать 401, 403, cross-lane denial и отсутствие mass assignment.

Существующие открытые Unit/Entity/mail routes закрываются отдельным совместимым change: сначала usage inventory, затем auth in report-only/test environment, после этого controlled rollout. Нельзя молча сломать внешнего клиента.

## AI gateway и jobs

Будущий provider-neutral contract:

```text
App\Domain\AiSales\Contracts\AiGateway
App\Domain\AiSales\DTO\AiRequest
App\Domain\AiSales\DTO\AiStructuredResult
```

Provider adapter находится под `Infrastructure` внутри bounded context или отдельного `app/Infrastructure/AiSales`. Existing Yandex price-list AI config/credentials не переиспользуются неявно.

Jobs в `App\Jobs\AiSales` получают IDs, а не сериализованный sensitive graph. Обязательны unique/idempotency key, context policy re-check at execution, retry caps, timeouts, budget reservation, cancellation и redacted failure logs. Очередь `ai-sales` получает отдельный worker/health check; agent не запускает scheduler/worker сам.

## UI integration

Единственная рабочая карточка остаётся `resources/js/Pages/Ameise/Unit.vue`:

- context selector в header;
- `AiSalesContextSummary`;
- `AiSalesSourcesPanel` и observation review;
- `AiSalesTimeline`;
- `AiSalesEntityProposalDialog`;
- `AiSalesMailDraftPanel`.

Sales/Purchases/contacts/mail panels получают выбранный context и server-provided capabilities. Frontend flags не считаются authorization. Отдельную страницу «AI Leads» не создавать.

## Rollout и Stage 02

Рекомендуемый порядок следующего этапа:

1. Добавить characterization/authorization tests для текущих Unit↔Entity flows.
2. Выполнить read-only production reports: duplicate pivots, duplicate Unit identities, Entity requisites и legacy Lead consistency.
3. Ввести permissions/policies для нового bounded context.
4. Additive migration/model `UnitBusinessContext` и unique invariant.
5. Идемпотентно backfill contexts из Unit flags, с dry-run/report и без удаления flags.
6. Добавить typed sales/procurement read models и cross-lane tests.
7. Добавить UnitSource/Observation/ContactCandidate и Unit-first resolver.
8. Добавить Entity proposal/approval path; не включать AI SDK.
9. Встроить context selector и review UI в Unit card.
10. Добавить mail attribution schema, но оставить старый inference read path до измеренного backfill.

Stage 02 не должен:

- удалять/переименовывать `Lead`;
- переносить transactions на Unit;
- автоматически создавать Entity;
- отправлять почту;
- подключать внешний AI к production данным;
- устранять unrelated Pint/test baseline массовым форматированием.

Exit criteria: additive schema rolled out safely, backfill report сходится с flags, policies и cross-lane negative tests проходят, duplicate ambiguities видимы пользователю, rollback отключает новый read path без потери старых данных.
