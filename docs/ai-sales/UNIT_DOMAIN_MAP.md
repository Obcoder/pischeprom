# Карта домена Unit

## Идентичность и реализация

- Модель: `App\Models\Unit`.
- Таблица: `units`.
- Первичная миграция: `database/migrations/2024_11_06_154418_create_units_table.php`.
- Контроллеры: `API/UnitController`, `API/UnitRelationController`, `API/UnitFileController`, `API/UnitMailController`, `Web/UnitController` и sync-контроллеры.
- Resource: `App\Http\Resources\UnitResource`; сейчас он минимален, а контроллеры часто возвращают загруженную модель напрямую.
- Основной UI: `resources/js/Pages/Ameise/Unit.vue` и `resources/js/Components/Unit/*`.

Фактическое имя модели/таблицы — `Unit` / `units`. В коде нет отдельного Contact/Company aggregate, который должен заменить Unit для холодной работы.

## Поля

| Поле | Назначение | Ограничения |
|---|---|---|
| `id` | внутренний идентификатор | primary key |
| `name` | отображаемое имя досье | индекс, но не unique |
| `is_customer` | роль клиента | boolean, default false, индекс |
| `is_supplier` | роль поставщика | boolean, default false, индекс |
| `created_at`, `updated_at` | технические даты | не являются audit trail |

Модель разрешает mass assignment этих четырёх business-полей и по умолчанию eager-loads fields, labels, telephones и uris.

В `units` нет:

- status/lifecycle;
- canonical name, aliases и identity keys;
- owner/assignee;
- source/provenance/confidence;
- business-context или lane;
- notes как самостоятельной сущности;
- soft delete/archive;
- created_by/updated_by и immutable audit.

## Прямые связи Unit

| Связь | Cardinality/хранилище | Смысл |
|---|---|---|
| `entities` | M:N через `entity_unit` | юридические/физические владельцы операций |
| `emails` | M:N через `email_unit` | прямые email досье |
| `telephones` | M:N через `telephone_unit` | прямые телефоны |
| `uris` | M:N через `unit_uri` | сайты/страницы |
| `cities` | M:N через `city_unit` | география деятельности |
| `buildings` | M:N через `building_unit` | площадки/адресные объекты |
| `fields` | M:N через `field_unit` | тематические поля |
| `industries` | M:N через `industry_unit` | отрасли; pivot может отмечать primary |
| `classifications` | M:N через `entity_classification_unit` | классификации Unit |
| `labels` | M:N через `label_unit` | пользовательские метки |
| `products` | M:N через `product_unit` | связь с Product и `action_id` |
| `stages` | M:N через `stage_unit` | start/end dates и is_active |
| `manufactures` | relation через manufacturers | производимые Product |
| `consumptions` | 1:N | интерес/потребность Unit в Product |
| `quotations` | 1:N | индивидуальные условия Unit для Good |
| `supplierPipelineCards` | 1:N | supplier stage, notes, next contact |
| `leads` | 1:N | legacy Lead; `unit_id` может быть null у других leads |
| `phoneCalls` | 1:N | звонки с прямой ссылкой на Unit |

К Unit также могут напрямую ссылаться `MaxChat` и некоторые интеграционные записи, даже если обратная relation не объявлена в модели.

### Данные, которые уже принадлежат Unit

Непосредственно Unit принадлежат:

- имя и customer/supplier flags;
- прямые телефоны, email и URI;
- города, здания, отрасли, поля, классификации и labels;
- стадии и supplier pipeline state/notes;
- Product interests, consumptions, quotations и manufactures;
- файлы досье;
- ссылки на legacy leads, calls и некоторые chats.

Продажи, закупки, заказы, чеки и банковские операции Unit не принадлежат. Они принадлежат Entity и только агрегируются в карточке досье.

## Файлы

`UnitFileController` работает с object storage:

- текущий префикс — `units/{unit_id}`;
- поддерживается legacy path на основе `units/{unit_name}`;
- доступны list/upload/delete/create-folder/move/rename.

В БД нет записи документа с owner, category, context, checksum, source, classification, retention и audit. Rename/move/delete — операции над объектами, а не версионируемым dossier document. Legacy path по изменяемому и неуникальному имени создаёт риск коллизий.

## Товары, цены и транзакции

- `Product` — предметная/таксономическая сущность и основа интересов/производства.
- `Good` — продаваемая SKU/catalog сущность.
- `Consumption` связывает Unit и Product.
- `Quotation` связывает Unit и Good и содержит индивидуальную цену/валюту/меру/denominator.
- Актуальные типовые цены находятся в `good_price_type_values` и расчётах/formulas Good.
- Старая таблица `prices` уже удалена применённой миграцией.

`Quotation` и `Consumption` являются прямыми данными Unit, но сегодня не имеют business context, source, confidence или видимости. Их нельзя безусловно показывать одновременно sales- и procurement-агенту.

## Агрегаты Entity в карточке

`Web\UnitController` загружает большой `DETAIL_RELATIONS` graph. Текущее поведение:

| Данные | Как попадают в Unit | Состояние |
|---|---|---|
| Sales | `unit.entities[*].sales` и count через join `sales → entity_unit` | есть |
| Orders | через `entities.orders` | есть условно; permission handling непоследователен |
| Purchases | потенциально через Entity | в Unit UI не агрегируются |
| Checks/banking | потенциально через Entity | в Unit UI нет |
| Contacts | прямые Unit + контакты каждой Entity | есть, возможны дубли |
| Mail | email Unit OR email связанных Entity | есть, контекст неоднозначен |
| Documents | object storage Unit; transaction documents отдельно | единого представления нет |
| Events/timeline | calls/mail/stages раздельно | единой timeline нет |

Правильная будущая агрегация Sales/Purchases — read model/query через уникальный набор Entity IDs, без копирования строк в Unit. При join необходимо защищаться от повторных `entity_unit` pairs.

## Роли и mixed-role

`is_customer` и `is_supplier` независимы, поэтому mixed-role Unit уже возможен. Производитель выражен отдельной relation `manufactures`; supplier prospect — supplier pipeline; customer prospect — legacy leads/stages. Эти механизмы не образуют единый lifecycle.

Главный риск: у customer+supplier Unit одни и те же contacts, files, mail и observations доступны без признака направления. Флаг роли не является security boundary.

Минимальное совместимое расширение — одна строка `UnitBusinessContext` на Unit и lane (`sales` или `procurement`), со своим status/owner/visibility/timestamps. Это добавление к Unit, а не новая сущность Lead и не конвертация Unit в Entity.

## UI

Карточка `Ameise/Unit.vue` включает:

- overview с info, OKVED/classifications, contacts, files и buildings;
- trade tabs с consumptions, quotations и orders;
- manufactures;
- entities;
- stages/labels/URIs;
- calls;
- sales;
- emails/sendings;
- admin edit/delete и управление связями.

В UI можно создавать/прикреплять/откреплять Entity. Permission flags для части операций задаются непоследовательно; в Web controller встречаются hardcoded capabilities. Отдельного context switch, source review queue, discovery inbox, AI run history или approval queue нет.

Рекомендуемая точка расширения — существующая Unit page: header context selector и вложенные компоненты `Components/Unit/AiSales/*`. Отдельная страница/карточка «AI Lead» создаст второй aggregate и запрещена.

## Polymorphic relations

У `Unit` нет polymorphic Eloquent relations. Polymorphic связи, влияющие на соседние данные, существуют в banking:

- `Sale::bankAllocations()` — `morphMany`;
- `BankTransactionAllocation::allocatable()` — `morphTo`;
- bank match/audit target relations — `morphTo`.

Новый AI tool не должен получать произвольный morph type/id или универсальный обход связей. Доступ должен идти через типизированные application services и allowlist DTO.

## Основные пробелы

1. Нет устойчивой identity/dedupe модели; `name` не уникален.
2. Нет sales/procurement isolation.
3. Нет provenance/verification у web discoveries, observations и прямых контактов.
4. Нет soft delete/archive и полноценного аудита.
5. Нет уникальности на `entity_unit` и ряде contact pivots.
6. Нет сохранённой context-связи mail/calls/files/events.
7. Нет безопасного domain authorization boundary у API.
8. Есть legacy `Lead`, который частично дублирует lifecycle.
9. UnitResource не задаёт строгий экспортный контракт.
10. Агрегация Entity неполна и местами может раскрыть данные другого направления.

Unit всё же является подходящим aggregate root для холодной работы: он уже объединяет идентичность, контакты, интересы, объекты, коммуникации и несколько Entity. Следующие этапы должны укрепить этот root, а не заменять его.
