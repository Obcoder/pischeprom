# Интеграция Avito API в Ameise

## Результат аудита исходного прототипа

До этой реализации в проекте существовали только `GET /api/avito/user`, один сервис получения client-credentials token и демонстрационная Vue-страница. Прототип:

- формировал неверный URL `https://api.avito.ru/token/token` при настройках по умолчанию;
- записывал `client_id`, `client_secret`, access token и ответы API в application log;
- ожидал посторонний bearer token из `localStorage` браузера;
- не имел OAuth Authorization Code, refresh token, scopes, webhook inbox, аудита запросов, миграций, тестов и ссылки в header;
- не представлял объявления и не интегрировал остальные функции Avito.

Старый сервис и маршрут удалены.

Для уже развёрнутого legacy-значения `AVITO_API_URL=https://api.avito.ru/token`
предусмотрена точечная нормализация к API origin; новые окружения должны задавать
`AVITO_API_URL=https://api.avito.ru`, как показано в `.env.example`.

## Покрытие официального API

Команда `php artisan avito:catalog-sync` загружает официальный каталог и OpenAPI 3.0 каждого раздела с `developers.avito.ru`, нормализует ссылки и схемы и фиксирует snapshot в `resources/avito/api-catalog.json`.

На 5 августа 2026 года snapshot содержит:

- 25 разделов;
- 245 уникальных функций;
- 75 GET, 158 POST, 9 PUT и 3 DELETE;
- hosts только `api.avito.ru` и `pro.autoteka.ru`;
- Client Credentials и Authorization Code со всеми объявленными scopes;
- JSON, form-urlencoded, multipart upload и бинарные ответы;
- 14 функций, помеченных Avito как deprecated.

Три grant flow на `/token` сохранены как отдельные системные функции. Пять одинаковых операций, опубликованных Avito сразу в двух разделах, дедуплицированы и содержат `also_listed_in`.

Основные источники:

- каталог: <https://developers.avito.ru/api-catalog>;
- машинный список: <https://developers.avito.ru/web/1/openapi/list>;
- авторизация: <https://developers.avito.ru/api-catalog/auth/documentation>;
- условия API: <https://www.avito.ru/legal/pro_tools/public-api>.

## Реализованный контур

- `/Ameise/avito` — отдельный раздел из иконки storefront в header Ameise;
- Excel-like реестр всех функций с поиском, фильтрами, групповым включением и ссылкой на первичную документацию;
- универсальная консоль вызова строго allowlisted operation: path/query/header параметры, JSON, multipart и binary download;
- client credentials остаются только на сервере и кэшируются короче срока действия token;
- OAuth Authorization Code с `state`, шифрованными access/refresh tokens и автоматическим refresh;
- preflight через безопасный `GET /core/v1/accounts/self`;
- журнал запросов с request UUID, HTTP status, latency, редактированными и зашифрованными payload;
- webhook inbox с shared secret (`X-Secret` из API «Работа», совместимый `X-Avito-Webhook-Secret` или query), дедупликацией и шифрованием payload;
- hourly refresh истекающих OAuth tokens и удаление журналов старше retention period;
- серверный host allowlist, запрет redirects и запрет произвольных параметров вне OpenAPI;
- изменяющие операции требуют включённого `AVITO_MUTATIONS_ENABLED` и явного подтверждения.

Отдельная авторизация страницы не добавлена: раздел следует общей модели доступа Ameise. Поэтому `AVITO_MUTATIONS_ENABLED=false` — безопасное production-значение до появления общей авторизации Ameise.

## Production-конфигурация

Минимум для своего аккаунта:

```dotenv
AVITO_ENABLED=true
AVITO_CLIENT_ID=...
AVITO_CLIENT_SECRET=...
AVITO_REDIRECT_URI=https://пищепром-сервер.рф/api/avito/oauth/callback
AVITO_MUTATIONS_ENABLED=false
AVITO_WEBHOOK_SECRET=...
```

Для OAuth сторонних аккаунтов redirect URI должен в точности совпадать с зарегистрированным в приложении Avito. Полный список параметров находится в `.env.example`.

Проверки:

```bash
php artisan avito:preflight --schema
php artisan avito:preflight --schema --live
php artisan avito:catalog-sync --check
```

`--live` безопасно читает только профиль и не меняет данные Avito.
