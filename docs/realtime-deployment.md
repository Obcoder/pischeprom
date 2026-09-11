# Обновление складов и продаж через WebSocket

Production использует Laravel Reverb на том же домене, что и приложение.
Браузер подключается к `wss://<APP_URL host>/realtime/app/<public key>`.
Nginx завершает TLS и передаёт только клиентские WebSocket-подключения
на `127.0.0.1:8085`. API публикации Reverb `/apps` доступен приложению
через loopback и не публикуется в этом Nginx location.

Изменения отправляются через выделенную очередь `realtime` после успешного
сохранения транзакции. Worker этой очереди работает отдельно от обработчиков
почты, банковских операций и прайс-листов. Pinia получает уведомление и загружает
актуальные данные через авторизованные HTTP endpoints. Проверок состояния БД
по таймеру из браузера нет. Database queue worker проверяет свою внутреннюю
очередь с `--sleep=1`; это одна серверная очередь, а не запросы от каждой вкладки.

WebSocket-канал закрытый, подписка требует авторизации. Payload содержит только
темы изменений и UUID события, без сумм, остатков и персональных данных. Доступ
к актуальным значениям заново проверяется обычными API. Близкие события
объединяются за 150 мс в одно обновление; скрытая вкладка догружает данные при
возвращении к ней. Незавершённый ввод в формах сохраняется.

События создают Eloquent observers и прикладной код. Произвольный SQL `UPDATE`
или mass update, обходящий observers, сам по себе не создаёт уведомление.
Такие интеграции должны отправлять `CommerceDataChanged` после commit своей
транзакции. Это не универсальный мониторинг всех изменений в БД.

Если постановка уведомления в очередь не удалась, бизнес-операция сохраняется,
а сбой записывается как `commerce_realtime_unavailable`. Ошибка публикации в
Reverb повторяется до пяти попыток, после чего регистрируется ошибка задания.
Остатки и суммы сохраняются в БД независимо от доступности WebSocket.
Переподключение, возврат фокуса или ручное обновление страницы позволяют
загрузить актуальные значения после пропущенного уведомления.

## Установка при деплое

Основной GitHub Actions workflow вызывает `scripts/deploy-production.sh`.
Дополнительные GitHub secrets и отдельная frontend-сборка с production-ключами
не нужны: публичные параметры подключения приходят из Laravel во время работы.

При деплое выполняются следующие действия:

Перед включением maintenance выполняется отдельный preflight: новый PHP parser
временно извлекается из выбранного commit, проверяет текущий HTTPS vhost без
записи конфигураций, наличие утилит и non-interactive sudo. Все внешние команды
этой проверки имеют timeout. Если preflight не проходит, текущий сайт продолжает
работать на прежнем коде. Nginx dump остаётся в закрытом временном каталоге и
удаляется после проверки.

1. Существующие Reverb и realtime worker останавливаются до смены кода.
2. После установки Composer-зависимостей запускается
   `scripts/provision-production-realtime.sh` под общей блокировкой деплоя.
3. Provisioner проверяет полный `nginx -T` и находит ровно один активный HTTPS
   vhost с точным `server_name` из `APP_URL` и `root=<TARGET_DIR>/public`.
   В этот блок добавляется include `/etc/nginx/snippets/pischeprom-realtime.conf`.
   Остальные vhost не изменяются. При ошибке `nginx -t` или reload предыдущие
   конфигурации сайта и snippet восстанавливаются.
4. На VPS генерируются `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`.
   При следующих деплоях они сохраняются. Некорректный существующий ключ приводит
   к остановке деплоя, чтобы случайно не сменить ключ работающего приложения.
   `.env` обновляется атомарно с сохранением владельца и закрытием чтения others.
   `.env`, секрет и полный Nginx dump не попадают в GitHub Actions logs/artifacts.
5. Устанавливаются systemd units `pischeprom-reverb.service` и
   `pischeprom-realtime-worker.service` из `deploy/systemd`.
   Они работают от владельца приложения и группы `www-data`; root им не нужен.
6. После миграций, config cache и нормализации прав запускается Reverb.
   `scripts/check-production-realtime.php` проверяет TLS с проверкой сертификата,
   HTTP 101, ответ `pusher:connection_established`, подписанную внутреннюю
   публикацию в отдельный технический канал и доступность таблицы очереди.
   Затем запускается realtime worker и завершается обычный деплой.

Production `.env` должен содержать `APP_URL=https://<hostname>` без подкаталога
и нестандартного порта. Provisioner задаёт следующие значения:

```dotenv
REALTIME_ENABLED=true
REALTIME_QUEUE_CONNECTION=database
REALTIME_QUEUE=realtime
REALTIME_WS_PORT=443
REALTIME_WS_SCHEME=https
REALTIME_WS_PATH=/realtime
BROADCAST_CONNECTION=reverb
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8085
REVERB_HOST=127.0.0.1
REVERB_PORT=8085
REVERB_SCHEME=http
REVERB_SCALING_ENABLED=false
```

`REALTIME_WS_HOST` и `REVERB_ALLOWED_ORIGINS` устанавливаются в hostname `APP_URL`.
Для production должны быть доступны `nginx`, `systemd-analyze`, `systemctl`,
PHP CLI, существующая database queue и непросроченный сертификат домена.
TLS smoke подключается к Nginx на `127.0.0.1:443`, сохраняя SNI и проверку
сертификата для hostname приложения. Типичный `listen 443 ssl` подходит.

SSH-пользователю нужны существующие права deploy и non-interactive sudo для
Nginx (`nginx -T`, `nginx -t`, reload), установки/восстановления его конфигураций,
запуска PHP planner, проверки/установки systemd units и управления двумя сервисами.
Provisioner использует `sudo -n`: недостающие права немедленно завершают деплой
ошибкой, без ожидания ввода пароля. Произвольные нестандартные конфигурации
Nginx не переписываются: отсутствующий/неоднозначный HTTPS vhost, другой
document root или ранее определённый `/realtime` требуют адаптации конфигурации
на VPS. После смены кода действует обычное правило деплоя: при ошибке приложение
остаётся в maintenance до устранения причины.

## Проверка и восстановление

Команды выполняются на VPS из каталога приложения:

```bash
sudo systemctl is-active pischeprom-reverb pischeprom-realtime-worker
php scripts/check-production-realtime.php "$PWD"
sudo journalctl -u pischeprom-reverb -u pischeprom-realtime-worker --since '10 minutes ago'
```

В браузере в Network → WS должно быть соединение `/realtime/app/...` со статусом
101. Проверка сценария: открыть «Продажи» и «Склады» в разных вкладках, провести
продажу и убедиться, что остатки изменились без перезагрузки страницы.

После устранения сетевого сбоя Reverb/Echo восстанавливает соединение, а клиент
заново загружает актуальные данные. Уведомления служат сигналом перечитать БД;
клиент не рассчитывает остатки повторным вычитанием событий. Это защищает
от повторной доставки и от событий, пропущенных во время перезапуска сервера.

При ручной смене кода/конфигурации необходимо перезапускать оба долгоживущих
процесса. Обычный GitHub Actions deploy делает это автоматически.

Документация: [Laravel Reverb](https://laravel.com/docs/12.x/reverb),
[Laravel queues](https://laravel.com/docs/12.x/queues).
