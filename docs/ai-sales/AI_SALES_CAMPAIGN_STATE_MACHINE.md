# AI Sales campaign state machine

## States

`draft`, `review_required`, `approved`, `scheduled`, `running`, `paused`, `blocked`, `completed`, `cancelled`, `archived`.

## Allowed transitions

| From | To |
|---|---|
| draft | review_required, cancelled |
| review_required | approved, scheduled, cancelled |
| approved | scheduled, running, review_required, cancelled |
| scheduled | running, paused, review_required, cancelled |
| running | review_required, paused, blocked, completed, cancelled |
| paused | approved, scheduled, running, review_required, cancelled |
| blocked | review_required, cancelled |
| completed | scheduled, archived |
| cancelled | archived |
| archived | none |

Transitions use optimistic `lock_version` checks. Submission requires the owner and a complete Product-first scope and positive fail-closed limits. Approval freezes Product, criteria/geography, cadence, automation, budgets, workflow, policy and disclosure hashes. Any material edit clears approval and returns an approved campaign to `review_required`.

A start operation uses a distributed cache lock, an approval-bound idempotency key and a one-active-run check. Pause stops advancement without inventing a retry. Resume revalidates approval. Cancel marks the campaign and all non-terminal run steps cancelled. Stale approval, policy or budget failures stop the run with safe codes. Scheduled completion computes the next occurrence; manual completion stays completed.

Run steps are fixed 1–14. They are never reordered by a model or browser. Cancellation and approval are checked before progression; `retry_count=0`, `failover_count=0`, and the queue worker has one try.
