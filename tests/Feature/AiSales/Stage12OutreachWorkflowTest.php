<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Outreach\OutreachSafeDto;
use App\Models\OutreachDraft;
use App\Models\OutreachDraftRevision;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class Stage12OutreachWorkflowTest extends Stage12TestCase
{
    public function test_fake_generation_uses_allowlisted_safe_dto_and_never_dispatches(): void
    {
        [$actor, $unit, $context, , $match, $email, $contact] = $this->outreachFixture();
        $draftId = $this->actingAs($actor)->postJson(
            "/api/ai-sales/units/{$unit->id}/outreach/drafts",
            $this->draftPayload($context, $match, $contact),
        )->assertCreated()->json('data.id');

        $response = $this->postJson("/api/ai-sales/units/{$unit->id}/outreach/drafts/{$draftId}/generate")
            ->assertCreated()->assertJsonPath('data.dlp_status', 'passed');
        $draft = OutreachDraft::query()->with(['businessContext', 'productMatch.product'])->findOrFail($draftId);
        $safe = OutreachSafeDto::fromDraft($draft)->toArray();
        $encoded = json_encode($safe, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString($email->address, $encoded);
        $this->assertArrayNotHasKey('recipient', $safe);
        $this->assertArrayNotHasKey('email', $safe);
        $this->assertSame('review_required', $draft->fresh()->status->value);
        $this->assertDatabaseCount('outreach_draft_revisions', 1);
        $this->assertDatabaseCount('outreach_draft_claims', 1);
        $this->assertStringNotContainsString('<script', $response->json('data.html'));
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_human_edit_is_append_only_and_dlp_blocks_procurement_secret(): void
    {
        [$actor, $unit, $context, , $match, , $contact] = $this->outreachFixture();
        $draftId = $this->actingAs($actor)->postJson("/api/ai-sales/units/{$unit->id}/outreach/drafts", $this->draftPayload($context, $match, $contact))->assertCreated()->json('data.id');
        $first = $this->postJson("/api/ai-sales/units/{$unit->id}/outreach/drafts/{$draftId}/generate")->assertCreated()->json('data');
        $content = $first['structured_content'];
        $content['value_proposition'] = 'Внутренняя закупочная цена и маржа не должны покидать procurement lane.';

        $second = $this->postJson("/api/ai-sales/units/{$unit->id}/outreach/drafts/{$draftId}/revisions", ['structured_content' => $content])
            ->assertCreated()->assertJsonPath('data.revision_number', 2)->assertJsonPath('data.dlp_status', 'blocked')->json('data');

        $this->assertContains('procurement_secret', $second['dlp_findings']);
        $this->assertSame($first['plaintext'], OutreachDraftRevision::query()->findOrFail($first['id'])->plaintext);
        $this->assertDatabaseCount('outreach_draft_revisions', 2);
        $this->assertSame('blocked', OutreachDraft::query()->findOrFail($draftId)->status->value);
        Mail::assertNothingSent();
    }

    public function test_unit_with_sales_and_procurement_lanes_is_strictly_isolated(): void
    {
        $actor = $this->outreachUser(['ai_sales.procurement.view']);
        $unit = $this->unit();
        [, , $sales, , $salesMatch, , $salesContact] = $this->outreachFixture($actor, $unit, 'sales');
        [, , $procurement, , $procurementMatch, , $procurementContact] = $this->outreachFixture($actor, $unit, 'procurement');

        $this->actingAs($actor)->postJson("/api/ai-sales/units/{$unit->id}/outreach/drafts", $this->draftPayload($sales, $salesMatch, $salesContact))->assertCreated();
        $this->postJson("/api/ai-sales/units/{$unit->id}/outreach/drafts", $this->draftPayload($procurement, $procurementMatch, $procurementContact))->assertForbidden();
        $this->postJson("/api/ai-sales/units/{$unit->id}/outreach/drafts", [
            ...$this->draftPayload($sales, $procurementMatch, $salesContact),
        ])->assertUnprocessable();
        $this->assertDatabaseCount('outreach_drafts', 1);
    }
}
