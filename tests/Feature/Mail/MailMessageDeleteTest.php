<?php

namespace Tests\Feature\Mail;

use App\Models\MailMessage;
use App\Models\User;
use App\Services\Mail\MailDeletionException;
use App\Services\Mail\YandexMailboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MailMessageDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
    }

    public function test_guest_cannot_delete_message_or_contact_server(): void
    {
        $message = $this->message();
        $service = Mockery::mock(YandexMailboxService::class);
        $service->shouldNotReceive('deleteMessage');
        $this->app->instance(YandexMailboxService::class, $service);

        $this->deleteJson($this->url($message))->assertUnauthorized();

        $this->assertDatabaseHas('mail_messages', ['id' => $message->id, 'deleted_at' => null]);
        Http::assertNothingSent();
    }

    #[DataProvider('unauthorizedStaff')]
    public function test_customer_blocked_and_unverified_staff_cannot_delete(array $attributes): void
    {
        $message = $this->message();
        $service = Mockery::mock(YandexMailboxService::class);
        $service->shouldNotReceive('deleteMessage');
        $this->app->instance(YandexMailboxService::class, $service);

        $this->actingAs($this->staff($attributes))->deleteJson($this->url($message))->assertForbidden();

        $this->assertDatabaseHas('mail_messages', ['id' => $message->id, 'deleted_at' => null]);
        Http::assertNothingSent();
    }

    public static function unauthorizedStaff(): array
    {
        return [
            'customer' => [['type' => 'customer']],
            'blocked employee' => [['status' => 'blocked']],
            'unverified employee' => [['email_verified_at' => null]],
        ];
    }

    public function test_verified_employee_delegates_deletion_of_exact_message_to_mailbox_service(): void
    {
        $message = $this->message();
        $neighbor = $this->message(['imap_uid' => 43, 'message_id' => '<neighbor@example.test>']);
        $service = Mockery::mock(YandexMailboxService::class);
        $service->shouldReceive('deleteMessage')->once()
            ->with(Mockery::on(fn (MailMessage $target): bool => $target->is($message)))
            ->andReturnUsing(function (MailMessage $target) use ($message): void {
                $this->assertDatabaseHas('mail_messages', ['id' => $message->id, 'deleted_at' => null]);
                $target->delete();
            });
        $this->app->instance(YandexMailboxService::class, $service);

        $this->actingAs($this->staff())->deleteJson($this->url($message))->assertOk()->assertJsonStructure(['message']);

        $this->assertSoftDeleted('mail_messages', ['id' => $message->id]);
        $this->assertDatabaseHas('mail_messages', ['id' => $neighbor->id, 'deleted_at' => null]);
        $this->getJson($this->url($message))->assertNotFound();
        $this->getJson('/api/mail-messages')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $neighbor->id);
        Http::assertNothingSent();
    }

    public function test_server_failure_preserves_local_message_and_hides_provider_details(): void
    {
        $message = $this->message();
        $service = Mockery::mock(YandexMailboxService::class);
        $service->shouldReceive('deleteMessage')->once()
            ->with(Mockery::on(fn (MailMessage $target): bool => $target->is($message)))
            ->andThrow(new RuntimeException('IMAP failure password=synthetic-private-secret host=private-provider.example'));
        $this->app->instance(YandexMailboxService::class, $service);

        $response = $this->actingAs($this->staff())->deleteJson($this->url($message))->assertStatus(502);

        $this->assertStringNotContainsString('synthetic-private-secret', $response->getContent());
        $this->assertStringNotContainsString('private-provider.example', $response->getContent());
        $this->assertDatabaseHas('mail_messages', ['id' => $message->id, 'subject' => 'Запрос предложения', 'deleted_at' => null]);
        Http::assertNothingSent();
    }

    #[DataProvider('deletionConflicts')]
    public function test_actionable_deletion_conflict_preserves_message_and_http_status(int $status, string $safeMessage): void
    {
        $message = $this->message();
        $service = Mockery::mock(YandexMailboxService::class);
        $service->shouldReceive('deleteMessage')->once()
            ->andThrow(new MailDeletionException($safeMessage, $status));
        $this->app->instance(YandexMailboxService::class, $service);

        $this->actingAs($this->staff())->deleteJson($this->url($message))
            ->assertStatus($status)->assertJsonPath('message', $safeMessage);

        $this->assertDatabaseHas('mail_messages', ['id' => $message->id, 'deleted_at' => null]);
        Http::assertNothingSent();
    }

    public static function deletionConflicts(): array
    {
        return [
            'no server identity' => [422, 'У письма нет идентификатора на почтовом сервере.'],
            'concurrent operation' => [409, 'Письмо обрабатывается. Повторите удаление позже.'],
            'remote identity mismatch' => [409, 'Письмо на сервере не соответствует сохранённому письму.'],
        ];
    }

    public function test_missing_message_returns_not_found_without_contacting_mailbox(): void
    {
        $service = Mockery::mock(YandexMailboxService::class);
        $service->shouldNotReceive('deleteMessage');
        $this->app->instance(YandexMailboxService::class, $service);

        $this->actingAs($this->staff())->deleteJson('/api/mail-messages/999999')->assertNotFound();

        Http::assertNothingSent();
    }

    private function message(array $attributes = []): MailMessage
    {
        return MailMessage::query()->create([
            'mailbox' => 'office@example.test', 'folder' => 'INBOX', 'direction' => 'incoming',
            'imap_uid' => 42, 'message_id' => '<delete-target@example.test>',
            'subject' => 'Запрос предложения', 'from_address' => 'buyer@example.test',
            'text' => 'Текст письма', ...$attributes,
        ]);
    }

    private function staff(array $attributes = []): User
    {
        return User::factory()->create([
            'type' => 'employee', 'status' => 'active', 'email_verified_at' => now(), ...$attributes,
        ]);
    }

    private function url(MailMessage $message): string
    {
        return '/api/mail-messages/'.$message->id;
    }
}
