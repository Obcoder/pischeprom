<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_sequence')->nullable();
            $table->date('subject_date')->nullable();
            $table->boolean('auto_subject')->default(false);
            $table->unique(['unit_id', 'supplier_sequence']);
        });
        Schema::create('supplier_order_sequences', function (Blueprint $table) {
            $table->foreignId('unit_id')->primary()->constrained('units')->cascadeOnDelete();
            $table->unsignedBigInteger('last_sequence');
        });

        // Existing drafts and sent orders all count. Do not rewrite the subject
        // or body of any existing document, including already sent messages.
        DB::table('units')->whereIn('id', DB::table('supplier_orders')->select('unit_id'))
            ->orderBy('id')->chunkById(100, function ($units) {
                foreach ($units as $unit) {
                    $sequence = 0;
                    DB::table('supplier_orders')->where('unit_id', $unit->id)->orderBy('created_at')->orderBy('id')
                        ->get(['id', 'created_at'])->each(function ($order) use (&$sequence) {
                            DB::table('supplier_orders')->where('id', $order->id)->update([
                                'supplier_sequence' => ++$sequence,
                                'subject_date' => CarbonImmutable::parse($order->created_at, config('app.timezone', 'UTC'))
                                    ->setTimezone('Europe/Moscow')->toDateString(),
                            ]);
                        });
                    DB::table('supplier_order_sequences')->insert(['unit_id' => $unit->id, 'last_sequence' => $sequence]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_order_sequences');
        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->dropUnique(['unit_id', 'supplier_sequence']);
            $table->dropColumn(['supplier_sequence', 'subject_date', 'auto_subject']);
        });
    }
};
