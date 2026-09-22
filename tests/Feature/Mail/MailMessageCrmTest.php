<?php

namespace Tests\Feature\Mail;

use App\Models\Building;
use App\Models\City;
use App\Models\Country;
use App\Models\Email;
use App\Models\Entity;
use App\Models\Lead;
use App\Models\MailMessage;
use App\Models\Region;
use App\Models\Telephone;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MailMessageCrmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
    }

    public function test_message_signature_yields_reviewable_candidates_without_creating_records(): void
    {
        $message = $this->message([
            'text' => "Здравствуйте!\nООО «Поставщик»\nИНН: 7801234567\nТелефон: 8 (999) 123-45-67\nEmail: SALES@factory.test\nСайт: www.factory.test\nАдрес: Санкт-Петербург, ул. Ленина, д. 10",
            'html' => '<p>Подпись <a href="mailto:office@factory.test">Написать</a><a href="https://factory.test/catalog">Каталог</a></p>',
        ]);

        $this->actingAs($this->staff())->getJson($this->url($message))
            ->assertOk()->assertJsonPath('candidates.phones', ['+79991234567'])
            ->assertJsonPath('candidates.tax_ids', ['7801234567'])
            ->assertJsonFragment(['ООО «Поставщик»'])
            ->assertJsonFragment(['sales@factory.test'])
            ->assertJsonFragment(['office@factory.test'])
            ->assertJsonFragment(['https://www.factory.test'])
            ->assertJsonFragment(['https://factory.test/catalog'])
            ->assertJsonPath('candidates.addresses.0', 'Адрес: Санкт-Петербург, ул. Ленина, д. 10');

        foreach (['emails', 'entities', 'units', 'telephones', 'buildings', 'leads'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_html_only_signature_extracts_links_and_lines_without_loading_remote_content(): void
    {
        $message = $this->message([
            'text' => null,
            'html' => '<div>АО «Завод»</div><div>Адрес: Курск, улица Мира, 7</div><a href="tel:+79991234567">Позвонить</a><a href="mailto:info@factory.test?subject=Hi">Почта</a><a href="javascript:alert(1)">Опасная ссылка</a><img src="https://external.test/tracking">',
        ]);
        $this->actingAs($this->staff())->getJson($this->url($message))->assertOk()
            ->assertJsonPath('candidates.phones', ['+79991234567'])
            ->assertJsonPath('candidates.addresses', ['Адрес: Курск, улица Мира, 7'])
            ->assertJsonPath('candidates.websites', [])
            ->assertJsonFragment(['info@factory.test']);
        Http::assertNothingSent();
    }

    public function test_guests_customers_blocked_and_unverified_staff_cannot_use_crm(): void
    {
        $message = $this->message();
        $this->getJson($this->url($message))->assertUnauthorized();
        $this->postJson($this->url($message, '/entities'), ['name' => 'Запрещённый'])->assertUnauthorized();

        foreach ([
            $this->staff(['type' => 'customer']),
            $this->staff(['status' => 'blocked']),
            $this->staff(['email_verified_at' => null]),
        ] as $user) {
            $this->actingAs($user)->getJson($this->url($message))->assertForbidden();
            $this->getJson('/api/mail-crm/options')->assertForbidden();
            $this->postJson($this->url($message, '/entities'), ['name' => 'Запрещённый'])->assertForbidden();
            $this->postJson("/api/mail-messages/{$message->id}/lead")->assertForbidden();
        }
        $this->assertDatabaseCount('entities', 0);
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_legacy_admin_and_explicit_mail_permission_can_use_the_workspace(): void
    {
        $admin = $this->staff(['type' => 'customer']);
        $admin->assignRole(Role::findOrCreate('admin', 'crm'));
        $this->actingAs($admin)->getJson('/api/mail-crm/options')->assertOk();
        $operator = $this->staff(['type' => 'customer']);
        Permission::findOrCreate('mail.send', 'crm');
        $operator->givePermissionTo('mail.send');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($operator)->getJson('/api/mail-crm/entities?search=Example')->assertOk();
    }

    public function test_create_and_link_entity_reuses_existing_tax_identity_and_preserves_previous_links(): void
    {
        $message = $this->message();
        $oldEntity = Entity::query()->create(['name' => 'Другой контрагент']);
        $email = Email::query()->create(['address' => $message->from_address]);
        $email->entities()->attach($oldEntity->id);
        $unit = Unit::query()->create(['name' => 'Производство']);
        $payload = ['name' => 'Поставщик', 'INN' => '7801234567', 'unit_id' => $unit->id];
        $this->actingAs($this->staff());

        $created = $this->postJson($this->url($message, '/entities'), $payload)->assertCreated()->assertJsonPath('created', true);
        $id = $created->json('record.id');
        $this->postJson($this->url($message, '/entities'), [...$payload, 'name' => 'Другое название того же ИНН'])
            ->assertOk()->assertJsonPath('created', false)->assertJsonPath('record.id', $id);
        $this->assertDatabaseCount('entities', 2);
        $this->assertDatabaseHas('email_entity', ['email_id' => $email->id, 'entity_id' => $oldEntity->id]);
        $this->assertDatabaseHas('email_entity', ['email_id' => $email->id, 'entity_id' => $id]);
        $this->assertDatabaseHas('entity_unit', ['entity_id' => $id, 'unit_id' => $unit->id]);
        $this->assertDatabaseHas('email_mail_message', ['mail_message_id' => $message->id, 'email_id' => $email->id, 'role' => 'from']);
        $this->getJson($this->url($message))->assertJsonFragment(['id' => $id, 'name' => 'Поставщик', 'INN' => '7801234567']);
    }

    public function test_conflicting_legal_names_return_validation_error_without_binding_or_overwriting(): void
    {
        $entity = Entity::query()->create(['name' => 'Совпавшее название', 'INN' => '7801111111']);
        $message = $this->message();
        $this->actingAs($this->staff())->postJson($this->url($message, '/entities'), ['name' => $entity->name, 'INN' => '7802222222'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertSame('7801111111', $entity->fresh()->INN);
        $this->assertDatabaseCount('emails', 0);
    }

    public function test_accepted_company_lookup_keeps_supported_fields_and_raw_evidence_without_overwriting_existing_values(): void
    {
        $entity = Entity::query()->create(['name' => 'Клиент', 'INN' => '7801234567', 'legal_address' => 'Подтверждённый адрес']);
        $message = $this->message();
        $raw = ['value' => 'ООО Клиент', 'data' => ['inn' => '7801234567', 'management' => ['name' => 'Иванов Иван Иванович']]];
        $this->actingAs($this->staff())->postJson($this->url($message, '/entities'), [
            'name' => 'ООО Клиент', 'INN' => '7801234567', 'KPP' => '780101001',
            'legal_address' => 'Адрес справочника', 'dadata_raw' => $raw,
        ])->assertOk()->assertJsonPath('record.id', $entity->id);

        $entity->refresh();
        $this->assertSame('Подтверждённый адрес', $entity->legal_address);
        $this->assertSame('780101001', $entity->KPP);
        $this->assertSame($raw, $entity->dadata_raw);
        $this->assertNotNull($entity->dadata_loaded_at);
        $this->postJson($this->url($message, '/entities'), ['name' => 'Oversized', 'dadata_raw' => ['raw' => str_repeat('x', 100001)]])
            ->assertUnprocessable()->assertJsonValidationErrors('dadata_raw');
    }

    public function test_unit_email_phone_and_website_actions_preserve_contact_identity_and_are_repeatable(): void
    {
        $message = $this->message();
        $entity = Entity::query()->create(['name' => 'Клиент']);
        $this->actingAs($this->staff());
        $unitResponse = $this->postJson($this->url($message, '/units'), ['name' => 'Завод', 'entity_id' => $entity->id])->assertCreated();
        $unitId = $unitResponse->json('record.id');
        $this->postJson($this->url($message, '/units'), ['name' => 'Завод'])->assertOk()->assertJsonPath('record.id', $unitId);
        $this->assertDatabaseHas('entity_unit', ['entity_id' => $entity->id, 'unit_id' => $unitId]);

        $legacy = Telephone::query()->create(['number' => '89991234567']);
        $canonical = Telephone::query()->create(['number' => '+79991234567']);
        for ($i = 0; $i < 2; $i++) {
            $this->postJson($this->url($message, '/telephones'), ['number' => '8 (999) 123-45-67', 'unit_id' => $unitId, 'entity_id' => $entity->id])
                ->assertOk()->assertJsonPath('record.id', $canonical->id);
        }
        $this->assertDatabaseHas('telephones', ['id' => $legacy->id]);
        $this->assertDatabaseCount('telephone_unit', 1);
        $this->assertDatabaseCount('entity_telephone', 1);

        $this->postJson($this->url($message, '/websites'), ['address' => 'FACTORY.test/', 'unit_id' => $unitId])->assertCreated()
            ->assertJsonPath('record.address', 'https://factory.test');
        $this->postJson($this->url($message, '/websites'), ['address' => 'https://factory.test/', 'unit_id' => $unitId])->assertOk();
        $this->assertDatabaseCount('uris', 1);
        $this->assertDatabaseCount('unit_uri', 1);

        $this->postJson($this->url($message, '/emails'), ['address' => 'Contact@Factory.test', 'unit_id' => $unitId, 'entity_id' => $entity->id])
            ->assertCreated()->assertJsonPath('record.address', 'contact@factory.test');
        $contact = Email::query()->where('address', 'contact@factory.test')->firstOrFail();
        $this->assertDatabaseHas('email_unit', ['email_id' => $contact->id, 'unit_id' => $unitId]);
        $this->assertDatabaseHas('email_entity', ['email_id' => $contact->id, 'entity_id' => $entity->id]);
        // A signature address must not be recorded as a fabricated envelope participant.
        $this->assertDatabaseMissing('email_mail_message', ['email_id' => $contact->id]);
        Http::assertNothingSent();
    }

    public function test_building_is_reused_and_linked_to_selected_entity_and_unit(): void
    {
        $message = $this->message();
        $country = Country::query()->create(['name' => 'Россия', 'сodeISO' => 'RU']);
        $region = Region::query()->create(['name' => 'Курская область', 'country_id' => $country->id]);
        $city = City::query()->create(['name' => 'Курск', 'region_id' => $region->id]);
        $entity = Entity::query()->create(['name' => 'Клиент']);
        $unit = Unit::query()->create(['name' => 'Склад']);
        $payload = ['city_id' => $city->id, 'address' => 'ул. Ленина, 10', 'entity_id' => $entity->id, 'unit_id' => $unit->id, 'postcode' => '305000'];
        $this->actingAs($this->staff());
        $first = $this->postJson($this->url($message, '/buildings'), $payload)->assertCreated();
        $this->postJson($this->url($message, '/buildings'), $payload)->assertOk()->assertJsonPath('record.id', $first->json('record.id'));
        $this->assertDatabaseCount('buildings', 1);
        $building = Building::query()->firstOrFail();
        $this->assertTrue($building->entities()->whereKey($entity->id)->exists());
        $this->assertTrue($building->units()->whereKey($unit->id)->exists());
        $this->getJson('/api/mail-crm/cities?search='.urlencode('Курск'))->assertOk()->assertJsonPath('items.0.id', $city->id);
    }

    public function test_invalid_targets_urls_phones_and_outgoing_messages_cannot_create_records(): void
    {
        $message = $this->message();
        $unit = Unit::query()->create(['name' => 'Unit']);
        $this->actingAs($this->staff());
        $this->postJson($this->url($message, '/telephones'), ['number' => '123'])->assertUnprocessable();
        $this->postJson($this->url($message, '/websites'), ['address' => 'javascript:alert(1)', 'unit_id' => $unit->id])->assertUnprocessable();
        $this->postJson($this->url($message, '/websites'), ['address' => 'https://user:password@example.test', 'unit_id' => $unit->id])->assertUnprocessable();
        $this->postJson($this->url($message, '/websites'), ['address' => 'https://example.test', 'unit_id' => 999999])->assertUnprocessable();
        $this->postJson($this->url($message, '/buildings'), ['address' => 'Адрес'])->assertUnprocessable();
        $message->update(['direction' => 'outgoing']);
        $this->postJson($this->url($message, '/entities'), ['name' => 'Не создавать'])->assertUnprocessable();
        $this->assertDatabaseCount('entities', 0);
        $this->assertDatabaseCount('telephones', 0);
        $this->assertDatabaseCount('uris', 0);
    }

    public function test_repeated_lead_action_returns_original_record_without_changing_it(): void
    {
        $message = $this->message();
        $this->actingAs($this->staff());
        $url = "/api/mail-messages/{$message->id}/lead";
        $first = $this->postJson($url, ['title' => 'Первый лид'])->assertCreated()->assertJsonPath('created', true);
        $this->postJson($url, ['title' => 'Не заменять первый'])->assertOk()->assertJsonPath('created', false)
            ->assertJsonPath('lead.id', $first->json('lead.id'))->assertJsonPath('lead.title', 'Первый лид');
        $this->assertDatabaseCount('leads', 1);
    }

    public function test_existing_duplicate_leads_are_retained_and_no_further_lead_is_created(): void
    {
        $message = $this->message();
        $one = Lead::query()->create(['mail_message_id' => $message->id, 'source' => 'email', 'title' => 'Исторический 1', 'status' => 'closed']);
        $two = Lead::query()->create(['mail_message_id' => $message->id, 'source' => 'email', 'title' => 'Исторический 2', 'status' => 'open']);
        $this->actingAs($this->staff())->postJson("/api/mail-messages/{$message->id}/lead")
            ->assertOk()->assertJsonPath('created', false)->assertJsonPath('lead.id', $one->id);
        $this->assertDatabaseCount('leads', 2);
        $this->assertDatabaseHas('leads', ['id' => $two->id, 'title' => 'Исторический 2']);
    }

    private function message(array $attributes = []): MailMessage
    {
        return MailMessage::query()->create([
            'mailbox' => 'office@example.test', 'folder' => 'INBOX', 'direction' => 'incoming',
            'from_address' => 'buyer@factory.test', 'subject' => 'Запрос предложения', 'text' => 'Здравствуйте!',
            ...$attributes,
        ]);
    }

    private function staff(array $attributes = []): User
    {
        return User::factory()->create(['type' => 'employee', 'status' => 'active', 'email_verified_at' => now(), ...$attributes]);
    }

    private function url(MailMessage $message, string $suffix = ''): string
    {
        return "/api/mail-messages/{$message->id}/crm".$suffix;
    }
}
