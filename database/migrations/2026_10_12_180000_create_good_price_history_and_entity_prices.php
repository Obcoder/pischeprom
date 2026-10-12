<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('good_price_type_values', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1);
            $table->json('pricing_input')->nullable();
            $table->json('pricing_result')->nullable();
        });
        Schema::table('orders', fn (Blueprint $table) => $table->boolean('has_private_prices')->default(false));
        Schema::create('good_entity_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('good_id')->constrained('goods')->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('price_net', 16, 4);
            $table->decimal('price_gross', 16, 4);
            $table->decimal('vat_rate', 10, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->text('manual_comment')->nullable();
            $table->json('pricing_input')->nullable();
            $table->json('pricing_result')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['good_id', 'entity_id']);
            $table->index(['entity_id', 'is_active']);
        });
        // Contact/INN matches do not establish access to confidential prices.
        Schema::create('entity_price_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['entity_id', 'user_id']);
        });
        Schema::create('good_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('good_id')->nullable()->constrained('goods')->nullOnDelete();
            $table->string('scope', 20);
            $table->unsignedBigInteger('price_record_id');
            $table->string('event', 30);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at');
            $table->index(['good_id', 'id']);
            $table->index(['scope', 'price_record_id']);
        });
        // Preserve existing prices as an explicitly labelled starting snapshot.
        // Earlier changes cannot be reconstructed from overwritten records.
        DB::table('good_price_type_values')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $good = DB::table('goods')->find($row->good_id);
                $type = DB::table('price_types')->find($row->price_type_id);
                $currency = DB::table('currencies')->find($row->currency_id ?: $type?->currency_id);
                $measure = $good?->measure_id ? DB::table('measures')->find($good->measure_id) : null;
                DB::table('good_price_histories')->insert([
                    'good_id' => $row->good_id, 'scope' => 'common', 'price_record_id' => $row->id,
                    'event' => 'baseline', 'created_at' => now(),
                    'after' => json_encode([...get_object_vars($row), 'good_name' => $good?->name,
                        'target_name' => $type?->name, 'currency_code' => $currency?->code,
                        'measurement' => ['measure_id' => $measure?->id, 'unit_label' => $measure?->name,
                            'kilograms_per_unit' => match (mb_strtolower(rtrim(trim($measure?->name ?? ''), '.'))) {
                                'кг', 'килограмм', 'килограммы', 'kg', 'kilogram', 'kilograms' => 1,
                                'г', 'гр', 'грамм', 'граммы', 'gram', 'grams', 'g' => 0.001,
                                'т', 'тн', 'тонна', 'тонны', 'тонн', 't', 'ton', 'tons', 'tonne', 'tonnes' => 1000,
                                default => $good?->unit_weight_kg,
                            }]], JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('has_private_prices'));
        Schema::dropIfExists('good_price_histories');
        Schema::dropIfExists('entity_price_access');
        Schema::dropIfExists('good_entity_prices');
        Schema::table('good_price_type_values', fn (Blueprint $table) => $table->dropColumn(['revision', 'pricing_input', 'pricing_result']));
    }
};
