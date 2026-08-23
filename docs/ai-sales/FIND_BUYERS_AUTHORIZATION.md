# Find Buyers authorization

All endpoints live inside the existing `auth:sanctum`, `verified`, and `throttle:ai-sales` route group. Form Requests validate explicit allowlists, model policies protect Job records, and `FindBuyersAuthorizationService` rechecks permissions and sales-lane access in application services.

No new permission vocabulary is needed. Stage 11 reuses:

- `ai_sales.prospecting.view` and `ai_sales.sales.view` for launch/progress;
- `ai_sales.prospecting.jobs.manage` for draft/cancel;
- `ai_sales.search.plan` for launch and query planning;
- `ai_sales.prospecting.review` for the existing human approval path;
- `ai_sales.scoring.view` to reveal score projections.

The API routes are:

```text
GET   /api/ai-sales/find-buyers/launch-context
GET   /api/ai-sales/find-buyers/geography
GET   /api/ai-sales/find-buyers/dashboard
POST  /api/ai-sales/find-buyers/drafts
PATCH /api/ai-sales/find-buyers/drafts/{job}
POST  /api/ai-sales/find-buyers/drafts/{job}/plan
POST  /api/ai-sales/find-buyers/drafts/{job}/submit
POST  /api/ai-sales/find-buyers/jobs/{job}/cancel
GET   /api/ai-sales/find-buyers/jobs/{job}/progress
```

There is no execute endpoint. Route binding uses the Job public UUID. Services revalidate current owner, purpose, lane, status, wizard version, Product publication, Good mapping, geography hierarchy, and permissions, so hiding a Vue button is never the security boundary. Permission revocation between HTTP validation and service execution fails closed. Procurement-only actors cannot use the sales workflow.

Responses use Resources or bounded projection arrays. They never reveal the draft idempotency key, credentials, contacts, raw evidence, supplier-lane data, or provider bodies.
