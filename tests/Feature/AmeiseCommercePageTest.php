<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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
            ->assertInertia(fn (Assert $page) => $page->component('Ameise/Commerce'));
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
