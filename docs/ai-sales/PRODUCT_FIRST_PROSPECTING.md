# Product-first prospecting

Stage 08R makes `Product` the primary discovery scope while retaining the committed Stage 08 review and retention boundaries.

## Search Job scope

`prospecting_search_job_products` stores one `primary`, bounded `additional`, and bounded `exclude` Product set. Every new selected Product must be published. Approval requires a primary Product. Purpose remains server-owned:

- `buyer_discovery` → sales / prospective customer / `potential_need`;
- `supplier_discovery` → procurement / prospective supplier / `potential_offer`.

Optional `originating_good_ids` are concrete offer candidates. Each must be published and map to exactly one selected Product. Missing, ambiguous, or out-of-scope mappings set a code-owned compatibility state and block approval. The committed `primary_good_id` and `prospecting_search_job_goods` remain only for compatibility and diagnostics; browser requests cannot write the deprecated Good-first fields.

## Candidate scope

`prospecting_candidate_products` records the approved Product subset, bounded rationale, evidence reference/hash, confidence, source, and review provenance. Synthetic Candidate import accepts only a non-empty subset of its approved Job Product scope. Resolution refuses a Candidate without an approved Product relation.

The Candidate Resource exposes Product evidence and optional originating Goods separately. Provider, model, contour, prompt, tools, arbitrary URL, live execution, Entity actions, and automatic Unit creation remain outside this input contract.
