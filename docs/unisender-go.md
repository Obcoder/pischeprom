# Unisender Go commercial offers

Модуль `/Ameise/commercial-offers` реализует внутренний sales/admin-инструмент для массовых и персональных коммерческих предложений через Unisender Go API. Отправка выполняется через REST API `email/send.json`, не через SMTP.

## Env

```env
EMAIL_PROVIDER=unisender_go

UNISENDER_GO_ENABLED=true
UNISENDER_GO_API_BASE=https://go1.unisender.ru/en/transactional/api/v1
UNISENDER_GO_API_KEY=
UNISENDER_GO_FROM_EMAIL=com@food-server.ru
UNISENDER_GO_FROM_NAME="Pischeprom"
UNISENDER_GO_REPLY_TO=com@food-server.ru
UNISENDER_GO_TRACK_READ=true
UNISENDER_GO_TRACK_LINKS=true
UNISENDER_GO_GLOBAL_LANGUAGE=ru
UNISENDER_GO_WEBHOOK_URL=https://example.ru/webhooks/unisender-go
UNISENDER_GO_WEBHOOK_MAX_PARALLEL=10
UNISENDER_GO_WEBHOOK_DELIVERY_INFO=true

MAILINGS_BATCH_SIZE=500
MAILINGS_DAILY_LIMIT=500
MAILINGS_HOURLY_LIMIT=100
MAILINGS_STOP_ON_SPAM_RATE=0.002
MAILINGS_STOP_ON_HARD_BOUNCE_RATE=0.05
MAILINGS_REQUIRE_CONSENT_FOR_MASS=true
MAILINGS_TEST_RECIPIENT=com@food-server.ru
MAILINGS_DRY_RUN=false
```

`UNISENDER_GO_API_KEY` хранится только в env/config. Его нельзя выводить в логи, UI и exception context.

## API key

1. Откройте Unisender Go.
2. Создайте API key для transactional API.
3. Узнайте host вашего Go-инстанса в кабинете Unisender Go. Официальный SDK использует формат `https://{YOUR-HOST-NAME}/en/transactional/api/v1`, пример: `https://go1.unisender.ru/en/transactional/api/v1`.
4. Заполните `UNISENDER_GO_API_KEY` и `UNISENDER_GO_API_BASE` на production/stage.
5. Выполните `php artisan config:clear`, если Laravel config был закеширован.
6. Проверьте связь командой `php artisan mailings:unisender:test-api`.

## Домен отправителя

1. Подключите корпоративный домен отправителя в Unisender.
2. Настройте SPF, DKIM и DMARC по инструкции Unisender.
3. Не отправляйте коммерческие предложения с публичных ящиков вроде gmail/yandex/mail.ru.
4. Используйте `UNISENDER_GO_FROM_EMAIL` только с подтверждённого домена Unisender, сейчас для КП это `com@food-server.ru`.
5. Проверьте deliverability тестовой отправкой перед массовой кампанией.

## Webhook

Публичные endpoints:

```text
GET  /webhooks/unisender-go
POST /webhooks/unisender-go
```

GET возвращает `200 OK` для проверки URL. POST принимает JSON body, сохраняет raw payload в `mailing_webhook_calls`, проверяет auth и обрабатывает события.

Настройка через команду:

```bash
php artisan mailings:unisender:set-webhook --url=https://example.ru/webhooks/unisender-go
```

Конфиг webhook:

```text
status=active
event_format=json_post
delivery_info=1
single_event=0
max_parallel=10
events.email_status=delivered,opened,clicked,unsubscribed,subscribed,soft_bounced,hard_bounced,spam
events.spam_block=*
```

Webhook auth проверяется по правилу Unisender: MD5 от строкового raw body, где значение `auth` заменено на API key. API key не логируется.

## Массовая кампания

1. Создайте contacts или импортируйте CSV.
2. Убедитесь, что для массовой отправки у контактов `consent_status=confirmed`.
3. Создайте recipient set.
4. Создайте template или campaign draft.
5. Добавьте товары/категории в КП через product picker.
6. Проверьте preview, plaintext, unsubscribe link, абсолютные HTTPS URL.
7. Отправьте test email.
8. Approve.
9. Schedule или start.

Отправка идёт chunks до `min(MAILINGS_BATCH_SIZE, 500)`. Каждый batch получает `idempotence_key`. Каждый recipient получает metadata: `campaign_id`, `campaign_recipient_id`, `contact_id`, `mailing_message_id`.

## Персональное КП

1. Создайте campaign type `personal_offer`.
2. Выберите получателя или создайте контакт.
3. Добавьте товары/категории.
4. Отредактируйте тему, HTML, plaintext и цены offer items.
5. Отправьте test email.
6. При отправке без confirmed consent нужен отдельный compliance override на уровне бизнес-процесса и аудит действия.

Персональные отправки попадают в общую аналитику как campaign type `personal_offer`.

## Товары в КП

Product picker использует существующие `Good` и `Category`. При добавлении товара создаётся snapshot в `mailing_offer_items`:

```text
product_id, sku, title, thumbnail_url, price, currency, canonical_url, category, availability
```

Цена в КП редактируется в `offer_price` и не меняет основную цену товара в каталоге.

## Contacts и sets

Contacts хранятся по email, а не по Unit/Entity. Важные поля:

```text
consent_status, consent_source, consent_date, contact_source, source_url,
do_not_email, unsubscribed_at, complained_at, hard_bounced_at, soft_bounced_at
```

Массовая отправка включает только eligible recipients:

```text
consent_status=confirmed
do_not_email=false
unsubscribed_at is null
complained_at is null
hard_bounced_at is null
not in mailing_suppression_list
not duplicate in campaign
```

CSV импорт доступен через UI и команду:

```bash
php artisan mailings:import-contacts storage/app/imports/contacts.csv --set-id=1 --contact-source=expo-2026
```

Во вкладке `Recipients` есть блок `emails DB -> recipients`. Он показывает текущую CRM-таблицу `emails`, позволяет выбрать существующие адреса и синхронизировать их в `mailing_contacts`. Это не меняет исходные записи `emails`: для рассылок создаётся отдельный recipient со своим `consent_status`, suppression/отписками и историей событий. При синхронизации можно сразу указать `set id`, чтобы добавить выбранные адреса в recipient set.

## Статусы и события

Unisender события сохраняются в `mailing_events`. Если Unisender не прислал event id, модуль создаёт `event_fingerprint = sha256(provider + job_id + email + status + event_time + url + campaign_recipient_id)`. Повторный webhook не увеличивает counters.

Обработка статусов:

```text
delivered: delivered_at
opened: open_count, first_opened_at, last_opened_at, contact.last_opened_at
clicked: click_count, last_clicked_url, contact.last_clicked_at
unsubscribed: contact.do_not_email=true, local suppression, Unisender suppression
soft_bounced: soft_bounce_count, temporary suppression после 3 soft bounces
hard_bounced: do_not_email=true, permanent suppression
spam: do_not_email=true, complained suppression, проверка автоостановки
```

## Отписка

Публичные endpoints:

```text
GET  /mailings/unsubscribe/{token}
POST /mailings/unsubscribe/{token}
```

Token генерируется случайно для каждого `mailing_campaign_recipient`. При POST:

```text
recipient.status=unsubscribed
contact.unsubscribed_at заполнен
contact.do_not_email=true
mailing_suppression_list cause=unsubscribed
Unisender suppression/set.json cause=unsubscribed
```

## Suppression list

Локальная таблица `mailing_suppression_list` блокирует будущие отправки независимо от Unisender. Причины:

```text
unsubscribed
temporary_unavailable
permanent_unavailable
complained
manual_block
```

Удаление suppression должно быть доступно только администраторам с правом `sales_mailings.manage_suppression` и логируется в `mailing_audit_log`.

## Автоостановка

Кампания переводится в `paused_by_system`, если:

```text
spam rate > MAILINGS_STOP_ON_SPAM_RATE
hard bounce rate > MAILINGS_STOP_ON_HARD_BOUNCE_RATE
```

Причина записывается в `mailing_campaigns.metadata.system_pause_reason`.

## Admin page

```text
/Ameise/commercial-offers
```

Разделы:

```text
Dashboard, Campaigns, Recipients, Sets, Mark-up editor, Templates, Products, Events, Suppression, Settings
```

Интерфейс сделан как compact terminal cockpit: чёрный фон, зелёный monospace, sticky headers, dense tables, row height около 24px.

## Commands

```bash
php artisan mailings:send-due
php artisan mailings:process-queue
php artisan mailings:recalculate-stats
php artisan mailings:unisender:set-webhook
php artisan mailings:unisender:test-api
php artisan mailings:unisender:sync-template {templateId}
php artisan mailings:import-contacts {file}
php artisan mailings:cleanup-old-webhook-calls
```

## Удаление старого Mailgun

В проекте не должно быть:

```text
MAILGUN_* env
mailgun/mailgun-php dependency
Mailgun service/controller/routes/tests/docs
mailgun transport config
```

Проверка:

```bash
rg -n "Mailgun|mailgun|MAILGUN|mailgun-php|mailgun.com|api.mailgun.net|api.eu.mailgun.net" .
```

## Troubleshooting

`UNISENDER_GO_API_KEY is not configured`: заполните env и выполните `php artisan config:clear`.

`user not found` или `User with id ... not found`: Unisender не признал API key на выбранном Go host. Проверьте, что ключ взят именно из Unisender Go Transactional API, относится к нужному проекту/аккаунту, скопирован без пробелов/переносов, а `UNISENDER_GO_API_BASE` указывает на Go host вашего аккаунта в формате `https://{YOUR-HOST-NAME}/en/transactional/api/v1`, например `https://go1.unisender.ru/en/transactional/api/v1`. Если в кабинете указан другой host, используйте его, а не `go1`. Затем выполните `php artisan config:clear` на production.

`from_email must use a corporate domain`: замените отправителя на адрес корпоративного домена.

`free_tier ... external domain(s) 'gmail.com'`: на бесплатном тарифе Unisender Go отправляет только на checked emails/domains. Добавьте тестовый email или домен получателя в проверенные в кабинете Unisender, временно укажите `MAILINGS_TEST_RECIPIENT=com@food-server.ru` или смените тариф.

`No eligible recipients`: проверьте consent, suppression, unsubscribe/hard bounce/spam flags и состав set.

Webhook возвращает 403: проверьте, что Unisender отправляет JSON body без промежуточной модификации и что env API key совпадает с ключом проекта.

Opened/clicked не приходят: включите `UNISENDER_GO_TRACK_READ=true`, `UNISENDER_GO_TRACK_LINKS=true`, настройте webhook и проверьте DNS/tracking domain в Unisender.

Высокий bounce/spam: остановите кампанию, очистите базу, проверьте источник контактов и подтверждение согласия. Не используйте купленные или спарсенные базы.
