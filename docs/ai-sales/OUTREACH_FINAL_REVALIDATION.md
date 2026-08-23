# Outreach final revalidation

`OutreachFinalRevalidationService` recomputes eligibility at `prepare`, `queue` and `worker` checkpoints. A prior preview or approval is never trusted as a dispatch authorization.

The deterministic guard verifies:

- current authenticated actor permission, Unit access, sales lane and customer/prospective-customer role;
- active UnitBusinessContext with exact Unit/context binding;
- current, non-expired approved draft revision and all independent current-revision reviews;
- DLP, renderer, content, evidence and claims hashes/freshness;
- exact active Email/contact link without cross-lane or IDOR substitution;
- approved and fresh Product match, optional approved Good fit, and non-stale/non-blocked score snapshots;
- active purpose/Product/contact-scoped communication permission with exact Unit/context/Email/endpoint binding;
- endpoint, domain, Unit, context and global suppression precedence;
- server-owned valid From and Reply-To configuration;
- exact `outreach_zero_retry` request profile;
- queue/provider feature flags, zero retry/failover, global/domain daily limits and recipient cooldown immediately before transport.

Each decision stores only a checkpoint, safe block reasons and canonical hashes. Permission revocation, new suppression, draft revision, evidence change, sender configuration change or stale score between checkpoints blocks the dispatch. Global and per-domain limits default to `0`, so ordinary runtime is fail-closed.
