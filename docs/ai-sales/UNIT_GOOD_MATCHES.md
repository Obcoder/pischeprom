# Unit–Good matches

`unit_good_matches` binds exactly one Unit, UnitBusinessContext, and existing Good. Buyer jobs create `potential_need`; supplier jobs create `potential_offer`. Context ownership and direction are enforced by domain code. Rationale is bounded and Stage 07 DLP blocks opposite-lane/secret material.

The lifecycle is `suggested`, `reviewed`, `approved`, `rejected`, or `stale`. Origins are manual, rule, candidate, or reserved `ai_future`. A unique Unit/context/Good/direction identity prevents parallel duplicates. Evidence is a safe reference/hash and optional UnitSource/Candidate relation.

This is not a prospect score engine. No score snapshot/factor tables or model scoring were added; those belong to Stage 10.

## Stage 08R correction

The text above describes the committed Stage 08 meaning. New writes no longer use Good as the primary market relation. `unit_good_matches` is retained as the secondary [Good offer fit](GOOD_OFFER_FIT.md) table and must link to a compatible context-bound [Unit Product match](UNIT_PRODUCT_MATCHES.md). Unlinked historical rows remain review-only diagnostics until explicit [reconciliation](STAGE_08R_RECONCILIATION.md).
