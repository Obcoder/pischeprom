<?php

namespace App\Domain\AiSales\Outreach\OwnerCanary;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\OutreachDispatch;
use JsonException;
use Psr\Http\Message\RequestInterface;

final class OwnerControlledCanaryHttpGuard
{
    private int $providerRequests = 0;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $recipient,
        private readonly OutreachDispatch $dispatch,
    ) {}

    public function authorize(RequestInterface $request): RequestInterface
    {
        $uri = $request->getUri();
        $base = parse_url((string) config('services.unisender_go.api_base'));
        $expectedPath = rtrim((string) ($base['path'] ?? ''), '/').'/email/send.json';
        parse_str($uri->getQuery(), $query);

        if (mb_strtoupper($request->getMethod()) !== 'POST'
            || mb_strtolower($uri->getScheme()) !== 'https'
            || ! hash_equals(mb_strtolower((string) ($base['host'] ?? '')), mb_strtolower($uri->getHost()))
            || ($uri->getPort() !== null && $uri->getPort() !== 443)
            || ! hash_equals($expectedPath, $uri->getPath())
            || $query !== ['platform' => 'pischeprom.laravel']
            || $uri->getFragment() !== ''
            || $uri->getUserInfo() !== '') {
            throw new PolicyViolation('stage13b_http_target_blocked', 'Only the exact existing Unisender send endpoint is allowed.');
        }
        if ($this->providerRequests >= 1) {
            throw new PolicyViolation('stage13b_provider_request_cap_exceeded', 'The single provider request cap is exhausted.');
        }
        if (! hash_equals($this->apiKey, $request->getHeaderLine('X-API-KEY'))) {
            throw new PolicyViolation('stage13b_http_auth_blocked', 'The configured Unisender credential was not bound exactly.');
        }

        $rawBody = (string) $request->getBody();
        if ($rawBody === '' || strlen($rawBody) > 65_536) {
            throw new PolicyViolation('stage13b_http_payload_blocked', 'The bounded canary request body is absent or oversized.');
        }
        try {
            $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new PolicyViolation('stage13b_http_payload_blocked', 'The canary request body is malformed.');
        }
        if (! is_array($payload) || array_keys($payload) !== ['message'] || ! is_array($payload['message'])) {
            throw new PolicyViolation('stage13b_http_payload_blocked', 'The canary request root differs from the code-owned contract.');
        }

        $message = $payload['message'];
        $expectedKeys = [
            'body', 'from_email', 'from_name', 'global_metadata', 'idempotence_key',
            'recipients', 'reply_to', 'subject', 'tags', 'track_links', 'track_read',
        ];
        $actualKeys = array_keys($message);
        sort($expectedKeys);
        sort($actualKeys);
        $recipients = $message['recipients'] ?? null;
        $body = $message['body'] ?? null;
        $mail = $this->dispatch->mailMessage;
        $sending = $this->dispatch->sending;

        if ($actualKeys !== $expectedKeys
            || ! is_array($recipients)
            || count($recipients) !== 1
            || ! is_array($recipients[0] ?? null)
            || array_diff(array_keys($recipients[0]), ['email', 'metadata']) !== []
            || ! hash_equals($this->recipient, (string) ($recipients[0]['email'] ?? ''))
            || ($recipients[0]['metadata'] ?? null) !== [
                'sending_id' => (string) $this->dispatch->sending_id,
                'mail_message_id' => (string) $this->dispatch->mail_message_id,
            ]
            || ! is_array($body)
            || array_keys($body) !== ['html', 'plaintext']
            || ! hash_equals((string) $mail->html, (string) ($body['html'] ?? ''))
            || ! hash_equals((string) $mail->text, (string) ($body['plaintext'] ?? ''))
            || ! hash_equals((string) $mail->subject, (string) ($message['subject'] ?? ''))
            || ! hash_equals((string) config('services.unisender_go.from_email'), (string) ($message['from_email'] ?? ''))
            || ! hash_equals((string) config('services.unisender_go.from_name'), (string) ($message['from_name'] ?? ''))
            || ! hash_equals((string) config('services.unisender_go.reply_to'), (string) ($message['reply_to'] ?? ''))
            || ($message['track_links'] ?? null) !== 0
            || ($message['track_read'] ?? null) !== 0
            || ($message['global_metadata'] ?? null) !== ['workflow' => 'reviewed_outreach']
            || ($message['tags'] ?? null) !== ['reviewed_outreach']
            || ! hash_equals('outreach-'.$this->dispatch->public_id, (string) ($message['idempotence_key'] ?? ''))
            || ! $sending
            || $sending->request_profile !== 'outreach_zero_retry') {
            throw new PolicyViolation('stage13b_http_payload_blocked', 'The outgoing message differs from the one-recipient reviewed canary contract.');
        }

        foreach (['cc', 'bcc', 'attachments', 'headers', 'template_id', 'substitutions'] as $blocked) {
            if (array_key_exists($blocked, $message) || array_key_exists($blocked, $recipients[0])) {
                throw new PolicyViolation('stage13b_http_payload_blocked', 'The canary contains a forbidden delivery field.');
            }
        }

        $this->providerRequests++;

        return $request;
    }

    /** @return array<string, int> */
    public function summary(): array
    {
        return [
            'provider_send_requests' => $this->providerRequests,
            'emails_addressed' => $this->providerRequests,
            'timeweb_requests' => 0,
            'yandex_requests' => 0,
            'manual_mail_calls' => 0,
            'follow_up_sends' => 0,
            'auto_replies' => 0,
            'retries' => 0,
            'failovers' => 0,
        ];
    }
}
