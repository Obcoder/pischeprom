<?php

namespace Tests\Feature\AiPriceLists;

use App\Domain\AiPriceLists\Contracts\StructuredTextModelProviderInterface;
use App\Domain\AiPriceLists\Enums\MatchClass;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Enums\SourceChannel;
use App\Domain\AiPriceLists\Exceptions\InvalidPriceListTransition;
use App\Domain\AiPriceLists\Matching\CandidateSelectionValidator;
use App\Domain\AiPriceLists\Matching\PriceListCandidateReranker;
use App\Domain\AiPriceLists\Providers\FakeStructuredTextModelProvider;
use App\Domain\AiPriceLists\Services\PriceListStateMachine;
use App\Models\Good;
use App\Models\PriceListImport;
use App\Models\PriceListImportItem;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StateAndCandidateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_state_machine_allows_pipeline_transition_and_rejects_shortcut_to_applied(): void
    {
        $import = $this->import(PriceListStatus::Queued);
        $states = app(PriceListStateMachine::class);
        $import = $states->transition($import, PriceListStatus::Validating, progress: 8);

        $this->assertSame(PriceListStatus::Validating, $import->status);
        $this->assertDatabaseHas('price_list_events', [
            'price_list_import_id' => $import->id,
            'status_from' => 'queued',
            'status_to' => 'validating',
        ]);

        $this->expectException(InvalidPriceListTransition::class);
        $states->transition($import, PriceListStatus::Applied);
    }

    public function test_ai_candidate_id_must_belong_to_the_persisted_candidate_set(): void
    {
        $import = $this->import(PriceListStatus::ReviewRequired);
        $allowed = Good::query()->create(['name' => 'Разрешённый товар', 'is_published' => true]);
        $foreign = Good::query()->create(['name' => 'Посторонний товар', 'is_published' => true]);
        $item = PriceListImportItem::query()->create([
            'price_list_import_id' => $import->id,
            'position' => 1,
            'raw_name' => 'Товар',
            'normalized_name' => 'товар',
            'row_fingerprint' => hash('sha256', 'row-1'),
        ]);
        $item->candidates()->create([
            'good_id' => $allowed->id,
            'rank' => 1,
            'method' => 'token_similarity',
            'score' => '0.8000',
        ]);
        $validator = app(CandidateSelectionValidator::class);

        $this->assertSame($allowed->id, $validator->validateAiSelection($item, $allowed->id));
        $this->expectException(DomainException::class);
        $validator->validateAiSelection($item, $foreign->id);
    }

    public function test_ai_reranking_only_reorders_supplied_candidates_and_never_confirms_item(): void
    {
        config()->set([
            'ai-price-lists.ai.enabled' => true,
            'ai-price-lists.matching.ai_reranking_enabled' => true,
            'ai-price-lists.ai.daily_token_limit' => 1000,
            'ai-price-lists.ai.monthly_token_limit' => 1000,
        ]);
        $import = $this->import(PriceListStatus::Matching);
        $first = Good::query()->create(['name' => 'Мука первый кандидат', 'is_published' => true]);
        $second = Good::query()->create(['name' => 'Мука точная фасовка 20 кг', 'is_published' => true]);
        $item = PriceListImportItem::query()->create([
            'price_list_import_id' => $import->id,
            'position' => 1,
            'raw_name' => 'Мука 20 кг',
            'normalized_name' => 'мука 20 кг',
            'match_class' => MatchClass::Probable,
            'row_fingerprint' => hash('sha256', 'rerank-row'),
        ]);
        $item->candidates()->createMany([
            ['good_id' => $first->id, 'rank' => 1, 'method' => 'token_similarity', 'score' => '0.7000'],
            ['good_id' => $second->id, 'rank' => 2, 'method' => 'token_similarity', 'score' => '0.6500'],
        ]);
        $this->app->instance(StructuredTextModelProviderInterface::class, new FakeStructuredTextModelProvider([
            'selections' => [[
                'item_id' => $item->id,
                'good_id' => $second->id,
                'confidence' => 1.0,
                'reason' => 'Совпадает фасовка.',
            ]],
        ]));

        $stats = app(PriceListCandidateReranker::class)->rerank($import);

        $this->assertSame(1, $stats['reranked']);
        $this->assertSame($second->id, $item->candidates()->orderBy('rank')->value('good_id'));
        $this->assertNull($item->fresh()->good_id);
        $this->assertSame('unreviewed', $item->fresh()->decision_status->value);
        $this->assertSame('deterministic+ai_rerank', $item->fresh()->match_method);
        $this->assertDatabaseHas('ai_usage_records', [
            'price_list_import_id' => $import->id,
            'operation' => 'candidate_reranking',
            'status' => 'success',
        ]);
    }

    private function import(PriceListStatus $status): PriceListImport
    {
        return PriceListImport::query()->create([
            'source_key' => 'test:'.uniqid('', true),
            'source_channel' => SourceChannel::Email,
            'status' => $status,
            'progress' => 0,
            'disk' => 'local',
            'path' => 'test.csv',
            'original_name' => 'test.csv',
            'safe_name' => 'test.csv',
            'size_bytes' => 10,
        ]);
    }
}
