# Score lifecycle and staleness

The six scoring tables are append-only snapshots and immutable factors. A deterministic idempotency key prevents duplicate snapshots for the same definition/input/evidence/subject. The writer locks the subject and supersedes the previous current snapshot after the new snapshot and factors exist.

Human review and manual override copy the current snapshot into a new row. Override requires a bounded effective score, code-owned reason, safe note, optional expiry, actor and base snapshot. It never mutates computed score or eligibility. Expired overrides are marked stale and return through an explicit recalculation; no silent in-place fallback occurs.

Only `stale_at`, `stale_reason_code`, `superseded_at` and `superseded_by_snapshot_id` may change after creation. Code-owned stale reasons cover Product/Good/evidence/context/contact/mapping/relationship/definition changes and override expiry. Automatic recalculation is default-off to prevent event storms. The CLI is dry-run by default, chunked and idempotent; apply requires explicit confirmation outside production.
