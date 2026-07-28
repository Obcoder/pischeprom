# Эксплуатация Valhalla и матрицы

## Ежедневные проверки

```bash
php artisan logistics:routing-health
sudo systemctl is-active pischeprom-routing-worker
sudo systemctl --no-pager --full status pischeprom-routing-worker
cd infrastructure/valhalla
docker compose --env-file .env -f compose.yml ps
```

Техническая вкладка `/Ameise/logistics` показывает provider/engine/OSM version, latency, количество активных городов, состояния матрицы, города без координат и последние routing runs. Endpoint health требует `logistics.technical.view` и не раскрывает внутренний URL.

## Тестовая пара и рейс

Прямой smoke приватного Valhalla с host:

```bash
curl --fail --silent --show-error \
  -H 'Content-Type: application/json' \
  -d '{"locations":[{"lat":59.9343,"lon":30.3351},{"lat":55.7558,"lon":37.6173}],"costing":"truck","units":"kilometers"}' \
  http://127.0.0.1:8002/route

curl --fail --silent --show-error \
  -H 'Content-Type: application/json' \
  -d '{"sources":[{"lat":59.9343,"lon":30.3351}],"targets":[{"lat":55.7558,"lon":37.6173}],"costing":"truck","units":"kilometers","verbose":false}' \
  http://127.0.0.1:8002/sources_to_targets
```

Через Laravel сначала включите и проверьте routing-точки двух городов, затем:

```bash
php artisan logistics:matrix-calculate --cities=ID_SPB,ID_MOSCOW --profile=truck --dry-run
php artisan logistics:matrix-calculate --cities=ID_SPB,ID_MOSCOW --profile=truck
```

Полный расчёт всех включённых и проверенных логистических городов:

```bash
php artisan logistics:matrix-calculate --all --profile=truck --dry-run
php artisan logistics:matrix-calculate --all --profile=truck
```

`--dry-run` обязательно показывает число городов и верхнюю границу направленных пар до постановки. Полный режим не ограничен лимитом UI-фрагмента, выполняется через routing queue и никогда не перезаписывает ручные пары. В UI перед запуском показывается отдельное подтверждение с объёмом `N × (N − 1)`.

Для рейса вызовите из UI «Рассчитать маршрут» или аутентифицированный `POST /api/logistics/trips/{trip}/routes/calculate` с JSON `{"force":false}`. Ответ `202` содержит UUID run; polling выполняется через `GET /api/logistics/routing-runs/{uuid}`. Успех проверяется по `status=completed`, текущей версии маршрута и положительным `planned_distance_m`/`planned_duration_s`.

## Routing-точки городов

Координаты из `cities` можно скопировать при первом включении, но точку требуется явно проверить. Массовый публичный Nominatim не используется.

CSV для безопасного импорта:

```csv
city_id;routing_latitude;routing_longitude;source_reference
1;59.9343000;30.3351000;internal-survey-2026-07
2;55.7558000;37.6173000;internal-survey-2026-07
```

```bash
php artisan logistics:import-city-coordinates /absolute/path/logistics-cities.csv --dry-run
php artisan logistics:import-city-coordinates /absolute/path/logistics-cities.csv
```

Команда валидирует весь файл до записи и импортирует его атомарно. Изменение точки снимает verification и переводит все её не ручные пары в `stale`; после проверки координат запустите refresh.

## Правила cache/stale

- route hash: последовательность координат + профиль/габариты авто + provider + версия OSM;
- matrix pair hash: текущие точки + generic profile + provider + версия OSM;
- рассчитанная матрица истекает через `LOGISTICS_MATRIX_STALE_DAYS`;
- scheduler переводит истёкшие `calculated` в `stale`, но не создаёт большой расчёт сам;
- изменение routing-точки инвалидирует связанные автоматические пары, но сохраняет `manual`;
- изменение автомобиля/остановок делает текущий маршрут рейса stale;
- обновление OSM требует явной пометки старой версии и смены env;
- manual-значение заменяется только другим явным manual-действием;
- `no_route` можно повторить через UI «пересчитать выбранные» или CLI `matrix-calculate --include-no-route`.

Команды:

```bash
# Только отчёт
php artisan logistics:routing-mark-stale --old-osm-version=260725 --dry-run
php artisan logistics:matrix-refresh-stale --profile=truck --limit=500 --dry-run

# Пометить старую версию и поставить уже существующие stale/expired пары в очередь
php artisan logistics:routing-mark-stale --old-osm-version=260725
php artisan logistics:matrix-refresh-stale --profile=truck --limit=500

# Ограниченно повторить также failed
php artisan logistics:matrix-refresh-stale --profile=truck --limit=500 --include-failed
```

`--cities=1,2,3` у refresh ограничивает обе стороны пары выбранным набором. `--limit` принимает 1–10000 и не создаёт дополнительные cross-pairs.

## Обновление OSM-графа

Выберите одну доступную immutable дату для обоих Geofabrik extracts. Не смешивайте даты.

```bash
cd infrastructure/valhalla
./scripts/update-graph.sh YYMMDD
```

`update-graph.sh` последовательно выполняет download → staging build → route/matrix smoke → atomic activation. После успеха:

1. сохраните `YYMMDD` как `LOGISTICS_OSM_DATA_VERSION` в server-side Laravel `.env`;
2. выполните `php artisan config:cache`;
3. перезапустите routing worker;
4. выполните `logistics:routing-health`;
5. сначала dry-run, затем `routing-mark-stale --old-osm-version=OLD`;
6. пересчитывайте stale пары ограниченными порциями.

Размер и длительность каждой сборки находятся в `data/graphs/YYMMDD/build-metadata.json`. Старый граф не удаляйте, пока новая версия не прошла business smoke.

## Откат графа

```bash
cd infrastructure/valhalla
./scripts/rollback-graph.sh
```

После успешного smoke отката верните `LOGISTICS_OSM_DATA_VERSION` к имени активного каталога, пересоберите Laravel config cache и перезапустите worker. Не удаляйте строки новой версии: пометьте их stale после проверки, чтобы сохранить аудит.

## Наблюдаемость очереди

```bash
sudo journalctl -u pischeprom-routing-worker --since '1 hour ago' --no-pager
php artisan queue:failed
php artisan schedule:list | grep logistics
```

Безопасный retry конкретного failed job выполняйте стандартной командой Laravel только после устранения причины. Не запускайте одновременно несколько ручных расчётов одной и той же пары: unique jobs/cache locks защищают от дубля, но не заменяют контроль нагрузки.

## Troubleshooting

| Симптом | Проверка | Действие |
|---|---|---|
| health `unavailable` | `curl 127.0.0.1:8002/status`, `docker compose ps/logs` | проверить active symlink, volume, память и loopback URL |
| jobs стоят `queued` | systemd status/journal, Redis | запустить worker именно `redis-routing --queue=routing` |
| job выполняется повторно | config cache и `queue.connections.redis-routing.retry_after` | установить значение больше `120`, перезапустить worker |
| `no_route` | проверенные точки, покрытие двух PBF, truck limits | исправить точку/профиль или задать документированное manual-значение |
| `failed`/timeout | latency, память Valhalla, container logs | устранить ресурсную/сетевую причину и limited refresh `--include-failed` |
| город не считается | `is_matrix_enabled`, обе координаты, verification | проверить точку администратором |
| неверная версия в UI | `LOGISTICS_OSM_DATA_VERSION`, config cache, worker env | синхронизировать env с активным каталогом и restart |
| build не активирован | `.staging`, `build-metadata.json`, smoke output | не переключать symlink; исправить и собрать новый точный snapshot |

Не помещайте секреты, PBF и generated tiles в Git. Valhalla остаётся на loopback/private network; при отдельном host доступ ограничивается private firewall.

Данные: © OpenStreetMap contributors, ODbL — <https://www.openstreetmap.org/copyright>. Источники выгрузок: <https://download.geofabrik.de/russia.html>.
