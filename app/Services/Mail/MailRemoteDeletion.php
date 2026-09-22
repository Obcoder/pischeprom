<?php

namespace App\Services\Mail;

use App\Models\MailMessage;
use RuntimeException;
use Webklex\PHPIMAP\IMAP;

class MailRemoteDeletion
{
    public function delete($client, $folder, MailMessage $mailMessage): void
    {
        $uid = (int) $mailMessage->imap_uid;
        $client->openFolder($folder->path);
        $connection = $client->getConnection();
        $expected = $this->messageId($mailMessage->message_id);

        // A validated empty SEARCH confirms absence; fetch/connection failures do not.
        $matches = $connection->search(['UID '.$uid], IMAP::ST_UID)->validatedData();
        if ($matches === []) {
            // Legacy records may contain a sequence number or synthetic UID. Confirm absence
            // by Message-ID as well instead of mistaking an obsolete UID for a deleted mail.
            $this->requireMessageId($expected);
            $matchingIds = $connection->search(['HEADER Message-ID '.$connection->escapeString($expected)], IMAP::ST_UID)->validatedData();
            if ($matchingIds !== []) {
                throw new MailDeletionException('Письмо найдено на сервере под другим идентификатором. Синхронизируйте почту и удалите обновлённую запись.', 409);
            }

            return;
        }
        if (! is_array($matches) || array_map('intval', array_values($matches)) !== [$uid]) {
            throw new RuntimeException('Unexpected IMAP UID search response.');
        }

        $this->requireMessageId($expected);
        $remote = $folder->query()->leaveUnread()->setFetchBody(false)->setFetchFlags(false)->getMessageByUid($uid);
        $actual = $this->messageId((string) $remote->getMessageId());
        if ($actual !== $expected) {
            throw new MailDeletionException('Идентификатор письма на сервере изменился. Обновите список писем и повторите удаление.', 409);
        }

        $capabilities = array_map('strtoupper', $connection->getCapabilities()->validatedData());
        if (! in_array('UIDPLUS', $capabilities, true)) {
            throw new MailDeletionException('Почтовый сервер не поддерживает точечное удаление письма. Удаление отменено.', 409);
        }

        // Library delete()/move() can expunge other messages. Only expunge the verified UID.
        if (! $connection->store(['\\Deleted'], $uid, null, '+', true, IMAP::ST_UID)->validatedData()) {
            throw new RuntimeException('IMAP did not confirm the Deleted flag.');
        }
        $connection->requestAndResponse('UID EXPUNGE', [(string) $uid])->validatedData();
        if ($connection->search(['UID '.$uid], IMAP::ST_UID)->validatedData() !== []) {
            throw new RuntimeException('IMAP did not confirm message removal.');
        }
    }

    private function messageId(?string $value): string
    {
        return trim((string) $value, " \t\r\n<>");
    }

    private function requireMessageId(string $value): void
    {
        if ($value === '' || preg_match('/[\r\n\x00]/', $value)) {
            throw new MailDeletionException('Не удалось надёжно определить письмо на сервере: отсутствует корректный Message-ID. Удаление отменено.', 409);
        }
    }
}
