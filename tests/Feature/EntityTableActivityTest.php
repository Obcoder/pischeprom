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
        foreach (['entity_classifications', 'countries', 'building_types'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->string('name');
            });
        }
        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('country_id')->nullable();
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
                    $table->string('address')->nullable();
                    $table->softDeletes();
                } elseif ($name === 'telephones') {
                    $table->string('number')->nullable();
                } elseif ($name === 'chats') {
                    $table->string('numbers')->nullable();
                } elseif ($name === 'buildings') {
                    $table->unsignedBigInteger('city_id')->nullable();
                    $table->unsignedBigInteger('building_type_id')->nullable();
                    $table->string('address');
                    $table->string('postcode')->nullable();
                } else {
                    $table->string('name');
                    if ($name === 'cities') {
                        $table->unsignedBigInteger('region_id')->nullable();
                        $table->unsignedInteger('population')->default(0);
                    }
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

    public function test_list_includes_complete_city_and_address_geography_and_address_class(): void
    {
        $this->seedGeography();
        $entity = $this->entity('Два адреса', ['country_id' => 1]);
        DB::table('city_entity')->insert(['entity_id' => $entity, 'city_id' => 100]);
        DB::table('building_entities')->insert([
            ['entity_id' => $entity, 'building_id' => 1000],
            ['entity_id' => $entity, 'building_id' => 2000],
        ]);

        $response = $this->getJson('/api/entities')
            ->assertOk()
            ->assertJsonPath('data.0.country.name', 'Россия')
            ->assertJsonPath('data.0.cities.0.name', 'Москва')
            ->assertJsonPath('data.0.cities.0.region.id', 10)
            ->assertJsonPath('data.0.cities.0.region.name', 'Московский регион')
            ->assertJsonPath('data.0.cities.0.region.country.id', 1)
            ->assertJsonPath('data.0.cities.0.region.country.name', 'Россия')
            ->assertJsonCount(2, 'data.0.buildings');

        $addresses = collect($response->json('data.0.buildings'))->keyBy('id');
        $this->assertSame('ул. Тверская, 1', $addresses[1000]['address']);
        $this->assertSame('125009', $addresses[1000]['postcode']);
        $this->assertSame(['id' => 1, 'name' => 'Рабочий'], $addresses[1000]['building_type']);
        $this->assertSame(['id' => 2, 'name' => 'Склад'], $addresses[2000]['building_type']);
        $this->assertSame([
            'id' => 200,
            'name' => 'Минск',
            'region' => [
                'id' => 20,
                'name' => 'Минский регион',
                'country' => ['id' => 2, 'name' => 'Беларусь'],
            ],
        ], $addresses[2000]['city']);
    }

    public function test_geography_filters_find_direct_cities_address_only_cities_and_entity_country(): void
    {
        $this->seedGeography();
        $directCity = $this->entity('Город напрямую', ['country_id' => 2]);
        $addressOnly = $this->entity('Город только из адреса');
        $foreignAddress = $this->entity('Адрес в Беларуси');
        $countryOnly = $this->entity('Только страна', ['country_id' => 1]);
        $this->entity('Без географии');
        DB::table('city_entity')->insert(['entity_id' => $directCity, 'city_id' => 100]);
        DB::table('building_entities')->insert([
            ['entity_id' => $addressOnly, 'building_id' => 1000],
            ['entity_id' => $addressOnly, 'building_id' => 1001],
            ['entity_id' => $foreignAddress, 'building_id' => 2000],
        ]);

        foreach ([
            [['country_ids' => [1]], [$directCity, $addressOnly, $countryOnly]],
            [['country_ids' => [2]], [$directCity, $foreignAddress]],
            [['country_ids' => [1, 2]], [$directCity, $addressOnly, $foreignAddress, $countryOnly]],
            [['region_ids' => [10]], [$directCity, $addressOnly]],
            [['region_ids' => [20]], [$foreignAddress]],
            [['region_ids' => [10, 20]], [$directCity, $addressOnly, $foreignAddress]],
            [['city_ids' => [100]], [$directCity, $addressOnly]],
            [['city_ids' => [200]], [$foreignAddress]],
            [['city_ids' => [100, 200]], [$directCity, $addressOnly, $foreignAddress]],
            [['building_ids' => [1000]], [$addressOnly]],
            [['country_ids' => [1], 'region_ids' => [20]], []],
            [['country_ids' => [2], 'city_ids' => [100]], [$directCity]],
            [['region_ids' => [10], 'building_ids' => [2000]], []],
            [['region_ids' => [999]], []],
        ] as [$filters, $expected]) {
            $query = http_build_query($filters);
            $response = $this->getJson('/api/entities?'.$query)
                ->assertOk()
                ->assertJsonPath('meta.total', count($expected));
            $this->assertEqualsCanonicalizing($expected, $response->json('data.*.id'), $query);
        }
    }

    public function test_geography_filter_alternatives_do_not_escape_other_active_filters(): void
    {
        $this->seedGeography();
        DB::table('entity_classifications')->insert([
            ['id' => 1, 'name' => 'Клиент'],
            ['id' => 2, 'name' => 'Поставщик'],
        ]);
        $match = $this->entity('Клиент с продажей', ['entity_classification_id' => 1]);
        $wrongClass = $this->entity('Поставщик с продажей', ['entity_classification_id' => 2]);
        $noSale = $this->entity('Клиент без продажи', ['entity_classification_id' => 1]);
        foreach ([$match, $wrongClass, $noSale] as $entity) {
            DB::table('building_entities')->insert(['entity_id' => $entity, 'building_id' => 1000]);
        }
        foreach ([$match, $wrongClass] as $entity) {
            DB::table('sales')->insert(['entity_id' => $entity, 'date' => '2026-09-22']);
        }

        foreach (['country_ids' => 1, 'region_ids' => 10, 'city_ids' => 100] as $filter => $id) {
            $query = http_build_query([
                'has_sales' => 1,
                'entity_classification_ids' => [1],
                $filter => [$id],
            ]);
            $this->getJson('/api/entities?'.$query)
                ->assertOk()
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.id', $match);
        }

        $this->getJson('/api/entities?'.http_build_query([
            'has_sales' => 1,
            'entity_classification_ids' => [1],
            'country_ids' => [1],
            'region_ids' => [10],
            'city_ids' => [100],
            'building_ids' => [1000],
        ]))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $match);
    }

    public function test_filter_metadata_includes_geography_and_address_classes(): void
    {
        $this->seedGeography();

        $response = $this->getJson('/api/entities-meta')->assertOk();
        $cities = collect($response->json('cities'))->keyBy('id');
        $regions = collect($response->json('regions'))->keyBy('id');
        $addresses = collect($response->json('buildings'))->keyBy('id');

        $this->assertSame(10, $cities[100]['region_id']);
        $this->assertSame('Россия', $cities[100]['region']['country']['name']);
        $this->assertSame(2, $regions[20]['country_id']);
        $this->assertSame('Беларусь', $regions[20]['country']['name']);
        $this->assertSame(['id' => 1, 'name' => 'Рабочий'], $addresses[1000]['building_type']);
        $this->assertSame('Россия', $addresses[1000]['city']['region']['country']['name']);
    }

    private function seedGeography(): void
    {
        DB::table('countries')->insert([
            ['id' => 1, 'name' => 'Россия'],
            ['id' => 2, 'name' => 'Беларусь'],
        ]);
        DB::table('regions')->insert([
            ['id' => 10, 'name' => 'Московский регион', 'country_id' => 1],
            ['id' => 20, 'name' => 'Минский регион', 'country_id' => 2],
        ]);
        DB::table('cities')->insert([
            ['id' => 100, 'name' => 'Москва', 'region_id' => 10],
            ['id' => 200, 'name' => 'Минск', 'region_id' => 20],
        ]);
        DB::table('building_types')->insert([
            ['id' => 1, 'name' => 'Рабочий'],
            ['id' => 2, 'name' => 'Склад'],
        ]);
        DB::table('buildings')->insert([
            ['id' => 1000, 'city_id' => 100, 'building_type_id' => 1, 'address' => 'ул. Тверская, 1', 'postcode' => '125009'],
            ['id' => 1001, 'city_id' => 100, 'building_type_id' => 2, 'address' => 'ул. Тверская, 2', 'postcode' => null],
            ['id' => 2000, 'city_id' => 200, 'building_type_id' => 2, 'address' => 'пр. Независимости, 1', 'postcode' => null],
        ]);
    }

    private function entity(string $name, array $attributes = []): int
    {
        return DB::table('entities')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
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
