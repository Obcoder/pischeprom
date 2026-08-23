# Карта домена Entity

## Назначение и реализация

`App\Models\Entity` / таблица `entities` — точное юридическое или физическое лицо, на которое оформляются операции. Это не стадия развития Unit и не замена досье.

Основные файлы:

- `database/migrations/2024_11_20_220330_create_entities_table.php` и последующие requisites/DaData/banking migrations;
- `app/Models/Entity.php`;
- `app/Http/Controllers/API/EntityController.php`;
- `app/Http/Requests/Entity/StoreEntityRequest.php` и `UpdateEntityRequest.php`;
- `app/Http/Resources/EntityResource.php`;
- `resources/js/Pages/Ameise/Entity.vue` и `Components/Dictionaries/Entities/*`.

## Реквизиты

| Поле | Значение | Замечание |
|---|---|---|
| `id` | внутренний identity | primary key |
| `name` | краткое имя | unique index был удалён; повторы разрешены |
| `full_name` | полное имя | nullable |
| `entity_classification_id` | основная классификация | nullable FK |
| `INN` | налоговый идентификатор | nullable, не unique |
| `KPP` | код постановки на учёт | nullable, не unique |
| `OGRN` | регистрационный номер | nullable, не unique |
| `legal_address` | юридический адрес | nullable |
| `country_id` | страна | nullable FK |
| `bank_account_number` | расчётный счёт | скрыт стандартной serialization |
| `bank_name`, `bank_bic`, `bank_corr_account` | банк/БИК/корсчёт | скрыты стандартной serialization |
| `dadata_raw` | исходный payload DaData | JSON cast, не hidden |
| `dadata_loaded_at` | дата загрузки DaData | nullable datetime |
| timestamps | технические даты | нет soft delete/audit actor |

Банковские поля исключены из обычной serialization модели, но это не заменяет authorization и DTO allowlist. `dadata_raw` может содержать лишние персональные/реестровые данные и не должен автоматически попадать в AI.

## Юридическое и физическое лицо

Основная и дополнительные classifications хранятся отдельно:

- `classification()` — belongsTo `EntityClassification`;
- `additionalClassifications()` — M:N.

Форма умеет заполнять реквизиты из DaData, а customer-profile имеет отдельный `User.account_type`. Однако в `entities` нет обязательного person type discriminator и условных DB-инвариантов:

- для юрлица INN/OGRN не обязательны;
- для физлица не задан отдельный защищённый набор полей;
- длина/контрольная сумма реквизитов проверяются только как строки;
- уникальность INN/OGRN не обеспечена;
- classification сама по себе не гарантирует legal/physical semantics.

Для AI любое неизвестное лицо должно оставаться observation/candidate на Unit. Нельзя выводить пригодность к созданию Entity только из названия или ответа внешнего источника.

## Связи

| Relation | Cardinality | Назначение |
|---|---|---|
| `units` | M:N через `entity_unit` | одно лицо может участвовать в нескольких досье |
| `emails`, `telephones` | M:N | контакты лица |
| `cities`, `buildings` | M:N | география/адресные объекты |
| `location` | 1:1 | GIS location |
| `users` | M:N с role/status/is_primary | customer users/представители |
| `chats` | M:N | legacy chats |
| `avitoChats` | 1:N | Avito dialogs |
| `phoneCalls`, `leads` | 1:N | legacy CRM |
| `orders` | 1:N | заказы |
| `sales` | 1:N | продажи |
| `purchases` | 1:N | закупки |
| `bankConnections`, `bankTransactions` | 1:N | банковский контур |
| `bankPaymentDrafts` | 1:N как recipient | платёжные поручения |
| `ownedLogisticsVehicles` | 1:N | владелец транспорта |
| `carrierLogisticsTrips` | 1:N | перевозчик |

## Транзакции и документы

Источники истины:

- `sales.entity_id` и `good_sale` — продажа, строки Good, quantity/measure/price/total; банковская сверка хранит payment reference/status/paid/outstanding/overpaid;
- `purchases.entity_id` и `good_purchase` — закупка, строки Good, quantity/measure/price/currency/total;
- `orders.entity_id` и order items/status/delivery relations — заказ;
- `checks.entity_id` и commodity/service rows — чек/расчёт;
- banking connections/accounts/transactions/allocations/drafts — финансовая подсистема.

Отдельных моделей `Contract` или `Invoice` в проаудированном коде не найдено. Файлы Unit и вложения mail не являются типизированными договорами/счетами.

`Sale` имеет polymorphic `bankAllocations`. Banking allocation/match/audit использует morph targets; это внутренний финансовый механизм и не универсальный AI tool surface.

Транзакции нельзя копировать в Unit или будущий context. Unit read model выбирает их по связанным Entity IDs, применяет authorization и `distinct` по id.

## Связь с Unit и cardinality

Обе модели объявляют `belongsToMany` через `entity_unit`. Pivot содержит:

- `id`;
- `entity_id` с cascade update/delete;
- `unit_id` с cascade update/delete;
- `created_at` и `updated_at`.

Нет unique index на `(entity_id, unit_id)`, relation type, primary flag, validity period, source, confidence или author. Схема допускает:

- несколько Entity у одного Unit;
- одну Entity у нескольких Units;
- ошибочные повторные строки одной пары.

Контроллер синхронизирует список Unit при Entity create/update, а карточка Unit умеет attach/detach Entity. Фактическая cardinality — many-to-many.

## Контакты и география

Email, telephone, city и building существуют и на Unit, и на Entity. Это допустимо, если различать:

- общий контакт/площадку досье;
- проверенный контакт/адрес конкретного юридического лица;
- источник, срок актуальности и business context.

Сейчас эти признаки в pivots отсутствуют. Один email или телефон может быть связан с несколькими Unit/Entity, а UI объединяет наборы. Поэтому равенство контакта — сигнал дедупликации, но не автоматическое доказательство тождества.

## Duplicate logic

Entity form поддерживает ручной поиск/заполнение DaData. `StoreEntityRequest` валидирует типы и существование relation IDs, но:

- `authorize()` возвращает true;
- нет unique/duplicate rule для INN/OGRN;
- нет human-review record;
- нет provenance для выбранной подсказки;
- создание сразу пишет Entity и синхронизирует связи.

Дополнительные механизмы:

- `UserEntityResolver` ищет Entity по INN, затем email/телефону для customer profile;
- `TelephoneIdentityService` нормализует телефоны, объединяет telephone rows и переносит ссылки;
- Beeline при неизвестном номере может создать placeholder Entity;
- Avito может переиспользовать совпадение по телефону и предлагает user-triggered create/link.

Эти алгоритмы не образуют единый dedupe gate. AI не должен вызывать их как разрешение автоматически создать Entity.

## Lifecycle и UI

Entity list/detail показывает реквизиты, relations и статистику sales/purchases. Форма позволяет одним запросом синхронизировать buildings, cities, emails, telephones, units и chats. Удаление сначала detach-ит связи и затем hard-delete-ит Entity.

Риски:

1. Hard delete может разрушить dossier linkage и затруднить аудит.
2. Route имеет только `api` middleware.
3. Нет object-level policy/permission на CRUD.
4. Не все sensitive поля скрыты Resource-контрактом.
5. Имя и реквизиты допускают дубли.
6. Автоматические legacy placeholder paths обходят желаемый human gate.

## Решение для AI Sales

- Entity остаётся transaction owner.
- Unit остаётся cold-work/dossier root.
- AI может найти и ранжировать существующие Entity, но не создавать, merge-ить, detach-ить или удалять их.
- Если подходящей Entity нет, AI сохраняет source/observation/contact candidate у Unit и создаёт `EntityCreationProposal`.
- Только аутентифицированный пользователь с отдельным permission может одобрить proposal после duplicate/requisites preview.
- Одобрение вызывает единый application service в DB transaction, пишет audit и только затем создаёт/прикрепляет Entity.
