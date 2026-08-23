# Delta-аудит Unit

Проверено на `8f7b619812c6d92ba230980cebe8455f76f74bea`. Документ дополняет [UNIT_DOMAIN_MAP.md](UNIT_DOMAIN_MAP.md), не заменяя его.

## Фактическая реализация

| Область | Реализация |
|---|---|
| Model | `App\Models\Unit` |
| Table | `units` |
| Первичная migration | `2024_11_06_154418_create_units_table.php` |
| Role flags migration | `2026_06_08_120000_add_partner_role_flags_to_units_table.php` |
| API CRUD | `App\Http\Controllers\API\UnitController` |
| Relations | `API\UnitRelationController`, `Web\UnitRelationSyncController` |
| Files | `API\UnitFileController` |
| Mail | `API\UnitMailController` |
| Web/Inertia | `App\Http\Controllers\Web\UnitController` |
| Resource | `App\Http\Resources\UnitResource` |
| Pages | `resources/js/Pages/Ameise/Units.vue` и `Unit.vue` |
| Components | `resources/js/Components/Unit/*` |

`units` содержит только `id`, обязательный индексированный, но не уникальный `name`, `is_customer`, `is_supplier` и timestamps. SoftDeletes, aliases, owner, source, confidence, status и audit actor отсутствуют.

## Почему Unit уже похож на «Дело»

Unit уже объединяет:

- рабочее название;
- два одновременно допустимых customer/supplier flags;
- несколько Entity;
- прямые email/telephone/URI;
- города и здания;
- labels, fields, industries и classifications;
- stages и supplier pipeline card/notes;
- consumptions, quotations и manufactured/products relations;
- object-storage files;
- calls, legacy Leads и некоторые integration chats;
- mail/sendings через прямые и Entity contacts;
- продажи и заказы связанных Entity в UI.

Ни один другой существующий aggregate не собирает столько dossier-связей. Entity, Email и legacy Lead уже уже по назначению и не подходят на роль общей папки исследования.

## CRUD, routes и authorization

API:

- `Route::apiResource('units', UnitController::class)`;
- вложенные attach/detach routes для URI, telephone, building, industry, manufacture, label, field, city и Entity;
- file list/upload/move/rename/delete;
- mail list/send.

Web:

- `GET /Ameise/units`;
- `GET /Ameise/unit/{unit}`;
- legacy `POST /api/unit/store` и relation sync endpoints.

`route:list -v` подтверждает только middleware `api` для Unit API и `web` для страницы. `UnitPolicy`, Unit-specific Gate registration и domain permissions отсутствуют. `Web\UnitController` передаёт `orders.view/create=true` напрямую.

`API\UnitController::store` вызывает `Unit::create($request->all())`, затем sync relations и создаёт placeholder object в Yandex storage. Update валидирует name/flags. Destroy выполняет hard delete.

Перед Stage 03 новые endpoints должны находиться в отдельной authenticated group. Закрытие legacy routes — отдельный compatibility rollout, а не побочный эффект AI-модуля.

## Resource/DTO

`UnitResource` возвращает только id, name и timestamps; relation mappings закомментированы. Основные controllers отдают raw loaded Unit graph.

Это непригодно как AI export boundary:

- eager-loaded relations шире требуемого purpose;
- нет field classification;
- нет lane filtering;
- нет provenance;
- raw Entity может принести sensitive поля.

Stage 03 должен создавать отдельные immutable DTO/Resources под каждый tool, а не расширять raw `UnitResource` до «всего досье».

## UI

`Ameise/Unit.vue` использует существующую карточку как единое workspace:

- `UnitOverviewCard`;
- `UnitTradeTabsCard`;
- `UnitManufacturesCard`;
- `UnitSendingsCard`;
- `UnitCallsCard`;
- `UnitSalesCard`.

Overview содержит Info, ОКВЭД, Contacts, Files, Buildings и Entity cards. Из этой же карточки можно изменять/удалять Unit, прикреплять Entity и напрямую создавать/редактировать/удалять Entity.

Trade tabs показывают consumptions, quotations и orders. Orders дедуплицируются во frontend по id. Sales отображаются через `unit.entities[].sales`. Purchases в Unit page отсутствуют.

Нет:

- context switch;
- aliases/sources/observations panels;
- review queue;
- prospect score;
- unified timeline;
- AI run/provider/cost view;
- human Entity proposal вместо прямого CRUD.

Правильная UI entry point для будущих возможностей — эта страница и `Components/Unit/AiSales/*`. Отдельная карточка Lead запрещена.

## Policies, observers, events и jobs

Поиск в `app/Policies`, `app/Observers`, `app/Events`, `app/Listeners`, `app/Jobs` и `AppServiceProvider` показал:

- UnitPolicy отсутствует;
- Unit observer отсутствует;
- Unit domain events/listeners отсутствуют;
- Unit-specific queue jobs отсутствуют;
- отдельного audit/history pipeline нет.

Mail sync, telephony, MAX/Avito и supplier workflows создают связанные записи, но не формируют нормализованный UnitEvent. Их следует читать как source events и проецировать в context timeline, не копируя raw payload.

## Поиск, фильтры и дедупликация

Backend `Unit::scopeSearch` выполняет только `name LIKE %query%`. `UnitController@index` умеет дополнительный `good_id` через quotations и считает distinct sales. UI дополнительно фильтрует уже загруженный набор по labels, fields, cities и industries.

Отсутствуют:

- canonical/normalized name;
- registrable domain index;
- aliases;
- exact identity keys;
- weighted candidate resolver;
- merge candidates/review;
- automatic duplicate warning при Unit create.

`UnitRelationController` частично предотвращает повторные links через `syncWithoutDetaching`. Это не является dossier dedupe. `unit_uri` имеет composite unique, но `entity_unit`, `email_unit` и `telephone_unit` — нет.

## Связь с Entity

`Unit::entities()` и `Entity::units()` используют `belongsToMany` через `entity_unit`:

- несколько Entity у Unit — да;
- несколько Unit у Entity — да;
- duplicate pair на уровне БД — возможен.

Attach использует `syncWithoutDetaching`, Entity create/update использует `sync`. Relation не хранит role, lane, validity, source или reviewer.

## Contacts, mail и files

### Reuse

Существующие канонические channel rows следует сохранить:

- `Email`: lowercases address, имеет source/is_active/verified_at/last_seen_at и SoftDeletes;
- `Telephone`: number unique в текущей применённой схеме; есть общий identity service;
- `Uri`: address и validity-related flags;
- `email_unit`, `telephone_unit`, `unit_uri`;
- `MailMessage`/`email_mail_message`;
- `Sending`;
- `PhoneCall`;
- MAX/Avito chat records.

### Gaps

- Email/telephone pivots не содержат context, source, confidence, role или reviewer.
- URI address не canonicalized и не unique глобально.
- Email address не unique на уровне исходной migration.
- MailMessage связан с Unit только через shared email inference.
- `Sending` связан только с Email.
- PhoneCall имеет nullable Unit/Entity/Lead, которые могут расходиться.
- Unit files находятся в object storage без DB metadata, classification, context и audit.

Новый найденный corporate email/phone сначала должен быть contact candidate/observation с source/context. После review application service переиспользует существующую Email/Telephone row и создаёт context-aware link. Дублировать адрес в отдельной автономной lead_contacts таблице нельзя.

## Good, Product, Sale и Purchase

Прямо на Unit:

- `products` / `manufactures`;
- `Consumption → Product`;
- `Quotation → Good`;
- field producer/consumer matches.

Через Entity:

- `Sale → Entity` и `good_sale`;
- `Purchase → Entity` и `good_purchase`;
- Orders/Checks/banking.

Unit sales count уже использует correlated join и `COUNT(DISTINCT sales.id)`. UI sales и orders собирает из Entity. Purchase projection отсутствует.

Будущие queries должны:

1. авторизовать actor + UnitBusinessContext;
2. выбрать разрешённые Entity links;
3. выбрать distinct transaction IDs;
4. вернуть минимальный DTO/aggregate;
5. не записывать transaction rows в Unit tables.

## Ответы на delta-вопросы

### 1. Может ли Unit хранить неточное рабочее название?

Да. `name` — обычная обязательная строка без unique/canonical/legal semantics. Код не требует совпадения с Entity. Нужны aliases/provenance, чтобы уточнение не уничтожало историю.

### 2. Есть ли aliases?

Нет ни таблицы, ни модели, ни relation/UI. URI и Entity names не являются aliases Unit.

### 3. Где хранить непроверенные факты и источники?

Сейчас безопасного typed места нет. URI, labels, fields, industries и files — только частичные носители; они не хранят claim state/evidence. Требуются `unit_sources` и `unit_observations`, связанные с Unit/context.

### 4. Где хранить найденные корпоративные контакты?

До review — в context-aware UnitContactCandidate/observation. После review — переиспользовать `emails`/`telephones`/`uris` через отдельный provenance/context link. Entity не создаётся.

### 5. Можно ли безопасно добавить business contexts?

Да, additive table не конфликтует с текущими columns. Сначала нужны duplicate/data reports, FK/unique design, compatibility mapping flags и policies. Stage 02 migration не создаёт.

### 6. Как не копировать Sale/Purchase внутрь Unit?

Использовать typed read models через `entity_unit` и distinct transaction IDs. Timeline хранит ссылку/проекцию, а не вторую transaction row.

### 7. Какие связи уже выполняют будущие функции?

| Будущая функция | Частичный existing эквивалент |
|---|---|
| unit_sources | Unit URI, files, `Email.source` |
| unit_observations | labels, fields, industries, classifications, consumptions, quotations |
| unit_contacts | Email, Telephone, URI и pivots |
| unit_events | stage_unit, supplier card, MailMessage/Sending, PhoneCall, Lead, MAX/Avito records |
| unit_notes | supplier pipeline notes; MailMessageNote относится только к mail |
| unit_good_matches | Consumption/Product, Quotation/Good, product_unit/action |

Это данные для reuse/projection, но не полные эквиваленты из-за отсутствия context/provenance/status/classification.

### 8. Что реально отсутствует?

Aliases, typed source/evidence, observation lifecycle, Unit contact provenance link, UnitBusinessContext, prospect score snapshots, merge candidates, unified events, context mail attribution, policy/DTO boundary, audit/history и safe Entity proposal.

## Таблицы, которые нельзя дублировать

Не создавать конкурирующие аналоги:

- `units`;
- `entities` и `entity_unit`;
- `leads` и любые новые `lead_*` business tables;
- `emails`, `telephones`, `uris` и их existing pivots;
- `mail_messages`, `email_mail_message`, `sendings`;
- `phone_calls`, MAX/Avito message stores;
- `goods`, `products`, `consumptions`, `quotations`;
- `sales`/`good_sale`;
- `purchases`/`good_purchase`;
- Orders/Checks/banking source tables;
- supplier pipeline tables.

Новые Unit tables должны добавлять отсутствующую семантику — context, provenance, evidence, review, score, attribution и projection — и ссылаться на existing source rows.

## Безопасный план расширения

1. Добавить auth/permissions и characterization tests для новых AI routes.
2. Ввести UnitBusinessContext additive и оставить flags compatibility projection.
3. Добавить UnitSource/Observation и Unit-first identity resolver.
4. Добавить contact candidate/context link поверх existing channels.
5. Добавить Entity link proposal/approval.
6. Добавить distinct transaction read models.
7. Добавить context attribution для mail/events.
8. Встроить panels в существующую Unit page.

Каждый пункт — отдельный production-safe этап; ничего из этого не реализовано на Stage 02.
