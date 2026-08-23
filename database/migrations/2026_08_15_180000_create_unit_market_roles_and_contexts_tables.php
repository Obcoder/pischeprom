<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('display_name');
            $table->json('name_translations')->nullable();
            $table->boolean('is_system')->default(true)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $now = now();

        DB::table('market_roles')->insert([
            ['code' => 'customer', 'display_name' => 'Клиент', 'name_translations' => json_encode(['ru' => 'Клиент', 'en' => 'Customer']), 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'supplier', 'display_name' => 'Поставщик', 'name_translations' => json_encode(['ru' => 'Поставщик', 'en' => 'Supplier']), 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'prospective_customer', 'display_name' => 'Потенциальный клиент', 'name_translations' => json_encode(['ru' => 'Потенциальный клиент', 'en' => 'Prospective customer']), 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'prospective_supplier', 'display_name' => 'Потенциальный поставщик', 'name_translations' => json_encode(['ru' => 'Потенциальный поставщик', 'en' => 'Prospective supplier']), 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'manufacturer', 'display_name' => 'Производитель', 'name_translations' => json_encode(['ru' => 'Производитель', 'en' => 'Manufacturer']), 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'carrier', 'display_name' => 'Перевозчик', 'name_translations' => json_encode(['ru' => 'Перевозчик', 'en' => 'Carrier']), 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'service_provider', 'display_name' => 'Исполнитель услуг', 'name_translations' => json_encode(['ru' => 'Исполнитель услуг', 'en' => 'Service provider']), 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'other', 'display_name' => 'Другая роль', 'name_translations' => json_encode(['ru' => 'Другая роль', 'en' => 'Other']), 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::create('market_role_unit', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('market_role_id')->constrained('market_roles')->restrictOnDelete();
            $table->string('source', 64)->default('manual');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['unit_id', 'market_role_id'], 'market_role_unit_unique');
            $table->index(['unit_id', 'archived_at'], 'market_role_unit_active_idx');
        });

        Schema::create('unit_business_contexts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('role_code', 32);
            $table->string('lane', 32);
            $table->string('stage', 48)->default('new');
            $table->string('status', 24)->default('active');
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('primary_good_id')->nullable()->constrained('goods')->nullOnDelete();
            $table->string('primary_segment')->nullable();
            $table->string('source', 64)->default('manual');
            $table->timestamp('first_activity_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('role_code', 'unit_context_role_code_fk')
                ->references('code')
                ->on('market_roles')
                ->restrictOnDelete()
                ->restrictOnUpdate();
            $table->unique(['unit_id', 'lane', 'role_code'], 'unit_context_identity_unique');
            $table->index(['unit_id', 'lane', 'status'], 'unit_context_lane_status_idx');
            $table->index(['role_code', 'stage'], 'unit_context_role_stage_idx');
            $table->index(['owner_user_id', 'status'], 'unit_context_owner_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_business_contexts');
        Schema::dropIfExists('market_role_unit');
        Schema::dropIfExists('market_roles');
    }
};
