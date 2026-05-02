<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\SyncYandexMailboxJob;
use App\Models\Email;
use App\Models\MailMessage;
use App\Models\Sending;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use Illuminate\Support\Facades\Log;

class UnitMailController extends Controller
{
    public function index(Request $request, Unit $unit): JsonResponse
    {
        $relatedEmails = $this->relatedEmails($unit);
        $emailIds = $relatedEmails->pluck('id')->values();

        $itemsPerPage = (int) $request->input('itemsPerPage', 15);

        $query = MailMessage::query()
            ->with(['emails:id,address,name'])
            ->whereHas('emails', fn ($q) => $q->whereIn('emails.id', $emailIds))
            ->when($request->input('search'), function ($q, $search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('subject', 'like', "%{$search}%")
                        ->orWhere('from_address', 'like', "%{$search}%")
                        ->orWhere('from_name', 'like', "%{$search}%")
                        ->orWhere('preview', 'like', "%{$search}%")
                        ->orWhereHas('emails', fn ($eq) => $eq->where('address', 'like', "%{$search}%"));
                });
            })
            ->when($request->input('direction'), fn ($q, $direction) => $q->where('direction', $direction))
            ->orderByDesc('message_date')
            ->orderByDesc('id');

        if ($itemsPerPage === -1) {
            $items = $query->get();

            return response()->json([
                                        'data' => $items,
                                        'total' => $items->count(),
                                        'related_emails' => $relatedEmails->values(),
                                    ]);
        }

        $paginator = $query->paginate(
            perPage: max($itemsPerPage, 1),
            page: (int) $request->input('page', 1)
        );

        return response()->json([
                                    'data' => $paginator->items(),
                                    'total' => $paginator->total(),
                                    'related_emails' => $relatedEmails->values(),
                                ]);
    }

    public function send(Request $request, Unit $unit): JsonResponse
    {
        Log::info('Unit mail send endpoint reached', [
            'unit_id' => $unit->id,
            'unit_name' => $unit->name,
            'payload_keys' => array_keys($request->all()),
            'to' => $request->input('to'),
            'subject' => $request->input('subject'),
            'has_local_attachments' => $request->hasFile('attachments'),
            'storage_files' => $request->input('storage_files'),
        ]);

        $data = $request->validate([
                                       'to' => ['required', 'array', 'min:1'],
                                       'to.*' => ['required', 'email'],
                                       'cc' => ['nullable', 'array'],
                                       'cc.*' => ['nullable', 'email'],
                                       'subject' => ['required', 'string', 'max:255'],
                                       'body' => ['required', 'string'],
                                       'unit_file_paths' => ['nullable', 'array'],
                                       'unit_file_paths.*' => ['string'],
                                       'attachments' => ['nullable', 'array'],
                                       'attachments.*' => ['file', 'max:20480'],
                                   ]);

        $relatedAddresses = $this->relatedEmails($unit)
            ->pluck('address')
            ->map(fn ($address) => Str::lower($address))
            ->values();

        $to = collect($data['to'])
            ->map(fn ($address) => Str::lower(trim($address)))
            ->unique()
            ->values();

        $cc = collect($data['cc'] ?? [])
            ->map(fn ($address) => Str::lower(trim($address)))
            ->filter()
            ->unique()
            ->values();

        $forbidden = $to
            ->merge($cc)
            ->reject(fn ($address) => $relatedAddresses->contains($address))
            ->values();

        if ($forbidden->isNotEmpty()) {
            throw ValidationException::withMessages([
                                                        'to' => 'Есть адреса, которые не связаны с этим Unit: ' . $forbidden->implode(', '),
                                                    ]);
        }

        $unitFilePaths = collect($data['unit_file_paths'] ?? [])
            ->filter()
            ->unique()
            ->values();

        $localFiles = $request->file('attachments', []);

        $sent = [];

        foreach ($to as $recipientAddress) {
            $email = Email::withTrashed()->firstOrCreate(
                ['address' => $recipientAddress],
                [
                    'source' => 'manual',
                    'is_active' => true,
                    'last_seen_at' => now(),
                ]
            );

            if ($email->trashed()) {
                $email->restore();
            }

            $sending = Sending::create([
                                           'email_id' => $email->id,
                                           'subject' => $data['subject'],
                                           'text' => $this->renderTemplate($data['body'], $unit, $recipientAddress),
                                           'provider' => 'yandex_smtp',
                                           'status' => 'queued',
                                       ]);

            $html = $this->htmlBody(
                text: $this->renderTemplate($data['body'], $unit, $recipientAddress),
                sending: $sending
            );

            try {
                Mail::html($html, function ($message) use (
                    $recipientAddress,
                    $cc,
                    $data,
                    $localFiles,
                    $unitFilePaths
                ) {
                    $message
                        ->to($recipientAddress)
                        ->subject($data['subject']);

                    if ($cc->isNotEmpty()) {
                        $message->cc($cc->all());
                    }

                    foreach ($localFiles as $file) {
                        $message->attach($file->getRealPath(), [
                            'as' => $file->getClientOriginalName(),
                            'mime' => $file->getMimeType(),
                        ]);
                    }

                    foreach ($unitFilePaths as $path) {
                        $this->attachStorageFile($message, $path);
                    }
                });

                $sending->forceFill([
                                        'html' => $html,
                                        'status' => 'sent',
                                        'sent_at' => now(),
                                        'error' => null,
                                    ])->save();

                $sent[] = [
                    'email' => $recipientAddress,
                    'sending_id' => $sending->id,
                ];
            } catch (Throwable $exception) {
                $sending->forceFill([
                                        'status' => 'failed',
                                        'error' => $exception->getMessage(),
                                    ])->save();

                throw $exception;
            }
        }

        try {
            SyncYandexMailboxJob::dispatch(50)->delay(now()->addSeconds(15));
        } catch (Throwable $exception) {
            logger()->warning('Yandex sync dispatch after unit mail sending failed', [
                'unit_id' => $unit->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json([
                                    'message' => 'Письмо отправлено',
                                    'sent' => $sent,
                                ]);
    }

    protected function relatedEmails(Unit $unit)
    {
        $unit->loadMissing([
                               'emails',
                               'entities.emails',
                           ]);

        $direct = $unit->emails
            ->map(fn ($email) => [
                'id' => $email->id,
                'address' => $email->address,
                'name' => $email->name ?? null,
                'source' => 'unit',
                'source_label' => 'Unit',
                'entity_id' => null,
                'entity_name' => null,
            ]);

        $fromEntities = $unit->entities
            ->flatMap(function ($entity) {
                return $entity->emails->map(fn ($email) => [
                    'id' => $email->id,
                    'address' => $email->address,
                    'name' => $email->name ?? null,
                    'source' => 'entity',
                    'source_label' => 'Entity: ' . $entity->name,
                    'entity_id' => $entity->id,
                    'entity_name' => $entity->name,
                ]);
            });

        return $direct
            ->merge($fromEntities)
            ->filter(fn ($item) => !empty($item['address']))
            ->unique('address')
            ->sortBy('address')
            ->values();
    }

    protected function renderTemplate(string $body, Unit $unit, string $recipientAddress): string
    {
        return strtr($body, [
            '{{unit.name}}' => $unit->name,
            '{{unit.id}}' => (string) $unit->id,
            '{{email.address}}' => $recipientAddress,
        ]);
    }

    protected function htmlBody(string $text, Sending $sending): string
    {
        $trackingPixel = route('email.open', $sending->tracking_token);

        return nl2br(e($text))
            . '<br><br>'
            . '<img src="' . e($trackingPixel) . '" width="1" height="1" style="display:none" alt="">';
    }

    protected function attachStorageFile($message, string $path): void
    {
        $disk = config('services.yandex_mail.attachments_disk', config('filesystems.default'));

        if (!Storage::disk($disk)->exists($path)) {
            throw ValidationException::withMessages([
                                                        'unit_file_paths' => "Файл не найден в хранилище: {$path}",
                                                    ]);
        }

        $message->attachData(
            Storage::disk($disk)->get($path),
            basename($path),
            [
                'mime' => Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream',
            ]
        );
    }
}
