<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Локальный черновик {{ $draft->number }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #111; margin: 30px; }
        .warning { border: 2px solid #b91c1c; color: #991b1b; padding: 12px; margin-bottom: 20px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #333; padding: 7px; vertical-align: top; }
        th { text-align: left; width: 28%; background: #f3f4f6; }
        h1 { font-size: 20px; }
        .footer { margin-top: 24px; font-size: 12px; color: #444; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Печать</button>
    <div class="warning">{{ $warning }}</div>
    <h1>Черновик платёжного поручения № {{ $draft->number }} от {{ $draft->document_date->format('d.m.Y') }}</h1>
    <table>
        <tr><th>Статус</th><td>{{ $draft->status->value }} — только локальный документ</td></tr>
        <tr><th>Сумма</th><td>{{ $draft->amount }} {{ $draft->currency }}</td></tr>
        <tr><th>Плательщик</th><td>{{ $draft->payer_name }}<br>ИНН {{ $draft->payer_inn }} @if($draft->payer_kpp) КПП {{ $draft->payer_kpp }} @endif</td></tr>
        <tr><th>Счёт плательщика</th><td>{{ $draft->payer_account }}</td></tr>
        <tr><th>Банк плательщика</th><td>{{ $draft->payer_bank_name }}<br>БИК {{ $draft->payer_bic }}, к/с {{ $draft->payer_corr_account }}</td></tr>
        <tr><th>Получатель</th><td>{{ $draft->recipient_name }}<br>ИНН {{ $draft->recipient_inn }} @if($draft->recipient_kpp) КПП {{ $draft->recipient_kpp }} @endif</td></tr>
        <tr><th>Счёт получателя</th><td>{{ $draft->recipient_account }}</td></tr>
        <tr><th>Банк получателя</th><td>{{ $draft->recipient_bank_name }}<br>БИК {{ $draft->recipient_bic }}, к/с {{ $draft->recipient_corr_account }}</td></tr>
        <tr><th>Назначение</th><td>{{ $draft->purpose }}</td></tr>
        <tr><th>НДС</th><td>{{ $draft->vat_type }} @if($draft->vat_rate) {{ $draft->vat_rate }}% @endif @if($draft->vat_amount) — {{ $draft->vat_amount }} {{ $draft->currency }} @endif</td></tr>
        <tr><th>Очерёдность</th><td>{{ $draft->payment_priority }}</td></tr>
        @php
            $budgetLabels = [
                'kbk' => 'КБК',
                'oktmo' => 'ОКТМО',
                'payment_basis' => 'Основание платежа',
                'tax_period' => 'Налоговый период',
                'document_number' => 'Номер документа',
                'document_date' => 'Дата документа',
                'uin' => 'УИН',
            ];
            $budgetFields = array_filter(
                (array) $draft->budget_fields,
                static fn ($value) => $value !== null && $value !== ''
            );
        @endphp
        @if($budgetFields !== [])
            <tr>
                <th>Бюджетные поля</th>
                <td>
                    @foreach($budgetFields as $key => $value)
                        <div>{{ $budgetLabels[$key] ?? $key }}: {{ $value }}</div>
                    @endforeach
                </td>
            </tr>
        @endif
        @if($draft->purchase_id)
            <tr><th>Закупка</th><td>#{{ $draft->purchase_id }} (локальная связь)</td></tr>
        @endif
    </table>
    <div class="footer">
        Создано: {{ $draft->created_at?->format('d.m.Y H:i') }}.
        Документ не содержит электронной подписи и не подтверждает списание средств.
    </div>
</body>
</html>
