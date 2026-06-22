<?php

namespace App\Services\CommercialOffers;

use App\Models\MailingCampaign;
use App\Models\MailingCampaignRecipient;
use App\Models\MailingContact;
use App\Models\MailingOfferItem;
use Illuminate\Support\Str;

class MailingRenderer
{
    public const MAX_MESSAGE_BYTES = 20 * 1024 * 1024;

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
        return $this->productListTable($this->renderProductRow($item, $campaign, $recipient));
    }

    public function renderProductGrid(array $items, MailingCampaign $campaign, ?MailingCampaignRecipient $recipient = null): string
    {
        $rows = collect($items)
            ->map(fn ($item) => $this->renderProductRow($item, $campaign, $recipient))
            ->implode('');

        return $rows !== '' ? $this->productListTable($rows) : '';
    }

    private function renderProductRow(MailingOfferItem|array $item, MailingCampaign $campaign, ?MailingCampaignRecipient $recipient = null): string
    {
        $data = $item instanceof MailingOfferItem ? $item->toArray() : $item;
        $snapshot = is_array($data['snapshot'] ?? null) ? $data['snapshot'] : [];
        $title = e((string) ($data['title'] ?? 'Товар'));
        $description = e(Str::limit(strip_tags((string) ($data['description'] ?? '')), 92));
        $price = $data['offer_price'] ?? $data['original_price'] ?? null;
        $currency = $data['currency'] ?? 'RUB';
        $utmContent = 'product_'.($data['product_id'] ?? 'custom');
        $url = $this->withUtm((string) ($data['canonical_url'] ?? config('app.url')), $campaign, $utmContent);
        $image = $this->safeEmailImageUrl((string) ($data['thumbnail_url'] ?? ''));
        $sku = trim((string) ($data['sku'] ?? $snapshot['sku'] ?? ''));
        $category = trim((string) ($snapshot['category'] ?? ''));
        $meta = collect([$sku !== '' ? 'SKU '.$sku : null, $category ?: null])->filter()->implode(' / ');
        $imageHtml = $image !== ''
            ? '<img src="'.e($image).'" alt="'.$title.'" width="52" height="52" style="display:block;width:52px;height:52px;object-fit:cover;border:0;border-radius:6px;background:#eef4ea;">'
            : '<span style="display:block;width:52px;height:52px;border-radius:6px;background:#eef4ea;">&nbsp;</span>';
        $priceHtml = $price !== null
            ? e(number_format((float) $price, 2, ',', ' ')).' '.e($currency)
            : 'по запросу';

        return '<tr>'
            .'<td width="64" valign="top" style="padding:8px 10px 8px 12px;border-bottom:1px solid #e3edde;">'.$imageHtml.'</td>'
            .'<td valign="top" style="padding:8px 8px;border-bottom:1px solid #e3edde;font-family:Arial,sans-serif;color:#203020;">'
            .'<div style="font-size:14px;line-height:18px;font-weight:700;color:#1f2f1d;margin:0 0 2px;">'.$title.'</div>'
            .($meta !== '' ? '<div style="font-size:11px;line-height:15px;color:#71806d;margin:0 0 2px;">'.e($meta).'</div>' : '')
            .($description !== '' ? '<div style="font-size:12px;line-height:16px;color:#526052;margin:0;">'.$description.'</div>' : '')
            .'</td>'
            .'<td width="120" valign="top" align="right" style="padding:10px 8px;border-bottom:1px solid #e3edde;font-family:Arial,sans-serif;font-size:14px;line-height:18px;font-weight:700;color:#203b14;white-space:nowrap;">'.$priceHtml.'</td>'
            .'<td width="74" valign="top" align="right" style="padding:8px 12px 8px 6px;border-bottom:1px solid #e3edde;font-family:Arial,sans-serif;">'
            .'<a href="'.e($url).'" style="display:inline-block;background:#2f7d32;color:#ffffff;text-decoration:none;border-radius:4px;padding:6px 9px;font-size:11px;line-height:13px;font-weight:700;white-space:nowrap;">Открыть</a>'
            .'</td>'
            .'</tr>';
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
        if (strlen($html) > self::MAX_MESSAGE_BYTES) {
            $errors[] = 'Email HTML is too large: '.$this->formatBytes(strlen($html)).'. Limit is '.$this->formatBytes(self::MAX_MESSAGE_BYTES).'. Remove embedded/base64 media or reduce content.';
        }
        if (preg_match('/\bdata:(?:image|video|application)\//i', $html)) {
            $errors[] = 'Embedded base64/data media is not allowed in email. Upload images and use public HTTPS URLs.';
        }
        if (preg_match('/<(?:video|source)\b/i', $html)) {
            $errors[] = 'Video tags are not allowed in email. Use a regular HTTPS product page link instead.';
        }
        if (preg_match('/src=["\'][^"\']+\.(?:mp4|m4v|mov|webm|avi|mkv|mpeg|mpg|ogv|m3u8)(?:\?|#|["\'])/i', $html)) {
            $errors[] = 'Video URLs cannot be used as email image sources.';
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

    private function productListTable(string $rows): string
    {
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:0;border:1px solid #e3c2a6;border-radius:10px;overflow:hidden;margin:12px 0;background:#ffffff;">'
            .'<tr>'
            .'<td colspan="2" style="padding:9px 12px;background:#8b1e1e;color:#fff7e6;font-family:Arial,sans-serif;font-size:12px;line-height:16px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;border-bottom:3px solid #d69a2d;">Некоторые позиции каталога</td>'
            .'<td width="120" align="right" style="padding:9px 8px;background:#8b1e1e;color:#fff7e6;font-family:Arial,sans-serif;font-size:12px;line-height:16px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;border-bottom:3px solid #d69a2d;">Цена</td>'
            .'<td width="74" style="padding:9px 12px 9px 6px;background:#8b1e1e;border-bottom:3px solid #d69a2d;">&nbsp;</td>'
            .'</tr>'
            .$rows
            .'</table>';
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

    private function safeEmailImageUrl(string $url): string
    {
        $url = trim($url);
        $lowerUrl = Str::lower($url);

        if ($url === '' || str_starts_with($lowerUrl, 'data:')) {
            return '';
        }

        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));
        if (preg_match('/\.(mp4|m4v|mov|webm|avi|mkv|mpeg|mpg|ogv|m3u8|pdf|doc|docx|xls|xlsx|zip|rar)$/i', $path)) {
            return '';
        }

        if (! str_starts_with($lowerUrl, 'https://')) {
            return '';
        }

        return $url;
    }

    private function formatBytes(int $bytes): string
    {
        return round($bytes / 1024 / 1024, 2).' MiB';
    }
}
