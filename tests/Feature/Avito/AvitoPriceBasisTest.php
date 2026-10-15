<?php

namespace Tests\Feature\Avito;

use App\Models\AvitoListingGoodLink;
use App\Models\AvitoPublication;
use App\Models\Currency;
use App\Models\Good;
use App\Models\GoodPriceTypeValue;
use App\Models\Measure;
use App\Models\PriceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AvitoPriceBasisTest extends TestCase
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
            'avito.client_id' => 'basis-client',
            'avito.client_secret' => 'basis-secret',
            'avito.api_base_url' => 'https://api.avito.ru',
            'avito.token_url' => 'https://api.avito.ru/token',
            'avito.allowed_hosts' => ['api.avito.ru'],
            'avito.mutations_enabled' => true,
        ]);
        Http::preventStrayRequests();
    }

    public function test_package_price_uses_all_kilograms_and_rounds_after_multiplication(): void
    {
        [$good, $price, $link] = $this->fixture();
        $price->update(['price_gross' => 233.46]);

        $preview = $this->postJson($this->base($good, $link).'/preview', $this->payload($price, ['mode' => 'package']))
            ->assertOk()
            ->assertJsonPath('preview.price.good_value', 233.46)
            ->assertJsonPath('preview.price.avito_value', 2335)
            ->assertJsonPath('preview.price.price_basis.mode', 'package')
            ->assertJsonPath('preview.price.can_apply', true);

        $this->assertEquals(2334.6, $preview->json('preview.price.total_unrounded'));
        $this->assertEquals(10, $preview->json('preview.price.price_basis.quantity'));
        $this->assertEquals(10, $preview->json('preview.price.price_basis.package_kg'));
        $this->assertNotEmpty($preview->json('preview.price.sale_unit_label'));
        $this->assertNotEmpty($preview->json('preview.price.formula'));
        $this->assertNull($link->fresh()->price_basis, 'A preview must not change the saved sale unit.');
        Http::assertNothingSent();
    }

    public function test_legacy_listing_apply_requires_preview_fingerprint_and_rejects_changed_saved_basis(): void
    {
        [$good, $price, $link] = $this->fixture();
        $payload = ['account_id' => 321, 'fields' => ['price'], 'price_value_id' => $price->id];
        $this->postJson('/api/avito/listings/7001/good-transfer/apply', $payload + ['confirmed' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('expected_fingerprint');
        $preview = $this->postJson('/api/avito/listings/7001/good-transfer/preview', $payload)->assertOk();
        $link->update(['price_basis' => ['mode' => 'custom', 'quantity' => 10, 'label' => 'коробка']]);

        $this->postJson('/api/avito/listings/7001/good-transfer/apply', $payload + [
            'confirmed' => true, 'expected_fingerprint' => $preview->json('preview.source_fingerprint'),
        ])->assertConflict()->assertJsonPath('category', 'good_transfer_stale');
        Http::assertNothingSent();
    }

    public function test_package_price_does_not_multiply_a_price_already_per_box_by_its_weight(): void
    {
        [$good, $price, $link] = $this->fixture();
        $good->update(['measure_id' => Measure::firstOrCreate(['name' => 'коробка'])->id, 'unit_weight_kg' => 10]);
        $price->update(['price_gross' => 2330.4]);

        $preview = $this->postJson($this->base($good, $link).'/preview', $this->payload($price, ['mode' => 'package']))
            ->assertOk()
            ->assertJsonPath('preview.price.good_value', 2330.4)
            ->assertJsonPath('preview.price.avito_value', 2330)
            ->assertJsonPath('preview.price.can_apply', true);

        $this->assertEquals(1, $preview->json('preview.price.price_basis.quantity'));
        $this->assertEquals(2330.4, $preview->json('preview.price.total_unrounded'));
        Http::assertNothingSent();
    }

    public function test_package_conversion_preserves_fractional_ratio_precision_until_final_rounding(): void
    {
        [$good, $price, $link] = $this->fixture();
        $good->update(['measure_id' => Measure::firstOrCreate(['name' => 'коробка'])->id, 'unit_weight_kg' => 3]);
        $price->update(['price_gross' => 30000000.15]);

        $preview = $this->postJson($this->base($good, $link).'/preview', $this->payload($price, ['mode' => 'package']))
            ->assertOk()->assertJsonPath('preview.price.can_apply', true)->assertJsonPath('preview.price.avito_value', 100000001);

        $this->assertEqualsWithDelta(10 / 3, $preview->json('preview.price.price_basis.quantity'), 0.000000000001);
        $this->assertEqualsWithDelta(100000000.5, $preview->json('preview.price.total_unrounded'), 0.000001);
        Http::assertNothingSent();
    }

    public function test_fractional_custom_sale_unit_keeps_source_price_and_rounds_only_final_amount(): void
    {
        [$good, $price, $link] = $this->fixture();
        $price->update(['price_gross' => 233.4]);
        $payload = $this->payload($price, ['mode' => 'custom', 'quantity' => 0.5, 'label' => 'пакет 500 г']);

        $preview = $this->postJson($this->base($good, $link).'/preview', $payload)->assertOk()
            ->assertJsonPath('preview.price.good_value', 233.4)
            ->assertJsonPath('preview.price.avito_value', 117)
            ->assertJsonPath('preview.price.price_basis.quantity', 0.5)
            ->assertJsonPath('preview.price.price_basis.label', 'пакет 500 г')
            ->assertJsonPath('preview.price.can_apply', true);
        $this->assertEquals(116.7, $preview->json('preview.price.total_unrounded'));
        $this->fakeRemote();

        $this->postJson($this->base($good, $link).'/apply', $payload + [
            'confirmed' => true, 'expected_fingerprint' => $preview->json('preview.source_fingerprint'),
        ])->assertOk()->assertJsonPath('transfer.price.avito_value', 117);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/update_price') && $request['price'] === 117);
        $this->assertSame('custom', $link->fresh()->price_basis['mode']);
        $this->assertEquals(0.5, $link->fresh()->price_basis['quantity']);
        $this->assertEquals(233.4, $price->fresh()->price_gross, 'Sending an Avito price must not alter the saved Good price.');
    }

    public function test_package_requires_a_known_weight_conversion_and_custom_basis_can_be_used_instead(): void
    {
        [$good, $price, $link] = $this->fixture();
        $good->update(['measure_id' => Measure::firstOrCreate(['name' => 'шт'])->id, 'unit_weight_kg' => null]);

        $this->postJson($this->base($good, $link).'/preview', $this->payload($price, ['mode' => 'package']))
            ->assertOk()->assertJsonPath('preview.price.can_apply', false);
        $this->postJson($this->base($good, $link).'/preview', $this->payload($price, [
            'mode' => 'custom', 'quantity' => 2, 'label' => 'набор из двух штук',
        ]))->assertOk()->assertJsonPath('preview.price.can_apply', true)->assertJsonPath('preview.price.avito_value', 466);

        Http::assertNothingSent();
    }

    public function test_basis_quantity_cannot_change_between_preview_and_apply(): void
    {
        [$good, $price, $link] = $this->fixture();
        $payload = $this->payload($price, ['mode' => 'custom', 'quantity' => 2, 'label' => 'набор']);
        $preview = $this->postJson($this->base($good, $link).'/preview', $payload)->assertOk();
        $payload['price_basis']['quantity'] = 3;

        $this->postJson($this->base($good, $link).'/apply', $payload + [
            'confirmed' => true, 'expected_fingerprint' => $preview->json('preview.source_fingerprint'),
        ])->assertConflict()->assertJsonPath('category', 'good_transfer_stale');

        $this->assertNull($link->fresh()->price_basis);
        Http::assertNothingSent();
    }

    public function test_concurrent_saved_basis_change_invalidates_an_explicit_previously_confirmed_basis(): void
    {
        [$good, $price, $link] = $this->fixture();
        $payload = $this->payload($price, ['mode' => 'custom', 'quantity' => 2, 'label' => 'набор']);
        $preview = $this->postJson($this->base($good, $link).'/preview', $payload)->assertOk();
        $concurrentBasis = ['mode' => 'custom', 'quantity' => 5, 'label' => 'партия'];
        $link->update(['price_basis' => $concurrentBasis]);

        $this->postJson($this->base($good, $link).'/apply', $payload + [
            'confirmed' => true, 'expected_fingerprint' => $preview->json('preview.source_fingerprint'),
        ])->assertConflict()->assertJsonPath('category', 'good_transfer_stale');

        $this->assertSame($concurrentBasis, $link->fresh()->price_basis);
        Http::assertNothingSent();
    }

    public function test_package_weight_cannot_change_between_preview_and_apply(): void
    {
        [$good, $price, $link] = $this->fixture();
        $payload = $this->payload($price, ['mode' => 'package']);
        $preview = $this->postJson($this->base($good, $link).'/preview', $payload)->assertOk();
        $good->update(['denominator' => 20]);

        $this->postJson($this->base($good, $link).'/apply', $payload + [
            'confirmed' => true, 'expected_fingerprint' => $preview->json('preview.source_fingerprint'),
        ])->assertConflict()->assertJsonPath('category', 'good_transfer_stale');

        Http::assertNothingSent();
    }

    public function test_successful_basis_is_reused_and_cancelled_previews_do_not_replace_it(): void
    {
        [$good, $price, $link] = $this->fixture();
        $payload = $this->payload($price, ['mode' => 'package']);
        $preview = $this->postJson($this->base($good, $link).'/preview', $payload)->assertOk();
        $this->fakeRemote();
        $this->postJson($this->base($good, $link).'/apply', $payload + [
            'confirmed' => true, 'expected_fingerprint' => $preview->json('preview.source_fingerprint'),
        ])->assertOk();
        $saved = $link->fresh()->price_basis;
        $this->assertSame('package', $saved['mode']);

        $this->postJson($this->base($good, $link).'/preview', $this->payload($price, ['mode' => 'unit']))
            ->assertOk()->assertJsonPath('preview.price.avito_value', 233);
        $this->assertSame($saved, $link->fresh()->price_basis);
        $this->postJson($this->base($good, $link).'/preview', ['fields' => ['price'], 'price_value_id' => $price->id])
            ->assertOk()->assertJsonPath('preview.price.price_basis.mode', 'package')
            ->assertJsonPath('preview.price.avito_value', 2330);
    }

    public function test_failed_remote_write_does_not_save_selected_basis(): void
    {
        [$good, $price, $link] = $this->fixture();
        $payload = $this->payload($price, ['mode' => 'package']);
        $preview = $this->postJson($this->base($good, $link).'/preview', $payload)->assertOk();
        $this->fakeRemote(['https://api.avito.ru/core/v1/items/7001/update_price' => Http::response(['error' => ['message' => 'Unavailable']], 500)]);

        $response = $this->postJson($this->base($good, $link).'/apply', $payload + [
            'confirmed' => true, 'expected_fingerprint' => $preview->json('preview.source_fingerprint'),
        ]);

        $this->assertGreaterThanOrEqual(400, $response->status());
        $this->assertNull($link->fresh()->price_basis);
        $this->assertNull($link->fresh()->last_applied_at);
    }

    public function test_saved_measurement_requires_reconfirmation_after_goods_unit_changes(): void
    {
        [$good, $price, $link] = $this->fixture();
        $basis = ['mode' => 'custom', 'quantity' => 2, 'label' => 'набор'];
        $preview = $this->postJson($this->base($good, $link).'/preview', $this->payload($price, $basis))->assertOk();
        $link->update(['price_basis' => $preview->json('preview.price.price_basis')]);
        $good->update(['measure_id' => Measure::firstOrCreate(['name' => 'коробка'])->id, 'unit_weight_kg' => 10]);

        $this->postJson($this->base($good, $link).'/preview', ['fields' => ['price'], 'price_value_id' => $price->id])
            ->assertOk()->assertJsonPath('preview.price.can_apply', false);
        $this->postJson($this->base($good, $link).'/preview', $this->payload($price, $basis))
            ->assertOk()->assertJsonPath('preview.price.can_apply', true)->assertJsonPath('preview.price.avito_value', 466);
        Http::assertNothingSent();
    }

    public function test_invalid_custom_quantities_and_modes_are_rejected(): void
    {
        [$good, $price, $link] = $this->fixture();
        foreach ([
            ['mode' => 'custom', 'quantity' => 0, 'label' => 'набор'],
            ['mode' => 'custom', 'quantity' => -2, 'label' => 'набор'],
            ['mode' => 'custom', 'quantity' => 'not-a-number', 'label' => 'набор'],
            ['mode' => 'custom', 'quantity' => 1000000001, 'label' => 'набор'],
            ['mode' => 'unsupported'],
        ] as $basis) {
            $this->postJson($this->base($good, $link).'/preview', $this->payload($price, $basis))->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_unfinished_custom_basis_cannot_be_sent_to_avito(): void
    {
        [$good, $price, $link] = $this->fixture();
        $payload = $this->payload($price, ['mode' => 'custom', 'quantity' => 2]);
        $preview = $this->postJson($this->base($good, $link).'/preview', $payload)
            ->assertOk()->assertJsonPath('preview.price.can_apply', false);

        $this->postJson($this->base($good, $link).'/apply', $payload + [
            'confirmed' => true, 'expected_fingerprint' => $preview->json('preview.source_fingerprint'),
        ])->assertUnprocessable();
        $this->assertNull($link->fresh()->price_basis);
        Http::assertNothingSent();
    }

    public function test_saved_package_requires_reconfirmation_when_package_weight_changes(): void
    {
        [$good, $price, $link] = $this->fixture();
        $preview = $this->postJson($this->base($good, $link).'/preview', $this->payload($price, ['mode' => 'package']))->assertOk();
        $link->update(['price_basis' => $preview->json('preview.price.price_basis')]);
        $good->update(['denominator' => 20]);

        $this->postJson($this->base($good, $link).'/preview', ['fields' => ['price'], 'price_value_id' => $price->id])
            ->assertOk()->assertJsonPath('preview.price.can_apply', false);
        $this->postJson($this->base($good, $link).'/preview', $this->payload($price, ['mode' => 'package']))
            ->assertOk()->assertJsonPath('preview.price.can_apply', true)->assertJsonPath('preview.price.avito_value', 4660);
        Http::assertNothingSent();
    }

    public function test_multiplication_cannot_push_an_amount_beyond_avito_price_limit(): void
    {
        [$good, $price, $link] = $this->fixture();
        $price->update(['price_gross' => 1000000000]);
        $payload = $this->payload($price, ['mode' => 'custom', 'quantity' => 1001, 'label' => 'партия']);
        $preview = $this->postJson($this->base($good, $link).'/preview', $payload)
            ->assertOk()->assertJsonPath('preview.price.can_apply', false);

        $this->postJson($this->base($good, $link).'/apply', $payload + [
            'confirmed' => true, 'expected_fingerprint' => $preview->json('preview.source_fingerprint'),
        ])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_autoload_package_basis_is_saved_in_draft_and_immutable_approved_revision(): void
    {
        [$good, $price] = $this->fixture(false);
        $publication = $this->publication($good, $price, ['mode' => 'package']);

        $preview = $this->postJson("/api/avito/publications/{$publication->id}/preview", ['account_id' => 321])
            ->assertOk()->assertJsonPath('preview.valid', true)
            ->assertJsonPath('preview.payload.price', 2330)
            ->assertJsonPath('preview.price_details.good_value', 233)
            ->assertJsonPath('preview.price_details.price_basis.mode', 'package');
        $this->assertEquals(10, $preview->json('preview.price_details.price_basis.quantity'));
        $this->assertSame('package', $publication->fresh()->draft_payload['price_basis']['mode']);

        $this->postJson("/api/avito/publications/{$publication->id}/approve", ['account_id' => 321, 'confirmed' => true])
            ->assertOk()->assertJsonPath('publication.current_revision.payload.price', 2330);
        $revision = $publication->fresh()->currentRevision;
        $this->assertNotNull($revision);
        $this->assertSame('package', $revision->source_snapshot['price_basis']['mode']);
        $this->assertEquals(10, $revision->source_snapshot['price_basis']['quantity']);
        $this->assertEquals(10, $revision->payload_snapshot['price_basis']['quantity']);
        $good->update(['denominator' => 20]);
        $this->assertEquals(2330, $revision->fresh()->payload_snapshot['price']);
        Http::assertNothingSent();
    }

    public function test_autoload_stale_package_snapshot_blocks_approval_until_basis_is_reconfirmed(): void
    {
        [$good, $price] = $this->fixture(false);
        $publication = $this->publication($good, $price, ['mode' => 'package']);
        $good->update(['denominator' => 20]);

        $this->postJson("/api/avito/publications/{$publication->id}/preview", ['account_id' => 321])
            ->assertOk()->assertJsonPath('preview.valid', false)->assertJsonPath('preview.price_details.can_apply', false);
        $this->postJson("/api/avito/publications/{$publication->id}/approve", ['account_id' => 321, 'confirmed' => true])
            ->assertUnprocessable();
        $this->assertDatabaseCount('avito_publication_revisions', 0);

        $this->putJson("/api/avito/publications/{$publication->id}", [
            'account_id' => 321, 'price_basis' => ['mode' => 'package'],
        ])->assertOk()->assertJsonPath('preview.valid', true)->assertJsonPath('preview.payload.price', 4660);
        $this->postJson("/api/avito/publications/{$publication->id}/approve", ['account_id' => 321, 'confirmed' => true])
            ->assertOk()->assertJsonPath('publication.current_revision.payload.price', 4660);
        Http::assertNothingSent();
    }

    public function test_autoload_manual_price_override_is_final_price_not_price_per_base_unit(): void
    {
        [$good, $price] = $this->fixture(false);
        $publication = $this->publication($good, $price, ['mode' => 'package']);

        $this->putJson("/api/avito/publications/{$publication->id}", [
            'account_id' => 321, 'price_override' => 999.4,
        ])->assertOk()->assertJsonPath('preview.valid', true)->assertJsonPath('preview.payload.price', 999);

        $this->postJson("/api/avito/publications/{$publication->id}/approve", ['account_id' => 321, 'confirmed' => true])
            ->assertOk()->assertJsonPath('publication.current_revision.payload.price', 999);
        Http::assertNothingSent();
    }

    private function fixture(bool $linked = true): array
    {
        $currency = Currency::forceCreate(['name' => 'Рубль', 'code' => 'RUB']);
        $type = PriceType::create(['name' => 'Розничная', 'code' => 'retail', 'currency_id' => $currency->id, 'is_active' => true, 'is_public' => true]);
        $good = Good::create(['name' => 'Изюм', 'description' => 'Изюм сушёный, упаковка 10 кг.', 'denominator' => 10, 'measure_id' => Measure::firstOrCreate(['name' => 'кг'])->id, 'is_published' => true]);
        $price = GoodPriceTypeValue::create(['good_id' => $good->id, 'price_type_id' => $type->id, 'currency_id' => $currency->id, 'price_gross' => 233, 'is_published' => true]);
        $link = $linked ? AvitoListingGoodLink::create(['good_id' => $good->id, 'avito_account_id' => 321, 'avito_item_id' => 7001]) : null;

        return [$good, $price, $link];
    }

    private function publication(Good $good, GoodPriceTypeValue $price, array $basis): AvitoPublication
    {
        $created = $this->postJson('/api/avito/publications', ['account_id' => 321, 'good_id' => $good->id])->assertCreated();
        $id = $created->json('publication.id');
        $this->putJson("/api/avito/publications/{$id}", [
            'account_id' => 321,
            'category_node_slug' => 'produkty-pitaniya',
            'category_name' => 'Продукты питания',
            'selected_fields' => ['title', 'description', 'price'],
            'price_value_id' => $price->id,
            'price_basis' => $basis,
            'address' => 'Москва, Складочная улица, 1',
            'contact_phone' => '+7 999 123-45-67',
        ])->assertOk();

        return AvitoPublication::findOrFail($id);
    }

    private function payload(GoodPriceTypeValue $price, array $basis): array
    {
        return ['fields' => ['price'], 'price_value_id' => $price->id, 'price_basis' => $basis];
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
