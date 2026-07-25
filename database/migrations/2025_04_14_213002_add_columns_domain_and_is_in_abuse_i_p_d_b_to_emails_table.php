<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $domainExpression = match (DB::getDriverName()) {
            'sqlite' => "substr(address, instr(address, '@') + 1)",
            'pgsql' => "split_part(address, '@', 2)",
            default => "SUBSTRING_INDEX(address, '@', -1)",
        };

        Schema::table('emails', function (Blueprint $table) use ($domainExpression) {
            $table->string('domain')->storedAs($domainExpression)->nullable();
            $table->tinyInteger('AbuseIPDB')->nullable()->default(0)->after('domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            //
        });
    }
};
