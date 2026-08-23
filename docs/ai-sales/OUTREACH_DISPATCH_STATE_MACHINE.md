# Outreach dispatch state machine

`OutreachDispatchStateMachine` is the sole state transition helper for provider and response lifecycle changes.

| State | Meaning |
|---|---|
| `prepared`, `review_required`, `ready` | Reviewed outbox construction phase; `ready` is queueable only after all guards pass. |
| `queue_pending`, `queued` | Durable queue intent / worker acquisition. |
| `provider_accepted`, `sent`, `delivered` | Monotonic provider lifecycle based on safe transport metadata or verified normalized events. |
| `soft_bounced` | Review-only delivery problem; never an automatic retry. |
| `hard_bounced`, `complained`, `unsubscribed` | Terminal for new work, create endpoint suppression and cancel follow-up. |
| `replied` | Exact human reply correlated; all follow-up stops. |
| `blocked`, `cancelled`, `expired`, `failed` | Pre-provider terminal or operator lifecycle outcomes. |
| `ambiguous_acceptance` | Transport outcome is unknown; operator review is mandatory and automatic resend is prohibited. |

Terminal precedence is deterministic and prevents late lower-priority events from reopening work. Complaint and unsubscribe remain suppression outcomes; hard bounce remains terminal; a reply cannot start follow-up. Open/click update engagement counters only and never create permission or consent.

The state machine is idempotent for equal states. A queued worker locks the dispatch and refuses terminal or already accepted work. Provider acceptance that is already known is preserved even if a later local cancellation/suppression occurs; no new dispatch is created.
