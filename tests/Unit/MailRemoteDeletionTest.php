<?php

namespace Tests\Unit;

use App\Models\MailMessage;
use App\Services\Mail\MailDeletionException;
use App\Services\Mail\MailRemoteDeletion;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webklex\PHPIMAP\Attribute;
use Webklex\PHPIMAP\Connection\Protocols\Response;
use Webklex\PHPIMAP\Exceptions\MessageHeaderFetchingException;
use Webklex\PHPIMAP\Exceptions\ResponseException;
use Webklex\PHPIMAP\IMAP;

class MailRemoteDeletionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_verified_uid_is_deleted_with_targeted_expunge_and_removal_is_checked(): void
    {
        [$client, $folder, $connection] = $this->connection();
        $connection->shouldReceive('search')->once()->with(['UID 42'], IMAP::ST_UID)
            ->ordered()->andReturn($this->response([42]));
        $this->header($folder, 'target@example.test');
        $connection->shouldReceive('getCapabilities')->once()->ordered()->andReturn($this->response(['IMAP4rev1', 'uidplus']));
        $connection->shouldReceive('store')->once()->with(['\\Deleted'], 42, null, '+', true, IMAP::ST_UID)
            ->ordered()->andReturn($this->response(true));
        $connection->shouldReceive('requestAndResponse')->once()->with('UID EXPUNGE', ['42'])
            ->ordered()->andReturn($this->response([[7, 'EXPUNGE']]));
        $connection->shouldReceive('search')->once()->with(['UID 42'], IMAP::ST_UID)
            ->ordered()->andReturn($this->response([]));

        (new MailRemoteDeletion)->delete($client, $folder, $this->message());
    }

    public function test_absence_is_confirmed_by_uid_and_escaped_message_id_search_without_mutation(): void
    {
        [$client, $folder, $connection] = $this->connection();
        $connection->shouldReceive('search')->once()->with(['UID 42'], IMAP::ST_UID)->andReturn($this->response([]));
        $this->messageIdSearch($connection, []);
        $folder->shouldNotReceive('query');
        $this->noMutation($connection);
        $connection->shouldNotReceive('getCapabilities');

        (new MailRemoteDeletion)->delete($client, $folder, $this->message());
    }

    public function test_absent_legacy_uid_does_not_hide_a_message_still_found_by_message_id(): void
    {
        [$client, $folder, $connection] = $this->connection();
        $connection->shouldReceive('search')->once()->with(['UID 42'], IMAP::ST_UID)->andReturn($this->response([]));
        $this->messageIdSearch($connection, [87]);
        $folder->shouldNotReceive('query');
        $this->noMutation($connection);
        $connection->shouldNotReceive('getCapabilities');

        try {
            (new MailRemoteDeletion)->delete($client, $folder, $this->message());
            $this->fail('A changed UID must not be treated as confirmation that the message is gone.');
        } catch (MailDeletionException $exception) {
            $this->assertSame(409, $exception->status);
        }
    }

    public function test_absent_uid_without_message_id_cannot_confirm_remote_absence(): void
    {
        [$client, $folder, $connection] = $this->connection();
        $connection->shouldReceive('search')->once()->with(['UID 42'], IMAP::ST_UID)->andReturn($this->response([]));
        $connection->shouldNotReceive('escapeString');
        $folder->shouldNotReceive('query');
        $this->noMutation($connection);

        try {
            (new MailRemoteDeletion)->delete($client, $folder, $this->message(['message_id' => null]));
            $this->fail('An absent UID alone cannot confirm remote absence for a legacy record.');
        } catch (MailDeletionException $exception) {
            $this->assertSame(409, $exception->status);
        }
    }

    public function test_failed_message_id_search_cannot_confirm_remote_absence(): void
    {
        [$client, $folder, $connection] = $this->connection();
        $connection->shouldReceive('search')->once()->with(['UID 42'], IMAP::ST_UID)->andReturn($this->response([]));
        $this->messageIdSearch($connection, [], 'HEADER SEARCH failed');
        $folder->shouldNotReceive('query');
        $this->noMutation($connection);

        $this->expectException(ResponseException::class);
        (new MailRemoteDeletion)->delete($client, $folder, $this->message());
    }

    public function test_failed_search_is_never_treated_as_absence(): void
    {
        [$client, $folder, $connection] = $this->connection();
        $connection->shouldReceive('search')->once()->with(['UID 42'], IMAP::ST_UID)
            ->andReturn($this->response([])->addError('IMAP SEARCH failed'));
        $folder->shouldNotReceive('query');
        $this->noMutation($connection);

        $this->expectException(ResponseException::class);
        (new MailRemoteDeletion)->delete($client, $folder, $this->message());
    }

    public function test_connection_failure_is_never_treated_as_absence(): void
    {
        [$client, $folder, $connection] = $this->connection();
        $connection->shouldReceive('search')->once()->with(['UID 42'], IMAP::ST_UID)
            ->andThrow(new RuntimeException('IMAP connection timed out'));
        $folder->shouldNotReceive('query');
        $this->noMutation($connection);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('IMAP connection timed out');
        (new MailRemoteDeletion)->delete($client, $folder, $this->message());
    }

    #[DataProvider('unexpectedSearchResults')]
    public function test_unexpected_uid_search_results_never_start_deletion(mixed $result): void
    {
        [$client, $folder, $connection] = $this->connection();
        $connection->shouldReceive('search')->once()->andReturn($this->response($result));
        $folder->shouldNotReceive('query');
        $this->noMutation($connection);

        $this->expectException(RuntimeException::class);
        (new MailRemoteDeletion)->delete($client, $folder, $this->message());
    }

    public static function unexpectedSearchResults(): array
    {
        return [
            'another uid' => [[43]],
            'target and neighbor' => [[42, 43]],
            'unparsed data' => ['SEARCH 42'],
        ];
    }

    public function test_missing_local_message_id_prevents_server_mutation(): void
    {
        [$client, $folder, $connection] = $this->connection();
        $connection->shouldReceive('search')->once()->andReturn($this->response([42]));
        $folder->shouldNotReceive('query');
        $this->noMutation($connection);
        $connection->shouldNotReceive('getCapabilities');

        try {
            (new MailRemoteDeletion)->delete($client, $folder, $this->message(['message_id' => null]));
            $this->fail('An unverified message identity must not be deleted.');
        } catch (MailDeletionException $exception) {
            $this->assertSame(409, $exception->status);
        }
    }

    #[DataProvider('mismatchedMessageIds')]
    public function test_mismatched_or_missing_remote_message_id_prevents_mutation(string $remoteId): void
    {
        [$client, $folder, $connection] = $this->connection();
        $connection->shouldReceive('search')->once()->andReturn($this->response([42]));
        $this->header($folder, $remoteId);
        $this->noMutation($connection);
        $connection->shouldNotReceive('getCapabilities');

        try {
            (new MailRemoteDeletion)->delete($client, $folder, $this->message());
            $this->fail('A different message must not be deleted even if its UID matches.');
        } catch (MailDeletionException $exception) {
            $this->assertSame(409, $exception->status);
        }
    }

    public static function mismatchedMessageIds(): array
    {
        return [
            'different message' => ['neighbor@example.test'],
            'missing header' => [''],
            'local part case remains significant' => ['Target@example.test'],
        ];
    }

    public function test_header_fetch_failure_is_not_confused_with_already_deleted_message(): void
    {
        [$client, $folder, $connection] = $this->connection();
        $connection->shouldReceive('search')->once()->andReturn($this->response([42]));
        $query = $this->query($folder);
        $query->shouldReceive('getMessageByUid')->once()->with(42)
            ->andThrow(new MessageHeaderFetchingException('no headers found'));
        $this->noMutation($connection);

        $this->expectException(MessageHeaderFetchingException::class);
        (new MailRemoteDeletion)->delete($client, $folder, $this->message());
    }

    public function test_server_without_uidplus_does_not_even_mark_target_deleted(): void
    {
        [$client, $folder, $connection] = $this->connection();
        $connection->shouldReceive('search')->once()->andReturn($this->response([42]));
        $this->header($folder, 'target@example.test');
        $connection->shouldReceive('getCapabilities')->once()->andReturn($this->response(['IMAP4rev1', 'MOVE']));
        $this->noMutation($connection);

        try {
            (new MailRemoteDeletion)->delete($client, $folder, $this->message());
            $this->fail('Lack of UIDPLUS must not fall back to a mailbox-wide expunge or MOVE.');
        } catch (MailDeletionException $exception) {
            $this->assertSame(409, $exception->status);
        }
    }

    public function test_store_rejection_never_reaches_expunge(): void
    {
        [$client, $folder, $connection] = $this->connection();
        $this->readyForMutation($folder, $connection);
        $connection->shouldReceive('store')->once()->with(['\\Deleted'], 42, null, '+', true, IMAP::ST_UID)
            ->andReturn($this->response(false)->addError('STORE refused'));
        $connection->shouldNotReceive('requestAndResponse');

        $this->expectException(ResponseException::class);
        (new MailRemoteDeletion)->delete($client, $folder, $this->message());
    }

    public function test_uid_expunge_rejection_does_not_fall_back_to_global_expunge(): void
    {
        [$client, $folder, $connection] = $this->connection();
        $this->readyForMutation($folder, $connection);
        $connection->shouldReceive('store')->once()->andReturn($this->response(true));
        $connection->shouldReceive('requestAndResponse')->once()->with('UID EXPUNGE', ['42'])
            ->andReturn($this->response(false)->addError('UID EXPUNGE refused'));

        $this->expectException(ResponseException::class);
        (new MailRemoteDeletion)->delete($client, $folder, $this->message());
    }

    public function test_uid_still_present_after_expunge_is_a_failure(): void
    {
        [$client, $folder, $connection] = $this->connection();
        $connection->shouldReceive('search')->twice()->with(['UID 42'], IMAP::ST_UID)->andReturn($this->response([42]));
        $this->header($folder, 'target@example.test');
        $connection->shouldReceive('getCapabilities')->once()->andReturn($this->response(['UIDPLUS']));
        $connection->shouldReceive('store')->once()->andReturn($this->response(true));
        $connection->shouldReceive('requestAndResponse')->once()->with('UID EXPUNGE', ['42'])->andReturn($this->response(true));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('IMAP did not confirm message removal.');
        (new MailRemoteDeletion)->delete($client, $folder, $this->message());
    }

    private function readyForMutation($folder, $connection): void
    {
        $connection->shouldReceive('search')->once()->with(['UID 42'], IMAP::ST_UID)->andReturn($this->response([42]));
        $this->header($folder, 'target@example.test');
        $connection->shouldReceive('getCapabilities')->once()->andReturn($this->response(['UIDPLUS']));
    }

    private function connection(): array
    {
        $connection = Mockery::mock();
        $connection->shouldNotReceive('expunge');
        $connection->shouldNotReceive('moveMessage');
        $connection->shouldNotReceive('copyMessage');
        $client = Mockery::mock();
        $client->shouldReceive('openFolder')->once()->with('INBOX');
        $client->shouldReceive('getConnection')->once()->andReturn($connection);
        $client->shouldNotReceive('expunge');
        $folder = Mockery::mock();
        $folder->path = 'INBOX';

        return [$client, $folder, $connection];
    }

    private function header($folder, string $messageId): void
    {
        $remote = Mockery::mock();
        $remote->shouldReceive('getMessageId')->once()->andReturn(new Attribute('message_id', $messageId));
        $remote->shouldNotReceive('delete');
        $remote->shouldNotReceive('move');
        $this->query($folder)->shouldReceive('getMessageByUid')->once()->with(42)->andReturn($remote);
    }

    private function query($folder)
    {
        $query = Mockery::mock();
        $folder->shouldReceive('query')->once()->andReturn($query);
        $query->shouldReceive('leaveUnread')->once()->andReturnSelf();
        $query->shouldReceive('setFetchBody')->once()->with(false)->andReturnSelf();
        $query->shouldReceive('setFetchFlags')->once()->with(false)->andReturnSelf();

        return $query;
    }

    private function noMutation($connection): void
    {
        $connection->shouldNotReceive('store');
        $connection->shouldNotReceive('requestAndResponse');
    }

    private function messageIdSearch($connection, array $uids, ?string $error = null): void
    {
        $connection->shouldReceive('escapeString')->once()->with('target@example.test')->andReturn('"target@example.test"');
        $response = $this->response($uids);
        if ($error !== null) {
            $response->addError($error);
        }
        $connection->shouldReceive('search')->once()->with(
            Mockery::on(fn (array $tokens): bool => implode(' ', $tokens) === 'HEADER Message-ID "target@example.test"'),
            IMAP::ST_UID,
        )->andReturn($response);
    }

    private function response(mixed $result): Response
    {
        return Response::empty()->setResult($result)->setCanBeEmpty($result === []);
    }

    private function message(array $attributes = []): MailMessage
    {
        return new MailMessage([
            'mailbox' => 'office@example.test', 'folder' => 'INBOX', 'imap_uid' => 42,
            'message_id' => '<target@example.test>', ...$attributes,
        ]);
    }
}
