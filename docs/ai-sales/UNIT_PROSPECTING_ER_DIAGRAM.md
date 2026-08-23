# Unit prospecting ER diagram

```mermaid
erDiagram
    UNIT ||--o{ UNIT_BUSINESS_CONTEXT : has
    UNIT ||--o{ UNIT_SOURCE : retains
    UNIT ||--o{ UNIT_ALIAS : retains
    UNIT ||--o{ UNIT_OBSERVATION : retains
    UNIT ||--o{ UNIT_CONTACT_CONTEXT_LINK : classifies
    EMAIL ||--o{ UNIT_CONTACT_CONTEXT_LINK : reused_by
    TELEPHONE ||--o{ UNIT_CONTACT_CONTEXT_LINK : reused_by
    URI ||--o{ UNIT_CONTACT_CONTEXT_LINK : reused_by

    PROSPECTING_SEARCH_JOB ||--o{ PROSPECTING_SEARCH_QUERY : records
    PROSPECTING_SEARCH_JOB ||--o{ PROSPECTING_CANDIDATE : yields_fixture
    PROSPECTING_SEARCH_JOB }o--o{ GOOD : scopes
    PROSPECTING_CANDIDATE ||--o{ PROSPECTING_CANDIDATE_SOURCE : evidenced_by
    PROSPECTING_CANDIDATE ||--o{ PROSPECTING_CANDIDATE_CHANNEL : reviews
    PROSPECTING_CANDIDATE ||--o{ PROSPECTING_CANDIDATE_UNIT_MATCH : suggests
    UNIT ||--o{ PROSPECTING_CANDIDATE_UNIT_MATCH : candidate_for
    PROSPECTING_CANDIDATE }o--o| UNIT : human_resolves_to

    UNIT ||--o{ UNIT_GOOD_MATCH : dossier_match
    UNIT_BUSINESS_CONTEXT ||--o{ UNIT_GOOD_MATCH : lane_binds
    GOOD ||--o{ UNIT_GOOD_MATCH : references

    UNIT }o--o{ ENTITY : reviewed_link_only
    ENTITY ||--o{ SALE : owns
    ENTITY ||--o{ PURCHASE : owns
```

There is deliberately no Lead aggregate, no Candidate→Entity action, no Unit transaction copy, no `unit_contacts`, no score tables and no universal event table. SearchProvider/live discovery is not implemented until Stage 09.

## Stage 08R correction

The diagram above records the committed Stage 08 Good-first shape. The accepted Product-first correction is documented in [ADR-PRODUCT-FIRST-PROSPECTING-AND-GOOD-OFFER-FIT.md](ADR-PRODUCT-FIRST-PROSPECTING-AND-GOOD-OFFER-FIT.md). Its additive path is `ProspectingSearchJob → prospecting_search_job_products → Product`, `ProspectingCandidate → prospecting_candidate_products → Product`, and `UnitBusinessContext → UnitProductMatch → Product`. `UnitGoodMatch` is now an optional secondary child of `UnitProductMatch`; historical unlinked rows remain diagnostics.
