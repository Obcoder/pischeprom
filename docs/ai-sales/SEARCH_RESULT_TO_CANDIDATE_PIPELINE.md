# Search Result to Candidate Pipeline

## Preconditions

Manual Candidate ingestion requires:

- authenticated active user;
- lane visibility;
- `ai_sales.search.research` and existing `ai_sales.prospecting.review` permissions;
- approved current Product-first Job and query plan;
- non-duplicate persisted SearchResult;
- completed safe fetch from the same exact host;
- valid search provenance hashes.

Directory-only/search-snippet evidence is insufficient.

## Aggregation and normalization

`IngestProspectingSearchCandidate` collects at most 20 non-duplicate results for the same Job and exact host. `ProspectingCandidateService::createFromSearchResult()` reuses existing:

- `ProspectingCandidateNormalizer`;
- Job-scoped normalized organization/domain fingerprint;
- Candidate source/channel persistence;
- existing Unit candidate resolution/review flow;
- Candidate Product provenance.

The unique `(prospecting_search_job_id, fingerprint_hash)` key makes creation idempotent and concurrency-safe. SearchResult rows are linked only after Candidate persistence; conflicting links block.

## Evidence and Product scope

- sources reference `search-result:{result_hash}` and bounded public excerpts;
- protected contact channels are normalized/encrypted and remain `review_required`;
- only primary/additional Products from approved Job scope are accepted;
- `prospecting_candidate_products.source=search`;
- Product evidence hash derives from search evidence plus Product ID;
- no Good semantic fields are copied; Good remains an optional offer fit downstream.

## Human boundary

Ingestion creates only a `ProspectingCandidate`. It does not:

- create or link Unit automatically;
- create or link Entity;
- infer consent;
- send email;
- create transactions;
- resolve a Candidate without human review.

Existing `UnitCandidateResolver` and Candidate review endpoints remain the only path to evaluate/enrich/create a Unit, with their Stage 08/08R permissions and guards. Entity remains unchanged.
