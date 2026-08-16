<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_good_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('unit_business_context_id')->constrained('unit_business_contexts')->restrictOnDelete();
            $table->foreignId('good_id')->constrained('goods')->restrictOnDelete();
            $table->foreignId('unit_source_id')->nullable()->constrained('unit_sources')->restrictOnDelete();
            $table->foreignId('prospecting_candidate_id')->nullable()->constrained('prospecting_candidates')->nullOnDelete();
            $table->string('match_type', 24);
            $table->unsignedTinyInteger('relevance');
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('safe_rationale', 1000);
            $table->string('evidence_reference', 512)->nullable();
            $table->char('evidence_hash', 64);
            $table->string('status', 24)->default('suggested');
            $table->string('origin', 16)->default('manual');
            $table->string('rules_version', 64)->nullable();
            $table->string('model_version', 128)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('stale_after')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'unit_business_context_id', 'status'], 'unit_good_context_status_idx');
            $table->index(['good_id', 'match_type', 'status'], 'unit_good_match_lookup_idx');
            $table->unique(
                ['unit_id', 'unit_business_context_id', 'good_id', 'match_type'],
                'unit_good_match_unique',
            );
        });

        Schema::table('unit_contact_context_links', function (Blueprint $table): void {
            $table->char('normalized_hash', 64)->nullable()->after('channel_value_snapshot');
            $table->string('communication_state', 24)->default('review_required')->after('visibility_scope');
            $table->boolean('review_required')->default(true)->after('communication_state');
            $table->timestamp('last_verified_at')->nullable()->after('last_seen_at');
            $table->unique(
                ['unit_id', 'unit_business_context_id', 'channel_type', 'normalized_hash'],
                'unit_contact_normalized_unique',
            );
            $table->index(
                ['communication_state', 'review_required', 'archived_at'],
                'unit_contact_communication_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('unit_contact_context_links', function (Blueprint $table): void {
            $table->dropUnique('unit_contact_normalized_unique');
            $table->dropIndex('unit_contact_communication_idx');
            $table->dropColumn([
                'normalized_hash',
                'communication_state',
                'review_required',
                'last_verified_at',
            ]);
        });

        Schema::dropIfExists('unit_good_matches');
    }
};
