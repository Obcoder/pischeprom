<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:20px 12px;background-color:#f7f4f0;color:#2d201d;font-family:Arial,Helvetica,sans-serif;line-height:1.5;">
<div style="max-width:820px;margin:0 auto;background-color:#ffffff;border:1px solid #e5dfd9;border-top:4px solid #8c3328;">
    <div style="padding:22px 20px 16px;">
        <p style="margin:0 0 8px;color:#7c6b60;font-size:11px;letter-spacing:1px;">ПИЩЕПРОМ-СЕРВЕР</p>
        <h1 style="margin:0;font-size:22px;line-height:1.3;overflow-wrap:anywhere;word-break:break-word;">{{ $title }}</h1>
        @if($deliveryDate !== null)
            <p style="margin:10px 0 0;font-size:14px;">Дата поставки: <strong>{{ $deliveryDate }}</strong></p>
        @endif
        @if($body !== '')
            <div style="margin:18px 0 0;font-size:14px;overflow-wrap:anywhere;word-break:break-word;">{!! nl2br(e($body)) !!}</div>
        @endif
    </div>

    @if($items !== [])
        <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;table-layout:auto;font-size:13px;">
            <caption style="padding:0 20px 10px;text-align:left;font-size:14px;font-weight:bold;">Состав заказа</caption>
            <thead>
            <tr style="background-color:#f3efea;color:#635249;">
                <th scope="col" align="left" style="padding:9px 12px;border-top:1px solid #e5dfd9;border-bottom:1px solid #e5dfd9;">Товар</th>
                <th scope="col" align="right" style="padding:9px 8px;border-top:1px solid #e5dfd9;border-bottom:1px solid #e5dfd9;">Кол-во</th>
                <th scope="col" align="left" style="padding:9px 8px;border-top:1px solid #e5dfd9;border-bottom:1px solid #e5dfd9;">Ед.</th>
                <th scope="col" align="left" style="padding:9px 8px;border-top:1px solid #e5dfd9;border-bottom:1px solid #e5dfd9;">Комментарий</th>
                <th scope="col" align="right" style="padding:9px 8px;border-top:1px solid #e5dfd9;border-bottom:1px solid #e5dfd9;">Цена за ед.</th>
                <th scope="col" align="right" style="padding:9px 12px;border-top:1px solid #e5dfd9;border-bottom:1px solid #e5dfd9;">Сумма</th>
            </tr>
            </thead>
            <tbody>
            @foreach($items as $item)
                <tr>
                    <td valign="top" style="padding:10px 12px;border-bottom:1px solid #eee9e3;overflow-wrap:anywhere;word-break:break-word;">{{ $item['name'] }}</td>
                    <td align="right" valign="top" style="padding:10px 8px;border-bottom:1px solid #eee9e3;font-variant-numeric:tabular-nums;">{{ $item['quantity'] }}</td>
                    <td valign="top" style="padding:10px 8px;border-bottom:1px solid #eee9e3;overflow-wrap:anywhere;word-break:break-word;">{{ $item['unit'] }}</td>
                    <td valign="top" style="padding:10px 8px;border-bottom:1px solid #eee9e3;overflow-wrap:anywhere;word-break:break-word;">{!! nl2br(e($item['note'])) !!}</td>
                    <td align="right" valign="top" style="padding:10px 8px;border-bottom:1px solid #eee9e3;font-variant-numeric:tabular-nums;">{{ $item['price'] }}</td>
                    <td align="right" valign="top" style="padding:10px 12px;border-bottom:1px solid #eee9e3;font-variant-numeric:tabular-nums;">{{ $item['total'] }}</td>
                </tr>
            @endforeach
            </tbody>
            @if($totals !== [])
                <tfoot>
                @foreach($totals as $total)
                    <tr style="background-color:#faf8f5;">
                        <th scope="row" colspan="5" align="right" style="padding:10px 8px;border-bottom:1px solid #eee9e3;">{{ $totalLabel }}</th>
                        <td align="right" style="padding:10px 12px;border-bottom:1px solid #eee9e3;font-weight:bold;font-variant-numeric:tabular-nums;">{{ $total }}</td>
                    </tr>
                @endforeach
                </tfoot>
            @endif
        </table>
    @else
        <p style="margin:0;padding:4px 20px 20px;color:#7c6b60;font-size:14px;">Позиции заказа пока не добавлены.</p>
    @endif
    <div style="height:16px;line-height:16px;">&nbsp;</div>
</div>
</body>
</html>
