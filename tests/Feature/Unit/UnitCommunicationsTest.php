<?php

namespace Tests\Feature\Unit;

use App\Models\Email;
use App\Models\Entity;
use App\Models\MailMessage;
use App\Models\PhoneCall;
use App\Models\Telephone;
use App\Models\Unit;
use App\Models\Uri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UnitCommunicationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
    }

    public function test_contacts_support_create_update_detach_and_delete_for_unit_and_linked_entities(): void
    {
        $unit = $this->unit();
        $entity = Entity::query()->create(['name' => 'Associated company']);
        $unit->entities()->attach($entity);
        $this->actingAs($this->actor());

        $cases = [
            ['uris', 'address', 'example.test', 'https://example.test', 'https://new.example.test', Uri::class],
            ['telephones', 'number', '8 (999) 123-45-67', '+79991234567', '+79991234568', Telephone::class],
            ['emails', 'address', ' BUYER@EXAMPLE.TEST ', 'buyer@example.test', 'new@example.test', Email::class],
        ];

        foreach ($cases as [$type, $field, $input, $normalized, $replacement, $model]) {
            $base = "/api/units/{$unit->id}/communications/{$type}";
            $response = $this->postJson($base, [$field => $input, 'entity_id' => $entity->id])
                ->assertCreated()->assertJsonPath("data.{$field}", $normalized);
            $id = $response->json('data.id');
            $this->assertTrue($entity->{$type}()->whereKey($id)->exists());
            $this->assertFalse($unit->{$type}()->whereKey($id)->exists());

            $this->putJson("{$base}/{$id}", [$field => $replacement, 'entity_id' => $entity->id])
                ->assertOk()->assertJsonPath("data.{$field}", $replacement);
            $this->postJson($base, ['contact_id' => $id])->assertOk()->assertJsonPath('attached', true);
            $this->deleteJson("{$base}/{$id}", ['entity_id' => $entity->id, 'delete_record' => true])
                ->assertUnprocessable()->assertJsonValidationErrors('delete_record');
            $this->assertTrue($entity->{$type}()->whereKey($id)->exists());

            $this->deleteJson("{$base}/{$id}", ['entity_id' => $entity->id])->assertOk();
            $this->assertFalse($entity->{$type}()->whereKey($id)->exists());
            $this->assertNotNull($model::query()->find($id));
            $this->deleteJson("{$base}/{$id}", ['delete_record' => true])->assertOk();
            $this->assertNull($model::query()->find($id));
        }
    }

    public function test_read_aggregates_shared_contacts_once_with_all_owners_and_search_reuses_records(): void
    {
        $unit = $this->unit();
        $entity = Entity::query()->create(['name' => 'Associated company']);
        $unit->entities()->attach($entity);
        $email = Email::query()->create(['address' => 'shared@example.test', 'name' => 'Purchasing']);
        $unit->emails()->attach($email);
        $entity->emails()->attach($email);
        $uri = Uri::query()->create(['address' => 'https://company.example.test']);
        $entity->uris()->attach($uri);

        $this->actingAs($this->actor())
            ->getJson("/api/units/{$unit->id}/communications")
            ->assertOk()->assertJsonCount(1, 'data.emails')->assertJsonCount(2, 'data.emails.0.owners')
            ->assertJsonPath('data.emails.0.owners.1.id', $entity->id)
            ->assertJsonPath('data.uris.0.id', $uri->id);

        $this->getJson("/api/units/{$unit->id}/communications/emails/options?search=Purchasing")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $email->id);
        $this->postJson("/api/units/{$unit->id}/communications/emails", ['address' => 'SHARED@example.test'])
            ->assertOk()->assertJsonPath('created', false)->assertJsonPath('attached', false);
        $this->assertDatabaseCount('emails', 1);
    }

    public function test_mutations_require_verified_contact_manager_and_cannot_target_unrelated_owners_or_contacts(): void
    {
        $unit = $this->unit();
        $entity = Entity::query()->create(['name' => 'Unrelated company']);
        $email = Email::query()->create(['address' => 'unrelated@example.test']);
        $base = "/api/units/{$unit->id}/communications/emails";

        $this->postJson($base, ['address' => 'new@example.test'])->assertUnauthorized();
        $this->actingAs($this->actor(manage: false))
            ->postJson($base, ['address' => 'new@example.test'])->assertForbidden();
        $this->actingAs($this->actor(verified: false))
            ->postJson($base, ['address' => 'new@example.test'])->assertForbidden();
        $this->actingAs($this->actor())
            ->postJson($base, ['address' => 'new@example.test', 'entity_id' => $entity->id])->assertNotFound();
        $this->putJson("{$base}/{$email->id}", ['address' => 'changed@example.test'])->assertNotFound();
        $this->deleteJson("{$base}/{$email->id}", ['delete_record' => true])->assertNotFound();
        $this->postJson("/api/units/{$unit->id}/communications/uris", ['address' => 'javascript:alert(1)'])
            ->assertUnprocessable()->assertJsonValidationErrors('address');
        $this->assertSame('unrelated@example.test', $email->fresh()->address);
        $this->assertDatabaseCount('emails', 1);
    }

    public function test_telephone_with_call_history_can_be_detached_but_not_destroyed(): void
    {
        $unit = $this->unit();
        $telephone = Telephone::query()->create(['number' => '+79991234567']);
        $unit->telephones()->attach($telephone);
        $call = PhoneCall::query()->create(['telephone_id' => $telephone->id, 'provider' => 'manual']);
        $base = "/api/units/{$unit->id}/communications/telephones/{$telephone->id}";

        $this->actingAs($this->actor())->deleteJson($base, ['delete_record' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('delete_record');
        $this->assertTrue($unit->telephones()->whereKey($telephone->id)->exists());
        $this->assertSame($telephone->id, $call->fresh()->telephone_id);
        $this->deleteJson($base)->assertOk();
        $this->assertNotNull($telephone->fresh());
    }

    public function test_unit_call_history_includes_linked_entities_and_their_telephones_without_duplicates(): void
    {
        $unit = $this->unit();
        $entity = Entity::query()->create(['name' => 'Associated company']);
        $other = Entity::query()->create(['name' => 'Other company']);
        $unit->entities()->attach($entity);
        $directTelephone = Telephone::query()->create(['number' => '+79991234567']);
        $entityTelephone = Telephone::query()->create(['number' => '+79991234568']);
        $unit->telephones()->attach($directTelephone);
        $entity->telephones()->attach($entityTelephone);

        $included = collect([
            ['unit_id' => $unit->id],
            ['entity_id' => $entity->id],
            ['telephone_id' => $directTelephone->id],
            ['telephone_id' => $entityTelephone->id],
            ['unit_id' => $unit->id, 'entity_id' => $entity->id, 'telephone_id' => $entityTelephone->id],
        ])->map(fn (array $attributes) => PhoneCall::query()->create([
            ...$attributes, 'provider' => 'manual', 'direction' => 'in', 'started_at' => now(),
        ])->id);
        PhoneCall::query()->create(['entity_id' => $other->id, 'provider' => 'manual']);

        $response = $this->actingAs($this->actor())->getJson("/api/phone-calls?unit_id={$unit->id}")
            ->assertOk()->assertJsonPath('total', 5);
        $this->assertEqualsCanonicalizing($included->all(), array_column($response->json('data'), 'id'));
        $this->getJson("/api/phone-calls?unit_id={$unit->id}&direction=out")->assertJsonPath('total', 0);
    }

    public function test_unit_mail_history_includes_entity_email_without_duplicate_messages(): void
    {
        $unit = $this->unit();
        $entity = Entity::query()->create(['name' => 'Associated company']);
        $unit->entities()->attach($entity);
        $email = Email::query()->create(['address' => 'entity@example.test']);
        $entity->emails()->attach($email);
        $message = MailMessage::query()->create([
            'mailbox' => 'office@example.test', 'folder' => 'INBOX',
            'direction' => 'incoming', 'from_address' => $email->address,
        ]);
        $message->emails()->attach($email->id, ['role' => 'from']);

        $this->actingAs($this->actor())->getJson("/api/units/{$unit->id}/mail-messages")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $message->id)
            ->assertJsonPath('related_emails.0.entity_id', $entity->id);
        $unit->emails()->attach($email);
        $this->getJson("/api/units/{$unit->id}/mail-messages")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonCount(1, 'related_emails');
    }

    private function unit(): Unit
    {
        return Unit::query()->create(['name' => 'Communications Unit', 'is_customer' => true, 'is_supplier' => false]);
    }

    private function actor(bool $manage = true, bool $verified = true): User
    {
        $actor = User::factory()->create(['status' => 'active', 'email_verified_at' => $verified ? now() : null]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = $manage ? ['ai_sales.view', 'ai_sales.contexts.manage'] : ['ai_sales.view'];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'crm']);
        }

        $actor->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $actor;
    }
}
