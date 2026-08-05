<?php

namespace Tests\Feature\Avito;

use App\Models\AvitoChat;
use App\Models\AvitoContactCandidate;
use App\Models\AvitoMessengerAccount;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodMedia;
use App\Models\GoodPriceTypeValue;
use App\Models\OrderStatus;
use App\Models\PriceType;
use App\Models\Region;
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

        $entityResponse = $this->postJson("/api/avito/messenger/chats/{$chat->id}/crm/entity", [
            'name' => 'Клиент из Avito',
            'full_name' => 'Общество с ограниченной ответственностью Клиент из Avito',
            'INN' => '7801234567',
            'KPP' => '780101001',
            'OGRN' => '1027800000000',
            'legal_address' => '190000, Санкт-Петербург, Невский проспект, 1',
            'bank_account_number' => '40702810000000000001',
            'bank_name' => 'Тестовый банк',
            'bank_bic' => '044030001',
            'bank_corr_account' => '30101810000000000001',
        ])->assertCreated()
            ->assertJsonPath('entity.KPP', '780101001')
            ->assertJsonPath('entity.OGRN', '1027800000000');
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

        $phone = AvitoContactCandidate::query()->where('type', 'phone')->firstOrFail();
        $this->postJson("/api/avito/messenger/chats/{$chat->id}/crm/telephones", [
            'candidate_id' => $phone->id,
        ])->assertCreated()->assertJsonPath('telephone.number', '+79991234567');
        $this->assertDatabaseHas('entity_telephone', ['entity_id' => $entityId]);
        $this->assertSame('accepted', $phone->fresh()->status);

        $country = Country::query()->create(['name' => 'Россия', 'сodeISO' => 'RU']);
        $region = Region::query()->create(['name' => 'Москва', 'country_id' => $country->id]);
        $city = City::query()->create(['name' => 'Москва', 'region_id' => $region->id]);
        $address = AvitoContactCandidate::query()->where('type', 'address')->firstOrFail();
        $buildingResponse = $this->postJson("/api/avito/messenger/chats/{$chat->id}/crm/buildings", [
            'candidate_id' => $address->id,
            'city_id' => $city->id,
            'address' => 'ул. Ленина, д. 10',
            'postcode' => '101000',
        ])->assertCreated();
        $buildingId = (int) $buildingResponse->json('building.id');
        $this->assertDatabaseHas('building_entities', [
            'entity_id' => $entityId,
            'building_id' => $buildingId,
        ]);

        $good = Good::query()->create(['name' => 'Тестовый товар', 'is_published' => true]);
        $status = OrderStatus::query()->where('code', OrderStatus::OPEN)->firstOrFail();
        $orderResponse = $this->postJson("/api/avito/messenger/chats/{$chat->id}/crm/orders", [
            'order_status_id' => $status->id,
            'currency_code' => 'RUB',
            'building_ids' => [$buildingId],
            'contact_telephone_id' => $phone->fresh()->telephone_id,
            'send_confirmation' => false,
            'items' => [[
                'good_id' => $good->id,
                'quantity' => 2,
                'unit_price' => 350,
            ]],
        ])->assertCreated()->assertJsonPath('order.total_amount', 700);
        $orderId = (int) $orderResponse->json('order.id');

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
            ->assertJsonPath('entity.telephones.0.number', '+79991234567')
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
