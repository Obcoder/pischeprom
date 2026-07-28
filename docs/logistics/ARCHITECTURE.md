# Архитектура модуля «Логистика»

## Границы

Laravel хранит бизнес-сущности, снимки результатов и аудит в MySQL. Valhalla хранит собственный дорожный граф и предоставляет только внутренние `/route`, `/sources_to_targets` и `/status`. Существующий GIS-контур (`entity_locations`, `gis_route_drafts`, provider-абстракции 2ГИС/Яндекс) сохранён; логистика не дублирует его и не использует его Haversine-оценки как автомобильное расстояние.

```text
Vue/Inertia admin
       │ authenticated /api/logistics
       ▼
Controllers → Form Requests / Policies → domain services → MySQL
                                             │
                                             ├─ Redis queue: routing
                                             ▼
                                RoutingProviderInterface
                                  ├─ ValhallaRoutingProvider → private Valhalla
                                  └─ FakeRoutingProvider     → tests only
```

## Доменная схема

```text
entities ──< logistics_vehicles ──< logistics_trips >── users
                   │                      │
                   │                      ├──< logistics_trip_stops >── cities
                   │                      ├──< logistics_trip_routes
                   │                      └──< logistics_trip_expenses >── checks
                   │                                      │
                   │                                      └── logistics_expense_categories
                   │
cities ──1 logistics_cities
   └──< logistics_city_distances >── cities   (направленная пара)

users ──< logistics_routing_runs
```

### Владение и удаление

- `Vehicle` и `LogisticsTrip` используют soft delete.
- Авто, встречавшееся в истории рейсов, нельзя удалить физически; FK рейса использует `RESTRICT`.
- Остановки, версии маршрута и расходы удаляются вместе с физическим удалением рейса, но обычный UI только архивирует рейс.
- FK расхода к `checks` использует `RESTRICT`; модель `Check` и API возвращают конфликт при попытке удалить распределённый чек.
- Удаление расхода или архивирование рейса никогда не удаляет чек.
- Контрагент/пользователь при удалении обнуляется там, где историческая запись остаётся понятной без него.

## Таблицы

| Таблица | Назначение и ключевые ограничения |
|---|---|
| `logistics_vehicles` | уникальные нормализованные `registration_number` и nullable `vin`, техпараметры, soft delete |
| `logistics_trips` | уникальный номер, даты, груз, plan/fact, авто, профиль, soft delete |
| `logistics_trip_stops` | уникальный `(trip_id, sequence)`, snapshot координат, `origin/waypoint/destination` |
| `logistics_expense_categories` | уникальный code, 9 категорий из идемпотентного seeder |
| `logistics_trip_expenses` | decimal snapshot суммы и валюты, optional check, категория |
| `logistics_cities` | связь 1:1 с `cities`, проверенная routing-точка и участие в матрице |
| `logistics_city_distances` | направленная пара, метры/секунды, status/provenance/expiry; unique с hash=`default` |
| `logistics_trip_routes` | неизменяемая история результатов/ошибок, одна текущая версия на уровне сервиса |
| `logistics_routing_runs` | UUID, тип/status, счётчики polling, безопасная ошибка и параметры |

Миграции:

1. `2026_07_28_000001_create_logistics_vehicles_table.php`;
2. `2026_07_28_000002_create_logistics_trips_and_expenses_tables.php`;
3. `2026_07_28_000003_create_logistics_city_matrix_tables.php`;
4. `2026_07_28_000004_create_logistics_routing_history_tables.php`.

Статусы и типы хранятся строками и приводятся PHP backed enums; MySQL `ENUM` не используется. Деньги — `decimal(15,2)`, масса/объём — decimal, расстояние и время — целые метры/секунды, координаты — `decimal(10,7)`.

## Маршрут рейса

1. Рейс и остановки блокируются/читаются по `sequence`; требуется минимум две точки.
2. Snapshot координат остановки имеет приоритет. При его отсутствии один раз копируется проверенная routing-точка `logistics_cities`.
3. `VehicleRoutingProfileFactory` передаёт Valhalla высоту, ширину, длину, полную массу в тоннах, осевую нагрузку и число осей. Грузоподъёмность не используется как полная масса.
4. Hash включает точки, профиль автомобиля, provider и `LOGISTICS_OSM_DATA_VERSION`.
5. Cache lock и unique job не допускают одновременный одинаковый расчёт.
6. Успех создаёт новую текущую версию и обновляет plan в рейсе; прежняя версия остаётся.
7. `no_route` и `failed` сохраняются отдельной аудируемой версией без прямолинейного fallback.

Изменение остановки, порядка, автомобиля или профиля помечает текущий успешный маршрут `stale` и очищает отметку актуального расчёта в рейсе. Изменение критичных габаритов авто инвалидирует незавершённые рейсы этого авто.

## Матрица

- `A → B` и `B → A` — разные строки; диагональ `0` строится только в ответе UI.
- Generic-профиль использует `vehicle_profile_hash=default`; отдельная полная матрица на каждое авто не создаётся.
- Явный full-matrix run обходит лимит отображаемого фрагмента и включает все активные проверенные `logistics_cities`; расчёт по-прежнему разбивается на небольшие source/target jobs.
- На автопересчёт допускаются только включённые города с проверенными координатами.
- Ручная строка не перезаписывается refresh и не переводится в `stale` при изменении точки.
- Координаты, provider, engine version, OSM version и request hash сохраняются вместе с результатом.
- Jobs разбивают выборку на небольшие source/target-батчи; UI получает `logistics_routing_runs` polling-ом.
- `no_route` — терминальный результат запроса, а временная недоступность provider проходит ограниченный retry/backoff и затем становится `failed`.

## Расходы и метрики

`logistics_trip_expenses` — сущность распределения, поэтому существующая схема `checks` не меняется. Блокировка строки чека сериализует параллельные распределения. Сумма в центах сравнивается с доступным остатком legacy-поля `checks.amount`; расход хранит собственный decimal snapshot.

`TripMetricsService` группирует разные валюты, а не складывает их. Производные значения возвращаются только при ненулевом знаменателе. Для cost/km используется фактический пробег, иначе плановый с явным `distance_basis=planned`.

## Безопасность

- внутренний URL Valhalla читается только из server-side config/env;
- API требует Sanctum, verified user, permission и policies;
- для завершённого рейса существенные изменения разрешены только с техническим правом;
- провайдер логирует действие, код и длительность без координат, URL и payload;
- клиент получает типизированные безопасные domain errors;
- массовый публичный Nominatim не используется.
