<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'type')) {
                $table->string('type', 24)
                    ->default('customer')
                    ->after('password')
                    ->index();
            }

            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status', 24)
                    ->default('active')
                    ->after('type')
                    ->index();
            }

            if (! Schema::hasColumn('users', 'account_type')) {
                $table->string('account_type', 24)
                    ->default('individual')
                    ->after('status')
                    ->index();
            }

            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 32)
                    ->nullable()
                    ->after('email')
                    ->index();
            }

            if (! Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')
                    ->nullable()
                    ->after('phone');
            }

            if (! Schema::hasColumn('users', 'city_id')) {
                $table->foreignId('city_id')
                    ->nullable()
                    ->after('phone_verified_at')
                    ->constrained('cities')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'personal_data_consent_at')) {
                $table->timestamp('personal_data_consent_at')
                    ->nullable()
                    ->after('city_id');
            }

            if (! Schema::hasColumn('users', 'personal_data_consent_ip')) {
                $table->string('personal_data_consent_ip', 45)
                    ->nullable()
                    ->after('personal_data_consent_at');
            }

            if (! Schema::hasColumn('users', 'marketing_consent_at')) {
                $table->timestamp('marketing_consent_at')
                    ->nullable()
                    ->after('personal_data_consent_ip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                         'marketing_consent_at',
                         'personal_data_consent_ip',
                         'personal_data_consent_at',
                         'phone_verified_at',
                         'phone',
                         'account_type',
                         'status',
                         'type',
                     ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('users', 'city_id')) {
                $table->dropForeign(['city_id']);
                $table->dropColumn('city_id');
            }
        });
    }
};
