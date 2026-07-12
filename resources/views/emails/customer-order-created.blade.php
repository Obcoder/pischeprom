<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Новый заказ {{ $order->number }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #2d201d;">
<h1 style="font-size: 22px; margin-bottom: 8px;">Новый заказ {{ $order->number }}</h1>

<p>
    Клиент: <strong>{{ $order->customer_name }}</strong><br>
    Email: <strong>{{ $order->customer_email ?: 'не указан' }}</strong><br>
    Телефон: <strong>{{ $order->customer_phone ?: 'не указан' }}</strong>
</p>

<p>
    Адрес доставки: <strong>{{ $order->delivery_address ?: 'не указан' }}</strong><br>
    Удобное время: <strong>{{ $order->preferred_delivery_time ?: 'не указано' }}</strong>
</p>

<p>
    Общая сумма:
    <strong>
        @if($order->total_amount > 0)
            {{ number_format($order->total_amount, 2, ',', ' ') }} {{ $order->currency_code === 'RUB' ? '₽' : $order->currency_code }}
        @else
            по запросу
        @endif
    </strong><br>
    Общий вес:
    <strong>
        @if($order->total_weight > 0)
            {{ number_format($order->total_weight, 3, ',', ' ') }} кг
        @else
            уточняется
        @endif
    </strong>
</p>

<table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%; border-color: #ddd;">
    <thead>
    <tr>
        <th align="left">Товар</th>
        <th align="right">Кол-во</th>
        <th align="right">Цена</th>
        <th align="right">Сумма</th>
        <th align="right">Вес</th>
    </tr>
    </thead>
    <tbody>
    @foreach($order->items as $item)
        <tr>
            <td>
                {{ $item->good_name }}
                @if($item->country_name)
                    <br><small>{{ $item->country_name }}</small>
                @endif
            </td>
            <td align="right">{{ number_format($item->quantity, 0, ',', ' ') }}</td>
            <td align="right">
                @if($item->price_gross > 0)
                    {{ number_format($item->price_gross, 2, ',', ' ') }} {{ $item->currency_code === 'RUB' ? '₽' : $item->currency_code }}
                @else
                    по запросу
                @endif
            </td>
            <td align="right">
                @if($item->line_total > 0)
                    {{ number_format($item->line_total, 2, ',', ' ') }} {{ $item->currency_code === 'RUB' ? '₽' : $item->currency_code }}
                @else
                    по запросу
                @endif
            </td>
            <td align="right">
                @if($item->line_weight > 0)
                    {{ number_format($item->line_weight, 3, ',', ' ') }} кг
                @else
                    -
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
