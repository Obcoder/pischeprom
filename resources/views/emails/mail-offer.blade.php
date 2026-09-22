<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Предложение · ПИЩЕПРОМ-СЕРВЕР</title>
    <style>
        @media only screen and (max-width: 480px) {
            .offer-product-image-cell, .offer-product-name-cell { display: block !important; width: 100% !important; }
            .offer-product-image-cell { padding: 0 0 14px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;color:#172033;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;background-color:#f1f5f9;">
    <tr>
        <td align="center" style="padding:20px 12px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:680px;border-collapse:collapse;table-layout:fixed;">
                @if($body !== '')
                    <tr>
                        <td style="padding:24px;background-color:#ffffff;border-radius:16px;font-size:15px;line-height:1.65;word-break:break-word;">{!! nl2br(e($body)) !!}</td>
                    </tr>
                    <tr><td height="16" style="height:16px;font-size:0;line-height:0;">&nbsp;</td></tr>
                @endif
                <tr>
                    <td style="padding:24px;background-color:#172033;border-radius:16px 16px 0 0;">
                        <p style="margin:0;color:#ffffff;font-size:11px;line-height:1.5;font-weight:bold;letter-spacing:2px;">ПИЩЕПРОМ-СЕРВЕР</p>
                        <h1 style="margin:12px 0 0;color:#ffffff;font-size:26px;line-height:1.2;font-weight:bold;">{{ $items !== [] ? 'Коммерческое предложение' : 'Доставка' }}</h1>
                        @if($items !== [])
                            <p style="margin:10px 0 0;color:#cbd5e1;font-size:13px;line-height:1.5;">Товары и условия поставки</p>
                        @endif
                    </td>
                </tr>
                <tr><td height="4" style="height:4px;background-color:#991b3b;font-size:0;line-height:0;">&nbsp;</td></tr>
                @foreach($items as $item)
                    <tr>
                        <td style="padding:24px;background-color:#ffffff;border-bottom:1px solid #e2e8f0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;">
                                <tr>
                                    @if($item['image_url'])
                                        <td class="offer-product-image-cell" width="112" valign="top" style="width:112px;padding:0 18px 16px 0;">
                                            <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" width="112" style="display:block;width:112px;max-width:100%;height:auto;border:0;border-radius:10px;">
                                        </td>
                                    @endif
                                    <td class="offer-product-name-cell" valign="top" style="padding:0 0 16px;word-break:break-word;">
                                        <p style="margin:0 0 7px;color:#991b3b;font-size:10px;line-height:1.5;font-weight:bold;letter-spacing:1.5px;">ТОВАР {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</p>
                                        <h2 style="margin:0;color:#172033;font-size:23px;line-height:1.3;font-weight:bold;">{{ $item['name'] }}</h2>
                                        @if($item['package_label'])
                                            <p style="margin:9px 0 0;color:#64748b;font-size:12px;line-height:1.5;">Упаковка · {{ $item['package_label'] }}</p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            @if($item['description'] !== '')
                                <p style="margin:0 0 18px;color:#475569;font-size:14px;line-height:1.65;word-break:break-word;">{!! nl2br(e($item['description'])) !!}</p>
                            @endif
                            @if($item['specifications'] !== [])
                                <table width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;margin-bottom:18px;table-layout:fixed;">
                                    <caption style="padding:0 0 8px;text-align:left;color:#64748b;font-size:10px;line-height:1.5;font-weight:bold;letter-spacing:1px;">ХАРАКТЕРИСТИКИ</caption>
                                    @foreach($item['specifications'] as $specification)
                                        <tr>
                                            <th width="44%" scope="row" valign="top" style="width:44%;padding:8px 12px 8px 0;border-bottom:1px solid #edf1f5;color:#64748b;font-size:12px;line-height:1.5;font-weight:normal;text-align:left;word-break:break-word;">{{ $specification['label'] }}</th>
                                            <td valign="top" style="padding:8px 0;border-bottom:1px solid #edf1f5;color:#172033;font-size:12px;line-height:1.5;word-break:break-word;">{{ $specification['value'] }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:separate;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">
                                <tr>
                                    <td style="padding:16px;">
                                        <p style="margin:0;color:#172033;font-size:25px;line-height:1.25;font-weight:bold;">{{ $item['price_label'] }}@if($item['has_price'] && $item['price_unit_label'] !== '')<span style="font-size:13px;font-weight:normal;color:#64748b;"> / {{ $item['price_unit_label'] }}</span>@endif</p>
                                        @if($item['includes_vat'])
                                            <p style="margin:5px 0 0;color:#64748b;font-size:11px;line-height:1.5;">С НДС</p>
                                        @endif
                                        @if($item['quantity_label'] !== null)
                                            <p style="margin:12px 0 0;color:#475569;font-size:12px;line-height:1.7;">Количество: <strong style="color:#172033;">{{ $item['quantity_label'] }} {{ $item['price_unit_label'] }}</strong>@if($item['total_label'])<br>Сумма: <strong style="color:#991b3b;">{{ $item['total_label'] }}</strong>@endif</p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            @if($item['url'])
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="border-collapse:separate;margin-top:16px;">
                                    <tr>
                                        <td align="center" bgcolor="#991b3b" style="background-color:#991b3b;border-radius:8px;mso-padding-alt:12px 18px;">
                                            <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:12px 18px;color:#ffffff;font-size:12px;line-height:1.4;font-weight:bold;text-decoration:none;">Подробнее о товаре&nbsp; →</a>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @if($logistics !== null)
                    <tr>
                        <td style="padding:24px;background-color:#ffffff;">
                            <p style="margin:0 0 14px;color:#991b3b;font-size:10px;line-height:1.5;letter-spacing:1.5px;font-weight:bold;">ЛОГИСТИКА</p>
                            <h2 style="margin:0 0 20px;color:#172033;font-size:23px;line-height:1.3;">Маршрут и варианты доставки</h2>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:separate;background-color:#f1f5f9;border-radius:12px;table-layout:fixed;">
                                <tr>
                                    <td valign="top" style="padding:18px 8px 18px 16px;word-break:break-word;">
                                        <p style="margin:0 0 6px;color:#991b3b;font-size:11px;line-height:1.5;font-weight:bold;">● &nbsp;ОТКУДА</p>
                                        <p style="margin:0;color:#172033;font-size:17px;line-height:1.35;font-weight:bold;">{{ $logistics['origin'] }}</p>
                                    </td>
                                    <td width="30" align="center" style="width:30px;color:#94a3b8;font-size:24px;line-height:1;">→</td>
                                    <td valign="top" style="padding:18px 16px 18px 8px;word-break:break-word;">
                                        <p style="margin:0 0 6px;color:#991b3b;font-size:11px;line-height:1.5;font-weight:bold;">● &nbsp;КУДА</p>
                                        <p style="margin:0;color:#172033;font-size:17px;line-height:1.35;font-weight:bold;">{{ $logistics['destination'] }}</p>
                                    </td>
                                </tr>
                            </table>
                            @foreach($logistics['options'] as $option)
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:separate;margin-top:12px;border:1px solid #e2e8f0;border-radius:12px;">
                                    <tr>
                                        <td style="padding:16px;word-break:break-word;">
                                            <p style="margin:0 0 8px;color:#64748b;font-size:10px;line-height:1.5;font-weight:bold;letter-spacing:1px;">ВАРИАНТ {{ $loop->iteration }}</p>
                                            <h3 style="margin:0;color:#172033;font-size:16px;line-height:1.4;">{{ $option['name'] }}</h3>
                                            <p style="margin:10px 0 0;color:#991b3b;font-size:21px;line-height:1.3;font-weight:bold;">{{ $option['price_label'] }}</p>
                                            @if($option['duration'] !== '')
                                                <p style="margin:7px 0 0;color:#475569;font-size:13px;line-height:1.5;">Срок: <strong>{{ $option['duration'] }}</strong></p>
                                            @endif
                                            @if($option['note'] !== '')
                                                <p style="margin:10px 0 0;color:#64748b;font-size:12px;line-height:1.6;">{!! nl2br(e($option['note'])) !!}</p>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            @endforeach
                            @if($logistics['note'] !== '')
                                <p style="margin:14px 0 0;padding:0 0 0 12px;border-left:3px solid #cbd5e1;color:#64748b;font-size:12px;line-height:1.6;word-break:break-word;">{!! nl2br(e($logistics['note'])) !!}</p>
                            @endif
                        </td>
                    </tr>
                @endif
                <tr><td height="12" style="height:12px;background-color:#ffffff;border-radius:0 0 16px 16px;font-size:0;line-height:0;">&nbsp;</td></tr>
                @if($quotedBody !== '')
                    <tr>
                        <td style="padding:24px 8px 8px;">
                            <p style="margin:0 0 10px;color:#94a3b8;font-size:10px;line-height:1.5;letter-spacing:1px;">ПРЕДЫДУЩЕЕ ПИСЬМО</p>
                            <div style="padding-left:14px;border-left:2px solid #cbd5e1;color:#64748b;font-size:12px;line-height:1.65;word-break:break-word;">{!! nl2br(e($quotedBody)) !!}</div>
                        </td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>
</body>
</html>
