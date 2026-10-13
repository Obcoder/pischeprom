<?php

namespace Tests\Feature\Suppliers;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuppliersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_workspace_requires_verified_staff_and_opens_unit_without_registering_it(): void
    {
        $this->get('/Ameise/suppliers')->assertRedirect();
        $this->actingAs(User::factory()->create(['type' => 'customer', 'status' => 'active']));
        $this->get('/Ameise/suppliers')->assertForbidden();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active', 'email_verified_at' => null]));
        $this->get('/Ameise/suppliers')->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
        $unit = Unit::create(['name' => 'Выбранный поставщик']);
        $this->get('/Ameise/suppliers?unit_id='.$unit->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Ameise/Suppliers')->where('initialUnitId', $unit->id));
        $this->assertDatabaseCount('supplier_profiles', 0);
        $this->assertFalse($unit->fresh()->is_supplier);
        $this->getJson('/Ameise/suppliers?unit_id=-1')->assertUnprocessable();
    }
}
