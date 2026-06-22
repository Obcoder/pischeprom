<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendMailingCampaignJob;
use App\Models\Email;
use App\Models\MailingCampaign;
use App\Models\MailingContact;
use App\Models\MailingContactSet;
use App\Models\MailingEvent;
use App\Models\MailingOfferItem;
use App\Models\MailingSuppression;
use App\Models\MailingTemplate;
use App\Models\MailingWebhookCall;
use App\Services\CommercialOffers\MailingAuditLogger;
use App\Services\CommercialOffers\MailingCampaignService;
use App\Services\CommercialOffers\ProductOfferBuilder;
use App\Services\CommercialOffers\RecipientSetService;
use App\Services\CommercialOffers\UnisenderGoClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CommercialOffersController extends Controller
{
    public function __construct(
        private readonly MailingCampaignService $campaigns,
        private readonly RecipientSetService $recipientSets,
        private readonly ProductOfferBuilder $products,
        private readonly UnisenderGoClient $client,
        private readonly MailingAuditLogger $audit,
    ) {}

    public function index(): Response
    {
        $this->authorizeSales('sales_mailings.view');

        return Inertia::render('Admin/CommercialOffers', [
            'dashboard' => $this->dashboardPayload(),
            'settings' => $this->settingsPayload(),
            'permissions' => [
                'view' => Gate::allows('sales_mailings.view'),
                'edit' => Gate::allows('sales_mailings.edit'),
                'send_test' => Gate::allows('sales_mailings.send_test'),
                'send_mass' => Gate::allows('sales_mailings.send_mass'),
                'compliance_override' => Gate::allows('sales_mailings.compliance_override'),
                'manage_templates' => Gate::allows('sales_mailings.manage_templates'),
                'manage_suppression' => Gate::allows('sales_mailings.manage_suppression'),
            ],
        ]);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $this->authorizeSales('sales_mailings.view');

        $items = MailingCampaign::query()
            ->withCount('recipients')
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->when($request->string('q')->toString(), fn ($query, $q) => $query->where('name', 'like', "%{$q}%"))
            ->latest('id')
            ->paginate($request->integer('per_page', 50));

        return response()->json($items);
    }

    public function createCampaign(Request $request): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string'],
            'subject' => ['nullable', 'string', 'max:998'],
            'template_id' => ['nullable', 'integer'],
            'contact_set_id' => ['nullable', 'integer'],
            'html_markup' => ['nullable', 'string'],
            'plaintext' => ['nullable', 'string'],
            'from_email' => ['nullable', 'email'],
            'from_name' => ['nullable', 'string'],
            'reply_to' => ['nullable', 'email'],
        ]);

        return response()->json($this->campaigns->createDraft($data, $request->user()?->id), 201);
    }

    public function showCampaign(int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.view');

        return response()->json(MailingCampaign::query()->with(['template', 'contactSet', 'offerItems', 'recipients.contact'])->findOrFail($id));
    }

    public function updateCampaign(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');

        return response()->json($this->campaigns->updateDraft($id, $request->all(), $request->user()?->id));
    }

    public function previewCampaign(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.view');

        return response()->json($this->campaigns->preview($id, $request->integer('contact_id') ?: null));
    }

    public function sendTestCampaign(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.send_test');
        $email = $request->input('email') ?: config('services.mailings.test_recipient');

        try {
            return response()->json($this->campaigns->sendTest($id, (string) $email, $request->user()?->id)->toArray());
        } catch (Throwable $exception) {
            Log::warning('Commercial offer test send failed', [
                'campaign_id' => $id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Test email failed: '.$exception->getMessage(),
            ], 422);
        }
    }

    public function approveCampaign(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.send_mass');

        return response()->json($this->campaigns->approve($id, $request->user()?->id, $request->string('note')->toString() ?: null));
    }

    public function scheduleCampaign(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.send_mass');
        $request->validate(['scheduled_at' => ['required', 'date']]);

        return response()->json($this->campaigns->schedule($id, $request->date('scheduled_at'), $request->user()?->id));
    }

    public function startCampaign(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.send_mass');

        if (config('queue.default') !== 'sync') {
            SendMailingCampaignJob::dispatch($id, $request->user()?->id);
            MailingCampaign::query()->whereKey($id)->update(['status' => 'sending', 'started_at' => now()]);

            return response()->json(['status' => 'queued']);
        }

        return response()->json($this->campaigns->startSending($id, $request->user()?->id));
    }

    public function pauseCampaign(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');

        return response()->json($this->campaigns->pause($id, $request->user()?->id));
    }

    public function resumeCampaign(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');

        return response()->json($this->campaigns->resume($id, $request->user()?->id));
    }

    public function cancelCampaign(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');

        return response()->json($this->campaigns->cancel($id, $request->user()?->id));
    }

    public function duplicateCampaign(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');

        return response()->json($this->campaigns->cloneCampaign($id, $request->user()?->id), 201);
    }

    public function contacts(Request $request): JsonResponse
    {
        $this->authorizeSales('sales_mailings.view');

        $items = MailingContact::query()
            ->when($request->string('q')->toString(), function ($query, $q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('email', 'like', "%{$q}%")
                        ->orWhere('company_name', 'like', "%{$q}%")
                        ->orWhere('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%");
                });
            })
            ->when($request->string('consent_status')->toString(), fn ($query, $status) => $query->where('consent_status', $status))
            ->latest('id')
            ->paginate($request->integer('per_page', 100));

        return response()->json($items);
    }

    public function sourceEmails(Request $request): JsonResponse
    {
        $this->authorizeSales('sales_mailings.view');

        $query = Email::query()
            ->select($this->emailSourceColumns())
            ->whereNotNull('address')
            ->when($request->string('q')->toString(), function ($query, string $q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('address', 'like', "%{$q}%");

                    foreach (['name', 'domain', 'comment', 'source'] as $column) {
                        if (Schema::hasColumn('emails', $column)) {
                            $inner->orWhere($column, 'like', "%{$q}%");
                        }
                    }
                });
            })
            ->when(Schema::hasColumn('emails', 'is_active') && ! $request->boolean('include_inactive'), function ($query): void {
                $query->where('is_active', true);
            })
            ->orderByDesc(Schema::hasColumn('emails', 'last_seen_at') ? 'last_seen_at' : 'updated_at')
            ->orderBy('address');

        $items = $query->paginate($request->integer('per_page', 100));
        $normalized = $items->getCollection()
            ->map(fn (Email $email) => MailingContact::normalizeEmail($email->address))
            ->filter()
            ->unique()
            ->values();
        $existingContacts = MailingContact::query()
            ->whereIn('normalized_email', $normalized)
            ->get(['id', 'normalized_email', 'consent_status', 'do_not_email', 'unsubscribed_at', 'hard_bounced_at', 'complained_at'])
            ->keyBy('normalized_email');

        $items->getCollection()->transform(function (Email $email) use ($existingContacts): array {
            $contact = $existingContacts->get(MailingContact::normalizeEmail($email->address));

            return [
                'id' => $email->id,
                'address' => $email->address,
                'name' => $email->getAttribute('name'),
                'domain' => $email->getAttribute('domain'),
                'source' => $email->getAttribute('source'),
                'comment' => $email->getAttribute('comment'),
                'is_active' => $email->getAttribute('is_active'),
                'verified_at' => $email->getAttribute('verified_at'),
                'last_seen_at' => $email->getAttribute('last_seen_at'),
                'imported' => $contact !== null,
                'mailing_contact_id' => $contact?->id,
                'mailing_consent_status' => $contact?->consent_status,
                'mailing_do_not_email' => $contact?->do_not_email,
                'mailing_blocked' => $contact
                    ? ($contact->do_not_email || $contact->unsubscribed_at || $contact->hard_bounced_at || $contact->complained_at)
                    : false,
            ];
        });

        return response()->json($items);
    }

    public function createContact(Request $request): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');
        $data = $request->validate([
            'email' => ['required', 'email'],
            'first_name' => ['nullable', 'string'],
            'last_name' => ['nullable', 'string'],
            'company_name' => ['nullable', 'string'],
            'position' => ['nullable', 'string'],
            'source_type' => ['nullable', 'string'],
            'source_url' => ['nullable', 'string'],
            'contact_source' => ['nullable', 'string'],
            'consent_status' => ['nullable', 'string'],
            'consent_source' => ['nullable', 'string'],
            'legal_basis_note' => ['nullable', 'string'],
        ]);
        $data['normalized_email'] = MailingContact::normalizeEmail($data['email']);
        $data['consent_status'] ??= 'unknown';

        $contact = MailingContact::query()->updateOrCreate(['normalized_email' => $data['normalized_email']], $data);
        $this->audit->log('contact.saved', MailingContact::class, $contact->id, null, $contact->toArray(), $request);

        return response()->json($contact, 201);
    }

    public function importExistingEmails(Request $request): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');
        $data = $request->validate([
            'email_ids' => ['required', 'array', 'min:1'],
            'email_ids.*' => ['integer'],
            'set_id' => ['nullable', 'integer'],
            'consent_status' => ['nullable', 'string'],
        ]);

        $emails = Email::query()
            ->select($this->emailSourceColumns())
            ->whereIn('id', collect($data['email_ids'])->map(fn ($id) => (int) $id)->filter()->unique())
            ->get();
        $stats = ['requested' => count($data['email_ids']), 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'added_to_set' => 0];
        $contactIds = [];
        $consentStatus = $data['consent_status'] ?? 'unknown';

        foreach ($emails as $email) {
            $normalized = MailingContact::normalizeEmail($email->address);

            if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                $stats['skipped']++;

                continue;
            }

            $contact = MailingContact::query()->firstOrNew(['normalized_email' => $normalized]);
            $isNew = ! $contact->exists;
            $before = $contact->exists ? $contact->toArray() : null;
            $contactData = $this->mailingContactDataFromEmail($email, $consentStatus, $isNew);
            $contactData['custom_fields'] = array_replace($contact->custom_fields ?? [], $contactData['custom_fields'] ?? []);
            $contactData['tags'] = array_values(array_unique(array_merge($contact->tags ?? [], $contactData['tags'] ?? [])));
            $contact->fill($contactData);
            $contact->save();
            $contactIds[] = $contact->id;
            $isNew ? $stats['imported']++ : $stats['updated']++;

            $this->audit->log(
                $isNew ? 'contact.imported_from_email' : 'contact.synced_from_email',
                MailingContact::class,
                $contact->id,
                $before,
                $contact->fresh()->toArray(),
                $request
            );
        }

        if (! empty($data['set_id']) && $contactIds) {
            $stats['added_to_set'] = $this->recipientSets->addContacts((int) $data['set_id'], $contactIds, $request->user()?->id);
        }

        return response()->json($stats);
    }

    public function updateContact(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');
        $contact = MailingContact::query()->findOrFail($id);
        $before = $contact->toArray();
        $contact->fill(Arr::only($request->all(), [
            'email', 'first_name', 'last_name', 'middle_name', 'company_name', 'position', 'phone', 'website',
            'source_type', 'source_url', 'contact_source', 'consent_status', 'consent_source', 'consent_date',
            'consent_proof_url', 'consent_proof_note', 'legal_basis_note', 'responsible_manager_id', 'do_not_email',
            'tags', 'custom_fields',
        ]))->save();
        $this->audit->log('contact.updated', MailingContact::class, $contact->id, $before, $contact->fresh()->toArray(), $request);

        return response()->json($contact->fresh());
    }

    public function importContacts(Request $request): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');
        $request->validate(['file' => ['required', 'file'], 'set_id' => ['nullable', 'integer']]);
        $import = $this->recipientSets->importCsv($request->file('file'), $request->only(['set_id', 'source_type', 'contact_source']) + ['created_by' => $request->user()?->id]);
        $this->audit->log('contacts.imported', get_class($import), $import->id, null, $import->toArray(), $request);

        return response()->json($import, 201);
    }

    public function bulkUpdateContacts(Request $request): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');
        $data = $request->validate(['ids' => ['required', 'array'], 'fields' => ['required', 'array']]);
        $fields = Arr::only($data['fields'], ['consent_status', 'contact_source', 'source_type', 'do_not_email', 'responsible_manager_id', 'tags']);
        $count = MailingContact::query()->whereIn('id', $data['ids'])->update($fields);
        $this->audit->log('contacts.bulk_updated', MailingContact::class, null, null, ['count' => $count, 'fields' => array_keys($fields)], $request);

        return response()->json(['updated' => $count]);
    }

    public function sets(Request $request): JsonResponse
    {
        $this->authorizeSales('sales_mailings.view');

        return response()->json(MailingContactSet::query()->latest('id')->paginate($request->integer('per_page', 100)));
    }

    public function createSet(Request $request): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');
        $set = $this->recipientSets->createSet($request->validate(['name' => ['required', 'string'], 'description' => ['nullable', 'string'], 'type' => ['nullable', 'string']]) + ['created_by' => $request->user()?->id]);

        return response()->json($set, 201);
    }

    public function updateSet(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');

        return response()->json($this->recipientSets->updateSet($id, $request->all() + ['updated_by' => $request->user()?->id]));
    }

    public function addSetMembers(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');
        $contactIds = collect($request->input('contact_ids', []))->map(fn ($value) => (int) $value)->filter()->all();
        foreach ((array) $request->input('emails', []) as $email) {
            $normalized = MailingContact::normalizeEmail($email);
            if (filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                $contact = MailingContact::query()->firstOrCreate(['normalized_email' => $normalized], ['email' => $normalized, 'consent_status' => 'unknown', 'contact_source' => 'manual']);
                $contactIds[] = $contact->id;
            }
        }

        return response()->json(['added' => $this->recipientSets->addContacts($id, $contactIds, $request->user()?->id)]);
    }

    public function removeSetMember(int $id, int $contactId): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');

        return response()->json(['removed' => $this->recipientSets->removeContacts($id, [$contactId])]);
    }

    public function duplicateSet(int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');

        return response()->json($this->recipientSets->duplicateSet($id), 201);
    }

    public function templates(Request $request): JsonResponse
    {
        $this->authorizeSales('sales_mailings.view');

        return response()->json(MailingTemplate::query()->withCount('versions')->latest('id')->paginate($request->integer('per_page', 100)));
    }

    public function createTemplate(Request $request): JsonResponse
    {
        $this->authorizeSales('sales_mailings.manage_templates');
        $data = $request->validate([
            'name' => ['required', 'string'],
            'subject' => ['required', 'string', 'max:998'],
            'html_markup' => ['required', 'string'],
            'plaintext' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);
        $template = MailingTemplate::query()->create($data + [
            'slug' => Str::slug($data['name']).'-'.Str::random(5),
            'from_email' => config('services.unisender_go.from_email'),
            'from_name' => config('services.unisender_go.from_name'),
            'reply_to' => config('services.unisender_go.reply_to'),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);
        $this->campaigns->createTemplateVersion($template, 'initial', $request->user()?->id);
        $this->audit->log('template.created', MailingTemplate::class, $template->id, null, $template->toArray(), $request);

        return response()->json($template->fresh('versions'), 201);
    }

    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.manage_templates');
        $template = MailingTemplate::query()->findOrFail($id);
        $before = $template->toArray();
        $template->fill(Arr::only($request->all(), ['name', 'description', 'type', 'subject', 'preheader', 'from_email', 'from_name', 'reply_to', 'editor_schema', 'html_markup', 'plaintext', 'track_links', 'track_read', 'active']))->save();
        $this->campaigns->createTemplateVersion($template->fresh(), $request->string('change_note')->toString() ?: 'updated', $request->user()?->id);
        $this->audit->log('template.updated', MailingTemplate::class, $template->id, $before, $template->fresh()->toArray(), $request);

        return response()->json($template->fresh('versions'));
    }

    public function duplicateTemplate(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.manage_templates');
        $template = MailingTemplate::query()->findOrFail($id);
        $copy = $template->replicate(['slug', 'unisender_template_id']);
        $copy->name = $template->name.' copy';
        $copy->slug = Str::slug($copy->name).'-'.Str::random(5);
        $copy->created_by = $request->user()?->id;
        $copy->updated_by = $request->user()?->id;
        $copy->save();
        $this->campaigns->createTemplateVersion($copy, 'duplicated', $request->user()?->id);

        return response()->json($copy, 201);
    }

    public function syncTemplate(int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.manage_templates');
        $template = MailingTemplate::query()->findOrFail($id);
        $response = $this->client->setTemplate([
            'template' => [
                'id' => $template->unisender_template_id,
                'name' => $template->slug,
                'subject' => $template->subject,
                'body' => ['html' => $template->html_markup, 'plaintext' => $template->plaintext],
            ],
        ]);
        $remoteId = Arr::get($response, 'template_id') ?: Arr::get($response, 'result.template_id') ?: Arr::get($response, 'id');
        if ($remoteId) {
            $template->update(['unisender_template_id' => (string) $remoteId]);
        }

        return response()->json(['template' => $template->fresh(), 'response' => $response]);
    }

    public function sendTestTemplate(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.send_test');
        $template = MailingTemplate::query()->findOrFail($id);
        $campaign = $this->campaigns->createDraft(['name' => 'Test '.$template->name, 'type' => 'test', 'template_id' => $template->id], $request->user()?->id);

        try {
            return response()->json($this->campaigns->sendTest($campaign, (string) ($request->input('email') ?: config('services.mailings.test_recipient')), $request->user()?->id)->toArray());
        } catch (Throwable $exception) {
            Log::warning('Commercial offer template test send failed', [
                'template_id' => $id,
                'campaign_id' => $campaign->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Test email failed: '.$exception->getMessage(),
            ], 422);
        }
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $this->authorizeSales('sales_mailings.manage_templates');

        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $data['image'];
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: Str::random(8);
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $path = $file->storeAs(
            'commercial-offers/images/'.now()->format('Y/m'),
            $safeName.'-'.Str::random(8).'.'.$extension,
            'public',
        );

        $url = Storage::disk('public')->url($path);

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = url($url);
        }

        return response()->json([
            'url' => $url,
            'path' => $path,
            'alt' => $data['alt'] ?? null,
        ], 201);
    }

    public function productSearch(Request $request): JsonResponse
    {
        $this->authorizeSales('sales_mailings.view');
        $q = $request->string('q')->toString();

        return response()->json([
            'products' => $this->products->searchProducts($q, $request->all()),
            'categories' => $this->products->searchCategories($q, $request->all()),
        ]);
    }

    public function addOfferItem(Request $request, mixed $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');

        if (! is_numeric($id) || ! MailingCampaign::query()->whereKey((int) $id)->exists()) {
            return response()->json([
                'message' => 'Select a valid campaign before adding products to КП.',
            ], 422);
        }

        if ($request->input('item_type') === 'category') {
            $request->validate(['category_id' => ['required', 'integer', 'exists:categories,id']]);

            return response()->json($this->products->addCategoryToCampaign((int) $id, (int) $request->input('category_id'), $request->all()), 201);
        }

        $request->validate(['product_id' => ['required', 'integer', 'exists:goods,id']]);

        return response()->json($this->products->addProductToCampaign((int) $id, (int) $request->input('product_id'), $request->all()), 201);
    }

    public function updateOfferItem(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');
        $item = MailingOfferItem::query()->findOrFail($id);
        $item->fill(Arr::only($request->all(), ['title', 'sku', 'thumbnail_url', 'canonical_url', 'offer_price', 'currency', 'description', 'sort_order']))->save();

        return response()->json($item->fresh());
    }

    public function deleteOfferItem(int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');
        MailingOfferItem::query()->findOrFail($id)->delete();

        return response()->json(['deleted' => true]);
    }

    public function reorderOfferItems(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');

        return response()->json(['ok' => true, 'items' => $this->products->reorderItems($id, (array) $request->input('item_ids', []))]);
    }

    public function events(Request $request): JsonResponse
    {
        $this->authorizeSales('sales_mailings.view');
        $items = MailingEvent::query()
            ->when($request->integer('campaign_id'), fn ($query, $id) => $query->where('campaign_id', $id))
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->when($request->string('q')->toString(), fn ($query, $q) => $query->where('email', 'like', "%{$q}%"))
            ->latest('created_at')
            ->paginate($request->integer('per_page', 100));

        return response()->json($items);
    }

    public function suppression(Request $request): JsonResponse
    {
        $this->authorizeSales('sales_mailings.view');

        return response()->json(MailingSuppression::query()->latest('id')->paginate($request->integer('per_page', 100)));
    }

    public function addSuppression(Request $request): JsonResponse
    {
        $this->authorizeSales('sales_mailings.manage_suppression');
        $data = $request->validate(['email' => ['required', 'email'], 'cause' => ['required', 'string'], 'note' => ['nullable', 'string']]);
        $email = MailingContact::normalizeEmail($data['email']);
        $suppression = MailingSuppression::query()->updateOrCreate(
            ['normalized_email' => $email],
            ['email' => $email, 'cause' => $data['cause'], 'source' => 'manual', 'note' => $data['note'] ?? null, 'created_by' => $request->user()?->id]
        );
        $this->client->setSuppression($email, $data['cause']);
        $this->audit->log('suppression.added', MailingSuppression::class, $suppression->id, null, $suppression->toArray(), $request);

        return response()->json($suppression, 201);
    }

    public function deleteSuppression(Request $request, int $id): JsonResponse
    {
        $this->authorizeSales('sales_mailings.manage_suppression');
        $suppression = MailingSuppression::query()->findOrFail($id);
        $before = $suppression->toArray();
        $suppression->delete();
        $this->audit->log('suppression.deleted', MailingSuppression::class, $id, $before, null, $request);

        return response()->json(['deleted' => true]);
    }

    public function testApi(): JsonResponse
    {
        $this->authorizeSales('sales_mailings.view');

        if (! (bool) config('services.unisender_go.enabled', false)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unisender Go is disabled. Set UNISENDER_GO_ENABLED=true.',
            ], 422);
        }

        if (blank(config('services.unisender_go.api_key'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'UNISENDER_GO_API_KEY is not configured.',
            ], 422);
        }

        try {
            return response()->json([
                'status' => 'ok',
                'response' => $this->client->listSuppression(['limit' => 1]),
            ]);
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            $apiBase = (string) config('services.unisender_go.api_base');
            $lowerMessage = Str::lower($message);
            $isAuthError = Str::contains($lowerMessage, [
                'user not found',
                'unauthorized',
                'forbidden',
                'invalid api key',
                'api key',
            ]) || preg_match('/user\s+.*not\s+found/i', $message) === 1;

            Log::warning('Unisender Go API test failed', [
                'provider' => 'unisender_go',
                'exception' => $exception::class,
                'message' => $message,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $isAuthError
                    ? 'Unisender Go rejected API key/account: '.$message.'. Check that UNISENDER_GO_API_KEY belongs to Unisender Go Transactional API, the correct project/account is active, the key has no extra spaces, and UNISENDER_GO_API_BASE uses your Go host, for example https://go1.unisender.ru/en/transactional/api/v1. Current API base: '.$apiBase.'. After env changes run php artisan config:clear.'
                    : 'Unisender Go API test failed: '.$message,
            ], $isAuthError ? 422 : 502);
        }
    }

    public function setWebhook(): JsonResponse
    {
        $this->authorizeSales('sales_mailings.edit');

        return response()->json($this->client->setWebhook($this->client->defaultWebhookConfig()));
    }

    private function dashboardPayload(): array
    {
        $campaigns = MailingCampaign::query();
        $totalRecipients = (int) MailingCampaign::query()->sum('total_recipients');
        $delivered = (int) MailingCampaign::query()->sum('delivered_count');
        $opened = (int) MailingCampaign::query()->sum('unique_opened_count');
        $clicked = (int) MailingCampaign::query()->sum('unique_clicked_count');
        $unsubscribed = (int) MailingCampaign::query()->sum('unsubscribed_count');
        $hardBounced = (int) MailingCampaign::query()->sum('hard_bounced_count');
        $spam = (int) MailingCampaign::query()->sum('spam_count');
        $base = max(1, $totalRecipients);

        return [
            'drafts' => (clone $campaigns)->where('status', 'draft')->count(),
            'scheduled' => (clone $campaigns)->where('status', 'scheduled')->count(),
            'sending' => (clone $campaigns)->where('status', 'sending')->count(),
            'completed' => (clone $campaigns)->where('status', 'completed')->count(),
            'paused_by_system' => (clone $campaigns)->where('status', 'paused_by_system')->count(),
            'total_recipients' => $totalRecipients,
            'delivered_rate' => round($delivered / $base, 4),
            'open_rate' => round($opened / max(1, $delivered), 4),
            'click_rate' => round($clicked / max(1, $delivered), 4),
            'unsubscribe_rate' => round($unsubscribed / $base, 4),
            'hard_bounce_rate' => round($hardBounced / $base, 4),
            'spam_rate' => round($spam / $base, 4),
            'last_webhook_time' => optional(MailingWebhookCall::query()->latest('created_at')->first())->created_at,
            'unisender_api_status' => config('services.unisender_go.enabled') && config('services.unisender_go.api_key') ? 'configured' : 'not_configured',
        ];
    }

    private function settingsPayload(): array
    {
        return [
            'provider' => config('services.email_provider'),
            'enabled' => (bool) config('services.unisender_go.enabled'),
            'api_base' => config('services.unisender_go.api_base'),
            'from_email' => config('services.unisender_go.from_email'),
            'from_name' => config('services.unisender_go.from_name'),
            'reply_to' => config('services.unisender_go.reply_to'),
            'track_read' => (bool) config('services.unisender_go.track_read'),
            'track_links' => (bool) config('services.unisender_go.track_links'),
            'batch_size' => (int) config('services.mailings.batch_size'),
            'daily_limit' => (int) config('services.mailings.daily_limit'),
            'hourly_limit' => (int) config('services.mailings.hourly_limit'),
            'webhook_url' => config('services.unisender_go.webhook_url'),
            'test_recipient' => config('services.mailings.test_recipient'),
            'api_key_configured' => filled(config('services.unisender_go.api_key')),
        ];
    }

    private function authorizeSales(string $ability): void
    {
        if (! auth()->check()) {
            return;
        }

        abort_unless(Gate::allows($ability), 403);
    }

    private function emailSourceColumns(): array
    {
        return collect(['id', 'address', 'created_at', 'updated_at', 'name', 'domain', 'comment', 'source', 'is_active', 'verified_at', 'last_seen_at'])
            ->filter(fn (string $column) => $column === 'id' || Schema::hasColumn('emails', $column))
            ->values()
            ->all();
    }

    private function mailingContactDataFromEmail(Email $email, string $consentStatus, bool $isNew): array
    {
        $normalized = MailingContact::normalizeEmail($email->address);
        $lastSeenAt = $email->getAttribute('last_seen_at');

        return array_filter([
            'email' => $normalized,
            'first_name' => $email->getAttribute('name') ?: null,
            'source_type' => 'pischeprom_db',
            'contact_source' => 'emails',
            'consent_status' => $isNew ? $consentStatus : null,
            'tags' => ['pischeprom-email'],
            'custom_fields' => [
                'source_email_id' => $email->id,
                'source_email_address' => $email->address,
                'source_email_name' => $email->getAttribute('name'),
                'source_email_domain' => $email->getAttribute('domain'),
                'source_email_source' => $email->getAttribute('source'),
                'source_email_is_active' => $email->getAttribute('is_active'),
                'source_email_last_seen_at' => $lastSeenAt instanceof \DateTimeInterface ? $lastSeenAt->format('Y-m-d H:i:s') : $lastSeenAt,
            ],
        ], fn ($value) => $value !== null);
    }
}
