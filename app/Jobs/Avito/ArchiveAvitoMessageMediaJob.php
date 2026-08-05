<?php

namespace App\Jobs\Avito;

use App\Models\AvitoMessage;
use App\Services\Avito\AvitoMessengerMediaArchive;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ArchiveAvitoMessageMediaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public function __construct(public readonly int $messageId) {}

    public function handle(AvitoMessengerMediaArchive $archive): void
    {
        $message = AvitoMessage::query()->find($this->messageId);

        if ($message) {
            $archive->archiveMessage($message);
        }
    }
}
