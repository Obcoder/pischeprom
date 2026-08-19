# AI Sales campaign architecture

`ClientAcquisitionCampaign` is the missing recurring-program aggregate above the existing Product-first pipeline. It owns safe campaign configuration and approval, but not the records produced by each stage.

## Data ownership

| Concern | Source of truth |
|---|---|
| Campaign definition and frozen limits | `ai_sales_campaigns` |
| Product scope (`primary`, `additional`, `exclude`) | `ai_sales_campaign_products` |
| Campaign-to-run/search binding | `ai_sales_campaign_run_links` |
| Run and ordered step state | existing `ai_agent_runs`, `ai_agent_run_steps` |
| Search, research and Candidate state | existing prospecting tables |
| Unit dossier/context/provenance | existing Unit domain |
| Product/Good matches and scores | existing match/snapshot tables |
| Draft, claims and reviews | existing outreach tables |
| Review queue and metrics | read-only projections, no copied rows |

V1 purpose, lane and role are server-owned `buyer_discovery`, `sales`, and `prospective_customer`. Product is the semantic scope; an optional Good is accepted only when its Product mapping is deterministic. Material configuration is represented by canonical hashes and one human approval snapshot.

The fixed workflow creates or reuses the existing Search Job, then reuses Yandex abstraction, safe public research, Candidate resolution, Unit context/matches, deterministic scoring and Stage 12 drafting. A campaign cannot create/link Entity, grant consent or permission, dispatch mail, select provider tools, retry, or fail over.

## Runtime boundaries

- API: authenticated, verified, throttled, FormRequest allowlists, policy and application-service authorization.
- Queue: run ID and actor user ID only, unique lock, `tries=1`, no failover.
- Scheduler: dry-run by default; apply is flag-gated and blocked in production at Stage 14.
- Automation: global switch plus feature-specific switches plus approved immutable policy snapshot; absent/zero configuration blocks.
- UI: operator control/review surface only. It has neither production-live nor email-send action.
