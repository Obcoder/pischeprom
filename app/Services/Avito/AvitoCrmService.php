<?php

namespace App\Services\Avito;

use App\Models\AvitoChat;
use App\Models\AvitoContactCandidate;
use App\Models\AvitoMessage;
use App\Models\Building;
use App\Models\Entity;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Telephone;
use App\Models\User;
use App\Services\Orders\OrderWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AvitoCrmService
{
    public function __construct(
        private readonly AvitoContactDetector $detector,
        private readonly OrderWriter $orders,
    ) {}

    public function linkEntity(AvitoChat $chat, Entity $entity): AvitoChat
    {
        DB::transaction(function () use ($chat, $entity): void {
            $this->identityChats($chat)->update(['entity_id' => $entity->id]);
        });

        return $chat->fresh('entity');
    }

    public function unlinkEntity(AvitoChat $chat): AvitoChat
    {
        DB::transaction(function () use ($chat): void {
            $this->identityChats($chat)->update(['entity_id' => null]);
        });

        return $chat->fresh('entity');
    }

    public function createAndLinkEntity(AvitoChat $chat, array $data): Entity
    {
        return DB::transaction(function () use ($chat, $data): Entity {
            $entity = Entity::query()->create([
                'name' => trim($data['name']),
                'full_name' => $this->nullableTrimmedString($data['full_name'] ?? null),
                'entity_classification_id' => $data['entity_classification_id'] ?? null,
                'INN' => $this->nullableTrimmedString($data['INN'] ?? null),
                'KPP' => $this->nullableTrimmedString($data['KPP'] ?? null),
                'OGRN' => $this->nullableTrimmedString($data['OGRN'] ?? null),
                'legal_address' => $this->nullableTrimmedString($data['legal_address'] ?? null),
                'country_id' => $data['country_id'] ?? null,
                'bank_account_number' => $this->nullableTrimmedString($data['bank_account_number'] ?? null),
                'bank_name' => $this->nullableTrimmedString($data['bank_name'] ?? null),
                'bank_bic' => $this->nullableTrimmedString($data['bank_bic'] ?? null),
                'bank_corr_account' => $this->nullableTrimmedString($data['bank_corr_account'] ?? null),
            ]);

            $entity->cities()->sync($data['city_ids'] ?? []);
            $entity->units()->sync($data['unit_ids'] ?? []);

            $this->linkEntity($chat, $entity);

            return $entity->fresh([
                'classification',
                'country',
                'cities.region.country',
                'units',
                'telephones',
                'buildings.city.region',
            ]);
        });
    }

    public function saveTelephone(
        AvitoChat $chat,
        ?AvitoContactCandidate $candidate,
        ?string $number,
        ?User $actor = null,
    ): Telephone {
        $entity = $this->requireEntity($chat);
        $candidate = $candidate ? $this->candidateFor($chat, $candidate, AvitoContactCandidate::TYPE_PHONE) : null;
        $normalized = $this->detector->normalizePhone((string) ($number ?: $candidate?->normalized_value ?: $candidate?->raw_value));

        if (! $normalized) {
            throw ValidationException::withMessages([
                'number' => 'Не удалось привести номер к российскому формату +7XXXXXXXXXX.',
            ]);
        }

        return DB::transaction(function () use ($chat, $entity, $candidate, $normalized, $actor): Telephone {
            $telephone = Telephone::query()->firstOrCreate(['number' => $normalized]);
            $entity->telephones()->syncWithoutDetaching([$telephone->id]);

            $this->acceptMatchingCandidates(
                $chat,
                AvitoContactCandidate::TYPE_PHONE,
                $normalized,
                [
                    'telephone_id' => $telephone->id,
                    'resolved_by_user_id' => $actor?->id,
                ],
                $candidate,
            );

            return $telephone->fresh('entities:id,name');
        });
    }

    public function saveBuilding(
        AvitoChat $chat,
        array $data,
        ?AvitoContactCandidate $candidate = null,
        ?User $actor = null,
    ): Building {
        $entity = $this->requireEntity($chat);
        $candidate = $candidate ? $this->candidateFor($chat, $candidate, AvitoContactCandidate::TYPE_ADDRESS) : null;
        $address = trim((string) ($data['address'] ?: $candidate?->raw_value));

        if ($address === '') {
            throw ValidationException::withMessages(['address' => 'Укажите адрес здания.']);
        }

        return DB::transaction(function () use ($entity, $candidate, $data, $address, $actor): Building {
            $building = Building::query()->firstOrCreate(
                [
                    'city_id' => (int) $data['city_id'],
                    'address' => $address,
                ],
                [
                    'building_type_id' => $data['building_type_id'] ?? null,
                    'postcode' => filled($data['postcode'] ?? null) ? trim($data['postcode']) : null,
                ],
            );

            $updates = [];
            if (! $building->building_type_id && filled($data['building_type_id'] ?? null)) {
                $updates['building_type_id'] = $data['building_type_id'];
            }
            if (blank($building->postcode) && filled($data['postcode'] ?? null)) {
                $updates['postcode'] = trim($data['postcode']);
            }
            if ($updates !== []) {
                $building->update($updates);
            }

            $entity->buildings()->syncWithoutDetaching([$building->id]);

            if ($candidate) {
                $candidate->update([
                    'status' => AvitoContactCandidate::STATUS_ACCEPTED,
                    'building_id' => $building->id,
                    'resolved_by_user_id' => $actor?->id,
                    'resolved_at' => now(),
                ]);
            }

            return $building->fresh(['city.region.country', 'buildingType', 'entities:id,name']);
        });
    }

    public function createOrder(AvitoChat $chat, array $data, ?User $actor = null): Order
    {
        $entity = $this->requireEntity($chat)->loadMissing(['telephones:id,number', 'buildings:id']);
        $buildingIds = collect($data['building_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $contactTelephoneId = filled($data['contact_telephone_id'] ?? null)
            ? (int) $data['contact_telephone_id']
            : null;

        if ($contactTelephoneId && ! $entity->telephones->contains('id', $contactTelephoneId)) {
            throw ValidationException::withMessages([
                'contact_telephone_id' => 'Выбранный телефон не принадлежит клиенту этого чата.',
            ]);
        }

        if ($buildingIds->diff($entity->buildings->pluck('id'))->isNotEmpty()) {
            throw ValidationException::withMessages([
                'building_ids' => 'Один из адресов не принадлежит клиенту этого чата.',
            ]);
        }

        $sourceMessageId = filled($data['source_message_id'] ?? null)
            ? (int) $data['source_message_id']
            : null;
        if ($sourceMessageId && ! AvitoMessage::query()
            ->whereKey($sourceMessageId)
            ->where('avito_chat_id', $chat->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'source_message_id' => 'Исходное сообщение не относится к текущему чату.',
            ]);
        }

        return DB::transaction(function () use (
            $chat,
            $entity,
            $data,
            $actor,
            $buildingIds,
            $contactTelephoneId,
            $sourceMessageId,
        ): Order {
            $statusId = $data['order_status_id'] ?? OrderStatus::query()
                ->where('code', OrderStatus::OPEN)
                ->value('id');
            $order = $this->orders->save(null, [
                'entity_id' => $entity->id,
                'order_status_id' => $statusId,
                'created_by_user_id' => $actor?->id,
                'contact_telephone_id' => $contactTelephoneId,
                'preferred_delivery_time' => $data['preferred_delivery_time'] ?? null,
                'internal_comment' => $data['internal_comment'] ?? null,
                'currency_code' => strtoupper((string) ($data['currency_code'] ?? 'RUB')),
                'building_ids' => $buildingIds->all(),
                'items' => $data['items'],
            ]);

            $chat->orders()->syncWithoutDetaching([
                $order->id => ['source_message_id' => $sourceMessageId],
            ]);

            return $order->fresh($this->orders->relations());
        });
    }

    public function rejectCandidate(AvitoContactCandidate $candidate, ?User $actor = null): AvitoContactCandidate
    {
        $candidate->update([
            'status' => AvitoContactCandidate::STATUS_REJECTED,
            'resolved_by_user_id' => $actor?->id,
            'resolved_at' => now(),
        ]);

        return $candidate->fresh();
    }

    public function reopenCandidate(AvitoContactCandidate $candidate): AvitoContactCandidate
    {
        $candidate->update([
            'status' => AvitoContactCandidate::STATUS_PENDING,
            'telephone_id' => null,
            'building_id' => null,
            'resolved_by_user_id' => null,
            'resolved_at' => null,
        ]);

        return $candidate->fresh();
    }

    private function identityChats(AvitoChat $chat)
    {
        return AvitoChat::query()
            ->where('avito_messenger_account_id', $chat->avito_messenger_account_id)
            ->when(
                filled($chat->peer_user_id),
                fn ($query) => $query->where('peer_user_id', $chat->peer_user_id),
                fn ($query) => $query->whereKey($chat->id),
            );
    }

    private function requireEntity(AvitoChat $chat): Entity
    {
        $entity = $chat->entity()->first();

        if (! $entity) {
            throw ValidationException::withMessages([
                'entity_id' => 'Сначала создайте или привяжите клиента к чату Avito.',
            ]);
        }

        return $entity;
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function candidateFor(
        AvitoChat $chat,
        AvitoContactCandidate $candidate,
        string $type,
    ): AvitoContactCandidate {
        $belongs = $candidate->type === $type
            && $candidate->message()->where('avito_chat_id', $chat->id)->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'candidate_id' => 'Кандидат не относится к текущему чату или имеет другой тип.',
            ]);
        }

        return $candidate;
    }

    private function acceptMatchingCandidates(
        AvitoChat $chat,
        string $type,
        string $normalized,
        array $resolution,
        ?AvitoContactCandidate $candidate,
    ): void {
        $query = AvitoContactCandidate::query()
            ->where('type', $type)
            ->where('normalized_value', $normalized)
            ->whereHas('message', fn ($message) => $message->where('avito_chat_id', $chat->id));

        $query->update($resolution + [
            'status' => AvitoContactCandidate::STATUS_ACCEPTED,
            'resolved_at' => now(),
        ]);

        if ($candidate && $candidate->normalized_value !== $normalized) {
            $candidate->update($resolution + [
                'status' => AvitoContactCandidate::STATUS_ACCEPTED,
                'resolved_at' => now(),
            ]);
        }
    }
}
