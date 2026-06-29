# GIS-модуль CRM MVP

Первый MVP добавляет GIS-слой поверх существующей CRM-сущности `entities`. Новая таблица контрагентов не создаётся.

## Env

Добавить в `.env`:

```dotenv
GIS_DEFAULT_PROVIDER=2gis
GIS_REQUEST_TIMEOUT=10
GIS_DEFAULT_LAT=55.751244
GIS_DEFAULT_LON=37.618423
GIS_DEFAULT_ZOOM=5

TWOGIS_API_KEY=
TWOGIS_GEOCODE_URL=https://catalog.api.2gis.com/3.0/items/geocode
TWOGIS_MAP_SCRIPT_URL=https://mapgl.2gis.com/api/js/v1

YANDEX_MAPS_API_KEY=
YANDEX_GEOCODE_URL=https://geocode-maps.yandex.ru/1.x/
YANDEX_MAP_SCRIPT_URL=https://api-maps.yandex.ru/2.1/
```

Ключи, которые попадают во frontend SDK карт, должны быть ограничены по домену/HTTP Referer в кабинетах 2ГИС и Яндекса.

Ограничение Referer не настраивается в `.env`: это настройка безопасности в личных кабинетах провайдеров. Для production нужно разрешить только домены проекта и запретить wildcard-доступ.

## Страницы

- `/Ameise/gis` — админ-панель GIS: статус провайдеров, покрытие entities координатами, черновики маршрутов и быстрые действия.
- `/Ameise/gis/2gis` — карта CRM на 2ГИС.
- `/Ameise/gis/yandex` — карта CRM на Яндекс Картах на тех же backend endpoints и данных.
- `/Ameise/gis/entities/no-location` — entities без координат, геокодирование и ручной ввод точки.

Legacy-прямые URL `/gis/2gis`, `/gis/yandex`, `/gis/entities/no-location` оставлены для совместимости.

## API

- `GET /api/gis/entities` — entities с координатами. Фильтры: `north`, `south`, `east`, `west`, `search`, `type`, `status`, `city`, `manager_id`, `limit`.
- `GET /api/gis/entities/no-location` — entities без координат.
- `GET /api/gis/entities/{id}/location` — координаты одной entity.
- `PUT /api/gis/entities/{id}/location` — ручное сохранение координат, всегда `source=manual`, `is_confirmed=true`.
- `POST /api/gis/entities/{id}/geocode` — геокодирование через `provider=2gis|yandex`, без автоматической перезаписи координат.
- `POST /api/gis/routes/preview` — нормализация точек и черновой route preview.
- `POST /api/gis/routes/distance-matrix` — матрица расстояний для `origins` и `destinations`.
- `POST /api/gis/routes/drafts` — сохранение черновика маршрута.
- `GET /api/gis/routes/drafts/{id}` — просмотр черновика.
- `DELETE /api/gis/routes/drafts/{id}` — удаление черновика.

## Таблицы

- `entity_locations` — координаты существующих `entities`.
- `gis_route_drafts` — черновики маршрутов CRM-планирования.
- `gis_route_points` — точки черновиков маршрутов.

Spatial `POINT SRID 4326` добавлен отдельной миграцией `2026_06_29_000004_add_geo_point_to_gis_tables.php` после проверки локальной MySQL `8.0.35`.

- `entity_locations.geo_point` — nullable `POINT SRID 4326`, backfill из `lon/lat`.
- `gis_route_points.geo_point` — nullable `POINT SRID 4326`, backfill из `lon/lat`.

Новые ручные координаты и точки черновиков маршрутов синхронизируют `geo_point`, если колонка уже есть в БД.

## Использование entities

Для карты нормализуются реальные поля проекта:

- `name` -> название.
- `legal_address` или первый связанный `building.address` -> адрес.
- `classification.name` -> `type`.
- primary `entity_user.status` -> `status`.
- `cities.name` -> `city`.
- `users.id` -> `manager_id`.

## Провайдеры

Backend зависит от `GisProviderInterface`, а не от конкретного 2ГИС/Яндекса. Выбор провайдера идёт через `GisService`.

Геокодирование подключено через HTTP API провайдеров. Route preview в MVP возвращает явно помеченный `stub`: линия по прямым сегментам и оценка расстояния по haversine. Это позволяет сохранять CRM-черновики маршрутов до подключения коммерческого routing API 2ГИС/Яндекса.

`distanceMatrix` реализован как локальная оценка по haversine для всех пар `origins` x `destinations`. В ответе `summary.status=local_estimate`; коммерческие matrix API 2ГИС/Яндекса можно подключить позже внутри конкретных провайдеров без изменения бизнес-кода.

## Ограничения MVP

Не реализовано:

- задания водителю;
- мобильный кабинет водителя;
- GPS-трекинг;
- контроль перемещения;
- статусы доставки;
- полноценная VRP/TSP-оптимизация;
- полигоны зон доставки;
- миграция на PostgreSQL/PostGIS.
