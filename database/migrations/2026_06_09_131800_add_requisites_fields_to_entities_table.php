<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (! Schema::hasColumn('entities', 'full_name')) {
                $table->string('full_name', 1024)->nullable()->after('name');
            }

            if (! Schema::hasColumn('entities', 'KPP')) {
                $table->string('KPP', 32)->nullable()->after('INN');
            }

            if (! Schema::hasColumn('entities', 'legal_address')) {
                $table->string('legal_address', 1024)->nullable()->after('OGRN');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (Schema::hasColumn('entities', 'legal_address')) {
                $table->dropColumn('legal_address');
            }

            if (Schema::hasColumn('entities', 'KPP')) {
                $table->dropColumn('KPP');
            }

            if (Schema::hasColumn('entities', 'full_name')) {
                $table->dropColumn('full_name');
            }
        });
    }
};
