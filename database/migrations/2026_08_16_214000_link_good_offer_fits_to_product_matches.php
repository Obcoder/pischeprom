<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospecting_search_jobs', function (Blueprint $table): void {
            $table->string('product_mapping_state', 48)->default('legacy_unreconciled')->after('status');
            $table->string('product_mapping_reason_code', 96)->nullable()->after('product_mapping_state');
            $table->index(['status', 'product_mapping_state'], 'prospecting_job_product_mapping_idx');
        });

        Schema::table('prospecting_search_job_goods', function (Blueprint $table): void {
            $table->string('source_origin', 32)->default('legacy_stage08')->after('role');
            $table->string('compatibility_state', 48)->default('legacy_unreconciled')->after('source_origin');
            $table->index(['compatibility_state', 'good_id'], 'prospecting_job_good_compatibility_idx');
        });

        Schema::table('unit_good_matches', function (Blueprint $table): void {
            $table->foreignId('unit_product_match_id')->nullable()->after('unit_business_context_id')
                ->constrained('unit_product_matches')->restrictOnDelete();
            $table->string('fit_status', 32)->nullable()->after('status');
            $table->string('compatibility_state', 48)->default('legacy_unreconciled')->after('fit_status');
            $table->index(['unit_product_match_id', 'fit_status'], 'unit_good_product_fit_idx');
            $table->index(['compatibility_state', 'status'], 'unit_good_compatibility_idx');
        });
    }

    public function down(): void
    {
        Schema::table('unit_good_matches', function (Blueprint $table): void {
            $table->dropIndex('unit_good_product_fit_idx');
            $table->dropIndex('unit_good_compatibility_idx');
            $table->dropConstrainedForeignId('unit_product_match_id');
            $table->dropColumn(['fit_status', 'compatibility_state']);
        });

        Schema::table('prospecting_search_job_goods', function (Blueprint $table): void {
            $table->dropIndex('prospecting_job_good_compatibility_idx');
            $table->dropColumn(['source_origin', 'compatibility_state']);
        });

        Schema::table('prospecting_search_jobs', function (Blueprint $table): void {
            $table->dropIndex('prospecting_job_product_mapping_idx');
            $table->dropColumn(['product_mapping_state', 'product_mapping_reason_code']);
        });
    }
};
