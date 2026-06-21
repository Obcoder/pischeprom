<?php

namespace App\Services\CommercialOffers;

use App\Models\MailingCampaign;
use App\Models\MailingCampaignRecipient;
use App\Models\MailingContact;
use App\Models\MailingOfferItem;
use Illuminate\Support\Str;

class MailingRenderer
{
    public function renderCampaignHtml(MailingCampaign $campaign, MailingContact $contact, array $products = [], ?MailingCampaignRecipient $recipient = null): string
    {
        $html = $recipient?->personal_html_markup ?: $campaign->html_markup ?: $campaign->template?->html_markup ?: $this->defaultMarkup();
        $offerItemsHtml = $this->renderProductGrid($products, $campaign, $recipient);
        $unsubscribeUrl = $recipient ? route('mailings.unsubscribe.show', $recipient->unsubscribe_token) : '{{unsubscribe_url}}';

        $variables = [
            '{{to_name}}' => trim($contact->first_name.' '.$contact->last_name) ?: $contact->email,
            '{{first_name}}' => (string) $contact->first_name,
            '{{last_name}}' => (string) $contact->last_name,
            '{{company_name}}' => (string) $contact->company_name,
            '{{manager_name}}' => '',
            '{{manager_phone}}' => '',
            '{{manager_email}}' => (string) $campaign->reply_to,
            '{{campaign_name}}' => $campaign->name,
            '{{unsubscribe_url}}' => $unsubscribeUrl,
            '{{products_html}}' => $offerItemsHtml,
            '{{offer_items_html}}' => $offerItemsHtml,
        ];

        $body = strtr($html, $variables);
        if (! str_contains($body, $unsubscribeUrl) && ! str_contains($body, '{{unsubscribe_url}}')) {
            $body .= $this->unsubscribeFooter($unsubscribeUrl);
        }

        return $this->inlineCssIfPossible($this->sanitizeHtml($body));
    }

    public function renderPlaintext(MailingCampaign $campaign, MailingContact $contact, array $products = [], ?MailingCampaignRecipient $recipient = null): string
    {
        $text = $recipient?->personal_plaintext ?: $campaign->plaintext ?: $campaign->template?->plaintext;
        if (! $text) {
            $text = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $this->renderCampaignHtml($campaign, $contact, $products, $recipient)));
        }

        return trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function renderProductCard(MailingOfferItem|array $item, MailingCampaign $campaign, ?MailingCampaignRecipient $recipient = null): string
    {
        $data = $item instanceof MailingOfferItem ? $item->toArray() : $item;
        $title = e((string) ($data['title'] ?? 'Товар'));
        $description = e(Str::limit(strip_tags((string) ($data['description'] ?? '')), 180));
        $price = $data['offer_price'] ?? $data['original_price'] ?? null;
        $currency = $data['currency'] ?? 'RUB';
        $utmContent = 'product_'.($data['product_id'] ?? 'custom');
        $url = $this->withUtm((string) ($data['canonical_url'] ?? config('app.url')), $campaign, $utmContent);
        $image = (string) ($data['thumbnail_url'] ?? '');
        $imageHtml = $image !== '' ? '<img src="'.e($image).'" alt="'.$title.'" width="160" style="display:block;width:160px;max-width:100%;border:0;border-radius:6px;">' : '';
        $priceHtml = $price !== null ? '<div style="font-size:18px;font-weight:700;color:#203b14;margin:8px 0;">'.e(number_format((float) $price, 2, ',', ' ')).' '.e($currency).'</div>' : '';

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #d7e6d1;border-radius:10px;margin:0 0 12px 0;background:#ffffff;">'
            .'<tr><td width="180" valign="top" style="padding:14px;">'.$imageHtml.'</td>'
            .'<td valign="top" style="padding:14px;font-family:Arial,sans-serif;color:#203020;">'
            .'<div style="font-size:16px;font-weight:700;margin-bottom:6px;">'.$title.'</div>'
            .'<div style="font-size:13px;line-height:1.45;color:#526052;">'.$description.'</div>'.$priceHtml
            .'<a href="'.e($url).'" style="display:inline-block;background:#2f7d32;color:#ffffff;text-decoration:none;border-radius:5px;padding:9px 14px;font-size:13px;font-weight:700;">Запросить КП</a>'
            .'</td></tr></table>';
    }

    public function renderProductGrid(array $items, MailingCampaign $campaign, ?MailingCampaignRecipient $recipient = null): string
    {
        return collect($items)
            ->map(fn ($item) => $this->renderProductCard($item, $campaign, $recipient))
            ->implode('');
    }

    public function sanitizeHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;

        return $html;
    }

    public function inlineCssIfPossible(string $html): string
    {
        return $html;
    }

    public function generatePreheader(string $html, ?string $preheader = null): string
    {
        return trim($preheader ?: Str::limit(strip_tags($html), 120));
    }

    public function validateEmailHtml(string $html): array
    {
        $errors = [];
        if (! str_contains($html, 'unsubscribe')) {
            $errors[] = 'Missing unsubscribe link/block.';
        }
        if (strlen($html) > 1024 * 1024) {
            $errors[] = 'HTML is larger than 1MB.';
        }
        if (preg_match('/src=["\']http:\/\//i', $html)) {
            $errors[] = 'Images must use HTTPS URLs.';
        }

        return $errors;
    }

    private function defaultMarkup(): string
    {
        return '<table role="presentation" width="100%" style="background:#f4f7f2;padding:20px;"><tr><td align="center">'
            .'<table role="presentation" width="680" style="max-width:680px;background:#ffffff;border-radius:12px;padding:22px;font-family:Arial,sans-serif;color:#203020;">'
            .'<tr><td><div style="display:none;max-height:0;overflow:hidden;">{{preheader}}</div><h1>{{campaign_name}}</h1><p>Здравствуйте, {{first_name}}.</p><p>Подготовили для вас коммерческое предложение.</p>{{offer_items_html}}<p>С уважением, Pischeprom</p><p style="font-size:12px;color:#667">Если письмо неактуально, <a href="{{unsubscribe_url}}">отпишитесь</a>.</p></td></tr></table>'
            .'</td></tr></table>';
    }

    private function unsubscribeFooter(string $unsubscribeUrl): string
    {
        return '<p style="font-family:Arial,sans-serif;font-size:12px;color:#667;text-align:center;">Вы получили это письмо как коммерческое предложение. <a href="'.e($unsubscribeUrl).'">Отписаться</a></p>';
    }

    private function withUtm(string $url, MailingCampaign $campaign, string $content): string
    {
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query([
            'utm_source' => 'unisender',
            'utm_medium' => 'email',
            'utm_campaign' => Str::slug($campaign->name) ?: $campaign->id,
            'utm_content' => $content,
        ]);
    }
}
