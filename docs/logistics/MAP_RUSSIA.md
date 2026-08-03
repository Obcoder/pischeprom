# Логистика: вся Россия и интерактивная карта

Обновлено: 2026-08-03.

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
собираемая Docker-based workflow. Production-аудит `2026-08-02` подтвердил
работающую Valhalla `3.6.3` на loopback `127.0.0.1:8002`; legacy OSM fallback
приложения — `260725`. MapLibre, PMTiles, Planetiler, glyphs/sprites и проверка
Nginx Range до дополнения отсутствовали.

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
- однократная регистрация PMTiles protocol, обязательный Vite bundled worker
  для MapLibre GL JS 6 и динамическая загрузка JS/CSS только при инициализации
  карты;
- собственный same-origin style, русский OpenMapTiles-compatible слой,
  allowlisted HTTPS Object Storage/CDN для immutable PMTiles/sprites/glyphs и
  видимая атрибуция OpenStreetMap/OpenMapTiles;
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
infrastructure/logistics-gis/scripts/publish-map-assets.sh
infrastructure/logistics-gis/scripts/rollback-release.sh
infrastructure/logistics-gis/scripts/smoke-test-legacy-current.sh
infrastructure/logistics-gis/scripts/smoke-test-release.sh
infrastructure/logistics-gis/scripts/export-application-state.sh
infrastructure/logistics-gis/scripts/install-application-state.sh
infrastructure/logistics-gis/scripts/validate-map-assets.php
infrastructure/logistics-gis/systemd/pischeprom-valhalla.service.example
infrastructure/logistics-gis/object-storage/cors.example.json
infrastructure/logistics-gis/tests/application-state-bundle-test.sh
infrastructure/logistics-gis/tests/map-assets-validator-test.sh
infrastructure/logistics-gis/tests/preflight-calculator-test.sh
infrastructure/logistics-gis/tests/private-listen-test.sh
infrastructure/logistics-gis/tests/range-cors-test.sh
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
7. `publish-map-assets.sh` возобновляемо публикует immutable PMTiles, glyphs и
   sprites в S3-compatible Object Storage/CDN, не перезаписывает существующие
   объекты и требует публичные HEAD, Range/206, CORS и SHA-256.
8. `publish-valhalla-artifacts.sh` сохраняет matching graph и manifest в
   полностью приватном immutable bucket, публикует marker последним и требует
   `403` для anonymous access.
9. `activate-map-release.sh` повторяет публичный Range/206+CORS и активирует
   только постоянную map delivery. Он не переключает, не запускает и не требует
   Valhalla.
10. `export-application-state.sh` и `install-application-state.sh` переносят на
   основной VPS только checksum-verified JSON state. Поэтому выключение
   Valhalla/GIS VPS не выключает карту.
11. Опциональный `activate-release.sh` отдельно атомарно хранит `previous`,
   переключает routing `current`, перезапускает настроенный runtime, повторяет
   route/matrix smoke и только после успеха помечает старые автоматические
   значения stale. Ошибка возвращает прежний symlink.
12. `rollback-release.sh` доступен даже при нездоровом current и восстанавливает
   согласованную previous-пару; для первого legacy rollback без прежнего
   PMTiles выполняется только явно помеченный legacy route/matrix smoke
   Санкт-Петербург → Москва, восстанавливается routing, а карта честно
   переходит на резервный фон; широкого удаления нет.

Тяжёлая сборка удалена из `.github/workflows/routing.yml`: workflow теперь
выполняет только syntax/calculator/boundary checks. Стандартный deploy её не
запускает. Явно запущенный `yandex-gis-builder.yml` оркестрирует временную VM:
`build-start` запускает detached service, `build-status` забирает ограниченный
журнал, а `release-publish` публикует immutable artifacts, переносит JSON state
на application VPS и удаляет VM только после application + Range/CORS smoke.

## Изменения БД

Изменений БД и новых миграций нет. Имеющейся схемы достаточно:

- route geometry/history уже существует;
- stop snapshots/order уже существуют;
- координаты городов и контрагентов уже существуют;
- preview матрицы является ограниченным runtime cache, а не новой массовой
  таблицей геометрий.

## Фактический production preflight и deploy

Первичный preflight основного VPS корректно вернул `FAIL`: тяжёлая full-Russia
сборка не помещалась в его RAM/диск. Поэтому сборка была перенесена на отдельную
временную VM и никогда не запускалась на application VPS или GitHub runner.

`2026-08-03` временный builder прошёл blocking preflight, pinned toolchain и
полную последовательность `download → build → smoke → publish → Range/206 →
activate`. Финальная публикация выполнена ручным
[run 30797074675](https://github.com/Obcoder/pischeprom/actions/runs/30797074675).

### Активный релиз `russia-20260802`

- источник: `russia-260802.osm.pbf`, `4 134 706 838` bytes, OSM timestamp
  `2026-08-02T20:21:48Z`, MD5 `1c57b379d8dbd18667f051e32ba00772`;
- Valhalla `3.6.3`: graph extract `7 395 596 449` bytes; staging smoke прошёл
  `/status`, Санкт-Петербург → Псков, Москва → Нижний Новгород,
  Екатеринбург → Тюмень, Новосибирск → Красноярск, Хабаровск → Владивосток,
  Москва → Новосибирск, truck route и 3×3 matrix;
- PMTiles v3, Planetiler `0.10.2`: `7 784 103 974` bytes, zoom `0…14`,
  SHA-256 `a8d8249b0bf1f2d67306472d350c9fed094e2b5a60704b2aef1796a0e8a2b110`;
- public map release опубликован в
  `https://pischeprom-gis-map-8as88kt.storage.yandexcloud.net/logistics/releases/russia-20260802/`;
- matching graph/manifest опубликованы в приватном bucket; anonymous marker
  возвращает `403`;
- application state установлен атомарно, `LOGISTICS_MAP_ENABLED=true`, а
  подтверждённый владельцем `LOGISTICS_AUTHORIZATION_ENABLED=false` сохранён;
- временная VM, auto-delete disk и отдельная security group удалены после всех
  production smoke. Постоянной платы за builder compute/disk больше нет.

### Production-приёмка

- `/api/logistics/map/config` — `200`, `enabled=true`,
  `delivery=object_storage_cdn`, `release.status=active`;
- PMTiles HEAD — `200`; `Range: bytes=0-16383` — `206`,
  `Content-Range: bytes 0-16383/7784103974`, magic `PMTiles`;
- CORS разрешает exact production origin и экспортирует Range/cache headers;
- cache policy — `public,max-age=31536000,immutable`;
- MapLibre/Vite client загружает отдельный bundled worker и после header делает
  рабочие byte-range запросы к архиву; браузерный screenshot подтвердил воду,
  границы, подписи и прикладные точки без console/network errors;
- финальный app verify/deploy —
  [run 30799057918](https://github.com/Obcoder/pischeprom/actions/runs/30799057918),
  Linux GIS validation —
  [run 30795117473](https://github.com/Obcoder/pischeprom/actions/runs/30795117473).

### Текущий режим Valhalla

Valhalla runtime намеренно не включён постоянно. Production diagnostics
возвращает `overall_status=degraded`, но раздельные состояния корректны:
`map.available=true`, `routing.available=false`. Карта, справочники и уже
сохранённые route geometries продолжают работать; недоступны только новые
route/matrix расчёты. Full-Russia graph сохранён приватно для будущего
отключаемого runtime.

## Проверки реализации

- полный regression suite: `244 tests`, `1539 assertions`, `5 skipped`;
- production CI: `156 passed`, `1117 assertions`, `2 skipped`, Pint, client и
  SSR build;
- все GIS shell scripts прошли `bash -n`, fixtures preflight/assets/
  publication/application-state/builder — успешно;
- PMTiles проверен и go-pmtiles CLI, и реальными browser Range-запросами;
- `git diff --check`, pinned versions и запрет тяжёлой работы в обычном deploy
  проверены.

## Production runbook

Повторное обновление запускается только вручную через
`.github/workflows/yandex-gis-builder.yml`:

1. `plan` — только расчёт/проверка ресурсов, без создания;
2. `apply` — временный builder с auto-delete disk и runner-scoped SSH;
3. `build-start` — detached preflight/download/build/finalize;
4. `build-status` — санитаризированный bounded status/log;
5. `release-publish` — public map + private graph, application-state handoff,
   Range/CORS/application smoke и автоматическое удаление builder;
6. `destroy` — отдельная аварийная уборка exact managed resources.

Нельзя публиковать релиз, если `status != completed`, smoke не прошёл или
manifest/checksum не совпадают. Повторный `release-publish` возобновляем и не
перезаписывает immutable объекты.

Локальная эквивалентная последовательность и layout описаны в
`infrastructure/logistics-gis/README.md`. `activate-release.sh` относится только
к отдельной активации Valhalla и не входит в обязательную map-only публикацию.

## Оставшийся опциональный шаг

Если понадобятся новые маршруты/матрицы, нужно отдельно развернуть отключаемый
Valhalla runtime из приватного graph artifact и настроить защищённый transport
от application VPS. Это не блокирует и не изменяет уже активную карту.
Billing budget alerts и отдельный CDN/custom DNS также не являются блокерами:
первый production release работает через bucket-specific HTTPS origin.

## Официальные технические источники

- Geofabrik full-Russia extract: <https://download.geofabrik.de/russia.html>
- Valhalla graph build: <https://valhalla.github.io/valhalla/start/building/>
- Planetiler requirements: <https://github.com/onthegomap/planetiler>
- MapLibre + PMTiles: <https://maplibre.org/maplibre-gl-js/docs/examples/pmtiles/>
- PMTiles concepts/Range: <https://docs.protomaps.com/pmtiles/>
- Yandex Object Storage Range API: <https://yandex.cloud/en/docs/storage/s3/api-ref/object/get>
- Yandex Object Storage pricing: <https://yandex.cloud/en/docs/storage/pricing>
- Yandex Cloud CDN large-file segmentation: <https://yandex.cloud/en/docs/cdn/concepts/slicing>
- Cloudflare R2 pricing: <https://developers.cloudflare.com/r2/pricing/>
- Cloudflare R2 CORS: <https://developers.cloudflare.com/r2/buckets/cors/>
- OpenMapTiles: <https://openmaptiles.org/>
- OpenStreetMap attribution: <https://www.openstreetmap.org/copyright>
