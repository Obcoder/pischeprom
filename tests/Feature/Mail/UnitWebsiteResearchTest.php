<?php

namespace Tests\Feature\Mail;

use App\Domain\AiSales\Web\PublicDnsResolver;
use App\Models\Email;
use App\Models\Entity;
use App\Models\MailMessage;
use App\Models\MailMessageResearch;
use App\Models\Unit;
use App\Models\UnitWebsiteResearch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UnitWebsiteResearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
        config()->set([
            'mail-research.enabled' => true,
            'mail-research.api_key' => 'synthetic-key',
            'mail-research.model' => 'synthetic-model',
            'mail-research.token_parameter' => 'max_tokens',
        ]);
        $this->app->instance(PublicDnsResolver::class, new PublicDnsResolver([
            'catalog.example.com' => ['93.184.216.34'],
        ]));
    }

    public function test_unit_history_and_saving_require_active_verified_staff(): void
    {
        $mail = $this->mail();
        $unit = $this->linkedUnit($mail);
        $research = $this->research($mail);
        $saveUrl = $this->saveUrl($mail, $research);
        $historyUrl = $this->historyUrl($unit);
        $this->getJson($historyUrl)->assertUnauthorized();
        $this->postJson($saveUrl, ['unit_id' => $unit->id])->assertUnauthorized();

        foreach ([
            ['type' => 'customer'],
            ['status' => 'blocked'],
            ['email_verified_at' => null],
        ] as $attributes) {
            $this->actingAs($this->staff($attributes));
            $this->getJson($historyUrl)->assertForbidden();
            $this->postJson($saveUrl, ['unit_id' => $unit->id])->assertForbidden();
            $this->postJson($this->researchUrl($mail).'/website', [
                'url' => $research->query, 'unit_id' => $unit->id,
            ])->assertForbidden();
        }

        $this->actingAs($this->staff());
        $this->getJson($historyUrl)->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('unit_website_researches', 0);
        Http::assertNothingSent();
    }

    public function test_research_index_lists_only_linked_units_without_creating_snapshots(): void
    {
        $mail = $this->mail();
        $direct = $this->linkedUnit($mail, name: 'Прямая связь');
        $viaEntity = $this->linkedUnit($mail, throughEntity: true, name: 'Через юрлицо');
        $unrelated = Unit::query()->create(['name' => 'Чужой Unit']);
        $entity = Entity::query()->firstOrFail();
        $entity->units()->attach($direct->id);
        $research = $this->research($mail);

        $response = $this->actingAs($this->staff())->getJson($this->researchUrl($mail))
            ->assertOk()->assertJsonCount(2, 'linked_units')
            ->assertJsonPath('data.0.id', $research->id)
            ->assertJsonPath('data.0.saved_units', []);

        $this->assertEqualsCanonicalizing([$direct->id, $viaEntity->id], array_column($response->json('linked_units'), 'id'));
        foreach ($response->json('linked_units') as $linked) {
            $this->assertArrayHasKey('name', $linked);
            $this->assertStringContainsString('#website-research', $linked['url']);
            $this->assertNotSame($unrelated->id, $linked['id']);
        }
        $this->assertDatabaseCount('unit_website_researches', 0);
        Http::assertNothingSent();
    }

    public function test_existing_research_is_saved_from_server_data_and_repeated_save_reuses_snapshot(): void
    {
        $mail = $this->mail();
        $unit = $this->linkedUnit($mail);
        $research = $this->research($mail);
        config()->set('mail-research.enabled', false);
        $this->actingAs($this->staff());

        $response = $this->postJson($this->saveUrl($mail, $research), [
            'unit_id' => $unit->id,
            'url' => 'https://client-forged.example/',
            'result' => ['products' => [['name' => 'Подменённый товар']]],
        ])->assertOk()->assertJsonPath('data.id', $research->id)
            ->assertJsonPath('saved_to_unit.id', $unit->id)
            ->assertJsonPath('saved_to_unit.name', $unit->name)
            ->assertJsonPath('data.saved_units.0.id', $unit->id);
        $snapshotId = $response->json('unit_research_id');
        $this->assertIsInt($snapshotId);
        $snapshot = UnitWebsiteResearch::query()->findOrFail($snapshotId);
        $this->assertSame($research->query, $snapshot->url);
        $this->assertSame($research->result, $snapshot->result);
        $this->assertSame($mail->id, $snapshot->source_mail_message_id);
        $this->assertSame($research->id, $snapshot->source_research_id);

        $this->postJson($this->saveUrl($mail, $research), ['unit_id' => $unit->id])
            ->assertOk()->assertJsonPath('unit_research_id', $snapshotId)
            ->assertJsonCount(1, 'data.saved_units');
        $updatedResult = [
            ...$research->result,
            'products' => [['name' => 'Обновлённый пектин', 'description' => '', 'source_url' => $research->query]],
        ];
        $research->update(['result' => $updatedResult]);
        $this->postJson($this->saveUrl($mail, $research), ['unit_id' => $unit->id])
            ->assertOk()->assertJsonPath('unit_research_id', $snapshotId);
        $this->assertSame($updatedResult, $snapshot->fresh()->result);
        $this->getJson($this->researchUrl($mail))->assertOk()
            ->assertJsonPath('data.0.saved_units.0.unit_research_id', $snapshotId);
        $this->getJson($this->historyUrl($unit))->assertOk()
            ->assertJsonPath('data.0.id', $snapshotId)
            ->assertJsonPath('data.0.result', $research->result);
        $this->assertDatabaseCount('unit_website_researches', 1);
        Http::assertNothingSent();
    }

    public function test_save_accepts_unit_related_through_sender_entity(): void
    {
        $mail = $this->mail();
        $unit = $this->linkedUnit($mail, throughEntity: true);
        $research = $this->research($mail);
        $this->assertDatabaseCount('email_unit', 0);

        $this->actingAs($this->staff())->postJson($this->saveUrl($mail, $research), ['unit_id' => $unit->id])
            ->assertOk()->assertJsonPath('data.saved_units.0.id', $unit->id);
        $this->assertDatabaseHas('unit_website_researches', [
            'unit_id' => $unit->id, 'source_research_id' => $research->id,
        ]);
        Http::assertNothingSent();
    }

    public function test_save_rejects_foreign_research_company_result_and_unlinked_unit(): void
    {
        $mail = $this->mail();
        $unit = $this->linkedUnit($mail);
        $foreign = $this->research($this->mail('other@example.test'));
        $company = $this->research($mail, kind: 'company', query: '7701234567');
        $website = $this->research($mail);
        $unrelated = Unit::query()->create(['name' => 'Несвязанный Unit']);
        $this->actingAs($this->staff());

        $this->postJson($this->saveUrl($mail, $foreign), ['unit_id' => $unit->id])->assertNotFound();
        $this->postJson($this->saveUrl($mail, $company), ['unit_id' => $unit->id])->assertUnprocessable();
        $this->postJson($this->saveUrl($mail, $website), ['unit_id' => $unrelated->id])
            ->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $this->postJson($this->saveUrl($mail, $website), [])->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $this->postJson($this->saveUrl($mail, $website), ['unit_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $index = $this->getJson($this->researchUrl($mail))->assertOk()->assertJsonCount(2, 'data');
        $this->assertNotContains($foreign->id, array_column($index->json('data'), 'id'));
        foreach ($index->json('data') as $record) {
            $this->assertSame($mail->id, $record['mail_message_id']);
        }
        $this->assertDatabaseCount('unit_website_researches', 0);
        Http::assertNothingSent();
    }

    public function test_new_scan_saves_to_selected_unit_and_cached_scan_can_save_to_another_linked_unit(): void
    {
        $mail = $this->mail();
        $first = $this->linkedUnit($mail, name: 'Первый Unit');
        $second = $this->linkedUnit($mail, throughEntity: true, name: 'Второй Unit');
        $this->website();
        $this->actingAs($this->staff());
        $endpoint = $this->researchUrl($mail).'/website';

        $fresh = $this->postJson($endpoint, ['url' => 'https://catalog.example.com', 'unit_id' => $first->id])
            ->assertOk()->assertJsonPath('cached', false)
            ->assertJsonPath('saved_to_unit.id', $first->id)
            ->assertJsonPath('data.saved_units.0.id', $first->id);
        $sourceId = $fresh->json('data.id');
        $firstSnapshot = $fresh->json('unit_research_id');
        $this->assertIsInt($firstSnapshot);
        Http::assertSentCount(3);

        $cached = $this->postJson($endpoint, ['url' => 'https://catalog.example.com/', 'unit_id' => $second->id])
            ->assertOk()->assertJsonPath('cached', true)
            ->assertJsonPath('saved_to_unit.id', $second->id)
            ->assertJsonPath('data.id', $sourceId)->assertJsonCount(2, 'data.saved_units');
        $this->assertNotSame($firstSnapshot, $cached->json('unit_research_id'));
        $this->assertDatabaseCount('mail_message_researches', 1);
        $this->assertDatabaseCount('unit_website_researches', 2);
        foreach ([$first, $second] as $unit) {
            $this->assertDatabaseHas('unit_website_researches', ['unit_id' => $unit->id, 'source_research_id' => $sourceId]);
        }
        Http::assertSentCount(3);
    }

    public function test_scan_with_null_unit_keeps_result_only_in_mail_until_explicit_save(): void
    {
        $mail = $this->mail();
        $unit = $this->linkedUnit($mail);
        $this->website();
        $response = $this->actingAs($this->staff())->postJson($this->researchUrl($mail).'/website', [
            'url' => 'https://catalog.example.com/', 'unit_id' => null,
        ])->assertOk()->assertJsonPath('data.saved_units', []);
        $this->assertDatabaseCount('unit_website_researches', 0);
        Http::assertSentCount(3);

        $research = MailMessageResearch::query()->findOrFail($response->json('data.id'));
        $this->postJson($this->saveUrl($mail, $research), ['unit_id' => $unit->id])->assertOk();
        $this->assertDatabaseCount('unit_website_researches', 1);
        Http::assertSentCount(3);
    }

    public function test_invalid_target_is_rejected_before_external_scan_or_cached_save(): void
    {
        $mail = $this->mail();
        $unrelated = Unit::query()->create(['name' => 'Несвязанный Unit']);
        $this->actingAs($this->staff());
        $endpoint = $this->researchUrl($mail).'/website';
        $this->postJson($endpoint, ['url' => 'https://catalog.example.com/', 'unit_id' => $unrelated->id])
            ->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $this->assertDatabaseCount('mail_message_researches', 0);

        $this->research($mail);
        $this->postJson($endpoint, ['url' => 'https://catalog.example.com/', 'unit_id' => $unrelated->id])
            ->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $this->postJson($endpoint, ['url' => 'https://catalog.example.com/', 'unit_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $this->assertDatabaseCount('unit_website_researches', 0);
        Http::assertNothingSent();
    }

    public function test_snapshot_stays_independent_of_source_updates_and_survives_mail_deletion(): void
    {
        $mail = $this->mail();
        $unit = $this->linkedUnit($mail);
        $research = $this->research($mail);
        $original = $research->result;
        $this->actingAs($this->staff());
        $response = $this->postJson($this->saveUrl($mail, $research), ['unit_id' => $unit->id])->assertOk();
        $snapshotId = $response->json('unit_research_id');

        $research->update(['result' => ['products' => [['name' => 'Обновлённый каталог']]]]);
        $this->getJson($this->historyUrl($unit))->assertOk()->assertJsonPath('data.0.result', $original);
        $mail->delete();

        $this->assertDatabaseMissing('mail_message_researches', ['id' => $research->id]);
        $this->assertDatabaseHas('unit_website_researches', [
            'id' => $snapshotId, 'source_mail_message_id' => null, 'source_research_id' => null,
        ]);
        $this->getJson($this->historyUrl($unit))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.result', $original)
            ->assertJsonPath('data.0.source_mail_message_id', null)
            ->assertJsonPath('data.0.source_research_id', null)
            ->assertJsonPath('data.0.url', 'https://catalog.example.com/');
        Http::assertNothingSent();
    }

    public function test_unit_history_is_scoped_paginated_and_excludes_internal_user_fields(): void
    {
        $unit = Unit::query()->create(['name' => 'Основной Unit']);
        $other = Unit::query()->create(['name' => 'Чужой Unit']);
        $actor = $this->staff();
        $ids = [];
        for ($i = 0; $i < 11; $i++) {
            $ids[] = UnitWebsiteResearch::query()->create([
                'unit_id' => $unit->id, 'saved_by_user_id' => $actor->id,
                'url' => 'https://catalog.example.com/'.$i,
                'result' => ['products' => [['name' => 'Товар '.$i]]],
                'researched_at' => now()->subDays(2), 'saved_at' => now()->subMinutes(20 - $i),
            ])->id;
        }
        $foreign = UnitWebsiteResearch::query()->create([
            'unit_id' => $other->id, 'url' => 'https://other.example.com/',
            'result' => ['products' => [['name' => 'Чужой результат']]],
            'researched_at' => now(), 'saved_at' => now(),
        ]);

        $first = $this->actingAs($actor)->getJson($this->historyUrl($unit))
            ->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('meta.total', 11)
            ->assertJsonPath('meta.current_page', 1)->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('links.prev', null)->assertJsonPath('data.0.id', $ids[10]);
        $this->assertNotNull($first->json('links.next'));
        foreach ($first->json('data') as $record) {
            $this->assertSame($unit->id, $record['unit_id']);
            $this->assertNotSame($foreign->id, $record['id']);
            $this->assertArrayNotHasKey('saved_by_user_id', $record);
            $this->assertArrayNotHasKey('created_at', $record);
            $this->assertArrayNotHasKey('updated_at', $record);
        }
        $first->assertJsonStructure(['data' => [['id', 'unit_id', 'source_mail_message_id', 'source_research_id', 'url', 'result', 'researched_at', 'saved_at']]]);
        $this->getJson($this->historyUrl($unit).'?page=2')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)->assertJsonPath('data.0.id', $ids[0])
            ->assertJsonPath('links.next', null);
        $this->getJson('/api/units/999999/website-research')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_database_rejects_duplicate_unit_and_source_pair(): void
    {
        $mail = $this->mail();
        $unit = $this->linkedUnit($mail);
        $research = $this->research($mail);
        $response = $this->actingAs($this->staff())->postJson($this->saveUrl($mail, $research), ['unit_id' => $unit->id])->assertOk();
        $snapshot = UnitWebsiteResearch::query()->findOrFail($response->json('unit_research_id'));

        try {
            $snapshot->replicate()->save();
            $this->fail('A second snapshot for the same Unit and source must violate the unique constraint.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('UNIQUE constraint failed', $exception->getMessage());
        }
        $this->assertDatabaseCount('unit_website_researches', 1);
        Http::assertNothingSent();
    }

    private function staff(array $attributes = []): User
    {
        return User::factory()->create([
            'type' => 'employee', 'status' => 'active', 'email_verified_at' => now(), ...$attributes,
        ]);
    }

    private function mail(string $from = 'buyer@factory.test'): MailMessage
    {
        return MailMessage::query()->create([
            'mailbox' => 'sales@example.test', 'folder' => 'INBOX', 'direction' => 'incoming',
            'from_address' => $from, 'subject' => 'Каталог продукции', 'text' => 'Здравствуйте!',
        ]);
    }

    private function linkedUnit(MailMessage $mail, bool $throughEntity = false, string $name = 'Производство'): Unit
    {
        $unit = Unit::query()->create(['name' => $name]);
        $email = Email::query()->firstOrCreate(['address' => $mail->from_address]);
        if ($throughEntity) {
            $entity = Entity::query()->create(['name' => 'ООО '.$name]);
            $email->entities()->attach($entity->id);
            $entity->units()->attach($unit->id);
        } else {
            $email->units()->attach($unit->id);
        }

        return $unit;
    }

    private function research(MailMessage $mail, string $kind = 'website', string $query = 'https://catalog.example.com/'): MailMessageResearch
    {
        return MailMessageResearch::query()->create([
            'mail_message_id' => $mail->id, 'kind' => $kind,
            'input_hash' => hash('sha256', $query), 'query' => $query,
            'result' => $kind === 'website' ? [
                'products' => [['name' => 'Пектин цитрусовый', 'description' => '', 'source_url' => $query]],
                'summary' => 'Найдено позиций: 1.',
                'pages' => [['url' => $query, 'title' => 'Каталог']], 'warnings' => [], 'partial' => true,
            ] : ['companies' => [], 'source' => 'DaData'],
        ]);
    }

    private function researchUrl(MailMessage $mail): string
    {
        return '/api/mail-messages/'.$mail->id.'/research';
    }

    private function saveUrl(MailMessage $mail, MailMessageResearch $research): string
    {
        return $this->researchUrl($mail).'/'.$research->id.'/unit';
    }

    private function historyUrl(Unit $unit): string
    {
        return '/api/units/'.$unit->id.'/website-research';
    }

    private function website(): void
    {
        Http::fake([
            'https://catalog.example.com/robots.txt' => Http::response('', 404),
            'https://catalog.example.com/' => Http::response('<html><title>Каталог</title><body><h1>Пектин цитрусовый</h1></body></html>', 200, ['Content-Type' => 'text/html']),
            'https://api.timeweb.ai/v1/chat/completions' => Http::response(['choices' => [[
                'finish_reason' => 'stop', 'message' => ['content' => json_encode([
                    'summary' => 'Каталог', 'products' => [[
                        'name' => 'Пектин цитрусовый', 'description' => '', 'page' => 0, 'evidence' => 'Пектин цитрусовый',
                    ]],
                ], JSON_UNESCAPED_UNICODE)],
            ]]], 200, ['Content-Type' => 'application/json']),
        ]);
    }
}
