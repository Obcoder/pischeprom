<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Outreach\Enums\OutreachReviewType;
use App\Models\CommunicationPermission;
use App\Models\OutreachDraft;
use App\Models\OutreachDraftReview;
use App\Models\UnitContactContextLink;

class Stage12PermissionAndSuppressionTest extends Stage12TestCase
{
    public function test_public_email_is_not_permission_and_suppression_always_wins(): void
    {
        [$actor, $unit, $context, $product, $match, , $contact] = $this->outreachFixture();
        $draftId = $this->actingAs($actor)->postJson("/api/ai-sales/units/{$unit->id}/outreach/drafts", $this->draftPayload($context, $match, $contact))->assertCreated()->json('data.id');
        $revisionId = $this->postJson("/api/ai-sales/units/{$unit->id}/outreach/drafts/{$draftId}/generate")->assertCreated()->json('data.id');

        $unknown = $this->postJson("/api/ai-sales/units/{$unit->id}/outreach/drafts/{$draftId}/eligibility-preview")
            ->assertOk()->json('data');
        $this->assertFalse($unknown['eligible']);
        $this->assertContains('scoped_permission_missing', $unknown['block_reasons']);

        $permissionId = $this->postJson("/api/ai-sales/units/{$unit->id}/outreach/permissions", [
            'unit_business_context_id' => $context->id, 'unit_contact_context_link_id' => $contact->id,
            'purpose' => 'advertising_outreach', 'product_id' => $product->id,
            'valid_from' => now()->subMinute()->toISOString(), 'valid_until' => now()->addDays(7)->toISOString(),
            'evidence' => [[
                'type' => 'written_response', 'reference' => 'fixture:stage12:permission',
                'content_hash' => hash('sha256', 'permission evidence'), 'captured_at' => now()->toISOString(),
                'source_controller' => 'stage12_test',
            ]],
        ])->assertCreated()->assertJsonPath('data.status', 'pending_review')->json('data.id');
        $this->postJson("/api/ai-sales/units/{$unit->id}/outreach/permissions/{$permissionId}/review", [
            'decision' => 'granted', 'reason_code' => 'human_evidence_review',
        ])->assertOk()->assertJsonPath('data.status', 'granted');

        foreach (OutreachReviewType::cases() as $type) {
            $response = $this->postJson("/api/ai-sales/units/{$unit->id}/outreach/drafts/{$draftId}/reviews", [
                'revision_id' => $revisionId, 'review_type' => $type->value,
                'decision' => 'approved', 'reason_code' => 'human_review',
            ]);
            $this->assertSame(201, $response->status(), $type->value.': '.$response->getContent());
        }
        $approved = $this->postJson("/api/ai-sales/units/{$unit->id}/outreach/drafts/{$draftId}/eligibility-preview")
            ->assertOk()->json('data');
        $this->assertFalse($approved['eligible']);
        $this->assertTrue($approved['content_ready']);
        $this->assertContains('stage12_dispatch_not_implemented', $approved['block_reasons']);

        $this->postJson("/api/ai-sales/units/{$unit->id}/outreach/suppressions", [
            'unit_business_context_id' => $context->id, 'scope' => 'endpoint',
            'unit_contact_context_link_id' => $contact->id, 'reason' => 'do_not_contact',
            'source' => 'stage12_test', 'evidence_reference' => 'fixture:stage12:dnc',
            'evidence_hash' => hash('sha256', 'dnc evidence'),
        ])->assertCreated();
        $blocked = $this->postJson("/api/ai-sales/units/{$unit->id}/outreach/drafts/{$draftId}/eligibility-preview")
            ->assertOk()->json('data');
        $this->assertFalse($blocked['eligible']);
        $this->assertFalse($blocked['content_ready']);
        $this->assertContains('stage12_suppression_do_not_contact', $blocked['block_reasons']);
    }

    public function test_permission_is_scoped_and_append_only_evidence_and_reviews_are_separate(): void
    {
        [$actor, $unit, $context, $product, $match, , $contact] = $this->outreachFixture();
        $other = UnitContactContextLink::query()->create([
            ...$contact->only([
                'unit_id', 'unit_business_context_id', 'channel_type', 'channel_value_snapshot', 'contact_role',
                'verification_status', 'data_classification', 'visibility_scope', 'communication_state', 'review_required',
            ]),
            'email_id' => \App\Models\Email::query()->create(['address' => 'other-'.uniqid().'@example.test', 'source' => 'fixture'])->id,
            'normalized_hash' => hash('sha256', uniqid('other', true)),
        ]);
        $permissionId = $this->actingAs($actor)->postJson("/api/ai-sales/units/{$unit->id}/outreach/permissions", [
            'unit_business_context_id' => $context->id, 'unit_contact_context_link_id' => $contact->id,
            'purpose' => 'advertising_outreach', 'product_id' => $product->id,
            'evidence' => [[
                'type' => 'signed_documented_consent', 'reference' => 'contract:stage12:test',
                'content_hash' => hash('sha256', 'contract'), 'captured_at' => now()->toISOString(),
            ]],
        ])->assertCreated()->json('data.id');
        $permission = CommunicationPermission::query()->findOrFail($permissionId);
        $evidence = $permission->evidence()->firstOrFail();
        $this->assertNotSame($contact->id, $other->id);
        $this->assertSame($contact->id, $permission->unit_contact_context_link_id);

        $this->expectException(\LogicException::class);
        $evidence->update(['safe_note' => 'mutation']);
    }

    public function test_each_review_type_is_required_for_current_revision(): void
    {
        [$actor, $unit, $context, , $match, , $contact] = $this->outreachFixture();
        $draftId = $this->actingAs($actor)->postJson("/api/ai-sales/units/{$unit->id}/outreach/drafts", $this->draftPayload($context, $match, $contact))->assertCreated()->json('data.id');
        $revisionId = $this->postJson("/api/ai-sales/units/{$unit->id}/outreach/drafts/{$draftId}/generate")->assertCreated()->json('data.id');
        foreach (['content', 'claims', 'permission'] as $type) {
            $response = $this->postJson("/api/ai-sales/units/{$unit->id}/outreach/drafts/{$draftId}/reviews", [
                'revision_id' => $revisionId, 'review_type' => $type, 'decision' => 'approved', 'reason_code' => 'human_review',
            ]);
            $this->assertSame(201, $response->status(), $type.': '.$response->getContent());
        }
        $this->assertSame('review_required', OutreachDraft::query()->findOrFail($draftId)->status->value);
        $this->assertDatabaseCount('outreach_draft_reviews', 3);
        $this->assertSame(3, OutreachDraftReview::query()->distinct('review_type')->count('review_type'));
    }
}
