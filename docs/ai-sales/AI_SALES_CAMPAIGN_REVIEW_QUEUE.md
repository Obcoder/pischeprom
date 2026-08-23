# AI Sales campaign review queue

`CampaignReviewQueue` is a unified read projection. It creates no queue table and never becomes a second source of truth.

It projects these categories from existing rows: `query_plan_review`, `candidate_duplicate_review`, `new_unit_review`, `product_match_review`, `outreach_content_review`, `outreach_claim_review`, `permission_review`, `policy_block`, `provider_error`, and `budget_block`.

Each safe item identifies the campaign and source row and, when available, run, step, Search Job, Unit, context and Product. It contains only a bounded reason code, next permitted action, safe evidence/hash, score/confidence, age/SLA, owner and reviewer. It does not copy raw search/page/provider bodies, prompts, full draft content, recipient values, secrets, or opposite-lane data.

Access requires campaign view permission, sales-lane visibility and campaign ownership/reviewer/admin scope. Mutations continue through their original domain services and policies. Resolving a projected item therefore cannot bypass Candidate, Unit, score, outreach, permission or dispatch review boundaries.
