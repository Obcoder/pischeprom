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

    public function send(Request $request, Unit $unit)
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

        $validated = $request->validate([
                                            'to' => ['required', 'array', 'min:1'],
                                            'to.*' => ['required', 'email'],

                                            'subject' => ['required', 'string', 'max:255'],
                                            'body' => ['nullable', 'string'],

                                            'attachments' => ['nullable', 'array'],
                                            'attachments.*' => ['file', 'max:20480'],

                                            'storage_files' => ['nullable', 'array'],
                                            'storage_files.*' => ['string'],
                                        ]);

        $to = array_values(array_filter($validated['to'] ?? []));
        $subject = $validated['subject'];
        $body = $validated['body'] ?? '';

        $localAttachments = $request->file('attachments', []);
        $storageFiles = $validated['storage_files'] ?? [];

        try {
            Mail::html(nl2br(e($body)), function ($message) use (
                $to,
                $subject,
                $localAttachments,
                $storageFiles
            ) {
                $message->to($to);
                $message->subject($subject);

                foreach ($localAttachments as $file) {
                    if (!$file) {
                        continue;
                    }

                    $message->attach($file->getRealPath(), [
                        'as' => $file->getClientOriginalName(),
                        'mime' => $file->getMimeType() ?: 'application/octet-stream',
                    ]);
                }

                $diskName = config('filesystems.unit_files_disk', 'yandex');
                $disk = Storage::disk($diskName);

                foreach ($storageFiles as $path) {
                    if (!$path) {
                        continue;
                    }

                    if (!$disk->exists($path)) {
                        Log::warning('Unit mail storage attachment not found', [
                            'disk' => $diskName,
                            'path' => $path,
                        ]);

                        continue;
                    }

                    $message->attachData(
                        $disk->get($path),
                        basename($path),
                        [
                            'mime' => $disk->mimeType($path) ?: 'application/octet-stream',
                        ]
                    );
                }
            });

            Log::info('Unit mail sent successfully', [
                'unit_id' => $unit->id,
                'unit_name' => $unit->name,
                'to' => $to,
                'subject' => $subject,
                'local_attachments_count' => count($localAttachments),
                'storage_files_count' => count($storageFiles),
            ]);

            return response()->json([
                                        'message' => 'Письмо успешно отправлено.',
                                    ]);
        } catch (Throwable $exception) {
            Log::error('Unit mail send failed', [
                'unit_id' => $unit->id,
                'unit_name' => $unit->name,
                'to' => $to,
                'subject' => $subject,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                                        'message' => 'Не удалось отправить письмо.',
                                        'error' => $exception->getMessage(),
                                    ], 500);
        }
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
