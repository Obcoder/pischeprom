<?php

namespace Tests\Feature\Suppliers;

use App\Models\Email;
use App\Models\Entity;
use App\Models\SupplierOrder;
use App\Models\SupplierPipeline;
use App\Models\SupplierPipelineCard;
use App\Models\SupplierPipelineStage;
use App\Models\SupplierProfile;
use App\Models\Telephone;
use App\Models\Unit;
use App\Models\Uri;
use App\Models\User;
use App\Services\Suppliers\SupplierAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierRegistryTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-13 22:00:00', 'UTC'));
        Http::preventStrayRequests();
        Queue::fake();
        Mail::fake();
        $this->staff = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $this->actingAs($this->staff);
    }

    private function supplier(string $name, array $profile = []): Unit
    {
        $unit = Unit::create(['name' => $name, 'is_supplier' => true]);
        SupplierProfile::create(['unit_id' => $unit->id, 'is_watched' => true, ...$profile]);

        return $unit;
    }

    private function order(Unit $unit, string $status, ?string $sentAt = null, ?string $deliveryDate = null): SupplierOrder
    {
        return SupplierOrder::create(['unit_id' => $unit->id, 'status' => $status, 'sent_at' => $sentAt,
            'delivery_date' => $deliveryDate, 'to' => [], 'cc' => [], 'create_key' => (string) Str::uuid(),
            'create_payload_hash' => str_repeat('0', 64)]);
    }

    private function url(array $filters = []): string
    {
        return '/api/suppliers?'.http_build_query($filters);
    }

    public function test_scopes_and_whole_filtered_summary_ignore_pagination_and_do_not_alarm_paused_profiles(): void
    {
        $a = $this->supplier('A', ['next_review_on' => '2026-10-13']);
        $b = $this->supplier('B', ['next_review_on' => '2026-10-14', 'status' => 'potential']);
        $this->supplier('C', ['next_review_on' => '2026-10-13', 'status' => 'paused']);
        $this->supplier('D', ['next_review_on' => '2026-10-13', 'is_watched' => false]);
        $this->supplier('E', ['next_review_on' => '2026-10-13', 'status' => 'archived']);
        $this->order($a, 'sent', '2026-07-01 12:00:00', '2026-10-13');
        $this->order($b, 'send_unknown');
        $this->getJson($this->url(['per_page' => 1, 'page' => 2]))->assertOk()
            ->assertJsonPath('meta.total', 3)->assertJsonPath('data.0.unit.id', $b->id)
            ->assertJsonPath('summary', ['suppliers_count' => 3, 'watched_count' => 3, 'review_due_count' => 2,
                'overdue_delivery_suppliers_count' => 1, 'uncertain_send_suppliers_count' => 1])
            ->assertJsonPath('data.0.profile.review_state', 'today');
        $this->getJson($this->url(['scope' => 'all']))->assertOk()->assertJsonPath('meta.total', 5)
            ->assertJsonPath('summary.watched_count', 3)->assertJsonPath('summary.review_due_count', 2);
        $this->getJson($this->url(['scope' => 'archived']))->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('summary.watched_count', 0)->assertJsonPath('summary.review_due_count', 0);
        $this->getJson($this->url(['scope' => 'all', 'attention' => 'review_due']))->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonPath('summary.suppliers_count', 2);
    }

    public function test_period_order_sort_and_no_orders_use_placed_documents_at_moscow_day_boundaries(): void
    {
        $a = $this->supplier('A');
        $b = $this->supplier('B');
        $c = $this->supplier('C');
        $this->order($a, 'sent', '2026-10-09 21:00:00'); // Moscow Oct 10 start.
        $this->order($a, 'confirmed', '2026-10-10 20:59:59');
        $this->order($a, 'closed', '2026-10-10 21:00:00'); // Moscow Oct 11, excluded.
        $this->order($b, 'closed', '2026-10-10 10:00:00');
        $this->order($b, 'cancelled', '2026-10-10 11:00:00');
        $this->order($c, 'sent', '2026-10-09 20:59:59');
        $this->order($c, 'draft', '2026-10-10 15:00:00');
        $this->order($c, 'send_unknown', '2026-10-10 16:00:00');
        $this->order($c, 'sent');
        $filters = ['date_from' => '2026-10-10', 'date_to' => '2026-10-10', 'sort' => 'order_count', 'direction' => 'desc'];
        $response = $this->getJson($this->url($filters))->assertOk()->assertJsonPath('period.days', 1);
        $this->assertSame([$a->id, $b->id, $c->id], array_column(array_column($response->json('data'), 'unit'), 'id'));
        $this->assertSame([2, 1, 0], array_column(array_column(array_column($response->json('data'), 'metrics'), 'orders'), 'count'));
        $this->getJson($this->url([...$filters, 'attention' => 'no_orders']))->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.unit.id', $c->id);
    }

    public function test_attention_is_current_not_limited_to_report_period_and_last_order_sort_is_all_time(): void
    {
        $a = $this->supplier('A');
        $b = $this->supplier('B');
        $c = $this->supplier('C');
        $this->order($a, 'sent', '2026-10-13 10:00:00', '2026-10-13');
        $this->order($b, 'closed', '2026-10-12 10:00:00', '2026-10-12');
        $this->order($c, 'cancelled', '2026-10-13 11:00:00', '2026-10-12');
        $this->order($c, 'sending');
        $filters = ['date_from' => '2020-01-01', 'date_to' => '2020-01-31'];
        $this->getJson($this->url([...$filters, 'attention' => 'delivery_overdue']))->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.unit.id', $a->id)
            ->assertJsonPath('data.0.metrics.orders.count', 0)->assertJsonPath('data.0.metrics.monitoring.overdue_delivery_count', 1);
        $this->getJson($this->url([...$filters, 'attention' => 'uncertain_send']))->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.unit.id', $c->id);
        $response = $this->getJson($this->url([...$filters, 'sort' => 'last_order', 'direction' => 'desc']))->assertOk();
        $this->assertSame([$a->id, $b->id, $c->id], array_column(array_column($response->json('data'), 'unit'), 'id'));
    }

    public function test_filters_include_entity_name_and_sort_review_dates_with_null_last(): void
    {
        $a = $this->supplier('First', ['next_review_on' => '2026-10-20', 'priority' => 'high', 'owner_user_id' => $this->staff->id]);
        $b = $this->supplier('Second', ['next_review_on' => '2026-10-14', 'priority' => 'high']);
        $c = $this->supplier('Third');
        $entity = Entity::create(['name' => 'Special legal company']);
        $a->entities()->attach($entity);
        $this->getJson($this->url(['search' => 'legal', 'owner_user_id' => $this->staff->id, 'priority' => 'high', 'status' => 'active']))
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.unit.id', $a->id)
            ->assertJsonPath('data.0.profile.owner.id', $this->staff->id);
        $response = $this->getJson($this->url(['sort' => 'next_review']))->assertOk();
        $this->assertSame([$b->id, $a->id, $c->id], array_column(array_column($response->json('data'), 'unit'), 'id'));
        $this->getJson($this->url(['search' => 'absent']))->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('summary.suppliers_count', 0)->assertJsonPath('summary.review_due_count', 0);
    }

    public function test_page_metrics_are_batched_only_for_selected_page(): void
    {
        $a = $this->supplier('A');
        $b = $this->supplier('B');
        $this->supplier('C');
        $this->supplier('D');
        $real = new SupplierAnalytics;
        $this->partialMock(SupplierAnalytics::class, function ($mock) use ($real, $a, $b): void {
            $mock->shouldReceive('summaries')->once()->with([$b->id, $a->id], \Mockery::type('array'))
                ->andReturnUsing(fn ($ids, $period) => $real->summaries($ids, $period));
        });
        // A custom page size still uses one batch for exactly that page.
        $this->getJson($this->url(['per_page' => 2, 'page' => 2, 'direction' => 'desc']))->assertOk()
            ->assertJsonPath('meta.total', 4);
    }

    public function test_show_includes_shared_entities_safe_contacts_and_read_only_pipeline_without_heavy_relations(): void
    {
        $a = $this->supplier('A');
        $b = Unit::create(['name' => 'Other unit']);
        $entity = Entity::create(['name' => 'Shared legal entity']);
        $a->entities()->attach($entity);
        $b->entities()->attach($entity);
        $email = Email::create(['address' => 'supplier@example.test', 'name' => 'Sales', 'is_active' => true]);
        $a->emails()->attach($email);
        $entity->emails()->attach($email);
        $deleted = Email::create(['address' => 'deleted@example.test', 'is_active' => true]);
        $a->emails()->attach($deleted);
        $deleted->delete();
        $telephone = Telephone::create(['number' => '+79991234567']);
        $entity->telephones()->attach($telephone);
        $uri = Uri::create(['address' => 'https://supplier.example.test']);
        $a->uris()->attach($uri);
        $pipeline = SupplierPipeline::create(['name' => 'Sourcing']);
        $stage = SupplierPipelineStage::create(['supplier_pipeline_id' => $pipeline->id, 'name' => 'Negotiating']);
        SupplierPipelineCard::create(['supplier_pipeline_id' => $pipeline->id, 'supplier_pipeline_stage_id' => $stage->id,
            'unit_id' => $a->id, 'title' => 'Card', 'notes' => 'Private context not in response', 'next_contact_at' => '2026-10-20 10:00:00']);
        DB::enableQueryLog();
        $this->getJson('/api/suppliers/'.$a->id)->assertOk()->assertJsonCount(1, 'contacts.emails')
            ->assertJsonPath('contacts.emails.0.address', $email->address)
            ->assertJsonPath('contacts.telephones.0.number', $telephone->number)
            ->assertJsonPath('contacts.uris.0.uri', $uri->address)->assertJsonPath('entities.0.shared', true)
            ->assertJsonPath('entities.0.linked_unit_ids', [$a->id, $b->id])
            ->assertJsonPath('pipelines.0.name', 'Sourcing')->assertJsonPath('pipelines.0.stage', 'Negotiating')
            ->assertJsonMissingPath('pipelines.0.notes')->assertJsonMissingPath('unit.entities');
        $queries = implode("\n", array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();
        foreach (['"sales"', '"sendings"', '"good_purchase"', '"supplier_order_items"', '"field_unit"', '"label_unit"'] as $table) {
            $this->assertStringNotContainsString($table, $queries);
        }
        $this->assertDatabaseCount('supplier_pipeline_cards', 1);
        $this->assertDatabaseCount('supplier_profile_events', 0);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_invalid_filters_are_rejected_before_export_and_index(): void
    {
        foreach ([['scope' => 'automatic'], ['per_page' => 51], ['sort' => 'status desc;'], ['search' => str_repeat('x', 151)],
            ['date_from' => '2026-10-14', 'date_to' => '2026-10-13'], ['date_from' => '2000-01-01', 'date_to' => '2026-10-13']] as $filters) {
            $this->getJson($this->url($filters))->assertUnprocessable();
            $this->getJson('/api/suppliers/export?'.http_build_query($filters))->assertUnprocessable();
        }
    }

    public function test_csv_preserves_filter_order_and_batch_limits_with_period_and_currency_labels(): void
    {
        for ($i = 0; $i < 201; $i++) {
            $this->supplier(sprintf('Supplier %03d', $i));
        }
        $this->supplier('Archived', ['status' => 'archived']);
        $real = new SupplierAnalytics;
        $batches = [];
        $this->partialMock(SupplierAnalytics::class, function ($mock) use ($real, &$batches): void {
            $mock->shouldReceive('summaries')->twice()->andReturnUsing(function ($ids, $period) use ($real, &$batches) {
                $batches[] = count($ids);

                return $real->summaries($ids, $period);
            });
        });
        $response = $this->get('/api/suppliers/export?date_from=2026-10-01&date_to=2026-10-10&direction=desc')->assertOk();
        $csv = $response->streamedContent();
        $this->assertSame([200, 1], $batches);
        $lines = explode("\n", trim($csv));
        $this->assertCount(202, $lines);
        $this->assertStringContainsString('Заявки: суммы по валютам', $lines[0]);
        $this->assertStringContainsString('Закупки: суммы по валютам (без общих юрлиц)', $lines[0]);
        $this->assertStringContainsString('Supplier 200', $lines[1]);
        $this->assertStringContainsString('Supplier 000', $lines[201]);
        $this->assertStringContainsString('2026-10-01;2026-10-10', $lines[1]);
        $this->assertStringNotContainsString('Archived', $csv);
    }
}
