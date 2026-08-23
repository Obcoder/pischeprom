# ADR: Product-first prospecting and Good offer fit

Status: accepted for Stage 08R on 2026-08-16.

## Context

Committed Stage 08 used `Good` as the discovery scope and durable Unit relevance. The actual catalogue model does not support that interpretation: `Product` describes what the market needs or offers, while `Good` is a concrete commercial catalogue offer. `good_product` permits zero, one, or many distinct Products per Good and has no unique pair constraint.

## Decision

- `Product` is the primary Search Job and Candidate discovery scope.
- `UnitProductMatch` is the durable, context-bound Unit market relation.
- `Good` is optional and secondary. A new `unit_good_matches` row is a Good offer fit and must reference a compatible `UnitProductMatch`.
- A Product match may exist without any Good.
- Only one distinct Good→Product mapping can be reconciled automatically. Zero or many mappings remain review-required and are never guessed.
- Existing Stage 08 tables and rows remain in place. Additive compatibility columns distinguish historical diagnostics from new offer fits.
- The context-free `product_unit` pivot is not an AI-sales match and is not read or written by this workflow.

## Consequences

Product relevance and Good commercial fit have separate evidence and review lifecycles. Product-first jobs cannot be approved without a primary published Product. Browser input cannot restore the deprecated primary-Good contract. Historical reconciliation is explicit, chunked, dry-run by default, idempotent, and production apply is blocked.

No Lead aggregate, Entity automation, canonical catalogue overwrite, transaction copy, score engine, provider integration, live search, or HTTP execution is introduced.
