<?php

namespace Tests\Feature\Unit;

use App\Models\Building;
use App\Models\City;
use App\Models\Country;
use App\Models\Email;
use App\Models\Entity;
use App\Models\EntityClassification;
use App\Models\Field;
use App\Models\Industry;
use App\Models\Label;
use App\Models\Region;
use App\Models\Telephone;
use App\Models\Unit;
use App\Models\Uri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class UnitCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }

    public function test_both_creation_routes_need_only_a_name_and_never_write_an_external_placeholder(): void
    {
        Storage::shouldReceive('disk')->never();

        foreach (['/api/units', '/api/unit/store'] as $index => $url) {
            $response = $this->postJson($url, ['name' => '  Новая компания '.$index.'  '])
                ->assertCreated()
                ->assertJsonPath('name', 'Новая компания '.$index)
                ->assertJsonPath('is_customer', false)
                ->assertJsonPath('is_supplier', false)
                ->assertJsonCount(0, 'entities')
                ->assertJsonCount(0, 'emails');
            $this->assertDatabaseHas('units', ['id' => $response->json('id'), 'name' => 'Новая компания '.$index]);
        }

        $this->assertDatabaseCount('units', 2);
        $this->assertDatabaseCount('market_role_unit', 0);
        $this->assertDatabaseCount('unit_business_contexts', 0);
    }

    public function test_creation_saves_all_current_relations_and_one_primary_industry(): void
    {
        $country = Country::query()->create(['name' => 'Россия', 'сodeISO' => 'RU', 'сodeTelefon' => '7']);
        $region = Region::query()->create(['name' => 'Татарстан', 'country_id' => $country->id]);
        $city = City::query()->create(['name' => 'Казань', 'region_id' => $region->id]);
        $building = Building::query()->create(['address' => 'Мира, 10', 'city_id' => $city->id]);
        $apartment = $building->apartments()->create(['number' => '7А', 'type' => 'office']);
        $entity = Entity::query()->create(['name' => 'Юридическое лицо']);
        $classification = EntityClassification::query()->create(['name' => 'Производитель']);
        $field = Field::query()->create(['title' => 'Пищевая промышленность']);
        $label = Label::query()->forceCreate(['name' => 'В работе']);
        $industry = Industry::query()->create(['code' => '10.1', 'title' => 'Переработка мяса']);
        $secondary = Industry::query()->create(['code' => '10.2', 'title' => 'Переработка рыбы']);
        $email = Email::query()->create(['address' => 'office@example.test']);
        $telephone = Telephone::query()->create(['number' => '+79991234567']);
        $uri = Uri::query()->create(['address' => 'https://example.test']);

        $created = $this->postJson('/api/units', [
            'name' => 'Компания', 'is_customer' => true, 'is_supplier' => true,
            'buildings' => [$building->id], 'building_apartments' => [$building->id => $apartment->id],
            'cities' => [$city->id], 'entities' => [$entity->id], 'classifications' => [$classification->id],
            'fields' => [$field->id], 'labels' => [$label->id],
            'industries' => [$industry->id, $secondary->id], 'primary_industry_id' => $industry->id,
            'emails' => [$email->id], 'telephones' => [$telephone->id], 'uris' => [$uri->id],
        ])->assertCreated()->assertJsonPath('is_customer', true)->assertJsonPath('is_supplier', true)
            ->assertJsonPath('entities.0.id', $entity->id)
            ->assertJsonPath('classifications.0.id', $classification->id)
            ->assertJsonPath('buildings.0.pivot.apartment_id', $apartment->id);

        $unit = Unit::query()->findOrFail($created->json('id'));
        foreach ([
            'cities' => $city, 'entities' => $entity, 'classifications' => $classification,
            'fields' => $field, 'labels' => $label, 'emails' => $email,
            'telephones' => $telephone, 'uris' => $uri,
        ] as $relation => $model) {
            $this->assertSame([$model->id], $unit->{$relation}()->get()->modelKeys());
        }
        $this->assertSame($industry->id, $unit->primaryIndustry()->id);
        $this->assertDatabaseHas('industry_unit', [
            'unit_id' => $unit->id, 'industry_id' => $secondary->id, 'is_primary' => false,
        ]);
        $this->assertDatabaseCount('entities', 1);
        $this->assertDatabaseCount('building_entities', 0);
        $this->assertDatabaseCount('apartment_entity', 0);
        $this->assertDatabaseCount('market_role_unit', 0);
    }

    public function test_missing_relations_and_invalid_flags_are_rejected_before_creating_any_unit(): void
    {
        $relations = [
            'buildings', 'cities', 'emails', 'fields', 'industries', 'labels',
            'telephones', 'uris', 'entities', 'classifications',
        ];
        $payload = ['name' => 'Невалидная компания', 'is_customer' => 'yes', 'is_supplier' => 'yes'];
        foreach ($relations as $relation) {
            $payload[$relation] = [999999999];
        }

        $this->postJson('/api/units', $payload)->assertUnprocessable()->assertJsonValidationErrors([
            ...array_map(fn ($relation) => $relation.'.0', $relations), 'is_customer', 'is_supplier',
        ]);
        $this->assertDatabaseCount('units', 0);
    }

    public function test_creation_validates_name_relations_and_inline_contacts_without_saving_partial_records(): void
    {
        foreach ([
            ['name' => '   '],
            ['name' => str_repeat('Я', 256)],
            ['name' => ['unexpected']],
            ['cities' => 'not-an-array'],
            ['entities' => [['id' => 1]]],
            ['new_emails' => ['not-an-email']],
            ['new_telephones' => ['+7abc9991234567']],
            ['new_telephones' => ['123']],
            ['new_uris' => ['javascript:alert(1)']],
            ['new_uris' => ['ftp://example.test']],
            ['new_uris' => [['unexpected']]],
        ] as $invalid) {
            $key = array_key_first($invalid);
            $error = $key !== 'name' && is_array($invalid[$key]) ? $key.'.0' : $key;
            $this->postJson('/api/units', [...['name' => 'Компания'], ...$invalid])
                ->assertUnprocessable()->assertJsonValidationErrors($error);
        }

        $this->assertDatabaseCount('units', 0);
        $this->assertDatabaseCount('emails', 0);
        $this->assertDatabaseCount('telephones', 0);
        $this->assertDatabaseCount('uris', 0);
    }

    public function test_primary_industry_must_be_selected_and_can_be_omitted(): void
    {
        $industry = Industry::query()->create(['code' => '10.1', 'title' => 'Производство']);
        $this->postJson('/api/units', ['name' => 'Компания', 'primary_industry_id' => $industry->id])
            ->assertUnprocessable()->assertJsonValidationErrors('primary_industry_id');
        $this->assertDatabaseCount('units', 0);

        $created = $this->postJson('/api/units', [
            'name' => 'Компания', 'industries' => [$industry->id], 'primary_industry_id' => null,
        ])->assertCreated();
        $this->assertDatabaseHas('industry_unit', [
            'unit_id' => $created->json('id'), 'industry_id' => $industry->id, 'is_primary' => false,
        ]);
    }

    public function test_apartments_cannot_be_assigned_to_another_or_unselected_building(): void
    {
        $building = Building::query()->create(['address' => 'Мира, 1']);
        $other = Building::query()->create(['address' => 'Мира, 2']);
        $apartment = $other->apartments()->create(['number' => '1', 'type' => 'office']);

        foreach ([$building->id, $other->id] as $buildingId) {
            $this->postJson('/api/units', [
                'name' => 'Компания', 'buildings' => [$building->id],
                'building_apartments' => [$buildingId => $apartment->id],
                'new_emails' => ['should-not-exist@example.test'],
            ])->assertUnprocessable()->assertJsonValidationErrors('building_apartments.'.$buildingId);
        }
        $this->assertDatabaseCount('units', 0);
        $this->assertDatabaseCount('emails', 0);
        $this->assertDatabaseCount('building_unit', 0);
    }

    public function test_inline_contacts_normalize_reuse_and_attach_once_preserving_existing_owners(): void
    {
        $other = Unit::query()->create(['name' => 'Другая компания']);
        $email = Email::query()->create(['address' => 'office@example.test', 'name' => 'Отдел продаж']);
        $restored = Email::query()->create(['address' => 'archive@example.test', 'is_active' => false]);
        $restored->delete();
        $telephone = Telephone::query()->create(['number' => '79991234567']);
        $uri = Uri::query()->create(['address' => 'https://example.test']);
        $other->emails()->attach($email);
        $other->telephones()->attach($telephone);
        $other->uris()->attach($uri);

        $created = $this->postJson('/api/units', [
            'name' => 'Новый Unit',
            'emails' => [$email->id], 'telephones' => [$telephone->id], 'uris' => [$uri->id],
            'new_emails' => [' OFFICE@EXAMPLE.TEST ', 'office@example.test', 'archive@example.test', 'new@example.test'],
            'new_telephones' => ['8 (999) 123-45-67', '+7 999 123-45-67', '+44 20 7946 0958'],
            'new_uris' => ['example.test', 'https://example.test', 'new.example.test'],
        ])->assertCreated()->assertJsonCount(3, 'emails')->assertJsonCount(2, 'telephones')->assertJsonCount(2, 'uris');

        $id = $created->json('id');
        $this->assertDatabaseCount('emails', 3);
        $this->assertDatabaseCount('telephones', 2);
        $this->assertDatabaseCount('uris', 2);
        $this->assertDatabaseHas('email_unit', ['unit_id' => $id, 'email_id' => $email->id]);
        $this->assertDatabaseHas('telephone_unit', ['unit_id' => $id, 'telephone_id' => $telephone->id]);
        $this->assertDatabaseHas('unit_uri', ['unit_id' => $id, 'uri_id' => $uri->id]);
        $this->assertDatabaseHas('telephones', ['number' => '+442079460958']);
        $this->assertDatabaseHas('uris', ['address' => 'https://new.example.test']);
        $this->assertNull($restored->fresh()->deleted_at);
        $this->assertTrue($restored->fresh()->is_active);
        $this->assertSame('Отдел продаж', $email->fresh()->name);
        $this->assertSame([$email->id], $other->emails()->get()->modelKeys());
        $this->assertSame([$telephone->id], $other->telephones()->get()->modelKeys());
        $this->assertSame([$uri->id], $other->uris()->get()->modelKeys());
    }

    public function test_soft_deleted_email_id_is_rejected_instead_of_creating_an_invisible_relation(): void
    {
        $email = Email::query()->create(['address' => 'archive@example.test']);
        $email->delete();

        $this->postJson('/api/units', ['name' => 'Компания', 'emails' => [$email->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('emails.0');
        $this->assertDatabaseCount('units', 0);
        $this->assertDatabaseCount('email_unit', 0);
    }

    public function test_failure_in_last_contact_rolls_back_unit_pivots_new_contacts_and_restored_email(): void
    {
        $building = Building::query()->create(['address' => 'Мира, 3']);
        $entity = Entity::query()->create(['name' => 'Юридическое лицо']);
        $email = Email::query()->create(['address' => 'archive@example.test', 'is_active' => false]);
        $email->delete();
        $event = 'eloquent.creating: '.Uri::class;
        Event::listen($event, fn () => throw new RuntimeException('Simulated contact write failure'));
        $this->withoutExceptionHandling();

        try {
            $this->postJson('/api/units', [
                'name' => 'Откат', 'buildings' => [$building->id], 'entities' => [$entity->id],
                'new_emails' => ['archive@example.test', 'new@example.test'],
                'new_telephones' => ['+79991234567'], 'new_uris' => ['https://example.test'],
            ]);
            $this->fail('Expected the simulated contact write to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated contact write failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        foreach (['units', 'building_unit', 'entity_unit', 'email_unit', 'telephone_unit', 'telephones', 'uris'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('emails', 1);
        $this->assertTrue(Email::withTrashed()->findOrFail($email->id)->trashed());
        $this->assertFalse(Email::withTrashed()->findOrFail($email->id)->is_active);
    }

    public function test_empty_unit_file_root_can_be_listed_and_first_file_uploaded_without_a_placeholder(): void
    {
        Storage::fake('yandex');
        $created = $this->postJson('/api/units', ['name' => 'Компания без файлов'])->assertCreated();
        $id = $created->json('id');
        Storage::disk('yandex')->assertMissing("units/{$id}/placeholder.txt");
        $this->getJson("/api/units/{$id}/files")->assertOk()->assertJsonCount(0, 'files');
        $this->postJson("/api/units/{$id}/files", [
            'file' => UploadedFile::fake()->create('document.txt', 1, 'text/plain'),
        ])->assertCreated();
        Storage::disk('yandex')->assertExists("units/{$id}/document.txt");
    }

    public function test_both_creation_aliases_keep_the_staff_access_boundary(): void
    {
        Auth::guard('web')->logout();
        foreach (['/api/units', '/api/unit/store'] as $url) {
            $this->postJson($url, ['name' => 'Запрещено'])->assertUnauthorized();
        }

        foreach ([['type' => 'customer', 'status' => 'active'], ['type' => 'employee', 'status' => 'inactive']] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            foreach (['/api/units', '/api/unit/store'] as $url) {
                $this->postJson($url, ['name' => 'Запрещено'])->assertForbidden();
            }
        }
        $this->assertDatabaseCount('units', 0);
    }
}
