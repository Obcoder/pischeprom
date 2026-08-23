<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Outreach\Enums\OutreachReplyClass;
use App\Models\MailMessage;

final class FakeOutreachReplyTriageProvider
{
    public function classify(MailMessage $message): OutreachReplyClass
    {
        $text = mb_strtolower(mb_substr(trim((string) $message->subject.' '.(string) $message->preview), 0, 2_000));

        return match (true) {
            preg_match('/mailer-daemon|delivery[ -]status|недостав/iu', $text) === 1 => OutreachReplyClass::DeliverySystem,
            preg_match('/out of office|автоответ|в отпуск/iu', $text) === 1 => OutreachReplyClass::OutOfOffice,
            preg_match('/отпис|unsubscribe/iu', $text) === 1 => OutreachReplyClass::UnsubscribeRequest,
            preg_match('/не интерес|неактуаль|not interested/iu', $text) === 1 => OutreachReplyClass::NotInterested,
            preg_match('/не тот адрес|wrong contact/iu', $text) === 1 => OutreachReplyClass::WrongContact,
            preg_match('/образец|sample/iu', $text) === 1 => OutreachReplyClass::SampleRequest,
            preg_match('/цен|price|стоимост/iu', $text) === 1 => OutreachReplyClass::PriceRequest,
            preg_match('/налич|availability/iu', $text) === 1 => OutreachReplyClass::AvailabilityRequest,
            preg_match('/интерес|давайте|готовы обсудить/iu', $text) === 1 => OutreachReplyClass::PositiveInterest,
            preg_match('/возраж|слишком|дорог/iu', $text) === 1 => OutreachReplyClass::Objection,
            default => OutreachReplyClass::Unknown,
        };
    }
}
