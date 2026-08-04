# AI-прайс-листы поставщиков — прогресс реализации

Обновлено: 2026-08-05.

## Обязательный аудит

- [x] Локальные инструкции проверены: `AGENTS.md` в репозитории и его родительском рабочем контексте отсутствуют.
- [x] `git status`: ветка `main`, синхронизирована с `origin/main`, рабочее дерево до начала реализации чистое.
- [x] Стек проверен: PHP 8.4.1 (проект требует `^8.2`), Laravel 12.64.0, Jetstream 5.5.2, Inertia Laravel 3.1.0 / Vue adapter 3.1.1, Vue 3.5.34, Vuetify 3.8.5, Vite 8.0.13.
- [x] Почтовый контур проверен: `YandexMailboxService` сохраняет входящие сообщения в `mail_messages`, вложения — в `mail_message_attachments` на настроенный Yandex S3 disk. Точка расширения — после успешного сохранения `MailMessageAttachment`; второй IMAP-клиент не нужен.
- [x] MAX проверен: публичный `POST /api/max/webhook`, `MaxWebhookController`, `MaxMessengerService`, `MaxWebhookEvent`, `MaxMessage`, `MaxChat`. Секрет уже сравнивается через `hash_equals`; webhook необходимо дополнить строгой дедупликацией и асинхронным ingestion вложений, не меняя stock-alert обработчик.
- [x] Поставщик: `Entity` связан с `Email` через `email_entity`; `MaxChat` уже имеет nullable `entity_id`. Это надёжные первичные способы определения поставщика, создавать параллельную identity-таблицу не требуется.
- [x] Каталог: строка прайса относится к `Good` (реальная товарная позиция). `Product` — переводимая классификационная сущность, связанная с `Good` many-to-many; автоматически связывать строку одновременно с обеими сущностями нельзя.
- [x] Цена: `good_price_type_values` хранит продажные/расчётные значения по типу цены, но не поставщика и не происхождение. Для закупочных прайсов требуется отдельная append-only история `supplier_good_prices` с `entity_id`, `good_id`, валютой, НДС и provenance на строку импорта. Существующие продажные цены модуль не изменяет.
- [x] Справочники: `Measure`, `VatRate`, `Currency`, `Country` существуют; производитель в фактической модели товара не является отдельным `Manufacturer` model (таблица `manufacturers` — pivot Product↔Unit). Неизвестные справочные значения сохраняются как распознанный текст и не создаются автоматически.
- [x] Права: Spatie Permission с guard `crm`, центральный `Gate::before`, permissions seeder и Policies. Добавляется отдельное семейство `ai_price_lists.*`.
- [x] UI: Inertia/Vue/Vuetify, общий `VerwalterLayout`, навигация в header. Новый раздел будет доступен как `AI → Прайс-листы` и использовать тот же layout.
- [x] Хранилище: существующий приватно используемый disk `yandex` (S3 Yandex Object Storage). Постоянные Object Storage URL для нового модуля не выдаются; скачивание только через авторизованный backend stream/temporary URL.
- [x] Очереди: Redis уже настроен, scheduler находится в `routes/console.php`; production docs используют Supervisor/systemd. Добавляется логическая очередь `price-lists`, jobs dispatch after commit и команда восстановления зависших импортов.
- [x] Audit: общий универсальный audit отсутствует; банковский журнал специализирован и append-only. Для модуля нужны собственные append-only `price_list_events` и `ai_usage_records`.
- [x] Тесты: PHPUnit 11, основной test DB — SQLite memory; feature/unit стиль изучен. MySQL-совместимость миграций дополнительно проверяется через SQL/индексы и документируется, так как MySQL test service локально не объявлен.
- [x] Парсеры до изменений: библиотек XLS/XLSX, DOCX, PDF и JSON Schema нет. Есть `intervention/image`, DOM/XML и ZipArchive окружения. Требуются PhpSpreadsheet, PhpWord, PDF parser и JSON Schema validator.
- [x] AI-код: существующий Yandex Direct AI слой узкоспециализирован. Создаётся минимальная общая provider abstraction (`StructuredTextModelProviderInterface`, `OcrProviderInterface`) без агентной платформы.
- [x] Актуальные официальные контракты перепроверены: Yandex AI Studio OpenAI-compatible structured output поддерживает `json_schema`; для документов нужен `x-data-logging-enabled: false`; Vision OCR использует backend `recognizeText`; MAX production рекомендует webhook и событие `message_created`, тело сообщения содержит attachments.

## Значимые фактические отличия от предположений ТЗ

1. Проектовые API routes исторически в основном не закрыты общей auth middleware. Новый модуль изолируется собственными `auth:sanctum` + policy/permission проверками, не затрагивая legacy routes.
2. `good_price_type_values` — не история закупочных предложений поставщиков. Модуль не подменяет ею закупочные цены и создаёт отдельную append-only таблицу.
3. `Product` не является SKU поставщика; основной match/apply target — `Good`.
4. Отдельного универсального scanner/audit/AI provider слоя нет; добавляются небольшие контракты и безопасные default/fake реализации.
5. Отдельного lint/typecheck script во frontend нет; обязательная проверка frontend — production build.

## Поэтапный чек-лист

- [x] Фаза 1: аудит и этот progress-документ.
- [x] Фаза 2: модели, миграции, enums/state machine, policy/permissions.
- [x] Фаза 3: приватное хранение, validation/quarantine, ingestion email и MAX, SSRF-safe downloader.
- [x] Фаза 4: deterministic XLS/XLSX/CSV/DOCX/PDF parsers и OCR abstraction.
- [x] Фаза 5: Yandex AI classification/structured extraction/reranking, schema/domain validation, fake providers.
- [x] Фаза 6: нормализация, explainable candidates и supplier aliases.
- [x] Фаза 7: review и атомарный идемпотентный apply в историю цен поставщика.
- [x] Фаза 8: Inertia/Vuetify UI списка, карточки, позиций, review/retry/apply.
- [x] Фаза 9: audit, usage/budgets, уведомления, recovery scheduler и документация эксплуатации.
- [x] Фаза 10: fixtures/generators, unit/feature tests, formatter, полный тестовый прогон и production frontend build.
- [x] Release gate: совместимое обновление Composer lock, ноль security advisories,
  изолированный MySQL 8.0 preflight, безопасный default `enabled=false` и отдельный
  GitHub Actions provisioner для production runtime/credentials/smoke/rollback.

## Выполненные проверки

- `php -d memory_limit=512M vendor/bin/phpunit --colors=never tests/Unit/AiPriceLists tests/Feature/AiPriceLists` — 51 тест, 225 assertions, включая production preflight, успешно.
- `php -d memory_limit=512M vendor/bin/phpunit --colors=never` — 299 тестов,
  1816 assertions; 5 legacy-тестов штатно пропущено.
- `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan migrate:fresh --force` — все миграции с нуля, включая `2026_08_04_100000_create_ai_price_list_tables`, успешно.
- `php artisan route:list --path=ai/price-lists --except-vendor` — 18 маршрутов модуля; `php artisan route:list --path=max/webhook --except-vendor` — существующий публичный webhook сохранён.
- `php artisan schedule:list` — `price-lists:recover-stale` зарегистрирован каждые 10 минут.
- `vendor/bin/pint --test --dirty` — 113 изменённых PHP-файлов, успешно.
- `composer validate --no-check-publish` и `composer check-platform-reqs --no-dev` — успешно на PHP 8.4.1.
- `npm run build` на Node 24.13.0 — client 1841 modules и SSR 1944 modules, успешно. Остались предупреждения проекта о старом `caniuse-lite` и крупных chunks, сборку они не блокируют.
- `composer audit --locked` — advisories отсутствуют после совместимого обновления
  68 транзитивных пакетов без смены major-версий; проверка добавлена в deploy gate.
- `npm audit --audit-level=high` — vulnerabilities отсутствуют после совместимого
  обновления Axios/FormData/Immutable/PostCSS/Vite и замены заброшенного
  `@vueuse/head` на исправленный `@unhead/vue`; проверка добавлена в deploy gate.
- Полный `migrate:fresh` и 51 тест модуля выполнены на отдельной MySQL 8.0.35 базе;
  структура проверена, временная база удалена.
- Реальный приватный Yandex Object Storage smoke (write/read/hash/delete случайного
  синтетического объекта) выполнен успешно.

Реальные Yandex AI/Vision и MAX проверяются только на VPS отдельной командой
`price-lists:production-preflight --all`: секреты не копируются локально и не
выводятся в GitHub Actions. Production activation разрешена только после её успеха.

## Неизменяемые safety-инварианты

- `PRICE_LIST_AUTO_APPLY=false`; AI/OCR никогда не пишет товары или цены напрямую.
- Новый `Good` создаётся только после явного apply и всегда с `is_published=false`.
- `Entity`, справочники, продажные цены и существующие товары автоматически не создаются/не изменяются.
- В AI/OCR запросах принудительно `x-data-logging-enabled: false`; секреты и полный документ не попадают в logs/audit.
- Webhook MAX сохраняет существующий route/secret check и быстро возвращает ответ; скачивание/парсинг выполняются только в очереди.
- Production rollout выполняется исключительно существующим GitHub Actions SSH-контуром;
  Docker/Coolify и ручное копирование production-секретов не используются.
