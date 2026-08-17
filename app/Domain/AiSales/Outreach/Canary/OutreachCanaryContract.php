<?php

namespace App\Domain\AiSales\Outreach\Canary;

use App\Domain\AiSales\DTO\Providers\AiProviderInputItem;
use App\Domain\AiSales\DTO\Providers\AiProviderOutputItem;
use App\Domain\AiSales\DTO\Providers\AiProviderRequest;
use App\Domain\AiSales\DTO\Providers\AiProviderResponse;
use App\Domain\AiSales\DTO\Providers\AiProviderUsage;
use App\Domain\AiSales\DTO\Providers\AiRequestRequirements;
use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiProviderResponseStatus;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Outreach\OutreachSafeDto;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Models\OutreachDraft;
use JsonException;

final class OutreachCanaryContract
{
    public const SCENARIO = 'outreach_food_factory_broccoli_ru_v1';

    public const MODEL_ID = 'openai/gpt-5.6-luna';

    public const SCHEMA_PROFILE = 'outreach_email_ru_b2b.v1';

    public const WIRE_SCHEMA_NAME = 'outreach_email_ru_b2b_v1';

    public const RUN_PUBLIC_ID = '12b00000-0000-4000-8000-000000000001';

    public const MAX_INPUT_TOKENS = 8_000;

    public const MAX_OUTPUT_TOKENS = 1_800;

    public const MAX_RUB = '25.0000';

    public const EVIDENCE_REFERENCE = 'synthetic-public-source-1';

    public const EVIDENCE_CLAIM = 'Фиктивная компания производит замороженные овощные смеси.';

    public const EVIDENCE_INDICATOR = 'В синтетическом каталоге указаны смеси с брокколи.';

    public function buildRequest(OutreachSafeDto $dto, string $recipientMarker): AiProviderRequest
    {
        $payload = $dto->toArray();
        $this->assertSafeDto($payload, $recipientMarker);
        $items = [
            new AiProviderInputItem('instruction', 'stage12b_outreach_instruction', $this->instruction()),
            new AiProviderInputItem('sanitized_data', 'stage12b_outreach_safe_dto', $payload),
        ];
        $schema = $this->responseSchema();
        $maxInputTokens = $this->effectiveTokenCap('max_input_tokens', self::MAX_INPUT_TOKENS);
        $maxOutputTokens = $this->effectiveTokenCap('max_output_tokens', self::MAX_OUTPUT_TOKENS);

        return new AiProviderRequest(
            runPublicId: self::RUN_PUBLIC_ID,
            stepSequence: 1,
            contour: AiProcessingContour::ExternalSanitized,
            modelProfile: AiModelProfile::OutreachDrafting,
            inputItems: $items,
            responseSchema: $schema,
            toolSchemas: [],
            requirements: new AiRequestRequirements(
                ['responses', 'strict_structured_outputs'],
                $maxInputTokens,
                $maxOutputTokens,
                true,
            ),
            idempotencyKey: hash('sha256', 'stage12b:'.self::SCENARIO.':request-1'),
            policyDecisionHash: hash('sha256', 'stage12b:external-sanitized:no-contact:no-dispatch:v1'),
            promptHash: hash('sha256', AiCanonicalJson::encode($this->instruction())),
            schemaHash: $this->schemaHash(),
            sanitizedPayloadHash: $this->inputHash($items),
            classificationSummary: ['public' => 2],
            containsLocalOnlyData: false,
            timeoutSeconds: 45,
            syntheticOnly: true,
            responseSchemaName: self::WIRE_SCHEMA_NAME,
        );
    }

    public function authorizeRequest(AiProviderRequest $request): bool
    {
        if ($request->runPublicId !== self::RUN_PUBLIC_ID) {
            return false;
        }

        if ($request->stepSequence !== 1
            || $request->contour !== AiProcessingContour::ExternalSanitized
            || $request->modelProfile !== AiModelProfile::OutreachDrafting
            || ! $request->syntheticOnly
            || $request->responseSchemaName !== self::WIRE_SCHEMA_NAME
            || $request->responseSchema !== $this->responseSchema()
            || $request->toolSchemas !== []
            || $request->requirements->capabilities !== ['responses', 'strict_structured_outputs']
            || $request->requirements->maxInputTokens !== $this->effectiveTokenCap('max_input_tokens', self::MAX_INPUT_TOKENS)
            || $request->requirements->maxOutputTokens !== $this->effectiveTokenCap('max_output_tokens', self::MAX_OUTPUT_TOKENS)
            || ! $request->requirements->requiresStoreFalse
            || $request->store() !== false
            || $request->classificationSummary !== ['public' => 2]
            || $request->containsLocalOnlyData
            || ! hash_equals(hash('sha256', 'stage12b:'.self::SCENARIO.':request-1'), $request->idempotencyKey)
            || ! hash_equals(hash('sha256', 'stage12b:external-sanitized:no-contact:no-dispatch:v1'), $request->policyDecisionHash)
            || ! hash_equals(hash('sha256', AiCanonicalJson::encode($this->instruction())), $request->promptHash)
            || ! hash_equals($this->schemaHash(), $request->schemaHash)
            || ! hash_equals($this->inputHash($request->inputItems), $request->sanitizedPayloadHash)
            || count($request->inputItems) !== 2) {
            throw new PolicyViolation('stage12b_provider_envelope_blocked', 'The Stage 12B provider envelope differs from its code-owned contract.');
        }

        [$instruction, $dto] = $request->inputItems;
        if ($instruction->type !== 'instruction'
            || $instruction->label !== 'stage12b_outreach_instruction'
            || $instruction->data !== $this->instruction()
            || $dto->type !== 'sanitized_data'
            || $dto->label !== 'stage12b_outreach_safe_dto') {
            throw new PolicyViolation('stage12b_provider_items_blocked', 'The Stage 12B provider items differ from their code-owned contract.');
        }

        $this->assertSafeDto($dto->data);

        return true;
    }

    /** @return array{content: array<string, mixed>, response_status: string, request_id_hash: ?string, usage: array<string, mixed>} */
    public function normalizeResponse(
        AiProviderResponse $response,
        OutreachDraft $draft,
        string $recipientMarker,
    ): array {
        if ($response->status !== AiProviderResponseStatus::Completed) {
            throw new PolicyViolation('stage12b_provider_status_blocked', 'The provider did not complete the synthetic draft.');
        }
        if ($response->providerCode !== 'timeweb'
            || $response->providerRoute !== 'external_sanitized'
            || $response->modelId !== self::MODEL_ID) {
            throw new PolicyViolation('stage12b_provider_identity_blocked', 'The normalized provider identity differs from the fixed route/model.');
        }
        if ($response->toolCalls !== [] || $response->citations !== []) {
            throw new PolicyViolation('stage12b_provider_extras_blocked', 'Provider tools, search or citations are forbidden.');
        }
        if (count($response->outputItems) !== 1) {
            throw new PolicyViolation('stage12b_provider_output_count_blocked', 'Exactly one normalized text output is required.');
        }

        $item = $response->outputItems[0];
        $text = $item instanceof AiProviderOutputItem && $item->type === 'text'
            ? ($item->data['text'] ?? null)
            : null;
        if (! is_string($text) || $text === '' || strlen($text) > 24_576) {
            throw new PolicyViolation('stage12b_provider_output_missing', 'The normalized provider output is absent or oversized.');
        }
        if ($recipientMarker !== '' && str_contains(mb_strtolower($text), mb_strtolower($recipientMarker))) {
            throw new PolicyViolation('stage12b_recipient_returned', 'Recipient data appeared in provider output.');
        }

        try {
            $decoded = json_decode($text, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new PolicyViolation('stage12b_output_json_invalid', 'The structured provider output is invalid JSON.');
        }
        if (! is_array($decoded)) {
            throw new PolicyViolation('stage12b_output_schema_invalid', 'The structured provider output has an invalid root.');
        }
        $this->assertProviderOutput($decoded);

        $productMatch = $draft->productMatch;
        $content = [
            'subject' => trim($decoded['subject']),
            'greeting' => 'Добрый день.',
            'introduction' => trim($decoded['opening']),
            'value_proposition' => trim($decoded['relevance_statement']),
            'evidence_points' => array_map('trim', $decoded['offer_items']),
            'call_to_action' => trim($decoded['call_to_action']),
            'closing' => trim($decoded['closing']),
            'claims' => array_map(static fn (array $claim): array => [
                'type' => 'product_relevance',
                'text' => trim($claim['text']),
                'evidence_type' => 'unit_product_match',
                'evidence_reference' => (string) $productMatch->evidence_reference,
                'evidence_hash' => (string) $productMatch->evidence_hash,
            ], $decoded['claims']),
        ];

        $encodedContent = AiCanonicalJson::encode($content);
        if (($recipientMarker !== '' && str_contains(mb_strtolower($encodedContent), mb_strtolower($recipientMarker)))
            || preg_match('/(?:<\/?[a-z][^>]*>|https?:\/\/|www\.|mailto:)/iu', $encodedContent) === 1) {
            throw new PolicyViolation('stage12b_output_dlp_blocked', 'Provider output contains contact, HTML or URL material.');
        }

        return [
            'content' => $content,
            'response_status' => $response->status->value,
            'request_id_hash' => $response->requestId ? hash('sha256', $response->requestId) : null,
            'usage' => [
                'input_tokens' => $response->usage->inputTokens,
                'output_tokens' => $response->usage->outputTokens,
                'reasoning_tokens' => $response->usage->reasoningTokens,
                'normalized_rub' => $response->usage->normalizedRubAmount,
            ],
        ];
    }

    public function fakeResponse(): AiProviderResponse
    {
        $payload = [
            'subject' => 'Брокколи для производства овощных смесей',
            'salutation_style' => 'neutral_business',
            'opening' => 'Предлагаем рассмотреть продукт для выпуска замороженных овощных смесей.',
            'relevance_statement' => 'Брокколи соответствует заявленному направлению синтетического производства.',
            'offer_items' => ['Продукт может быть рассмотрен технологом в рамках плановой оценки сырья.'],
            'call_to_action' => 'Если направление актуально, предлагаем обсудить требования к продукту.',
            'closing' => 'С уважением, команда поставщика.',
            'claims' => [[
                'type' => 'product_relevance',
                'text' => 'Брокколи релевантна производству замороженных овощных смесей.',
                'evidence_key' => 'product_relevance',
            ]],
        ];

        return new AiProviderResponse(
            AiProviderResponseStatus::Completed,
            'timeweb',
            'external_sanitized',
            self::MODEL_ID,
            'stage12b-http-fake-request',
            [new AiProviderOutputItem('text', ['text' => AiCanonicalJson::encode($payload)])],
            [],
            [],
            new AiProviderUsage(420, 180, null, null, 0, 0, null, null, '0.2025'),
        );
    }

    public function inputHash(array $items): string
    {
        return AiCanonicalJson::hash(array_map(static function (mixed $item): array {
            if (! $item instanceof AiProviderInputItem) {
                throw new PolicyViolation('stage12b_provider_items_blocked', 'Provider items must use the bounded input DTO.');
            }

            return ['type' => $item->type, 'label' => $item->label, 'data' => $item->data];
        }, $items));
    }

    /** @return array{input_hash: string, input_bytes: int, input_token_estimate: int} */
    public function inputSummary(AiProviderRequest $request): array
    {
        $encoded = AiCanonicalJson::encode(array_map(
            static fn (AiProviderInputItem $item): array => ['type' => $item->type, 'label' => $item->label, 'data' => $item->data],
            $request->inputItems,
        ));
        $estimate = (int) ceil(strlen($encoded) / 2);
        if ($estimate > $request->requirements->maxInputTokens) {
            throw new PolicyViolation('stage12b_input_token_cap_exceeded', 'The bounded input token estimate exceeds the canary cap.');
        }

        return [
            'input_hash' => hash('sha256', $encoded),
            'input_bytes' => strlen($encoded),
            'input_token_estimate' => $estimate,
        ];
    }

    public function responseSchema(): array
    {
        $text = static fn (int $max): array => ['type' => 'string', 'minLength' => 1, 'maxLength' => $max];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'subject', 'salutation_style', 'opening', 'relevance_statement', 'offer_items',
                'call_to_action', 'closing', 'claims',
            ],
            'properties' => [
                'subject' => $text(160),
                'salutation_style' => ['type' => 'string', 'enum' => ['neutral_business']],
                'opening' => $text(800),
                'relevance_statement' => $text(1_200),
                'offer_items' => [
                    'type' => 'array', 'minItems' => 1, 'maxItems' => 3, 'items' => $text(500),
                ],
                'call_to_action' => $text(600),
                'closing' => $text(300),
                'claims' => [
                    'type' => 'array', 'minItems' => 1, 'maxItems' => 2,
                    'items' => [
                        'type' => 'object', 'additionalProperties' => false,
                        'required' => ['type', 'text', 'evidence_key'],
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['product_relevance']],
                            'text' => $text(500),
                            'evidence_key' => ['type' => 'string', 'enum' => ['product_relevance']],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function instruction(): array
    {
        return [
            'scenario' => self::SCENARIO,
            'schema_profile' => self::SCHEMA_PROFILE,
            'template' => 'Create a concise Russian B2B draft from the delimited fictional public data only. Return the strict schema only. Do not add contacts, URLs, HTML, prices, stock, MOQ, discounts, supplier identities, legal decisions, send instructions or tool calls.',
        ];
    }

    private function schemaHash(): string
    {
        return hash('sha256', json_encode(
            $this->responseSchema(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    private function effectiveTokenCap(string $key, int $hardMaximum): int
    {
        $configured = (int) config('ai-sales.providers.timeweb.probe.'.$key, 0);

        return $configured > 0 ? min($configured, $hardMaximum) : $hardMaximum;
    }

    private function assertSafeDto(array $payload, string $recipientMarker = ''): void
    {
        $this->assertExactKeys($payload, [
            'schema_version', 'task_profile', 'lane', 'role_code', 'purpose', 'unit_reference_hash',
            'product', 'offer', 'constraints',
        ], 'stage12b_safe_dto_shape_blocked');
        $this->assertExactKeys((array) ($payload['product'] ?? []), [
            'reference_hash', 'name', 'match_rationale', 'evidence_reference', 'evidence_hash',
        ], 'stage12b_safe_dto_product_blocked');
        $this->assertExactKeys((array) ($payload['constraints'] ?? []), [
            'no_prices', 'no_stock_claims', 'no_moq_claims', 'no_discounts',
            'no_recipient_contact_data', 'no_raw_correspondence',
        ], 'stage12b_safe_dto_constraints_blocked');

        $product = $payload['product'];
        if (($payload['schema_version'] ?? null) !== 'stage12-outreach-safe-dto-v1'
            || ($payload['task_profile'] ?? null) !== 'outreach_drafting'
            || ($payload['lane'] ?? null) !== 'sales'
            || ($payload['role_code'] ?? null) !== 'prospective_customer'
            || ($payload['purpose'] ?? null) !== 'advertising_outreach'
            || preg_match('/^[a-f0-9]{64}$/D', (string) ($payload['unit_reference_hash'] ?? '')) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', (string) ($product['reference_hash'] ?? '')) !== 1
            || ($product['name'] ?? null) !== 'Брокколи'
            || ($product['match_rationale'] ?? null) !== self::EVIDENCE_INDICATOR
            || ($product['evidence_reference'] ?? null) !== self::EVIDENCE_REFERENCE
            || ($product['evidence_hash'] ?? null) !== hash('sha256', self::EVIDENCE_CLAIM.'|'.self::EVIDENCE_INDICATOR)
            || $payload['offer'] !== null
            || collect($payload['constraints'] ?? [])->contains(fn (mixed $value): bool => $value !== true)) {
            throw new PolicyViolation('stage12b_safe_dto_values_blocked', 'The Outreach Safe DTO differs from the fixed fictional scenario.');
        }

        $encoded = AiCanonicalJson::encode($payload);
        if (strlen($encoded) > 16_384
            || ($recipientMarker !== '' && str_contains(mb_strtolower($encoded), mb_strtolower($recipientMarker)))
            || preg_match('/"(?:email|phone|contact|recipient|supplier|purchase|margin|raw_correspondence|contract|invoice|payment|entity_id|good_id)"\s*:/iu', $encoded) === 1
            || preg_match('/(?:mailto:|\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b|https?:\/\/)/iu', $encoded) === 1) {
            throw new PolicyViolation('stage12b_safe_dto_dlp_blocked', 'Contact, restricted or oversized data appeared in Outreach Safe DTO.');
        }
    }

    private function assertProviderOutput(array $payload): void
    {
        $this->assertExactKeys($payload, [
            'subject', 'salutation_style', 'opening', 'relevance_statement', 'offer_items',
            'call_to_action', 'closing', 'claims',
        ], 'stage12b_output_schema_invalid');
        foreach (['subject' => 160, 'opening' => 800, 'relevance_statement' => 1_200, 'call_to_action' => 600, 'closing' => 300] as $key => $max) {
            if (! is_string($payload[$key]) || trim($payload[$key]) === '' || mb_strlen($payload[$key]) > $max) {
                throw new PolicyViolation('stage12b_output_schema_invalid', 'A bounded output field is invalid.');
            }
        }
        if ($payload['salutation_style'] !== 'neutral_business'
            || ! is_array($payload['offer_items']) || ! array_is_list($payload['offer_items'])
            || count($payload['offer_items']) < 1 || count($payload['offer_items']) > 3
            || collect($payload['offer_items'])->contains(fn (mixed $value): bool => ! is_string($value) || trim($value) === '' || mb_strlen($value) > 500)
            || ! is_array($payload['claims']) || ! array_is_list($payload['claims'])
            || count($payload['claims']) < 1 || count($payload['claims']) > 2) {
            throw new PolicyViolation('stage12b_output_schema_invalid', 'The output arrays or salutation style are invalid.');
        }
        foreach ($payload['claims'] as $claim) {
            if (! is_array($claim)) {
                throw new PolicyViolation('stage12b_output_schema_invalid', 'A provider claim is invalid.');
            }
            $this->assertExactKeys($claim, ['type', 'text', 'evidence_key'], 'stage12b_output_schema_invalid');
            if (($claim['type'] ?? null) !== 'product_relevance'
                || ($claim['evidence_key'] ?? null) !== 'product_relevance'
                || ! is_string($claim['text'] ?? null)
                || trim($claim['text']) === ''
                || mb_strlen($claim['text']) > 500) {
                throw new PolicyViolation('stage12b_claim_blocked', 'The provider claim is unsupported or unbound.');
            }
        }

        $encoded = AiCanonicalJson::encode($payload);
        if (preg_match('/(?:<\/?[a-z][^>]*>|https?:\/\/|www\.|mailto:|\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b|(?<!\d)(?:\+7|8)[\s()\-]*\d{3}[\s()\-]*\d{3}[\s\-]*\d{2}[\s\-]*\d{2}(?!\d)|\b(?:price|stock|discount|moq)\b|цен[аы]\s*[:=]|в\s+наличии|скидк[а-я]*|минимальн[а-я ]+парти[яи]|supplier\s+(?:identity|contact)|поставщик\s*[:=]|permission\s*[:=]|send\s+now|ignore\s+(?:all\s+)?previous|system\s+prompt)/iu', $encoded) === 1) {
            throw new PolicyViolation('stage12b_output_dlp_blocked', 'The provider output contains forbidden commercial, active or instruction material.');
        }
    }

    private function assertExactKeys(array $value, array $expected, string $code): void
    {
        $actual = array_keys($value);
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            throw new PolicyViolation($code, 'A Stage 12B object contains missing or additional fields.');
        }
    }
}
