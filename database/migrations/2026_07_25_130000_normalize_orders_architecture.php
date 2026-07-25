<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (! Schema::hasTable('order_statuses')) {
            Schema::create('order_statuses', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 32)->unique();
                $table->string('name', 64);
                $table->string('color', 32)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(100);
                $table->boolean('is_closed')->default(false);
                $table->timestamps();
            });
        }

        $statuses = [
            [
                'code' => 'open',
                'name' => 'Открытые',
                'color' => '#2563eb',
                'sort_order' => 10,
                'is_closed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'deferred',
                'name' => 'Отложенные',
                'color' => '#d97706',
                'sort_order' => 20,
                'is_closed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'closed',
                'name' => 'Закрытые',
                'color' => '#64748b',
                'sort_order' => 30,
                'is_closed' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($statuses as $status) {
            DB::table('order_statuses')->updateOrInsert(
                ['code' => $status['code']],
                $status
            );
        }

        if (! Schema::hasTable('building_order')) {
            Schema::create('building_order', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('order_id')
                    ->constrained('orders')
                    ->cascadeOnDelete();
                $table->foreignId('building_id')
                    ->constrained('buildings')
                    ->cascadeOnDelete();
                $table->string('role', 32)->default('logistics');
                $table->unsignedSmallInteger('position')->default(0);
                $table->timestamps();

                $table->unique(['order_id', 'building_id', 'role']);
                $table->index(['order_id', 'position']);
            });
        }

        $missingOrderColumns = collect([
            'entity_id',
            'order_status_id',
            'created_by_user_id',
            'contact_telephone_id',
            'internal_comment',
            'closed_at',
        ])->reject(fn (string $column) => Schema::hasColumn('orders', $column));

        Schema::table('orders', function (Blueprint $table) use ($missingOrderColumns): void {
            if ($missingOrderColumns->contains('entity_id')) {
                $table->foreignId('entity_id')
                    ->nullable()
                    ->after('number')
                    ->constrained('entities')
                    ->nullOnDelete();
            }

            if ($missingOrderColumns->contains('order_status_id')) {
                $table->foreignId('order_status_id')
                    ->nullable()
                    ->after('entity_id')
                    ->constrained('order_statuses')
                    ->restrictOnDelete();
            }

            if ($missingOrderColumns->contains('created_by_user_id')) {
                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->after('order_status_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if ($missingOrderColumns->contains('contact_telephone_id')) {
                $table->foreignId('contact_telephone_id')
                    ->nullable()
                    ->after('created_by_user_id')
                    ->constrained('telephones')
                    ->nullOnDelete();
            }

            if ($missingOrderColumns->contains('internal_comment')) {
                $table->text('internal_comment')->nullable()->after('preferred_delivery_time');
            }

            if ($missingOrderColumns->contains('closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('notified_at');
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasIndex('orders', ['order_status_id', 'submitted_at'])) {
                $table->index(['order_status_id', 'submitted_at']);
            }

            if (! Schema::hasIndex('orders', ['entity_id', 'submitted_at'])) {
                $table->index(['entity_id', 'submitted_at']);
            }

            if (! Schema::hasIndex('orders', ['closed_at'])) {
                $table->index('closed_at');
            }
        });

        $statusIds = DB::table('order_statuses')->pluck('id', 'code');
        $hasLegacyUser = Schema::hasColumn('orders', 'user_id');
        $hasLegacyStatus = Schema::hasColumn('orders', 'status');
        $hasLegacyAddress = Schema::hasColumn('orders', 'delivery_address');

        DB::table('orders')
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (
                $hasLegacyAddress,
                $hasLegacyStatus,
                $hasLegacyUser,
                $statusIds,
                $now
            ): void {
                foreach ($orders as $order) {
                    $statusCode = $hasLegacyStatus
                        ? match ($order->status) {
                            'completed', 'cancelled', 'closed' => 'closed',
                            'deferred', 'postponed' => 'deferred',
                            default => 'open',
                        }
                    : ($statusIds->search($order->order_status_id, true) ?: 'open');

                    $userId = $hasLegacyUser ? $order->user_id : $order->created_by_user_id;
                    $entityId = $order->entity_id;

                    if (! $entityId && $userId) {
                        $entityId = DB::table('entity_user')
                            ->where('user_id', $userId)
                            ->orderByDesc('is_primary')
                            ->orderBy('id')
                            ->value('entity_id');
                    }

                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update([
                            'entity_id' => $entityId,
                            'order_status_id' => $statusIds[$statusCode],
                            'created_by_user_id' => $userId,
                            'closed_at' => $statusCode === 'closed'
                                ? ($order->closed_at ?: $order->updated_at ?: $now)
                                : null,
                        ]);

                    $address = $hasLegacyAddress
                        ? trim((string) $order->delivery_address)
                        : '';

                    if ($address === '') {
                        continue;
                    }

                    $cityId = $userId
                        ? DB::table('users')->where('id', $userId)->value('city_id')
                        : null;

                    $buildingId = DB::table('buildings')
                        ->where('address', $address)
                        ->when(
                            $cityId,
                            fn ($query) => $query->where('city_id', $cityId),
                            fn ($query) => $query->whereNull('city_id')
                        )
                        ->value('id');

                    if (! $buildingId) {
                        $buildingId = DB::table('buildings')->insertGetId([
                            'city_id' => $cityId,
                            'address' => $address,
                            'postcode' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    if ($entityId) {
                        DB::table('building_entities')->insertOrIgnore([
                            'building_id' => $buildingId,
                            'entity_id' => $entityId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    DB::table('building_order')->updateOrInsert(
                        [
                            'order_id' => $order->id,
                            'building_id' => $buildingId,
                            'role' => 'delivery',
                        ],
                        [
                            'position' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            });

        $foreignKeys = collect(Schema::getForeignKeys('orders'))->pluck('name');

        if (
            Schema::hasColumn('orders', 'user_id')
            && (
                DB::getDriverName() === 'sqlite'
                || $foreignKeys->contains('orders_user_id_foreign')
            )
        ) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropForeign(['user_id']));
        }

        if (Schema::hasIndex('orders', 'orders_status_index')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropIndex('orders_status_index'));
        }

        if (Schema::hasIndex('orders', 'orders_user_id_status_index')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropIndex('orders_user_id_status_index'));
        }

        $legacyColumns = collect([
            'user_id',
            'status',
            'customer_name',
            'customer_email',
            'customer_phone',
            'customer_phone_source',
            'customer_account_type',
            'customer_city_name',
            'customer_entity_name',
            'delivery_address',
            'metadata',
        ])->filter(fn (string $column) => Schema::hasColumn('orders', $column));

        if ($legacyColumns->isNotEmpty()) {
            Schema::table(
                'orders',
                fn (Blueprint $table) => $table->dropColumn($legacyColumns->all())
            );
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('status', 32)->default('new')->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 64)->nullable();
            $table->string('customer_phone_source', 32)->nullable();
            $table->string('customer_account_type', 32)->nullable();
            $table->string('customer_city_name')->nullable();
            $table->string('customer_entity_name')->nullable();
            $table->text('delivery_address')->nullable();
            $table->json('metadata')->nullable();

            $table->index(['user_id', 'status']);
        });

        DB::table('orders')
            ->leftJoin('order_statuses', 'order_statuses.id', '=', 'orders.order_status_id')
            ->select([
                'orders.id',
                'orders.created_by_user_id',
                'order_statuses.code as status_code',
            ])
            ->orderBy('orders.id')
            ->get()
            ->each(function ($order): void {
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'user_id' => $order->created_by_user_id,
                        'status' => match ($order->status_code) {
                            'closed' => 'completed',
                            'deferred' => 'deferred',
                            default => 'new',
                        },
                    ]);
            });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['order_status_id', 'submitted_at']);
            $table->dropIndex(['entity_id', 'submitted_at']);
            $table->dropIndex(['closed_at']);
            $table->dropConstrainedForeignId('contact_telephone_id');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('order_status_id');
            $table->dropConstrainedForeignId('entity_id');
            $table->dropColumn(['internal_comment', 'closed_at']);
        });

        Schema::dropIfExists('building_order');
        Schema::dropIfExists('order_statuses');
    }
};
