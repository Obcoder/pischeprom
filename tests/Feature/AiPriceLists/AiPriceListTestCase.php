<?php

namespace Tests\Feature\AiPriceLists;

use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Enums\SourceChannel;
use App\Models\Entity;
use App\Models\PriceListImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class AiPriceListTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'queue.default' => 'sync',
            'ai-price-lists.enabled' => true,
            'ai-price-lists.authorization_enabled' => true,
            'ai-price-lists.queue_connection' => 'sync',
            'ai-price-lists.queue' => 'price-lists',
            'ai-price-lists.mail_ingestion.queue_connection' => 'sync',
            'ai-price-lists.mail_ingestion.queue' => 'mail-sync',
            'ai-price-lists.storage_disk' => 'local',
            'ai-price-lists.storage_prefix' => 'supplier-price-lists-test',
            'ai-price-lists.ai.enabled' => true,
            'ai-price-lists.ai.provider' => 'fake',
            'ai-price-lists.scanner' => 'null',
            'ai-price-lists.max.send_acknowledgement' => false,
            'services.max.access_token' => '',
        ]);
        Storage::fake('local');
        Notification::fake();
    }

    protected function userWith(array $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'crm']);
        }

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    protected function import(array $attributes = []): PriceListImport
    {
        return PriceListImport::query()->create([
            'source_key' => 'test:'.uniqid('', true),
            'source_channel' => SourceChannel::Email,
            'status' => PriceListStatus::ReviewRequired,
            'progress' => 100,
            'entity_id' => Entity::query()->create(['name' => 'Поставщик '.uniqid()])->id,
            'disk' => 'local',
            'path' => 'imports/test.csv',
            'original_name' => 'test.csv',
            'safe_name' => 'test.csv',
            'extension' => 'csv',
            'mime_type' => 'text/plain',
            'size_bytes' => 100,
            ...$attributes,
        ]);
    }
}
