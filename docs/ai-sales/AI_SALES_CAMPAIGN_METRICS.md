# AI Sales campaign metrics

`ClientAcquisitionCampaignMetrics` is a read-only, exact-count projection over campaign run links and existing workflow records. It reports:

- runs started/completed/blocked;
- queries planned/executed;
- search results, unique domain hashes and fetch outcomes;
- Candidate totals and exact/probable/new decisions;
- Units created/enriched;
- Product matches and current score snapshots;
- drafts and review categories;
- normalized Yandex request count, run token/cost totals, and Timeweb requests where known.

No conversion value is inferred when the underlying record does not exist. Fetch failure is not negative company evidence. Metrics are campaign-scoped and authorization-scoped. In Stage 14 `emails_sent` is a hard zero because campaign orchestration has no dispatch path; it is not a claim about unrelated legacy mail.
