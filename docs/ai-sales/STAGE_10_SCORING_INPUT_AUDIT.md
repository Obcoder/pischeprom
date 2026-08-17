# Stage 10 scoring input audit

Date: 2026-08-17
Base commit: `cf90ed99b0c6019cccbeff74f0cf11bca272d606`

This audit was completed before the Stage 10 scoring implementation. The order of authority is the live schema/code, accepted Stage 03–09B ADRs and documentation, and only then the Stage 10 proposal.

## Domain subjects

The repository already has the three required context-bound subjects and they remain the source of truth:

- `UnitProductMatch` binds one Product to one `UnitBusinessContext`; Product relevance must be attached to that row and must not depend on a Good.
- `UnitGoodMatch` binds a Good to a `UnitProductMatch` and the same Unit/context; Good fit must be attached to that row.
- `UnitBusinessContext` contains the lane and role; prospect priority must be attached to that context, never to a context-free Unit.

No new Lead, Unit, Entity, Product, or Good source of truth is required. `Entity` remains the source for transactions linked through `entity_unit`.

## Audited inputs and safe use

| Input | Actual source | Stage 10 use | Guard |
|---|---|---|---|
| Product match evidence | `unit_product_matches.evidence_reference`, `evidence_hash`, status, confidence and provenance links | direct Product evidence and review state | exact Unit/context/lane binding; rejected is zero; missing evidence blocks |
| Candidate Product evidence | approved `prospecting_candidate_products` and bounded `prospecting_candidate_sources` | public Product evidence and independent source families | references/hashes and metadata only; never raw provider/page bodies |
| Unit observations | `unit_observations` plus `unit_sources` | verified process/end-use, industry, geography, contradiction and freshness signals | public/internal-safe classification, same-lane visibility, code-owned observation-key allowlist |
| Public corporate channels | `unit_contact_context_links` | verified-channel presence metadata for prospect priority | no channel value, snapshot or normalized hash enters a factor/explanation |
| DNC/suppression | context stage and `unit_contact_context_links.communication_state` | eligibility only | blocks effective queue eligibility without changing computed research score |
| Product publication | `products.is_published` | evidence/completeness state | no implicit commercial assertion |
| Good mapping | distinct `good_product.product_id` through `GoodProductMappingResolver` | exact mapping factor/prerequisite | 0/N mapping has no score; duplicate pivot rows collapse to distinct Product IDs; mismatch blocks |
| Good publication | `goods.is_published` | unpublished cap | it is not availability, stock or commercial approval |
| Sales relationship | distinct `sales.id` through linked Entity IDs | sales-context relationship factor | count only; no values/details copied; procurement excluded |
| Procurement relationship | distinct `purchases.id` through linked Entity IDs | procurement-context relationship factor | count only; no values/details copied; sales excluded |
| Duplicate state | unresolved context-bound Candidate/Unit match metadata | cap/block/review | candidate names, contacts and raw result content are excluded |

All assemblers must use explicit selects, bounded result counts and bytes, no model `toArray()`, no lazy-loaded relation graph, and no generic SQL supplied by a user or model. Every provenance source is rebound to the exact Unit/context before use; same-domain pages collapse to one registrable-domain family, while an invalid or cross-Unit source fails closed.

## Code-owned observation vocabulary

Stage 10 may consume only these bounded public observation families:

- `product.direct_mention.{product_id}`
- `product.process_use.{product_id}`
- `product.industry_fit.{product_id}`
- `product.geographic_fit.{product_id}`
- `unit.public_fact`
- `unit.profile_fact`
- `unit.business_summary`
- `unit.capability`
- `unit.certification`
- `unit.location_summary`
- candidate-resolution observations `public_activity` and `prospecting_relevance`

The Product-specific families are deterministic human/reviewed evidence hooks. A record with an unknown key, missing provenance, disallowed classification/visibility, or opposite-lane context cannot affect a score. Unverified evidence is low-confidence metadata only. Contradicted and stale observations remain visible as explicit penalties; a failed page fetch is never treated as negative Product evidence.

## Unavailable or unsafe inputs

The audited model has no Stage 10-approved, context-bound fields for MOQ, packaging compatibility, format/processing compatibility, origin/grade/size compatibility, regional delivery, approved availability, or approved price. Generic Good descriptions, stock movements, price rows and legacy relevance values are not accepted as substitutes. These Good-fit factors are therefore `unknown` with contribution `0` in V1.

The following are never scoring inputs: raw HTML/XML, raw provider bodies, arbitrary URLs/prompts, personal channel values, secrets, credentials, email content, transaction line items/amounts, opposite-lane evidence, legacy Lead data, AI self-reported scores, or fetch failures.

## Existing limitations retained

Stage 09B public fetch intentionally fails closed for unsupported or unsafe markup. That limitation may reduce available evidence; it does not create a contradiction or penalty. Fetch security is unchanged in Stage 10.

## Persistence conclusion

No existing table stores immutable, explainable score snapshots or per-factor rows. Six additive tables are justified: a snapshot and factor table for Product relevance, Good fit and context prospect priority. Definitions, weights, thresholds, caps and explanation templates must remain immutable code-owned registries and must not be editable in the database or browser.
