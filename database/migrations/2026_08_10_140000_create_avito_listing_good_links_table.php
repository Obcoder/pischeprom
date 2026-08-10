<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avito_listing_good_links', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('avito_account_id');
            $table->unsignedBigInteger('avito_item_id');
            $table->foreignId('good_id')->constrained('goods')->cascadeOnDelete();
            $table->foreignId('last_price_value_id')
                ->nullable()
                ->constrained('good_price_type_values')
                ->nullOnDelete();
            $table->json('last_selected_fields')->nullable();
            $table->json('last_media_ids')->nullable();
            $table->boolean('include_facts')->default(true);
            $table->timestamp('last_prepared_at')->nullable();
            $table->timestamp('last_applied_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['avito_account_id', 'avito_item_id'],
                'avito_listing_good_links_account_item_unique'
            );
            $table->index(['good_id', 'updated_at'], 'avito_listing_good_links_good_updated_index');
        });

        Schema::create('avito_listing_good_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avito_listing_good_link_id')
                ->constrained('avito_listing_good_links')
                ->cascadeOnDelete();
            $table->string('mode', 24);
            $table->string('status', 32)->index();
            $table->json('selected_fields');
            $table->json('applied_fields')->nullable();
            $table->json('manual_fields')->nullable();
            $table->longText('source_snapshot')->nullable();
            $table->longText('remote_meta')->nullable();
            $table->timestamps();

            $table->index(
                ['avito_listing_good_link_id', 'created_at'],
                'avito_listing_good_transfers_link_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avito_listing_good_transfers');
        Schema::dropIfExists('avito_listing_good_links');
    }
};
