# Развёртывание логистики

> Для нового полного покрытия России и PMTiles источником истины является
> [MAP_RUSSIA.md](MAP_RUSSIA.md) и `infrastructure/logistics-gis/README.md`.
> Описанная ниже двухрегиональная Docker-инфраструктура сохранена как история
> уже задеплоенного контура и для совместимости/rollback. Она не является
> способом построения новой карты России. Workflow `routing.yml` теперь делает
> только лёгкие проверки и не принимает snapshot/не строит GIS-артефакты.

## Предварительные условия

- текущие поддерживаемые проектом PHP/Composer, Node.js 22.18.0 и MySQL;
- Redis для cache locks и отдельной очереди `routing`;
- Docker Engine с Compose v2 нужен только уже работающему legacy
  двухрегиональному routing runtime, если production действительно использует
  сохранённую compose-конфигурацию;
- scheduler Laravel раз в минуту;
- backup MySQL и проверенная процедура восстановления перед production-миграцией.

Исторический граф Northwestern + Central был построен отдельно от Laravel, но
не является текущим full-Russia routing runtime. Новый граф и PMTiles нельзя
собирать на GitHub-hosted runner или основном VPS:
фактические требования сначала рассчитывает
`infrastructure/logistics-gis/scripts/preflight.sh`, после чего ручной GitHub
workflow оркестрирует отдельную временную Linux VM достаточного размера.
Исходные PBF не заменяются generated tiles.

## Текущий production-режим

Активен map-only релиз `russia-20260802`: PMTiles, glyphs и sprites постоянно
доступны через bucket-specific HTTPS Object Storage origin, matching Valhalla
graph хранится в приватном bucket. Временный builder, его auto-delete disk и
security group удалены после публикации.

`/api/logistics/routing-status` в штатном дешёвом режиме сообщает
`map.available=true`, `routing.available=false`. Это означает: карта и
сохранённые геометрии доступны, а новые route/matrix jobs нельзя запускать до
отдельного включения Valhalla runtime.

## Laravel env

```dotenv
APP_TIMEZONE=Europe/Moscow

LOGISTICS_AUTHORIZATION_ENABLED=false
LOGISTICS_CURRENCY_CODE=RUB
LOGISTICS_ROUTING_DRIVER=valhalla
LOGISTICS_DEFAULT_ROUTING_PROFILE=truck
LOGISTICS_MATRIX_MAX_CITIES_PER_REQUEST=50
LOGISTICS_MATRIX_STALE_DAYS=30
LOGISTICS_MATRIX_BATCH_CITIES=10
LOGISTICS_ROUTING_QUEUE=routing
LOGISTICS_ROUTING_QUEUE_CONNECTION=redis-routing
LOGISTICS_ROUTING_REDIS_CONNECTION=default
LOGISTICS_ROUTING_RETRY_AFTER=180
LOGISTICS_ROUTING_LOCK_STORE=redis
LOGISTICS_OSM_DATA_VERSION=260725

VALHALLA_ENGINE_VERSION=3.6.3
VALHALLA_BASE_URL=http://127.0.0.1:8002
VALHALLA_CONNECT_TIMEOUT=3
VALHALLA_TIMEOUT=30
VALHALLA_RETRY_TIMES=2
VALHALLA_RETRY_DELAY_MS=250
```

Если Laravel работает контейнером в той же private Docker network, используйте внутреннее имя `http://valhalla:8002`. Для Laravel на host используется loopback URL выше. Не публикуйте порт на `0.0.0.0`.

`LOGISTICS_ROUTING_RETRY_AFTER` должен быть больше job timeout `120`; после изменения env выполните `php artisan config:clear`/`config:cache` и перезапустите worker.

## Историческая двухрегиональная сборка (не для full Russia)

Инфраструктура находится в `infrastructure/valhalla`. Образ закреплён как `ghcr.io/valhalla/valhalla-scripted:3.6.3`, runtime-порт — только `127.0.0.1`.

### GitHub Actions

Workflow `routing.yml` выполняет только syntax, calculator, boundary и pinned
toolchain checks. Обычный workflow приложения также не перестраивает GIS и
только обслуживает уже установленный routing worker.

Full-Russia обновление выполняется вручную по
[production runbook](MAP_RUSSIA.md#production-runbook) через
`yandex-gis-builder.yml`: точные подтверждения требуются для `build-start`,
`release-publish` и `destroy`. GitHub runner только управляет временной Yandex
VM и переносит проверенный JSON state; download/build выполняются detached на
самой VM, а публичный SSH открыт лишь для IP активного runner и закрывается в
конце каждого action.

### Ручная сборка

```bash
cd infrastructure/valhalla
cp .env.example .env

./scripts/download-osm.sh 260725
./scripts/build-graph.sh 260725
./scripts/activate-graph.sh 260725

docker compose --env-file .env -f compose.yml ps
curl --fail http://127.0.0.1:8002/status
```

Скрипт загружает согласованные по дате immutable extracts:

- `central-fed-district-260725.osm.pbf`;
- `northwestern-fed-district-260725.osm.pbf`.

Опубликованный Geofabrik MD5 проверяется до сборки; затем создаётся локальный `SHA256SUMS`. Build идёт в `data/graphs/260725.staging`, выполняет route и matrix smoke Санкт-Петербург → Москва и только после успеха переименовывает каталог. `activate-graph.sh` атомарно переключает symlink `current`, сохраняет `previous`, перезапускает runtime и повторяет smoke. При ошибке возвращается предыдущий symlink.

Не запускайте build profile как часть обычного Laravel deploy.

## Миграция приложения

Без destructive operations:

```bash
composer install --no-dev --classmap-authoritative --no-interaction --prefer-dist
php artisan optimize:clear
php artisan migrate --force --isolated
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=LogisticsExpenseCategorySeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Не применять `migrate:fresh`, `db:wipe`, `truncate` или ручное удаление таблиц. GitHub Actions запускает проверки и точный `scripts/deploy-production.sh`; скрипт уже включает обе идемпотентные seed-команды, проверку logistics routes и graceful restart установленного routing worker.

## Routing worker

Адаптируйте `User`, `Group`, `WorkingDirectory` и PHP path:

```bash
sudo install -m 0644 \
  deploy/systemd/pischeprom-routing-worker.service.example \
  /etc/systemd/system/pischeprom-routing-worker.service

sudo systemctl daemon-reload
sudo systemctl enable --now pischeprom-routing-worker
sudo systemctl status pischeprom-routing-worker
```

Фактическая команда unit:

```bash
php artisan queue:work redis-routing --queue=routing \
  --sleep=2 --tries=3 --timeout=120 --max-time=3600
```

Если unit отсутствует, deploy продолжается с предупреждением, но jobs останутся в очереди, пока другой worker не слушает `routing`.

## Scheduler

Оставьте существующий запуск раз в минуту:

```cron
* * * * * cd /absolute/path/pischeprom && php artisan schedule:run >> /dev/null 2>&1
```

Ежедневно в `01:35` scheduler только помечает истёкшие рассчитанные строки матрицы как `stale`; пересчёт запускается явно через UI или `logistics:matrix-refresh-stale`.

## Проверка после deploy

```bash
php artisan route:list --path=api/logistics
php artisan logistics:routing-health
php artisan logistics:matrix-calculate --cities=ID_SPB,ID_MOSCOW --profile=truck --dry-run
sudo systemctl status pischeprom-routing-worker
sudo journalctl -u pischeprom-routing-worker -n 100 --no-pager
```

Затем откройте `/Ameise/logistics` в анонимном окне: при
`LOGISTICS_AUTHORIZATION_ENABLED=false` должны работать страница, чтение и
изменяющие API-операции. Проверьте diagnostics, карту и справочники. В текущем
map-only режиме не ставьте тестовый маршрут в очередь: сначала включите
отдельный Valhalla runtime и дождитесь `services.routing.available=true`.
Проверка самой карты от routing не зависит.

Когда будет готова общая авторизация `/Ameise`, установите
`LOGISTICS_AUTHORIZATION_ENABLED=true`, пересоберите config cache и проверьте
Sanctum, подтверждение email и logistics permissions. Не утверждайте успешность
live-маршрута до положительного health/smoke на production-графе.
