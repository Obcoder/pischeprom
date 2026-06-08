<?php

namespace App\Services\Mail;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UnansweredOutgoingMailService
{
    private const OVERDUE_HOURS = 48;

    public function summarizeForUnits(iterable $unitIds): array
    {
        $unitIds = collect($unitIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($unitIds->isEmpty() || !$this->hasRequiredTables()) {
            return [];
        }

        $emailToUnitIds = $this->emailToUnitIds($unitIds);

        if ($emailToUnitIds->isEmpty()) {
            return [];
        }

        $emailIds = $emailToUnitIds->keys()->values();
        $emailAddresses = DB::table('emails')
            ->whereIn('id', $emailIds)
            ->pluck('address', 'id');

        $latestOutgoingByUnit = $this->latestOutgoingByUnit($emailToUnitIds, $emailIds);

        if ($latestOutgoingByUnit->isEmpty()) {
            return [];
        }

        $latestIncomingByEmail = $this->latestIncomingByEmail($latestOutgoingByUnit);
        $now = now();

        return $latestOutgoingByUnit
            ->mapWithKeys(function ($row, int $unitId) use ($emailAddresses, $latestIncomingByEmail, $now) {
                $sentAt = $this->date($row->message_date);
                $lastIncomingAt = $this->date($latestIncomingByEmail->get((int) $row->email_id));
                $answered = $sentAt && $lastIncomingAt && $lastIncomingAt->greaterThan($sentAt);
                $isOverdue = $sentAt && !$answered && $sentAt->lessThanOrEqualTo($now->copy()->subHours(self::OVERDUE_HOURS));

                return [
                    $unitId => [
                        'mail_message_id' => (int) $row->id,
                        'recipient_email_id' => (int) $row->email_id,
                        'recipient_email' => $emailAddresses->get((int) $row->email_id),
                        'subject' => $row->subject,
                        'preview' => $row->preview,
                        'sent_at' => $sentAt?->toIso8601String(),
                        'last_incoming_at' => $lastIncomingAt?->toIso8601String(),
                        'answered' => (bool) $answered,
                        'is_overdue' => (bool) $isOverdue,
                        'overdue_hours' => self::OVERDUE_HOURS,
                    ],
                ];
            })
            ->all();
    }

    private function hasRequiredTables(): bool
    {
        return Schema::hasTable('emails')
            && Schema::hasTable('email_unit')
            && Schema::hasTable('email_mail_message')
            && Schema::hasTable('mail_messages');
    }

    private function emailToUnitIds(Collection $unitIds): Collection
    {
        $directRows = DB::table('email_unit')
            ->whereIn('unit_id', $unitIds)
            ->get(['email_id', 'unit_id']);

        $entityRows = collect();

        if (Schema::hasTable('email_entity') && Schema::hasTable('entity_unit')) {
            $entityRows = DB::table('email_entity')
                ->join('entity_unit', 'entity_unit.entity_id', '=', 'email_entity.entity_id')
                ->whereIn('entity_unit.unit_id', $unitIds)
                ->get([
                    'email_entity.email_id',
                    'entity_unit.unit_id',
                ]);
        }

        return $directRows
            ->merge($entityRows)
            ->filter(fn ($row) => $row->email_id && $row->unit_id)
            ->groupBy(fn ($row) => (int) $row->email_id)
            ->map(fn (Collection $rows) => $rows
                ->pluck('unit_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values());
    }

    private function latestOutgoingByUnit(Collection $emailToUnitIds, Collection $emailIds): Collection
    {
        $rows = DB::table('mail_messages')
            ->join('email_mail_message', 'email_mail_message.mail_message_id', '=', 'mail_messages.id')
            ->whereIn('email_mail_message.email_id', $emailIds)
            ->whereIn('email_mail_message.role', ['to', 'cc'])
            ->where('mail_messages.direction', 'outgoing')
            ->whereNotNull('mail_messages.message_date')
            ->orderByDesc('mail_messages.message_date')
            ->orderByDesc('mail_messages.id')
            ->get([
                'mail_messages.id',
                'mail_messages.subject',
                'mail_messages.preview',
                'mail_messages.message_date',
                'email_mail_message.email_id',
            ]);

        $latestByUnit = collect();

        foreach ($rows as $row) {
            foreach ($emailToUnitIds->get((int) $row->email_id, collect()) as $unitId) {
                if (!$latestByUnit->has($unitId)) {
                    $latestByUnit->put($unitId, $row);
                }
            }
        }

        return $latestByUnit;
    }

    private function latestIncomingByEmail(Collection $latestOutgoingByUnit): Collection
    {
        $emailIds = $latestOutgoingByUnit
            ->pluck('email_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $oldestSentAt = $latestOutgoingByUnit
            ->pluck('message_date')
            ->filter()
            ->min();

        if ($emailIds->isEmpty() || !$oldestSentAt) {
            return collect();
        }

        return DB::table('mail_messages')
            ->join('email_mail_message', 'email_mail_message.mail_message_id', '=', 'mail_messages.id')
            ->whereIn('email_mail_message.email_id', $emailIds)
            ->where('email_mail_message.role', 'from')
            ->where('mail_messages.direction', 'incoming')
            ->where('mail_messages.message_date', '>', $oldestSentAt)
            ->groupBy('email_mail_message.email_id')
            ->selectRaw('email_mail_message.email_id, MAX(mail_messages.message_date) as last_incoming_at')
            ->pluck('last_incoming_at', 'email_mail_message.email_id');
    }

    private function date(mixed $value): ?CarbonInterface
    {
        if (!$value) {
            return null;
        }

        return $value instanceof CarbonInterface
            ? $value
            : Carbon::parse($value);
    }
}
