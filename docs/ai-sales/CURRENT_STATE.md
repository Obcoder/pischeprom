# Текущее состояние перед AI Sales Agents

Дата аудита: 2026-08-15. Базовая ревизия: `af839e447ef4a2aa80608d4a9a1ac727800d8bdc`. Аудит выполнен в ветке `feature/ai-sales-agents` без изменения прикладного кода, схемы и данных.

## Границы и достоверность

- Источники фактов: модели, миграции, контроллеры, маршруты, Vue-компоненты, конфигурация, workflow, эксплуатационная документация и read-only команды Artisan.
- Локальная MySQL доступна, но все проверенные доменные таблицы пусты. Поэтому фактическая cardinality подтверждена схемой и Eloquent-связями, а не production-распределением данных.
- `php artisan migrate:status` показал 16 pending-миграций. Ни одна миграция в ходе аудита не запускалась.
- В репозитории уже есть отдельные подсистемы `Lead` и `AiPriceLists`. Это существующий baseline, а не результат AI Sales Agents.

## Технологический стек

| Область | Фактическое состояние |
|---|---|
| Backend | PHP CLI 8.4.1; `composer.json` допускает PHP ^8.2; Laravel Framework 12.64.0 |
| Auth | Laravel Jetstream 5.5.2, Sanctum 4.1.1, Spatie Permission 6.24; guard ролей `crm` |
| Frontend | Vue установлен 3.5.34, Inertia Vue/Laravel 3.1.1, Vuetify 3.8.5, Vite 8.2.0 |
| Локальный Node | 16.14.0 / npm 8.3.1; недостаточен для установленного Vite 8 |
| CI Node | 22.18.0 в `.github/workflows/main.yml` |
| БД | MySQL, локальный сервер 8.0.35 |
| State drivers | локально: cache/database, queue/database, session/database |
| Mail | Laravel mailer `smtp`; прикладной `EMAIL_PROVIDER` по умолчанию `log` |
| Files | default disk `local`; часть Unit/mail-файлов работает с Yandex Object Storage/S3 |
| SSR | Vite собирает client и SSR bundle; production-документация содержит отдельный systemd service |

Версии frontend взяты из установленного lockfile, а не только из диапазонов `package.json`.

## Развёртывание и процессы

Production workflow запускается при push в `main`:

1. GitHub Actions проверяет Composer, audit, выбранные Pint-наборы и тесты.
2. Frontend собирается на Node 22.18.
3. Deployment выполняется на VPS по SSH с привязкой к конкретному commit SHA.
4. Документация и инфраструктурные файлы указывают на Nginx, PHP-FPM и systemd.
5. Laravel scheduler вызывается cron-записью раз в минуту.

Общего Docker/Coolify deployment для приложения нет. В `infrastructure/valhalla/compose.yml` есть изолированный compose-файл GIS/Valhalla; это существующий инфраструктурный артефакт и не основание переводить приложение на Docker.

Проверенные worker-профили:

- default/database в локальном окружении;
- Redis queue `banking`;
- отдельное Redis connection/queue `routing`;
- Redis queue `price-lists`;
- queue `mail-notifications`.

В production-документах рекомендуются systemd units; местами описан Supervisor как альтернативный способ запуска, но готовой общей Supervisor-конфигурации в репозитории нет. Локальные бинарники `redis-server` и `redis-cli` отсутствуют, хотя production connections настроены.

Scheduler содержит, среди прочего, синхронизацию Yandex mailbox, Beeline calls, Yandex Direct, Avito, Sber banking, logistics и восстановление AI price-list jobs. Для долгих/сетевых задач применяются `withoutOverlapping`, а для части задач — `onOneServer`.

## Почта и коммуникации

В системе сосуществуют несколько контуров:

- `Email` — общий контакт с many-to-many связями к `Unit` и `Entity`;
- `MailMessage` — входящие/исходящие сообщения с IMAP/message-id/threading, адресатами, body, headers и attachments; связь с бизнес-объектами выводится через email;
- `Sending` — старая отправка/трекинг со status, open/click, IP, user-agent и provider IDs;
- Commercial Offers mailing — отдельные `mailing_contacts`, campaigns, recipients, consent evidence, suppression, unsubscribe, bounce и spam events;
- Beeline phone calls, MAX chats и Avito messenger.

Провайдеры и интеграции, найденные в конфигурации/коде: SMTP, Yandex/Beget mailboxes через IMAP, Unisender Go, Yandex Object Storage, Yandex Search, Wikipedia, DaData, Beeline PBX, MAX, Yandex Direct, Avito, Sber mTLS, 2GIS/Yandex maps, Valhalla, Telegram, а также Yandex AI Studio/Vision для существующего модуля price lists.

`UnitMailController` отправляет письмо непосредственно через выбранный SMTP mailbox. На этом маршруте нет approval/compliance gate. Он объединяет прямые email Unit и email связанных Entity, то есть контекст продажи и закупки не разделён. Attachment paths из object storage не ограничены префиксом конкретного Unit.

Commercial Offers лучше защищает массовые рассылки — хранит consent, suppression, unsubscribe, bounce/spam и approval — но его `mailing_contacts` не связаны с Unit/Entity/context и дублируют общий справочник `emails`.

## Фактический lifecycle Unit

`App\Models\Unit` / таблица `units` — текущий контейнер досье:

1. Unit создаётся с `name` и двумя независимыми флагами `is_customer`/`is_supplier`.
2. К нему напрямую прикрепляются контакты, URI, города/объекты, отрасли/поля/метки, стадии, продукты, потребности, котировки, производимые продукты, supplier pipeline card и файлы.
3. Одна или несколько Entity прикрепляются через `entity_unit`.
4. Продажи и заказы показываются через связанные Entity; закупки в карточке Unit сейчас не агрегируются.
5. Переписка выводится по прямым email Unit и email Entity; это эвристика, а не сохранённая контекстная связь.
6. Удаление Unit сейчас жёсткое. Soft delete, архив, владелец, provenance и audit trail отсутствуют.

`name` индексирован, но не уникален. Status как поле отсутствует. `stage_unit` и supplier pipeline дают разные механизмы стадий, не разделённые на sales/procurement business context.

## Фактический lifecycle Entity

`App\Models\Entity` / `entities` описывает юридическое или физическое лицо:

1. Entity создаётся вручную через форму/API, иногда с подсказкой DaData.
2. Хранит name/full_name, classification, INN/KPP/OGRN, legal address, country, банковские реквизиты и необработанный ответ DaData.
3. Связывается many-to-many с Unit, email, telephone, city/building и users.
4. `Sale`, `Purchase`, `Order`, `Check` и banking records принадлежат Entity.
5. Entity удаляется жёстко; связи detach/cascade. Soft delete и единый audit trail отсутствуют.

Тип лица выражен общей `entity_classification_id` и данными DaData. На уровне БД нет обязательных инвариантов «юрлицо/физлицо», уникальности INN/OGRN или полного duplicate gate.

Есть исключения из ручного lifecycle:

- Beeline CRM context при неизвестном телефоне может автоматически создать placeholder Entity `Клиент {phone}` и Lead.
- Avito имеет пользовательскую операцию create/link Entity и повторно использует совпадение по телефону, но не имеет полного юридического review gate.

До подключения AI эти legacy-пути надо изолировать от новых инструментов; Stage 01 их не меняет.

## Роли и контексты сегодня

| Значение | Где хранится | Ограничение |
|---|---|---|
| Клиент / поставщик | `units.is_customer` и `units.is_supplier` | оба флага могут быть true; нет отдельного состояния/владельца/истории для каждой роли |
| Потенциальный клиент | legacy `leads.status` и косвенно Unit stages | не является Unit-first context; `leads.unit_id` nullable |
| Потенциальный поставщик | supplier pipeline card/stage у Unit | состояние частично дублируется с `is_supplier` и `stage_unit` |
| Производитель | `manufacturers` / products relation к Unit | независим от customer/supplier flags |
| Перевозчик/исполнитель | Entity vehicles/trips и logistics roles | единой Unit-роли нет |
| Entity classification | `entities.entity_classification_id` и additional classifications | классифицирует лицо, а не направление работы с досье |

Следовательно, business roles сегодня принадлежат и Unit, и Entity, но означают разное: Unit flags описывают коммерческую роль досье, Entity classification — тип/класс владельца транзакций. Полноценного `UnitBusinessContext` нет.

## Контроль доступа

Критический факт route audit: Unit CRUD, Unit relations/files/mail, Entity CRUD и MailMessage API имеют только middleware `api`. Карточка `Ameise/unit/{unit}` имеет только `web`. Middleware `auth`/`auth:sanctum` и domain permissions на этих маршрутах не отображаются.

Дополнительные наблюдения:

- `StoreEntityRequest::authorize()` возвращает true;
- `UnitController::store()` использует массовое присваивание разрешённых fillable полей без отдельного FormRequest;
- Unit/Entity destroy выполняют hard delete;
- существующий RoleSeeder не определяет permissions для Unit, Entity, mail, sales и procurement;
- authorization существующего AI price-list API может быть отключена конфигурацией.

Новые AI endpoints нельзя добавлять до явной аутентификации, permission matrix, object-level authorization и audit. Отдельно нужно безопасно закрыть legacy API после инвентаризации клиентов, иначе можно сломать существующие интеграции.

## Секреты

`.env` и варианты production/backup env исключены из Git; `auth.json` также игнорируется. Sber private material по документации хранится вне репозитория в `/etc/pischeprom/...`. В Git найден только публичный российский CA bundle, приватные ключи не обнаружены.

Правило для AI: ни env values, OAuth tokens, SMTP credentials, mTLS private material, raw auth headers, session/cookie, ни encrypted provider payloads не должны попадать в prompt, trace или tool output.

## Baseline-вывод

Архитектура уже содержит правильное разделение ответственности: Unit может быть досье, а Entity — точным владельцем сделки. Однако она пока не обеспечивает контекстную изоляцию, сохранённую provenance, безопасную дедупликацию, неизменяемый audit и единый auth boundary.

Новый AI Sales контур должен расширять Unit, а не создавать ещё один Lead aggregate. Существующий `Lead` следует считать legacy ingestion/workflow, заморозить для новых AI-зависимостей и позднее адаптировать к `UnitBusinessContext` без разрушительного удаления данных.
