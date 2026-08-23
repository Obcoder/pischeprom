<?php

namespace Tests\Unit\AiSales;

use App\Domain\AiSales\Contracts\AiProviderInterface;
use App\Domain\AiSales\Contracts\TimewebAiProviderInterface;
use App\Domain\AiSales\DTO\Providers\AiProviderInputItem;
use App\Domain\AiSales\DTO\Providers\AiProviderRequest;
use App\Domain\AiSales\DTO\Providers\AiRequestRequirements;
use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiProviderErrorCategory;
use App\Domain\AiSales\Enums\AiProviderResponseStatus;
use App\Domain\AiSales\Enums\FakeAiProviderScenario;
use App\Domain\AiSales\Providers\AiProviderRegistry;
use App\Infrastructure\AiSales\Providers\FakeExternalSanitizedAiProvider;
use App\Infrastructure\AiSales\Providers\FakeLocalRuAiProvider;
use Illuminate\Support\Facades\Http;
use LogicException;
use Mockery;
use Tests\TestCase;

class FakeAiProviderContractTest extends TestCase
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

    public function test_fake_providers_cover_normal_structured_tool_and_normalized_failure_scenarios(): void
    {
        $normal = (new FakeExternalSanitizedAiProvider)->createResponse($this->request());
        $structured = (new FakeExternalSanitizedAiProvider(FakeAiProviderScenario::StructuredOutput))
            ->createResponse($this->request());
        $tool = (new FakeExternalSanitizedAiProvider(FakeAiProviderScenario::FunctionCall))
            ->createResponse($this->request());

        $this->assertSame(AiProviderResponseStatus::Completed, $normal->status);
        $this->assertSame('structured', $normal->outputItems[0]->type);
        $this->assertSame(AiProviderResponseStatus::Completed, $structured->status);
        $this->assertSame(AiProviderResponseStatus::RequiresAction, $tool->status);
        $this->assertCount(1, $tool->toolCalls);
        $this->assertSame(1, $tool->usage->toolCallCount);

        $expected = [
            FakeAiProviderScenario::Timeout->value => AiProviderErrorCategory::Timeout,
            FakeAiProviderScenario::RateLimited->value => AiProviderErrorCategory::RateLimited,
            FakeAiProviderScenario::ServerError->value => AiProviderErrorCategory::ServerError,
            FakeAiProviderScenario::SchemaMismatch->value => AiProviderErrorCategory::SchemaMismatch,
            FakeAiProviderScenario::DlpBlock->value => AiProviderErrorCategory::DlpBlocked,
            FakeAiProviderScenario::ContourBlock->value => AiProviderErrorCategory::ContourBlocked,
            FakeAiProviderScenario::ProviderUnavailable->value => AiProviderErrorCategory::ProviderUnavailable,
        ];

        foreach ($expected as $scenario => $category) {
            $response = (new FakeExternalSanitizedAiProvider(FakeAiProviderScenario::from($scenario)))
                ->createResponse($this->request());
            $this->assertSame(AiProviderResponseStatus::Failed, $response->status, $scenario);
            $this->assertSame($category, $response->error?->category, $scenario);
            $this->assertSame(0, $response->usage->totalTokens(), $scenario);
        }
    }

    public function test_external_fake_rejects_local_only_payload_and_request_is_store_false(): void
    {
        $request = $this->request(containsLocalOnlyData: true);
        $response = (new FakeExternalSanitizedAiProvider)->createResponse($request);

        $this->assertFalse($request->store());
        $this->assertSame(AiProviderResponseStatus::Failed, $response->status);
        $this->assertSame(AiProviderErrorCategory::ContourBlocked, $response->error?->category);
        $this->assertSame('fake_external_local_only_rejected', $response->error?->safeCode);

        $localResponse = (new FakeLocalRuAiProvider)->createResponse($this->request(
            contour: AiProcessingContour::LocalRu,
            containsLocalOnlyData: true,
        ));
        $this->assertSame(AiProviderResponseStatus::Completed, $localResponse->status);
        $this->assertSame('local_ru', $localResponse->providerRoute);
    }

    public function test_stage04_registry_rejects_any_non_fake_provider_contract(): void
    {
        $registry = new AiProviderRegistry;
        $provider = Mockery::mock(AiProviderInterface::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('approved fake or Timeweb AI provider contracts');
        $registry->register($provider);
    }

    public function test_timeweb_marker_requires_explicit_synthetic_only_registry_mode(): void
    {
        $provider = Mockery::mock(TimewebAiProviderInterface::class);
        config()->set('ai-sales.transport_mode', 'fake_only');

        try {
            (new AiProviderRegistry)->register($provider);
            $this->fail('Timeweb provider must be disabled in fake-only mode.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('synthetic-only transport mode', $exception->getMessage());
        }

        config()->set('ai-sales.transport_mode', 'timeweb_synthetic_only');
        $provider->shouldReceive('code')->andReturn('timeweb');
        $provider->shouldReceive('route')->andReturn(\App\Domain\AiSales\Enums\AiProviderRoute::ExternalSanitized);
        $registry = new AiProviderRegistry;
        $registry->register($provider);

        $this->assertSame([$provider], $registry->all());
    }

    private function request(
        AiProcessingContour $contour = AiProcessingContour::ExternalSanitized,
        bool $containsLocalOnlyData = false,
    ): AiProviderRequest {
        $requiresStoreFalse = $contour === AiProcessingContour::ExternalSanitized;

        return new AiProviderRequest(
            '00000000-0000-4000-8000-000000000001',
            1,
            $contour,
            AiModelProfile::StandardResearch,
            [new AiProviderInputItem('sanitized_data', 'synthetic', ['name' => 'Public Unit'])],
            [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['summary'],
                'properties' => ['summary' => ['type' => 'string']],
            ],
            [],
            new AiRequestRequirements(['chat_completions'], 100, 100, $requiresStoreFalse),
            hash('sha256', 'idempotency'),
            hash('sha256', 'policy'),
            hash('sha256', 'prompt'),
            hash('sha256', 'schema'),
            hash('sha256', 'payload'),
            ['public' => 1],
            $containsLocalOnlyData,
            5,
        );
    }
}
