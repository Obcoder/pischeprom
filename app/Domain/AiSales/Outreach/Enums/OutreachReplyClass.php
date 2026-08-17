<?php

namespace App\Domain\AiSales\Outreach\Enums;

enum OutreachReplyClass: string
{
    case PositiveInterest = 'positive_interest';
    case PriceRequest = 'price_request';
    case SampleRequest = 'sample_request';
    case AvailabilityRequest = 'availability_request';
    case Objection = 'objection';
    case NotInterested = 'not_interested';
    case WrongContact = 'wrong_contact';
    case UnsubscribeRequest = 'unsubscribe_request';
    case OutOfOffice = 'out_of_office';
    case DeliverySystem = 'delivery_system';
    case Unknown = 'unknown';
}
