# Deterministic Unit candidate deduplication

Resolver version `stage08-deterministic-v1` runs locally without LLM, provider or HTTP. Strong signals are exact verified public URI/domain, corporate email domain and normalized public phone, in that order. Exact normalized name/alias plus exact city or region is review-only. Fuzzy name alone can never merge or create identity.

Outcomes are `exact_existing`, `probable_existing_review`, `new_unit_allowed`, and `rejected_invalid`. Decisions contain signal codes, Unit IDs, safe evidence references, bounded confidence components, rules version/hash, and always require human review. `prospecting_candidate_unit_matches` stores explainable hashes/references, never compared raw values.

Ambiguous or probable matches block new Unit creation. Registry hints remain unverified observations and never establish Entity identity. The legacy `entities.INN/KPP/OGRN` columns and `entity_unit` pivot do not carry verification provenance, so the `verified_linked_entity_identifier` local-only signal stays fail-closed and is not emitted. It may be enabled only after a human-reviewed, provenance-bearing identifier exists; raw legacy requisites are not silently treated as verified. There is no Unit merge or automatic Entity link.
