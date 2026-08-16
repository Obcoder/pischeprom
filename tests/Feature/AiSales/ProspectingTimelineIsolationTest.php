<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Enums\UnitGoodMatchType;
use App\Domain\AiSales\Enums\UnitProductMatchType;
use App\Domain\AiSales\Services\UnitGoodMatchService;
use App\Domain\AiSales\Services\UnitProductMatchService;
use App\Models\Email;
use App\Models\Entity;
use App\Models\Good;
use App\Models\MailMessage;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\UnitBusinessContext;
use App\Models\UnitContactContextLink;
use App\Models\UnitSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProspectingTimelineIsolationTest extends Stage08TestCase
{
    public function test_dual_role_unit_requires_explicit_context_and_projects_distinct_lane_transactions(): void
    {
        $actor = $this->prospectingUser(['sales', 'procurement'], ['ai_sales.classifications.view_internal']);
        $unit = $this->unit(['name' => 'Dual lane timeline']);
        $sales = $this->createContext($actor, $unit, ['lane' => 'sales', 'role_code' => 'prospective_customer']);
        $procurement = $this->createContext($actor, $unit, ['lane' => 'procurement', 'role_code' => 'prospective_supplier']);
        $entity = Entity::query()->create(['name' => 'Synthetic linked Entity']);
        $unit->entities()->syncWithoutDetaching([$entity->id]);
        Sale::query()->create(['date' => now()->toDateString(), 'entity_id' => $entity->id, 'total' => 100]);
        Purchase::query()->create(['date' => now()->toDateString(), 'entity_id' => $entity->id, 'amount' => 50]);
        UnitSource::query()->create([
            'unit_id' => $unit->id,
            'unit_business_context_id' => $sales['id'],
            'source_key' => hash('sha256', 'synthetic:restricted-timeline-marker-stage08'),
            'source_type' => 'synthetic_restricted',
            'source_label' => 'restricted-timeline-marker-stage08',
            'source_reference' => 'synthetic:restricted',
            'data_classification' => 'commercial_confidential',
            'visibility_scope' => 'sales_lane',
            'observed_at' => now(),
            'created_by_type' => 'human',
            'created_by_user_id' => $actor->id,
        ]);
        $email = Email::query()->create(['address' => 'timeline@stage08.example', 'is_active' => true]);
        $unit->emails()->syncWithoutDetaching([$email->id]);
        UnitContactContextLink::query()->create([
            'unit_id' => $unit->id,
            'unit_business_context_id' => $sales['id'],
            'channel_type' => 'email',
            'email_id' => $email->id,
            'channel_value_snapshot' => 'ti***@stage08.example',
            'normalized_hash' => hash('sha256', 'email|timeline@stage08.example'),
            'contact_role' => 'business_general',
            'verification_status' => 'verified',
            'data_classification' => 'public',
            'visibility_scope' => 'sales_lane',
            'communication_state' => 'review_required',
            'review_required' => true,
        ]);
        $message = MailMessage::query()->create([
            'mailbox' => 'synthetic@stage08.example',
            'folder' => 'INBOX',
            'direction' => 'incoming',
            'message_date' => now(),
            'subject' => 'raw-mail-subject-marker-stage08',
            'raw_headers' => 'raw-mail-header-marker-stage08',
        ]);
        $message->emails()->attach($email->id, ['role' => 'from']);
        DB::table('mail_message_attachments')->insert([
            'mail_message_id' => $message->id,
            'disk' => 'synthetic',
            'path' => 'private-file-path-marker-stage08',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($actor)->getJson("/api/ai-sales/units/{$unit->id}/prospecting-dossier")
            ->assertUnprocessable()->assertJsonValidationErrors('context_id');
        $salesPayload = $this->actingAs($actor)->getJson("/api/ai-sales/units/{$unit->id}/prospecting-dossier?context_id={$sales['id']}")
            ->assertOk()->assertJsonPath('data.context.lane', 'sales')
            ->assertJsonPath('data.transaction_count', 1)
            ->assertJsonPath('data.dual_role_warning', true)
            ->json('data');
        $procurementPayload = $this->actingAs($actor)->getJson("/api/ai-sales/units/{$unit->id}/prospecting-dossier?context_id={$procurement['id']}")
            ->assertOk()->assertJsonPath('data.context.lane', 'procurement')
            ->assertJsonPath('data.transaction_count', 1)
            ->json('data');
        $this->assertCount(1, $salesPayload['linked_entities']);
        $this->assertSame(['message_count' => 1, 'attachment_count' => 1, 'raw_content_included' => false], $salesPayload['communications']);
        $this->assertSame([], $salesPayload['ai_runs']);
        $this->assertSame([], $salesPayload['tool_calls']);
        $this->assertStringNotContainsString('raw_body', json_encode($salesPayload, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('raw-mail-subject-marker-stage08', json_encode($salesPayload, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('raw-mail-header-marker-stage08', json_encode($salesPayload, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('private-file-path-marker-stage08', json_encode($salesPayload, JSON_THROW_ON_ERROR));
        $this->assertSame('explicit_context_required', $salesPayload['lane_isolation']);
        $this->assertSame('explicit_context_required', $procurementPayload['lane_isolation']);
        $this->assertTrue($salesPayload['timeline']['meta']['projection_only']);
        $salesOnly = $this->prospectingUser(['sales']);
        $this->actingAs($salesOnly)->getJson("/api/ai-sales/units/{$unit->id}/prospecting-dossier?context_id={$procurement['id']}")->assertForbidden();
        $salesOnlyPayload = $this->actingAs($salesOnly)
            ->getJson("/api/ai-sales/units/{$unit->id}/prospecting-dossier?context_id={$sales['id']}")
            ->assertOk()
            ->json('data');
        $this->assertStringNotContainsString('restricted-timeline-marker-stage08', json_encode($salesOnlyPayload['timeline'], JSON_THROW_ON_ERROR));
        $restrictedSales = $this->prospectingUser(['sales'], ['ai_sales.classifications.view_internal']);
        $this->actingAs($restrictedSales)
            ->getJson("/api/ai-sales/units/{$unit->id}/prospecting-dossier?context_id={$sales['id']}")
            ->assertOk()
            ->assertJsonCount(0, 'data.linked_entities');
    }

    public function test_good_match_is_context_bound_directional_and_opposite_lane_rationale_is_blocked(): void
    {
        $actor = $this->prospectingUser(['sales', 'procurement']);
        $unit = $this->unit();
        $sales = UnitBusinessContext::query()->findOrFail($this->createContext($actor, $unit, ['lane' => 'sales', 'role_code' => 'prospective_customer'])['id']);
        $product = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Синтетический продукт timeline',
            'is_published' => true,
        ]);
        $good = Good::query()->create(['name' => 'Synthetic timeline Good', 'is_published' => true]);
        $good->products()->attach($product->id);
        $productMatch = app(UnitProductMatchService::class)->suggest($unit, $sales, [
            'product_id' => $product->id,
            'match_type' => UnitProductMatchType::PotentialNeed,
            'safe_rationale' => 'Public synthetic Product need signal.',
            'origin' => 'rule',
        ], $actor);
        $service = app(UnitGoodMatchService::class);
        $match = $service->suggest($unit, $sales, [
            'unit_product_match_id' => $productMatch->id,
            'good_id' => $good->id,
            'match_type' => UnitGoodMatchType::PotentialNeed,
            'fit_confidence' => 75,
            'safe_rationale' => 'Public synthetic need signal.',
            'origin' => 'rule',
        ], $actor);
        $this->assertSame($sales->id, $match->unit_business_context_id);
        $this->expectException(ValidationException::class);
        $service->suggest($unit, $sales, [
            'unit_product_match_id' => $productMatch->id,
            'good_id' => $good->id,
            'match_type' => UnitGoodMatchType::PotentialOffer,
            'fit_confidence' => 75,
            'safe_rationale' => 'supplier_secret must not cross lanes',
        ], $actor);
    }
}
