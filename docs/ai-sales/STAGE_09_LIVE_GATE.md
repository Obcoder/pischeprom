# Stage 09 Live Gate

Stage 09 is code-only. Acceptance requires `live Yandex HTTP = 0` and `live Timeweb HTTP = 0`. Automated tests use `FakeSearchProvider`, Laravel HTTP fakes and fake AI only.

## Default-off flags

All must remain `false` after deploy until a separate owner-reviewed Stage 09B:

```text
AI_PROSPECTING_QUERY_PLANNING_ENABLED
AI_PROSPECTING_SEARCH_EXECUTION_ENABLED
AI_PROSPECTING_EXISTING_YANDEX_PROVIDER_ENABLED
AI_PROSPECTING_PAGE_FETCH_ENABLED
AI_PROSPECTING_AUTO_CANDIDATE_INGESTION
AI_PROSPECTING_PUBLIC_RESEARCH_ENABLED
```

Existing global kill switches, provider failover and native tools remain blocking. `AI_PROSPECTING_LIVE_SEARCH_ENABLED` is deprecated/unprofiled and must stay false; Stage 09 execution fails closed if it is true.

## Existing connection only

Stage 09B may use only the owner-controlled server-side values already named by:

```text
YANDEX_SEARCH_API_KEY
YANDEX_SEARCH_FOLDER_ID
YANDEX_SEARCH_REGION
YANDEX_SEARCH_HOST
```

Do not create, copy, print, rotate or rename these values. The exact host must remain the code allowlisted Yandex endpoint. No second credentials/config/settings store is permitted.

## Owner review before any live probe

- confirm staging/non-production environment and isolated non-production DB;
- confirm `.env` and keys are not tracked/frontend-visible;
- confirm Product page authorization change is accepted;
- confirm existing key/service-account scope, quota and billing limits;
- review Yandex data-processing/retention terms and current robots obligations;
- verify outbound allowlist, TLS and DNS pinning support;
- start with one approved synthetic/public Product-first Job;
- use no customer, supplier, Unit, Entity, transaction or correspondence data;
- set retries/failovers/auto Candidate ingestion to zero/off;
- define a small request/page/cost budget and operator kill procedure;
- retain only safe execution/result/usage metadata.

## Required Stage 09B evidence

- exact endpoint/profile/request count and safe request IDs;
- result/parser behavior without raw body retention;
- Product page regression on staging;
- bounded SSRF-safe page fetch against owner-approved public fixtures;
- safe errors for auth/rate/timeout/malformed/oversized cases;
- proof of zero fallback and zero external AI calls;
- post-probe flags returned to default-off;
- credential/private-key/raw-body/frontend-bundle scans;
- production/default MySQL unchanged.

Any key exposure, unexpected host, raw-body persistence, private-network resolution, unbounded redirect, unauthorized route, fallback, or domain-data read is a stop condition.

Stage 10 scoring, outreach, scheduler/autonomy, Good-card action and live AI research are outside this gate and remain unimplemented.
