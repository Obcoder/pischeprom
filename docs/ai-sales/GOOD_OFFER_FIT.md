# Good offer fit

Stage 08R reinterprets new `unit_good_matches` writes as an optional concrete Good offer fit beneath a `UnitProductMatch`.

A new row must:

- reference a Unit Product match in the same Unit and business context;
- use the same need/offer direction as that Product match;
- reference a Good with exactly one distinct mapped Product;
- map that Good to the referenced Product;
- start as `offer_candidate`, review-required, and unscored.

The fit lifecycle is `offer_candidate`, `preferred_offer`, `approved_for_offer`, `quoted`, `rejected`, or `stale`. The historical `relevance` column remains physically present but is not exposed as Product relevance or a fit score; the API explicitly reports that no fit score is available. Evidence confidence, bounded rationale, provenance, and compatibility state remain visible separately.

Rows without `unit_product_match_id` are historical Good-first diagnostics. They are preserved, cannot receive new lifecycle actions until reconciled, and are visible only through the permission-gated legacy diagnostics projection. No Good-card live-search action is added.
