<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->decimal('total', 20, 2)->default(0)->change();
            $table->string('payment_reference', 128)->nullable()->unique()->after('entity_id');
            $table->string('payment_status', 32)->default('unpaid')->index()->after('total');
            $table->decimal('paid_amount', 20, 2)->default(0)->after('payment_status');
            $table->decimal('outstanding_amount', 20, 2)->default(0)->after('paid_amount');
            $table->decimal('overpaid_amount', 20, 2)->default(0)->after('outstanding_amount');
            $table->timestamp('paid_at')->nullable()->after('overpaid_amount');
        });

        Schema::table('entities', function (Blueprint $table): void {
            $table->string('bank_account_number', 34)->nullable()->after('legal_address');
            $table->string('bank_name', 1024)->nullable()->after('bank_account_number');
            $table->string('bank_bic', 16)->nullable()->after('bank_name');
            $table->string('bank_corr_account', 34)->nullable()->after('bank_bic');
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->decimal('amount', 20, 2)->default(0)->change();
        });

        DB::table('sales')->update([
            'outstanding_amount' => DB::raw('total'),
        ]);
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table): void {
            $table->dropColumn([
                'bank_account_number',
                'bank_name',
                'bank_bic',
                'bank_corr_account',
            ]);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropUnique(['payment_reference']);
            $table->dropColumn([
                'payment_reference',
                'payment_status',
                'paid_amount',
                'outstanding_amount',
                'overpaid_amount',
                'paid_at',
            ]);
            $table->double('total')->default(0)->change();
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->double('amount')->default(0)->change();
        });
    }
};
