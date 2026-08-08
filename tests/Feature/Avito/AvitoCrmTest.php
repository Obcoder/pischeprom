<?php

namespace Tests\Feature\Avito;

use App\Models\AvitoChat;
use App\Models\AvitoContactCandidate;
use App\Models\AvitoMessengerAccount;
use App\Models\BuildingType;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodMedia;
use App\Models\GoodPriceTypeValue;
use App\Models\OrderStatus;
use App\Models\PhoneCall;
use App\Models\PriceType;
use App\Models\Region;
use App\Models\Telephone;
use App\Models\Unit;
use App\Services\Telephones\TelephoneIdentityService;
use App\Services\Telephony\BeelinePbxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvitoCrmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
        config([
            'cache.default' => 'array',
            'avito.enabled' => true,
            'avito.client_id' => 'messenger-client',
            'avito.client_secret' => 'messenger-secret',
            'avito.api_base_url' => 'https://api.avito.ru',
            'avito.token_url' => 'https://api.avito.ru/token',
            'avito.allowed_hosts' => ['api.avito.ru', 'pro.autoteka.ru'],
            'avito.mutations_enabled' => true,
            'avito.mutation_confirmation' => 'AVITO',
            'avito.webhook_secret' => 'crm-webhook-secret',
            'avito.messenger.archive_disk' => 'avito',
        ]);
    }

    public function test_incoming_contacts_become_reviewable_candidates_and_create_complete_crm_context(): void
    {
        [$account, $chat] = $this->chatFixture();
        $secondChat = AvitoChat::query()->create([
            'avito_messenger_account_id' => $account->id,
            'external_chat_id' => 'chat-crm-second',
            'peer_user_id' => '999',
            'peer_name' => 'Покупатель',
        ]);

        $this->postJson('/api/avito/webhook', [
            'id' => 'crm-event-1',
            'payload' => [
                'type' => 'message',
                'value' => [
                    'id' => 'crm-message-1',
                    'chat_id' => $chat->external_chat_id,
                    'chat_type' => 'u2i',
                    'user_id' => 777,
                    'author_id' => 999,
                    'created' => 1785916800,
                    'type' => 'text',
                    'content' => ['text' => "Позвоните мне: 8 (999) 123-45-67\nАдрес: ул. Ленина, д. 10"],
                ],
            ],
        ], ['X-Secret' => 'crm-webhook-secret'])->assertAccepted();

        $this->assertDatabaseHas('avito_contact_candidates', [
            'type' => AvitoContactCandidate::TYPE_PHONE,
            'normalized_value' => '+79991234567',
            'status' => AvitoContactCandidate::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('avito_contact_candidates', [
            'type' => AvitoContactCandidate::TYPE_ADDRESS,
            'status' => AvitoContactCandidate::STATUS_PENDING,
        ]);
        $this->getJson("/api/avito/messenger/chats/{$chat->id}")
            ->assertOk()
            ->assertJsonPath('messages.data.0.contact_candidates.0.status', 'pending');

        $country = Country::query()->create(['name' => 'Россия', 'сodeISO' => 'RU']);
        $region = Region::query()->create(['name' => 'Москва', 'country_id' => $country->id]);
        $city = City::query()->create(['name' => 'Москва', 'region_id' => $region->id]);
        $unit = Unit::query()->create(['name' => 'Московский Unit']);
        $homeBuildingType = BuildingType::query()->where('name', 'Домашний')->firstOrFail();

        $this->getJson('/api/avito/messenger/crm/options')
            ->assertOk()
            ->assertJsonFragment(['id' => $unit->id, 'name' => 'Московский Unit'])
            ->assertJsonFragment(['id' => $homeBuildingType->id, 'name' => 'Домашний']);

        $entityResponse = $this->postJson("/api/avito/messenger/chats/{$chat->id}/crm/entity", [
            'name' => 'Клиент из Avito',
            'full_name' => 'Общество с ограниченной ответственностью Клиент из Avito',
            'INN' => '7801234567',
            'KPP' => '780101001',
            'OGRN' => '1027800000000',
            'legal_address' => '190000, Санкт-Петербург, Невский проспект, 1',
            'country_id' => $country->id,
            'city_ids' => [$city->id],
            'unit_ids' => [$unit->id],
            'bank_account_number' => '40702810000000000001',
            'bank_name' => 'Тестовый банк',
            'bank_bic' => '044030001',
            'bank_corr_account' => '30101810000000000001',
        ])->assertCreated()
            ->assertJsonPath('entity.KPP', '780101001')
            ->assertJsonPath('entity.OGRN', '1027800000000')
            ->assertJsonPath('entity.cities.0.id', $city->id)
            ->assertJsonPath('entity.units.0.id', $unit->id);
        $entityId = (int) $entityResponse->json('entity.id');

        $this->assertDatabaseHas('entities', [
            'id' => $entityId,
            'INN' => '7801234567',
            'KPP' => '780101001',
            'OGRN' => '1027800000000',
            'legal_address' => '190000, Санкт-Петербург, Невский проспект, 1',
            'bank_account_number' => '40702810000000000001',
            'bank_name' => 'Тестовый банк',
            'bank_bic' => '044030001',
            'bank_corr_account' => '30101810000000000001',
        ]);
        $this->assertDatabaseHas('avito_chats', ['id' => $chat->id, 'entity_id' => $entityId]);
        $this->assertDatabaseHas('avito_chats', ['id' => $secondChat->id, 'entity_id' => $entityId]);
        $this->assertDatabaseHas('city_entity', ['city_id' => $city->id, 'entity_id' => $entityId]);
        $this->assertDatabaseHas('entity_unit', ['unit_id' => $unit->id, 'entity_id' => $entityId]);

        $phone = AvitoContactCandidate::query()->where('type', 'phone')->firstOrFail();
        $this->postJson("/api/avito/messenger/chats/{$chat->id}/crm/telephones", [
            'candidate_id' => $phone->id,
        ])->assertCreated()->assertJsonPath('telephone.number', '+79991234567');
        $this->assertDatabaseHas('entity_telephone', ['entity_id' => $entityId]);
        $this->assertSame('accepted', $phone->fresh()->status);

        $address = AvitoContactCandidate::query()->where('type', 'address')->firstOrFail();
        $buildingResponse = $this->postJson("/api/avito/messenger/chats/{$chat->id}/crm/buildings", [
            'candidate_id' => $address->id,
            'city_id' => $city->id,
            'building_type_id' => $homeBuildingType->id,
            'address' => 'ул. Ленина, д. 10',
            'postcode' => '101000',
        ])->assertCreated();
        $buildingId = (int) $buildingResponse->json('building.id');
        $this->assertDatabaseHas('building_entities', [
            'entity_id' => $entityId,
            'building_id' => $buildingId,
        ]);
        $this->assertDatabaseHas('buildings', [
            'id' => $buildingId,
            'building_type_id' => $homeBuildingType->id,
        ]);

        $good = Good::query()->create(['name' => 'Тестовый товар', 'is_published' => true]);
        $status = OrderStatus::query()->where('code', OrderStatus::OPEN)->firstOrFail();
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'crm-order-token', 'expires_in' => 86400]),
            'https://api.avito.ru/messenger/v1/accounts/777/chats/chat-crm/messages' => Http::response([
                'id' => 'order-confirmation-1',
                'author_id' => 777,
                'direction' => 'out',
                'type' => 'text',
                'created' => 1785916800,
                'content' => ['text' => 'Подтверждение заказа'],
            ]),
        ]);
        $orderResponse = $this->postJson("/api/avito/messenger/chats/{$chat->id}/crm/orders", [
            'order_status_id' => $status->id,
            'currency_code' => 'RUB',
            'building_ids' => [$buildingId],
            'contact_telephone_id' => $phone->fresh()->telephone_id,
            'send_confirmation' => true,
            'items' => [[
                'good_id' => $good->id,
                'quantity' => 2,
                'unit_price' => 350,
            ]],
        ])->assertCreated()
            ->assertJsonPath('order.total_amount', 700)
            ->assertJsonPath('outbound.sent', 1);
        $orderId = (int) $orderResponse->json('order.id');
        $orderNumber = (string) $orderResponse->json('order.number');

        Http::assertSent(function ($request) use ($orderNumber): bool {
            if ($request->url() !== 'https://api.avito.ru/messenger/v1/accounts/777/chats/chat-crm/messages') {
                return false;
            }

            $text = (string) data_get($request->data(), 'message.text');

            return str_contains($text, "Заказ {$orderNumber} создан.")
                && str_contains($text, 'Тестовый товар')
                && str_contains($text, 'Итого: 700 RUB')
                && ! str_contains($text, 'подтвердите заказ ответным сообщением');
        });

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'entity_id' => $entityId,
            'contact_telephone_id' => $phone->fresh()->telephone_id,
        ]);
        $this->assertDatabaseHas('avito_chat_order', [
            'avito_chat_id' => $chat->id,
            'order_id' => $orderId,
        ]);
        $this->getJson("/api/avito/messenger/chats/{$chat->id}/crm")
            ->assertOk()
            ->assertJsonPath('entity.id', $entityId)
            ->assertJsonPath('entity.cities.0.name', 'Москва')
            ->assertJsonPath('entity.units.0.name', 'Московский Unit')
            ->assertJsonPath('entity.telephones.0.number', '+79991234567')
            ->assertJsonPath('entity.buildings.0.building_type', 'Домашний')
            ->assertJsonPath('orders.0.id', $orderId);
    }

    public function test_existing_phone_suggests_entity_and_candidate_can_be_rejected(): void
    {
        [, $chat] = $this->chatFixture();
        $entity = Entity::query()->create(['name' => 'Существующий клиент']);
        $telephoneId = DB::table('telephones')->insertGetId([
            'number' => '+79991234567',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $entity->telephones()->attach($telephoneId);
        $message = $chat->messages()->create([
            'external_message_id' => 'phone-match',
            'author_id' => '999',
            'direction' => 'in',
            'type' => 'text',
            'remote_type' => 'text',
            'text' => 'Мой номер +7 999 123-45-67',
            'content' => ['text' => 'Мой номер +7 999 123-45-67'],
        ]);

        $this->artisan('avito:crm-backfill')->assertSuccessful();
        $candidate = $message->contactCandidates()->firstOrFail();

        $this->getJson("/api/avito/messenger/chats/{$chat->id}/crm")
            ->assertOk()
            ->assertJsonPath('candidates.0.matched_entities.0.id', $entity->id);
        $this->patchJson("/api/avito/messenger/crm/candidates/{$candidate->id}", [
            'status' => 'rejected',
        ])->assertOk()->assertJsonPath('candidate.status', 'rejected');
    }

    public function test_avito_reuses_call_entity_and_canonicalizes_legacy_phone_without_creating_duplicate(): void
    {
        [, $chat] = $this->chatFixture();
        $placeholder = Entity::query()->create(['name' => 'Клиент +79991234567']);
        $legacyTelephone = Telephone::query()->create(['number' => '79991234567']);
        $placeholder->telephones()->attach($legacyTelephone);
        $call = PhoneCall::query()->create([
            'provider' => 'beeline',
            'provider_call_id' => 'call-before-avito',
            'client_phone' => '79991234567',
            'telephone_id' => $legacyTelephone->id,
            'entity_id' => $placeholder->id,
        ]);
        $chat->messages()->create([
            'external_message_id' => 'call-first-phone',
            'author_id' => '999',
            'direction' => 'in',
            'type' => 'text',
            'remote_type' => 'text',
            'text' => 'Телефон: 8 (999) 123-45-67',
            'content' => ['text' => 'Телефон: 8 (999) 123-45-67'],
        ]);
        $this->artisan('avito:crm-backfill')->assertSuccessful();

        $entityCount = Entity::query()->count();

        $this->postJson("/api/avito/messenger/chats/{$chat->id}/crm/entity", [
            'name' => 'Ирина',
        ])->assertOk()
            ->assertJsonPath('entity.id', $placeholder->id)
            ->assertJsonPath('entity.name', 'Ирина')
            ->assertJsonPath('entity.telephones.0.number', '+79991234567');

        $this->assertSame($entityCount, Entity::query()->count());
        $this->assertDatabaseMissing('telephones', ['id' => $legacyTelephone->id]);
        $this->assertDatabaseHas('telephones', ['number' => '+79991234567']);
        $this->assertSame($placeholder->id, $call->fresh()->entity_id);
        $this->assertSame($placeholder->id, $chat->fresh()->entity_id);
    }

    public function test_beeline_call_reuses_entity_created_from_avito_phone(): void
    {
        config(['services.beeline_pbx.own_numbers' => ['79650160001']]);
        [, $chat] = $this->chatFixture();
        $chat->messages()->create([
            'external_message_id' => 'avito-first-phone',
            'author_id' => '999',
            'direction' => 'in',
            'type' => 'text',
            'remote_type' => 'text',
            'text' => 'Позвоните: +7 999 123-45-67',
            'content' => ['text' => 'Позвоните: +7 999 123-45-67'],
        ]);
        $this->artisan('avito:crm-backfill')->assertSuccessful();

        $response = $this->postJson("/api/avito/messenger/chats/{$chat->id}/crm/entity", [
            'name' => 'Клиент сначала из Avito',
        ])->assertCreated()
            ->assertJsonPath('entity.telephones.0.number', '+79991234567');
        $entityId = (int) $response->json('entity.id');
        $entityCount = Entity::query()->count();

        $this->postJson('/api/phone-calls', [
            'source' => 'website',
            'client_phone' => '8 (999) 123-45-67',
        ])->assertCreated()
            ->assertJsonPath('data.entity.id', $entityId)
            ->assertJsonPath('data.telephone.number', '+79991234567');

        $call = app(BeelinePbxService::class)->registerCall([
            'cmd' => 'event',
            'type' => 'xsi:CallReceivedEvent',
            'callId' => 'call-after-avito',
            'direction' => 'terminator',
            'callingParty' => ['address' => 'sip:89991234567@beeline.ru'],
            'calledParty' => ['address' => 'sip:+79650160001@beeline.ru'],
            'localParty' => ['address' => 'sip:9650160001@beeline.ru'],
        ]);

        $this->assertSame($entityId, $call->entity_id);
        $this->assertSame($entityCount, Entity::query()->count());
        $this->assertSame('+79991234567', $call->telephone?->number);
    }

    public function test_legacy_phone_rows_and_automatic_call_placeholder_are_folded_into_named_entity(): void
    {
        $entity = Entity::query()->create(['name' => 'Ирина']);
        $canonicalTelephone = Telephone::query()->create(['number' => '+79991234567']);
        $entity->telephones()->attach($canonicalTelephone);

        $placeholder = Entity::query()->create(['name' => 'Клиент +79991234567']);
        $legacyTelephone = Telephone::query()->create(['number' => '79991234567']);
        $placeholder->telephones()->attach($legacyTelephone);
        $call = PhoneCall::query()->create([
            'provider' => 'beeline',
            'provider_call_id' => 'legacy-duplicate-call',
            'client_phone' => '79991234567',
            'telephone_id' => $legacyTelephone->id,
            'entity_id' => $placeholder->id,
        ]);

        $resolved = app(TelephoneIdentityService::class)->resolve('8 (999) 123-45-67');
        $call->refresh();

        $this->assertSame($canonicalTelephone->id, $resolved?->id);
        $this->assertSame($entity->id, $call->entity_id);
        $this->assertSame($canonicalTelephone->id, $call->telephone_id);
        $this->assertDatabaseMissing('telephones', ['id' => $legacyTelephone->id]);
        $this->assertDatabaseMissing('entities', ['id' => $placeholder->id]);
    }

    public function test_good_card_sends_current_price_text_link_and_converted_product_photo(): void
    {
        Storage::fake('yandex');
        Storage::fake('avito');
        [, $chat] = $this->chatFixture();
        $currency = Currency::forceCreate(['name' => 'Российский рубль', 'code' => 'RUB']);
        $priceType = PriceType::query()->create([
            'name' => 'Розница',
            'code' => 'retail',
            'currency_id' => $currency->id,
            'is_active' => true,
            'is_public' => true,
        ]);
        $good = Good::query()->create([
            'name' => 'Масло какао',
            'description' => '<p>Натуральное масло для кондитерского производства.</p>',
            'denominator' => 5,
            'is_published' => true,
        ]);
        $price = GoodPriceTypeValue::query()->create([
            'good_id' => $good->id,
            'price_type_id' => $priceType->id,
            'currency_id' => $currency->id,
            'price_gross' => 1250,
            'is_published' => true,
        ]);
        $fakeImage = UploadedFile::fake()->image('product.webp', 120, 80);
        $mediaPath = "goods/{$good->id}/images/product.webp";
        Storage::disk('yandex')->put($mediaPath, file_get_contents($fakeImage->getRealPath()));
        $media = GoodMedia::query()->create([
            'good_id' => $good->id,
            'type' => 'image',
            'disk' => 'yandex',
            'path' => $mediaPath,
            'url' => 'https://storage.example.test/product.webp',
            'mime_type' => 'image/webp',
            'is_published' => true,
            'is_ava' => true,
        ]);
        $cdnUrl = 'https://img.k.avito.ru/chat/1280x960/product.jpg';

        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'crm-token', 'expires_in' => 86400]),
            'https://api.avito.ru/messenger/v1/accounts/777/chats/chat-crm/messages' => Http::response([
                'id' => 'good-text-1', 'author_id' => 777, 'direction' => 'out', 'type' => 'text',
                'created' => 1785916800, 'content' => ['text' => 'Карточка товара'],
            ]),
            'https://api.avito.ru/messenger/v1/accounts/777/uploadImages' => Http::response([
                'good-image-id' => ['1280x960' => $cdnUrl],
            ]),
            'https://api.avito.ru/messenger/v1/accounts/777/chats/chat-crm/messages/image' => Http::response([
                'id' => 'good-image-1', 'author_id' => 777, 'direction' => 'out', 'type' => 'image',
                'created' => 1785916801, 'content' => ['image' => ['sizes' => ['1280x960' => $cdnUrl]]],
            ]),
            $cdnUrl => Http::response('jpeg-binary', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->getJson('/api/avito/messenger/crm/goods?search=какао')
            ->assertOk()
            ->assertJsonPath('items.0.prices.0.amount', 1250)
            ->assertJsonPath('items.0.media.0.id', $media->id);
        $this->postJson("/api/avito/messenger/chats/{$chat->id}/crm/goods/{$good->id}/send", [
            'price_value_id' => $price->id,
            'quantity' => 2,
            'include_description' => true,
            'include_price' => true,
            'include_stock' => true,
            'include_link' => true,
            'media_ids' => [$media->id],
        ])->assertCreated()->assertJsonPath('sent', 2)->assertJsonPath('warnings', []);

        Http::assertSent(function ($request) use ($good): bool {
            if ($request->url() !== 'https://api.avito.ru/messenger/v1/accounts/777/chats/chat-crm/messages') {
                return false;
            }

            $text = (string) data_get($request->data(), 'message.text');

            return str_contains($text, $good->name)
                && str_contains($text, '1 250 RUB')
                && str_contains($text, '/g/'.$good->slug);
        });
        Http::assertSent(fn ($request) => $request->url() === 'https://api.avito.ru/messenger/v1/accounts/777/uploadImages'
            && str_contains((string) $request->body(), 'image/jpeg'));
    }

    private function chatFixture(): array
    {
        $account = AvitoMessengerAccount::query()->create([
            'source_key' => 'client_credentials',
            'external_user_id' => '777',
            'name' => 'Магазин',
            'sync_enabled' => true,
        ]);
        $chat = AvitoChat::query()->create([
            'avito_messenger_account_id' => $account->id,
            'external_chat_id' => 'chat-crm',
            'chat_type' => 'u2i',
            'context_type' => 'item',
            'context_id' => '123',
            'title' => 'Товар',
            'peer_user_id' => '999',
            'peer_name' => 'Покупатель',
            'is_unread' => true,
        ]);

        return [$account, $chat];
    }
}
