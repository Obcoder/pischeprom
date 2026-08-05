<?php

namespace Tests\Feature\Avito;

use App\Models\AvitoChat;
use App\Models\AvitoMessageTemplate;
use App\Models\AvitoMessengerAccount;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodPriceTypeValue;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PriceType;
use App\Models\Region;
use App\Models\Telephone;
use Database\Seeders\AvitoMessageTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AvitoMessageTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
        config([
            'cache.default' => 'array',
            'avito.enabled' => true,
            'avito.client_id' => 'template-client',
            'avito.client_secret' => 'template-secret',
            'avito.api_base_url' => 'https://api.avito.ru',
            'avito.token_url' => 'https://api.avito.ru/token',
            'avito.allowed_hosts' => ['api.avito.ru', 'pro.autoteka.ru'],
            'avito.mutations_enabled' => true,
            'avito.mutation_confirmation' => 'AVITO',
            'avito.messenger.archive_disk' => 'avito',
        ]);
    }

    public function test_templates_have_public_crud_categories_variables_and_idempotent_starters(): void
    {
        $this->seed(AvitoMessageTemplateSeeder::class);
        $this->seed(AvitoMessageTemplateSeeder::class);
        $this->assertDatabaseCount('avito_message_templates', 6);

        $this->getJson('/api/avito/messenger/templates')
            ->assertOk()
            ->assertJsonCount(6, 'items')
            ->assertJsonPath('items.0.is_favorite', true)
            ->assertJsonFragment(['key' => 'client_name'])
            ->assertJsonFragment(['value' => 'product', 'label' => 'Товары'])
            ->assertJsonPath('meta.message_limit', 1000);

        $starter = AvitoMessageTemplate::query()->where('system_key', 'follow-up')->firstOrFail();
        $this->deleteJson("/api/avito/messenger/templates/{$starter->id}")->assertOk();
        $this->seed(AvitoMessageTemplateSeeder::class);
        $this->assertSoftDeleted('avito_message_templates', ['id' => $starter->id]);
        $this->assertSame(5, AvitoMessageTemplate::query()->count());

        $created = $this->postJson('/api/avito/messenger/templates', [
            'name' => 'Персональный ответ',
            'category' => 'general',
            'body' => 'Добрый день, {{client_name}}!',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('template.placeholders.0', 'client_name');
        $templateId = (int) $created->json('template.id');

        $this->patchJson("/api/avito/messenger/templates/{$templateId}", [
            'name' => 'Персональный ответ 2',
            'is_favorite' => true,
        ])->assertOk()
            ->assertJsonPath('template.name', 'Персональный ответ 2')
            ->assertJsonPath('template.is_favorite', true);
        $this->getJson('/api/avito/messenger/templates?search=Персональный')
            ->assertOk()
            ->assertJsonCount(1, 'items');
        $this->deleteJson("/api/avito/messenger/templates/{$templateId}")->assertOk();
        $this->assertSoftDeleted('avito_message_templates', ['id' => $templateId]);
    }

    public function test_preview_renders_client_order_product_and_chat_context_on_the_server(): void
    {
        [, $chat] = $this->chatFixture();
        $country = Country::query()->create(['name' => 'Россия', 'сodeISO' => 'RU']);
        $region = Region::query()->create(['name' => 'Москва', 'country_id' => $country->id]);
        $city = City::query()->create(['name' => 'Москва', 'region_id' => $region->id]);
        $entity = Entity::query()->create(['name' => 'ООО Покупатель', 'full_name' => 'Покупатель Иван Иванович']);
        $telephone = Telephone::query()->create(['number' => '+79991234567']);
        $entity->telephones()->attach($telephone);
        $building = $entity->buildings()->create([
            'city_id' => $city->id,
            'address' => 'ул. Ленина, д. 10',
            'postcode' => '101000',
        ]);
        $chat->update(['entity_id' => $entity->id]);

        $currency = Currency::forceCreate(['name' => 'Российский рубль', 'code' => 'RUB']);
        $priceType = PriceType::query()->create([
            'name' => 'Розница',
            'code' => 'retail-template',
            'currency_id' => $currency->id,
            'is_active' => true,
            'is_public' => true,
        ]);
        $good = Good::query()->create([
            'name' => 'Масло какао',
            'description' => '<p>Натуральный продукт</p>',
            'is_published' => true,
        ]);
        GoodPriceTypeValue::query()->create([
            'good_id' => $good->id,
            'price_type_id' => $priceType->id,
            'currency_id' => $currency->id,
            'price_gross' => 1250,
            'is_published' => true,
        ]);
        $status = OrderStatus::query()->where('code', OrderStatus::OPEN)->firstOrFail();
        $order = Order::query()->create([
            'number' => 'PP-TEMPLATE-1',
            'entity_id' => $entity->id,
            'order_status_id' => $status->id,
            'total_amount' => 2500,
            'currency_code' => 'RUB',
            'preferred_delivery_time' => 'завтра после 12:00',
        ]);
        $order->items()->create([
            'good_id' => $good->id,
            'good_name' => $good->name,
            'quantity' => 2,
            'price_gross' => 1250,
            'currency_code' => 'RUB',
            'line_total' => 2500,
        ]);
        $order->buildings()->attach($building->id, ['role' => 'delivery', 'position' => 0]);
        $chat->orders()->attach($order->id);
        $template = AvitoMessageTemplate::query()->create([
            'name' => 'Полный контекст',
            'category' => 'order',
            'body' => "{{client_full_name}} · {{client_phone}}\n{{order_number}} · {{order_status}} · {{order_total}} {{order_currency}}\n{{order_items}}\n{{delivery_address}} · {{preferred_delivery_time}}\n{{good_name}} · {{good_price}} {{good_currency}} · {{good_stock}}\n{{context_title}} · {{today}}",
        ]);

        $response = $this->postJson("/api/avito/messenger/chats/{$chat->id}/message-templates/{$template->id}/preview", [
            'order_id' => $order->id,
            'good_id' => $good->id,
            'telephone_id' => $telephone->id,
            'building_id' => $building->id,
        ])->assertOk()
            ->assertJsonPath('preview.unresolved', [])
            ->assertJsonPath('preview.within_limit', true)
            ->assertJsonPath('preview.context.order_id', $order->id)
            ->assertJsonPath('preview.context.good_id', $good->id);
        $text = (string) $response->json('preview.text');
        $this->assertStringContainsString('Покупатель Иван Иванович', $text);
        $this->assertStringContainsString('+79991234567', $text);
        $this->assertStringContainsString('PP-TEMPLATE-1', $text);
        $this->assertStringContainsString('Масло какао', $text);
        $this->assertStringContainsString('1 250 RUB', $text);
        $this->assertStringContainsString('Москва, ул. Ленина, д. 10', $text);
        $this->assertStringNotContainsString('{{', $text);
    }

    public function test_template_can_be_sent_directly_or_from_edited_composer_and_usage_is_archived(): void
    {
        [, $chat] = $this->chatFixture();
        $template = AvitoMessageTemplate::query()->create([
            'name' => 'Быстрый ответ',
            'category' => 'general',
            'body' => 'Здравствуйте, {{peer_name}}!',
            'is_active' => true,
        ]);
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'template-token', 'expires_in' => 86400]),
            'https://api.avito.ru/messenger/v1/accounts/777/chats/chat-template/messages' => Http::sequence()
                ->push([
                    'id' => 'template-message-1', 'author_id' => 777, 'direction' => 'out', 'type' => 'text',
                    'created' => 1785916800, 'content' => ['text' => 'Здравствуйте, Покупатель!'],
                ])
                ->push([
                    'id' => 'template-message-2', 'author_id' => 777, 'direction' => 'out', 'type' => 'text',
                    'created' => 1785916801, 'content' => ['text' => 'Отредактированный быстрый ответ'],
                ]),
        ]);

        $this->postJson("/api/avito/messenger/chats/{$chat->id}/message-templates/{$template->id}/send")
            ->assertCreated()
            ->assertJsonPath('item.text', 'Здравствуйте, Покупатель!')
            ->assertJsonPath('template.usage_count', 1);
        $this->postJson("/api/avito/messenger/chats/{$chat->id}/messages", [
            'text' => 'Отредактированный быстрый ответ',
            'template_id' => $template->id,
        ])->assertCreated();

        $this->assertDatabaseCount('avito_message_template_usages', 2);
        $this->assertDatabaseHas('avito_message_template_usages', ['mode' => 'direct']);
        $this->assertDatabaseHas('avito_message_template_usages', ['mode' => 'composer']);
        $this->assertSame(2, $template->fresh()->usage_count);
        $this->assertNotNull($template->fresh()->last_used_at);
        $encrypted = (string) DB::table('avito_message_template_usages')->latest('id')->value('rendered_body');
        $this->assertStringNotContainsString('Отредактированный быстрый ответ', $encrypted);
    }

    public function test_direct_send_rejects_unresolved_or_inactive_template(): void
    {
        [, $chat] = $this->chatFixture();
        $template = AvitoMessageTemplate::query()->create([
            'name' => 'Требуется товар',
            'category' => 'product',
            'body' => '{{good_name}} — {{unknown_variable}}',
            'is_active' => true,
        ]);

        $this->postJson("/api/avito/messenger/chats/{$chat->id}/message-templates/{$template->id}/send")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('template');
        $template->update(['is_active' => false]);
        $this->postJson("/api/avito/messenger/chats/{$chat->id}/message-templates/{$template->id}/send")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('template');
        Http::assertNothingSent();
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
            'external_chat_id' => 'chat-template',
            'chat_type' => 'u2i',
            'context_type' => 'item',
            'context_id' => '123',
            'context_url' => 'https://www.avito.ru/item/123',
            'title' => 'Товар из объявления',
            'peer_user_id' => '999',
            'peer_name' => 'Покупатель',
        ]);

        return [$account, $chat];
    }
}
