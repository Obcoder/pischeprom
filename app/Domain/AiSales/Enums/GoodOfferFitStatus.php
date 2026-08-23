<?php

namespace App\Domain\AiSales\Enums;

enum GoodOfferFitStatus: string
{
    case OfferCandidate = 'offer_candidate';
    case PreferredOffer = 'preferred_offer';
    case ApprovedForOffer = 'approved_for_offer';
    case Quoted = 'quoted';
    case Rejected = 'rejected';
    case Stale = 'stale';
}
