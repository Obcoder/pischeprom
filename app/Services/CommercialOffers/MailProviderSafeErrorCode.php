<?php

namespace App\Services\CommercialOffers;

enum MailProviderSafeErrorCode: string
{
    case AuthenticationFailed = 'authentication_failed';
    case PermissionDenied = 'permission_denied';
    case InvalidContentType = 'invalid_content_type';
    case PayloadTooLarge = 'payload_too_large';
    case MalformedPayload = 'malformed_payload';
    case InvalidSignature = 'invalid_signature';
    case DuplicateEvent = 'duplicate_event';
    case UnknownEvent = 'unknown_event';
    case RateLimited = 'rate_limited';
    case Timeout = 'timeout';
    case ConnectionFailed = 'connection_failed';
    case Provider5xx = 'provider_5xx';
    case MalformedResponse = 'malformed_response';
    case AmbiguousAcceptance = 'ambiguous_acceptance';
    case ProcessingFailedSafe = 'processing_failed_safe';

    public function summary(): string
    {
        return match ($this) {
            self::AuthenticationFailed => 'Provider authentication failed.',
            self::PermissionDenied => 'Provider rejected the operation.',
            self::InvalidContentType => 'Webhook content type or encoding is not allowed.',
            self::PayloadTooLarge => 'Webhook payload exceeds the allowed size.',
            self::MalformedPayload => 'Webhook payload is malformed.',
            self::InvalidSignature => 'Webhook signature is missing or invalid.',
            self::DuplicateEvent => 'Provider event was already accepted.',
            self::UnknownEvent => 'Provider event type is not supported.',
            self::RateLimited => 'Webhook request rate limit exceeded.',
            self::Timeout => 'Provider request timed out.',
            self::ConnectionFailed => 'Provider connection failed.',
            self::Provider5xx => 'Provider is temporarily unavailable.',
            self::MalformedResponse => 'Provider returned an invalid response.',
            self::AmbiguousAcceptance => 'Provider acceptance is ambiguous; operator review is required.',
            self::ProcessingFailedSafe => 'Provider operation could not be processed safely.',
        };
    }
}
