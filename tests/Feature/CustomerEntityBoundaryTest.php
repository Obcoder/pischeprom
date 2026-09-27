<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\Email;
use App\Models\Entity;
use App\Models\Good;
use App\Models\Order;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class CustomerEntityBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        Notification::fake();
        Queue::fake();
    }

    public function test_public_profile_cannot_overwrite_or_claim_an_existing_crm_entity_by_inn(): void
    {
        $user = User::factory()->create(['type' => 'customer']);
        $entity = $this->crmEntity();
        $original = $entity->fresh()->getRawOriginal();

        $this->actingAs($user)->post('/dashboard/profile', $this->profile($user, $entity->INN) + [
            'customer_created_by_user_id' => $user->id,
        ])->assertRedirect(route('dashboard'));

        $this->assertSame($original, $entity->fresh()->getRawOriginal());
        $this->assertSame(0, $entity->cities()->count());
        $this->assertDatabaseHas('entity_user', [
            'user_id' => $user->id, 'entity_id' => $entity->id, 'role' => 'customer', 'is_primary' => true,
        ]);
        $this->assertSame('Изменённое имя покупателя', $user->fresh()->name);

        // An old self-claimed owner pivot must not grant organization editing rights.
        $user->entities()->updateExistingPivot($entity->id, ['role' => 'owner']);
        $this->post('/dashboard/profile', $this->profile($user, $entity->INN))->assertRedirect(route('dashboard'));
        $this->assertSame($original, $entity->fresh()->getRawOriginal());
        $this->assertDatabaseHas('entity_user', ['user_id' => $user->id, 'entity_id' => $entity->id, 'role' => 'customer']);
    }

    public function test_registration_with_an_existing_inn_succeeds_without_changing_crm_data_or_contacts(): void
    {
        $entity = $this->crmEntity();
        $email = Email::query()->create(['address' => 'crm@example.test', 'source' => 'test', 'is_active' => true]);
        $entity->emails()->attach($email);
        $original = $entity->fresh()->getRawOriginal();
        $city = $this->city();

        $this->post('/register', [
            'account_type' => 'organization',
            'name' => 'Новый покупатель',
            'email' => 'new-buyer@example.test',
            'phone' => '+79995556677',
            'city_id' => $city->id,
            'password' => 'password',
            'password_confirmation' => 'password',
            'personal_data_consent' => true,
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
            'organization_inn' => $entity->INN,
            'organization_name' => 'Попытка замены названия',
            'organization_kpp' => '999999999',
            'organization_legal_address' => 'Подменённый адрес',
            'customer_created_by_user_id' => 1,
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseCount('entities', 1);
        $this->assertSame($original, $entity->fresh()->getRawOriginal());
        $this->assertSame([$email->id], $entity->emails()->pluck('emails.id')->all());
        $this->assertSame(0, $entity->telephones()->count());
        $this->assertSame(0, $entity->cities()->count());
        $this->assertDatabaseHas('entity_user', ['user_id' => auth()->id(), 'entity_id' => $entity->id, 'role' => 'customer']);
    }

    public function test_only_the_recorded_creator_can_edit_an_entity_created_through_the_public_profile(): void
    {
        $owner = User::factory()->create(['type' => 'customer']);
        $data = $this->profile($owner, '7700000022');
        $this->actingAs($owner)->post('/dashboard/profile', $data)->assertRedirect(route('dashboard'));
        $entity = Entity::query()->where('INN', $data['organization_inn'])->firstOrFail();
        $this->assertSame($owner->id, (int) $entity->customer_created_by_user_id);
        $this->assertDatabaseHas('entity_user', ['user_id' => $owner->id, 'entity_id' => $entity->id, 'role' => 'owner']);

        $this->post('/dashboard/profile', [...$data, 'organization_name' => 'Законное новое название'])
            ->assertRedirect(route('dashboard'));
        $this->assertSame('Законное новое название', $entity->fresh()->name);

        $other = User::factory()->create(['type' => 'customer']);
        $this->actingAs($other)->post('/dashboard/profile', $this->profile($other, $entity->INN))
            ->assertRedirect(route('dashboard'));
        $this->assertSame('Законное новое название', $entity->fresh()->name);
        $this->assertSame($owner->id, (int) $entity->fresh()->customer_created_by_user_id);
        $this->assertDatabaseHas('entity_user', ['user_id' => $other->id, 'entity_id' => $entity->id, 'role' => 'customer']);
    }

    public function test_registration_does_not_restore_a_deleted_crm_contact(): void
    {
        $entity = $this->crmEntity();
        $email = Email::query()->create(['address' => 'deleted@example.test', 'source' => 'test']);
        $entity->emails()->attach($email);
        $email->delete();

        $this->post('/register', [
            'account_type' => 'individual',
            'name' => 'Новый отдельный покупатель',
            'email' => $email->address,
            'password' => 'password',
            'password_confirmation' => 'password',
            'personal_data_consent' => true,
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertTrue($email->fresh()->trashed());
        $this->assertDatabaseMissing('entity_user', ['entity_id' => $entity->id, 'user_id' => auth()->id()]);
        $this->assertDatabaseHas('entities', ['name' => 'Новый отдельный покупатель', 'customer_created_by_user_id' => auth()->id()]);
    }

    public function test_claimed_entity_never_discloses_other_customers_orders_and_checkout_keeps_contacts_on_own_order(): void
    {
        $user = User::factory()->create(['type' => 'customer']);
        $other = User::factory()->create(['type' => 'customer']);
        $entity = $this->crmEntity();
        $this->actingAs($user)->post('/dashboard/profile', $this->profile($user, $entity->INN))
            ->assertRedirect(route('dashboard'));
        Order::query()->create(['entity_id' => $entity->id, 'created_by_user_id' => $other->id, 'number' => 'PRIVATE-OTHER']);
        Order::query()->create(['entity_id' => $entity->id, 'number' => 'PRIVATE-HISTORICAL']);
        $good = Good::query()->create(['name' => 'Товар для собственного заказа', 'is_published' => true]);

        $response = $this->postJson('/orders', [
            'items' => [['good_id' => $good->id, 'quantity' => 1]],
            'delivery_address' => 'Личный адрес доставки',
            'preferred_delivery_time' => 'Завтра',
            'customer_phone' => '+79990002233',
        ])->assertCreated();
        $order = Order::query()->findOrFail($response->json('order.id'));
        $this->assertSame($user->id, $order->created_by_user_id);
        $this->assertSame($entity->id, $order->entity_id);
        $this->assertSame('+79990002233', $order->contactTelephone->number);
        $this->assertSame('Личный адрес доставки', $order->buildings->sole()->address);
        $this->assertSame(0, $entity->telephones()->count());
        $this->assertSame(0, $entity->buildings()->count());

        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')->has('orders', 1)->where('orders.0.id', $order->id)
            ->missing('auth.user.user')->missing('auth.user.entities')->missing('entities.0.dadata_raw')
            ->missing('entities.0.buildings')->missing('entities.0.bank_account_number'));
    }

    private function crmEntity(): Entity
    {
        return Entity::query()->create([
            'name' => 'Проверенная CRM организация',
            'INN' => '7700000011',
            'legal_address' => 'Адрес из CRM',
            'bank_account_number' => '40702810000000000001',
        ]);
    }

    private function profile(User $user, string $inn): array
    {
        return [
            'account_type' => 'organization',
            'name' => 'Изменённое имя покупателя',
            'email' => $user->email,
            'city_id' => $this->city()->id,
            'organization_inn' => $inn,
            'organization_name' => 'Непроверенные реквизиты',
            'organization_full_name' => 'Полное непроверенное название',
            'organization_kpp' => '999999999',
            'organization_ogrn' => '9999999999999',
            'organization_legal_address' => 'Подменённый адрес',
            'organization_dadata_raw' => ['untrusted' => true],
        ];
    }

    private function city(): City
    {
        return City::query()->firstOrCreate(['name' => 'Город покупателя'], [
            'region_id' => Region::query()->firstOrCreate(['name' => 'Регион покупателя'], [
                'country_id' => Country::query()->firstOrCreate(['name' => 'Россия'], ['сodeISO' => 'RU'])->id,
            ])->id,
        ]);
    }
}
