<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('good_seos', function (Blueprint $table) {
            $table->boolean('include_in_sitemap')
                ->default(true)
                ->after('is_active');

            $table->boolean('include_in_yandex_feed')
                ->default(true)
                ->after('include_in_sitemap');

            $table->timestamp('index_now_sent_at')
                ->nullable()
                ->after('include_in_yandex_feed');

            $table->timestamp('last_generated_at')
                ->nullable()
                ->after('index_now_sent_at');

            $table->string('yandex_direct_title_1')
                ->nullable()
                ->after('last_generated_at');

            $table->string('yandex_direct_title_2')
                ->nullable()
                ->after('yandex_direct_title_1');

            $table->text('yandex_direct_text')
                ->nullable()
                ->after('yandex_direct_title_2');

            $table->text('utm_template')
                ->nullable()
                ->after('yandex_direct_text');

            $table->string('availability_status')
                ->default('on_request')
                ->after('utm_template');

            $table->string('min_order')
                ->nullable()
                ->after('availability_status');

            $table->text('delivery_note')
                ->nullable()
                ->after('min_order');

            $table->text('payment_note')
                ->nullable()
                ->after('delivery_note');

            $table->json('faq')
                ->nullable()
                ->after('payment_note');
        });
    }

    public function down(): void
    {
        Schema::table('good_seos', function (Blueprint $table) {
            $table->dropColumn([
                                   'include_in_sitemap',
                                   'include_in_yandex_feed',
                                   'index_now_sent_at',
                                   'last_generated_at',
                                   'yandex_direct_title_1',
                                   'yandex_direct_title_2',
                                   'yandex_direct_text',
                                   'utm_template',
                                   'availability_status',
                                   'min_order',
                                   'delivery_note',
                                   'payment_note',
                                   'faq',
                               ]);
        });
    }
};
