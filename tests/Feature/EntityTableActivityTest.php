<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EntityTableActivityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'realtime.enabled' => false,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Http::preventStrayRequests();

        Schema::create('entities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('INN')->nullable();
            $table->string('OGRN')->nullable();
            $table->unsignedBigInteger('entity_classification_id')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->timestamps();
        });
        foreach (['sales', 'purchases'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('entity_id');
                $table->date('date');
            });
        }
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('order_status_id');
            $table->unsignedBigInteger('shipped_sale_id')->nullable();
            $table->timestamp('submitted_at');
        });
        Schema::create('order_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
        });
        Schema::create('avito_chats', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('peer_name')->nullable();
            $table->string('title')->nullable();
            $table->boolean('is_unread')->default(false);
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('waiting_since')->nullable();
            $table->text('waiting_note')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->text('payload')->nullable();
        });

        $relations = [
            'buildings' => ['building_entities', 'building_id'],
            'cities' => ['city_entity', 'city_id'],
            'emails' => ['email_entity', 'email_id'],
            'telephones' => ['entity_telephone', 'telephone_id'],
            'units' => ['entity_unit', 'unit_id'],
            'chats' => ['chat_entity', 'chat_id'],
        ];
        foreach ($relations as $name => [$pivot, $foreignKey]) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->id();
                if ($name === 'emails') {
                    $table->softDeletes();
                }
            });
            Schema::create($pivot, function (Blueprint $table) use ($foreignKey): void {
                $table->unsignedBigInteger('entity_id');
                $table->unsignedBigInteger($foreignKey);
            });
        }

        DB::table('order_statuses')->insert([
            ['id' => 1, 'code' => 'open'],
            ['id' => 2, 'code' => 'deferred'],
            ['id' => 3, 'code' => 'closed'],
        ]);
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_list_reports_commerce_sales_all_order_statuses_and_local_avito_chats(): void
    {
        $entity = $this->entity('Покупатель');
        $other = $this->entity('Другой');
        DB::table('sales')->insert([
            ['id' => 1, 'entity_id' => $entity, 'date' => '2026-09-01'],
            ['id' => 2, 'entity_id' => $entity, 'date' => '2026-09-20'],
            ['id' => 3, 'entity_id' => $other, 'date' => '2026-09-22'],
        ]);
        foreach ([1, 2, 3] as $status) {
            DB::table('orders')->insert([
                'entity_id' => $entity,
                'order_status_id' => $status,
                'shipped_sale_id' => $status === 3 ? 2 : null,
                'submitted_at' => "2026-09-0{$status} 12:00:00",
            ]);
        }
        $readChat = $this->chat($entity, ['last_message_at' => '2026-09-20 12:00:00']);
        $unreadChat = $this->chat($entity, [
            'is_unread' => true,
            'unread_count' => 2,
            'peer_name' => 'Анна',
            'title' => 'Какао',
            'waiting_since' => '2026-09-21 12:00:00',
            'waiting_note' => 'Уточнить доставку',
            'last_message_at' => '2026-09-21 12:00:00',
            'payload' => 'private provider payload',
        ]);
        $this->chat($other, ['is_unread' => true, 'unread_count' => 4]);
        $this->chat(null, ['is_unread' => true]);

        $response = $this->getJson('/api/entities?has_orders=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $entity)
            ->assertJsonPath('data.0.sales_count', 2)
            ->assertJsonPath('data.0.sales_max_date', '2026-09-20')
            ->assertJsonPath('data.0.orders_count', 3)
            ->assertJsonPath('data.0.orders_max_submitted_at', '2026-09-03 12:00:00')
            ->assertJsonPath('data.0.avito_chats_count', 2)
            ->assertJsonPath('data.0.avito_unread_chats_count', 1)
            ->assertJsonCount(2, 'data.0.avito_chats')
            ->assertJsonPath('data.0.avito_chats.0.id', $unreadChat)
            ->assertJsonPath('data.0.avito_chats.0.peer_name', 'Анна')
            ->assertJsonPath('data.0.avito_chats.0.is_unread', true)
            ->assertJsonPath('data.0.avito_chats.0.unread_count', 2)
            ->assertJsonPath('data.0.avito_chats.0.waiting_note', 'Уточнить доставку')
            ->assertJsonPath('data.0.avito_chats.1.id', $readChat)
            ->assertJsonMissingPath('data.0.avito_chats.0.payload');

        $this->assertNotNull($response->json('data.0.avito_chats.0.waiting_since'));
    }

    public function test_presence_filters_combine_and_support_explicit_absence_and_all_values(): void
    {
        $empty = $this->entity('Без связей');
        $sale = $this->entity('Продажа');
        $closedOrder = $this->entity('Закрытый заказ');
        $read = $this->entity('Прочитанный чат');
        $unread = $this->entity('Непрочитанный чат');
        DB::table('sales')->insert(['entity_id' => $sale, 'date' => '2026-09-21']);
        DB::table('orders')->insert([
            'entity_id' => $closedOrder,
            'order_status_id' => 3,
            'submitted_at' => '2026-09-21 12:00:00',
        ]);
        $this->chat($read);
        // Avito can report unread without supplying the exact message count.
        $this->chat($unread, ['is_unread' => true, 'unread_count' => 0]);

        foreach ([
            'has_sales=1' => [$sale],
            'has_sales=0' => [$empty, $closedOrder, $read, $unread],
            'has_orders=true' => [$closedOrder],
            'has_orders=false' => [$empty, $sale, $read, $unread],
            'has_avito_chats=1' => [$read, $unread],
            'has_avito_chats=0' => [$empty, $sale, $closedOrder],
            'has_unread_avito=1' => [$unread],
            'has_unread_avito=0' => [$empty, $sale, $closedOrder, $read],
            'has_avito_chats=1&has_unread_avito=0' => [$read],
            'has_sales=1&has_orders=1' => [],
            'has_sales=&has_orders=&has_avito_chats=&has_unread_avito=' => [$empty, $sale, $closedOrder, $read, $unread],
        ] as $query => $expected) {
            $response = $this->getJson('/api/entities?'.$query)
                ->assertOk()
                ->assertJsonPath('meta.total', count($expected));
            $this->assertEqualsCanonicalizing($expected, $response->json('data.*.id'), $query);
        }
    }

    public function test_new_aggregate_sorting_and_page_markers_follow_the_filtered_list(): void
    {
        $first = $this->entity('Первый');
        $second = $this->entity('Второй');
        $this->entity('Без чата');
        $this->chat($first);
        $this->chat($second);
        $this->chat($second);
        DB::table('orders')->insert([
            ['entity_id' => $first, 'order_status_id' => 1, 'submitted_at' => '2026-09-20 12:00:00'],
            ['entity_id' => $second, 'order_status_id' => 2, 'submitted_at' => '2026-09-21 12:00:00'],
            ['entity_id' => $second, 'order_status_id' => 3, 'submitted_at' => '2026-09-22 12:00:00'],
        ]);

        foreach (['avito_chats_count', 'orders_count', 'orders_max_submitted_at'] as $sort) {
            $this->getJson('/api/entities?has_avito_chats=1&itemsPerPage=1&page=2&sortDesc=true&sortBy='.$sort)
                ->assertOk()
                ->assertJsonPath('data.0.id', $first)
                ->assertJsonPath('meta.total', 2)
                ->assertJsonPath('meta.current_page', 2)
                ->assertJsonPath('meta.last_page', 2)
                ->assertJsonPath('meta.page_markers.0.first_name', 'Второй')
                ->assertJsonPath('meta.page_markers.1.first_name', 'Первый');
        }
    }

    public function test_invalid_presence_filters_are_rejected_instead_of_silently_selecting_absence(): void
    {
        $this->getJson('/api/entities?has_sales=maybe&has_orders[]=1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['has_sales', 'has_orders']);
    }

    private function entity(string $name): int
    {
        return DB::table('entities')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function chat(?int $entityId, array $attributes = []): int
    {
        return DB::table('avito_chats')->insertGetId([
            'entity_id' => $entityId,
            ...$attributes,
        ]);
    }
}
