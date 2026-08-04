# Деплой «Пищепром-Сервер» через GitHub Actions

## Граница данных

GitHub Actions используется только для проверки исходного кода, сборки публичных
Vue/Vite assets и управления деплоем по SSH.

В GitHub нельзя добавлять:

- production `.env`;
- Sber access/refresh tokens;
- client secret;
- mTLS certificate и private key;
- пароль private key;
- банковские payload, дампы БД, логи и резервные копии.

Эти данные остаются на российском VPS. Workflow передаёт на VPS только собранные
`public/build`, `bootstrap/ssr` и их SHA-256 checksum. PHP-код VPS получает через
существующий Git remote строго по commit SHA из workflow.

## Workflow

Основной production workflow находится в `.github/workflows/main.yml`.

Он запускается:

- после push в `main`;
- вручную через `workflow_dispatch`, если workflow выбран из ветки `main`.

Для одного production-окружения одновременно разрешён только один deploy.
Новый запуск ждёт завершения уже начавшегося и не отменяет его.

Для однократной подготовки AI-прайс-листов добавлен отдельный ручной workflow
`.github/workflows/ai-price-lists-production.yml`. Его всегда запускают сначала с
`action=plan`, затем с `action=apply` и точным подтверждением
`ACTIVATE_AI_PRICE_LISTS`. Он использует существующую GitHub→Yandex OIDC-федерацию,
создаёт выделенный service account только с ролями `ai.languageModels.user` и
`ai.vision.user`, а API key — только со scopes `yc.ai.languageModels.execute` и
`yc.ai.vision.execute` и сроком один год.

AI API key не сохраняется в GitHub secrets/artifacts: одноразовое значение маскируется,
передаётся напрямую во временный файл VPS с mode `0600`, атомарно переносится в
server-side `.env`, после чего временные копии удаляются. Режим `rotate_existing_key`
создаёт новый ключ, проводит все smoke и только после успеха удаляет прежние ключи,
созданные этим workflow.

### Verify job

До доступа к production secrets выполняются:

1. Composer validation, установка dev-зависимостей и блокирующие Composer/npm
   security audit без известных high-risk зависимостей.
2. Pint для банковского модуля.
3. Все unit tests и feature tests банковского модуля с SQLite.
4. `SBER_API_ENABLED=false`, поэтому запрос к Sber невозможен.
5. Client и SSR build на Node 22.18.0.
6. Проверка отсутствия symlink в build.
7. Формирование архива и SHA-256 checksum.

Полный legacy Feature suite пока не используется как deploy gate: существующая
миграция `2025_05_02_165318_drop_column_from_manufacturers_table.php` падает на
SQLite из-за индекса удаляемой колонки, а старый `ExampleTest` не подготавливает
таблицу `categories`. После исправления этих отдельных проблем workflow следует
перевести на полный `php artisan test`.

Build artifact хранится в GitHub Actions один день. Он не содержит `.env`,
`storage`, PHP dependencies или банковские данные.

Вывод команд, способных вернуть provider response или прикладное исключение,
не передаётся в Actions log. При их ошибке workflow показывает только безопасную
категорию и предлагает проверить локальные server-side logs.

### Deploy job

Deploy выполняется только после успешного verify job и только для `main`:

1. Проверяется SSH private key и закреплённый `known_hosts`.
2. Build и checksum передаются во временный каталог VPS.
3. Серверный deploy получает точный `GITHUB_SHA`.
4. Проверяется, что commit входит в `origin/main`.
5. Изменённые и неожиданные untracked-файлы на сервере сохраняются в отдельный
   recoverable Git stash; его SHA выводится в deploy log. Server-side `.env`,
   ignored `storage`, build и dependencies в stash не попадают.
6. Берётся `flock`, исключающий параллельный deploy.
7. Queue workers корректно останавливаются с ожиданием текущей job.
8. Текущий scheduler прерывается, Laravel переводится в maintenance mode.
9. Выполняются checkout exact SHA и `composer install --no-dev`.
10. Проверяются checksum и allowlist путей build-архива.
11. Выполняются миграции с `--isolated` и idempotent permission seeder.
12. Кэшируются config/routes/views.
13. `bank:sber:health --if-enabled` проверяет конфигурацию без HTTP-запроса.
14. Владение checkout возвращается исходному непривилегированному владельцу
    приложения и группе `www-data`, даже если SSH deploy выполнялся от root.
15. Запускаются queues, SSR и установленные systemd workers. Если SSR не
    поднимается за 15 секунд, deployment продолжает работу в штатном
    client-side fallback режиме и пишет warning.
16. Выполняются обязательные HTTP deploy smoke checks; их ошибка остаётся
    блокирующей.
17. Временный архив удаляется.

Если ошибка произошла до переключения кода, trap возвращает исходные сервисы и
выводит приложение из maintenance mode. После начала checkout автоматическое
восстановление небезопасно: приложение остаётся в maintenance mode, а
остановленные workers не запускаются до ручной диагностики. Автоматический
rollback миграций намеренно не выполняется: откат схемы требует отдельного
проверенного плана и резервной копии.

Текущая структура VPS использует приложение непосредственно в `TARGET_DIR`,
поэтому deploy выполняется под maintenance mode, а не переключением release
symlink. Переход на полностью атомарную схему `releases/current` потребует
отдельного изменения Nginx, systemd и путей secret-файлов.

## GitHub Environment

Создать GitHub Environment с точным именем:

```text
production
```

Рекомендуемые protection rules:

- deployment branches: только `main`;
- required reviewer;
- запрет self-review, если он доступен в тарифе;
- environment secrets вместо repository-wide secrets.

Нужны только следующие secrets:

```text
HOST
PORT
USERNAME
TARGET_DIR
SSH_PRIVATE_KEY
SSH_KNOWN_HOSTS
```

Назначение:

- `HOST` — адрес российского VPS;
- `PORT` — SSH port;
- `USERNAME` — непривилегированный deploy user;
- `TARGET_DIR` — абсолютный путь существующего Git checkout, не `/`;
- `SSH_PRIVATE_KEY` — отдельный deploy key;
- `SSH_KNOWN_HOSTS` — заранее проверенная строка host key.

Sber credentials не являются GitHub secrets и в этот список не входят.

## Закрепление SSH host key

`SSH_KNOWN_HOSTS` нельзя формировать внутри workflow через непроверенный
`ssh-keyscan`: это не защищает первый контакт от MITM.

Получить public host key через доверенный канал — консоль VPS-провайдера либо
проверенную административную машину:

```bash
ssh-keyscan -p <PORT> <HOST> > pischeprom_known_hosts
ssh-keygen -lf pischeprom_known_hosts
```

Сверить fingerprint с консолью сервера и только затем сохранить содержимое
файла в GitHub Environment secret `SSH_KNOWN_HOSTS`.

Private key должен принадлежать отдельному deploy user и не должен совпадать
с Sber mTLS private key.

Это ключ направления GitHub Actions → VPS. Отдельный read-only deploy key,
настроенный на самом VPS, используется в обратном направлении VPS → GitHub для
`git fetch origin main`. Ни один из этих ключей не должен использоваться для
mTLS Sber API.

## Однократная подготовка VPS

На VPS должны существовать:

- Git checkout в `TARGET_DIR`;
- server-side `.env`;
- `storage` и `bootstrap/cache`;
- PHP 8.2+ и Composer 2;
- Redis, MySQL и scheduler;
- Git deploy key для read-only получения `origin/main`;
- ограниченный passwordless `sudo` только для нужных `systemctl`, `chown` и
  permission-команд deploy-скрипта.

Sber secret-файлы размещаются вне `TARGET_DIR` и public web-root, например:

```text
/etc/pischeprom/sber/client-secret
/etc/pischeprom/sber/mtls.crt
/etc/pischeprom/sber/mtls.key
/etc/pischeprom/sber/mtls-key-password
/etc/pischeprom/sber/id-token-public.pem
```

Private key и secret/password files должны иметь права `0600` и владельца,
под которым работает Laravel worker.

## Banking worker

Пример unit находится в:

```text
deploy/systemd/pischeprom-banking-worker.service.example
```

До включения `BANKING_ENABLED` адаптировать `User`, `Group`, `WorkingDirectory`,
путь PHP и путь `artisan`, затем установить unit:

```bash
sudo install -m 0644 \
  deploy/systemd/pischeprom-banking-worker.service.example \
  /etc/systemd/system/pischeprom-banking-worker.service

sudo systemctl daemon-reload
sudo systemctl enable --now pischeprom-banking-worker
sudo systemctl status pischeprom-banking-worker
```

Если unit ещё не установлен, deploy продолжится с предупреждением. При этом
jobs очереди `banking` не будут обрабатываться, если её не слушает другой worker.

Scheduler остаётся системным cron:

```cron
* * * * * cd /home/forge/pischeprom && php artisan schedule:run >> /dev/null 2>&1
```

## Первый deploy банковского модуля

1. Создать backup MySQL и проверить восстановление.
2. Оставить в server-side `.env`:

   ```dotenv
   BANKING_ENABLED=false
   SBER_API_ENABLED=false
   SBER_READ_ONLY=true
   ```

3. Установить banking worker.
4. Настроить GitHub Environment и required reviewer.
5. Выполнить push в `main` и одобрить production deploy.
6. Проверить Actions log, состояние миграций и `/Ameise/bank`.
7. Разместить sandbox credentials только на VPS.
8. Выполнить `php artisan bank:sber:health`.
9. Только после успешного health check отдельно включить sandbox-интеграцию.

Workflow сам не вызывает Sber API и не запускает sandbox smoke synchronization.

## Rollback

Для отката кода создать revert commit в `main`; он пройдёт те же проверки и
deploy. Не выполнять `migrate:rollback` автоматически.

Если preflight сохранил локальные server-side изменения, их stash SHA указан
строкой `Server-side changes were preserved in Git stash ...`. Проверить его
нужно на VPS без автоматического применения:

```bash
git stash show --stat <STASH_SHA>
```

Не выполнять `git stash pop` поверх нового production-кода без ручной проверки
diff и совместимости. Stash остаётся только на российском VPS и не передаётся в
GitHub Actions.

Перед миграциями, меняющими или удаляющими данные, нужен отдельный backup и
проверенный forward-fix/rollback plan. Текущие банковские миграции добавляют
таблицы и платёжные поля, но всё равно должны применяться только после backup.

Если deploy остановился после checkout:

1. Не выполнять `artisan up`, пока не установлена причина.
2. Проверить текущий SHA, `storage/logs`, `migrate:status` и состояние БД.
3. Завершить forward-fix либо подготовить совместимый revert commit.
4. Выполнить config/route/view cache и локальные health checks.
5. Запустить SSR/workers.
6. Только затем выполнить `php artisan up` и smoke checks.

## Проверка после deploy

На VPS без вывода секретов:

```bash
php artisan about
php artisan migrate:status
php artisan bank:sber:health --if-enabled
php artisan schedule:list
php artisan route:list --path=Ameise/bank
sudo systemctl status pischeprom-banking-worker
```

Нельзя прикладывать к GitHub issue или Actions log содержимое `.env`, банковских
таблиц, Sber response payload или banking log.
