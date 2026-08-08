<?php

namespace App\Services\Avito;

use App\Models\AvitoChat;
use App\Models\AvitoContactCandidate;
use App\Models\AvitoMessage;
use App\Support\PhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AvitoContactDetector
{
    /**
     * Detect CRM facts without mutating Entity, Telephone or Building records.
     * Every result remains a reviewable candidate until an operator accepts it.
     *
     * @return Collection<int, AvitoContactCandidate>
     */
    public function detectMessage(AvitoMessage $message): Collection
    {
        if ($message->crm_scanned_at) {
            return collect();
        }

        if ($message->direction !== 'in' || $message->remote_type === 'deleted') {
            return collect();
        }

        $detected = collect();
        $text = trim((string) $message->text);

        if ($text !== '') {
            foreach ($this->phones($text) as $phone) {
                $detected->push($this->store(
                    $message,
                    AvitoContactCandidate::TYPE_PHONE,
                    $phone['raw'],
                    $phone['normalized'],
                    $phone['confidence'],
                ));
            }

            foreach ($this->addresses($text) as $address) {
                $detected->push($this->store(
                    $message,
                    AvitoContactCandidate::TYPE_ADDRESS,
                    $address,
                    $this->normalizeAddress($address),
                    76,
                    ['source' => 'message_text'],
                ));
            }
        }

        $location = (array) data_get($message->content, 'location', []);
        $locationTitle = trim((string) ($location['title'] ?? $location['text'] ?? ''));

        if ($message->type === 'location' && $locationTitle !== '') {
            $detected->push($this->store(
                $message,
                AvitoContactCandidate::TYPE_ADDRESS,
                Str::limit($locationTitle, 1024, ''),
                $this->normalizeAddress($locationTitle),
                98,
                [
                    'source' => 'avito_location',
                    'lat' => $location['lat'] ?? $location['latitude'] ?? null,
                    'lon' => $location['lon'] ?? $location['longitude'] ?? null,
                ],
            ));
        }

        $message->forceFill(['crm_scanned_at' => now()])->saveQuietly();

        return $detected->filter()->values();
    }

    public function detectChat(AvitoChat $chat): int
    {
        $detected = 0;

        $chat->messages()
            ->where('direction', 'in')
            ->where('remote_type', '!=', 'deleted')
            ->whereNull('crm_scanned_at')
            ->orderBy('id')
            ->chunkById(200, function ($messages) use (&$detected): void {
                foreach ($messages as $message) {
                    $detected += $this->detectMessage($message)->count();
                }
            });

        return $detected;
    }

    /**
     * @return array<int, array{raw: string, normalized: string, confidence: int}>
     */
    public function phones(string $text): array
    {
        preg_match_all(
            '/(?<!\d)(?:(?:\+?7|8)[\s\-.()]*)?(?:\d[\s\-.()]*){10}(?!\d)/u',
            $text,
            $matches,
        );

        return collect($matches[0] ?? [])
            ->map(function (string $raw): ?array {
                $raw = trim($raw, " \t\n\r\0\x0B.,;:()[]{}");
                $normalized = $this->normalizePhone($raw);

                if (! $normalized) {
                    return null;
                }

                $digits = preg_replace('/\D+/', '', $raw) ?: '';
                $hasFormatting = preg_match('/[\s\-.()]/u', $raw) === 1;
                $confidence = str_starts_with($raw, '+7')
                    ? 99
                    : (str_starts_with($digits, '8') ? 94 : ($hasFormatting ? 90 : 76));

                return compact('raw', 'normalized', 'confidence');
            })
            ->filter()
            ->unique('normalized')
            ->values()
            ->all();
    }

    public function normalizePhone(string $value): ?string
    {
        return PhoneNumber::russian($value);
    }

    /** @return array<int, string> */
    private function addresses(string $text): array
    {
        $parts = preg_split('/(?:\R+|[;]|(?<=[!?])\s+)/u', $text) ?: [];
        $streetPattern = '/(?:^|[\s,])(?:адрес(?:ом|у|а)?|ул(?:ица)?\.?|просп(?:ект)?\.?|пр[\s-]?т\.?|пер(?:еулок)?\.?|шоссе|наб(?:ережная)?\.?|бульвар|бул\.?|проезд|пл(?:ощадь)?\.?|аллея|тупик)(?:[\s,:]|$)/iu';
        $housePattern = '/(?:^|[\s,])(?:д(?:ом)?\.?\s*)?\d+[а-яa-z]?(?:[\/-]\d+[а-яa-z]?)?(?:[\s,]|$)/iu';

        return collect($parts)
            ->map(fn (string $part) => trim($part, " \t\n\r\0\x0B.,;"))
            ->filter(fn (string $part) => mb_strlen($part) >= 6
                && preg_match($streetPattern, $part) === 1
                && preg_match($housePattern, $part) === 1)
            ->map(fn (string $part) => Str::limit($part, 255, ''))
            ->unique(fn (string $part) => $this->normalizeAddress($part))
            ->values()
            ->all();
    }

    private function normalizeAddress(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?: $value;

        return trim($value, " \t\n\r\0\x0B.,;");
    }

    private function store(
        AvitoMessage $message,
        string $type,
        string $raw,
        string $normalized,
        int $confidence,
        array $metadata = [],
    ): AvitoContactCandidate {
        $fingerprint = hash('sha256', $type.'|'.$normalized);
        $candidate = AvitoContactCandidate::query()->firstOrNew([
            'avito_message_id' => $message->id,
            'fingerprint' => $fingerprint,
        ]);

        $candidate->fill([
            'type' => $type,
            'raw_value' => Str::limit($raw, 1024, ''),
            'normalized_value' => Str::limit($normalized, 512, ''),
            'confidence' => max(0, min(100, $confidence)),
            'metadata' => $metadata,
        ]);
        $candidate->status ??= AvitoContactCandidate::STATUS_PENDING;
        try {
            $candidate->save();
        } catch (UniqueConstraintViolationException) {
            $candidate = AvitoContactCandidate::query()
                ->where('avito_message_id', $message->id)
                ->where('fingerprint', $fingerprint)
                ->firstOrFail();
        }

        return $candidate;
    }
}
