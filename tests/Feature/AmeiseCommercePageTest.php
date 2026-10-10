<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AmeiseCommercePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }

    public function test_commerce_route_renders_the_combined_workspace(): void
    {
        $this->get(route('Ameise.commerce'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Ameise/Commerce')
                ->where('auth.permissions.sales.manage', false));
    }

    public function test_verified_admin_can_manage_sales_in_commerce(): void
    {
        $user = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $user->assignRole(Role::findOrCreate('admin', 'crm'));
        $this->actingAs($user);

        $this->get(route('Ameise.commerce'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Ameise/Commerce')
                ->where('auth.permissions.sales.manage', true));
    }

    public function test_blocked_admin_cannot_access_commerce(): void
    {
        $user = User::factory()->create(['type' => 'employee', 'status' => 'blocked']);
        $user->assignRole(Role::findOrCreate('admin', 'crm'));
        $this->actingAs($user);

        $this->get(route('Ameise.commerce'))->assertForbidden();
    }

    public function test_unverified_admin_cannot_manage_sales_in_commerce(): void
    {
        $user = User::factory()->unverified()->create(['type' => 'employee', 'status' => 'active']);
        $user->assignRole(Role::findOrCreate('admin', 'crm'));
        $this->actingAs($user);

        $this->get(route('Ameise.commerce'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Ameise/Commerce')
                ->where('auth.permissions.sales.manage', false));
    }

    public function test_old_purchase_bookmarks_redirect_to_the_combined_workspace(): void
    {
        foreach (['/Ameise/Purchases', '/Ameise/Purchases/'] as $url) {
            $this->get($url)
                ->assertStatus(301)
                ->assertRedirectToRoute('Ameise.commerce');
        }
    }
}
