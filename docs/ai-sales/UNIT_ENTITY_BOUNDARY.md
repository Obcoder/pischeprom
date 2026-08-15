# Граница Unit и Entity

Документ фиксирует каноническую boundary после Stage 01 и addendum.

## Определения

```text
Unit
= долговечное «Дело»: рабочая идентичность, investigation, cold contacts,
  sources, observations, contexts и агрегированное представление истории.

Entity
= конкретное физическое/юридическое лицо: проверенные реквизиты,
  transaction ownership и юридически значимые действия.
```

Unit не является черновиком Entity. Entity не является общей папкой Unit.

## Фактическая cardinality

Pivot `entity_unit`:

| Поле | Схема |
|---|---|
| `id` | PK |
| `entity_id` | FK → entities, cascade update/delete |
| `unit_id` | FK → units, cascade update/delete |
| timestamps | created_at/updated_at |

Обе модели используют `belongsToMany`. Поэтому:

- Unit может иметь несколько Entity;
- Entity может принадлежать нескольким Unit;
- одна и та же пара может повториться, потому что composite unique отсутствует.

Attach path использует `syncWithoutDetaching`, но DB invariant всё равно необходим после production duplicate report. Автоматически менять cardinality или merge records нельзя.

## Source of truth

| Данные | Source of truth | Unit behavior |
|---|---|---|
| Рабочее имя, aliases, research | Unit и будущие Unit sources/observations | хранит |
| Business role/stage/score | UnitBusinessContext | хранит по lane |
| Legal/physical identity | Entity | показывает verified summary |
| INN/KPP/OGRN/legal address | Entity | не копирует |
| Sale + lines | `sales` + `good_sale`, owner Entity | distinct read model |
| Purchase + lines | `purchases` + `good_purchase`, owner Entity | distinct read model |
| Order/Check | их existing tables, owner Entity | permissioned projection |
| Banking/payment | banking tables, owner/recipient Entity | default deny |
| Raw mail | `mail_messages` + email pivot | context attribution/reference |
| Sending/tracking | `sendings`/mailing subsystem | reference/summary |
| Unit files | existing object storage | metadata/context link, не копия |

Отдельных Contract/Invoice models в текущем коде не найдено. Если они появятся, transaction/legal owner остаётся Entity.

## Aggregation без дублирования

### Sales

```text
Unit
→ distinct entity_unit.entity_id allowed for sales context
→ sales.entity_id
→ distinct sales.id
→ permissioned aggregate/DTO
```

### Purchases

```text
Unit
→ distinct entity_unit.entity_id allowed for procurement context
→ purchases.entity_id
→ distinct purchases.id
→ permissioned aggregate/DTO
```

Требования:

- context policy применяется до relation load;
- totals считаются по unique transaction IDs;
- source row остаётся единственной изменяемой записью;
- event/timeline содержит source type/id и safe summary;
- materialized projection допустима только с rebuild/version strategy;
- shared Entity не делает sales и purchases общими между lanes.

Текущий `UnitController@index` уже считает sales через `COUNT(DISTINCT sales.id)`. Unit page загружает Entity sales/orders, но purchases projection пока отсутствует.

## Entity как legal/physical person

Entity хранит:

- name/full_name;
- main/additional classifications;
- INN/KPP/OGRN;
- legal address/country;
- bank requisites;
- DaData payload;
- contacts/locations/users;
- sales, purchases, orders, checks и banking links.

Текущие ограничения недостаточны для автоматического legal identity:

- person type не является строгим discriminator;
- реквизиты nullable;
- INN/OGRN не unique;
- `StoreEntityRequest::authorize()` возвращает true;
- нет общего duplicate/review/audit gate;
- destroy — hard delete.

Следовательно, web result с названием/ИНН/телефоном остаётся Unit observation до review.

## Existing duplicate/merge mechanisms

Механизмы существуют, но решают разные задачи:

- Entity form + DaData — ручное заполнение, не duplicate gate;
- `UserEntityResolver` — INN → email → phone для customer profile и может создать Entity;
- `TelephoneIdentityService` — нормализует/объединяет Telephone rows и иногда удаляет unreferenced placeholder Entity;
- Beeline — может создать `Клиент {phone}` и legacy Lead автоматически;
- Avito — user-triggered create/link, использует telephone match;
- Entity CRUD — синхронизирует Unit/contact relations.

Ни один механизм нельзя предоставить AI как generic `create/merge Entity`. Telephone equality не доказывает юридическое тождество.

## Human approval boundary

```text
AI/web_search
→ sanitized UnitSource + UnitObservation
→ Unit-first duplicate candidates
→ Entity duplicate candidates
→ EntityCreationProposal
→ authenticated human review
→ create new OR attach existing
→ transaction + audit
```

AI может:

- собирать публичные evidence/citations;
- предлагать тип лица и реквизиты как unverified;
- искать verified duplicate candidates;
- сформировать proposal.

AI не может:

- создавать/изменять/удалять Entity;
- attach/detach Entity;
- merge Entity/Unit;
- подтверждать реквизиты;
- переносить сделки;
- вызывать Beeline placeholder flow.

Approval требует:

- выбранный Unit и UnitBusinessContext;
- human actor и `ai_sales.entity.approve`;
- legal/physical validation по юрисдикции;
- повторный duplicate check в transaction;
- optimistic/version check proposal;
- явный выбор create/attach;
- provenance и immutable audit.

При ambiguous match никаких Entity/link изменений нет.

## Context-specific relation

`entity_unit` остаётся общим legacy link. Для определения роли Entity в lane нужен additive context link, а не изменение transaction ownership:

- ссылка на existing `entity_unit.id`;
- ссылка на UnitBusinessContext;
- relation role;
- primary flag;
- valid_from/valid_to;
- source/reviewer;
- timestamps.

Application service проверяет, что context и pivot относятся к одному Unit. Пока link не атрибутирован, он считается legacy/ambiguous и не даёт AI право читать все relations Entity.

## Contacts и mail

Direct Unit contacts и Entity contacts имеют разный смысл:

- Unit contact — найденный канал объекта рынка;
- Entity contact — канал проверенного legal/physical person;
- совпадение row допустимо, но links должны иметь provenance/context.

Существующий mail lookup объединяет direct Unit emails и Entity emails. Для shared contact это может связать письмо с несколькими Units. Новая attribution хранится отдельно с UnitBusinessContext, source/confidence/reviewer; existing email inference остаётся fallback.

Не добавлять один обязательный `unit_id` в `mail_messages` и не перепривязывать старые письма разрушительно.

## Duplicate handling

Порядок нового discovery:

1. exact source provider/external ID;
2. normalized registrable domain;
3. verified corporate email/phone;
4. exact/normalized name + location;
5. linked verified Entity requisites;
6. aliases;
7. fuzzy similarity только как suggestion.

Результат:

- exact Unit match → attach source/observation idempotently;
- ambiguous → merge/link candidate + review;
- no match → Unit creation proposal/controlled create;
- Entity match → proposal attach existing; Unit всё равно остаётся;
- no Entity → работа продолжается только на Unit.

Entity match не означает Unit merge: одно лицо может обслуживать несколько брендов/площадок.

## No-conversion lifecycle

```text
discovery
→ Unit/context
→ research/contact/match/score
→ human-reviewed Entity create/link
→ transaction belongs to Entity
→ Unit continues as dossier
```

Запрещены `convert Unit to Entity`, `delete Unit after conversion` и перенос source history в Entity. Retention/archive Unit проектируется отдельно от сделки.

## Acceptance boundary

- нет новой Lead model/table;
- каждый cold artifact имеет Unit;
- каждый context-sensitive artifact имеет UnitBusinessContext;
- Entity создаётся/связывается человеком;
- transaction rows не копируются;
- duplicate ambiguity не разрешается автоматически;
- opposite lane relations не загружаются;
- raw Entity/ORM graph не экспортируется provider.
