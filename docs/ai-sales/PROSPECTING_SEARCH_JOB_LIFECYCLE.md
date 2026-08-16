# Prospecting search job lifecycle

`ProspectingSearchJob` is a bounded, auditable instruction, not an AI run or search result. Stage 08 supports only:

```text
draft → review_required → approved → archived
                    ↘ cancelled → archived
```

Only the owner can edit a draft. A lane-authorized reviewer approves it. `auto_create_unit`, search count and cost are always zero/false. There is no execute endpoint. Goods use `prospecting_search_job_goods` with one `primary` and bounded `additional` assignments; criteria JSON is allowlisted and bounded.

`ProspectingSearchQuery` stores provider-neutral, safe display history for synthetic/manual fixtures. It stores hashes and counters, never raw response bodies, page HTML, secrets or prompts. Stage 09 owns any SearchProvider and live execution.

## Stage 08R correction

The lifecycle itself is unchanged, but the primary scope is now Product, not Good. See [PRODUCT_FIRST_PROSPECTING.md](PRODUCT_FIRST_PROSPECTING.md). `prospecting_search_job_goods` and `primary_good_id` are retained for historical compatibility; new browser writes use a required-on-approval primary Product and only optional Product-compatible originating Goods.
