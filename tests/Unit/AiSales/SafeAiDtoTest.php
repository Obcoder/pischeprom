<?php

namespace Tests\Unit\AiSales;

use App\Domain\AiSales\DTO\Units\AggregateDemandSummary;
use App\Domain\AiSales\DTO\Units\AggregateSupplySummary;
use App\Domain\AiSales\DTO\Units\CustomerOfferSummary;
use App\Domain\AiSales\DTO\Units\PublicBusinessContactSummary;
use App\Domain\AiSales\DTO\Units\PublicGoodSummary;
use App\Domain\AiSales\DTO\Units\SanitizedEntityLegalSummary;
use App\Domain\AiSales\DTO\Units\UnitBusinessContextSummary;
use App\Domain\AiSales\DTO\Units\UnitSharedPublicProfile;
use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Policies\AiDataClassificationRegistry;
use App\Domain\AiSales\Policies\AiDisclosureContext;
use App\Domain\AiSales\Services\AiContextSanitizer;
use Tests\TestCase;

class SafeAiDtoTest extends TestCase
{
    public function test_every_safe_dto_has_an_exact_code_owned_field_schema(): void
    {
        $registry = app(AiDataClassificationRegistry::class);

        foreach ($this->dtos() as $dto) {
            $fields = array_keys($dto->fields());

            $this->assertSame($fields, array_values(array_intersect($fields, $registry->registeredFields($dto::class))));
            $this->assertEqualsCanonicalizing($fields, $registry->registeredFields($dto::class));
            $this->assertSafeValues($dto->fields());
            $this->assertGreaterThan(0, $dto->maxBytes());
        }
    }

    public function test_profile_row_and_string_caps_drop_unbounded_input(): void
    {
        $profile = new UnitSharedPublicProfile(
            str_repeat('N', 500),
            array_fill(0, 100, str_repeat('A', 500)),
            array_fill(0, 100, str_repeat('I', 500)),
            array_fill(0, 100, str_repeat('C', 500)),
            array_fill(0, 100, str_repeat('U', 2000)),
            array_fill(0, 100, str_repeat('O', 1000)),
        );
        $fields = $profile->fields();

        $this->assertSame(255, mb_strlen($fields['name']));
        $this->assertCount(20, $fields['aliases']);
        $this->assertCount(20, $fields['industries']);
        $this->assertCount(20, $fields['cities']);
        $this->assertCount(10, $fields['public_uris']);
        $this->assertCount(25, $fields['observations']);
        $this->assertSame(255, mb_strlen($fields['aliases'][0]));
        $this->assertSame(1024, mb_strlen($fields['public_uris'][0]));
        $this->assertSame(500, mb_strlen($fields['observations'][0]));
    }

    public function test_sanitizer_enforces_byte_cap_after_multibyte_normalization(): void
    {
        $profile = new UnitSharedPublicProfile(
            str_repeat('я', 255),
            array_fill(0, 20, str_repeat('я', 255)),
            array_fill(0, 20, str_repeat('я', 255)),
            array_fill(0, 20, str_repeat('я', 255)),
            array_fill(0, 10, str_repeat('я', 1024)),
            array_fill(0, 25, str_repeat('я', 500)),
        );
        $context = new AiDisclosureContext(
            1,
            2,
            BusinessLane::Sales,
            UnitRoleCode::Customer,
            AiAudience::Customer,
            AiPurpose::ProductMatching,
            true,
        );

        try {
            app(AiContextSanitizer::class)->sanitize($profile, $context);
            $this->fail('Expected Safe DTO byte limit to block the payload.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('safe_dto_byte_limit', $violation->errorCode);
        }
    }

    public function test_sanitizer_masks_entity_registry_identifier(): void
    {
        $dto = new SanitizedEntityLegalSummary('Entity', 'company', 'RU', '1234567890');
        $context = new AiDisclosureContext(
            1,
            2,
            BusinessLane::Sales,
            UnitRoleCode::Customer,
            AiAudience::Customer,
            AiPurpose::UnitResearch,
            true,
        );

        $sanitized = app(AiContextSanitizer::class)->sanitize($dto, $context);

        $this->assertSame('12******90', $sanitized['registry_identifier_masked']);
        $this->assertStringNotContainsString('1234567890', json_encode($sanitized));
    }

    private function dtos(): array
    {
        return [
            new PublicGoodSummary('Good', 'Description', ['form' => 'powder']),
            new CustomerOfferSummary('Good', '100.00', 'RUB', 'kg', '2026-09-01'),
            new UnitSharedPublicProfile('Unit', ['Alias'], ['Industry'], ['City'], ['https://example.test'], ['Fact']),
            new UnitBusinessContextSummary('unit:1:context:2', 'sales', 'customer', 'active', 'active'),
            new SanitizedEntityLegalSummary('Entity', 'company', 'RU', '**1234'),
            new AggregateDemandSummary('Good', '2026-Q3', '10–20 t', 2, 10),
            new AggregateSupplySummary('Good', 'Moscow', '10–20 t', 4, '2026-Q3'),
            new PublicBusinessContactSummary('uri', 'https://example.test', 'Public site', true),
        ];
    }

    private function assertSafeValues(array $values): void
    {
        foreach ($values as $value) {
            $this->assertTrue($value === null || is_scalar($value) || is_array($value));

            if (is_array($value)) {
                $this->assertSafeValues($value);
            }
        }
    }
}
