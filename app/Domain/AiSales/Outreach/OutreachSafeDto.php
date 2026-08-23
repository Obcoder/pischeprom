<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Outreach\Enums\MessagePurpose;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Models\OutreachDraft;
use LogicException;

final readonly class OutreachSafeDto
{
    public function __construct(private array $payload) {}

    public static function fromDraft(OutreachDraft $draft): self
    {
        foreach (['businessContext', 'productMatch', 'productMatch.product'] as $relation) {
            self::assertRelationLoaded($draft, $relation);
        }

        $context = $draft->businessContext;
        $productMatch = $draft->productMatch;
        $product = $productMatch->product;
        $good = null;

        if ($draft->unit_good_match_id) {
            self::assertRelationLoaded($draft, 'goodMatch');
            self::assertRelationLoaded($draft->goodMatch, 'good');
            $good = $draft->goodMatch->good;
        }

        $payload = [
            'schema_version' => 'stage12-outreach-safe-dto-v1',
            'task_profile' => 'outreach_drafting',
            'lane' => $context->lane->value,
            'role_code' => $context->role_code->value,
            'purpose' => ($draft->purpose instanceof MessagePurpose ? $draft->purpose : MessagePurpose::from($draft->purpose))->value,
            'unit_reference_hash' => hash('sha256', 'unit:'.$draft->unit_id),
            'product' => [
                'reference_hash' => hash('sha256', 'product:'.$product->id),
                'name' => self::bounded($product->rus ?? $product->eng ?? 'Продукт', 255),
                'match_rationale' => self::bounded($productMatch->safe_rationale, 500),
                'evidence_reference' => self::bounded($productMatch->evidence_reference, 512),
                'evidence_hash' => $productMatch->evidence_hash,
            ],
            'offer' => $good ? [
                'reference_hash' => hash('sha256', 'good:'.$good->id),
                'name' => self::bounded($good->name ?? $good->title ?? 'Предложение', 255),
                'fit_rationale' => self::bounded($draft->goodMatch->safe_rationale, 500),
                'evidence_reference' => self::bounded($draft->goodMatch->evidence_reference, 512),
                'evidence_hash' => $draft->goodMatch->evidence_hash,
            ] : null,
            'constraints' => [
                'no_prices' => true,
                'no_stock_claims' => true,
                'no_moq_claims' => true,
                'no_discounts' => true,
                'no_recipient_contact_data' => true,
                'no_raw_correspondence' => true,
            ],
        ];

        if (strlen(AiCanonicalJson::encode($payload)) > 16_384) {
            throw new LogicException('Outreach Safe DTO exceeds the bounded payload limit.');
        }

        return new self($payload);
    }

    public function toArray(): array
    {
        return $this->payload;
    }

    public function hash(): string
    {
        return AiCanonicalJson::hash($this->payload);
    }

    private static function assertRelationLoaded(object $model, string $relation): void
    {
        $segments = explode('.', $relation);
        $current = $model;
        foreach ($segments as $segment) {
            if (! method_exists($current, 'relationLoaded') || ! $current->relationLoaded($segment)) {
                throw new LogicException('Outreach Safe DTO requires explicitly preloaded relation: '.$relation);
            }
            $current = $current->{$segment};
            if (! $current) {
                throw new LogicException('Outreach Safe DTO relation is missing: '.$relation);
            }
        }
    }

    private static function bounded(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
