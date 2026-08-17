# Unisender webhook and provider persistence remediation

## Scope and deployment

The vulnerable callback was confirmed in the production baseline branch `main` at `af839e447ef4a2aa80608d4a9a1ac727800d8bdc`: it persisted `raw_payload` before auth verification. The remediation is implemented in shared legacy mail/Unisender code on `feature/ai-sales-agents`, has no Stage 13 or AI dependency, and is intended to be cherry-picked and deployed independently.

No production migration or cleanup is performed by application boot, scheduler, queue startup, or deploy hooks.

## Verified ingress

`POST /webhooks/unisender-go` has only:

1. dedicated burst-aware throttle;
2. exact `application/json` / identity encoding guard;
3. 256 KiB encoded body cap;
4. bounded in-memory JSON parse;
5. documented Unisender auth verification using constant-time comparison;
6. schema and 100-event cap;
7. allowlisted normalization;
8. one minimal DB transaction;
9. ID-only async job dispatch.

The configured provider format is `json_post`; gzip is rejected. The route opts out of cookie, session, CSRF, bindings and Inertia middleware. Provider signature is the authentication boundary; `auth:sanctum` is intentionally not used.

Invalid requests persist nothing and dispatch nothing. Request hashes and event fingerprints provide replay/dedupe without inventing a timestamp window. A duplicate valid request returns safe HTTP 200. The worker uses normalized event rows only, is idempotent, has one try and applies deterministic terminal precedence (`spam` > `unsubscribed` > `hard_bounced`).

## Normalized schema

Migration `2026_08_17_123000_harden_unisender_provider_persistence.php` is additive and reversible. It adds:

- webhook request hash, status, verification/processing timestamps and safe error metadata;
- provider event/message IDs, normalized type/status, webhook-call link, internal `MailingMessage`, `Sending` and `MailMessage` links, processing timestamps and safe error metadata;
- recipient-safe error code/summary fields, replacing legacy raw failure, delivery and clicked-URL columns;
- outbound request/response hashes, request profile, HTTP status category, safe request ID, safe error metadata and ambiguous-acceptance timestamp.

The historical migration is unchanged. Legacy raw columns become nullable and deprecated. New Eloquent writes to those columns fail closed.

## Outbound profiles

`legacy_manual` retains the audited legacy transport retry setting. `outreach_zero_retry` has zero transport retries and queue `tries=1`. A connection failure under the outreach profile becomes `ambiguous_acceptance`, requires operator review and is never resent automatically. Provider response bodies and exception text are not persisted or logged.

## Legacy audit and owner-gated cleanup

Use:

```bash
php artisan mailings:provider-payloads:audit --chunk=500
php artisan mailings:provider-payloads:purge --chunk=500
```

Both commands are safe-output and the purge command is dry-run by default. `--apply` is blocked outside local/testing/staging. It clears deprecated webhook/event/outbound payload and raw error columns while preparing hashes/normalized metadata when possible. It is chunked and idempotent.

Proposed retention after owner-approved production cleanup:

- purge all historical raw provider/webhook bodies and raw error/recipient/network copies;
- retain normalized webhook call envelopes for 30 days for replay/audit evidence;
- retain normalized delivery events according to the commercial communication/legal retention policy, proposed maximum 365 days unless an active legal hold requires otherwise;
- retain immutable request/response hashes and provider/internal IDs with their parent business record;
- review retention quarterly and never restore raw payload capture for troubleshooting.

Production row counts and cleanup remain owner-gated. Evidence files, keys and raw provider bodies must never be attached to cleanup output or stored in Git.
