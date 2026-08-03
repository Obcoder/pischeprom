# Эксплуатация Valhalla и матрицы

> Full-Russia graph + PMTiles обновляются только явно запущенным native Linux
> pipeline из [MAP_RUSSIA.md](MAP_RUSSIA.md). Ручной workflow
> `yandex-gis-builder.yml` через GitHub Actions оркестрирует временную Yandex
> Cloud VM: `build-start` запускает detached build, `build-status` читает
> санитаризированное состояние, а защищённый `release-publish` публикует и
> активирует постоянную карту. Обычный CI/deploy приложения тяжёлые GIS-операции
> не запускает. PMTiles постоянно доступны из Object Storage/CDN; Valhalla
> остаётся отдельным отключаемым compute-сервисом. Старые команды
> `infrastructure/valhalla` относятся к сохранённому двухрегиональному runtime.

## Ежедневные проверки

```bash
php artisan logistics:routing-health
sudo systemctl is-active pischeprom-routing-worker
sudo systemctl --no-pager --full status pischeprom-routing-worker
curl --fail --silent http://PRIVATE_GIS_IP:8002/status
infrastructure/logistics-gis/scripts/check-pmtiles-range.sh \
  https://MAP_HOST/logistics/releases/russia-YYYYMMDD/russia.pmtiles
```

При выключенном Valhalla первая команда ожидаемо сообщает degraded/unavailable;
это не авария карты. Обязательная ежедневная проверка map-only режима —
`services.map.available=true` в diagnostics и успешный PMTiles Range/206.

Status самого Valhalla проверяйте через фактически аудированный runtime:
`systemctl` для native unit либо `docker compose ps` только пока production
остаётся на сохранённом legacy compose deployment.

Техническая вкладка `/Ameise/logistics` независимо показывает постоянную
публикацию карты и доступность Valhalla, а также provider/engine/OSM version,
latency, состояние матрицы, города без координат и последние routing runs.
Выключенная Valhalla даёт degraded GIS status, но не должна выключать карту.
Endpoint health требует `logistics.technical.view` и не раскрывает внутренний
URL.

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

Если постановка job была прервана недоступной очередью и остались старые `queued` runs / `pending` пары, сначала проверьте объём восстановления, затем освободите их для чистого повторного расчёта:

```bash
php artisan logistics:routing-recover-stuck --older-than=15 --dry-run
php artisan logistics:routing-recover-stuck --older-than=15
```

Команда не трогает свежие задачи и ручные расстояния. После неё явно повторите расчёт выбранного фрагмента или полной матрицы.

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

> Для новой согласованной пары Valhalla + PMTiles используйте раздел
> `Production runbook` в [MAP_RUSSIA.md](MAP_RUSSIA.md#production-runbook).
> Приведённая ниже команда оставлена только для исторического двухрегионального
> контура и не должна использоваться для выполнения full-Russia ТЗ.

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
| health `unavailable` | `curl 127.0.0.1:8002/status`, status/log фактического runtime | проверить active symlink, runtime adapter, память и loopback URL |
| jobs стоят `queued` | systemd status/journal, Redis | запустить worker именно `redis-routing --queue=routing` |
| job выполняется повторно | config cache и `queue.connections.redis-routing.retry_after` | установить значение больше `120`, перезапустить worker |
| `no_route` | проверенные точки, покрытие активного graph, truck limits | исправить точку/профиль или задать документированное manual-значение |
| `failed`/timeout | latency, память Valhalla, логи фактического runtime | устранить ресурсную/сетевую причину и limited refresh `--include-failed` |
| город не считается | `is_matrix_enabled`, обе координаты, verification | проверить точку администратором |
| неверная версия в UI | active release manifest, activation state, config cache, worker env | восстановить согласованный `current`/activation state; legacy env использовать только как fallback |
| карта работает, routing недоступен | status отдельного GIS VPS и private/VPN route | это штатный режим при выключенном compute; включить VPS перед новыми расчётами |
| пустая карта при healthy CDN | `map_publication`, allowlisted origin, browser CORS, MapLibre worker, HEAD/206 и последующие tile ranges | проверить Vite import `?worker&url`, exact origin/CORS, JSON bundle и config cache |
| build не активирован | `staging`, component/release manifests, smoke output | не переключать symlink; исправить и собрать новый точный snapshot |

Не помещайте секреты, PBF и generated tiles в Git. Valhalla остаётся на loopback/private network; при отдельном host доступ ограничивается private firewall.

Данные: © OpenStreetMap contributors, ODbL — <https://www.openstreetmap.org/copyright>. Источники выгрузок: <https://download.geofabrik.de/russia.html>.
