# Статус реализации логистики

Обновлено: 2026-07-28.

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
- Добавлен отдельный раздел Vue/Vuetify с пятью вкладками, server-side таблицами, формами, validation/errors/loading, CSV и OSM attribution.
- Добавлена воспроизводимая Valhalla-инфраструктура: pinned image 3.6.3, loopback/private network, immutable two-region download с checksum, staging build, route/matrix smoke, atomic activate и rollback.
- Добавлен отдельный ручной GitHub Actions workflow: ресурсоёмкая сборка графа выполняется на runner, а VPS получает только проверенный архив; workflow также устанавливает runtime, PHP Redis и routing worker и выполняет production API smoke.
- Добавлен отдельный `redis-routing` connection, systemd worker example, интеграция worker/seeder/routes в production deploy и logistics checks в CI.
- Созданы все пять обязательных документов.
- По последующему решению владельца добавлен явный полный расчёт всех включённых и проверенных городов (`--all` и UI) без лимита выбранного фрагмента.
- С `/Ameise/logistics` и `/api/logistics/*` временно снята обязательная авторизация; permission model сохранена и возвращается единым `LOGISTICS_AUTHORIZATION_ENABLED=true` после внедрения общей авторизации всего раздела.

## Проверено фактически

- Четыре миграции успешно применялись к отдельной временной SQLite test database; рабочая MySQL не изменялась.
- Targeted backend suite: `35 passed, 1 skipped, 243 assertions`; пропущен только opt-in smoke с живой Valhalla.
- Полный regression suite: `235 tests, 1414 assertions, 5 skipped` через `php -d memory_limit=512M vendor/bin/phpunit --colors=never`.
- Все затронутые PHP-файлы прошли `vendor/bin/pint --test`; общий legacy-код проекта вне задачи по-прежнему содержит style issues и не переформатировался массово.
- Production client + SSR build успешно выполнен на Node.js 22.18.0; остались только предупреждения о старой базе Browserslist и размере существующих общих chunks.
- Все Valhalla shell scripts прошли `bash -n`.
- `docker compose ... config --quiet` успешно проверен с example env и staging volume.
- `composer validate --no-check-publish --strict`, `git diff HEAD --check`, регистрация 31 API route и scheduler прошли успешно.
- Production CI после исправления: `147 passed, 2 skipped, 992 assertions`, PHP style, client build и SSR build; [deploy run 30403630659](https://github.com/Obcoder/pischeprom/actions/runs/30403630659).
- На GitHub-hosted runner собран и проверен граф из checksum-verified PBF Центрального и Северо-Западного федеральных округов, snapshot `260725`; на VPS запущен Valhalla `3.6.3`, доступный Laravel только через `127.0.0.1:8002`.
- Production health возвращает `healthy=true`, OSM `260725`; Redis routing worker активен. Provisioning, route/matrix smoke и production API E2E прошли в [routing run 30403764857](https://github.com/Obcoder/pischeprom/actions/runs/30403764857).
- Полный auto-расчёт `8ff7407e-a2ff-45f8-86c4-89e3407bdedb` завершил `2/2` направленных пар без ошибок: Санкт-Петербург → Воронеж `1 255,502 км`, Воронеж → Санкт-Петербург `1 326,100 км`.
- Production `.env` синхронизирован с Redis queue, loopback Valhalla и версиями engine/OSM; перед изменением workflow сохранил закрытую резервную копию.

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
- полноценный tile server/карта;
- отдельная полная матрица для каждого автомобиля;
- миграция MySQL на PostgreSQL/PostGIS.

## Оставшиеся эксплуатационные задачи

- Выполнить backup/restore drill MySQL и additive migrations.
- Выдать права ролям, проверить реальные routing-точки и выполнить приёмочный рейс Санкт-Петербург → Москва.
