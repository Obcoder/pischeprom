# Existing outbound mail, event and reply pipeline audit

Date: 2026-08-17
Stage: 13 V2
Baseline: `50191e6432a21a1908d2f80612a76db938d281be`

This audit was completed before the Stage 13 migration and runtime changes. It describes the code and schema that actually exist after the accepted Unisender security remediation. Stage 13 extends this stack and does not create a second SMTP, provider, contact, correspondence, tracking or webhook implementation.

| Aspect | Existing implementation | Stage 13 decision |
|---|---|---|
| MailMessage source of truth | `mail_messages` stores incoming/outgoing correspondence, RFC `message_id`, `in_reply_to`, `references`, full body and existing Email pivots. Yandex mailbox sync is the inbound writer. | Reuse. Raw reply text/HTML/headers remain only here and are never copied to outreach records. |
| Sending source of truth | `sendings` is the existing per-recipient delivery/tracking record linked to `emails`; it has provider/status/tracking fields but no MailMessage relation or safe provider metadata. | Add a nullable MailMessage relation and safe normalized provider metadata. Stage 13 does not write its legacy raw `error` field or duplicate message bodies into Sending. |
| Provider dispatch service/job | `UnisenderGoClient::sendEmail()` is the only Unisender transport. `SendMailingCampaignJob` drives campaign sends through `MailingCampaignService`. | Reuse `UnisenderGoClient` through one outreach orchestration worker; no second provider adapter. The worker is ID-only, uses the existing `outreach_zero_retry` profile and never calls manual-mail controllers/services. |
| `outreach_zero_retry` profile | `UnisenderRequestProfile::OutreachZeroRetry`: transport retries `0`, queue tries `1`; connection ambiguity maps to `ambiguous_acceptance`. | Mandatory and asserted at queue and worker checkpoints. Failover remains zero/off. |
| Provider message/job IDs | Safe IDs exist on `mailing_messages`, `mailing_events` and `sendings`. The hardened ingress accepts `sending_id` and `mail_message_id` from allowlisted recipient metadata. | Persist the safe provider job ID on Sending and OutreachDispatch; correlate normalized events using existing ID metadata. |
| Queue/outbox | Campaign queue exists, but there is no reviewed outreach outbox. | `outreach_dispatches` is the atomic orchestration/outbox record. Queue dispatch occurs only after commit. Duplicate prepare/queue and queue replay reuse one record. |
| Accepted/sent/delivered | Hardened `mailing_events` stores normalized status and safe IDs. Legacy webhook processing updates campaign recipients. | Reuse `mailing_events`; Stage 13 applies deterministic state transitions to the linked OutreachDispatch/Sending without copying an event payload. |
| Soft/hard bounce | Normalized `soft_bounced`/`hard_bounced`; legacy service tracks threshold and suppression. | Hard bounce creates an endpoint communication suppression and cancels follow-ups. Soft bounce is review-only and cannot trigger resend. |
| Complaint/unsubscribe | Provider `spam` and `unsubscribed` statuses update legacy suppression. Existing public unsubscribe uses an opaque campaign recipient token. | Reuse the same public unsubscribe routes and controller, extended to hashed outreach tokens. Complaint/unsubscribe create Stage 12 endpoint suppression and cancel pending work. |
| Open/click tracking | Provider events are normalized. Legacy `/email/open/{token}` and `/email/click/{token}` update Sending; the click action currently accepts an arbitrary HTTP URL and logs unsafe data on failure. | Reuse and harden the routes: opaque token, code-owned target allowlist, no raw IP/UA persistence for outreach, no token/URL/error logging. Open/click are engagement only and never permission. |
| Normalized webhook events | Verified-before-persistence `UnisenderWebhookIngress` → `UnisenderWebhookPersistenceService` → ID-only `ProcessUnisenderWebhookJob`; raw columns are deprecated and guarded. | Consume only `MailingEvent` IDs and normalized columns. Never read or write deprecated raw/parsed/request/response fields. |
| Inbound MailMessage | Yandex sync creates incoming `MailMessage` and Email pivots. The schema has RFC correlation columns, although sync did not populate them at baseline. | Store bounded RFC `In-Reply-To`/`References` in MailMessage, then exact-correlate to an outreach outgoing MailMessage. No fuzzy company-name correlation. |
| Message-ID/In-Reply-To/References | Manual outgoing mail creates a local RFC Message-ID and reply headers. | Outreach creates a local RFC Message-ID. Correlation accepts exact normalized IDs only. |
| Reply-to alias/token | No dedicated code-owned outreach alias exists. Unisender has server-owned configured Reply-To. | Use only the configured server-owned Reply-To plus exact RFC thread identifiers; do not invent an address or accept one from a request. |
| Follow-up structures | None for outreach. | Create recommendation-only plan/step records. Stage 13 defaults to zero follow-ups and never schedules or sends. |
| Limits/cooldown | Commercial campaigns have legacy limits; Stage 12 dispatch is permanently disabled. | Add code-owned outreach global/domain daily caps, both default `0`, and revalidate them immediately before transport. |
| Permissions/UI | Stage 12 has draft/review/permission/suppression permissions and Unit Outreach UI. | Add narrow dispatch/event/reply/follow-up permissions. Only admin receives them through the existing all-permissions seed path. UI is visibility/review only by default and has no live provider-send control. |

## Security conclusions

- Hardened `POST /webhooks/unisender-go` is the only provider callback and remains signature-authenticated before every mutation.
- Manual routes and `AuthorizedMailDispatchService` are not an outreach dispatch path.
- Stage 13 uses existing `Email`, `MailMessage`, `Sending`, `mailing_events`, Stage 12 permission/suppression and Unit/context records.
- `mailing_webhook_calls.raw_payload`, `parsed_payload`, `mailing_events.payload`, legacy provider request/response payloads and raw exception text remain deprecated and are neither read nor written.
- Existing campaign/contact rows are not created merely to deliver an outreach message; `Email` remains the recipient owner.
- No controller calls Unisender. No Stage 13 default enables queueing, provider delivery, event ingestion, reply correlation, triage or follow-up.
