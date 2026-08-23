# Stage 11 live gate

Stage 11 is a code-only UI/orchestration release. Its defaults are:

```text
AI_FIND_BUYERS_UI_ENABLED=false
AI_FIND_BUYERS_DRAFTS_ENABLED=false
AI_FIND_BUYERS_LIVE_EXECUTION_ENABLED=false
AI_FIND_BUYERS_AUTO_RESEARCH_ENABLED=false
AI_FIND_BUYERS_AUTO_SCORING_ENABLED=false
AI_PROSPECTING_SEARCH_EXECUTION_ENABLED=false
AI_PROSPECTING_EXISTING_YANDEX_PROVIDER_ENABLED=false
AI_PROSPECTING_PAGE_FETCH_ENABLED=false
AI_PROSPECTING_PUBLIC_RESEARCH_ENABLED=false
AI_PROSPECTING_AUTO_CANDIDATE_INGESTION=false
AI_PROSPECTING_AUTO_SCORING_ENABLED=false
AI_EXTERNAL_CALLS_ENABLED=false
AI_PROVIDER_FAILOVER_ENABLED=false
```

`FindBuyersFeatureGuard` fails closed if any Stage 11 execution/research/auto flag, external-call flag, or failover flag is enabled. The API has no live execute action. Query planning is code-owned and may be enabled only for local/synthetic validation alongside the UI/draft flags.

Before a future Stage 11B live gate, separately verify authorization, provider credentials without disclosure, bounded budgets, exact server-owned profile, existing Yandex policy, SSRF/robots/content security, retention, observability, kill switches, no-failover behavior, human query-plan approval, and a dedicated bounded synthetic acceptance. Stage 11 itself supplies no approval for live Yandex, Timeweb, email, autonomous scheduling, Candidate ingestion, Unit creation, or Entity mutation.

The only Stage 11 end-to-end runner is `ai-sales:run-synthetic-find-buyers`. It requires a fresh isolated SQLite database in local/testing, uses repository-owned fixtures and `FakeSearchProvider`, prevents stray HTTP, performs explicit simulated human review, reports only safe aggregates, and rolls back. Production/default MySQL, owner `.env`, external providers, mail, queues, and schedulers are outside its scope.

Partial and fail-closed fetch evidence is a valid outcome and appears only as safe status/error-code counts. No fetch guard may be weakened to improve a result. Stage 12 is not part of this gate.
