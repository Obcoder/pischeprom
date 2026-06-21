<?php

namespace App\Services\CommercialOffers;

use App\Models\MailingContact;
use App\Models\MailingContactImport;
use App\Models\MailingContactSet;
use App\Models\MailingSuppression;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RecipientSetService
{
    public function createSet(array $data): MailingContactSet
    {
        return MailingContactSet::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'manual',
            'filter_definition' => $data['filter_definition'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'updated_by' => $data['updated_by'] ?? null,
            'active' => $data['active'] ?? true,
        ]);
    }

    public function updateSet(MailingContactSet|int $set, array $data): MailingContactSet
    {
        $set = $set instanceof MailingContactSet ? $set : MailingContactSet::query()->findOrFail($set);
        $set->fill(Arr::only($data, ['name', 'description', 'type', 'filter_definition', 'updated_by', 'active']))->save();

        return $set->fresh();
    }

    public function duplicateSet(MailingContactSet|int $set): MailingContactSet
    {
        $set = $set instanceof MailingContactSet ? $set : MailingContactSet::query()->findOrFail($set);
        $copy = $this->createSet($set->only(['description', 'type', 'filter_definition']) + [
            'name' => $set->name.' copy',
            'active' => true,
        ]);

        $this->addContacts($copy, $set->contacts()->pluck('mailing_contacts.id')->all());

        return $copy->fresh();
    }

    public function addContacts(MailingContactSet|int $set, array $contacts, ?int $userId = null): int
    {
        $set = $set instanceof MailingContactSet ? $set : MailingContactSet::query()->findOrFail($set);
        $ids = collect($contacts)->map(fn ($item) => $item instanceof MailingContact ? $item->id : (int) $item)->filter()->unique();

        foreach ($ids as $id) {
            DB::table('mailing_contact_set_members')->updateOrInsert(
                ['set_id' => $set->id, 'contact_id' => $id],
                ['added_by' => $userId, 'added_at' => now(), 'status' => 'active']
            );
        }

        $this->refreshSetCount($set);

        return $ids->count();
    }

    public function removeContacts(MailingContactSet|int $set, array $contacts): int
    {
        $set = $set instanceof MailingContactSet ? $set : MailingContactSet::query()->findOrFail($set);
        $ids = collect($contacts)->map(fn ($item) => $item instanceof MailingContact ? $item->id : (int) $item)->filter()->unique();
        $count = DB::table('mailing_contact_set_members')
            ->where('set_id', $set->id)
            ->whereIn('contact_id', $ids)
            ->update(['status' => 'removed']);

        $this->refreshSetCount($set);

        return $count;
    }

    public function importCsv(UploadedFile|string $file, array $options = []): MailingContactImport
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        if (! is_string($path) || ! is_readable($path)) {
            throw new RuntimeException('CSV file is not readable.');
        }

        $import = MailingContactImport::query()->create([
            'filename' => $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($path),
            'source_type' => $options['source_type'] ?? 'csv',
            'status' => 'processing',
            'created_by' => $options['created_by'] ?? null,
        ]);

        $handle = fopen($path, 'r');
        $header = null;
        $stats = ['total_rows' => 0, 'imported_count' => 0, 'skipped_count' => 0, 'duplicate_count' => 0, 'invalid_count' => 0];
        $errors = [];
        $seen = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map(fn ($value) => Str::snake(trim((string) $value)), $row);

                continue;
            }

            $stats['total_rows']++;
            $data = array_combine($header, array_pad($row, count($header), null)) ?: [];
            $email = MailingContact::normalizeEmail($data['email'] ?? $data['e_mail'] ?? $data['mail'] ?? '');

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stats['invalid_count']++;
                $errors[] = ['row' => $stats['total_rows'], 'email' => $email, 'error' => 'invalid_email'];

                continue;
            }

            if (isset($seen[$email])) {
                $stats['duplicate_count']++;

                continue;
            }
            $seen[$email] = true;

            $exists = MailingContact::query()->where('normalized_email', $email)->exists();
            $contact = MailingContact::query()->updateOrCreate(
                ['normalized_email' => $email],
                [
                    'email' => $email,
                    'first_name' => $data['first_name'] ?? $data['name'] ?? null,
                    'last_name' => $data['last_name'] ?? null,
                    'company_name' => $data['company_name'] ?? $data['company'] ?? null,
                    'position' => $data['position'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'source_type' => $options['source_type'] ?? 'csv',
                    'contact_source' => $data['contact_source'] ?? $options['contact_source'] ?? null,
                    'source_url' => $data['source_url'] ?? null,
                    'consent_status' => $data['consent_status'] ?? 'unknown',
                    'consent_source' => $data['consent_source'] ?? null,
                    'legal_basis_note' => $data['legal_basis_note'] ?? null,
                ]
            );

            if (! empty($options['set_id'])) {
                $this->addContacts((int) $options['set_id'], [$contact->id], $options['created_by'] ?? null);
            }

            $exists ? $stats['duplicate_count']++ : $stats['imported_count']++;
        }

        if (is_resource($handle)) {
            fclose($handle);
        }

        $import->update($stats + ['status' => 'completed', 'errors_json' => $errors ?: null]);

        return $import->fresh();
    }

    public function exportCsv(MailingContactSet|int|null $set = null): string
    {
        $contacts = $set ? ($set instanceof MailingContactSet ? $set : MailingContactSet::query()->findOrFail($set))->contacts : MailingContact::query()->limit(5000)->get();
        $lines = ['email,first_name,last_name,company_name,consent_status,do_not_email'];

        foreach ($contacts as $contact) {
            $lines[] = implode(',', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', [
                $contact->email,
                $contact->first_name,
                $contact->last_name,
                $contact->company_name,
                $contact->consent_status,
                $contact->do_not_email ? '1' : '0',
            ]));
        }

        return implode("\n", $lines);
    }

    public function validateContacts(array $emails): array
    {
        return collect($emails)->map(function ($email) {
            $normalized = MailingContact::normalizeEmail($email);

            return [
                'email' => $normalized,
                'valid' => (bool) filter_var($normalized, FILTER_VALIDATE_EMAIL),
                'suppressed' => MailingSuppression::query()->where('normalized_email', $normalized)->exists(),
            ];
        })->all();
    }

    public function buildDynamicSetByFilters(array $filters): Collection
    {
        return MailingContact::query()
            ->when($filters['company'] ?? null, fn ($q, $value) => $q->where('company_name', 'like', "%{$value}%"))
            ->when($filters['tag'] ?? null, fn ($q, $value) => $q->where('tags', 'like', "%{$value}%"))
            ->get();
    }

    public function countEligibleRecipients(MailingContactSet|int $set, bool $mass = true): int
    {
        return $this->eligibleQuery($set, $mass)->count();
    }

    public function getEligibleRecipientsForCampaign(MailingContactSet|int $set, bool $mass = true, int $limit = 500): Collection
    {
        return $this->eligibleQuery($set, $mass)->limit($limit)->get();
    }

    private function eligibleQuery(MailingContactSet|int $set, bool $mass)
    {
        $set = $set instanceof MailingContactSet ? $set : MailingContactSet::query()->findOrFail($set);
        $query = $set->contacts()->wherePivot('status', 'active');

        if ($mass && (bool) config('services.mailings.require_consent_for_mass', true)) {
            $query->where('consent_status', MailingContact::CONSENT_CONFIRMED);
        }

        return $query
            ->where('do_not_email', false)
            ->whereNull('unsubscribed_at')
            ->whereNull('complained_at')
            ->whereNull('hard_bounced_at')
            ->whereNotIn('normalized_email', MailingSuppression::query()->select('normalized_email'))
            ->orderBy('normalized_email');
    }

    private function refreshSetCount(MailingContactSet $set): void
    {
        $set->update([
            'contacts_count' => DB::table('mailing_contact_set_members')
                ->where('set_id', $set->id)
                ->where('status', 'active')
                ->count(),
        ]);
    }
}
