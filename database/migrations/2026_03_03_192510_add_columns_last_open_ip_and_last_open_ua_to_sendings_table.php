<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sendings', function (Blueprint $table) {

            // IP может быть IPv4 или IPv6 → 45 символов достаточно
            if (!Schema::hasColumn('sendings', 'last_open_ip')) {
                $table->string('last_open_ip', 45)
                    ->nullable()
                    ->after('last_clicked_at');
            }

            // User-Agent может быть длинным → лучше text
            if (!Schema::hasColumn('sendings', 'last_open_ua')) {
                $table->text('last_open_ua')
                    ->nullable()
                    ->after('last_open_ip');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sendings', function (Blueprint $table) {

            if (Schema::hasColumn('sendings', 'last_open_ua')) {
                $table->dropColumn('last_open_ua');
            }

            if (Schema::hasColumn('sendings', 'last_open_ip')) {
                $table->dropColumn('last_open_ip');
            }
        });
    }
};
