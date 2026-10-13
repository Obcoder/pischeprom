<?php

namespace App\Services\SupplierOrders;

use App\Http\Requests\Mail\SendSupplierOrderMailRequest;
use App\Models\AuthorizedMailDispatchAttempt;
use App\Models\MailMessage;
use App\Models\SupplierOrder;
use App\Models\User;
use App\Services\Auth\StaffAccess;
use App\Services\Mail\AuthorizedMailDispatchService;
use App\Services\Mail\MailDispatchException;
use App\Services\Mail\PreparedMailContent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SupplierOrderMailSender
{
    public const ROUTE_NAME = 'supplier-orders.send';

    private const UNKNOWN_WARNING = 'Результат отправки не подтверждён. Повторная отправка заблокирована, чтобы не создать дубликат. Проверьте почту и обновите статус отправки.';

    public function __construct(
        private readonly AuthorizedMailDispatchService $dispatch,
        private readonly SupplierOrderWriter $writer,
        private readonly SupplierOrderMailRenderer $renderer,
        private readonly StaffAccess $staff,
    ) {}

    /** @return array{order: SupplierOrder, warning: ?string, message: string} */
    public function send(User $actor, SupplierOrder $order, int $revision, array $attachments = []): array
    {
        $order = $order->fresh(['items', 'unit']);
        $this->authorize($actor, $order);
        if (in_array($order->status, ['sending', 'send_unknown', 'sent', 'confirmed', 'closed'], true)) {
            return $this->reconcile($actor, $order);
        }
        $this->assertDraft($order, $revision);

        // All recipients/content come from this saved revision. Render and validate
        // before claiming, without any transport or database side effects.
        $key = (string) Str::uuid();
        $payload = SendSupplierOrderMailRequest::validateStoredMail([
            'idempotency_key' => $key,
            'subject' => trim((string) $order->subject) ?: 'Заказ поставщику '.$order->number,
            'body' => $order->body ?? '', 'mailbox' => $order->mailbox,
            'to' => $order->to ?? [], 'cc' => $order->cc ?? [],
            'attachments' => $attachments['attachments'] ?? [],
            'storage_files' => $attachments['storage_files'] ?? [],
        ]);
        $rendered = $this->renderer->render($this->writer->mailData($order));
        $content = new PreparedMailContent($rendered['html'], $rendered['text']);

        [$order, $owned] = DB::transaction(function () use ($actor, $order, $revision, $key): array {
            $locked = SupplierOrder::lockForUpdate()->findOrFail($order->id);
            $this->authorize($actor, $locked);
            if (in_array($locked->status, ['sending', 'send_unknown', 'sent', 'confirmed', 'closed'], true)) {
                return [$locked, false];
            }
            $this->assertDraft($locked, $revision);
            $locked->forceFill([
                'status' => 'sending', 'revision' => $locked->revision + 1,
                'sending_key' => $key, 'sending_user_id' => $actor->id, 'sending_started_at' => now(),
                'mail_message_id' => null, 'sent_at' => null, 'send_error_code' => null, 'updated_by' => $actor->id,
            ])->save();

            return [$locked, true];
        });
        if (! $owned) {
            return $this->reconcile($actor, $order);
        }

        // No order lock or surrounding transaction may span SMTP. The committed
        // key also lets reconciliation recover after a process dies before linking.
        try {
            $result = $this->dispatch->dispatchMessage(
                $actor, $payload, self::ROUTE_NAME, $order->unit, preparedContent: $content,
            );
        } catch (Throwable $exception) {
            $safeCode = $exception instanceof MailDispatchException ? $exception->safeCode : 'preparation_failed';
            try {
                $attempt = $this->attemptFor($order);
                if ($attempt === null) {
                    // This invocation returned before the mail service claimed or
                    // started SMTP. Unlike an interrupted invocation, retry is safe.
                    $this->resetUnstarted($order, $key, $safeCode);
                } else {
                    $this->synchronize($order->id, $key, true);
                }
            } catch (Throwable) {
                // The committed sending claim remains frozen if persistence fails.
            }
            if ($exception instanceof MailDispatchException) {
                throw $exception;
            }
            report($exception);
            throw new MailDispatchException('send_preparation_failed', 'Не удалось завершить отправку. Обновите статус заказа перед дальнейшими действиями.', 502);
        }

        try {
            $order = $this->synchronize($order->id, $key, true);
        } catch (Throwable) {
            // SMTP may have succeeded. A failed order update must never invite a resend.
            return $this->result($order->fresh(), self::UNKNOWN_WARNING);
        }

        return $this->result($order, $result['warning'] ?? null);
    }

    /** Read persisted delivery evidence only: this method never calls SMTP. */
    public function reconcile(User $actor, SupplierOrder $order): array
    {
        $order = $order->fresh(['unit']);
        $this->authorize($actor, $order);

        return $this->result($this->synchronize($order->id, $order->sending_key));
    }

    private function authorize(User $actor, SupplierOrder $order): void
    {
        if (! $this->staff->allows($actor) || ! $order->unit) {
            throw new AuthorizationException('Supplier mail dispatch is not authorized.');
        }
        $this->dispatch->authorize($actor, $order->unit);
    }

    private function assertDraft(SupplierOrder $order, int $revision): void
    {
        if ($order->status !== 'draft') {
            throw new MailDispatchException('order_not_draft', 'Отправить можно только сохранённый черновик.', 409);
        }
        if ($order->revision !== $revision) {
            throw new MailDispatchException('revision_conflict', 'Заказ изменён другим сотрудником. Обновите данные перед отправкой.', 409);
        }
    }

    private function attemptFor(SupplierOrder $order): ?AuthorizedMailDispatchAttempt
    {
        if (! $order->sending_key || ! $order->sending_user_id) {
            return null;
        }

        return AuthorizedMailDispatchAttempt::where('idempotency_key_hash', hash('sha256',
            $order->sending_user_id.'|'.self::ROUTE_NAME.'|'.Str::lower($order->sending_key),
        ))->where('unit_id', $order->unit_id)->first();
    }

    private function synchronize(int $orderId, ?string $key, bool $dispatchFinished = false): SupplierOrder
    {
        return DB::transaction(function () use ($orderId, $key, $dispatchFinished): SupplierOrder {
            $order = SupplierOrder::lockForUpdate()->findOrFail($orderId);
            if (! in_array($order->status, ['sending', 'send_unknown'], true) || $order->sending_key !== $key) {
                return $order;
            }
            $attempt = $this->attemptFor($order);
            $message = $attempt?->mail_message_id ? MailMessage::withTrashed()->find($attempt->mail_message_id) : null;
            if ($message) {
                $order->mail_message_id = $message->id;
            }
            // Read both rows afresh: dispatch can return an in-memory "sent" model
            // even when the acceptance transaction was rolled back.
            if ($attempt?->status === 'dispatched' && $attempt->dispatched_at
                && $message?->delivery_status === 'sent' && $message->smtp_accepted_at) {
                $order->forceFill(['status' => 'sent', 'sent_at' => $message->smtp_accepted_at, 'send_error_code' => null]);
            } elseif ($attempt?->status === 'failed' && (! $message || $message->delivery_status === 'failed')) {
                $order->forceFill($this->draftAfterFailure($attempt->safe_error_code ?? 'preparation_failed'));
            } elseif ($attempt?->status === 'unknown' || $message?->delivery_status === 'unknown' || $dispatchFinished) {
                $order->forceFill(['status' => 'send_unknown', 'send_error_code' => $attempt?->safe_error_code ?? 'delivery_unconfirmed']);
            }
            if ($order->isDirty()) {
                $order->revision++;
                $order->save();
            }

            return $order;
        });
    }

    private function resetUnstarted(SupplierOrder $order, string $key, string $safeCode): void
    {
        DB::transaction(function () use ($order, $key, $safeCode): void {
            $locked = SupplierOrder::lockForUpdate()->findOrFail($order->id);
            if ($locked->status === 'sending' && $locked->sending_key === $key && $this->attemptFor($locked) === null) {
                $locked->forceFill([...$this->draftAfterFailure($safeCode), 'revision' => $locked->revision + 1])->save();
            }
        });
    }

    private function draftAfterFailure(string $code): array
    {
        return [
            'status' => 'draft', 'sending_key' => null, 'sending_user_id' => null,
            'sending_started_at' => null, 'sent_at' => null, 'send_error_code' => $code,
        ];
    }

    private function result(SupplierOrder $order, ?string $warning = null): array
    {
        if ($order->status === 'send_unknown') {
            $warning = self::UNKNOWN_WARNING;
        } elseif ($order->status === 'sending') {
            $warning ??= 'Отправка уже начата. Повторное письмо не отправлено. Обновите статус отправки позже.';
        }

        return ['order' => $order, 'warning' => $warning, 'message' => match ($order->status) {
            'sent', 'confirmed', 'closed' => 'Заказ отправлен поставщику.',
            'sending' => 'Отправка выполняется.', 'send_unknown' => 'Отправка требует проверки.',
            default => $order->send_error_code ? 'Письмо не отправлено. Черновик доступен для исправления и новой отправки.' : 'Заказ ещё не отправлен.',
        }];
    }
}
