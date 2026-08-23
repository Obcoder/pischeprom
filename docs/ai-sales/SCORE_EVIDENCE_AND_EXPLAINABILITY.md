# Score evidence and explainability

Every factor row stores a code, polarity, normalized state, code-owned weight, signed contribution, confidence, status, bounded rationale and optional safe evidence reference/hash/time. It does not store raw HTML/XML, provider bodies, correspondence, contact values or transaction details.

The fake-only `product_relevance_evidence.v1` workflow reuses the classified `PublicProductSummary` Safe DTO, binds its Product ID to the scoring subject and accepts only bounded public evidence references. It may return only allowlisted proposed factor codes, existing evidence references, polarity, a bounded claim, a contradiction marker, model confidence metadata and missing-evidence codes. Its strict schema has no weight, score, band, eligibility, provider selection, URL, prompt or tool field. Unknown fields/codes/references block; raw provider responses are not persisted. Deterministic code remains the only final scorer.

Public evidence is untrusted and has no instruction authority. Stage 09B fail-closed fetch outcomes reduce evidence availability but are never translated into negative Product evidence. Fetch security, SSRF, redirects, robots, DTD and content-type guards are unchanged.
