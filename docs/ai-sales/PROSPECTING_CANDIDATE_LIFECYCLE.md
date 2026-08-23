# Prospecting candidate lifecycle

A Candidate is transient and starts as `pending_resolution`. Deterministic evaluation moves it to `exact_existing_unit`, `probable_existing_review`, `new_unit_review`, or `rejected`. An explicit permitted human action then produces `existing_unit_enriched`, `new_unit_created`, or `rejected`. Retention ends in `anonymized`.

Candidate text is Unicode-normalized and bounded. URLs are canonical HTTP(S) without credentials and reject literal private/reserved/link-local and local/metadata hosts. Sources keep a short excerpt and evidence hash, not full pages. Contact values are encrypted at rest and Resources expose only aggregate channel counts. Person-specific channels are `personal_data` and review-only.

Resolution is transactional, locks the Candidate, checks an approved job and re-evaluates duplicate signals. It is idempotent and does not mutate Entity, legal identity, consent, transactions, mail or canonical Unit name.

## Stage 08R correction

Candidate identity resolution is unchanged, but an approved `prospecting_candidate_products` relation is now mandatory. Resolution first creates/reuses context-bound [Unit Product matches](UNIT_PRODUCT_MATCHES.md); a compatible originating Good may then become a separate [Good offer fit](GOOD_OFFER_FIT.md). A Product match is valid without a Good.
