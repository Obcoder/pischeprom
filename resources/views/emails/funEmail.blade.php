<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <style>
        @media only screen and (max-width: 900px) {
            .header-table td {
                display: block;
                width: 100% !important;
                text-align: center !important;
            }

            .header-table img {
                margin: 0 auto 10px auto;
            }
        }
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }

        .header {
            background-color: #B71C1C;
            color: #FFE082;
            padding: 10px;
        }

        .header img {
            max-width: 100px;
            margin-bottom: 10px;
            border-radius: 80%;
        }

        .header h1 {
            margin: 0;
            font-size: 20px;
        }

        .content {
            padding: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        tr.data:hover{
            background-color: #FFE082;
        }

        th {
            background-color: #B71C1C;
            color: #FFE082;
            padding: 10px;
            text-align: left;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        .footer {
            background-color: #eeeeee;
            text-align: center;
            font-size: 14px;
            color: #555;
            padding: 10px 20px;
        }

        .phone {
            font-weight: bold;
            color: #0d47a1;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <table class="header-table" style="width: 100%;">
                <tr>
                    <td style="width: 30%; text-align: left; vertical-align: middle;">
                        <img src="https://storage.yandexcloud.net/cold-reserve/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg"
                             alt="Логотип компании ПИЩЕПРОМ-СЕРВЕР">
                    </td>
                    <td style="width: 70%; text-align: right; vertical-align: middle;">
                        <h1 style="margin: 0; font-size: 18px;">ПИЩЕПРОМ-СЕРВЕР</h1>
                        <p style="margin: 4px 0 0 0; font-size: 12px;">
                            ПИЩЕПРОМ-СЕРВЕР: пищевое сырьё, пищевые ингредиенты и добавки, стабильно, каталог пищевых технологий.
                            Создаем логистическую сеть для снижения транспортных расходов при доставке товаров.
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="content">
            <p><strong>Коммерческое предложение</strong></p>
            <p><strong>ПИЩЕПРОМ-СЕРВЕР:</strong> предлагает к закупке:
                <br>
                <div>Цены (руб) включают НДС</div>
            </p>
            <table>
                <tr>
                    <th>Товар</th>
                    <th>Цена</th>
                </tr>
                @foreach($details['products'] as $product)
                    <tr class="data">
                        <td><strong>{{$product['rus']}}</strong></td>
                        <td style="vertical-align: top;">{{$product['price']}}</td>
    {{--                    @json($product)--}}
                    </tr>
                @endforeach

            </table>
        </div>

        <div class="footer">
            <!-- FULL-WIDTH FOOTER (email-safe) -->
            <table role="presentation" style="width:100%; margin:0; padding:0; background:#0f172a;">
                <tr>
                    <td align="center" style="padding:24px 12px;">
                        <!-- Inner wrapper also 100% (full-width email) -->
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                               style="width:100%; max-width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="padding:0 0 14px 0; border-bottom:1px solid rgba(255,255,255,0.12);">
                                    <div style="font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:22px; color:#ffffff; font-weight:700;">
                                        Пищепром-Сервер
                                    </div>
                                    <div style="font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:18px; color:rgba(255,255,255,0.75); margin-top:4px;">
                                        Поддержка и заявки
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:4px 0 0 0;">
                                    <!-- CONTACT ROW -->
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                           style="width:100%; border-collapse:collapse;">
                                        <tr>
                                            <!-- Left: contacts -->
                                            <td valign="top" style="padding:0 0 12px 0;">
                                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                                    <tr>
                                                        <td style="font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#ffffff; padding:0 0 6px 0;">
                                                            📞 <a href="tel:+79650160001" style="color:#ffffff; text-decoration:none;">+7 (965) 016-0001</a>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#ffffff; padding:0 0 6px 0;">
                                                            ✉️ <a href="mailto:office@180022.ru" style="color:#ffffff; text-decoration:none;">office@180022.ru</a>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#ffffff;">
                                                            💬 Telegram: <a href="https://t.me/pischepromserver_bot" style="color:#93c5fd; text-decoration:none;">@pischepromserver_bot</a>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                            <td>
                                                <a href="https://vk.com/market-231868854?screen=group" target="_blank"
                                                   style="display:inline-block; text-decoration:none;">
                                                    <!-- VK logo as inline SVG (email-safe in most clients; fallback text below) -->
                                                    <span style="display:inline-block; vertical-align:middle;">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 64 64" style="display:block;">
                                                                      <rect x="0" y="0" width="64" height="64" rx="14" fill="#0077FF"/>
                                                                      <path d="M34.7 44.7c-13.3 0-20.9-9.2-21.2-24.6h6.8c.2 11.4 5.2 16.2 9.2 17.2V20.1h6.4v9.8c3.9-.4 8-4.8 9.4-9.8h6.4c-1.1 6.7-5.6 12.1-8.9 14.4 3.3 1.8 8.4 6.5 10 10.2h-7.1c-1.7-5.2-5.9-9.2-9.8-9.7v9.7h-1.2z" fill="#ffffff"/>
                                                                    </svg>
                                                                </span>
                                                    <!-- Fallback text (for clients that strip SVG) -->
                                                    <span style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:16px; color:#93c5fd; text-decoration:none; display:none;">
                                                                    VK
                                                                </span>
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td>
                        <!-- CATEGORIES -->
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                               style="width:100%; border-collapse:collapse; margin-top:10px;">
                            <tr>
                                <td style="font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:18px; color:rgba(255,255,255,0.75); padding:0 0 8px 0;">
                                    Категории:
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <!-- Simple “tag” grid -->
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                        <tr>
                                            <!-- Tag 1 -->
                                            <td style="padding:0 8px 8px 0;">
                                                <a href="#" style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:14px; color:#ffffff; text-decoration:none; background:rgba(255,255,255,0.10); border:1px solid rgba(255,255,255,0.14); border-radius:999px; padding:7px 10px; display:inline-block;">
                                                    Рыба
                                                </a>
                                            </td>
                                            <!-- Tag 2 -->
                                            <td style="padding:0 8px 8px 0;">
                                                <a href="#" style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:14px; color:#ffffff; text-decoration:none; background:rgba(255,255,255,0.10); border:1px solid rgba(255,255,255,0.14); border-radius:999px; padding:7px 10px; display:inline-block;">
                                                    Напитки
                                                </a>
                                            </td>
                                            <!-- Tag 3 -->
                                            <td style="padding:0 8px 8px 0;">
                                                <a href="#" style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:14px; color:#ffffff; text-decoration:none; background:rgba(255,255,255,0.10); border:1px solid rgba(255,255,255,0.14); border-radius:999px; padding:7px 10px; display:inline-block;">
                                                    Соусы и специи
                                                </a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <!-- Tag 4 -->
                                            <td style="padding:0 8px 0 0;">
                                                <a href="#" style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:14px; color:#ffffff; text-decoration:none; background:rgba(255,255,255,0.10); border:1px solid rgba(255,255,255,0.14); border-radius:999px; padding:7px 10px; display:inline-block;">
                                                    Крупы и бакалея
                                                </a>
                                            </td>
                                            <!-- Tag 5 -->
                                            <td style="padding:0 8px 0 0;">
                                                <a href="#" style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:14px; color:#ffffff; text-decoration:none; background:rgba(255,255,255,0.10); border:1px solid rgba(255,255,255,0.14); border-radius:999px; padding:7px 10px; display:inline-block;">
                                                    Заморозка
                                                </a>
                                            </td>
                                            <!-- Tag 6 -->
                                            <td style="padding:0;">
                                                <a href="#" style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:14px; color:#ffffff; text-decoration:none; background:rgba(255,255,255,0.10); border:1px solid rgba(255,255,255,0.14); border-radius:999px; padding:7px 10px; display:inline-block;">
                                                    Другое
                                                </a>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Small note -->
                                    <div style="font-family:Arial, Helvetica, sans-serif; font-size:11px; line-height:16px; color:rgba(255,255,255,0.55); margin-top:10px;">
                                        Если вы не запрашивали это письмо — просто проигнорируйте.
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
