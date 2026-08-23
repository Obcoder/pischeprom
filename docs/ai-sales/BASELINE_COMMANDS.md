# Безопасные baseline-команды

## Правила

- Выполнять из корня репозитория.
- До начала проверить `git status --short --branch` и не перезаписывать чужие изменения.
- Использовать read-only schema commands. Не запускать `migrate` без отдельного rollout approval.
- Не выводить `.env`, credentials, connection URLs, tokens или private keys.
- Не использовать production database для тестов.
- Pint запускать только с `--test` на аудиторском этапе.

Категорически запрещены для baseline: `migrate:fresh`, `migrate:reset`, `db:wipe`, destructive SQL, `git reset --hard`, массовый `pint` без `--test` и любые команды очистки production storage.

## Git

```bash
git status --short --branch
git branch --show-current
git log -5 --oneline --decorate
git remote -v
git branch --list feature/ai-sales-agents
```

При чистом дереве:

```bash
git switch -c feature/ai-sales-agents
```

На Git 2.22 команда `switch` недоступна, поэтому совместимый вариант:

```bash
git checkout -b feature/ai-sales-agents
```

## Версии и конфигурация без секретов

```bash
php -v
composer --version
php artisan --version
composer show laravel/framework inertiajs/inertia-laravel laravel/jetstream laravel/sanctum
node --version
npm --version
npm ls vue vuetify @inertiajs/vue3 vite --depth=0
php artisan about --only=environment,drivers
```

Для проверки MySQL/Redis использовать только sanitized health/version query. Не печатать DSN/password. Отсутствие локального Redis допустимо при `QUEUE_CONNECTION=database` или `sync` в тестах.

## Маршруты и middleware

```bash
php artisan route:list --no-ansi
php artisan route:list -v --path=api/units --no-ansi
php artisan route:list -v --path=api/entities --no-ansi
php artisan route:list -v --path=api/mail-messages --no-ansi
php artisan route:list -v --path=Ameise/unit --no-ansi
```

`-v` обязателен при security audit: обычный список не показывает отсутствие `auth`.

## Схема и scheduler

Только read-only:

```bash
php artisan migrate:status --no-ansi
php artisan schedule:list --no-ansi
```

До Stage 02 полезны отдельные SELECT-отчёты по дублям `entity_unit`, Unit names, Entity requisites и contacts. Запросы должны запускаться сначала на snapshot/read replica и не содержать UPDATE/DELETE.

## Backend quality

```bash
composer validate --no-interaction --no-check-publish
php artisan test --compact
vendor/bin/pint --test
```

Если общий test runner упирается в local PHP memory, диагностировать конкретный набор напрямую:

```bash
php -d memory_limit=512M vendor/bin/phpunit tests/Unit/AiPriceLists/FileSecurityTest.php
```

Увеличение memory limit — только диагностический запуск; оно не исправляет причину и не должно скрывать production limit.

CI использует более узкие проверенные Pint/test-наборы из `.github/workflows/main.yml`. Для воспроизведения release gate следует копировать оттуда актуальные команды и env (`DB_CONNECTION=sqlite`, `QUEUE_CONNECTION=sync`, отключённые banking/SSR integrations), а не подключаться к рабочей БД.

## Frontend

В `package.json` сейчас есть только `dev` и `build`:

```bash
npm run
npm run build
```

Type-check и lint scripts не настроены, поэтому корректный результат аудита — «not configured», а не запуск выдуманной команды. Для установленного Vite 8 использовать Node 22.18 как в CI:

```bash
npm ci --no-audit --no-fund
npm run build
```

`npm ci` изменяет локальный `node_modules`, но не source/lockfile; перед production build всё равно нужен изолированный runner.

## Queue и deployment

Без запуска worker:

```bash
php artisan schedule:list --no-ansi
php artisan config:show queue
php artisan config:show mail
rg -n "queue:work|schedule:run|systemd|supervisor|php-fpm|nginx" .github docs infrastructure
```

Не запускать `queue:work` в аудите: он может потребить реальные pending jobs. Не выполнять `schedule:run`, потому что scheduler содержит сетевые синхронизации и банковские задачи.

## Финальная проверка документов

```bash
git status --short
git diff --check
git diff --stat
git diff -- docs/ai-sales
```

После проверки добавить только созданные документы и сделать заданный commit:

```bash
git add docs/ai-sales
git commit -m "chore(ai-sales): audit Unit Entity architecture and security baseline"
```

## Результаты аудита 2026-08-15

| Команда/проверка | Результат |
|---|---|
| Git status/branch/history/remote | PASS; исходный `main` был чист, создана `feature/ai-sales-agents` от `af839e447` |
| PHP/Laravel/Composer | PHP 8.4.1, Laravel 12.64.0, Composer 2.8.3 |
| Node/frontend | Node 16.14.0 локально; Vue 3.5.34, Vuetify 3.8.5, Inertia 3.1.1, Vite 8.2.0 |
| MySQL | PASS read-only; локальный MySQL 8.0.35 |
| Проверка локальных counts | PASS; проверенные Unit/Entity/transaction/mail таблицы пусты |
| `php artisan route:list` | PASS; полный список сформирован, critical middleware дополнительно проверены с `-v` |
| `php artisan migrate:status` | PASS; 16 pending, миграции не запускались |
| `php artisan schedule:list` | PASS |
| Composer validation | PASS; `composer.json` valid |
| `php artisan test --compact` | FAIL baseline: PHP memory limit 128 MiB exhausted в `PriceListFileValidator.php:38` после 76 progress tests |
| Повтор `php -d memory_limit=512M artisan test` | FAIL тем же лимитом 128 MiB: Artisan test subprocess не унаследовал CLI `-d` |
| Targeted `FileSecurityTest` через PHPUnit/512 MiB | PASS: 5 tests, 18 assertions |
| Полный `php -d memory_limit=512M vendor/bin/phpunit` | PASS: 384 tests, 2667 assertions, 5 skipped; peak memory 159 MiB |
| `vendor/bin/pint --test` | FAIL baseline: 161 style issue в 1168 проверенных файлах |
| Frontend type-check/lint | NOT CONFIGURED: scripts отсутствуют |
| `npm run build` | FAIL environment: Node 16 не экспортирует требуемый Vite/Rolldown `styleText`; CI использует Node 22.18 |
| `git diff --check` | PASS для созданных документов перед commit |

Baseline failures не исправлялись, потому что они не относятся к документации Stage 01. Для воспроизводимого результата сначала следует выровнять локальный Node с CI, затем отдельно исследовать memory behavior полного Artisan runner и только после этого оценивать реальные test failures.

### Pending migrations

Read-only status показал:

```text
2026_07_26_120000_create_mail_message_max_deliveries_table
2026_07_28_000001_create_logistics_vehicles_table
2026_07_28_000002_create_logistics_trips_and_expenses_tables
2026_07_28_000003_create_logistics_city_matrix_tables
2026_07_28_000004_create_logistics_routing_history_tables
2026_08_04_100000_create_ai_price_list_tables
2026_08_05_100000_create_avito_integration_tables
2026_08_05_110000_create_avito_messenger_archive_tables
2026_08_05_130000_add_crm_context_to_avito_messenger
2026_08_05_140000_create_avito_message_templates
2026_08_06_100000_create_avito_auto_reply_tables
2026_08_06_120000_add_home_building_type
2026_08_08_120000_normalize_telephone_entity_identity
2026_08_10_140000_create_avito_listing_good_links_table
2026_08_10_160000_create_avito_publication_tables
2026_08_10_170000_create_avito_workspace_settings_table
```

Всего 16 pending migrations. Список получен итоговым повторным `migrate:status`. Ни одна из них не запускалась.
