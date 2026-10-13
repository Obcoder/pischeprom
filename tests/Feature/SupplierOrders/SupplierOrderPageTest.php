<?php

namespace Tests\Feature\SupplierOrders;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierOrderPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_is_available_only_to_verified_active_staff(): void
    {
        $this->get('/Ameise/supplier-orders')->assertRedirect();
        $this->actingAs(User::factory()->create(['type' => 'customer', 'status' => 'active']));
        $this->get('/Ameise/supplier-orders')->assertForbidden();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'inactive']));
        $this->get('/Ameise/supplier-orders')->assertForbidden();
        $staff = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $this->actingAs($staff)->get('/Ameise/supplier-orders')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Ameise/SupplierOrders'));
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active', 'email_verified_at' => null]));
        $this->get('/Ameise/supplier-orders')->assertRedirect(route('verification.notice'));
    }
}
