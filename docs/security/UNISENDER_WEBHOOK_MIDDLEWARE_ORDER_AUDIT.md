# Unisender webhook middleware order audit

## Scope and evidence

This audit covers the isolated hotfix branch at base
`d5bd587ec6ed2305fc4a6d4aaa9f3a9cc548d9b1`. The production facts below are
from the owner-authorized read-only audit of production baseline
`af839e447ef4a2aa80608d4a9a1ac727800d8bdc`; this task did not reconnect to or
modify production.

Production uses:

```text
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
Unisender event_format=json_post
Unisender max_parallel=10
```

No secret values were read or recorded.

## Before Hotfix V2

`bootstrap/app.php` loaded `routes/web.php` through Laravel's `web` group. The
callback block removed browser middleware one class at a time, then declared:

```text
Illuminate\Routing\Middleware\ThrottleRequests:unisender-webhook
App\Http\Middleware\VerifyUnisenderWebhookRequest
```

The route registry confirmed that exact order. The named throttle used the
default cache store and ran before content, body, schema and signature checks.
With the production database cache an invalid signature therefore produced:

```text
HTTP=403
mailing_webhook_calls delta=0
mailing_events delta=0
cache delta=2
```

The callback manually excluded cookie encryption/queueing, session start,
shared session errors, CSRF, bindings, Jetstream/Inertia and preload-link
middleware. That exclusion list was fragile, and it did not make the
pre-signature named throttle stateless.

Laravel's global stack still runs for every request:

```text
ValidatePathEncoding
InvokeDeferredCallbacks
TrustProxies
HandleCors
PreventRequestsDuringMaintenance
ValidatePostSize
TrimStrings
ConvertEmptyStringsToNull
```

The application registers no Telescope/debug/request-recorder middleware in
that stack. The global middleware perform no application DB mutation on the
callback regression fixtures.

## Hotfix V2 route registration

`routes/provider-webhooks.php` is loaded by the `then` routing callback and is
not wrapped in either `web` or `api`. The public URLs remain unchanged:

```text
GET|HEAD /webhooks/unisender-go
POST     /webhooks/unisender-go
```

The POST route has exactly two route middleware, in this order:

```text
App\Http\Middleware\VerifyUnisenderWebhookRequest
App\Http\Middleware\ThrottleVerifiedUnisenderWebhookRequest
```

There is no generic `throttle:*`, session, cookie, CSRF, binding,
authentication, Inertia, Telescope or request-audit middleware on the route.
Provider signature remains the callback authentication boundary.

## Runtime request sequence

The resulting pipeline is:

```text
fixed method/path route
→ exact application/json and identity encoding
→ declared and actual encoded body cap (262144 bytes)
→ bounded in-memory JSON parse (depth 32)
→ events_by_user schema and 100-event cap
→ unchanged documented Unisender auth algorithm and hash_equals
→ in-memory AuthenticatedUnisenderWebhookRequest
→ post-verification global provider-scope limiter
→ allowlisted event normalization
→ normalized persistence transaction
→ after-transaction ID-only database queue dispatch
```

The authenticated DTO can hold provider event arrays only in request memory.
It is never logged, serialized, cached, queued or persisted. The limiter key is
the code-owned constant `unisender-webhook:verified-provider-scope:v1`; it does
not contain the API key, request hash, signature, recipient, body, IP or
User-Agent. At the audited `max_parallel=10`, the application limit remains
120 verified requests per minute.

Safe request rejections do not log body, auth, headers, recipient, IP or
User-Agent. A post-verification limiter backend failure logs only provider and
the code-owned `processing_failed_safe` code. Queue-dispatch failure retains
only normalized IDs and safe codes.

## Regression proof

The dedicated file-backed SQLite suite runs with all production-like mutation
drivers set to `database`. It records every SQL `INSERT`, `UPDATE`, `DELETE` or
`REPLACE` after fixture setup and snapshots every table count.

Wrong method, missing/wrong content type, unsupported encoding, oversized body,
malformed JSON, missing/invalid auth, invalid schema and over-cap batch each
produce:

```text
database write ledger=[]
all table deltas=0
cache/cache_locks=0
sessions=0
jobs/failed_jobs=0
webhook/event rows=0
Set-Cookie absent
```

A valid request may mutate only `cache`, `mailing_webhook_calls`,
`mailing_events` and `jobs`. A verified throttled request creates no normalized
event or job. Duplicate valid callbacks remain idempotent.
