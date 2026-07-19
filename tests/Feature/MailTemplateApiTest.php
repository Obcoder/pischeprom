<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MailTemplateApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('mail_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_mail_templates_can_be_created_listed_updated_and_deleted(): void
    {
        $created = $this->postJson('/api/mail-templates', [
            'name' => 'Запрос прайса',
            'subject' => 'Пришлите актуальный прайс',
            'body' => 'Здравствуйте! Пришлите, пожалуйста, актуальный прайс.',
            'is_active' => true,
        ]);

        $created
            ->assertCreated()
            ->assertJsonPath('name', 'Запрос прайса')
            ->assertJsonPath('is_active', true);

        $templateId = $created->json('id');

        $this->getJson('/api/mail-templates')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $templateId);

        $this->putJson("/api/mail-templates/{$templateId}", [
            'name' => 'Запрос прайса поставщику',
            'subject' => 'Актуальный прайс',
            'body' => 'Добрый день! Просим прислать актуальный прайс.',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('name', 'Запрос прайса поставщику')
            ->assertJsonPath('is_active', false);

        $this->deleteJson("/api/mail-templates/{$templateId}")
            ->assertOk();

        $this->assertSoftDeleted('mail_templates', [
            'id' => $templateId,
        ]);
    }
}
