# Stage 13 live gate

Stage 13 is code-only and default-off. Its tests and synthetic command use fake HTTP, Mail and Queue on an isolated file-backed SQLite database in the OS temporary directory. No live Unisender, Timeweb or Yandex request and no real email are allowed.

Before any future live acceptance, owners must separately verify:

- the hardened webhook security commit is deployed and its normalized ID-only worker is active;
- production migrations and legacy raw-row retention/purge were owner-reviewed separately;
- one exact reviewed revision, scoped permission, endpoint and Product/Good provenance are current;
- all three revalidation checkpoints pass with server-owned From/Reply-To;
- global and per-domain limits are explicitly non-zero and bounded;
- `outreach_zero_retry` remains transport retries `0`, queue tries `1`, failover `0`;
- an operator owns ambiguous-acceptance handling and suppression/reply review;
- logs and persistence scans show no raw provider body, raw reply copy, recipient duplication or secret;
- rollback restores all dispatch/event/reply/follow-up flags and limits to default-off.

A single owner-controlled email requires a separately authorized Stage 13B. Stage 14 is required for autonomy, scheduling or broader rollout. Stage 13 itself provides no working provider-send UI control.
