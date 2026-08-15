<?php

namespace Tests\Unit\AiSales;

use App\Domain\AiSales\DTO\Providers\AiResidencyVerification;
use App\Domain\AiSales\DTO\SafeAiDto;
use App\Domain\AiSales\DTO\Units\CustomerOfferSummary;
use App\Domain\AiSales\DTO\Units\PublicBusinessContactSummary;
use App\Domain\AiSales\DTO\Units\UnitSharedPublicProfile;
use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiProcessingDecision;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\AiResidencyVerificationStatus;
use App\Domain\AiSales\Enums\AiTaskProfile;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Policies\AiDisclosureContext;
use App\Domain\AiSales\Policies\AiProcessingContourPolicy;
use App\Domain\AiSales\Services\DeterministicAiPayloadScanner;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessingContourPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        parent::tearDown();
    }

    public function test_public_safe_dto_is_allowed_external_but_contour_override_is_blocked(): void
    {
        $dto = new UnitSharedPublicProfile('Public Unit', ['Alias'], ['Food'], ['Moscow'], ['https://example.test']);
        $context = $this->context(BusinessLane::Sales, UnitRoleCode::Customer, external: true);
        $policy = app(AiProcessingContourPolicy::class);

        $allowed = $policy->decide(
            $dto,
            $context,
            AiTaskProfile::PublicCompanyResearch,
            AiProcessingContour::ExternalSanitized,
        );
        $override = $policy->decide(
            $dto,
            $context,
            AiTaskProfile::PublicCompanyResearch,
            AiProcessingContour::LocalRu,
            $this->residency(),
        );

        $this->assertSame(AiProcessingDecision::Allow, $allowed->decision);
        $this->assertSame(AiProcessingContour::ExternalSanitized, $allowed->selectedContour);
        $this->assertTrue($allowed->permitsProviderSelection());
        $this->assertSame(AiProcessingDecision::Block, $override->decision);
        $this->assertSame(AiProcessingContour::None, $override->selectedContour);
        $this->assertSame('contour_override_blocked', $override->reasonCode);
    }

    public function test_unclassified_personal_and_secret_material_fail_closed(): void
    {
        $unclassified = new class implements SafeAiDto
        {
            public function fields(): array
            {
                return ['unregistered' => 'value'];
            }

            public function maxBytes(): int
            {
                return 1024;
            }
        };
        $policy = app(AiProcessingContourPolicy::class);
        $context = $this->context(BusinessLane::Sales, UnitRoleCode::Customer, external: true);

        $unknown = $policy->decide(
            $unclassified,
            $context,
            AiTaskProfile::PublicCompanyResearch,
            AiProcessingContour::ExternalSanitized,
        );
        $personal = $policy->decide(
            new PublicBusinessContactSummary('email', 'person@example.test', 'public site', true),
            new AiDisclosureContext(
                10,
                20,
                BusinessLane::Sales,
                UnitRoleCode::Customer,
                AiAudience::Internal,
                AiPurpose::ContactDiscovery,
                true,
            ),
            AiTaskProfile::PublicCompanyResearch,
            AiProcessingContour::ExternalSanitized,
        );
        $scanner = app(DeterministicAiPayloadScanner::class);
        $secretPayload = [
            '.env' => 'blocked',
            'nested' => ['authorization' => 'Bearer abcdefghijklmnopqrstuvwxyz'],
        ];
        $localSecretScan = $scanner->scan($secretPayload, AiProcessingContour::LocalRu);
        $externalSecretScan = $scanner->scan($secretPayload, AiProcessingContour::ExternalSanitized);

        $this->assertSame('unclassified_field', $unknown->reasonCode);
        $this->assertSame(AiProcessingContour::None, $unknown->selectedContour);
        $this->assertSame('local_only_data_external_blocked', $personal->reasonCode);
        $this->assertSame(AiProcessingContour::None, $personal->selectedContour);
        $this->assertTrue($localSecretScan->blocked());
        $this->assertTrue($externalSecretScan->blocked());
        $this->assertGreaterThanOrEqual(1, $localSecretScan->secretCount);
        $this->assertSame($localSecretScan->secretCount, $externalSecretScan->secretCount);
    }

    public function test_local_ru_is_not_blanket_access_and_stale_residency_requires_review(): void
    {
        $policy = app(AiProcessingContourPolicy::class);
        $procurement = new AiDisclosureContext(
            10,
            30,
            BusinessLane::Procurement,
            UnitRoleCode::Supplier,
            AiAudience::Supplier,
            AiPurpose::ProductMatching,
            false,
        );
        $crossLane = $policy->decide(
            new CustomerOfferSummary('Sugar', '100.00', 'RUB'),
            $procurement,
            AiTaskProfile::InternalDossierSummary,
            AiProcessingContour::LocalRu,
            $this->residency(),
        );
        $stale = new AiResidencyVerification(
            'fake',
            'local_ru',
            'fake-local-ru-v1',
            AiProcessingContour::LocalRu,
            'RU',
            AiResidencyVerificationStatus::Verified,
            1,
            now()->subDay(),
            now()->subMinute(),
            'test',
        );
        $residencyDecision = $policy->decide(
            new UnitSharedPublicProfile('Local Unit'),
            $this->context(BusinessLane::Sales, UnitRoleCode::Customer, external: false),
            AiTaskProfile::InternalDossierSummary,
            AiProcessingContour::LocalRu,
            $stale,
        );

        $this->assertSame(AiProcessingDecision::Block, $crossLane->decision);
        $this->assertNotSame('allowed', $crossLane->reasonCode);
        $this->assertSame(AiProcessingDecision::RequireReview, $residencyDecision->decision);
        $this->assertTrue($residencyDecision->requiresHumanReview);
        $this->assertFalse($residencyDecision->permitsProviderSelection());
    }

    public function test_decision_hash_is_deterministic_and_binds_lane_context_and_policy_versions(): void
    {
        $policy = app(AiProcessingContourPolicy::class);
        $dto = new UnitSharedPublicProfile('Deterministic Unit');
        $sales = $policy->decide(
            $dto,
            $this->context(BusinessLane::Sales, UnitRoleCode::Customer, external: true),
            AiTaskProfile::PublicCompanyResearch,
            AiProcessingContour::ExternalSanitized,
        );
        $same = $policy->decide(
            $dto,
            $this->context(BusinessLane::Sales, UnitRoleCode::Customer, external: true),
            AiTaskProfile::PublicCompanyResearch,
            AiProcessingContour::ExternalSanitized,
        );
        $procurement = $policy->decide(
            $dto,
            $this->context(BusinessLane::Procurement, UnitRoleCode::Supplier, external: true),
            AiTaskProfile::PublicCompanyResearch,
            AiProcessingContour::ExternalSanitized,
        );

        $this->assertSame($sales->decisionHash, $same->decisionHash);
        $this->assertNotSame($sales->decisionHash, $procurement->decisionHash);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $sales->decisionHash);
    }

    private function context(BusinessLane $lane, UnitRoleCode $role, bool $external): AiDisclosureContext
    {
        return new AiDisclosureContext(
            10,
            20,
            $lane,
            $role,
            AiAudience::Internal,
            AiPurpose::UnitResearch,
            $external,
        );
    }

    private function residency(): AiResidencyVerification
    {
        return new AiResidencyVerification(
            'fake',
            'local_ru',
            'fake-local-ru-v1',
            AiProcessingContour::LocalRu,
            'RU',
            AiResidencyVerificationStatus::Verified,
            1,
            now(),
            now()->addDay(),
            'test',
        );
    }
}
