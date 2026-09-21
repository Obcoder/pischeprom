<!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"><title>{{ $inquiry->kindLabel() }}</title></head>
<body style="margin:0;background:#f4f5ef;color:#233026;font:16px/1.6 Arial,sans-serif">
<div style="max-width:680px;margin:32px auto;padding:32px;background:white;border-radius:20px">
    <p style="font-size:12px;letter-spacing:2px;color:#68796b">ПИЩЕПРОМ-СЕРВЕР · ЗАЯВКА С САЙТА</p>
    <h1 style="font-size:26px;line-height:1.2">{{ $inquiry->kindLabel() }}</h1>
    <p><strong>{{ $inquiry->number }}</strong> · {{ $inquiry->created_at->format('d.m.Y H:i') }}</p>
    <h2 style="font-size:20px"><a href="{{ $inquiry->good_url }}" style="color:#356d38">{{ $inquiry->good_name }}</a></h2>
    <table style="width:100%;border-collapse:collapse;text-align:left" cellpadding="8">
        <tr><th>Количество</th><td>{{ $inquiry->quantity }} упак.</td></tr>
        @if($inquiry->package_weight)
            <tr><th>Вес упаковки / общий вес</th><td>{{ $inquiry->package_weight }} кг / {{ $inquiry->quantity * $inquiry->package_weight }} кг</td></tr>
        @endif
        <tr><th>Цена на сайте</th><td>{{ $inquiry->listed_price !== null ? number_format($inquiry->listed_price, 2, ',', ' ').' '.$inquiry->currency_code.' / '.($inquiry->price_unit === 'kg' ? 'кг' : 'упак.') : 'Требует согласования' }}</td></tr>
        @if($inquiry->kind === 'bargain')
            <tr style="background:#f0f6e7"><th>Предложенная цена</th><td><strong>{{ number_format($inquiry->proposed_price, 2, ',', ' ') }} {{ $inquiry->currency_code }} / {{ $inquiry->price_unit === 'kg' ? 'кг' : 'упак.' }}</strong></td></tr>
            <tr><th>Предлагаемая сумма</th><td>{{ number_format($inquiry->proposed_price * $inquiry->quantity * ($inquiry->package_weight ?? 1), 2, ',', ' ') }} {{ $inquiry->currency_code }}</td></tr>
            <tr><th>Сценарий</th><td>{{ $inquiry->scenarioLabel() }}</td></tr>
        @endif
        @if($inquiry->order)
            <tr><th>Заказ в CRM</th><td><a href="{{ route('Ameise.orders.show', $inquiry->order) }}">{{ $inquiry->order->number }}</a></td></tr>
        @endif
        <tr><th>Заказчик</th><td>{{ $inquiry->customer_name }}</td></tr>
        <tr><th>Email</th><td><a href="mailto:{{ $inquiry->customer_email }}">{{ $inquiry->customer_email }}</a></td></tr>
        <tr><th>Телефон</th><td>{{ $inquiry->customer_phone ?: 'Не указан' }}</td></tr>
        <tr><th>Компания</th><td>{{ $inquiry->company ?: 'Не указана' }}</td></tr>
        <tr><th>Город</th><td>{{ $inquiry->delivery_city ?: 'Уточнить' }}</td></tr>
        <tr><th>Адрес доставки</th><td>{{ $inquiry->delivery_address ?: 'Уточнить' }}</td></tr>
        <tr><th>Как ответить</th><td>{{ $inquiry->preferred_contact === 'max' ? 'MAX: '.$inquiry->max_contact : 'По email' }}</td></tr>
    </table>
    @if($inquiry->comment)
        <h3>Комментарий заказчика</h3>
        <p style="white-space:pre-wrap">{{ $inquiry->comment }}</p>
    @endif
    <p style="padding:16px;background:#f4f5ef;border-radius:10px">Для ответа клиенту используйте «Ответить» на это письмо. Цена, наличие и доставка требуют подтверждения менеджером. Контактные данные указаны посетителем и не проверены.</p>
    <p style="font-size:12px;color:#738077">Согласие на обработку персональных данных: {{ $inquiry->consent_at->format('d.m.Y H:i:s') }}.</p>
</div>
</body>
</html>
