<?php

namespace Tests\Feature;

use App\Mail\CustomerOrderCreatedMail;
use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodEntityPrice;
use App\Models\GoodPriceHistory;
use App\Models\GoodPriceTypeValue;
use App\Models\Measure;
use App\Models\Order;
use App\Models\PriceType;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\User;
use App\Services\Goods\EntityCabinetPrices;
use App\Services\Goods\GoodMeasurement;
use App\Services\Goods\GoodPriceAudit;
use App\Services\Goods\PublicGoodOffer;
use App\Services\MaxMessengerService;
use App\Services\Orders\CustomerOrderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoodPricingWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Good $good;

    private PriceType $type;

    private int $rub;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.asset_url', 'https://assets.example.test');
        $this->withHeader('X-Inertia-Version', hash('xxh128', 'https://assets.example.test'));
        Storage::fake('yandex');
        Http::preventStrayRequests();
        Queue::fake();
        Mail::fake();
        $this->staff = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $this->actingAs($this->staff);
        $this->rub = Currency::forceCreate(['code' => 'RUB', 'name' => 'Рубль'])->id;
        Currency::forceCreate(['code' => 'USD', 'name' => 'Доллар']);
        $this->good = Good::create(['name' => 'Товар с историей', 'is_published' => true]);
        $this->type = PriceType::create(['name' => 'Розничная', 'code' => 'retail', 'currency_id' => $this->rub, 'is_active' => true, 'is_public' => true]);
    }

    private function input(array $override = []): array
    {
        return array_replace([
            'scope' => 'common', 'target_id' => $this->type->id, 'revision' => 0,
            'method' => 'manual', 'amount' => 123.4567, 'amount_includes_vat' => true,
            'source_includes_vat' => true, 'source_vat_rate' => 20, 'vat_rate' => 20,
            'currency_id' => $this->rub, 'active' => true, 'rounding_step' => 0,
            'measurement' => $this->good->fresh()->measurement(),
        ], $override);
    }

    private function url(string $suffix = ''): string
    {
        return '/api/goods/'.$this->good->id.'/pricing'.$suffix;
    }

    private function customer(): array
    {
        $user = User::factory()->create(['type' => 'customer', 'status' => 'active']);
        $entity = Entity::forceCreate(['name' => 'Закрытые условия', 'customer_created_by_user_id' => $user->id]);
        $entity->users()->attach($user->id, ['role' => 'owner', 'status' => 'active', 'is_primary' => true]);
        $price = GoodEntityPrice::create(['good_id' => $this->good->id, 'entity_id' => $entity->id, 'currency_id' => $this->rub,
            'price_net' => 654.32, 'price_gross' => 785.184, 'vat_rate' => 20, 'is_active' => true,
            'manual_comment' => 'Секретный комментарий', 'pricing_result' => ['cost_net' => 321.09]]);

        return [$user, $entity, $price->fresh()];
    }

    public function test_price_lifecycle_is_atomic_versioned_and_preserves_before_after_and_actor(): void
    {
        $row = $this->putJson($this->url(), $this->input(['valid_to' => today()->addWeek()->toDateString()]))
            ->assertOk()->assertJsonPath('price_gross', 123.4567)->assertJsonPath('price_net', 102.8806)->assertJsonPath('revision', 1)->json();
        $updated = $this->putJson($this->url(), $this->input(['id' => $row['id'], 'revision' => 1, 'amount' => 144, 'comment' => 'Договорились', 'active' => false]))
            ->assertOk()->assertJsonPath('price_gross', 144)->assertJsonPath('revision', 2)->json();
        $this->putJson($this->url(), $this->input(['id' => $row['id'], 'revision' => 1, 'amount' => 1]))->assertConflict();
        $this->putJson($this->url(), $this->input())->assertConflict();
        $this->deleteJson($this->url('/common/'.$row['id']), ['revision' => 1])->assertConflict();
        $this->deleteJson($this->url('/common/'.$row['id']), ['revision' => $updated['revision']])->assertNoContent();
        $history = $this->getJson($this->url('/history'))->assertOk()->assertJsonPath('total', 3)->json('data');
        $this->assertSame('deleted', $history[0]['event']);
        $this->assertNull($history[0]['after']);
        $this->assertEquals(144, $history[0]['before']['price_gross']);
        $this->assertEquals(123.4567, $history[1]['before']['price_gross']);
        $this->assertSame($this->staff->name, $history[1]['actor_name']);
        $this->assertSame('Договорились', $history[1]['after']['manual_comment']);
        $this->assertNull($history[2]['before']);
        $this->assertDatabaseMissing('good_price_type_values', ['id' => $row['id']]);
        $this->assertDatabaseCount('good_price_histories', 3);
    }

    public function test_calculator_uses_source_currency_units_vat_margin_and_rounds_only_final_price(): void
    {
        $supplier = Unit::create(['name' => 'Поставщик']);
        $quotation = Quotation::create(['good_id' => $this->good->id, 'unit_id' => $supplier->id, 'price' => 1200,
            'currency_id' => $this->rub, 'measure_id' => Measure::where('name', 'кг')->value('id'), 'denominator' => 10]);
        $input = $this->input(['method' => 'margin', 'source_key' => 'quotation:'.$quotation->id, 'parameter' => 20, 'rounding_step' => 7]);
        $this->postJson($this->url('/preview'), $input)->assertOk()
            ->assertJsonPath('cost_net', 100)->assertJsonPath('price_gross', 154)->assertJsonPath('price_net', 128.3333);
        $this->postJson($this->url('/preview'), [...$input, 'parameter' => 100])->assertUnprocessable()->assertJsonValidationErrors('parameter');
        $this->postJson($this->url('/preview'), [...$input, 'currency_id' => Currency::where('code', 'USD')->value('id')])->assertUnprocessable()->assertJsonValidationErrors('currency_id');
        $quotation->update(['good_id' => Good::create(['name' => 'Другой'])->id]);
        $this->postJson($this->url('/preview'), $input)->assertUnprocessable()->assertJsonValidationErrors('source_key');
        $quotation->update(['good_id' => $this->good->id, 'measure_id' => Measure::where('name', 'л')->value('id')]);
        $this->postJson($this->url('/preview'), $input)->assertUnprocessable()->assertJsonValidationErrors('source_key');
        $quotation->update(['measure_id' => Measure::where('name', 'кг')->value('id')]);
        app(GoodMeasurement::class)->update($this->good, ['measure_id' => Measure::where('name', 'коробка')->value('id'), 'unit_weight_kg' => 10]);
        $this->postJson($this->url('/preview'), $input)->assertUnprocessable()->assertJsonValidationErrors('measurement');
        $this->postJson($this->url('/preview'), [...$input, 'measurement' => $this->good->fresh()->measurement(), 'method' => 'markup', 'parameter' => 25, 'rounding_step' => 0])->assertOk()->assertJsonPath('cost_net', 1000)->assertJsonPath('price_gross', 1500);
        $this->getJson($this->url())->assertOk()->assertJsonPath('partners.0.name', 'Поставщик');
        $this->assertDatabaseCount('good_price_histories', 0);
    }

    public function test_unit_conversion_includes_private_prices_and_retains_calculation_basis(): void
    {
        [$user, $entity] = $this->customer();
        GoodEntityPrice::query()->delete();
        $private = $this->putJson($this->url(), $this->input(['scope' => 'entity', 'target_id' => $entity->id, 'amount' => 120]))->assertOk()->json();
        app(GoodMeasurement::class)->update($this->good, ['measure_id' => Measure::where('name', 'коробка')->value('id'), 'unit_weight_kg' => 10]);
        $price = GoodEntityPrice::findOrFail($private['id']);
        $this->assertEquals(1200, $price->price_gross);
        $this->assertEquals(1200, $price->pricing_input['amount']);
        $entry = GoodPriceHistory::latest('id')->first();
        $this->assertSame('unit_changed', $entry->event);
        $this->assertSame('кг', $entry->before['measurement']['unit_label']);
        $this->assertSame('коробка', $entry->after['measurement']['unit_label']);
        $this->assertEquals(2, $price->revision);
    }

    public function test_audit_failure_rolls_back_price_write_and_old_endpoints_are_also_audited(): void
    {
        $price = GoodPriceTypeValue::create(['good_id' => $this->good->id, 'price_type_id' => $this->type->id, 'price_gross' => 100]);
        $this->patchJson('/api/goods/'.$this->good->id.'/price-type-values/'.$price->id, ['price_gross' => 200])->assertOk();
        $this->assertDatabaseCount('good_price_histories', 2);
        $this->mock(GoodPriceAudit::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        try {
            $price->fresh()->update(['price_gross' => 300]);
            $this->fail('Expected the audit error');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertEquals(200, $price->fresh()->price_gross);
        $this->assertDatabaseCount('good_price_histories', 2);
    }

    public function test_migration_seeds_an_honest_baseline_without_changing_existing_prices(): void
    {
        $price = GoodPriceTypeValue::create(['good_id' => $this->good->id, 'price_type_id' => $this->type->id, 'price_gross' => 432.1234]);
        $migration = require database_path('migrations/2026_10_12_180000_create_good_price_history_and_entity_prices.php');
        $migration->down();
        $migration->up();
        $entry = GoodPriceHistory::sole();
        $this->assertSame('baseline', $entry->event);
        $this->assertEquals(432.1234, $entry->after['price_gross']);
        $this->assertSame('RUB', $entry->after['currency_code']);
        $this->assertEquals(1, $entry->after['measurement']['kilograms_per_unit']);
        $this->assertEquals(432.1234, $price->fresh()->price_gross);
    }

    public function test_private_prices_require_real_entity_access_and_are_absent_from_public_offer(): void
    {
        [$owner, $entity, $price] = $this->customer();
        $claimant = User::factory()->create(['type' => 'customer', 'status' => 'active']);
        // Even a historical pivot labelled owner is not proof of identity.
        $entity->users()->attach($claimant->id, ['role' => 'owner', 'status' => 'active']);
        $service = app(EntityCabinetPrices::class);
        $this->assertCount(1, $service->forUser($owner));
        $this->assertCount(0, $service->forUser($claimant));
        $this->putJson('/api/pricing/entities/'.$entity->id.'/access/'.$claimant->id, ['allowed' => true])->assertOk();
        $this->assertCount(1, $service->forUser($claimant));
        $this->actingAs($claimant)->putJson('/api/pricing/entities/'.$entity->id.'/access/'.$claimant->id, ['allowed' => true])->assertForbidden();
        foreach ([$this->url(), $this->url('/history'), '/api/pricing/entities', '/api/pricing/entities/'.$entity->id.'/access'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
        $this->assertNull(app(PublicGoodOffer::class)->pricesFor($this->good->fresh())->first());
        $this->assertArrayNotHasKey('manual_comment', $service->forUser($owner)->sole());
        $this->assertArrayNotHasKey('pricing_result', $service->forUser($owner)->sole());
        $this->actingAs($this->staff)->putJson('/api/pricing/entities/'.$entity->id.'/access/'.$claimant->id, ['allowed' => false])->assertOk();
        $this->assertCount(0, $service->forUser($claimant));
        $entity->users()->updateExistingPivot($owner->id, ['status' => 'inactive']);
        $this->assertCount(0, $service->forUser($owner));
    }

    public function test_private_price_validity_and_staff_changes_do_not_publish_into_general_prices(): void
    {
        [$owner, $entity, $price] = $this->customer();
        $this->assertDatabaseCount('good_price_type_values', 0);
        $service = app(EntityCabinetPrices::class);
        foreach ([['valid_from' => today()->addDay()], ['valid_from' => null, 'valid_to' => today()->subDay()], ['valid_to' => null, 'is_active' => false]] as $change) {
            $price->update($change);
            $this->assertCount(0, $service->forUser($owner));
        }
        $price->update(['is_active' => true, 'valid_from' => today(), 'valid_to' => today()]);
        $this->assertCount(1, $service->forUser($owner));
        $this->good->update(['is_published' => false]);
        $this->assertCount(0, $service->forUser($owner));
        $this->assertDatabaseCount('good_price_calculations', 0);
        $this->assertDatabaseCount('good_price_type_values', 0);
    }

    public function test_saved_calculation_applies_once_in_the_current_unit_and_can_be_disabled_with_its_type(): void
    {
        app(GoodMeasurement::class)->update($this->good, ['measure_id' => Measure::where('name', 'коробка')->value('id'), 'unit_weight_kg' => 10]);
        $calculation = \App\Models\GoodPriceCalculation::create(['good_id' => $this->good->id, 'currency_id' => $this->rub, 'sale_net_per_kg' => 100, 'purchase_net_per_kg' => 80]);
        $row = $this->putJson($this->url(), $this->input(['method' => 'saved', 'calculation_id' => $calculation->id]))
            ->assertOk()->assertJsonPath('price_gross', 1200)->assertJsonPath('result.cost_net', 800)->json();
        $this->type->update(['is_active' => false]);
        $this->putJson($this->url(), $this->input(['id' => $row['id'], 'revision' => 1, 'amount' => 1200]))->assertUnprocessable();
        $this->putJson($this->url(), $this->input(['id' => $row['id'], 'revision' => 1, 'amount' => 1200, 'active' => false]))->assertOk()->assertJsonPath('active', false);
    }

    public function test_bulk_offers_and_mail_catalog_cannot_select_private_prices(): void
    {
        [$owner] = $this->customer();
        $this->actingAs($owner);
        $mail = app(\App\Services\Mail\MailOfferCatalog::class)->resolve([['good_id' => $this->good->id]]);
        $this->assertNull($mail[0]['price']);
        $offers = app(\App\Services\CommercialOffers\ProductOfferBuilder::class)->searchProducts($this->good->name)->items();
        $this->assertStringNotContainsString('785.184', json_encode($offers));
        $this->assertStringNotContainsString('654.32', json_encode($offers));
        auth()->forgetGuards();
        $this->app['auth']->guard()->logout();
        $this->getJson($this->url('/history'))->assertUnauthorized();
        $this->putJson($this->url(), $this->input())->assertUnauthorized();
    }

    public function test_private_order_cannot_be_reassigned_to_another_entity(): void
    {
        [$owner, $entity, $price] = $this->customer();
        $order = Order::create(['entity_id' => $entity->id, 'has_private_prices' => true]);
        $other = Entity::create(['name' => 'Другая организация']);
        try {
            app(\App\Services\Orders\OrderWriter::class)->save($order, ['entity_id' => $other->id]);
            $this->fail('Private conditions must not transfer to another Entity');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('entity_id', $exception->errors());
        }
        $this->assertEquals($entity->id, $order->fresh()->entity_id);
    }

    public function test_logout_clears_encrypted_cabinet_history(): void
    {
        [$owner] = $this->customer();
        $this->actingAs($owner)->get('/dashboard', ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('encryptHistory', true);
        $this->post('/logout')->assertRedirect('/');
        $this->get('/', ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('clearHistory', true);
        $this->app['auth']->forgetGuards();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    private function checkout(GoodEntityPrice $price): array
    {
        return ['entity_id' => $price->entity_id, 'items' => [['good_id' => $price->good_id, 'price_id' => $price->id,
            'price_revision' => $price->revision, 'quantity' => 2, 'measurement' => $this->good->measurement(), 'price_gross' => 1]],
            'delivery_address' => 'Санкт-Петербург, Тестовая, 1', 'preferred_delivery_time' => 'Завтра 10–12', 'customer_phone' => '+79991234567'];
    }

    public function test_private_checkout_uses_server_price_and_redacts_email_and_messenger_totals(): void
    {
        [$owner, $entity, $price] = $this->customer();
        $sent = [];
        $max = $this->mock(MaxMessengerService::class);
        $max->shouldReceive('sendToChat')->andReturnUsing(function ($chat, $text) use (&$sent) {
            $sent[] = $text;

            return true;
        });
        config(['services.max.manager_chat_ids' => ['manager'], 'services.orders.manager_emails' => ['manager@example.test']]);
        $owner->update(['max_chat_id' => 'customer']);
        $response = $this->actingAs($owner)->postJson('/dashboard/entity-orders', $this->checkout($price))->assertCreated()->assertJsonPath('order.total_amount', 1570.368);
        $order = Order::findOrFail($response->json('order.id'));
        $this->assertTrue($order->has_private_prices);
        $this->assertEquals(785.184, $order->items->sole()->price_gross);
        $this->assertSame($price->id, $order->items->sole()->snapshot['private_price']['id']);
        $html = (new CustomerOrderCreatedMail($order))->render();
        foreach (['785', '1 570', '654', 'Секретный'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
        $this->assertCount(2, $sent);
        foreach ($sent as $text) {
            $this->assertStringNotContainsString('1 570', $text);
        }
        Mail::assertSent(CustomerOrderCreatedMail::class);
        $this->get('/dashboard', ['X-Inertia' => 'true'])->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('props.privatePrices.0.price_gross', 785.184)->assertJsonPath('encryptHistory', true);
    }

    public function test_private_checkout_rejects_other_entity_stale_revision_expired_and_public_channel(): void
    {
        [$owner, $entity, $price] = $this->customer();
        $this->mock(CustomerOrderNotificationService::class)->shouldNotReceive('notify');
        $foreign = User::factory()->create(['type' => 'customer', 'status' => 'active']);
        $this->actingAs($foreign)->postJson('/dashboard/entity-orders', $this->checkout($price))->assertNotFound();
        $this->actingAs($owner)->postJson('/orders', $this->checkout($price))->assertUnprocessable();
        $payload = $this->checkout($price);
        $price->update(['price_gross' => 888]);
        $this->postJson('/dashboard/entity-orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('items.0.price_id');
        $price->update(['valid_to' => today()->subDay()]);
        $this->postJson('/dashboard/entity-orders', $this->checkout($price->fresh()))->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }
}
