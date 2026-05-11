<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('quotations', 'denominator')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->double('denominator')->default(1)->after('measure_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('quotations', 'denominator')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->dropColumn('denominator');
            });
        }
    }
};
