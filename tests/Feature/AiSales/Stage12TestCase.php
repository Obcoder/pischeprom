<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Models\Email;
use App\Models\Product;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\UnitContactContextLink;
use App\Models\UnitProductMatch;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

abstract class Stage12TestCase extends Stage11TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        config()->set([
            'ai-sales.enabled' => true,
            'ai-sales.outreach_drafting_enabled' => true,
            'ai-sales.outreach_sending_enabled' => false,
            'ai-sales.outreach.ui_enabled' => true,
            'ai-sales.outreach.drafts_enabled' => true,
            'ai-sales.outreach.fake_generation_enabled' => true,
            'ai-sales.outreach.permission_ledger_enabled' => true,
            'ai-sales.outreach.suppression_management_enabled' => true,
            'ai-sales.outreach.dispatch_enabled' => false,
            'ai-sales.outreach.live_generation_enabled' => false,
            'ai-sales.outreach.auto_send_enabled' => false,
            'ai-sales.outreach.transport_mode' => 'fake_only',
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
        ]);
    }

    protected function outreachUser(array $extra = []): User
    {
        return $this->userWith(array_values(array_unique([
            'ai_sales.view', 'ai_sales.sales.view', 'ai_sales.outreach.view', 'ai_sales.outreach.draft',
            'ai_sales.outreach.review', 'ai_sales.outreach.claims.review',
            'ai_sales.communication_permissions.view', 'ai_sales.communication_permissions.manage',
            'ai_sales.communication_suppressions.manage', ...$extra,
        ])));
    }

    /** @return array{User, Unit, UnitBusinessContext, Product, UnitProductMatch, Email, UnitContactContextLink} */
    protected function outreachFixture(?User $actor = null, ?Unit $unit = null, string $lane = 'sales'): array
    {
        $actor ??= $this->outreachUser($lane === 'procurement' ? ['ai_sales.procurement.view'] : []);
        $unit ??= $this->unit(['name' => 'Stage 12 Unit '.uniqid()]);
        $context = UnitBusinessContext::query()->create([
            'unit_id' => $unit->id, 'lane' => $lane,
            'role_code' => $lane === 'sales' ? 'prospective_customer' : 'prospective_supplier',
            'stage' => 'qualified', 'status' => 'active', 'source' => 'stage12-test', 'created_by' => $actor->id,
        ]);
        $product = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Stage 12 Product '.uniqid(), 'eng' => 'Stage 12 Product', 'is_published' => true,
        ]);
        $reference = 'fixture:stage12:product:'.uniqid();
        $match = UnitProductMatch::query()->create([
            'unit_id' => $unit->id, 'unit_business_context_id' => $context->id, 'product_id' => $product->id,
            'match_type' => $lane === 'sales' ? 'potential_need' : 'potential_offer',
            'status' => 'approved', 'origin' => 'manual', 'evidence_confidence' => 100,
            'safe_rationale' => 'Repository-owned fictional public relevance.',
            'evidence_reference' => $reference, 'evidence_hash' => hash('sha256', $reference),
            'created_by' => $actor->id, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'stale_after' => now()->addDays(30),
        ]);
        $email = Email::query()->create([
            'address' => 'stage12-'.uniqid().'@example.test', 'source' => 'synthetic_fixture', 'is_active' => true,
        ]);
        $unit->emails()->syncWithoutDetaching([$email->id]);
        $contact = UnitContactContextLink::query()->create([
            'unit_id' => $unit->id, 'unit_business_context_id' => $context->id,
            'channel_type' => 'email', 'email_id' => $email->id,
            'channel_value_snapshot' => 'synthetic-corporate-email', 'normalized_hash' => hash('sha256', $email->address),
            'contact_role' => 'business_general', 'verification_status' => ObservationVerificationStatus::Verified,
            'data_classification' => DataClassification::PersonalData, 'visibility_scope' => UnitVisibilityScope::SalesLane,
            'communication_state' => 'review_required', 'review_required' => true,
            'last_verified_at' => now(), 'created_by' => $actor->id,
        ]);

        return [$actor, $unit, $context, $product, $match, $email, $contact];
    }

    protected function draftPayload(UnitBusinessContext $context, UnitProductMatch $match, UnitContactContextLink $contact): array
    {
        return [
            'unit_business_context_id' => $context->id,
            'unit_contact_context_link_id' => $contact->id,
            'unit_product_match_id' => $match->id,
            'purpose' => 'advertising_outreach',
        ];
    }
}
