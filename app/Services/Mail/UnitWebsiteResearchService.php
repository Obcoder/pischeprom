<?php

namespace App\Services\Mail;

use App\Models\MailMessage;
use App\Models\MailMessageResearch;
use App\Models\UnitWebsiteResearch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnitWebsiteResearchService
{
    public function __construct(private readonly MailMessageCrmService $crm) {}

    public function linkedUnits(MailMessage $message): array
    {
        return array_map(fn (array $unit): array => $this->unitPayload($unit['id'], $unit['name']), $this->crm->context($message)['linked']['units']);
    }

    public function requireLinkedUnit(MailMessage $message, int $unitId): array
    {
        foreach ($this->linkedUnits($message) as $unit) {
            if ((int) $unit['id'] === $unitId) {
                return $unit;
            }
        }

        throw ValidationException::withMessages(['unit_id' => 'Сначала свяжите email из письма с этим Unit или его Entity.']);
    }

    public function save(MailMessage $message, MailMessageResearch $research, int $unitId, User $actor): UnitWebsiteResearch
    {
        abort_unless((int) $research->mail_message_id === (int) $message->id, 404);
        if ($research->kind !== 'website') {
            throw ValidationException::withMessages(['research' => 'В Unit можно сохранить исследование сайта.']);
        }
        $this->requireLinkedUnit($message, $unitId);

        return DB::transaction(function () use ($message, $research, $unitId, $actor): UnitWebsiteResearch {
            // Serialize repeated saves; keep a full copy independent of the source mail's lifetime.
            $research = MailMessageResearch::query()->whereKey($research->id)->lockForUpdate()->firstOrFail();

            return UnitWebsiteResearch::query()->updateOrCreate([
                'unit_id' => $unitId,
                'source_research_id' => $research->id,
            ], [
                'source_mail_message_id' => $message->id,
                'saved_by_user_id' => $actor->id,
                'url' => $research->query,
                'result' => $research->result,
                'researched_at' => $research->updated_at,
                'saved_at' => now(),
            ]);
        }, 3);
    }

    public function researchPayload(MailMessageResearch $research): array
    {
        $research->loadMissing('unitCopies.unit');

        return [
            ...$research->attributesToArray(),
            'saved_units' => $research->unitCopies->filter(fn ($copy) => $copy->unit !== null)
                ->map(fn ($copy): array => [
                    ...$this->unitPayload($copy->unit->id, $copy->unit->name),
                    'unit_research_id' => $copy->id,
                ])->values()->all(),
        ];
    }

    public function unitPayload(int $id, string $name): array
    {
        return ['id' => $id, 'name' => $name, 'url' => route('web.unit.show', ['unit' => $id], false).'?section=overview#website-research'];
    }
}
