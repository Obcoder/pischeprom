# Autonomous Unit creation policy

Policy: `autonomous_unit_creation.v1`. Default: blocked.

All of the following are required for the optional operation:

- campaign and auto-Unit switches enabled, including the existing prospecting switch;
- `autonomous_reviewed` campaign with explicit auto-Unit approval and current campaign hash;
- Candidate belongs to this campaign, is `new_unit_review` with deterministic outcome `new_unit_allowed`, and is sales/prospective-customer;
- valid non-personal corporate domain and no exact/probable unresolved duplicate;
- at least one primary corporate public source and the configured number of independent public source families;
- no secret, unclassified, opposite-lane, or personal-only evidence;
- approved Product scope and positive per-run/day/month campaign and global Unit caps;
- distributed fingerprint lock plus a final duplicate recheck.

The result is one working Unit dossier, a sales `UnitBusinessContext`, aliases/sources/contact provenance allowed by existing classification rules, and Product match evidence. It never creates or links Entity, never copies transactions, never creates legacy Lead, and never grants consent or communication permission. Any failed condition leaves the Candidate for human review.
