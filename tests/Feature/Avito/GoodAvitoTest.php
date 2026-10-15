<?php

namespace Tests\Feature\Avito;

use App\Models\AvitoAutoloadFeed;
use App\Models\AvitoListingGoodLink;
use App\Models\AvitoPublication;
use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodEntityPrice;
use App\Models\GoodPriceTypeValue;
use App\Models\Measure;
use App\Models\PriceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoodAvitoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
        Cache::clear();
        config([
            'cache.default' => 'array',
            'avito.enabled' => true,
            'avito.client_id' => 'good-client',
            'avito.client_secret' => 'good-secret',
            'avito.api_base_url' => 'https://api.avito.ru',
            'avito.token_url' => 'https://api.avito.ru/token',
            'avito.allowed_hosts' => ['api.avito.ru'],
            'avito.mutations_enabled' => true,
        ]);
        Http::preventStrayRequests();
    }

    public function test_context_is_local_and_excludes_private_unpublished_and_inactive_price_types(): void
    {
        [$good, $price, $link] = $this->fixture();
        GoodEntityPrice::create(['good_id' => $good->id, 'entity_id' => Entity::create(['name' => 'Клиент'])->id, 'currency_id' => $price->currency_id, 'price_gross' => 71717, 'price_net' => 71717, 'vat_rate' => 0, 'is_active' => true]);
        foreach ([['is_public' => false], ['is_active' => false]] as $flags) {
            $type = PriceType::create($flags + ['name' => 'Закрытая', 'code' => uniqid(), 'is_public' => true, 'is_active' => true]);
            GoodPriceTypeValue::create(['good_id' => $good->id, 'price_type_id' => $type->id, 'price_gross' => 91919, 'is_published' => true]);
        }
        $type = PriceType::create(['name' => 'Черновик', 'code' => uniqid(), 'is_public' => true, 'is_active' => true]);
        GoodPriceTypeValue::create(['good_id' => $good->id, 'price_type_id' => $type->id, 'price_gross' => 81818, 'is_published' => false]);
        $this->getJson("/api/avito/goods/{$good->id}")->assertOk()
            ->assertJsonCount(1, 'good.prices')->assertJsonPath('good.prices.0.id', $price->id)
            ->assertJsonPath('good.prices.0.revision', 1)->assertJsonPath('links.0.id', $link->id)
            ->assertJsonPath('measurement.unit_label', 'кг')->assertDontSee('91919')->assertDontSee('81818')->assertDontSee('71717');
        Http::assertNothingSent();
    }

    public function test_attach_verifies_remote_ownership_and_does_not_reassign_another_goods_listing(): void
    {
        [$good] = $this->fixture(false);
        $this->fakeRemote();
        $this->putJson("/api/avito/goods/{$good->id}/links", ['account_id' => 321, 'item_id' => 7001])
            ->assertOk()->assertJsonPath('link.good_id', $good->id)->assertJsonPath('listing.status', 'active');
        $other = Good::create(['name' => 'Другой товар']);
        $this->putJson("/api/avito/goods/{$other->id}/links", ['account_id' => 321, 'item_id' => 7001])
            ->assertConflict()->assertJsonPath('category', 'good_link_conflict');
        $this->assertDatabaseHas('avito_listing_good_links', ['good_id' => $good->id, 'avito_item_id' => 7001]);
        $this->assertDatabaseCount('avito_listing_good_links', 1);
    }

    public function test_attach_rejects_wrong_account_and_malformed_success_without_creating_a_link(): void
    {
        [$good] = $this->fixture(false);
        $this->fakeRemote(['https://api.avito.ru/core/v1/accounts/321/items/7001/' => Http::response([])]);
        $this->putJson("/api/avito/goods/{$good->id}/links", ['account_id' => 999, 'item_id' => 7001])
            ->assertConflict()->assertJsonPath('category', 'good_link_account_mismatch');
        $this->putJson("/api/avito/goods/{$good->id}/links", ['account_id' => 321, 'item_id' => 7001])
            ->assertStatus(502)->assertJsonPath('category', 'listing_invalid_response');
        $this->assertDatabaseCount('avito_listing_good_links', 0);
    }

    public function test_link_operations_are_scoped_to_the_open_good(): void
    {
        [$good, $price, $link] = $this->fixture();
        $other = Good::create(['name' => 'Другой товар']);
        $base = "/api/avito/goods/{$other->id}/links/{$link->id}";
        $this->deleteJson($base)->assertNotFound();
        $this->getJson($base.'/listing')->assertNotFound();
        $this->postJson($base.'/preview', ['fields' => ['price'], 'price_value_id' => $price->id])->assertNotFound();
        $this->postJson($base.'/apply', ['fields' => ['price'], 'price_value_id' => $price->id])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_saved_price_push_has_explicit_unit_rounding_confirmation_and_history(): void
    {
        [$good, $price, $link] = $this->fixture();
        $good->update(['measure_id' => Measure::firstOrCreate(['name' => 'коробка'])->id, 'unit_weight_kg' => 10]);
        $price->update(['price_gross' => 2330.4]);
        $payload = ['fields' => ['price'], 'price_value_id' => $price->id];
        $preview = $this->postJson($this->base($good, $link).'/preview', $payload)->assertOk()
            ->assertJsonPath('preview.price.good_value', 2330.4)->assertJsonPath('preview.price.avito_value', 2330)
            ->assertJsonPath('preview.price.unit_label', 'коробка')->assertJsonPath('preview.price.basis', 'good_unit');
        $this->postJson($this->base($good, $link).'/apply', $payload)->assertUnprocessable();
        Http::assertNothingSent();
        $this->fakeRemote();
        $this->postJson($this->base($good, $link).'/apply', $payload + [
            'confirmed' => true, 'expected_fingerprint' => $preview->json('preview.source_fingerprint'),
        ])->assertOk()->assertJsonPath('transfer.price.avito_value', 2330)->assertJsonPath('history.0.status', 'applied');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/update_price') && $request['price'] === 2330);
        $this->assertDatabaseHas('avito_listing_good_transfers', ['avito_listing_good_link_id' => $link->id, 'status' => 'applied']);
    }

    public function test_stale_price_or_unit_is_rejected_before_remote_requests(): void
    {
        [$good, $price, $link] = $this->fixture();
        $payload = ['fields' => ['price'], 'price_value_id' => $price->id];
        $preview = $this->postJson($this->base($good, $link).'/preview', $payload)->assertOk();
        $price->update(['price_gross' => 999]);
        $this->postJson($this->base($good, $link).'/apply', $payload + [
            'confirmed' => true, 'expected_fingerprint' => $preview->json('preview.source_fingerprint'),
        ])->assertConflict()->assertJsonPath('category', 'good_transfer_stale');
        $preview = $this->postJson($this->base($good, $link).'/preview', $payload)->assertOk();
        $good->update(['measure_id' => Measure::firstOrCreate(['name' => 'шт'])->id]);
        $this->postJson($this->base($good, $link).'/apply', $payload + [
            'confirmed' => true, 'expected_fingerprint' => $preview->json('preview.source_fingerprint'),
        ])->assertConflict()->assertJsonPath('category', 'good_transfer_stale');
        Http::assertNothingSent();
    }

    public function test_change_during_account_verification_cancels_external_mutation(): void
    {
        [$good, $price, $link] = $this->fixture();
        $payload = ['fields' => ['price'], 'price_value_id' => $price->id];
        $preview = $this->postJson($this->base($good, $link).'/preview', $payload)->assertOk();
        $this->fakeRemote(['https://api.avito.ru/core/v1/accounts/321/items/7001/' => function () use ($price) {
            $price->update(['price_gross' => 999]);

            return Http::response(['status' => 'active']);
        }]);
        $this->postJson($this->base($good, $link).'/apply', $payload + [
            'confirmed' => true, 'expected_fingerprint' => $preview->json('preview.source_fingerprint'),
        ])->assertConflict()->assertJsonPath('category', 'good_transfer_stale');
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/update_price'));
    }

    public function test_deleted_link_after_remote_success_does_not_report_failed_price_update(): void
    {
        [$good, $price, $link] = $this->fixture();
        $payload = ['fields' => ['price'], 'price_value_id' => $price->id];
        $preview = $this->postJson($this->base($good, $link).'/preview', $payload)->assertOk();
        $this->fakeRemote(['https://api.avito.ru/core/v1/items/7001/update_price' => function () use ($link) {
            $link->delete();

            return Http::response(['ok' => true]);
        }]);
        $this->postJson($this->base($good, $link).'/apply', $payload + [
            'confirmed' => true, 'expected_fingerprint' => $preview->json('preview.source_fingerprint'),
        ])->assertOk()->assertJsonPath('transfer.status', 'applied')
            ->assertJsonPath('transfer.link_changed', true)->assertJsonPath('link', null);
        $this->assertDatabaseHas('avito_api_calls', ['endpoint' => 'https://api.avito.ru/core/v1/items/7001/update_price', 'status' => 'success']);
    }

    public function test_lost_write_response_is_unknown_and_not_retried(): void
    {
        [$good, $price, $link] = $this->fixture();
        $payload = ['fields' => ['price'], 'price_value_id' => $price->id];
        $preview = $this->postJson($this->base($good, $link).'/preview', $payload)->assertOk();
        $this->fakeRemote(['https://api.avito.ru/core/v1/items/7001/update_price' => Http::failedConnection()]);
        $this->postJson($this->base($good, $link).'/apply', $payload + [
            'confirmed' => true, 'expected_fingerprint' => $preview->json('preview.source_fingerprint'),
        ])->assertStatus(502)->assertJsonPath('category', 'good_transfer_unknown');
        $this->assertDatabaseHas('avito_listing_good_transfers', ['avito_listing_good_link_id' => $link->id, 'mode' => 'apply', 'status' => 'unknown']);
        $this->assertDatabaseCount('avito_api_calls', 3);
        $this->assertNull($link->fresh()->last_applied_at);
    }

    public function test_nonpublic_other_good_or_entity_price_cannot_be_selected(): void
    {
        [$good, $price, $link] = $this->fixture();
        $price->priceType->update(['is_public' => false]);
        $this->postJson($this->base($good, $link).'/preview', ['fields' => ['price'], 'price_value_id' => $price->id])
            ->assertUnprocessable()->assertJsonPath('category', 'good_transfer_price');
        $price->priceType->update(['is_public' => true]);
        $other = Good::create(['name' => 'Другой товар']);
        $price->update(['good_id' => $other->id]);
        $this->postJson($this->base($good, $link).'/preview', ['fields' => ['price'], 'price_value_id' => $price->id])
            ->assertUnprocessable()->assertJsonPath('category', 'good_transfer_price');
        $entityPrice = GoodEntityPrice::forceCreate(['id' => 99999, 'good_id' => $good->id, 'entity_id' => Entity::create(['name' => 'Клиент'])->id, 'currency_id' => $price->currency_id, 'price_gross' => 71717, 'price_net' => 71717, 'vat_rate' => 0, 'is_active' => true]);
        $this->postJson($this->base($good, $link).'/preview', ['fields' => ['price'], 'price_value_id' => $entityPrice->id])
            ->assertUnprocessable()->assertJsonValidationErrors('price_value_id');
        Http::assertNothingSent();
    }

    public function test_missing_unit_currency_gross_and_expired_price_are_blocked(): void
    {
        [$good, $price, $link] = $this->fixture();
        $payload = ['fields' => ['price'], 'price_value_id' => $price->id];
        $good->update(['measure_id' => null]);
        $this->postJson($this->base($good, $link).'/preview', $payload)->assertOk()->assertJsonPath('preview.price.can_apply', false);
        $good->update(['measure_id' => Measure::firstOrCreate(['name' => 'кг'])->id]);
        $price->currency->forceFill(['code' => 'USD'])->save();
        $this->postJson($this->base($good, $link).'/preview', $payload)->assertOk()->assertJsonPath('preview.price.can_apply', false);
        $price->currency->forceFill(['code' => 'RUB'])->save();
        $price->update(['price_gross' => null, 'price_net' => 100]);
        $this->postJson($this->base($good, $link).'/preview', $payload)->assertOk()->assertJsonPath('preview.price.can_apply', false);
        $price->update(['price_gross' => 233, 'valid_to' => now()->subDay()]);
        $this->postJson($this->base($good, $link).'/preview', $payload)->assertOk()->assertJsonPath('preview.price.can_apply', false);
        Http::assertNothingSent();
    }

    public function test_feed_managed_price_and_link_are_protected_and_new_feed_wont_duplicate_listing(): void
    {
        [$good, $price, $link] = $this->fixture();
        $publication = $this->publication($good, 7001);
        $publication->revisions()->create(['version' => 1, 'status' => 'approved', 'is_current' => true, 'approved_at' => now(), 'selected_fields' => ['price'], 'payload_snapshot' => ['price' => 233], 'source_snapshot' => []]);
        $this->postJson($this->base($good, $link).'/preview', ['fields' => ['price'], 'price_value_id' => $price->id])
            ->assertOk()->assertJsonPath('preview.price.can_apply', false)->assertJsonPath('preview.price.managed_publication_id', $publication->id);
        $this->deleteJson($this->base($good, $link))->assertConflict();
        $this->deleteJson('/api/avito/listings/7001/good-link', ['account_id' => 321])->assertConflict();
        $this->postJson('/api/avito/publications', ['account_id' => 321, 'good_id' => $good->id])
            ->assertConflict()->assertJsonPath('category', 'autoload_existing_listing');
        Http::assertNothingSent();
    }

    public function test_external_autoload_prevents_a_price_push_that_would_be_overwritten(): void
    {
        [$good, $price, $link] = $this->fixture();
        $payload = ['fields' => ['price'], 'price_value_id' => $price->id];
        $preview = $this->postJson($this->base($good, $link).'/preview', $payload)->assertOk();
        $this->fakeRemote(['https://api.avito.ru/core/v1/accounts/321/items/7001/' => Http::response(['status' => 'active', 'autoload_item_id' => 'external-1'])]);
        $this->postJson($this->base($good, $link).'/apply', $payload + [
            'confirmed' => true, 'expected_fingerprint' => $preview->json('preview.source_fingerprint'),
        ])->assertConflict()->assertJsonPath('category', 'good_transfer_managed_publication');
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/update_price'));
    }

    public function test_publication_list_and_status_counts_can_be_scoped_to_good(): void
    {
        [$good] = $this->fixture();
        $this->publication($good, 7001);
        $other = Good::create(['name' => 'Другой товар']);
        $this->publication($other, 7002);
        $this->getJson('/api/avito/publications?account_id=321&good_id='.$good->id)
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.good_id', $good->id)
            ->assertJsonPath('status_counts.published', 1)->assertJsonPath('meta.total', 1);
    }

    private function fixture(bool $linked = true): array
    {
        $currency = Currency::forceCreate(['name' => 'Рубль', 'code' => 'RUB']);
        $type = PriceType::create(['name' => 'Розничная', 'code' => 'retail', 'currency_id' => $currency->id, 'is_active' => true, 'is_public' => true]);
        $good = Good::create(['name' => 'Изюм', 'denominator' => 10, 'is_published' => true]);
        $price = GoodPriceTypeValue::create(['good_id' => $good->id, 'price_type_id' => $type->id, 'currency_id' => $currency->id, 'price_gross' => 233, 'is_published' => true]);
        $link = $linked ? AvitoListingGoodLink::create(['good_id' => $good->id, 'avito_account_id' => 321, 'avito_item_id' => 7001]) : null;

        return [$good, $price, $link];
    }

    private function publication(Good $good, int $item): AvitoPublication
    {
        $feed = AvitoAutoloadFeed::firstOrCreate(['avito_account_id' => 321], ['name' => 'Goods', 'access_token' => 'fixture-token']);

        return AvitoPublication::create(['avito_autoload_feed_id' => $feed->id, 'avito_account_id' => 321, 'good_id' => $good->id, 'avito_item_id' => $item, 'external_id' => 'fixture-'.$item, 'status' => 'published', 'draft_payload' => []]);
    }

    private function base(Good $good, AvitoListingGoodLink $link): string
    {
        return "/api/avito/goods/{$good->id}/links/{$link->id}";
    }

    private function fakeRemote(array $overrides = []): void
    {
        Http::fake($overrides + [
            'https://api.avito.ru/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 86400]),
            'https://api.avito.ru/core/v1/accounts/self' => Http::response(['id' => 321]),
            'https://api.avito.ru/core/v1/accounts/321/items/7001/' => Http::response(['status' => 'active', 'url' => 'https://www.avito.ru/7001']),
            'https://api.avito.ru/core/v1/items/7001/update_price' => Http::response(['ok' => true]),
        ]);
    }
}
