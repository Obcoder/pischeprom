<?php

namespace Tests\Feature\Suppliers;

use App\Models\SupplierProfile;
use App\Models\SupplierProfileEvent;
use App\Models\Unit;
use App\Models\User;
use App\Services\Suppliers\SupplierProfileWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('yandex');
        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
        $this->staff = User::factory()->create(['type' => 'employee', 'status' => 'active', 'name' => 'Анна Закупщик']);
        $this->actingAs($this->staff);
        $this->unit = Unit::create(['name' => 'Поставщик без профиля', 'is_supplier' => true]);
        $this->travelTo(Carbon::parse('2026-10-12 22:30:00', 'UTC'));
    }

    private function fields(array $override = []): array
    {
        return array_replace([
            'revision' => 0, 'status' => 'active', 'priority' => 'normal', 'is_watched' => true,
            'owner_user_id' => $this->staff->id, 'review_interval_days' => 30,
            'next_review_on' => '2026-10-13', 'notes' => '',
        ], $override);
    }

    private function url(string $suffix): string
    {
        return '/api/suppliers/'.$this->unit->id.'/'.$suffix;
    }

    public function test_reading_virtual_profiles_events_and_options_does_not_register_existing_suppliers(): void
    {
        $writer = app(SupplierProfileWriter::class);
        $data = $writer->data(null, $this->unit->id);
        $this->assertFalse($data['exists']);
        $this->assertFalse($data['is_watched']);
        $this->assertSame(0, $data['revision']);
        $this->assertSame('off', $data['review_state']);
        $this->getJson($this->url('events'))->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/suppliers/options')->assertOk()->assertJsonPath('today', '2026-10-13')
            ->assertJsonPath('default_date_from', '2026-07-16')->assertJsonPath('default_date_to', '2026-10-13')
            ->assertJsonPath('owners.0.id', $this->staff->id);
        $this->assertDatabaseCount('supplier_profiles', 0);
        $this->assertDatabaseCount('supplier_profile_events', 0);
        $this->assertDatabaseCount('units', 1);
    }

    public function test_explicit_new_unit_creation_is_idempotent_across_retries_later_edits_and_midnight(): void
    {
        $input = ['create_key' => (string) Str::uuid(), 'name' => 'Новый поставщик'];
        $created = $this->postJson('/api/suppliers', $input)->assertCreated()
            ->assertJsonPath('profile.is_watched', true)->assertJsonPath('profile.next_review_on', '2026-10-13')
            ->assertJsonPath('profile.revision', 1)->assertJsonPath('profile.review_state', 'today')->json();
        $this->postJson('/api/suppliers', $input)->assertOk()->assertJsonPath('unit.id', $created['unit']['id']);
        $this->postJson('/api/suppliers', [...$input, 'create_key' => strtoupper($input['create_key'])])->assertOk()->assertJsonPath('unit.id', $created['unit']['id']);
        $this->travelTo(Carbon::parse('2026-10-14 01:00:00', 'UTC'));
        $this->patchJson('/api/suppliers/'.$created['unit']['id'].'/profile', $this->fields([
            'revision' => 1, 'priority' => 'critical', 'notes' => 'Договорились о поставке',
        ]))->assertOk()->assertJsonPath('revision', 2);
        $this->postJson('/api/suppliers', $input)->assertOk()->assertJsonPath('profile.revision', 2)
            ->assertJsonPath('profile.priority', 'critical')->assertJsonPath('profile.next_review_on', '2026-10-13');
        $this->postJson('/api/suppliers', [...$input, 'name' => 'Другой поставщик'])->assertConflict();
        $this->assertDatabaseCount('units', 2);
        $this->assertDatabaseCount('supplier_profiles', 1);
        $this->assertDatabaseCount('supplier_profile_events', 2);
        $this->assertTrue(Unit::find($created['unit']['id'])->is_supplier);
        $this->postJson('/api/suppliers', ['create_key' => (string) Str::uuid(), 'unit_id' => $created['unit']['id']])->assertConflict();
        $this->postJson('/api/suppliers', ['create_key' => (string) Str::uuid(), 'name' => 'Дубликат', 'unit_id' => $created['unit']['id']])
            ->assertUnprocessable();
        $this->postJson('/api/suppliers', ['create_key' => (string) Str::uuid(), 'unit_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('unit_id');
    }

    public function test_existing_unit_is_added_explicitly_and_revision_controls_creation_and_all_changes(): void
    {
        $this->unit->update(['is_supplier' => false]);
        $this->patchJson($this->url('profile'), $this->fields(['revision' => 1]))->assertConflict();
        $this->patchJson($this->url('profile'), $this->fields())->assertOk()->assertJsonPath('exists', true)
            ->assertJsonPath('revision', 1)->assertJsonPath('owner.name', $this->staff->name);
        $this->assertTrue($this->unit->fresh()->is_supplier);
        $this->patchJson($this->url('profile'), $this->fields())->assertConflict();
        $this->patchJson($this->url('profile'), $this->fields(['revision' => 1, 'priority' => 'high', 'notes' => 'Проверить прайс']))
            ->assertOk()->assertJsonPath('revision', 2)->assertJsonPath('notes', 'Проверить прайс');
        $this->patchJson($this->url('profile'), $this->fields(['revision' => 1, 'notes' => 'Потерянное обновление']))->assertConflict();
        $events = $this->getJson($this->url('events'))->assertOk()->assertJsonPath('total', 2)->json('data');
        $this->assertSame('updated', $events[0]['type']);
        $this->assertSame(['before' => 'normal', 'after' => 'high'], $events[0]['changes']['priority']);
        $this->assertSame(['before' => '', 'after' => 'Проверить прайс'], $events[0]['changes']['notes']);
        $this->assertSame($this->staff->name, $events[0]['actor']['name']);
        $this->assertSame('created', $events[1]['type']);
        $this->assertDatabaseCount('units', 1);
    }

    public function test_review_uses_moscow_business_day_and_notes_do_not_change_schedule(): void
    {
        $this->patchJson($this->url('profile'), $this->fields(['review_interval_days' => 45]))->assertOk();
        $reviewed = $this->postJson($this->url('reviews'), ['revision' => 1, 'note' => 'Прайс и наличие проверены'])
            ->assertOk()->assertJsonPath('revision', 2)->assertJsonPath('next_review_on', '2026-11-27')
            ->assertJsonPath('review_state', 'scheduled')->json();
        $this->assertSame('2026-10-12T22:30:00.000000Z', $reviewed['last_reviewed_at']);
        $this->postJson($this->url('reviews'), ['revision' => 1, 'note' => 'Повтор'])->assertConflict();
        $this->travelTo(Carbon::parse('2026-10-15 01:00:00', 'UTC'));
        $this->postJson($this->url('notes'), ['revision' => 2, 'note' => 'Ждём коммерческое предложение', 'next_review_on' => '2027-01-01'])
            ->assertOk()->assertJsonPath('revision', 3)->assertJsonPath('next_review_on', '2026-11-27')
            ->assertJsonPath('last_reviewed_at', $reviewed['last_reviewed_at']);
        $this->postJson($this->url('reviews'), ['revision' => 3, 'note' => 'Уточнить срок', 'next_review_on' => '2026-10-20'])
            ->assertOk()->assertJsonPath('revision', 4)->assertJsonPath('next_review_on', '2026-10-20');
        $this->postJson($this->url('reviews'), ['revision' => 4, 'note' => ' '])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->postJson($this->url('reviews'), ['revision' => 4, 'note' => 'Проверка', 'next_review_on' => '2026-02-31'])
            ->assertUnprocessable()->assertJsonValidationErrors('next_review_on');
        $events = $this->getJson($this->url('events'))->assertOk()->assertJsonPath('total', 4)->json('data');
        $this->assertSame('reviewed', $events[0]['type']);
        $this->assertSame('note', $events[1]['type']);
        $this->assertSame([], $events[1]['changes']);
        $this->assertSame('Прайс и наличие проверены', $events[2]['body']);
    }

    public function test_owner_validation_matches_active_staff_including_legacy_crm_managers(): void
    {
        $customer = User::factory()->create(['type' => 'customer', 'status' => 'active']);
        $blocked = User::factory()->create(['type' => 'employee', 'status' => 'blocked']);
        $legacy = User::factory()->create(['type' => 'customer', 'status' => 'active']);
        $legacy->assignRole(Role::findOrCreate('manager', 'crm'));
        foreach ([$customer->id, $blocked->id, 999999] as $id) {
            $this->patchJson($this->url('profile'), $this->fields(['owner_user_id' => $id]))
                ->assertUnprocessable()->assertJsonValidationErrors('owner_user_id');
        }
        $this->patchJson($this->url('profile'), $this->fields(['owner_user_id' => $legacy->id]))
            ->assertOk()->assertJsonPath('owner.id', $legacy->id);
        $owners = $this->getJson('/api/suppliers/options')->assertOk()->json('owners');
        $this->assertEqualsCanonicalizing([$this->staff->id, $legacy->id], array_column($owners, 'id'));
        $this->patchJson($this->url('profile'), $this->fields(['revision' => 1, 'owner_user_id' => null]))
            ->assertOk()->assertJsonPath('owner', null);
        $this->getJson($this->url('events'))->assertOk()->assertJsonPath('data.0.changes.owner_name.before', $legacy->name)
            ->assertJsonPath('data.0.changes.owner_name.after', null);
    }

    public function test_control_states_respect_watch_status_and_preserve_archive_history_without_side_effects(): void
    {
        $revision = 0;
        foreach ([
            [['next_review_on' => null], 'unscheduled'],
            [['next_review_on' => '2026-10-12'], 'overdue'],
            [['next_review_on' => '2026-10-13'], 'today'],
            [['status' => 'potential', 'next_review_on' => '2026-10-14'], 'scheduled'],
            [['status' => 'paused', 'next_review_on' => '2026-10-12'], 'off'],
            [['is_watched' => false, 'next_review_on' => '2026-10-12'], 'off'],
            [['status' => 'archived', 'next_review_on' => '2026-10-12'], 'off'],
        ] as [$fields, $state]) {
            $this->patchJson($this->url('profile'), $this->fields([...$fields, 'revision' => $revision]))
                ->assertOk()->assertJsonPath('review_state', $state)->assertJsonPath('revision', ++$revision);
        }
        $this->assertDatabaseCount('supplier_profiles', 1);
        $this->assertDatabaseCount('supplier_profile_events', 7);
        $this->assertTrue($this->unit->fresh()->is_supplier);
        $this->assertDatabaseCount('supplier_pipeline_cards', 0);
        $this->assertDatabaseCount('supplier_orders', 0);
        $this->assertDatabaseCount('good_stock_movements', 0);
        Mail::assertNothingSent();
        $this->expectException(\LogicException::class);
        SupplierProfile::first()->delete();
    }

    public function test_timeline_is_paginated_append_only_and_preserves_actor_name_after_user_deletion(): void
    {
        $this->patchJson($this->url('profile'), $this->fields())->assertOk();
        $writer = app(SupplierProfileWriter::class);
        for ($revision = 1; $revision <= 31; $revision++) {
            $writer->note($this->unit, ['revision' => $revision, 'note' => 'Заметка '.$revision], $this->staff);
        }
        $replacement = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $this->actingAs($replacement);
        $this->staff->update(['name' => 'Переименованный пользователь']);
        $this->staff->delete();
        $this->getJson($this->url('events'))->assertOk()->assertJsonPath('total', 32)->assertJsonCount(30, 'data')
            ->assertJsonPath('data.0.body', 'Заметка 31')->assertJsonPath('data.0.actor.name', 'Анна Закупщик')
            ->assertJsonPath('data.0.actor.id', null);
        $this->getJson($this->url('events').'?page=2')->assertOk()->assertJsonCount(2, 'data');
        $this->expectException(\LogicException::class);
        SupplierProfileEvent::first()->update(['body' => 'Незаметная перезапись']);
    }

    public function test_profile_and_new_unit_creation_roll_back_if_history_cannot_be_recorded(): void
    {
        $event = 'eloquent.creating: '.SupplierProfileEvent::class;
        Event::listen($event, static function (): never {
            throw new \RuntimeException('History unavailable');
        });
        try {
            app(SupplierProfileWriter::class)->create(['create_key' => (string) Str::uuid(), 'name' => 'Не должен остаться'], $this->staff);
            $this->fail('Creation should fail when history cannot be written.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('History unavailable', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertDatabaseCount('units', 1);
        $this->assertDatabaseCount('supplier_profiles', 0);
        $this->assertDatabaseCount('supplier_profile_events', 0);
    }
}
