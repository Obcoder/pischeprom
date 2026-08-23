# Связь Unit и Entity

## Фактическая схема

`units (1) ← entity_unit → (1) entities` образует many-to-many:

| `entity_unit` | Ограничение |
|---|---|
| `id` | primary key |
| `entity_id` | unsigned bigint, FK → `entities.id`, cascade update/delete |
| `unit_id` | unsigned bigint, FK → `units.id`, cascade update/delete |
| `created_at`, `updated_at` | timestamps |

В pivot отсутствуют:

- unique `(unit_id, entity_id)`;
- business role/context;
- primary/canonical Entity;
- source/confidence;
- valid-from/valid-to;
- created_by/approved_by;
- soft delete/audit.

Eloquent с обеих сторон использует `belongsToMany`. Следовательно:

- Unit может иметь несколько Entity уже сегодня;
- Entity может быть связана с несколькими Unit уже сегодня;
- повтор одной пары технически возможен и может умножить join-агрегаты.

Локальная база во время аудита была пустой, поэтому реальные production-примеры не исследовались. Вывод о cardinality следует из применённой схемы, моделей и attach/sync-контроллеров.

## Разделение ответственности

| Объект | Ответственность |
|---|---|
| Unit | досье компании/сайта/площадки/рынка, cold discoveries, интересы, роли и контекст работы |
| Entity | точное юридическое/физическое лицо, реквизиты и владелец операций |
| UnitBusinessContext | будущее направление работы с Unit: sales или procurement; не новая карточка лица |
| Sale/Purchase/Order/Check/Banking | операции Entity; никогда не копируются в Unit/context |

### Правило no conversion

Unit не «превращается» в Entity после квалификации и не удаляется после сделки. Вместо этого:

1. Unit существует как долгоживущее досье.
2. Наблюдения и контакты проходят review.
3. Пользователь выбирает существующую Entity или одобряет создание новой.
4. Entity прикрепляется к Unit.
5. Сделки создаются на Entity.
6. Unit продолжает агрегировать историю всех связанных лиц и контекстов.

Это сохраняет холодную историю, не смешивает реквизиты с гипотезами и позволяет одному досье работать с несколькими владельцами операций.

## Реальные сценарии cardinality

### Несколько Entity у Unit

- бренд/сайт представлен головным ООО, региональным ООО и ИП;
- завод и торговый дом используют одну публичную карточку;
- после реорганизации новая Entity продолжает историю того же рыночного досье;
- разные Entity выставляют документы в sales и procurement отношениях.

В этих случаях нельзя выбирать «первую Entity» без контекстной роли и периода действия.

### Одна Entity у нескольких Unit

- одно ООО владеет несколькими брендами/сайтами;
- одно лицо связано с несколькими заводами или филиалами, заведёнными отдельными Unit;
- marketplace/profile и официальный сайт представлены разными Unit, но сделки идут на одно лицо.

Автоматическое слияние таких Unit по INN уничтожит различимую cold-work историю. Совпадение Entity не означает совпадение Unit.

## Потоки агрегации

### Продажи

`Unit → entity_unit → Entity → sales → good_sale → Good`

Источник истины — `sales`/`good_sale`. Unit query:

1. получает уникальные `entity_id` разрешённых links;
2. выбирает `Sale` по ним;
3. применяет authorization и sales context;
4. возвращает уникальные sales IDs и агрегаты.

### Закупки

`Unit → entity_unit → Entity → purchases → good_purchase → Good`

Источник истины — `purchases`/`good_purchase`. Алгоритм тот же, но доступен только procurement lane. В текущей Unit card этот read model отсутствует.

### Заказы, чеки и платежи

Они также выбираются через Entity. Необходимо отдельное разрешение на каждый тип данных; наличие связи Unit↔Entity само по себе не даёт AI доступа к банковским или документным данным.

### Переписка и контакты

Текущий mail query использует:

`Unit.email_ids ∪ Unit.entities.email_ids → mail_message_email → MailMessage`

Это поддерживает старые данные, но один общий email связывает сообщение сразу с несколькими dossiers и lanes. В будущем сохранённая context link должна иметь приоритет; email inference остаётся fallback с меткой `inferred` и требует review для неоднозначных совпадений.

## Что агрегируется сегодня

| Область | Текущий Unit | Требуемое состояние |
|---|---|---|
| Sales | да, через Entity | типизированный read model + distinct + permission |
| Purchases | relation доступна у Entity, но не Unit UI | procurement read model |
| Orders | загружаются условно через Entity | единая policy и context |
| Checks/banking | не агрегируются карточкой | только по явному permission; banking по умолчанию скрыт |
| Contacts | Unit и Entity показываются вместе | показать owner/source/context и дубли |
| Mail | вывод по email | сохранённая Unit/context attribution |
| Documents | Unit files и transaction records раздельно | metadata/context links без копирования содержимого |
| Events | разрозненные calls/mail/stages | append-only context timeline |

## Риски текущей схемы

1. Duplicate pivot rows могут завышать sums/counts.
2. Cascade/hard delete стирает связи без dossier history.
3. Нет ответа, какая Entity действует в каком lane и периоде.
4. Direct Unit contacts и Entity contacts могут расходиться.
5. `leads.entity_id` и `leads.unit_id` изменяются независимо и могут описывать несогласованную пару.
6. Mail inference по shared contact может раскрыть supplier correspondence sales-пользователю.
7. Создание placeholder Entity по телефону смешивает неизвестный контакт с transaction owner.

До добавления unique constraint нужна read-only проверка дублей в production и безопасный dedupe plan. Stage 01 не изменяет pivot.

## Минимальный UnitBusinessContext

Совместимое расширение:

- таблица `unit_business_contexts`;
- одна активная строка на `(unit_id, context_key)`;
- `context_key` только `sales` или `procurement` на первом этапе;
- свои `status`, `owner_user_id`, `visibility_scope`, `last_activity_at` и `next_action_at`;
- archive/status вместо физического удаления;
- все новые sources, observations, drafts и events ссылаются на context.

Существующие `is_customer`/`is_supplier` временно остаются compatibility projection:

- наличие sales context проецируется в `is_customer`;
- наличие procurement context — в `is_supplier`;
- обратный backfill создаёт context rows идемпотентно после отдельного review.

Флаги не должны быть единственным источником состояния после перехода.

## Dedupe web discoveries: сначала Unit

Рекомендуемый детерминированный порядок:

1. Точное совпадение provider + external source ID с уже прикреплённым `UnitSource`.
2. Нормализованный registrable domain/URL.
3. Проверенный телефон или email как candidate match, не как автоматическое тождество.
4. Нормализованное название + город/адрес/отрасль.
5. Fuzzy ranking только для списка кандидатов.

Результат resolver — `matched`, `ambiguous` или `new_candidate` с evidence. Только высоконадёжный exact Unit match может автоматически прикрепить observation; merge Unit всегда подтверждает человек. Поиск Entity выполняется после выбора Unit и не создаёт её.

## Human-controlled Entity creation

AI contract не содержит команд `createEntity`, `updateEntity`, `mergeEntity` или `deleteEntity`. Разрешены:

- поиск кандидатов Entity;
- чтение allowlisted summary;
- создание draft proposal с evidence;
- уведомление reviewer.

Approval endpoint должен требовать auth, permission `ai_sales.entity.approve`, CSRF/appropriate Sanctum boundary, duplicate preview и явное подтверждение реквизитов. Application service записывает actor, source и before/after audit в одной transaction. Ошибка или неоднозначное совпадение оставляет proposal открытым и не меняет Entity.

## Additive links для mail/drafts/events

Для новых данных предпочтительны:

- context FK в AI draft/event/source/observation;
- отдельная attribution pivot между `MailMessage` и `UnitBusinessContext` с relation type, source, confidence и reviewer;
- nullable Entity link только когда лицо подтверждено;
- идемпотентный backfill существующих сообщений как `inferred`, без перезаписи старых email relations.

Не следует добавлять единственный обязательный `unit_id` прямо в `mail_messages`: одно сообщение может затрагивать несколько dossiers, а существующие строки не имеют однозначного владельца.
