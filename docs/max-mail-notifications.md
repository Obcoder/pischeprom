# Уведомления о входящей почте через MAX

Модуль отправляет новые входящие письма из выбранных ящиков в явно
перечисленные MAX-чаты или пользователям. Клиентские MAX-чаты из таблицы
`max_chats` автоматически получателями не становятся.

## Настройка

Добавьте в production `.env`:

```dotenv
MAX_MAIL_NOTIFICATIONS_ENABLED=true
MAX_MAIL_NOTIFICATION_MAILBOXES=com@food-server.ru
MAX_MAIL_NOTIFICATION_FOLDERS=INBOX
MAX_MAIL_NOTIFICATION_CHAT_IDS=123456789
MAX_MAIL_NOTIFICATION_USER_IDS=
MAX_MAIL_NOTIFICATION_QUEUE=mail-notifications
MAX_MAIL_NOTIFICATION_TEXT_CHUNK_LENGTH=3400
MAX_MAIL_NOTIFICATION_SEND_INTERVAL_MS=600
MAX_MAIL_NOTIFICATION_UPLOAD_DELAY_MS=1000
MAX_MAIL_NOTIFICATION_MAX_ATTACHMENT_BYTES=52428800
MAX_MAIL_NOTIFICATION_MAX_AGE_HOURS=0

# Database queue jobs can run for up to 600 seconds.
DB_QUEUE_RETRY_AFTER=900
```

`MAX_MAIL_NOTIFICATION_CHAT_IDS` и `MAX_MAIL_NOTIFICATION_USER_IDS` принимают
списки через запятую. Достаточно заполнить один из них.
Нужный `chat_id` или `user_id` можно взять из уже сохранённой привязки на
странице `Ameise/Max`; бот должен иметь возможность писать этому получателю.
В `APP_URL` должен быть указан публичный адрес приложения — он используется
для кнопки открытия письма.
Значение `MAX_MAIL_NOTIFICATION_MAX_AGE_HOURS=0` не отбрасывает письма после
длительного простоя синхронизации; положительное значение ограничивает возраст
вновь обнаруженного письма.

После изменения окружения:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan max:mail-notifications:health --remote
```

Для первого развёртывания безопаснее оставить уведомления выключенными,
применить миграцию и установить worker. Затем включите
`MAX_MAIL_NOTIFICATIONS_ENABLED=true`, выполните `php artisan optimize:clear`
и обязательно проверьте `php artisan max:mail-notifications:health --remote`
до перезапуска worker синхронизации почты.

## Отдельный worker

Уведомления вынесены из очереди `mail-sync`, чтобы загрузка больших писем и
вложений не задерживала получение следующих писем. Пример systemd unit:

```text
deploy/systemd/pischeprom-mail-notifications-worker.service.example
```

Перед установкой проверьте пользователя, группу, `WorkingDirectory` и путь к
PHP:

```bash
sudo install -m 0644 \
  deploy/systemd/pischeprom-mail-notifications-worker.service.example \
  /etc/systemd/system/pischeprom-mail-notifications-worker.service

sudo systemctl daemon-reload
sudo systemctl enable --now pischeprom-mail-notifications-worker
sudo systemctl status pischeprom-mail-notifications-worker
```

## Доставка и повторы

- Уведомление создаётся только для нового входящего письма в настроенной папке.
- Уникальность контролируется по письму и MAX-получателю.
- Дополнительно одинаковый `Message-ID` не создаёт повторную доставку тому же
  получателю.
- Полный текст разбивается на части с учётом ограничения MAX в 4000 символов.
- Каждое вложение загружается через MAX `/uploads` и отправляется отдельно.
- После каждой части сохраняется прогресс. Retry продолжает с первой
  недоставленной части и переиспользует token уже загруженного файла.
- Job делает до пяти попыток с паузами 60, 180, 600 и 1800 секунд.
- К первому сообщению и каждому вложению прикрепляется кнопка, открывающая
  письмо в `/Ameise/Mail?mail_message_id=...`.

Если вложение больше `MAX_MAIL_NOTIFICATION_MAX_ATTACHMENT_BYTES`, вместо него
приходит предупреждение и кнопка открытия письма. Значение `0` снимает
прикладной лимит, но требует достаточного `memory_limit` PHP.

MAX не предоставляет idempotency key для `POST /messages`. Таблица прогресса
предотвращает обычные повторы синхронизации и retry. Единственный неустранимый
пограничный случай — разрыв соединения после приёма сообщения MAX, но до
получения HTTP-ответа приложением.
