# Product ↔ Good actual-relation audit

Read-only structural audit performed for Stage 08R on 2026-08-16. It is based on committed models and migrations; production/default MySQL was not queried.

## Actual cardinality and tables

`Product::goods()` and `Good::products()` are both `BelongsToMany` relations through `good_product`. The pivot contains `id`, `good_id`, `product_id`, and timestamps with foreign keys, but has no unique constraint on `(good_id, product_id)`. Consequently a Good can have zero, one, or many distinct Products, and duplicate historical pivot rows are structurally possible. Reconciliation must count distinct Product IDs and collapse duplicate pivot rows in reads; it must never infer a Product from a Good name or characteristics.

The legacy `product_unit` pivot is a context-free operational relation with `action_id`. The `manufacturers` table is a separate Unit↔Product relation. Neither is suitable for an AI-sales market match. Stage 08R therefore adds `unit_product_matches`, bound to `UnitBusinessContext`, and does not create or write a generic `product_unit` match.

## Field and relation classification

| Field/relation | Actual implementation | Classification for Stage 08R | `PublicProductSummary` | Decision |
|---|---|---|---|---|
| Product identity | `products.id` | public catalogue reference when published | yes | reuse explicit scalar |
| Russian name | `products.rus`, required and indexed | public when `is_published=true` | yes, bounded | reuse |
| English name | `products.eng`, nullable | public when `is_published=true` | yes, bounded | reuse |
| Other translations | nullable scalar columns on `products` | potentially public, but not required by Stage 08R | no | fail closed until separately allowlisted |
| Publication | `products.is_published`, defaults true | policy gate | not emitted | require in every public query |
| Category | nullable `products.category_id`; `Product::category()` | public label only when both Product and Category are published | yes, bounded label | explicit join/select; no lazy graph |
| Goods | many-to-many via `good_product` | public only for published Goods and a published Product | separate bounded Good DTO list | reuse as secondary offers |
| Components | many-to-many via `component_product` | internal product composition | no | block |
| Consumers | `consumptions`, includes Unit and quantity/measure | customer/internal commercial data | no | block |
| Manufacturers | `manufacturers`, Unit↔Product | supplier identity/internal commercial data | no | block |
| Units | context-free `product_unit` with `action_id` | legacy internal relation | no | do not use for prospecting |
| Goods quotations/prices | Good relations and pricing tables | commercial confidential | no | block by default |
| Sales/Purchases/suppliers/margins | transaction and commercial relations | lane-confidential | no | never load in Product tools |

## Reconciliation consequence

- Good → exactly one distinct Product: deterministic mapping is allowed.
- Good → zero Products: `missing_product_mapping`, human review required, no Product guessed.
- Good → more than one distinct Product: `ambiguous_product_mapping`, human review required, no Product guessed.
- A Product match may exist without a Good offer fit.
- A new Good offer fit must reference a context-bound `UnitProductMatch` whose Product is one of the Good's distinct mapped Products.
