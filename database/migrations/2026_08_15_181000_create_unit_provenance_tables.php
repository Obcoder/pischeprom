<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('unit_business_context_id')->nullable()->constrained('unit_business_contexts')->restrictOnDelete();
            $table->char('source_key', 64)->unique();
            $table->string('source_type', 32);
            $table->string('source_label')->nullable();
            $table->string('source_reference', 1024)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('data_classification', 32);
            $table->string('visibility_scope', 32);
            $table->timestamp('observed_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('created_by_type', 16)->default('human');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'unit_business_context_id'], 'unit_source_context_idx');
            $table->index(['unit_id', 'visibility_scope'], 'unit_source_scope_idx');
        });

        Schema::create('unit_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('unit_business_context_id')->nullable()->constrained('unit_business_contexts')->restrictOnDelete();
            $table->foreignId('unit_source_id')->nullable()->constrained('unit_sources')->restrictOnDelete();
            $table->string('alias', 512);
            $table->string('normalized_alias');
            $table->string('alias_type', 32);
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('verification_status', 24)->default('unverified');
            $table->string('data_classification', 32);
            $table->string('visibility_scope', 32);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'normalized_alias', 'alias_type'], 'unit_alias_lookup_idx');
            $table->index(['unit_id', 'unit_business_context_id'], 'unit_alias_context_idx');
            $table->index(['unit_id', 'verification_status'], 'unit_alias_review_idx');
        });

        Schema::create('unit_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('unit_business_context_id')->nullable()->constrained('unit_business_contexts')->restrictOnDelete();
            $table->foreignId('unit_source_id')->nullable()->constrained('unit_sources')->restrictOnDelete();
            $table->string('observation_key', 128);
            $table->string('normalized_value', 1024)->nullable();
            $table->text('summary');
            $table->string('source_reference', 1024)->nullable();
            $table->string('verification_status', 24)->default('unverified');
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('data_classification', 32);
            $table->string('visibility_scope', 32);
            $table->timestamp('observed_at');
            $table->timestamp('last_checked_at')->nullable();
            $table->string('created_by_type', 16)->default('human');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rules_version', 64)->nullable();
            $table->string('model_version', 128)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'unit_business_context_id', 'observation_key'], 'unit_observation_context_key_idx');
            $table->index(['unit_id', 'verification_status'], 'unit_observation_review_idx');
            $table->index(['data_classification', 'visibility_scope'], 'unit_observation_policy_idx');
        });

        Schema::create('unit_contact_context_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('unit_business_context_id')->nullable()->constrained('unit_business_contexts')->restrictOnDelete();
            $table->foreignId('unit_source_id')->nullable()->constrained('unit_sources')->restrictOnDelete();
            $table->string('channel_type', 16);
            $table->foreignId('email_id')->nullable()->constrained('emails')->restrictOnDelete();
            $table->foreignId('telephone_id')->nullable()->constrained('telephones')->restrictOnDelete();
            $table->foreignId('uri_id')->nullable()->constrained('uris')->restrictOnDelete();
            $table->string('channel_value_snapshot', 512);
            $table->string('contact_role', 32)->default('business');
            $table->string('verification_status', 24)->default('unverified');
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('data_classification', 32);
            $table->string('visibility_scope', 32);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'unit_business_context_id', 'channel_type'], 'unit_contact_context_idx');
            $table->index(['unit_id', 'visibility_scope', 'archived_at'], 'unit_contact_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_contact_context_links');
        Schema::dropIfExists('unit_observations');
        Schema::dropIfExists('unit_aliases');
        Schema::dropIfExists('unit_sources');
    }
};
