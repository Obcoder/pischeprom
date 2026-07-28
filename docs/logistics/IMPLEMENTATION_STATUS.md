# Статус реализации логистики

Обновлено: 2026-07-28.

## Выполнено

- Проведён аудит PHP/Laravel/Vue/Vuetify/Inertia, схем `cities/entities/checks/users`, SoftDeletes, Spatie permissions (`crm` guard), Redis/systemd/deploy и существующего GIS.
- Существующие `City`, `Entity`, `Check`, `User` и GIS расширены без дублирующих справочников и без изменения основной MySQL архитектуры.
- Добавлены строковые enums, четыре additive-миграции, девять моделей/связей, factories и идемпотентный seeder девяти категорий расходов.
- Реализованы CRUD авто/рейсов, транзакционные остановки, архивирование, валидация дат/одометра/ёмкости и policy завершённого рейса.
- Реализована сущность распределения чека, физический delete guard чека, decimal snapshot, мультивалютные метрики и отображение рейсов в карточке чека.
- Реализованы Form Requests, API Resources, policies, шесть прав и 31 защищённый API route.
- Реализованы routing interface/DTO/typed exceptions, Valhalla provider, fake provider, профиль грузовика, polyline6 и request hashes.
- Реализованы версионные маршруты рейса, направленная матрица, manual/stale/no_route/failed, cache locks, unique queue jobs, batching и polling runs.
- Добавлены пять Artisan-команд: health, выбранная матрица, refresh stale/expired, mark stale и CSV import.
- Добавлен отдельный раздел Vue/Vuetify с пятью вкладками, server-side таблицами, формами, validation/errors/loading, CSV и OSM attribution.
- Добавлена воспроизводимая Valhalla-инфраструктура: pinned image 3.6.3, loopback/private network, immutable two-region download с checksum, staging build, route/matrix smoke, atomic activate и rollback.
- Добавлен отдельный `redis-routing` connection, systemd worker example, интеграция worker/seeder/routes в production deploy и logistics checks в CI.
- Созданы все пять обязательных документов.
- По последующему решению владельца добавлен явный полный расчёт всех включённых и проверенных городов (`--all` и UI) без лимита выбранного фрагмента.
- С `/Ameise/logistics` снят отдельный middleware авторизации и ссылка постоянно отображается в Ameise; защищённые API и permission model сохранены до общей авторизации всего раздела.

## Проверено фактически

- Четыре миграции успешно применялись к отдельной временной SQLite test database; рабочая MySQL не изменялась.
- Targeted backend suite: `29 passed, 1 skipped, 181 assertions`; пропущен только opt-in smoke с живой Valhalla.
- Полный regression suite: `229 tests, 1352 assertions, 5 skipped` через `php -d memory_limit=512M vendor/bin/phpunit --colors=never`.
- Все 121 затронутых PHP-файла прошли `vendor/bin/pint --test`; общий legacy-код проекта вне задачи по-прежнему содержит 163 style issue и не переформатировался массово.
- Production client + SSR build успешно выполнен на Node.js 22.18.0; остались только предупреждения о старой базе Browserslist и размере существующих общих chunks.
- Все Valhalla shell scripts прошли `bash -n`.
- `docker compose ... config --quiet` успешно проверен с example env и staging volume.
- `composer validate --no-check-publish --strict`, `git diff HEAD --check`, регистрация 31 API route и scheduler прошли успешно.

Живой OSM/PBF build и opt-in вызов Valhalla не выполнялись в этой сессии: они требуют загрузки региональных данных и запущенного routing-контейнера. Рабочая MySQL и фактический `.env` не изменялись.

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

## Перед production enable

- Выполнить backup/restore drill MySQL и additive migrations.
- Собрать реальный граф из обоих PBF, зафиксировать ресурсы/время и получить положительные route + matrix smoke.
- Настроить server-side env, private connectivity и синхронизировать `LOGISTICS_OSM_DATA_VERSION`.
- Установить/запустить `pischeprom-routing-worker`, проверить Redis retry_after и scheduler.
- Выдать права ролям, проверить реальные routing-точки и выполнить приёмочный рейс Санкт-Петербург → Москва.
