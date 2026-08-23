# Existing Mail and Sending Infrastructure Audit

Date: 2026-08-17
Stage: 12
Baseline: `ad3bd60f6aaadad61510f524e76740c1edff77e6`

## Scope and boundary

This is the required read-only audit performed before Stage 12 schema or runtime implementation. It covers the existing Laravel mail, legacy `Sending`, mailbox/message UI, commercial-offer mailing stack, consent-like fields, unsubscribe/bounce handling, tracking, queues, and every caller of the four legacy routes identified during the Stage 12 security gate.

Stage 12 reuses these records only as explicit inputs to a stricter communication-permission and suppression decision. It does not reinterpret a public or validated corporate address as consent, does not dispatch an outreach draft, does not create a `Sending`, and does not enqueue an email job.

## Route and caller inventory

| Route at baseline | Action | Caller | Baseline request/response and behavior | Baseline authorization | Stage 12 decision |
|---|---|---|---|---|---|
| `GET /send-email` | Inline closure in `routes/web.php` | No repository caller | Sends `TestEmail` to a hard-coded recipient as a GET side effect | None | Remove completely; no replacement HTTP route |
| `POST /api/mail` | `MailController::sendMail` | `resources/js/Pages/Ameise/Grossbuch.vue` | Caller submits `message`, but the controller ignores it, renders a product template, creates `Sending`, and updates status | None | Remove the misleading alias and update Grossbuch to the protected generic composer, which sends the text the user reviewed |
| `POST /api/mail-messages/send` | `MailMessageActionController::send` | `resources/js/Components/Contacts/Emails/MailComposerDialog.vue` | Multipart subject/body/mailbox/to/cc/bcc, uploads, storage file keys, reply and optional Entity/Unit IDs; sends SMTP and records `MailMessage`/Email links | None | Retain the used contract with strict caps and safe response, and route through the shared authorized manual-dispatch boundary |
| `POST /api/units/{unit}/mail/send` | `UnitMailController::send` | `UnitMailComposerDialog.vue`, `useUnitMail.js`, Unit overview/sendings cards | Duplicates generic SMTP and `MailMessage` recording; Unit-scoped reply check | None | Retain contract with `UnitPolicy`, Unit access and repeated service authorization, strict caps, throttle, idempotency, and the shared boundary |

No inspected caller implements a public contact form. The three POST routes are internal CRM workflows; anonymous compatibility is deliberately not preserved. `/api/mail` cannot retain a truthful compatible contract because the only caller and controller disagree on the content. Its caller is therefore consolidated onto `/api/mail-messages/send`.

## Existing transport and mailbox behavior

- `MailboxRegistry` combines code/config-owned and database mailbox records, selects an active configured mailbox, and creates a Laravel SMTP mailer at runtime. The selected `From` is therefore server-owned rather than request-owned.
- Generic and Unit composers call Laravel `Mail::mailer(...)->html(...)` synchronously. They do not enqueue a job.
- The baseline allows caller-selected configured mailbox addresses, but no arbitrary `From`. It has no explicit allowlisted `Reply-To`; Stage 12 keeps Reply-To server-owned and does not accept arbitrary headers.
- The two composer controllers independently normalize recipients, attachments, threading headers and locally recorded messages. This is duplicated delivery code.
- Baseline exceptions can include provider messages in JSON and routine logs; Unit logs include recipient addresses, subjects and storage paths. The remediation must replace these with safe error codes and metadata-only audit fields.
- Existing tracking headers (`In-Reply-To`, `References`) are derived from an existing local `MailMessage`; no arbitrary header request is required.

## Attachments

- Browser uploads are accepted at up to 50 MiB each at baseline, without a total/count cap or narrow MIME allowlist.
- `storage_files[]` accepts caller-provided strings or URLs, strips URL/bucket prefixes, and reads the resulting key from the Yandex filesystem disk. There is no ownership/prefix proof and this is an arbitrary object-path read surface.
- Both composer UIs select storage keys through the existing file picker. Stage 12 preserves supported caller fields but restricts count, per-file/total size, MIME type, path traversal, URL input and allowed server-owned prefixes. It never accepts a host filesystem path.

## Message and relationship persistence

- `MailMessage` is the existing incoming/outgoing correspondence record. Outgoing messages store full subject/text/HTML and normalized recipient structures and link existing or newly created `Email` rows through `email_mail_message`.
- Generic compose can associate the recipient email with a supplied Entity or Unit. Unit compose derives Unit email relationships and limits reply threading to messages associated with that Unit.
- `Email`, `email_unit`, `email_entity`, and `email_mail_message` are the existing contact/message structures and remain authoritative. Stage 12 must reuse them; it must not create a parallel contact table.
- `Sending` is a legacy per-email delivery/tracking record used by `/api/mail` and the `MyTestMail` template. Stage 12 outreach drafts never create it.

## Commercial-offer mailing stack

The existing commercial-offer stack is separate from the manual mailbox composers and includes:

- `mailing_contacts`, imports, contact sets and set members;
- templates and immutable template versions;
- campaigns, recipients, messages, provider events and links;
- `mailing_suppression_list`, webhook-call audit and campaign audit records;
- `MailingCampaignService`, `UnisenderGoClient`, renderer, recipient-set and webhook services;
- scheduler/CLI commands that prepare or process due campaigns.

The audit also found that the shared commercial-offer controller guard returned without denying a guest and that its permission fallback allowed access when permission resolution failed. Because the stack includes test and campaign send actions, the remediation places the entire controller group behind `auth:sanctum` and `verified`, makes the controller guard reject guests, and makes `sales_mailings.*` permission resolution fail closed. Existing ability checks remain in every action.

`MailingContact::scopeEligibleForMass` requires confirmed consent and excludes do-not-email, unsubscribe, complaint, hard bounce and local suppression. `UnisenderWebhookService` records unsubscribe, hard bounce and spam complaint state and adds local suppression. Public unsubscribe routes act on opaque campaign-recipient tokens. Provider webhook and tracking records remain part of the pre-existing stack.

Stage 12 does not call `MailingCampaignService`, `UnisenderGoClient`, queue processors, scheduler commands, provider webhooks, tracking endpoints, or test-send endpoints. Existing suppression and terminal `MailingContact` states are one-way blocking evidence in Stage 12. They can never grant permission.

## Permission and suppression semantics

No existing record implements the purpose-, sender-, product- and recipient-scoped evidence ledger required for AI-assisted outreach. In particular:

- `mailing_contacts.consent_status=confirmed` is legacy campaign state, not sufficient Stage 12 permission evidence by itself;
- a public, corporate, imported, validated or Unit-linked email is not consent;
- `unknown`, missing or expired permission blocks;
- do-not-email, unsubscribe, complaint, hard bounce and active suppression always win;
- sales/customer data cannot expose supplier/procurement information;
- raw correspondence is not an AI context source.

Stage 12 therefore adds an append-only evidence/decision ledger and a scoped suppression ledger while reusing `Email`, Unit context links and the existing hard-block sources.

## Authorization findings and remediation design

The four audited routes are outside a protective middleware group at baseline. The three mutating POST routes have neither authentication nor a server-side permission; the Unit route does not invoke `UnitPolicy`. Hiding composer buttons is not authorization.

The remediation will:

1. remove `GET /send-email` from the runtime registry;
2. protect every retained POST route with `auth:sanctum`, `verified`, and a conservative code-owned `mail-send` limiter;
3. remove `/api/mail`, update its only caller to the protected generic composer, and add the narrow `mail.send` permission to the code-owned permission catalog, granted initially only through the existing administrator all-permissions assignment;
4. enforce the permission in each FormRequest/controller and again in a shared `AuthorizedMailDispatchService`;
5. enforce `UnitPolicy` and Unit access both before and inside the service for Unit-bound sends;
6. require a UUID idempotency key and store only hashes/counts/status/safe error codes in a dispatch-attempt audit record;
7. preserve the used success contracts where safe, while removing raw provider errors and routine recipient/body/path logging;
8. make tests use `Mail::fake()`, `Queue::fake()` where relevant, and `Http::preventStrayRequests()`.

The authorized manual-mail service is not injected into or callable by the Stage 12 outreach application layer. Approval and dispatch eligibility for an outreach draft remain previews only while `AI_OUTREACH_DISPATCH_ENABLED=false` and the global AI sending flag is false.

## Reuse decision

Reuse:

- configured `MailboxRegistry` and Laravel Mail transport for existing authorized manual mail only;
- `MailMessage`, `MailMessageAttachment`, `Email`, Unit/Entity pivots and safe threading metadata;
- existing Unit access policy/service;
- existing mailing suppression, unsubscribe, complaint and bounce state strictly as blockers;
- established Product/Good matches, scoring snapshots and Unit contact-context links for draft evidence.

Do not reuse as a grant or duplicate:

- legacy `Sending` or campaigns for AI outreach;
- legacy `consent_status` as automatic advertising permission;
- raw `MailMessage` correspondence in AI DTOs;
- provider clients, mail queues, tracking or unsubscribe tokens for draft generation;
- a second SMTP/provider/contact stack.
