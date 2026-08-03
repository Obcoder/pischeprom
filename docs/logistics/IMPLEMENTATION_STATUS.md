# Статус реализации логистики

Обновлено: 2026-08-03.

## Дополнение «вся Россия и интерактивная карта»

- Без новых миграций переиспользованы существующие trips/stops/routes/matrix,
  polyline6, stale, policies, `cities`, `logistics_cities` и `entity_locations`.
- Добавлена вкладка «Карта», карта в карточке рейса, история версий, preview
  матрицы, bbox/cluster layers и расширенная диагностика. Вложенные справочники
  Cities, Buildings, Regions и Countries перенесены из «Географии» Grossbuch в
  `/Ameise/logistics` после «Диагностики», получили иконки и сохранение активной
  вкладки в URL/localStorage. Общая оболочка имеет стабильную ширину и не
  сжимается при переходе на «Авто».
- Закреплены `maplibre-gl 6.1.0` и `pmtiles 4.4.1`; чужие публичные OSM tile
  servers не используются. Для собственной карты добавлена allowlisted HTTPS
  доставка через S3-compatible Object Storage/CDN.
- Добавлен native full-Russia release pipeline с blocking preflight,
  checksum/version locks, staging smoke, независимой активацией постоянной
  карты, опциональной paired routing activation и rollback. Он не запускается
  из Laravel/queue/обычного deploy/автоматического CI; тяжёлый контур доступен
  только через ручные защищённые actions временного Yandex builder.
- Автоматический routing workflow оставлен лёгкой статической/toolchain
  проверкой; отдельный ручной workflow управляет detached build, status,
  publication/application handoff и удалением временной VM.
- По решению владельца PMTiles, glyphs и sprites вынесены в постоянно
  доступный Object Storage/CDN, а Valhalla/GIS compute спроектирован как
  отдельный отключаемый сервис. Matching graph хранится приватно; на основном
  VPS находится только атомарный checksum-verified JSON state. Выключение
  Valhalla не выключает карту и сохранённые геометрии.
- Full-Russia релиз `russia-20260802` собран на временной Yandex VM после
  blocking preflight, прошёл Valhalla route/matrix smoke, опубликован и
  активирован. VM, auto-delete disk и отдельная security group после публикации
  удалены; точные метрики и runbook зафиксированы в
  [MAP_RUSSIA.md](MAP_RUSSIA.md).

## Выполнено

- Проведён аудит PHP/Laravel/Vue/Vuetify/Inertia, схем `cities/entities/checks/users`, SoftDeletes, Spatie permissions (`crm` guard), Redis/systemd/deploy и существующего GIS.
- Существующие `City`, `Entity`, `Check`, `User` и GIS расширены без дублирующих справочников и без изменения основной MySQL архитектуры.
- Добавлены строковые enums, четыре additive-миграции, девять моделей/связей, factories и идемпотентный seeder девяти категорий расходов.
- Реализованы CRUD авто/рейсов, транзакционные остановки, архивирование, валидация дат/одометра/ёмкости и policy завершённого рейса.
- Реализована сущность распределения чека, физический delete guard чека, decimal snapshot, мультивалютные метрики и отображение рейсов в карточке чека.
- Реализованы Form Requests, API Resources, policies, шесть прав и 31 API route с переключаемой авторизацией.
- Реализованы routing interface/DTO/typed exceptions, Valhalla provider, fake provider, профиль грузовика, polyline6 и request hashes.
- Реализованы версионные маршруты рейса, направленная матрица, manual/stale/no_route/failed, cache locks, unique queue jobs, batching и polling runs.
- Добавлены шесть Artisan-команд: health, выбранная матрица, refresh stale/expired, восстановление зависших routing jobs, mark stale и CSV import.
- Добавлен отдельный раздел Vue/Vuetify с десятью вкладками, server-side
  таблицами, формами, картой, validation/errors/loading, CSV и OSM attribution.
- Для MapLibre GL JS 6 добавлен обязательный Vite bundled worker; production
  browser smoke подтверждает реальную отрисовку PMTiles, а не только доступность
  первых байтов архива. Статический зелёный статус заменён фактическим раздельным
  состоянием карты и Valhalla, дублирующая атрибуция удалена.
- Добавлена воспроизводимая Valhalla-инфраструктура: pinned image 3.6.3, loopback/private network, immutable two-region download с checksum, staging build, route/matrix smoke, atomic activate и rollback.
- Исторический двухрегиональный workflow больше не строит граф. Для full-Russia
  добавлен отдельный ручной `yandex-gis-builder.yml`: GitHub runner не строит
  данные сам, а безопасно оркестрирует временную Linux VM.
- Добавлен отдельный `redis-routing` connection, systemd worker example, интеграция worker/seeder/routes в production deploy и logistics checks в CI.
- Созданы исходные пять документов и отдельный full-Russia runbook.
- По последующему решению владельца добавлен явный полный расчёт всех включённых и проверенных городов (`--all` и UI) без лимита выбранного фрагмента.
- С `/Ameise/logistics` и `/api/logistics/*` временно снята обязательная авторизация; permission model сохранена и возвращается единым `LOGISTICS_AUTHORIZATION_ENABLED=true` после внедрения общей авторизации всего раздела.
- Владелец проекта `2026-08-02` явно подтвердил сохранение текущего публичного
  режима `LOGISTICS_AUTHORIZATION_ENABLED=false` для production-внедрения карты.

## Проверено фактически

- Четыре миграции успешно применялись к отдельной временной SQLite test
  database; рабочая MySQL не изменялась.
- Targeted logistics suite: `44 passed`, `368 assertions`, `1 skipped`;
  пропущен только opt-in smoke с живой Valhalla.
- Полный regression suite: `244 tests`, `1539 assertions`, `5 skipped` через
  `php -d memory_limit=512M vendor/bin/phpunit --colors=never`; пропущены только
  opt-in/live интеграции.
- Production CI: `156 passed`, `1117 assertions`, `2 skipped`, Pint для всех
  logistics-файлов, client + SSR build и exact-commit deploy —
  [run 30799057918](https://github.com/Obcoder/pischeprom/actions/runs/30799057918).
- Linux toolchain/workflow validation —
  [run 30795117473](https://github.com/Obcoder/pischeprom/actions/runs/30795117473).
- Full-Russia snapshot: `russia-260802.osm.pbf`, `4 134 706 838` bytes,
  OSM timestamp `2026-08-02T20:21:48Z`, Geofabrik MD5
  `1c57b379d8dbd18667f051e32ba00772`.
- Valhalla `3.6.3`: граф `7 395 596 449` bytes; staging smoke прошёл `/status`,
  пять региональных маршрутов, Москва → Новосибирск, truck route и 3×3 matrix.
- PMTiles v3/Planetiler `0.10.2`: `7 784 103 974` bytes, SHA-256
  `a8d8249b0bf1f2d67306472d350c9fed094e2b5a60704b2aef1796a0e8a2b110`,
  zoom `0…14`.
- Финальный `release-publish` —
  [run 30797074675](https://github.com/Obcoder/pischeprom/actions/runs/30797074675):
  public map и private graph опубликованы, application state установлен,
  `LOGISTICS_AUTHORIZATION_ENABLED=false` сохранён, builder/disk/SG удалены.
- Production `/api/logistics/map/config` сообщает `enabled=true`,
  `delivery=object_storage_cdn`, `status=active`; независимые HEAD/Range/CORS
  проверки возвращают `200/206`, точный `Content-Range` и immutable cache.
- Browser smoke загрузил bundled worker, выполнил шесть PMTiles fetch-запросов
  (пять рабочих byte ranges после header), получил только `206` без ошибок и
  визуально отрисовал воду, границы, подписи и прикладные точки.
- Приватный publication marker/graph остаётся недоступен анонимно (`403`).
- Текущий штатный режим: постоянная карта доступна, Valhalla compute выключена;
  `/api/logistics/routing-status` возвращает `degraded`, `map.available=true`,
  `routing.available=false`. Сохранённые маршруты работают, новые расчёты ждут
  отдельного включения routing runtime.

## Архитектурные решения аудита

- `cities` уже имеет `latitude/longitude`; `logistics_cities` хранит управляемый snapshot routing-точки и verification.
- Legacy `checks.amount` — `double`, валюты в чеке нет и чек удаляется физически. Поэтому расход хранит decimal snapshot/RUB default, а связанный чек защищён FK `RESTRICT` и model/controller guard.
- Существующий GIS имеет provider abstractions и Haversine stubs, но не self-hosted автомобильный routing. Он сохранён; логистика имеет специализированный provider layer и никогда не подменяет автомобильное расстояние прямой.
- Полная масса авто преобразуется из kg в truck `weight` в тоннах; payload capacity остаётся отдельной бизнес-проверкой груза.
- Generic matrix имеет стабильный `vehicle_profile_hash=default`; индивидуальный hash используется только для рейсов.
- OSM-граф обновляется независимо от deploy Laravel.

## Сознательно не реализовано в MVP

- GPS/телематика, мобильное приложение и задания водителю;
- live traffic, ДТП и оперативные перекрытия;
- VRP/TSP и автоматическая перестановка остановок;
- массовый публичный Nominatim или собственный geocoder;
- отдельная полная матрица для каждого автомобиля;
- миграция MySQL на PostgreSQL/PostGIS.

## Оставшиеся эксплуатационные задачи

- Если понадобятся новые автомобильные расчёты, развернуть отдельный
  отключаемый Valhalla runtime из приватного graph artifact и настроить
  защищённый transport от application VPS; карта этого не требует.
- Выполнить backup/restore drill MySQL и additive migrations.
- Перед будущим включением `LOGISTICS_AUTHORIZATION_ENABLED=true` выдать права
  ролям и провести отдельную приёмку доступа.
