<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MailingCampaignRecipient;
use App\Services\CommercialOffers\UnisenderWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MailingUnsubscribeController extends Controller
{
    public function show(string $token): Response
    {
        $recipient = MailingCampaignRecipient::query()->where('unsubscribe_token', $token)->firstOrFail();

        return response($this->html($recipient, false));
    }

    public function unsubscribe(Request $request, string $token, UnisenderWebhookService $service): Response
    {
        $recipient = MailingCampaignRecipient::query()->where('unsubscribe_token', $token)->firstOrFail();
        $service->unsubscribeRecipient($recipient, 'local');

        return response($this->html($recipient->fresh(), true));
    }

    private function html(MailingCampaignRecipient $recipient, bool $done): string
    {
        $email = e($recipient->email);
        $token = e($recipient->unsubscribe_token);
        $title = $done ? 'Вы отписаны' : 'Отписка от рассылки';
        $body = $done
            ? '<p>Адрес <strong>'.$email.'</strong> больше не будет получать коммерческие предложения.</p>'
            : '<p>Подтвердите отписку адреса <strong>'.$email.'</strong> от коммерческих предложений.</p><form method="POST" action="/mailings/unsubscribe/'.$token.'"><button type="submit">Отписаться</button></form>';

        return '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$title.'</title><style>body{margin:0;background:#050805;color:#9cff57;font:16px ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}.box{max-width:680px;margin:12vh auto;padding:24px;border:1px solid #1c3a1c;background:#071007}button{background:#9cff57;color:#050805;border:0;padding:10px 16px;font-weight:700;cursor:pointer}</style></head><body><main class="box"><h1>'.$title.'</h1>'.$body.'</main></body></html>';
    }
}
