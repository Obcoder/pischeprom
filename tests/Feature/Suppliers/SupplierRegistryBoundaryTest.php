<?php

namespace Tests\Feature\Suppliers;

use App\Models\Entity;
use App\Models\Purchase;
use App\Models\SupplierOrder;
use App\Models\SupplierProfile;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierRegistryBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::shouldReceive('mailer')->never();
        Queue::fake();
    }

    public function test_every_registry_endpoint_requires_active_verified_staff_even_with_a_mail_permission(): void
    {
        $unit = Unit::create(['name' => 'Private supplier']);
        $customer = $this->staff(['type' => 'customer']);
        $customer->givePermissionTo(Permission::findOrCreate('mail.send', 'crm'));
        $inactive = $this->staff(['status' => 'inactive']);
        $unverified = $this->staff(['email_verified_at' => null]);

        $requests = [
            ['GET', '/api/suppliers'], ['GET', '/api/suppliers/options'], ['GET', '/api/suppliers/export'],
            ['GET', "/api/suppliers/{$unit->id}"], ['GET', "/api/suppliers/{$unit->id}/analytics"],
            ['GET', "/api/suppliers/{$unit->id}/events"], ['POST', '/api/suppliers'],
            ['PATCH', "/api/suppliers/{$unit->id}/profile"], ['POST', "/api/suppliers/{$unit->id}/reviews"],
            ['POST', "/api/suppliers/{$unit->id}/notes"],
        ];
        foreach ($requests as [$method, $url]) {
            $this->json($method, $url)->assertUnauthorized();
        }
        foreach ([$customer, $inactive, $unverified] as $actor) {
            $this->actingAs($actor);
            foreach ($requests as [$method, $url]) {
                $this->json($method, $url)->assertForbidden();
            }
        }

        $this->assertDatabaseCount('supplier_profiles', 0);
        $this->assertDatabaseCount('supplier_profile_events', 0);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_staff_and_legacy_crm_managers_can_read_without_obtaining_mail_capability(): void
    {
        $unit = Unit::create(['name' => 'Supplier']);
        $staff = $this->staff();
        $manager = $this->staff(['type' => 'customer']);
        $manager->assignRole(Role::findOrCreate('manager', 'crm'));

        foreach ([$staff, $manager] as $actor) {
            $this->actingAs($actor)->getJson('/api/suppliers')->assertOk()
                ->assertHeader('Cache-Control', 'no-store, private');
            $this->getJson("/api/suppliers/{$unit->id}")->assertOk()
                ->assertJsonPath('profile.exists', false)->assertJsonPath('capabilities.can_send', false);
            $owners = $this->getJson('/api/suppliers/options')->assertOk()->json('owners');
            $this->assertContains($actor->id, array_column($owners, 'id'));
        }

        $this->assertDatabaseCount('supplier_profiles', 0);
    }

    public function test_gets_never_register_suppliers_or_write_data_and_only_explicit_add_enters_the_list(): void
    {
        $actor = $this->staff();
        $historical = Unit::create(['name' => 'Already has supplier evidence', 'is_supplier' => true]);
        $virtual = Unit::create(['name' => 'Only an existing Unit', 'is_supplier' => false]);
        $entity = Entity::create(['name' => 'Historical purchase Entity']);
        $entity->units()->attach($historical->id);
        Purchase::create(['entity_id' => $entity->id, 'date' => '2026-10-13', 'amount' => 999999]);
        SupplierOrder::create([
            'unit_id' => $historical->id, 'status' => 'sent', 'sent_at' => now(),
            'to' => ['supplier@example.test'], 'cc' => [],
            'created_by' => $actor->id, 'updated_by' => $actor->id,
            'create_key' => (string) Str::uuid(), 'create_payload_hash' => hash('sha256', 'boundary historical order'),
        ]);
        $writes = [];
        $recordWrites = true;
        DB::listen(function (QueryExecuted $query) use (&$writes, &$recordWrites): void {
            if ($recordWrites && preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $this->actingAs($actor);

        foreach (['watched', 'all', 'archived'] as $scope) {
            $this->getJson('/api/suppliers?scope='.$scope)->assertOk()
                ->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0)->assertJsonPath('summary.suppliers_count', 0);
        }
        $this->getJson('/api/suppliers/options')->assertOk();
        $export = $this->get('/api/suppliers/export?scope=all')->assertOk();
        $this->assertStringNotContainsString($historical->name, $export->streamedContent());
        foreach ([$historical, $virtual] as $unit) {
            $this->getJson("/api/suppliers/{$unit->id}")->assertOk()->assertJsonPath('profile.exists', false);
            $this->getJson("/api/suppliers/{$unit->id}/events")->assertOk()->assertJsonCount(0, 'data');
            $this->getJson("/api/suppliers/{$unit->id}/analytics")->assertOk();
        }

        $this->assertSame([], $writes, 'Reading the registry, export, events or analytics must not mutate application data.');
        $this->assertDatabaseCount('supplier_profiles', 0);
        $this->assertDatabaseCount('supplier_profile_events', 0);
        $this->assertFalse($virtual->fresh()->is_supplier);
        $this->assertTrue($historical->fresh()->is_supplier);
        $this->assertDatabaseCount('units', 2);
        $this->assertDatabaseCount('purchases', 1);
        $this->assertDatabaseCount('supplier_orders', 1);

        $recordWrites = false;
        $this->postJson('/api/suppliers', ['create_key' => (string) Str::uuid(), 'unit_id' => $virtual->id])
            ->assertCreated()->assertJsonPath('profile.exists', true);
        $this->getJson('/api/suppliers?scope=all')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.unit.id', $virtual->id)->assertJsonPath('summary.suppliers_count', 1);
        $this->assertTrue($virtual->fresh()->is_supplier);
        $this->assertDatabaseCount('supplier_profiles', 1);
        $this->assertDatabaseCount('units', 2);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_csv_neutralizes_formula_prefixes_in_supplier_and_owner_cells_without_breaking_records(): void
    {
        $actor = $this->staff(['name' => ' =HYPERLINK("https://example.invalid","owner")']);
        $names = ['=2+2', '+SUM(1,2)', '-SUM(1,2)', '@SUM(1,2)', " \t\r\n=2+2", '=2+2;one supplier'];
        foreach ($names as $name) {
            $unit = Unit::create(['name' => $name, 'is_supplier' => true]);
            SupplierProfile::create(['unit_id' => $unit->id, 'owner_user_id' => $actor->id, 'is_watched' => true]);
        }

        $response = $this->actingAs($actor)->get('/api/suppliers/export?scope=all')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $response->streamedContent());
        rewind($stream);
        $header = fgetcsv($stream, null, ';', '"', '');
        $rows = [];
        while (($row = fgetcsv($stream, null, ';', '"', '')) !== false) {
            $this->assertCount(count($header), $row);
            $this->assertStringStartsWith("'", $row[1]);
            $this->assertSame("'".$actor->name, $row[5]);
            $rows[] = $row;
        }
        fclose($stream);

        $this->assertCount(count($names), $rows);
        $actual = array_column($rows, 1);
        foreach ($names as $name) {
            $this->assertContains("'".$name, $actual);
        }
        $this->assertDatabaseCount('supplier_profiles', count($names));
        Queue::assertNothingPushed();
    }

    private function staff(array $attributes = []): User
    {
        return User::factory()->create([...['status' => 'active', 'type' => 'employee', 'email_verified_at' => now()], ...$attributes]);
    }
}
