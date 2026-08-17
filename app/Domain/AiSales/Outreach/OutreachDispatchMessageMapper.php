<?php

namespace App\Domain\AiSales\Outreach;

use App\Models\OutreachDraftRevision;

final class OutreachDispatchMessageMapper
{
    /** @return array{subject:string, plaintext:string, html:string} */
    public function map(OutreachDraftRevision $revision, string $unsubscribeUrl): array
    {
        $safeUrl = e($unsubscribeUrl);

        return [
            'subject' => $revision->subject,
            'plaintext' => rtrim($revision->plaintext)."\n\nОтписаться от сообщений: {$unsubscribeUrl}\n",
            'html' => rtrim($revision->html).'<p><a href="'.$safeUrl.'">Отписаться от сообщений</a></p>',
        ];
    }
}
