<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <style>
        @media only screen and (max-width: 600px) {
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
        <table class="header-table" width="100%" cellpadding="0" cellspacing="0" style="width: 100%;">
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
        <p>Компания <strong>ПИЩЕПРОМ-СЕРВЕР</strong> предлагает к закупке:</p>
        <table>
            <tr>
                <th>Товар</th>
                <th>Цена</th>
            </tr>
            @foreach($details['products'] as $product)
                <tr class="data">
                    <td><strong>{{$product->rus}}</strong></td>
                    <td style="vertical-align: top;">{{$product->price}}</td>
                </tr>
            @endforeach

        </table>
    </div>
<div>
    {{print_r($details['products'])}}
</div>
    <div class="footer">
        Пищепром-Сервер<br />
        <span class="phone">+7-905-753-26-48</span>
        <div>
            Запросы в ТГ:<br />
            <span>pischepromserver_bot</span>
        </div>
    </div>
</div>
</body>
</html>
