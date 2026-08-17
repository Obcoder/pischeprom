# Product-first Find Buyers workflow

Stage 11 adds an internal, authenticated workflow:

```text
Product or Good page
→ launch-context resolution
→ six-step wizard
→ ProspectingSearchJob draft
→ code-owned query plan
→ human review
→ read-only progress projection
```

Product is always the semantic discovery scope. Good is only an optional commercial offer. A Product-page launch fixes the current published Product. A Good-page launch resolves distinct `good_product` Product IDs: zero mappings block, one selects deterministically, and many require an explicit human choice. Duplicate pivot rows never create duplicate options. Every submitted Good/Product pair is revalidated server-side.

The workflow reuses `prospecting_search_jobs`, job Product/Good pivots, queries, executions, results, public fetch/research records, Candidates, Unit matches, and Stage 10 score snapshots. It does not create a parallel Find Buyers or Lead domain. The only schema addition is bounded launch/idempotency/review metadata on `prospecting_search_jobs`.

The server derives `purpose=buyer_discovery`, `lane=sales`, `role_code=prospective_customer`, and Product match semantics `potential_need`. It derives the safe objective from selected Product and validated geography. Browser-supplied purpose, lane, role, prompt, query, provider, model, contour, URL, tools, execution, Unit creation, and Entity inputs are prohibited.

At Stage 11, live search, page fetch, research, Candidate ingestion, automatic scoring, retries, failover, email, Unit creation, and Entity creation/linking remain unavailable from this workflow. Existing Yandex Product search remains a separate unchanged feature. Stage 11B is required before any live Find Buyers execution can be considered.

The repository-owned `ai-sales:run-synthetic-find-buyers` command demonstrates the complete logical path with `FakeSearchProvider`, synthetic page/research evidence, a synthetic Candidate, explicit human-review simulation, and deterministic scoring. It accepts only isolated SQLite in local/testing, prevents stray HTTP, and always rolls back all domain rows.
