# AI-прайс-листы поставщиков

Модуль добавляет административный раздел `AI → Прайс-листы` по адресу
`/Ameise/ai/price-lists`. Входящие вложения из существующей почты и MAX сохраняются
как отдельные импорты, проходят безопасный асинхронный конвейер и никогда не меняют
каталог или цены без явного подтверждения сотрудника.

До внедрения общей авторизации всего `/Ameise` раздел и его API работают без
отдельного входа: `AI_PRICE_LIST_AUTHORIZATION_ENABLED=false` по умолчанию. Прежняя
permission model сохранена и снова включается одним параметром
`AI_PRICE_LIST_AUTHORIZATION_ENABLED=true`. В публичном режиме операции всё равно
ограничены машиной состояний, а файл в статусе `quarantined` скачать нельзя.

## Архитектура и поток данных

### Почта

Существующий `YandexMailboxService` по-прежнему быстро сохраняет метаданные
`MailMessage` без тяжёлой загрузки тела и вложений. Для нового входящего
письма с признаком вложений ставится уникальный job в штатную очередь `mail-sync`.
Он получает список IMAP-вложений и:

1. исключает inline-логотипы и неподходящие форматы; таблицы допускаются сразу, а PDF/DOCX/изображения — только при сигнале прайса в теме или имени файла;
2. сохраняет только подходящие файлы как `MailMessageAttachment` на приватный disk;
3. определяет поставщика по точной существующей связи email → `Entity`;
4. создаёт импорт с уникальным ключом исходного attachment;
5. после commit ставит обработку в Redis-очередь `price-lists`.

Отдельного IMAP-клиента или ответа отправителю нет. Несколько подходящих вложений
одного письма дают несколько импортов со ссылкой на одно письмо; повторная синхронизация
не создаёт дубликаты.

### MAX

Публичный `POST /api/max/webhook` сохранён. Контроллер проверяет
`X-Max-Bot-Api-Secret` constant-time сравнением, минимальную структуру payload,
дедуплицирует событие и быстро отправляет только job. Тяжёлая работа выполняется вне
HTTP-запроса. Поддерживаются реальные attachment `file` и `image`, а поставщик берётся
из существующей связи `MaxChat.entity_id`.

Скачивание выполняет backend-клиент: только HTTPS/443, только allowlist MAX/CDN,
с повторной проверкой каждого redirect, публичного DNS/IP, MIME, времени и размера.
Авторизация MAX передаётся только точному API-host, а не CDN или redirect-host.
Одинаковое событие не создаёт второй импорт и не отправляет второе подтверждение.

Текущий production API MAX — `https://platform-api2.max.ru`; среди актуальных CDN
используются домены `max.ru` и `oneme.ru`. Перед изменением подписки сначала получите
существующие subscriptions и убедитесь, что callback URL, secret и
`message_created` уже настроены. Не создавайте вторую подписку вслепую.

### Конвейер

`validate → classify → extract → OCR (при необходимости) → normalize → match → review → apply`

- MIME/сигнатура, размер, ZIP bomb/path traversal, пароль и scanner проверяются до парсинга;
- сначала используются локальные детерминированные парсеры;
- неоднозначный текстовый фрагмент получает лёгкую AI-классификацию; низкая уверенность
  или сбой оставляют `awaiting_classification`;
- изображения не отправляются в дорогой OCR до ручного подтверждения, если назначение
  документа неоднозначно;
- Yandex Vision используется только для сканов/фото и проблемных страниц смешанного PDF;
- Yandex AI Studio получает ограниченные строки как недоверенный data-block и возвращает
  strict JSON Schema;
- нормализация и первичный matching детерминированы; AI может лишь немного переставить
  уже найденные probable/conflict candidates и не может подтвердить строку;
- review и apply ограничены policy и машиной состояний; отдельные permissions
  применяются только при `AI_PRICE_LIST_AUTHORIZATION_ENABLED=true`;
- apply создаёт append-only цену поставщика; новый `Good` создаётся только как
  `is_published=false`.

Каждый job уникален по импорту/этапу, имеет overlap-lock, retry/backoff, heartbeat и
безопасный повтор. Все переходы централизованы в `PriceListStateMachine`.

## Фактическая доменная модель

Строка прайса сопоставляется с `Good`. В этом проекте `Product` — классификационная
переводимая сущность, связанная с `Good`, а не SKU поставщика. Существующая таблица
`good_price_type_values` содержит продажные/расчётные цены без поставщика и provenance,
поэтому модуль её не изменяет.

Миграция создаёт:

- `price_list_imports` — источник, приватный файл, состояние, версии и статистика;
- `price_list_import_items` — raw evidence, source locator, нормализованные поля и решение;
- `price_list_item_candidates` — объяснимые candidates `Good`;
- `supplier_product_aliases` — только вручную подтверждённые aliases поставщика;
- `price_list_events` — append-only журнал этапов и действий;
- `ai_usage_records` — запросы, request ID, tokens/pages/latency и оценочная стоимость;
- `supplier_good_prices` — append-only история закупочных предложений с provenance;
- nullable unique `max_webhook_events.deduplication_key`.

Оригинал имеет отдельный импорт даже при совпадающем SHA-256: hash не является
глобальным unique. Это сохраняет аудит разных каналов/поставщиков. Автоматический кэш
извлечения намеренно не включён в первую версию, чтобы не переносить evidence между
контекстами без отдельной политики доступа.

## Форматы и лимиты

| Формат | Обработка |
|---|---|
| XLSX/XLS | PhpSpreadsheet в read/data-only режиме, без вычисления формул |
| CSV/TSV | UTF-8/Windows-1251, `;`, `,`, tab |
| DOCX | безопасная проверка ZIP, таблицы и абзацы без embedded objects |
| Текстовый PDF | локальный `smalot/pdfparser` |
| Сканированный/смешанный PDF | Vision OCR; у смешанного — только страницы без текста |
| JPEG/PNG | напрямую Vision после проверки размеров |
| TIFF | многостраничное преобразование `tiff2pdf` в PDF |
| BMP/GIF/HEIC | безопасное преобразование в PNG через Imagick при наличии decoder |
| DOC | `unsupported_format`, требуется DOCX или PDF |

Значения по умолчанию: файл 25 MB, Vision input 10 MB, PDF 50 страниц, OCR 30
страниц, изображение 20 MP, 20 листов, 20 000 строк, 100 колонок, Office ZIP до
5000 entries/200 MB распакованных данных/ratio 100. Timeout внешнего вызова — 120
секунд, job attempts — 4.

Для TIFF установите пакет `libtiff-tools` (`tiff2pdf`). Для BMP/GIF/HEIC, коррекции
ориентации и выборочного рендеринга страниц смешанного PDF нужен Imagick с нужными
codec и разрешённым PDF delegate/Ghostscript. Если системная возможность отсутствует,
импорт завершается безопасной понятной ошибкой, а не fallback-вызовом произвольного
shell/parser.

## Environment

Ниже все новые параметры; секреты задаются только в secret storage рабочей среды.
Безопасный default `AI_PRICE_LISTS_ENABLED=false`: production workflow включает
модуль только после успешных schema/Redis/storage/ClamAV/AI/Vision/MAX проверок.
`PRICE_LIST_AUTO_APPLY` программно остаётся `false`, а document logging всегда
принудительно выключен независимо от environment.

```dotenv
AI_PRICE_LISTS_ENABLED=true
AI_PRICE_LIST_AUTHORIZATION_ENABLED=false
AI_PRICE_LIST_QUEUE_CONNECTION=redis
AI_PRICE_LIST_QUEUE=price-lists
AI_PRICE_LIST_MAIL_QUEUE_CONNECTION=database
AI_PRICE_LIST_MAIL_QUEUE=mail-sync
AI_PRICE_LIST_STORAGE_DISK=yandex
AI_PRICE_LIST_STORAGE_PREFIX=supplier-price-lists

PRICE_LIST_MAX_FILE_MB=25
PRICE_LIST_MAX_PAGES=50
PRICE_LIST_MAX_OCR_PAGES=30
PRICE_LIST_MAX_OCR_FILE_MB=10
PRICE_LIST_MAX_IMAGE_MEGAPIXELS=20
PRICE_LIST_TIFF2PDF_BINARY=tiff2pdf
PRICE_LIST_MAX_SHEETS=20
PRICE_LIST_MAX_ROWS=20000
PRICE_LIST_MAX_COLUMNS=100
PRICE_LIST_MAX_ZIP_ENTRIES=5000
PRICE_LIST_MAX_UNCOMPRESSED_MB=200
PRICE_LIST_MAX_COMPRESSION_RATIO=100
PRICE_LIST_RETENTION_DAYS=730
PRICE_LIST_AUTO_APPLY=false
PRICE_LIST_FILE_SCANNER=null
PRICE_LIST_CLAMAV_SOCKET=
PRICE_LIST_CLAMDSCAN_BINARY=clamdscan
PRICE_LIST_CLAMD_CONFIG=/etc/clamav/clamd.conf
PRICE_LIST_CLAMDSCAN_TIMEOUT_SECONDS=120

PRICE_LIST_EXACT_MATCH_THRESHOLD=0.96
PRICE_LIST_PROBABLE_MATCH_THRESHOLD=0.70
PRICE_LIST_MAX_CANDIDATES=8
PRICE_LIST_AI_RERANKING_ENABLED=true
PRICE_LIST_AI_RERANK_CHUNK_SIZE=20
PRICE_LIST_PRICE_CHANGE_WARNING_PERCENT=25

AI_PRICE_LIST_MAX_ALLOWED_HOSTS=max.ru,oneme.ru
AI_PRICE_LIST_MAX_DOWNLOAD_TIMEOUT=30
AI_PRICE_LIST_MAX_DOWNLOAD_REDIRECTS=2
AI_PRICE_LIST_MAX_ACK_ENABLED=true
AI_PRICE_LIST_MAX_ATTACHMENTS_PER_MESSAGE=10
AI_PRICE_LIST_STALE_AFTER_MINUTES=20
AI_PRICE_LIST_MAX_RECOVERIES=3

AI_PROVIDER=yandex
AI_PRICE_LIST_MODEL=yandexgpt-5.1
AI_PRICE_LIST_TIMEOUT_SECONDS=120
AI_PRICE_LIST_MAX_ATTEMPTS=4
AI_PRICE_LIST_DAILY_TOKEN_LIMIT=500000
AI_PRICE_LIST_MONTHLY_TOKEN_LIMIT=5000000
AI_PRICE_LIST_DAILY_OCR_PAGE_LIMIT=500
AI_PRICE_LIST_MONTHLY_OCR_PAGE_LIMIT=5000
AI_PRICE_LIST_REQUESTS_PER_MINUTE=30
AI_PRICE_LIST_MAX_ROWS_PER_CHUNK=20
AI_PRICE_LIST_CLASSIFICATION_MIN_CONFIDENCE=0.90
AI_PRICE_LIST_ESTIMATED_COST_PER_1000_TOKENS=
AI_PRICE_LIST_COST_CURRENCY=RUB

YANDEX_CLOUD_FOLDER_ID=
YANDEX_AI_API_KEY=
YANDEX_AI_BASE_URL=https://ai.api.cloud.yandex.net/v1
YANDEX_AI_DATA_LOGGING=false
YANDEX_VISION_OCR_ENDPOINT=https://ocr.api.cloud.yandex.net/ocr/v1/recognizeText
YANDEX_VISION_OCR_MODEL=table
YANDEX_VISION_OCR_LANGUAGES=ru,en
```

Также используются уже существующие `MAX_API_URL`, `MAX_ACCESS_TOKEN`,
`MAX_WEBHOOK_SECRET` и параметры приватного disk `yandex`. Не помещайте ключ AI в
frontend-переменные `VITE_*`.

## Yandex Cloud: service account и минимальные права

Рекомендуется отдельный service account AI, не совпадающий с аккаунтом Object Storage.
Для текущих контрактов нужны:

- роль каталога `ai.languageModels.user` для AI Studio;
- роль каталога `ai.vision.user` для Vision OCR;
- API key с точными scopes `yc.ai.languageModels.execute` и
  `yc.ai.vision.execute`, сроком действия один год;
- доступ к Object Storage остаётся у уже настроенного Laravel disk и не расширяется
  ради AI.

Широкие `admin`/`editor` не нужны. В `YANDEX_CLOUD_FOLDER_ID` указывается каталог,
которому принадлежит модель/доступ; в `YANDEX_AI_API_KEY` — API key service account.
Провайдер использует `https://ai.api.cloud.yandex.net/v1/chat/completions`, явный URI
модели `gpt://<folder>/yandexgpt-5.1`, `Authorization: Api-Key`, `OpenAI-Project` и
`x-data-logging-enabled: false`. Vision вызывается через backend
`/ocr/v1/recognizeText` тем же принципом минимального доступа.

Актуальные первичные источники:

- [Chat Completions API](https://aistudio.yandex.ru/docs/ru/ai-studio/api/Chat-Completions/createChatCompletion.html)
- [Structured Outputs](https://aistudio.yandex.ru/docs/ru/ai-studio/operations/generation/completions-structured.html)
- [отключение логирования](https://aistudio.yandex.ru/docs/ru/ai-studio/operations/disable-logging.html)
- [доступ AI Studio](https://aistudio.yandex.ru/docs/ru/ai-studio/security/index.html)
- [актуальные модели](https://aistudio.yandex.ru/docs/ru/ai-studio/concepts/generation/models.html)
- [Vision OCR quickstart](https://yandex.cloud/ru/docs/vision/quickstart)
- [API key и scopes](https://yandex.cloud/ru/docs/iam/concepts/authorization/api-key)
- [официальные Docker images ClamAV](https://docs.clamav.net/manual/Installing/Docker.html)
- [MAX subscriptions](https://dev.max.ru/docs-api/methods/POST/subscriptions),
  [получение сообщения](https://dev.max.ru/docs-api/methods/GET/messages/-messageId-/),
  [Message](https://dev.max.ru/docs-api/objects/Message),
  [uploads](https://dev.max.ru/docs-api/methods/POST/uploads)

## Установка и запуск

Несмотря на корневое ограничение проекта `php: ^8.2`, текущий `composer.lock` уже
содержит Symfony 8-компоненты с требованием PHP 8.4. Поэтому release-host этого
состояния проекта должен использовать PHP 8.4 и проходить
`composer check-platform-reqs --no-dev`. Также нужны расширения проекта, Redis и Node,
поддерживаемый Vite 8 (Node 20.19+ / 22.12+; проверенная сборка выполнена на Node 24).
Команды приведены для обычного существующего deploy-процесса, без Docker/Coolify:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan optimize
php artisan queue:restart
```

Seeder даёт все права роли `admin`; `manager` получает view/process/review/assign,
но не apply и не технические ошибки. Назначайте `ai_price_lists.apply` и
`ai_price_lists.view_technical` только отдельно уполномоченным сотрудникам.

Production использует отдельный systemd unit из
`deploy/systemd/pischeprom-price-lists-worker.service.template`. Он слушает только
Redis-очередь `price-lists`, получает supplementary group `clamav`, перезапускается
при сбое и корректно останавливается перед очередным deploy.

Однократная подготовка и активация выполняются только через ручной GitHub Actions
workflow `.github/workflows/ai-price-lists-production.yml`:

1. `action=plan` — read-only проверка service account и двух IAM-ролей;
2. `action=apply`, confirmation `ACTIVATE_AI_PRICE_LISTS` — установка ClamAV,
   Ghostscript, Imagick/GD и `libtiff-tools`, поиск заранее созданного отдельного
   service account, создание ограниченного API key и systemd worker;
3. workflow атомарно обновляет только allowlist AI-параметров server-side `.env`,
   не передаёт ключ в artifact/GitHub secret и выполняет полный production preflight;
4. при ошибке новый ключ удаляется, `.env` восстанавливается из server-side backup,
   а прежнее состояние worker возвращается.

Для первой настройки MAX токен уже прошедшего модерацию бота временно добавляется
как Environment secret `MAX_ACCESS_TOKEN` в GitHub Environment `production`.
Workflow маскирует значение, передаёт его по закреплённому SSH-соединению во временном
файле с mode `0600` и удаляет файл после запуска. `MAX_WEBHOOK_SECRET` генерируется
криптографически непосредственно на VPS и не попадает в GitHub. При неуспешном
preflight оба изменения откатываются вместе с `.env`; после успешной активации
временный GitHub secret `MAX_ACCESS_TOKEN` следует удалить — рабочая копия остаётся
только в server-side `.env`.

Для ручной альтернативы пример Supervisor process остаётся таким:

```ini
[program:pischeprom-price-lists]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/pischeprom/artisan queue:work redis --queue=price-lists --sleep=1 --tries=4 --timeout=180 --max-time=3600
directory=/var/www/pischeprom
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/pischeprom-price-lists.log
stopwaitsecs=240
```

Пути адаптируйте к фактическому release. Стандартный scheduler проекта должен
продолжать выполняться каждую минуту:

```cron
* * * * * cd /var/www/pischeprom && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Он запускает `price-lists:recover-stale` каждые 10 минут. Перед ручным восстановлением:

```bash
php artisan price-lists:recover-stale --dry-run
php artisan price-lists:recover-stale
```

Для старых писем, созданных до включения автозагрузки, есть пакетный backfill. Без
`--apply` команда ничего не изменяет; пакет идёт от новых писем к старым, а выведенный
cursor продолжает обход без пропусков:

```bash
php artisan price-lists:mail-backfill --limit=10
php artisan price-lists:mail-backfill --apply --limit=10
php artisan price-lists:mail-backfill --apply --limit=10 --cursor=12345
```

На production эти операции запускаются workflow
`.github/workflows/ai-price-list-mail-backfill.yml`: `plan` — read-only, `apply` требует точное
подтверждение `BACKFILL_AI_PRICE_LIST_MAIL`. Workflow проверяет закреплённый SSH host,
точный deployed commit, schema, Redis и оба worker до постановки задач в очередь.

## Безопасность и retention

- оригиналы остаются на приватном Object Storage disk; постоянный URL не выдаётся;
- download проходит policy, а quarantined-файл доступен только с
  `ai_price_lists.view_technical`;
- временные локальные файлы имеют закрытые permissions и удаляются в `finally`;
- secrets, Authorization, URL файла и полный документ не попадают в audit/AI usage;
- Office ZIP и MIME проверяются по содержимому; формулы не вычисляются;
- `PRICE_LIST_FILE_SCANNER=clamav` обязателен для production-активации. Workflow
  определяет локальный Unix socket и проверяет его безопасным синтетическим файлом
  через официальный `clamdscan --stream`, не передавая закрытый путь daemon-процессу.
  Если первичная загрузка базы через FreshClam недоступна из-за CDN cooldown,
  provisioner один раз извлекает подписанную базу из официального образа
  `clamav/clamav:1.5.3`, закреплённого по digest и совпадающего с host engine,
  проверяет её локальным `clamscan`, после чего обновления снова выполняет FreshClam;
  default `null` оставлен только для локальной разработки и тестов;
- дневные/месячные token и OCR page budgets проверяются до внешнего запроса;
- `PRICE_LIST_RETENTION_DAYS` задаёт политику, но модуль намеренно ничего не удаляет
  автоматически. Сначала должна быть утверждена отдельная процедура, сохраняющая
  audit/provenance и учитывающая незавершённые импорты;
- автоматического rollback уже применённых append-only цен нет.

## Локальные тесты

Автотесты не обращаются к Yandex/MAX и используют fake providers, `Storage::fake` и
синтетические generators:

```bash
php -d memory_limit=512M vendor/bin/phpunit --colors=never tests/Unit/AiPriceLists tests/Feature/AiPriceLists
php -d memory_limit=512M vendor/bin/phpunit --colors=never
vendor/bin/pint --test
npm run build
```

Прямой запуск PHPUnit здесь важен: `artisan test` создаёт дочерний PHP-процесс и в
текущем окружении не наследует увеличенный `memory_limit`.

Для изолированной проверки миграций без рабочего подключения:

```bash
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan migrate:fresh --force
php artisan route:list --path=ai/price-lists
php artisan route:list --path=max/webhook
```

Перед production deploy миграция дополнительно проверена на изолированной базе
MySQL 8.0.35: выполнен полный `migrate:fresh`, тесты модуля и проверка таблиц, после
чего временная база удалена.

Санитизированный production preflight можно повторить на VPS без вывода секретов:

```bash
php artisan price-lists:production-preflight --all
```

## Ручной smoke-test

### Email

1. На staging отправить синтетический CSV/XLSX с `прайс` в имени от email, точно
   связанного с тестовой `Entity`.
2. Дождаться существующего mail sync и worker `price-lists`.
3. Проверить один импорт, ссылку на письмо, определённого поставщика, raw locator,
   кандидатов и переход в review/ready.
4. Повторить синхронизацию того же письма и убедиться, что импорт не продублировался.
5. Применить одну тестовую строку уполномоченным пользователем; повторный apply не
   должен создать вторую `supplier_good_prices`.

### MAX

1. На staging проверить существующую subscription: callback указывает на
   `/api/max/webhook`, содержит `message_created`, а secret совпадает с
   `MAX_WEBHOOK_SECRET`.
2. Отправить допустимый файл из чата с `entity_id`, затем из непривязанного чата.
3. Проверить быстрый HTTP 200, один webhook event/import, одно подтверждение бота и
   соответственно определённого/неопределённого поставщика.
4. Повторно доставить тот же update и убедиться, что импорт и ответ не дублируются.
5. Отправить oversized/неподдерживаемый файл и проверить краткую безопасную ошибку.

### Yandex

На staging использовать отдельный синтетический скан без персональных данных. Проверить
request ID, OCR pages, tokens и отсутствие документа в application log. Реальный вызов
невозможно проверить без действующих Yandex credentials и сетевого доступа окружения.

## Retry и известные ограничения

Кнопка `Повторить этап` переиспользует уже сохранённый оригинал и разрешена только
policy/машиной состояний. Подтверждённые строки не перезаписываются. Для массового
восстановления сначала используйте `--dry-run`; после исчерпания лимита восстановлений
импорт останавливается для диагностики.

Ограничения первой версии:

- `.doc` не поддерживается;
- качество таблиц PDF зависит от локального text layer и Vision;
- selective OCR смешанного PDF и нестандартные изображения требуют корректных
  Imagick delegates; TIFF — `libtiff-tools`;
- ambiguous image подтверждается вручную до OCR;
- callback-кнопки MAX для неоднозначного файла не добавлены: используется безопасный
  статус и ручное подтверждение в панели;
- автоматических email-ответов нет;
- статус сотрудникам отправляется существующим Laravel notification/email-контуром;
- цена хранится отдельно от существующих продажных цен, интеграция в downstream
  закупочные расчёты требует отдельного бизнес-решения;
- пользовательский end-to-end smoke через реальное письмо и MAX-вложение требует
  специально привязанного тестового поставщика; технические внешние smoke для Object
  Storage, MAX, AI Studio и Vision выполняет production workflow синтетическими данными.
