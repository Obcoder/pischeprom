<?php

namespace Tests\Feature\Unit;

use App\Models\Entity;
use App\Models\Label;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UnitPageRelationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        Mail::assertNothingSent();

        parent::tearDown();
    }

    public function test_telephone_dictionary_route_has_the_ziggy_name_used_by_the_unit_page(): void
    {
        $this->assertTrue(Route::has('telephones.index'));
        $this->assertSame('/api/telephones', route('telephones.index', absolute: false));

        $source = (string) file_get_contents(resource_path('js/Composables/useUnitPage.js'));

        $this->assertStringContainsString("route('telephones.index')", $source);
    }

    public function test_new_label_is_created_and_attached_without_mass_assignment_failure(): void
    {
        $unit = Unit::query()->create([
            'name' => 'Unit with a new label',
            'is_customer' => true,
            'is_supplier' => false,
        ]);

        $this->postJson("/api/units/{$unit->id}/labels", [
            'name' => 'Группа компаний',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Label attached.')
            ->assertJsonPath('data.name', 'Группа компаний');

        $label = Label::query()->where('name', 'Группа компаний')->firstOrFail();

        $this->assertDatabaseHas('label_unit', [
            'unit_id' => $unit->id,
            'label_id' => $label->id,
        ]);
        $this->assertSame(1, Label::query()->where('name', 'Группа компаний')->count());
        $this->assertSame(1, $unit->labels()->whereKey($label->id)->count());
        $this->assertSame(0, Entity::query()->count());

        $this->postJson("/api/units/{$unit->id}/labels", [
            'name' => 'Группа компаний',
        ])->assertOk();

        $this->assertSame(1, Label::query()->where('name', 'Группа компаний')->count());
        $this->assertSame(1, $unit->labels()->whereKey($label->id)->count());
    }

    public function test_existing_label_id_is_attached_without_creating_another_label(): void
    {
        $unit = Unit::query()->create([
            'name' => 'Unit with an existing label',
            'is_customer' => true,
            'is_supplier' => false,
        ]);
        $label = new Label;
        $label->name = 'Существующая метка';
        $label->save();

        $this->postJson("/api/units/{$unit->id}/labels", [
            'label_id' => $label->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $label->id);

        $this->assertSame(1, Label::query()->count());
        $this->assertSame(1, $unit->labels()->whereKey($label->id)->count());
        $this->assertSame(0, Entity::query()->count());
    }

    public function test_unit_components_do_not_ship_hidden_autofocus_attributes(): void
    {
        foreach ([
            'js/Components/Unit/UnitOverviewCard.vue',
            'js/Components/Unit/UnitFilesTab.vue',
            'js/Components/Unit/UnitAdminTab.vue',
        ] as $path) {
            $source = (string) file_get_contents(resource_path($path));

            $this->assertStringNotContainsString('autofocus', $source);
        }
    }
}
