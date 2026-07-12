<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'delivery_address')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->text('delivery_address')->nullable()->after('city_id');
            });
        }

        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'customer_phone_source')) {
                $table->string('customer_phone_source', 32)->nullable()->after('customer_phone');
            }

            if (! Schema::hasColumn('orders', 'delivery_address')) {
                $table->text('delivery_address')->nullable()->after('customer_entity_name');
            }

            if (! Schema::hasColumn('orders', 'preferred_delivery_time')) {
                $table->string('preferred_delivery_time')->nullable()->after('delivery_address');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                if (Schema::hasColumn('orders', 'preferred_delivery_time')) {
                    $table->dropColumn('preferred_delivery_time');
                }

                if (Schema::hasColumn('orders', 'delivery_address')) {
                    $table->dropColumn('delivery_address');
                }

                if (Schema::hasColumn('orders', 'customer_phone_source')) {
                    $table->dropColumn('customer_phone_source');
                }
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'delivery_address')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('delivery_address');
            });
        }
    }
};
