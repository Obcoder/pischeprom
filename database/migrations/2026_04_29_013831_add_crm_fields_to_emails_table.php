<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            if (!Schema::hasColumn('emails', 'name')) {
                $table->string('name')->nullable()->after('address');
            }

            if (!Schema::hasColumn('emails', 'comment')) {
                $table->text('comment')->nullable()->after('name');
            }

            if (!Schema::hasColumn('emails', 'source')) {
                $table->string('source')->nullable()->after('comment');
            }

            if (!Schema::hasColumn('emails', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('source');
            }

            if (!Schema::hasColumn('emails', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('is_active');
            }

            if (!Schema::hasColumn('emails', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('verified_at');
            }

            if (!Schema::hasColumn('emails', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            if (Schema::hasColumn('emails', 'name')) {
                $table->dropColumn('name');
            }

            if (Schema::hasColumn('emails', 'comment')) {
                $table->dropColumn('comment');
            }

            if (Schema::hasColumn('emails', 'source')) {
                $table->dropColumn('source');
            }

            if (Schema::hasColumn('emails', 'is_active')) {
                $table->dropColumn('is_active');
            }

            if (Schema::hasColumn('emails', 'verified_at')) {
                $table->dropColumn('verified_at');
            }

            if (Schema::hasColumn('emails', 'last_seen_at')) {
                $table->dropColumn('last_seen_at');
            }

            if (Schema::hasColumn('emails', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
