<?php

namespace Tests\Feature\Mail;

use App\Models\Email;
use App\Models\Entity;
use App\Models\MailMessage;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MailMessageRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_note_preserves_direct_and_indirect_unit_links_for_related_mail_navigation(): void
    {
        Http::preventStrayRequests();

        $directUnit = Unit::query()->create([
            'name' => 'Прямое подразделение',
            'is_customer' => true,
            'is_supplier' => false,
        ]);
        $entityUnit = Unit::query()->create([
            'name' => 'Подразделение контрагента',
            'is_customer' => true,
            'is_supplier' => false,
        ]);
        $entity = Entity::query()->create(['name' => 'Связанный контрагент']);
        $entity->units()->attach($entityUnit->id);
        $email = Email::query()->create(['address' => 'related@example.test']);
        $email->units()->attach($directUnit->id);
        $email->entities()->attach($entity->id);

        $message = $this->message('Текущее письмо', 'incoming');
        $message->emails()->attach($email->id, ['role' => 'from']);
        $reply = $this->message('Ответ контрагенту', 'outgoing');
        $reply->emails()->attach($email->id, ['role' => 'to']);
        $this->message('Письмо без связей', 'incoming');

        $saved = $this->postJson("/api/mail-messages/{$message->id}/notes", [
            'body' => 'Согласовать реквизиты счёта.',
        ])->assertOk()
            ->assertJsonPath('id', $message->id)
            ->assertJsonPath('notes.0.body', 'Согласовать реквизиты счёта.')
            ->assertJsonPath('emails.0.units.0.id', $directUnit->id)
            ->assertJsonPath('emails.0.entities.0.id', $entity->id)
            ->assertJsonPath('emails.0.entities.0.units.0.id', $entityUnit->id)
            ->assertJsonPath('emails.0.entities.0.units.0.name', $entityUnit->name)
            ->assertJsonMissingPath('emails.0.units.0.fields')
            ->assertJsonMissingPath('emails.0.entities.0.buildings')
            ->assertJsonMissingPath('emails.0.entities.0.units.0.fields');

        $related = $this->getJson('/api/mail-messages?'.http_build_query([
            'filters' => ['unit_id' => $saved->json('emails.0.entities.0.units.0.id')],
            'itemsPerPage' => 20,
            'page' => 1,
        ]))->assertOk()->assertJsonPath('total', 2);

        $this->assertEqualsCanonicalizing(
            [$message->id, $reply->id],
            array_column($related->json('data'), 'id'),
        );
        Http::assertNothingSent();
    }

    private function message(string $subject, string $direction): MailMessage
    {
        return MailMessage::query()->create([
            'mailbox' => 'office@example.test',
            'folder' => $direction === 'incoming' ? 'INBOX' : 'Sent',
            'direction' => $direction,
            'subject' => $subject,
            'from_address' => 'related@example.test',
            'message_date' => now(),
            'has_attachments' => false,
        ]);
    }
}
