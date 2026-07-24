# Интеграция «Банк» со Sber API «Компаниям»

## Назначение и границы

Модуль получает расчётные счета, последние известные остатки и выписки Sber API, сохраняет операции, выполняет консервативную сверку входящих платежей и позволяет создавать только локальные черновики платёжных поручений.

Первый этап технически read-only:

- публичный `BankProviderInterface` не содержит методов создания, отправки, подписания или исполнения платежа;
- `SberReadOnlyApiClient` принимает только закрытый список alias + HTTP method + path;
- любой неизвестный alias, метод, path, path-параметр или pagination host отклоняется до HTTP-запроса;
- `SBER_READ_ONLY=true` проверяется непосредственно перед каждым запросом;
- OAuth scopes ограничены `openid` и `GET_STATEMENT_ACCOUNT`;
- endpoint платежей, ЭП, эквайринга, SberPay, QR и СБП отсутствуют;
- локальный `BankPaymentDraftService` не зависит от Sber-клиента;
- automated tests отдельно проверяют невозможность запроса `/fintech/api/v1/payments` и отсутствие write-маршрутов.

Банковские данные должны обрабатываться только приложением на российском VPS, MySQL/Redis и резервными копиями в российском контуре. Их нельзя передавать во внешние APM, AI, error-tracking или зарубежные промежуточные сервисы.

## Сверенная документация и API

На момент реализации использованы актуальные разделы:

- [сценарий выписок по счетам](https://developers.sber.ru/docs/ru/sber-api/scenarios/rko/statements/overview);
- [сводная информация и остатки за день](https://developers.sber.ru/docs/ru/sber-api/specifications/statement/summary);
- [получение дневной выписки](https://developers.sber.ru/docs/ru/sber-api/specifications/statement/transactions);
- [инкрементальная выписка](https://developers.sber.ru/docs/ru/sber-api/specifications/statement/statement-increment);
- [получение отдельной операции](https://developers.sber.ru/docs/ru/sber-api/specifications/statement/transactions-id);
- [OAuth](https://developers.sber.ru/docs/ru/sber-api/start/oauth);
- [тестовый стенд](https://developers.sber.ru/docs/ru/sber-api/start/test-stand);
- [песочница](https://developers.sber.ru/docs/ru/sber-api/start/sandbox);
- [TLS/mTLS](https://developers.sber.ru/docs/ru/sber-api/start/tls);
- [рекомендации безопасности](https://developers.sber.ru/docs/ru/sber-api/start/recommendations);
- [промышленный стенд](https://developers.sber.ru/docs/ru/sber-api/start/prom-stand).

Allowlist первого этапа:

| Alias | Метод | Ресурс |
|---|---:|---|
| `oauth.token` | POST | `/ic/sso/api/v2/oauth/token` |
| `oauth.user_info` | GET | `/ic/sso/api/v2/oauth/user-info` |
| `statement.summary` | GET | `/fintech/api/v2/statement/summary` |
| `statement.daily` | GET | `/fintech/api/v2/statement/transactions` |
| `statement.increment` | GET | `/fintech/api/v2/statement/increment` |
| `statement.transaction` | GET | `/fintech/api/v2/statement/transactionId` |

Для отдельной операции используются query-параметры `accountNumber`, `statementDate`, `operationId`. Печатные/файловые ресурсы Сбера не включены: локальная печать черновика формируется самим приложением.

Конкретные поля ответов, issuer, открытый ключ проверки `id_token`, hostname и выданные параметры необходимо ещё раз сравнить с договором и карточкой сервиса перед sandbox smoke test. Код не выдумывает обязательные неизвестные поля и нормализует несколько документированных вариантов контейнеров ответа.

## Архитектура

Основной код находится в `App\Domain\Banking`:

- `Contracts` — провайдер-независимый read-only контракт;
- `DTO` и backed enums — типизированная граница банка;
- `Providers/Sber` — OAuth, токены, mTLS-клиент, pagination и нормализация;
- `Reconciliation` — нормализация назначения и консервативное сопоставление;
- `Services` — импорт, синхронизация, распределения, аудит, dashboard и локальные черновики;
- `Events` — доменные события после commit;
- `Exceptions` — типизированные безопасные ошибки.

Контроллеры небольшие и находятся в `App\Http\Controllers\Banking`, валидация — в Form Requests, доступ — через Policies/Gates и middleware. Все локальные изменяющие web-маршруты используют стандартную CSRF-защиту Laravel и отдельные rate limiters.

Очередь:

- `SyncSberAccountsJob`;
- `SyncSberStatementsJob`;
- `ReconcileBankTransactionsJob`;
- `RefreshSberTokenJob`;
- `CheckSberCredentialsExpiryJob`.

Jobs используют Redis connection, очередь `banking`, уникальные ключи, ограниченные retries, exponential backoff с jitter. Синхронизация счёта дополнительно защищена distributed lock по connection/account.

## Канонический объект оплаты

В проекте не обнаружена модель выставленного покупателю счёта. `Quotation` — строка котировки цены товара, а не invoice. Поэтому канонический receivable первого этапа — существующая `Sale`.

В `sales` добавлены:

- `payment_reference`;
- `payment_status`;
- `paid_amount`;
- `outstanding_amount`;
- `overpaid_amount`;
- `paid_at`.

Одна банковская сумма учитывается только через polymorphic allocation на `Sale`; отдельной параллельной «оплаты счёта» не создаётся. Коммерческий статус и сумма продажи не меняются. `Purchase` используется только как необязательный источник локального исходящего черновика.

Миграция переводит `sales.total` и `purchases.amount` в `DECIMAL(20,2)`, заполняет `outstanding_amount` существующих продаж их суммой, а новые `Sale` сразу получают полный остаток долга. Производные поля оплаты исключены из mass assignment и меняются только банковским сервисом.

## Таблицы и связи

| Таблица | Назначение |
|---|---|
| `bank_connections` | Подключение организации, зашифрованные tokens, статусы и сроки |
| `bank_oauth_attempts` | SHA-256 state/nonce, TTL и одноразовое использование |
| `bank_accounts` | Счета, маски, последний известный остаток и cursor |
| `bank_account_balance_snapshots` | История остатков «по состоянию на» |
| `bank_transactions` | Неудаляемые операции, обновляемые с сохранением зашифрованных ревизий |
| `bank_transaction_revisions` | Зашифрованные ревизии изменившихся payload |
| `bank_sync_runs` | История запусков и счётчики |
| `bank_sync_errors` | Безопасные ошибки без request/response body |
| `bank_match_suggestions` | Кандидаты, score, правила и решение |
| `bank_transaction_allocations` | Частичные и множественные распределения |
| `bank_payment_order_drafts` | Только локальные исходящие черновики |
| `bank_audit_events` | Append-only hash chain аудита |

Токены и raw payload используют encrypted casts Laravel. Денежные расчёты идут через decimal strings и minor units, без `float`.

## OAuth Authorization Code

1. Администратор с подтверждённым паролем выбирает собственную `Entity`.
2. Backend генерирует 32 случайных байта для `state` и `nonce`.
3. В БД сохраняются только SHA-256 hashes, срок жизни и инициатор.
4. Пользователь перенаправляется на allowlisted SberBusiness ID host.
5. Callback атомарно блокирует попытку, проверяет TTL и сразу помечает её использованной.
6. Authorization code немедленно обменивается на tokens по mTLS.
7. При наличии `id_token` проверяются RS256 signature, `iss`, `aud`, `exp`, `iat`, `nonce`.
8. Новая token pair шифруется и сохраняется в одной DB transaction.
9. После callback в Redis ставится синхронизация счетов и первичный импорт.

PKCE не включён, поскольку его нужно включать только при поддержке конкретной карточкой сервиса и настройками, выданными Сбером.

Обычный API access token рассчитан примерно на 60 минут. Refresh token обновляется новой парой и имеет скользящий срок, который по текущей документации составляет до 180 дней от успешного использования. Текущая документация указывает срок client secret 40 дней, а TLS-сертификата — 12 месяцев; модуль не пытается ротировать secret через API, а напоминает о безопасной ручной ротации. При 401 процесс получает distributed lock, перечитывает строку подключения, обновляет пару при необходимости и повторяет исходный запрос ровно один раз. Повторный 401 переводит подключение в `reauthorization_required`; временный network/5xx/429 сбой refresh оставляет возможность ограниченного retry и не изображает потерю OAuth-согласия.

## mTLS и секреты

Секреты не должны находиться в Git, `public`, `.env` в открытом виде или базе:

```text
/run/secrets/pischeprom/sber/
├── client-secret
├── client.crt
├── client.key
├── client-key-password   # только если ключ зашифрован
└── id-token-public.pem
```

Пример прав:

```bash
sudo chown www-data:www-data /run/secrets/pischeprom/sber/client-secret \
  /run/secrets/pischeprom/sber/client.key \
  /run/secrets/pischeprom/sber/client-key-password
sudo chmod 0600 /run/secrets/pischeprom/sber/client-secret \
  /run/secrets/pischeprom/sber/client.key \
  /run/secrets/pischeprom/sber/client-key-password
```

Runtime-клиент, а не только health check, запрещает secret/private-key paths внутри репозитория, широкие права приватного ключа, не-HTTPS base URL, credentials в URL и hostname вне общего и environment-specific allowlist. Sandbox нельзя направить на production host и наоборот. TLS verification и hostname verification никогда не отключаются. `SBER_CA_BUNDLE_PATH` можно оставить пустым для системного trust store либо указать доверенный CA bundle.

## Переменные окружения

Секретные значения указываются только путями к файлам:

```dotenv
BANKING_ENABLED=false
BANKING_PROVIDER=sber
BANKING_TIMEZONE=Europe/Moscow
BANKING_QUEUE=banking
BANKING_QUEUE_CONNECTION=redis
BANKING_LOCK_STORE=redis
BANKING_UNIDENTIFIED_NOTIFICATION_AMOUNT=100000.00
BANKING_LOG_LEVEL=info
BANKING_LOG_DAYS=30

SBER_API_ENABLED=false
SBER_READ_ONLY=true
SBER_ENVIRONMENT=sandbox
SBER_CLIENT_ID=
SBER_CLIENT_SECRET_FILE=
SBER_CLIENT_SECRET_EXPIRES_AT=
SBER_REDIRECT_URI=
SBER_MTLS_CERT_PATH=
SBER_MTLS_KEY_PATH=
SBER_MTLS_KEY_PASSWORD_FILE=
SBER_CA_BUNDLE_PATH=
SBER_JWT_PUBLIC_KEY_PATH=
SBER_JWT_ISSUER=
SBER_ALLOWED_HOSTS=efs-sbbol-ift-web.testsbi.sberbank.ru,iftfintech.testsbi.sberbank.ru,sbi.sberbank.ru,fintech.sberbank.ru
SBER_SANDBOX_AUTH_BASE_URL=https://efs-sbbol-ift-web.testsbi.sberbank.ru:9443
SBER_SANDBOX_API_BASE_URL=https://iftfintech.testsbi.sberbank.ru:9443
SBER_SANDBOX_ALLOWED_AUTH_HOSTS=efs-sbbol-ift-web.testsbi.sberbank.ru
SBER_SANDBOX_ALLOWED_API_HOSTS=iftfintech.testsbi.sberbank.ru
SBER_PRODUCTION_AUTH_BASE_URL=https://sbi.sberbank.ru:9443
SBER_PRODUCTION_API_BASE_URL=https://fintech.sberbank.ru:9443
SBER_PRODUCTION_ALLOWED_AUTH_HOSTS=sbi.sberbank.ru
SBER_PRODUCTION_ALLOWED_API_HOSTS=fintech.sberbank.ru
SBER_SCOPES=openid,GET_STATEMENT_ACCOUNT
SBER_REQUEST_TIMEOUT_SECONDS=30
SBER_CONNECT_TIMEOUT_SECONDS=10
SBER_OAUTH_STATE_TTL_MINUTES=10
SBER_TOKEN_REFRESH_LEEWAY_SECONDS=300
SBER_SYNC_INTERVAL_MINUTES=15
SBER_INITIAL_IMPORT_DAYS=90
SBER_CONTROL_SYNC_DAYS=3
SBER_INCREMENTAL_OVERLAP_SECONDS=120
SBER_AUTO_MATCH_ENABLED=true
SBER_AUTO_MATCH_THRESHOLD=90
```

После изменения `.env`:

```bash
php artisan config:clear
php artisan config:cache
php artisan bank:sber:health
```

Health command не выполняет сетевой запрос и не печатает значения или пути секретов.

## Sandbox: порядок подключения

1. Получить sandbox/тестовый доступ, client ID, client secret, mTLS certificate/key, redirect URI и параметры проверки `id_token` в личном кабинете/по договору Сбера.
2. Разместить secret-файлы вне репозитория с владельцем процесса PHP и правами `0600`.
3. Зарегистрировать точный callback:

   ```text
   https://<российский-домен>/banking/sber/oauth/callback
   ```

4. Оставить `SBER_ENVIRONMENT=sandbox`, `SBER_READ_ONLY=true`.
5. Заполнить только выданные параметры; не добавлять payment scopes.
6. Выполнить локальный health check.
7. Запустить worker/scheduler.
8. Для OAuth Authorization Code войти администратором с включённой существующей 2FA, открыть `/Ameise/bank`, выбрать собственную `Entity`, подтвердить пароль и нажать «Подключить Сбер».
9. Для sandbox набора «Компаниям», если пара access/refresh token выпущена непосредственно в Личном кабинете Sber API, импортировать её только защищённой командой из временных файлов по инструкции ниже.
10. Проверить queued account sync и initial 90-day import в UI.
11. Только отдельным явным решением выполнить sandbox smoke:

   ```bash
   php artisan bank:sber:sync --connection=<ID> --incremental --sandbox-smoke
   ```

Реальный Sber API не используется automated tests: все HTTP-ответы подменяются.

### Однократный импорт sandbox-токенов из Личного кабинета

Токены нельзя передавать аргументами команды, сохранять в `.env`, GitHub
Actions, репозитории или shell history. Создайте на VPS два временных файла
вне приложения, доступных только пользователю Laravel:

```text
/run/secrets/pischeprom/sber-import/access-token
/run/secrets/pischeprom/sber-import/refresh-token
```

Оба файла должны иметь владельца `forge` и права `0600`. Импорт работает
только при `SBER_ENVIRONMENT=sandbox`, `SBER_READ_ONLY=true`, точных scopes
`openid,GET_STATEMENT_ACCOUNT` и от имени активного CRM-администратора.

```bash
php artisan bank:sber:import-sandbox-tokens \
  --owner-entity=<ENTITY_ID> \
  --connected-by=<ADMIN_USER_ID> \
  --access-token-file=/run/secrets/pischeprom/sber-import/access-token \
  --refresh-token-file=/run/secrets/pischeprom/sber-import/refresh-token \
  --access-expires-at=<ISO-8601> \
  --refresh-expires-at=<ISO-8601>
```

Команда не выполняет сетевой запрос, сохраняет токены только через Laravel
encrypted casts, создаёт append-only audit event и после успешной транзакции
удаляет оба plaintext-файла. Существующее sandbox-подключение заменяется
только с явным `--replace`. Client secret и mTLS private key остаются в
постоянном защищённом хранилище файлов, поскольку нужны для refresh и mTLS.

## Миграции, права, worker и scheduler

После резервной копии и проверки плана миграций:

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
```

Миграции в рамках разработки автоматически не запускались.

Worker:

```bash
php artisan queue:work redis --queue=banking --tries=5 --timeout=1800
```

Для supervisor/systemd следует завести отдельный процесс и настроить graceful restart при deploy:

```bash
php artisan queue:restart
```

Production deploy выполняется через GitHub Actions. Workflow, обязательные
GitHub Environment secrets, закрепление SSH host key и установка отдельного
banking worker описаны в `docs/github-actions-deployment.md`. Sber credentials,
`.env`, банковские данные и логи через GitHub Actions не передаются.

Scheduler вызывается системным cron раз в минуту:

```cron
* * * * * cd /path/to/pischeprom && php artisan schedule:run >> /dev/null 2>&1
```

Laravel schedule:

- incremental — каждые `SBER_SYNC_INTERVAL_MINUTES`, по умолчанию 15;
- контроль текущего и трёх предыдущих дней — ежедневно;
- обновление списка счетов — ежедневно;
- упреждающее обновление token — каждые 30 минут;
- проверка сроков credentials — ежедневно.

Команды:

```bash
php artisan bank:sber:health
php artisan bank:sber:import-sandbox-tokens --help
php artisan bank:sber:sync --connection=<SANDBOX_ID> --from=2026-07-01 --to=2026-07-24 --sandbox-smoke
php artisan bank:sber:sync --connection=<SANDBOX_ID> --incremental --sandbox-smoke
php artisan bank:reconcile --from=2026-07-01 --to=2026-07-24
```

По умолчанию команды только ставят jobs в Redis. Для sandbox команда синхронизации всегда требует явный `--sandbox-smoke`; для production этот флаг, наоборот, отклоняется. `--sync` предназначен для контролируемой диагностики; диапазон ограничен 366 днями. При `SBER_API_ENABLED=false` синхронизация прекращается до постановки запроса.

## Синхронизация и идемпотентность

Первичный импорт запрашивает дни последовательно за настраиваемые 90 дней. Каждая дневная выписка полностью проходит pagination до отсутствия `rel=next`; только после получения всех страниц данные дня импортируются. Для каждого банковского дня официальный read-only ресурс `statement/summary` сохраняет snapshot исходящего остатка и `composedDateTime`.

Incremental использует `lastModifyDate` с overlap 120 секунд и обновляет последний известный остаток через `statement/summary`. Cursor изменяется только после успешного получения, нормализации и сохранения всей выписки и остатка. Если ответ содержит `reloadTime`, полный банковский день перезагружается до продвижения cursor.

Историческая ручная выгрузка всегда добавляет balance snapshot, но не заменяет карточку счёта остатком за более старый банковский день.

Идемпотентность обеспечивают:

- уникальный `(bank_account_id, provider_operation_id)`;
- SHA-256 fingerprint с подключением, счётом, датой и устойчивыми полями;
- semantic comparison decimal/date/enum полей;
- unique payload revision hash.

Операции не удаляются. При изменении или отмене создаётся revision, активные allocations деактивируются, `Sale` пересчитывается, старые pending suggestions истекают, операция переводится в review и отправляется безопасное уведомление. Изменённая проведённая операция не автосопоставляется повторно: снять review можно только явным ручным распределением либо отметкой «не требует сверки».

## Сверка

Автоматическая сверка выполняется только для `posted credit`. Debit отображается, но помечается не требующим сверки.

Алгоритм нормализует регистр, `ё/е`, `№`, пробелы и контекстные фразы. Номер извлекается только рядом со словами «счёт», «заказ», «продажа», `invoice`, а не произвольным substring.

Автоматическое allocation возможно только когда:

- кандидат единственный;
- точно совпал `payment_reference`/ID продажи;
- совпал ИНН или заранее известный расчётный счёт плательщика;
- сумма не противоречит положительному остатку долга;
- score достиг настроенного порога.

Одинаковая сумма сама по себе никогда не создаёт совпадение. Неоднозначность создаёт suggestion, не меняя оплату.

Отклонённый пользователем suggestion не создаётся повторно той же версией алгоритма для неизменившейся операции. При новом банковском payload прежнее отклонение становится `expired`, после чего обновлённая операция рассматривается заново. Ручная отмена allocation переводит операцию в `needs_review` с причиной `allocation_reversed`, поэтому фоновая автоматика не восстанавливает ошибочную связь; снять review можно новым ручным распределением либо явной отметкой «не требует сверки».

Формулы выполняются сервером в DB transaction с `lockForUpdate()`:

```text
paid_amount = сумма активных allocations по проведённым операциям
outstanding_amount = max(sale.total - paid_amount, 0)
overpaid_amount = max(paid_amount - sale.total, 0)
```

При превышении платежа над долгом распределяется только долг; остаток остаётся нераспределённым на банковской операции со статусом `overpaid` и требует ручной проверки. Никакого автоматического переноса превышения на другие продажи нет.

## Локальные черновики

Черновик можно создать вручную, из строки/карточки `Purchase` либо из карточки поставщика `Entity`. Deep link только предвыбирает серверные объекты: чувствительные реквизиты загружаются отдельным защищённым endpoint и доступны лишь при одновременных `bank.manage_payment_drafts` и `bank.view_sensitive`. Сервер проверяет связь закупки с получателем, ИНН, КПП, БИК, 20-значные счета, положительную decimal сумму, валюту, назначение, НДС и очерёдность.

Статусы `draft`, `exported`, `cancelled` локальны. `exported` означает только подготовленную HTML print form. В проекте не обнаружена действующая общая PDF-инфраструктура, поэтому PDF не добавлялся.

Во всех формах и печати показывается предупреждение:

> Это локальный черновик. Он не отправлен в Сбер, не подписан и не является исполненным платёжным поручением.

## Аудит, логи и хранение

- `bank_audit_events` не имеет update/delete API, модель блокирует эти операции и связывает записи SHA-256 hash chain.
- Banking log хранится в `storage/logs/banking-*.log`, создаётся с `0600` и проходит recursive redaction.
- Authorization header, tokens, secrets, TLS password, raw HTTP bodies и полные payload не записываются в ошибки.
- Обычный пользователь не видит encrypted raw payload.
- Номера счетов маскируются без `bank.view_sensitive`.
- Полные реквизиты локальных черновиков требуют одновременно `bank.manage_payment_drafts` и `bank.view_sensitive`.
- Срок хранения банковских записей, логов и backup задаётся внутренней политикой организации и требованиями договора; физическое удаление операций через модуль не предусмотрено.

## Ротация и восстановление

Client secret:

1. получить новый secret безопасным каналом;
2. атомарно заменить secret-файл с правами `0600`;
3. обновить `SBER_CLIENT_SECRET_EXPIRES_AT`;
4. `config:cache`, health check;
5. проверить refresh в sandbox.

mTLS certificate:

1. проверить новую цепочку и hostname вне приложения;
2. атомарно заменить certificate/key files;
3. сохранить private key `0600`;
4. перезапустить workers;
5. health check.

Уведомления формируются за 30, 14 и 7 дней и далее при критическом приближении срока. Они не содержат банковских реквизитов.

При `reauthorization_required`:

1. проверить health, scopes, срок secret/certificate и время сервера;
2. устранить конфигурационную причину;
3. администратору повторить OAuth через UI;
4. убедиться, что accounts/initial sync поставлены в очередь;
5. контрольной синхронизацией перечитать последние дни.

При ошибке страницы cursor не двигается. Для 429 учитывается `Retry-After`; 5xx/transport errors имеют ограниченные retries; 400/403 не повторяются бесконечно. Correlation ID ищется в UI и локальном banking log.

## Переключение production

Переключение не выполнялось. Production checklist:

- [ ] заключён договор и разрешён нужный read-only продукт;
- [ ] выданы production client ID/secret и mTLS certificate;
- [ ] точные issuer/key/redirect URI подтверждены;
- [ ] scopes равны `openid,GET_STATEMENT_ACCOUNT`;
- [ ] `SBER_READ_ONLY=true`;
- [ ] отсутствуют `PAY_*`, payment, signature scopes;
- [ ] secrets находятся вне repo/public, права проверены;
- [ ] hostname добавлены в общий и соответствующий sandbox/production allowlist;
- [ ] TLS verification не отключён;
- [ ] `APP_DEBUG=false`;
- [ ] PHP/queue/scheduler работают на российском VPS;
- [ ] GitHub Environment `production` ограничен веткой `main` и required reviewer;
- [ ] SSH host key закреплён через проверенный `SSH_KNOWN_HOSTS`;
- [ ] `pischeprom-banking-worker` установлен и слушает только очередь `banking`;
- [ ] MySQL, Redis, logs, backup и мониторинг остаются в российском контуре;
- [ ] banking channel исключён из внешних APM/error trackers;
- [ ] роли и маскирование проверены;
- [ ] backup и rollback миграций подготовлены;
- [ ] полный test suite и frontend build прошли;
- [ ] sandbox reconciliation подтверждена на тестовых операциях;
- [ ] production включается только отдельным change request.

После checklist меняются только выданные параметры и:

```dotenv
SBER_ENVIRONMENT=production
SBER_API_ENABLED=true
BANKING_ENABLED=true
```

## Будущие этапы, не реализованные сейчас

- поддержка отдельной модели invoice, если она появится в домене проекта, с явной миграцией канонического receivable;
- дополнительные read-only банковские провайдеры;
- экспорт локальной печатной формы в PDF после появления общей PDF-инфраструктуры;
- расширенная ручная обработка авансов/возвратов;
- отчёты по срокам дебиторской задолженности;
- формальная выгрузка аудита для внутреннего контроля.

Отправка, подписание, исполнение, подтверждение, отмена или повтор банковского платежа не являются будущим расширением этого read-only модуля и потребуют отдельного продукта, threat model, договора и архитектурного решения.
