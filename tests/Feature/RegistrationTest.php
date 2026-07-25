<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\Entity;
use App\Models\Telephone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_screen_cannot_be_rendered_if_support_is_disabled(): void
    {
        if (Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_new_users_can_register(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->post('/register', [
            'account_type' => 'individual',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+7 999 123-45-67',
            'password' => 'password',
            'password_confirmation' => 'password',
            'personal_data_consent' => true,
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('entities', ['name' => 'Test User']);
        $this->assertDatabaseHas('entity_user', [
            'user_id' => auth()->id(),
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('telephones', ['number' => '+79991234567']);
    }

    public function test_registration_attaches_user_to_entity_found_by_email(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $entity = Entity::query()->create(['name' => 'Existing customer']);
        $email = Email::query()->create([
            'address' => 'buyer@example.com',
            'source' => 'test',
            'is_active' => true,
        ]);
        $entity->emails()->attach($email);

        $this->post('/register', [
            'account_type' => 'individual',
            'name' => 'Buyer',
            'email' => 'BUYER@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'personal_data_consent' => true,
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseCount('entities', 1);
        $this->assertDatabaseHas('entity_user', [
            'entity_id' => $entity->id,
            'user_id' => auth()->id(),
        ]);
    }

    public function test_registration_attaches_user_to_entity_found_by_phone(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $entity = Entity::query()->create(['name' => 'Existing phone customer']);
        $telephone = Telephone::query()->create(['number' => '8 (999) 555-44-33']);
        $entity->telephones()->attach($telephone);

        $this->post('/register', [
            'account_type' => 'individual',
            'name' => 'Phone Buyer',
            'email' => 'phone-buyer@example.com',
            'phone' => '8 (999) 555-44-33',
            'password' => 'password',
            'password_confirmation' => 'password',
            'personal_data_consent' => true,
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseCount('entities', 1);
        $this->assertDatabaseHas('entity_user', [
            'entity_id' => $entity->id,
            'user_id' => auth()->id(),
        ]);
        $this->assertDatabaseCount('telephones', 1);
    }
}
