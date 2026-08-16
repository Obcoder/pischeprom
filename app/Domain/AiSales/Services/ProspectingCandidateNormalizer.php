<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\DTO\AiUntrustedContentEnvelope;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ProspectingChannelKind;
use App\Domain\AiSales\Enums\ProspectingCommunicationState;
use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Domain\AiSales\Tools\AiToolDlpGuard;
use Illuminate\Validation\ValidationException;
use Normalizer;

class ProspectingCandidateNormalizer
{
    public const RULES_VERSION = 'stage08-normalizer-v1';

    public function __construct(private readonly AiToolDlpGuard $dlp) {}

    public function normalize(array $input, ProspectingPurpose $purpose): array
    {
        $this->dlp->assertPayloadSafe($input, AiProcessingContour::LocalRu, $purpose->lane());
        $name = $this->normalizeName((string) ($input['working_name'] ?? ''));
        $website = filled($input['website'] ?? null) ? $this->canonicalizeUrl((string) $input['website']) : null;
        $domain = $website ? $this->normalizeDomain((string) parse_url($website, PHP_URL_HOST)) : null;

        if ($name === '' && $domain === null) {
            throw ValidationException::withMessages(['working_name' => 'A bounded working name or public domain is required.']);
        }

        $sources = collect($input['sources'] ?? [])->take(20)->map(function (array $source) use ($purpose): array {
            $url = filled($source['url'] ?? null) ? $this->canonicalizeUrl((string) $source['url']) : null;
            $reference = mb_substr(trim((string) ($source['reference'] ?? '')), 0, 512);
            if ($url === null && $reference === '') {
                throw ValidationException::withMessages(['sources' => 'Each source needs an HTTP(S) URL or safe reference.']);
            }
            $excerpt = $this->envelope(
                (string) ($source['excerpt'] ?? ''),
                (string) ($source['type'] ?? 'manual_fixture'),
                $reference !== '' ? $reference : (string) $url,
                $this->visibility($purpose),
            );

            return [
                'source_type' => mb_substr((string) ($source['type'] ?? 'manual_fixture'), 0, 32),
                'canonical_url' => $url,
                'source_reference' => $reference !== '' ? $reference : null,
                'title' => mb_substr(trim((string) ($source['title'] ?? '')), 0, 255) ?: null,
                'source_domain' => $url ? $this->normalizeDomain((string) parse_url($url, PHP_URL_HOST)) : null,
                'bounded_excerpt' => $excerpt->boundedText !== '' ? mb_substr($excerpt->boundedText, 0, 1000) : null,
                'evidence_hash' => hash('sha256', implode('|', [$url, $reference, $excerpt->contentHash])),
                'accessed_at' => $source['accessed_at'] ?? now(),
                'published_at' => $source['published_at'] ?? null,
                'data_classification' => DataClassification::Public->value,
                'visibility_scope' => $this->visibility($purpose)->value,
                'confidence' => $this->boundedScore($source['confidence'] ?? null),
                'source_quality' => $this->boundedScore($source['source_quality'] ?? null),
            ];
        })->unique('evidence_hash')->values()->all();

        $channelInput = collect($input['channels'] ?? [])->take(20);
        $hasWebsiteChannel = $website && $channelInput->contains(fn (array $channel) => ($channel['kind'] ?? null) === ProspectingChannelKind::Uri->value
            && $this->canonicalizeUrl((string) ($channel['value'] ?? '')) === $website);
        if ($website && ! $hasWebsiteChannel) {
            $channelInput = $channelInput->take(19)->push([
                'kind' => ProspectingChannelKind::Uri->value,
                'value' => $website,
                'contact_role' => 'business_general',
                'communication_state' => ProspectingCommunicationState::ReviewRequired->value,
            ]);
        }
        $channels = $channelInput->take(20)->map(function (array $channel): array {
            $kind = ProspectingChannelKind::from((string) ($channel['kind'] ?? ''));
            $role = in_array(($channel['contact_role'] ?? ''), ['business_general', 'person_specific'], true)
                ? (string) $channel['contact_role'] : 'business_general';
            $normalized = match ($kind) {
                ProspectingChannelKind::Email => $this->normalizeEmail((string) ($channel['value'] ?? '')),
                ProspectingChannelKind::Telephone => $this->normalizePhone((string) ($channel['value'] ?? '')),
                ProspectingChannelKind::Uri => $this->canonicalizeUrl((string) ($channel['value'] ?? '')),
            };
            $state = ProspectingCommunicationState::tryFrom((string) ($channel['communication_state'] ?? ''))
                ?? ProspectingCommunicationState::ReviewRequired;

            return [
                'channel_kind' => $kind->value,
                'normalized_hash' => hash('sha256', $kind->value.'|'.$normalized),
                'protected_value' => $normalized,
                'masked_display' => $this->mask($normalized, $kind),
                'contact_role' => $role,
                'verification_status' => 'unverified',
                'confidence' => $this->boundedScore($channel['confidence'] ?? null),
                'data_classification' => $role === 'person_specific'
                    ? DataClassification::PersonalData->value : DataClassification::Public->value,
                'communication_state' => $state->value,
                'last_verified_at' => null,
            ];
        })->unique(fn (array $channel) => $channel['channel_kind'].'|'.$channel['normalized_hash'])->values()->all();

        $activity = $this->envelope(
            (string) ($input['public_activity_summary'] ?? ''),
            'candidate_summary',
            'prospecting:activity',
            $this->visibility($purpose),
        )->boundedText;
        $relevance = $this->envelope(
            (string) ($input['relevance_summary'] ?? ''),
            'candidate_summary',
            'prospecting:relevance',
            $this->visibility($purpose),
        )->boundedText;
        $location = mb_substr(trim((string) ($input['location_display'] ?? '')), 0, 255) ?: null;
        $fingerprint = hash('sha256', implode('|', [$purpose->value, $name, $domain, $input['city_id'] ?? '', $location]));
        $payloadHash = hash('sha256', json_encode([
            $name, $domain, $location, $activity, $relevance,
            array_column($sources, 'evidence_hash'), array_column($channels, 'normalized_hash'),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return [
            'purpose' => $purpose->value,
            'lane' => $purpose->lane()->value,
            'role_code' => $purpose->role()->value,
            'working_name' => mb_substr(trim((string) ($input['working_name'] ?? $domain ?? '')), 0, 255),
            'normalized_name' => $name,
            'normalized_domain' => $domain,
            'canonical_website' => $website,
            'country_id' => $input['country_id'] ?? null,
            'region_id' => $input['region_id'] ?? null,
            'city_id' => $input['city_id'] ?? null,
            'location_display' => $location,
            'public_activity_summary' => mb_substr($activity, 0, 1000) ?: null,
            'relevance_summary' => mb_substr($relevance, 0, 1000) ?: null,
            'confidence_components' => $this->safeConfidence($input['confidence_components'] ?? []),
            'fingerprint_hash' => $fingerprint,
            'normalized_payload_hash' => $payloadHash,
            'sources' => $sources,
            'channels' => $channels,
        ];
    }

    public function normalizeName(string $value): string
    {
        $value = class_exists(Normalizer::class) ? (Normalizer::normalize($value, Normalizer::FORM_KC) ?: $value) : $value;
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\pL\pN.\-]+/u', ' ', $value) ?? '';

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $value) ?? ''), 0, 255);
    }

    public function normalizeDomain(string $domain): ?string
    {
        $domain = mb_strtolower(rtrim(trim($domain), '.'));
        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            $domain = $ascii !== false ? $ascii : $domain;
        }
        $domain = preg_replace('/^www\./', '', $domain) ?? $domain;
        if ($domain === '' || mb_strlen($domain) > 253 || ! str_contains($domain, '.')) {
            throw ValidationException::withMessages(['url' => 'A valid public domain is required.']);
        }
        $this->assertPublicHost($domain);

        return $domain;
    }

    public function canonicalizeUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (! is_array($parts) || ! in_array(mb_strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw ValidationException::withMessages(['url' => 'Only credential-free HTTP(S) source URLs are accepted.']);
        }
        $host = $this->normalizeDomain((string) $parts['host']);
        $path = '/'.ltrim((string) ($parts['path'] ?? ''), '/');
        $path = $path === '/' ? '' : rtrim($path, '/');

        return mb_strtolower((string) $parts['scheme']).'://'.$host.$path;
    }

    public function normalizeEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            throw ValidationException::withMessages(['channel' => 'Invalid email channel.']);
        }
        $this->normalizeDomain((string) substr(strrchr($email, '@'), 1));

        return $email;
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';
        if (str_starts_with($phone, '8') && strlen($phone) === 11) {
            $phone = '+7'.substr($phone, 1);
        }
        if (! preg_match('/^\+[1-9]\d{7,14}$/', $phone)) {
            throw ValidationException::withMessages(['channel' => 'Invalid E.164 telephone channel.']);
        }

        return $phone;
    }

    private function assertPublicHost(string $host): void
    {
        if (in_array($host, ['localhost', 'metadata.google.internal'], true)
            || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            throw ValidationException::withMessages(['url' => 'Local or metadata hosts are blocked.']);
        }
        if (filter_var($host, FILTER_VALIDATE_IP)
            && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw ValidationException::withMessages(['url' => 'Private, reserved, and link-local addresses are blocked.']);
        }
    }

    private function visibility(ProspectingPurpose $purpose): UnitVisibilityScope
    {
        return $purpose === ProspectingPurpose::BuyerDiscovery
            ? UnitVisibilityScope::SalesLane : UnitVisibilityScope::ProcurementLane;
    }

    private function envelope(string $text, string $type, string $reference, UnitVisibilityScope $scope): AiUntrustedContentEnvelope
    {
        return new AiUntrustedContentEnvelope($type, mb_substr($reference, 0, 512), $text, DataClassification::Public, $scope);
    }

    private function boundedScore(mixed $score): ?int
    {
        return is_numeric($score) ? max(0, min(100, (int) $score)) : null;
    }

    private function safeConfidence(array $values): array
    {
        return collect(array_slice($values, 0, 12, true))->mapWithKeys(fn ($value, $key) => [
            mb_substr(preg_replace('/[^a-z0-9_\-]/i', '', (string) $key) ?? '', 0, 32) => $this->boundedScore($value),
        ])->filter(fn ($value, $key) => $key !== '' && $value !== null)->all();
    }

    private function mask(string $value, ProspectingChannelKind $kind): string
    {
        return match ($kind) {
            ProspectingChannelKind::Email => preg_replace('/^(.{1,2}).*(@.*)$/u', '$1***$2', $value) ?? '***',
            ProspectingChannelKind::Telephone => '***'.substr($value, -4),
            ProspectingChannelKind::Uri => (string) parse_url($value, PHP_URL_HOST),
        };
    }
}
