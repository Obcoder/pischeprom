# Логистика: вся Россия и интерактивная карта

Обновлено: 2026-08-02.

## Фактический аудит до реализации

Переиспользован существующий модуль, а не создан второй контур логистики:

- Laravel 12.59 / PHP 8.4, Inertia 3, Vue 3, Vuetify, Vite и npm lockfile;
- `LogisticsTrip`, `Vehicle`, `LogisticsTripStop`; порядок остановок хранится в
  `logistics_trip_stops.sequence`;
- расходы рейса уже связаны с `checks` через `logistics_trip_expenses.check_id`;
- направленная матрица уже хранится в `logistics_city_distances`;
- история маршрутов уже хранится в `logistics_trip_routes`, текущая версия
  отмечается `is_current`, геометрия — `shape_polyline6`;
- существующие `RoutingProviderInterface`, `ValhallaRoutingProvider`, DTO,
  очереди, locks, stale-механизм и команды оставлены источником истины;
- Valhalla-клиент уже использует `/status`, `/route` и
  `/sources_to_targets`;
- координаты города: `cities.latitude/longitude`; подтверждённая routing-точка:
  `logistics_cities.routing_latitude/routing_longitude`;
- существующий `entity_locations` связан с `entities` и хранит `lat/lon`;
- политики/права логистики переиспользованы. Переключатель
  `LOGISTICS_AUTHORIZATION_ENABLED` в проекте по-прежнему по умолчанию `false`:
  владелец проекта явно подтвердил сохранение этого публичного режима
  `2026-08-02`. Permission model остаётся готовой к последующему включению
  общей авторизации через `LOGISTICS_AUTHORIZATION_ENABLED=true`.

В репозитории до дополнения была инфраструктура Valhalla только для двух ФО,
собираемая Docker-based workflow. Документация проекта утверждает Valhalla
`3.6.3`, loopback `127.0.0.1:8002` и OSM snapshot `260725`, но live production
в этой сессии не проверялся. MapLibre, PMTiles, Planetiler, glyphs/sprites и
проверка Nginx Range отсутствовали.

Доступная локальная MySQL из `.env` не содержала применённых таблиц логистики,
поэтому production-счётчики и browser-проверка данных из неё не выдавались за
фактические. Локальный Valhalla healthcheck был недоступен.

Baseline до изменений:

- релевантный backend suite: 35 тестов пройдены, 1 live Valhalla smoke пропущен;
- frontend build не стартовал на локальном Node 16.14, потому что текущий Vite
  требует Node 20.19+ / 22.12+; это ограничение окружения, не дефект карты;
- пользовательские изменения не удалялись и рабочая БД не очищалась.

## Что реализовано

### Backend и UI

- отдельная вкладка «Карта» внутри `/Ameise/logistics`;
- MapLibre GL JS `6.1.0` и PMTiles client `4.4.1`, закреплённые в npm lockfile;
- однократная регистрация PMTiles protocol и динамическая загрузка JS/CSS только
  при инициализации карты;
- собственный same-origin style, русский OpenMapTiles-compatible слой, локальные
  sprites/glyphs и видимая атрибуция OpenStreetMap/OpenMapTiles;
- bbox/zoom API с серверными лимитами для подтверждённых логистических городов,
  рейсов и `entity_locations`; точки кластеризуются, старые viewport-запросы
  отменяются;
- фильтры рейсов по периоду, статусу, автомобилю и городу, слои и легенда;
- on-demand GeoJSON `[longitude, latitude]` из сохранённого polyline6 — прямые
  отрезки между городами как автомобильный маршрут не строятся;
- пронумерованные по `sequence` остановки, popup с операцией и существующими
  плановыми/фактическими временами, диагностика отсутствующих координат;
- карта в существующей карточке рейса, выбор current/history без изменения
  активной версии, разные стили линий и `fitBounds` с обработкой 180-го
  меридиана;
- preview существующей ячейки матрицы. Для calculated/stale выполняется один
  точечный route request с TTL-кэшем; manual/no_route/failed/pending отличаются
  и не запускают массовый расчёт;
- слой контрагентов использует существующий `entity_locations`, выключен по
  умолчанию и ограничен bbox/limit;
- расширенная техническая диагностика: OSM release, охват Russia, PBF, Valhalla,
  PMTiles/Planetiler, preflight, smoke, Range и activation;
- ошибки WebGL/PMTiles оставляют табличные данные и действия доступными;
  map/listeners/markers/ResizeObserver освобождаются при unmount.

Существующие клиенты API не лишены прежней полной геометрии. Полные
`TripRouteResource` доступны как раньше; новый UI передаёт `summary=1`, получает
облегчённые строки и загружает выбранную геометрию отдельным map endpoint.

Новые endpoint-ы:

```text
GET /api/logistics/map/config
GET /api/logistics/map/style
GET /api/logistics/map/features
GET /api/logistics/trips/{trip}/map
GET /api/logistics/trips/{trip}/routes/{route}/map
GET /api/logistics/matrix/{distance}/preview
```

Все находятся в существующей logistics middleware/policy группе. PMTiles через
Laravel не проксируется; API не возвращает `/srv/...` и другие filesystem paths.

### Изменённые и созданные файлы

Созданы прикладные файлы:

```text
app/Http/Controllers/API/Logistics/MapConfigurationController.php
app/Http/Controllers/API/Logistics/MapController.php
app/Http/Controllers/API/Logistics/MatrixPreviewController.php
app/Http/Controllers/API/Logistics/TripMapController.php
app/Http/Requests/Logistics/MapFeaturesRequest.php
app/Http/Requests/Logistics/MatrixPreviewRequest.php
app/Http/Resources/Logistics/TripRouteSummaryResource.php
app/Services/Logistics/Map/GeoJsonFactory.php
app/Services/Logistics/Map/GisReleaseMetadataService.php
app/Services/Logistics/Map/MapConfigurationService.php
app/Services/Logistics/Map/MatrixRoutePreviewService.php
resources/js/Components/Logistics/LogisticsMap.vue
resources/js/Components/Logistics/MapTab.vue
resources/js/Components/Logistics/MatrixRoutePreviewDialog.vue
resources/js/Components/Logistics/TripRouteMap.vue
resources/js/Components/Logistics/mapRuntime.js
resources/maps/logistics-russia-style.json
tests/Feature/Logistics/LogisticsMapTest.php
docs/logistics/MAP_RUSSIA.md
```

Изменены существующие файлы:

```text
.env.example
.github/workflows/routing.yml
app/Http/Controllers/API/Logistics/RoutingStatusController.php
app/Http/Controllers/API/Logistics/TripController.php
app/Http/Controllers/API/Logistics/TripRoutingController.php
app/Http/Resources/Logistics/LogisticsTripResource.php
app/Http/Resources/Logistics/TripRouteResource.php
app/Services/Logistics/CityDistanceMatrixService.php
app/Services/Logistics/Routing/Providers/ValhallaRoutingProvider.php
app/Services/Logistics/TripRouteService.php
config/logistics.php
docs/logistics/DEPLOYMENT.md
docs/logistics/IMPLEMENTATION_STATUS.md
docs/logistics/OPERATIONS.md
docs/logistics/README.md
package.json
package-lock.json
resources/js/Components/Logistics/DiagnosticsTab.vue
resources/js/Components/Logistics/MatrixTab.vue
resources/js/Components/Logistics/TripDialog.vue
resources/js/Components/Logistics/TripsTab.vue
resources/js/Pages/Ameise/Logistics.vue
routes/api.php
```

Полностью создан новый каталог ручного GIS-контура:

```text
infrastructure/logistics-gis/.env.example
infrastructure/logistics-gis/.gitignore
infrastructure/logistics-gis/README.md
infrastructure/logistics-gis/nginx/logistics-map.conf.example
infrastructure/logistics-gis/scripts/activate-release.sh
infrastructure/logistics-gis/scripts/build-pmtiles.sh
infrastructure/logistics-gis/scripts/build-valhalla.sh
infrastructure/logistics-gis/scripts/calculate-preflight.php
infrastructure/logistics-gis/scripts/check-pmtiles-range.sh
infrastructure/logistics-gis/scripts/common.sh
infrastructure/logistics-gis/scripts/download-russia-pbf.sh
infrastructure/logistics-gis/scripts/finalize-release.sh
infrastructure/logistics-gis/scripts/preflight.sh
infrastructure/logistics-gis/scripts/rollback-release.sh
infrastructure/logistics-gis/scripts/smoke-test-legacy-current.sh
infrastructure/logistics-gis/scripts/smoke-test-release.sh
infrastructure/logistics-gis/scripts/validate-map-assets.php
infrastructure/logistics-gis/systemd/pischeprom-valhalla.service.example
infrastructure/logistics-gis/tests/map-assets-validator-test.sh
infrastructure/logistics-gis/tests/preflight-calculator-test.sh
```

### GIS release pipeline

Добавлен ручной native Linux pipeline в `infrastructure/logistics-gis`:

1. `preflight.sh` проверяет Linux/CPU/cores/load, RAM/swap, top processes,
   диск/inode/filesystem/storage, существующие PBF/graph/PMTiles, официальный
   remote PBF, инструменты, staging write и текущий Valhalla. JSON/log всегда
   фиксируют `PASS`, `WARN` (exit 2) или `FAIL` (exit 3).
2. Расчёт учитывает фактический PBF, download `.part`, staging graph,
   Planetiler scratch `10×`, PMTiles estimate, сохранённые current/previous и
   обязательные резервы приложения/БД. Ни WARN, ни FAIL не запускают следующий
   тяжёлый шаг.
3. `download-russia-pbf.sh` использует только официальный full-Russia extract,
   resume `.part`, immutable resolved URL, HTTP status/size, Geofabrik MD5 и
   manifest; ничего не активирует и не удаляет.
4. `build-valhalla.sh` повторяет preflight/checksum, берёт lock, использует уже
   установленную закреплённую Valhalla, фиксирует проверенный loopback/private
   listen, строит timezones/admins/tiles/extract в staging, работает с
   `nice`/`ionice`, сохраняет log/duration/peak RSS.
5. `build-pmtiles.sh` требует Java 21+, закреплённые Planetiler JAR + SHA-256,
   PMTiles CLI и checksum-manifest локальных glyph/sprite assets; использует тот
   же PBF, хранит scratch отдельно от публикуемого release и проверяет PMTiles
   archive.
6. `finalize-release.sh` проверяет SHA-256 graph extract, PMTiles и локальных
   map assets, принимает только согласованную пару одного PBF и гоняет
   на loopback отдельный временный Valhalla: `/status`, пять региональных,
   длинный, truck и 3×3 matrix smoke с geometry/time/distance.
7. `activate-release.sh` атомарно хранит `previous`, переключает `current`,
   перезапускает фактически настроенный существующий runtime, повторяет smoke,
   проверяет PMTiles Range/206 и только затем помечает старые автоматические
   значения stale существующей командой. Ошибка возвращает прежний symlink.
8. `rollback-release.sh` доступен даже при нездоровом current и восстанавливает
   согласованную previous-пару; для первого legacy rollback без прежнего
   PMTiles выполняется только явно помеченный legacy route/matrix smoke
   Санкт-Петербург → Москва, восстанавливается routing, а карта честно
   переходит на резервный фон; широкого удаления нет.

Тяжёлая сборка удалена из `.github/workflows/routing.yml`: workflow теперь
выполняет только syntax/calculator/boundary checks. Стандартный deploy её не
запускает.

## Изменения БД

Изменений БД и новых миграций нет. Имеющейся схемы достаточно:

- route geometry/history уже существует;
- stop snapshots/order уже существуют;
- координаты городов и контрагентов уже существуют;
- preview матрицы является ограниченным runtime cache, а не новой массовой
  таблицей геометрий.

## Фактический read-only preflight этой сессии

Запуск `preflight.sh --mode full --json` на доступной рабочей машине завершён
`FAIL` (exit 3), поэтому скачивание и обе сборки не запускались. Это Darwin
x86_64, а не production Linux; скрипт намеренно не выдаёт локальные метрики за
server evidence:

- CPU/RAM/swap/disk/inode production: не измерены;
- staging `/srv/pischeprom-gis`: отсутствует/не writable локально;
- локальная Java: 14, тогда как Planetiler требует 21+;
- native Valhalla/PMTiles/build tools: отсутствуют;
- текущий Valhalla: health не пройден;
- результат: `FAIL`.

Удалённые read-only метаданные официального Geofabrik на момент последней
проверки `2026-08-02T15:22:44Z`:

- alias: `https://download.geofabrik.de/russia-latest.osm.pbf`;
- immutable resolved URL: `https://download.geofabrik.de/russia-260801.osm.pbf`;
- размер: `4 134 132 440` bytes;
- OSM data timestamp из официального индекса: `2026-08-01T20:21:21Z`;
- Last-Modified: `Sat, 01 Aug 2026 22:38:57 GMT`;
- опубликованный MD5: `eaefeb62007ed1dc9e0a180dd3717d86`;
- рассчитанный минимальный full-operation disk threshold с текущими default
  коэффициентами и 20 GiB app reserve: `89 688 021 740` bytes;
- рассчитанный RAM threshold с 2 GiB app reserve: `6 442 450 944` bytes.

Это metadata probe, а не скачанный/проверенный локальный PBF. Значения
`russia-latest` меняются, поэтому production preflight обязан получить их заново.

## Проверки реализации

- релевантный backend suite: `42 tests`, `341 assertions`, `1 skipped`;
- полный regression suite: `241 tests`, `1512 assertions`, `5 skipped` при
  `memory_limit=512M`; пропуски включают только opt-in/live интеграции;
- PHP lint и Pint для затронутых файлов, `git diff --check` и регистрация шести
  новых API endpoint-ов прошли;
- client и SSR production build прошли на Node.js `22.14.0`; сборка сообщает
  только о давности локальной Browserslist-базы и крупных существующих chunks;
- все shell scripts прошли `bash -n`, preflight calculator — PASS/WARN/FAIL
  fixtures, валидатор ассетов — complete-manifest fixture;
- MapLibre style JSON, workflow YAML, exact npm lock versions и запрет тяжёлой
  GIS/Docker-работы в новом CI-контуре проверены;
- `npm audit` сообщил о 9 проблемах в общем дереве зависимостей проекта
  (`2 low`, `3 moderate`, `4 high`, `0 critical`); автоматический потенциально
  ломающий `audit fix` не выполнялся.

Локальная MySQL из рабочего `.env` не содержит таблиц логистики, live Valhalla
недоступен, а production host не был предоставлен. Поэтому локальные тесты не
подменяют обязательные production preflight, smoke, Range и browser-приёмку.

## Production runbook

Production-команды и layout подробно описаны в
`infrastructure/logistics-gis/README.md`. Короткая последовательность:

Перед первой активацией оператор обязан зарегистрировать фактически работающий
старый graph как `GIS_BASE_DIR/current`, адаптировать существующий runtime к
этому пути и проверить restart/health. Код намеренно не угадывает host-specific
Docker/systemd unit или каталоги по устаревшей документации; без rollback target
активация завершается до переключения. Минимальный legacy manifest и правило
`GIS_CURRENT_OSM_DATA_VERSION` приведены в полном runbook; без известной старой
версии OSM активация блокируется до изменения symlink.

```bash
set -a
. /etc/pischeprom-gis.env
set +a

infrastructure/logistics-gis/scripts/preflight.sh --mode full --json
# Продолжать только при PASS / exit 0.
infrastructure/logistics-gis/scripts/download-russia-pbf.sh
infrastructure/logistics-gis/scripts/build-valhalla.sh \
  /srv/pischeprom-gis/sources/russia-YYYYMMDD.osm.pbf
infrastructure/logistics-gis/scripts/build-pmtiles.sh \
  /srv/pischeprom-gis/sources/russia-YYYYMMDD.osm.pbf
infrastructure/logistics-gis/scripts/finalize-release.sh russia-YYYYMMDD
sudo nginx -t
infrastructure/logistics-gis/scripts/activate-release.sh russia-YYYYMMDD
```

Health и rollback:

```bash
curl --fail --silent http://127.0.0.1:8002/status
php artisan logistics:routing-health --json
infrastructure/logistics-gis/scripts/check-pmtiles-range.sh \
  https://PRODUCTION_HOST/maps/logistics/russia.pmtiles
infrastructure/logistics-gis/scripts/rollback-release.sh
```

Laravel env после проверенной активации (без секретов):

```dotenv
LOGISTICS_MAP_ENABLED=true
LOGISTICS_MAP_STYLE_URL=/api/logistics/map/style
LOGISTICS_MAP_PMTILES_URL=/maps/logistics/russia.pmtiles
LOGISTICS_MAP_GLYPHS_URL=/maps/logistics/fonts/{fontstack}/{range}.pbf
LOGISTICS_MAP_SPRITE_URL=/maps/logistics/sprites/basic
LOGISTICS_GIS_RELEASE_MANIFEST=/srv/pischeprom-gis/current/release-manifest.json
LOGISTICS_GIS_PREFLIGHT_STATUS=/srv/pischeprom-gis/state/last-preflight.json
LOGISTICS_GIS_RANGE_STATUS=/srv/pischeprom-gis/state/last-range-check.json
LOGISTICS_GIS_ACTIVATION_STATUS=/srv/pischeprom-gis/state/last-activation.json
LOGISTICS_GIS_PRODUCTION_SMOKE_STATUS=/srv/pischeprom-gis/state/last-production-smoke.json
```

PHP должен видеть указанные manifest/state JSON read-only (через существующую
service group либо точные read-only bind mounts, если приложение работает в
контейнере). Давать web-процессу доступ ко всему `sources/staging/graph/logs`
для этого не требуется.

После изменения env: `php artisan config:cache` и штатный restart PHP/queue
процессов проекта. `LOGISTICS_OSM_DATA_VERSION` остаётся legacy fallback;
активный verified manifest является источником версии для новых расчётов.

## Что не выполнялось на production

SSH/sudo-доступа к production в этой сессии не было. Поэтому не выполнены и не
заявляются выполненными:

- production CPU/RAM/swap/load/disk/inode preflight;
- скачивание 4.13 GB PBF;
- native install/provisioning Valhalla, Java, Planetiler, PMTiles/assets;
- staging graph и PMTiles build, их duration/peak RSS/paths/sizes;
- staging/production routing smoke;
- Nginx HEAD/Range 206 на production;
- переключение current/previous и rollback drill;
- browser screenshots и регрессия посторонних production-страниц.

Старый рабочий release не затрагивался и не удалялся. До PASS и успешных smoke
карта остаётся с `LOGISTICS_MAP_ENABLED=false`: прикладные точки могут работать
на резервном фоне, но интерфейс явно сообщает, что production PMTiles не активен.

## Официальные технические источники

- Geofabrik full-Russia extract: <https://download.geofabrik.de/russia.html>
- Valhalla graph build: <https://valhalla.github.io/valhalla/start/building/>
- Planetiler requirements: <https://github.com/onthegomap/planetiler>
- MapLibre + PMTiles: <https://maplibre.org/maplibre-gl-js/docs/examples/pmtiles/>
- PMTiles concepts/Range: <https://docs.protomaps.com/pmtiles/>
- OpenMapTiles: <https://openmaptiles.org/>
- OpenStreetMap attribution: <https://www.openstreetmap.org/copyright>
