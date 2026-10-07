<?php

namespace Tests\Feature;

use App\Models\Good;
use App\Models\GoodSeo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoodsSeoSemanticCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        config()->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config()->set('services.indexnow.key', null);
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }

    public function test_grouped_rows_round_trip_and_synchronize_unique_legacy_phrases(): void
    {
        $good = Good::create(['name' => 'Филе форели']);
        $rows = [
            ['group' => 'Форель', 'phrase' => 'форель оптом'],
            ['group' => 'Филе', 'phrase' => 'филе форели'],
            ['group' => 'Закупка', 'phrase' => 'ФОРЕЛЬ ОПТОМ'],
            ['group' => '', 'phrase' => 'купить рыбу'],
        ];
        $this->save($good, ['semantic_core_rows' => [
            ['group' => '  Форель  ', 'phrase' => '  форель оптом  '],
            ['group' => 'Филе', 'phrase' => 'филе форели'],
            ['group' => 'Закупка', 'phrase' => 'ФОРЕЛЬ ОПТОМ'],
            ['group' => 'форель', 'phrase' => 'ФОРЕЛЬ ОПТОМ'],
            ['phrase' => 'купить рыбу'],
        ], 'semantic_core' => ['Устаревшая копия']])->assertOk()
            ->assertJsonPath('semantic_core_rows', $rows)
            ->assertJsonPath('semantic_core', ['форель оптом', 'филе форели', 'купить рыбу']);

        $this->getJson('/api/goods/'.$good->id.'/seo')->assertOk()->assertJsonPath('semantic_core_rows', $rows);
        $this->save($good, ['meta_title' => 'Новый заголовок'])->assertOk()->assertJsonPath('semantic_core_rows', $rows);
        $this->assertSame($rows, $good->seo()->firstOrFail()->semantic_core_rows);
        Http::assertNothingSent();
    }

    public function test_legacy_phrase_edits_retain_all_groups_for_retained_phrases_and_remove_stale_rows(): void
    {
        $good = Good::create(['name' => 'Филе форели']);
        GoodSeo::create(['good_id' => $good->id, 'semantic_core' => ['форель оптом', 'удаляемая фраза'], 'semantic_core_rows' => [
            ['group' => 'Форель', 'phrase' => 'форель оптом'],
            ['group' => 'Удаляемая группа', 'phrase' => 'удаляемая фраза'],
            ['group' => 'Закупка', 'phrase' => 'форель оптом'],
        ]]);

        $this->save($good, ['semantic_core' => ['  новая фраза  ', 'ФОРЕЛЬ ОПТОМ', 'Новая Фраза']])->assertOk()
            ->assertJsonPath('semantic_core', ['новая фраза', 'ФОРЕЛЬ ОПТОМ'])
            ->assertJsonPath('semantic_core_rows', [
                ['group' => '', 'phrase' => 'новая фраза'],
                ['group' => 'Форель', 'phrase' => 'ФОРЕЛЬ ОПТОМ'],
                ['group' => 'Закупка', 'phrase' => 'ФОРЕЛЬ ОПТОМ'],
            ]);
        $this->save($good, ['semantic_core' => null])->assertOk()
            ->assertJsonPath('semantic_core', null)->assertJsonPath('semantic_core_rows', []);
        Http::assertNothingSent();
    }

    public function test_legacy_records_remain_unchanged_until_the_semantic_core_is_edited(): void
    {
        $good = Good::create(['name' => 'Филе форели']);
        $legacy = ['форель оптом', 'филе форели'];
        GoodSeo::create(['good_id' => $good->id, 'semantic_core' => $legacy]);
        $this->getJson('/api/goods/'.$good->id.'/seo')->assertOk()
            ->assertJsonPath('semantic_core', $legacy)->assertJsonPath('semantic_core_rows', null);
        $this->save($good, ['meta_title' => 'Сохранённый заголовок'])->assertOk()
            ->assertJsonPath('semantic_core', $legacy)->assertJsonPath('semantic_core_rows', null);
        $this->save($good, ['semantic_core' => $legacy])->assertOk()->assertJsonPath('semantic_core_rows', [
            ['group' => '', 'phrase' => 'форель оптом'], ['group' => '', 'phrase' => 'филе форели'],
        ]);
        $this->save($good, ['semantic_core_rows' => []])->assertOk()
            ->assertJsonPath('semantic_core', [])->assertJsonPath('semantic_core_rows', []);
        Http::assertNothingSent();
    }

    public function test_invalid_rows_are_rejected_without_changing_existing_seo(): void
    {
        $good = Good::create(['name' => 'Филе форели']);
        $seo = GoodSeo::create(['good_id' => $good->id, 'meta_title' => 'Сохранённый заголовок', 'semantic_core' => ['форель оптом'],
            'semantic_core_rows' => [['group' => 'Форель', 'phrase' => 'форель оптом']]]);
        $before = $seo->fresh()->getAttributes();
        $invalid = [
            [null, 'semantic_core_rows'],
            [['named' => ['phrase' => 'не список']], 'semantic_core_rows'],
            [[['group' => 'Без фразы']], 'semantic_core_rows.0.phrase'],
            [[['phrase' => '   ']], 'semantic_core_rows.0.phrase'],
            [[['phrase' => ['не строка']]], 'semantic_core_rows.0.phrase'],
            [[['group' => ['не строка'], 'phrase' => 'фраза']], 'semantic_core_rows.0.group'],
            [[['group' => str_repeat('а', 256), 'phrase' => 'фраза']], 'semantic_core_rows.0.group'],
            [[['phrase' => str_repeat('а', 1001)]], 'semantic_core_rows.0.phrase'],
            [[['phrase' => 'фраза', 'unexpected' => true]], 'semantic_core_rows.0'],
            [array_fill(0, 2001, ['phrase' => 'фраза']), 'semantic_core_rows'],
        ];
        foreach ($invalid as [$rows, $field]) {
            $this->save($good, ['semantic_core_rows' => $rows, 'meta_title' => 'Не сохранять'])->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->assertSame($before, $seo->fresh()->getAttributes());
        }
        Http::assertNothingSent();
    }

    public function test_legacy_edits_cannot_exceed_the_row_limit_by_preserving_existing_groups(): void
    {
        $good = Good::create(['name' => 'Филе форели']);
        $rows = array_map(fn (int $index): array => ['group' => 'Группа '.$index, 'phrase' => 'форель оптом'], range(1, 2000));
        $seo = GoodSeo::create(['good_id' => $good->id, 'semantic_core' => ['форель оптом'], 'semantic_core_rows' => $rows]);

        $this->save($good, ['semantic_core' => ['форель оптом', 'новая фраза']])->assertUnprocessable()->assertJsonValidationErrors('semantic_core');
        $this->assertSame($rows, $seo->fresh()->semantic_core_rows);
        $this->assertSame(['форель оптом'], $seo->fresh()->semantic_core);
        Http::assertNothingSent();
    }

    private function save(Good $good, array $attributes): \Illuminate\Testing\TestResponse
    {
        return $this->putJson('/api/goods/'.$good->id.'/seo', ['robots' => 'index,follow', 'is_active' => true, ...$attributes]);
    }
}
