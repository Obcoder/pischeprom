<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospecting_search_jobs', function (Blueprint $table): void {
            $table->string('launch_source_type', 24)->nullable()->after('workflow_version');
            $table->unsignedBigInteger('launch_source_id')->nullable()->after('launch_source_type');
            $table->string('wizard_version', 32)->nullable()->after('launch_source_id');
            $table->char('disclosure_policy_hash', 64)->nullable()->after('wizard_version');
            $table->char('draft_idempotency_key_hash', 64)->nullable()->after('disclosure_policy_hash');
            $table->foreignId('submitted_by')->nullable()->after('draft_idempotency_key_hash')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');

            $table->index(
                ['launch_source_type', 'launch_source_id'],
                'prospecting_job_launch_source_idx',
            );
            $table->unique(
                ['created_by', 'draft_idempotency_key_hash'],
                'prospecting_job_draft_idempotency_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('prospecting_search_jobs', function (Blueprint $table): void {
            $table->dropUnique('prospecting_job_draft_idempotency_unique');
            $table->dropIndex('prospecting_job_launch_source_idx');
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn([
                'launch_source_type',
                'launch_source_id',
                'wizard_version',
                'disclosure_policy_hash',
                'draft_idempotency_key_hash',
                'submitted_at',
            ]);
        });
    }
};
