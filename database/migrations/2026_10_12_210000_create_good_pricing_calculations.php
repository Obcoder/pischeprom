<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('good_pricing_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('good_id')->nullable()->constrained('goods')->nullOnDelete();
            $table->string('scope', 20);
            $table->foreignId('entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->string('target_name')->nullable();
            $table->string('name');
            $table->text('comment')->nullable();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('currency_code', 20)->nullable();
            $table->json('measurement');
            $table->json('input');
            $table->json('result');
            $table->decimal('price_net', 16, 4);
            $table->decimal('price_gross', 16, 4);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name')->nullable();
            $table->foreignId('price_history_id')->nullable()->unique()->constrained('good_price_histories')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['good_id', 'created_at']);
            $table->index(['scope', 'entity_id']);
        });

        $this->recoverHistory();
    }

    public function recoverHistory(): void
    {
        // The old workspace saved the calculation inside the price audit, but its
        // archive tab read a different table. Recover those exact saved snapshots.
        DB::table('good_price_histories')->whereNotNull('after')->orderBy('id')->chunkById(200, function ($histories) {
            foreach ($histories as $history) {
                $after = $this->decode($history->after);
                $input = $this->decode($after['pricing_input'] ?? null);
                $result = $this->decode($after['pricing_result'] ?? null);
                if (! $input || ! $result || ! isset($result['price_net'], $result['price_gross'])) {
                    continue;
                }
                if (DB::table('good_pricing_calculations')->where('price_history_id', $history->id)->exists()) {
                    continue;
                }
                $targetId = $history->scope === 'entity' ? ($after['entity_id'] ?? null) : ($after['price_type_id'] ?? null);
                $targetName = $after['target_name'] ?? null;
                $entityId = $history->scope === 'entity' && $targetId && DB::table('entities')->where('id', $targetId)->exists() ? $targetId : null;
                $currencyId = $result['currency_id'] ?? $after['currency_id'] ?? null;
                $currency = $currencyId ? DB::table('currencies')->find($currencyId) : null;
                $measurement = $this->decode($result['measurement'] ?? $after['measurement'] ?? $input['measurement'] ?? null);
                $input = [...$input, 'scope' => $history->scope, 'target_id' => $targetId,
                    'target_name' => $targetName, 'measurement' => $measurement];
                DB::table('good_pricing_calculations')->insertOrIgnore([
                    'good_id' => $history->good_id,
                    'scope' => $history->scope,
                    'entity_id' => $entityId,
                    'target_name' => $targetName,
                    'name' => mb_substr(trim($input['calculation_name'] ?? $input['name'] ?? '') ?: 'Расчёт'.($targetName ? ' · '.$targetName : ''), 0, 255),
                    'comment' => $input['comment'] ?? $after['manual_comment'] ?? null,
                    'currency_id' => $currency?->id,
                    'currency_code' => $after['currency_code'] ?? $currency?->code,
                    'measurement' => json_encode($measurement, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'input' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'price_net' => $result['price_net'],
                    'price_gross' => $result['price_gross'],
                    'created_by' => $history->actor_id,
                    'created_by_name' => $history->actor_name,
                    'price_history_id' => $history->id,
                    'created_at' => $history->created_at,
                ]);
            }
        });
    }

    private function decode(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }

    public function down(): void
    {
        Schema::dropIfExists('good_pricing_calculations');
    }
};
