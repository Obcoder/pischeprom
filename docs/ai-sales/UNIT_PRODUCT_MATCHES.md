# Unit Product matches

`unit_product_matches` is the durable Product relevance relation for prospecting. Its identity is `(unit_business_context_id, product_id, match_type)` and it also stores the owning Unit explicitly for fail-closed ownership checks.

The direction is context-bound:

- sales context → `potential_need`;
- procurement context → `potential_offer`;
- `cross_sell` and `unknown` remain explicit typed values where applicable.

Each row carries status (`suggested`, `reviewed`, `approved`, `rejected`, or `stale`), origin, bounded rationale, evidence reference/hash, optional evidence confidence, source and Candidate-Product provenance, rules version, actor review data, and staleness. Product relevance confidence is evidence metadata, not a Stage 10 prospect score.

Creation validates Unit/context ownership and lane direction. Review is authenticated, server-authorized, permission-checked, and lane-isolated. Rows use a review lifecycle and cannot be deleted. The legacy context-free `product_unit` relation is deliberately not used.
