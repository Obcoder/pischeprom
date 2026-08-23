<?php

namespace Tests\Unit\AiSales;

use App\Domain\AiSales\DTO\SafeAiDto;
use App\Domain\AiSales\DTO\Units\AggregateDemandSummary;
use App\Domain\AiSales\DTO\Units\CustomerOfferSummary;
use App\Domain\AiSales\DTO\Units\PublicBusinessContactSummary;
use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Policies\AiDataClassificationRegistry;
use App\Domain\AiSales\Policies\AiDisclosureContext;
use App\Domain\AiSales\Services\AiContextSanitizer;
use App\Domain\AiSales\Services\AiFieldAuthorizationService;
use Tests\TestCase;

class DisclosurePolicyTest extends TestCase
{
    public function test_unclassified_and_secret_fields_fail_closed(): void
    {
        $authorization = app(AiFieldAuthorizationService::class);
        $context = $this->salesContext(external: false, audience: AiAudience::Internal);

        $unknown = $authorization->decide('unknown_subject', 'unknown_field', $context);
        $secret = $authorization->decide('security.credentials', 'token', $context);
        $environment = $authorization->decide('security.credentials', '.env', $context);

        $this->assertFalse($unknown->allowed);
        $this->assertSame('unclassified_field', $unknown->code);
        $this->assertFalse($secret->allowed);
        $this->assertSame('secret_blocked', $secret->code);
        $this->assertFalse($environment->allowed);
        $this->assertSame('secret_blocked', $environment->code);
    }

    public function test_personal_data_and_raw_correspondence_are_not_exported(): void
    {
        $sanitizer = app(AiContextSanitizer::class);
        $contact = new PublicBusinessContactSummary('email', 'person@example.test', 'Public website', true);

        $this->expectPolicyViolation(
            fn () => $sanitizer->sanitize($contact, $this->salesContext(purpose: AiPurpose::ContactDiscovery)),
            'personal_data_external_blocked',
        );

        $decision = app(AiFieldAuthorizationService::class)->decide(
            'raw_correspondence',
            'body',
            $this->salesContext(external: false, audience: AiAudience::Internal),
        );
        $this->assertFalse($decision->allowed);
    }

    public function test_customer_and_supplier_compartments_cannot_cross_disclose(): void
    {
        $sanitizer = app(AiContextSanitizer::class);
        $supplierDemand = new AggregateDemandSummary('Сахар', '2026-Q3', '10–20 т', 2, 10);
        $customerOffer = new CustomerOfferSummary('Сахар', '100.00', 'RUB');

        $this->expectPolicyViolation(
            fn () => $sanitizer->sanitize($supplierDemand, $this->salesContext()),
            'audience_not_allowed',
        );
        $this->expectPolicyViolation(
            fn () => $sanitizer->sanitize($customerOffer, $this->procurementContext()),
            'audience_not_allowed',
        );
    }

    public function test_context_identity_role_lane_audience_and_purpose_are_mandatory(): void
    {
        $authorization = app(AiFieldAuthorizationService::class);
        $registry = app(AiDataClassificationRegistry::class);
        $subject = CustomerOfferSummary::class;
        $field = $registry->find($subject, 'price');
        $this->assertNotNull($field);

        $missingIdentity = new AiDisclosureContext(
            0,
            0,
            BusinessLane::Sales,
            UnitRoleCode::Customer,
            AiAudience::Customer,
            AiPurpose::ProductMatching,
            true,
        );
        $mismatched = new AiDisclosureContext(
            1,
            2,
            BusinessLane::Procurement,
            UnitRoleCode::Customer,
            AiAudience::Customer,
            AiPurpose::ProductMatching,
            true,
        );

        $this->assertSame('context_identity_required', $authorization->decide($subject, 'price', $missingIdentity)->code);
        $this->assertSame('role_lane_mismatch', $authorization->decide($subject, 'price', $mismatched)->code);
    }

    public function test_sanitizer_rejects_an_unregistered_field_even_from_a_safe_dto_contract(): void
    {
        $dto = new class implements SafeAiDto
        {
            public function fields(): array
            {
                return ['surprise_relation' => ['secret' => 'must not pass']];
            }

            public function maxBytes(): int
            {
                return 1024;
            }
        };

        $this->expectPolicyViolation(
            fn () => app(AiContextSanitizer::class)->sanitize($dto, $this->salesContext()),
            'unclassified_field',
        );
    }

    private function salesContext(
        bool $external = true,
        AiAudience $audience = AiAudience::Customer,
        AiPurpose $purpose = AiPurpose::ProductMatching,
    ): AiDisclosureContext {
        return new AiDisclosureContext(
            10,
            20,
            BusinessLane::Sales,
            UnitRoleCode::Customer,
            $audience,
            $purpose,
            $external,
        );
    }

    private function procurementContext(): AiDisclosureContext
    {
        return new AiDisclosureContext(
            10,
            30,
            BusinessLane::Procurement,
            UnitRoleCode::Supplier,
            AiAudience::Supplier,
            AiPurpose::ProductMatching,
            true,
        );
    }

    private function expectPolicyViolation(callable $operation, string $code): void
    {
        try {
            $operation();
            $this->fail("Expected policy violation {$code}.");
        } catch (PolicyViolation $violation) {
            $this->assertSame($code, $violation->errorCode);
        }
    }
}
