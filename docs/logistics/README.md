# Логистика

Модуль добавляет в существующую административную часть учёт автомобилей, рейсов с произвольным числом остановок, расходов по существующим чекам, версионируемых маршрутов и собственной направленной матрицы автомобильных расстояний.

Основная БД остаётся MySQL. Дорожный граф не загружается в MySQL: маршруты и матрицу считает отдельный self-hosted Valhalla на данных OpenStreetMap. Laravel обращается к нему только через `RoutingProviderInterface`; расстояние по прямой не используется как автомобильное.

Интерфейс доступен по `/Ameise/logistics` без авторизации, как и его API. В нём есть вкладки «Обзор», «Рейсы», «Карта», «Авто», «Матрица» и «Диагностика». Это временный режим до общей авторизации всего `/Ameise`; прежняя permission model сохранена и включается параметром `LOGISTICS_AUTHORIZATION_ENABLED=true`.

## Возможности первой версии

- server-side CRUD и архив автомобилей с нормализацией госномера и VIN;
- рейсы с безопасным номером `TR-YYYY-NNNNNN`, грузом, планом/фактом и упорядоченными остановками;
- снимки координат остановок и грузовой профиль конкретного автомобиля;
- расходы без чека и распределение существующего чека между рейсами без превышения его суммы;
- суммы по валютам и категориям, стоимость километра/килограмма/тонно-километра, расход топлива и отклонения;
- история версий маршрута рейса, `polyline6`, legs и параметры расчёта;
- направленная матрица `A → B`, отдельная от `B → A`, с состояниями `pending`, `calculated`, `manual`, `stale`, `no_route`, `failed`;
- расчёт выбранного фрагмента или полной матрицы всех готовых городов, polling прогресса, ручные значения и CSV-экспорт;
- диагностический экран Valhalla и фоновых запусков;
- интерактивная MapLibre/PMTiles-карта России, сохранённые дорожные линии,
  пронумерованные остановки, история маршрутов, preview матрицы и выключаемый
  слой существующих `entity_locations`.

## Права

| Право | Назначение |
|---|---|
| `logistics.view` | просмотр раздела и данных |
| `logistics.trips.manage` | создание, изменение и архив рейсов, расчёт маршрута |
| `logistics.vehicles.manage` | управление автомобилями |
| `logistics.expenses.manage` | управление расходами и связями с чеками |
| `logistics.matrix.manage` | routing-точки, матрица и ручные расстояния |
| `logistics.technical.view` | health и техническая диагностика |

Seeder выдаёт все права роли `admin`. Роль `manager` получает просмотр, рейсы, автомобили и расходы, но не управление матрицей и технический статус.

## HTTP/API

По умолчанию `LOGISTICS_AUTHORIZATION_ENABLED=false`, поэтому все маршруты `/api/logistics/*`, включая изменяющие, доступны без пользователя. При `LOGISTICS_AUTHORIZATION_ENABLED=true` единый middleware снова требует `auth:sanctum`, подтверждённый email и `logistics.view`, а изменяющие методы дополнительно проверяют policy/permission.

| Метод и URI | Назначение |
|---|---|
| `GET /api/logistics/dashboard` | метрики за период |
| `GET /api/logistics/references/{cities|entities|users}` | ограниченный поиск справочников |
| `GET /api/logistics/vehicles` | фильтры, сортировка, пагинация авто |
| `POST /api/logistics/vehicles` | создать авто |
| `GET/PUT/DELETE /api/logistics/vehicles/{vehicle}` | карточка, изменение, архив |
| `POST /api/logistics/vehicles/{vehicle}/restore` | восстановить авто |
| `GET /api/logistics/trips` | фильтры, сортировка, пагинация рейсов |
| `POST /api/logistics/trips` | создать рейс и остановки транзакционно |
| `GET/PUT/DELETE /api/logistics/trips/{trip}` | карточка, изменение, архив |
| `POST /api/logistics/trips/{trip}/stops/{stop}/move` | переместить остановку выше/ниже |
| `GET/POST /api/logistics/trips/{trip}/expenses` | расходы и метрики / новый расход |
| `PUT/DELETE /api/logistics/trips/{trip}/expenses/{expense}` | изменить / отвязать расход |
| `GET /api/logistics/checks` | поиск доступных чеков и остатка распределения |
| `GET /api/logistics/expense-categories` | активные категории |
| `GET /api/logistics/trips/{trip}/routes` | история маршрутов |
| `POST /api/logistics/trips/{trip}/routes/calculate` | поставить маршрут в очередь |
| `GET /api/logistics/cities` | города и их routing-настройки |
| `PUT /api/logistics/cities/{city}` | включить город, изменить/проверить точку |
| `GET /api/logistics/matrix` | выбранный ограниченный фрагмент |
| `POST /api/logistics/matrix/calculate` | поставить направленные пары в очередь |
| `PUT /api/logistics/matrix/manual` | ручная направленная пара с комментарием |
| `GET /api/logistics/matrix/export` | CSV выбранного фрагмента |
| `GET /api/logistics/routing-runs[/{run}]` | список/прогресс запусков |
| `GET /api/logistics/routing-status` | health Valhalla |
| `GET /api/logistics/map/config` | безопасная same-origin конфигурация карты |
| `GET /api/logistics/map/style` | versioned MapLibre style |
| `GET /api/logistics/map/features` | bbox/zoom слои городов, рейсов и контрагентов |
| `GET /api/logistics/trips/{trip}/map` | текущая route geometry и остановки GeoJSON |
| `GET /api/logistics/trips/{trip}/routes/{route}/map` | выбранная историческая geometry |
| `GET /api/logistics/matrix/{distance}/preview` | точечный cached route preview пары |

Список из приложения: `php artisan route:list --path=api/logistics`.

## Команды

```bash
php artisan logistics:routing-health
php artisan logistics:matrix-calculate --cities=1,2,3 --profile=truck --dry-run
php artisan logistics:matrix-calculate --cities=1,2,3 --profile=truck
php artisan logistics:matrix-calculate --all --profile=truck --dry-run
php artisan logistics:matrix-calculate --all --profile=truck
php artisan logistics:matrix-refresh-stale --profile=truck --limit=500 --dry-run
php artisan logistics:matrix-refresh-stale --profile=truck --limit=500
php artisan logistics:routing-recover-stuck --older-than=15 --dry-run
php artisan logistics:routing-mark-stale --old-osm-version=260725 --dry-run
php artisan logistics:import-city-coordinates /absolute/path/cities.csv --dry-run
```

Точка маршрутизации — это координаты на доступной автомобильной дороге или въезде в город, которые Valhalla использует вместо условного географического центра. Перед автоматическим расчётом оператор должен просмотреть координаты и подтвердить точку: это защищает от старта маршрута из реки, парка или места вне дорожного графа.

Полная матрица запускается явным `--all` или кнопкой «Полная матрица». В неё входят все `logistics_cities` с `is_matrix_enabled=true` и подтверждённой точкой маршрутизации; строятся все направленные пары. Лимит `LOGISTICS_MATRIX_MAX_CITIES_PER_REQUEST` применяется только к выбранному отображаемому фрагменту и не ограничивает полный фоновый расчёт. Ручные значения не перезаписываются.

## Документация

- [ARCHITECTURE.md](ARCHITECTURE.md) — модели, связи и потоки данных;
- [DEPLOYMENT.md](DEPLOYMENT.md) — первый и production deploy;
- [OPERATIONS.md](OPERATIONS.md) — health, расчёты, обновление OSM и troubleshooting;
- [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md) — выполненное, проверки и ограничения.
- [MAP_RUSSIA.md](MAP_RUSSIA.md) — аудит, интерактивная карта и безопасный
  full-Russia GIS release/runbook.

Результаты основаны на данных © OpenStreetMap contributors и распространяемых по ODbL. В пользовательском разделе присутствует обязательная атрибуция; условия: <https://www.openstreetmap.org/copyright>.
