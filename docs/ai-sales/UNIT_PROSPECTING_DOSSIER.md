# Unit prospecting dossier

Stage 08 keeps `Unit` as the durable dossier, `ProspectingCandidate` as a transient review object, and `Entity` as a confirmed legal or natural person. A Candidate is never a Lead, Unit, or Entity. Human resolution enriches or creates a working Unit, but never creates, links, or merges Entity and never copies Sale/Purchase rows.

The dossier reuses Stage 03 contexts, roles, sources, aliases, observations, contact links, audit events, the existing Email/Telephone/URI records, and the Entity proposal guard. New durable data is limited to an explainable Unit–Good suggestion. Job and Candidate data has a bounded retention profile.

All browser routes are authenticated, verified, throttled and policy protected. Purpose determines lane and role server-side. Provider, model, contour, prompt, tool, URL and execution choices are prohibited. Feature flags are default-off. Search providers and live discovery are not implemented; those belong to Stage 09.

## Boundary

- buyer discovery → `sales` + `prospective_customer`;
- supplier discovery → `procurement` + `prospective_supplier`;
- explicit context is required for a mixed-role Unit;
- `entity_unit`, Sales and Purchases remain source-of-truth relations;
- no `Lead` or `unit_contacts` aggregate is introduced.

## Stage 08R correction

Durable prospecting data is now Product-first. The dossier projects context-bound Product needs/offers before optional Good offer fits. Historical Good-first rows are retained as permission-gated diagnostics. See [UNIT_PRODUCT_MATCHES.md](UNIT_PRODUCT_MATCHES.md) and [GOOD_OFFER_FIT.md](GOOD_OFFER_FIT.md).
