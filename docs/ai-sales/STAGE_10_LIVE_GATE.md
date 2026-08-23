# Stage 10 live gate

Stage 10 is code-only and default-off. It authorizes no live scoring acceptance.

Required defaults:

```text
AI_SALES_ENABLED=false
AI_SALES_TRANSPORT_MODE=fake_only
AI_PROSPECTING_SCORING_ENABLED=false
AI_PROSPECTING_AUTO_SCORING_ENABLED=false
AI_PROSPECTING_SCORE_OVERRIDES_ENABLED=false
AI_PROSPECTING_AI_EVIDENCE_ENABLED=false
AI_PROSPECTING_LIVE_SCORING_ENABLED=false
AI_PROSPECTING_SEARCH_EXECUTION_ENABLED=false
AI_PROSPECTING_PAGE_FETCH_ENABLED=false
AI_PROSPECTING_PUBLIC_RESEARCH_ENABLED=false
AI_EXTERNAL_CALLS_ENABLED=false
AI_PROVIDER_FAILOVER_ENABLED=false
AI_OUTREACH_SENDING_ENABLED=false
```

Automated tests use fake providers and `Http::preventStrayRequests()`. Live Yandex and Timeweb request budgets are both zero. No scheduler, outreach, email, Unit/Entity automation, Good-card search button or Stage 11 workflow is enabled.

Any future live gate must be a separate owner-authorized stage with an isolated database, exact model/provider evidence, explicit budgets and fresh policy review. The known Stage 09B public-fetch fail-closed limitation remains documented and must not be weakened as a scoring workaround.
