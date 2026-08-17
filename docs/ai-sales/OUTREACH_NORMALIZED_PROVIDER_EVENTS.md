# Outreach normalized provider events

Stage 13 consumes only records already authenticated and normalized by the hardened Unisender ingress:

`UnisenderWebhookIngress` → safe persistence → ID-only `ProcessUnisenderWebhookJob` → `UnisenderWebhookService` → `OutreachNormalizedEventService`.

The outreach service receives a `MailingEvent` model and uses only its safe provider/event IDs, `sending_id`, normalized type/status, verified/event/processed timestamps, safe error code and bounded summary. It never reads `raw_payload`, `parsed_payload`, `payload`, request/response payloads, recipient copies or raw exception messages.

Normalized mappings are:

- accepted/sent/delivered → monotonic dispatch and Sending status;
- soft bounce → review-only state, no resend;
- hard bounce → terminal state, endpoint suppression, follow-up cancellation;
- spam/complaint → terminal complaint state, endpoint suppression, follow-up cancellation;
- unsubscribe → terminal state, endpoint suppression, follow-up cancellation;
- open/click → counters/timestamps only, never consent or permission.

Endpoint suppressions also block every still-pending dispatch and cancel every pending follow-up bound to the same existing Email endpoint. They do not rewrite an already provider-accepted outcome as unsent.

Ingress request and event fingerprints provide replay/deduplication. The hardened worker marks the event processed and passes IDs only. Out-of-order lower-precedence events cannot downgrade a terminal state.
