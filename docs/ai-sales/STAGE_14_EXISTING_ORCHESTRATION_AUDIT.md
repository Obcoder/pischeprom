# Stage 14 existing orchestration audit

Date: 2026-08-19. Baseline: Stage 13 plus the stateless Unisender pre-auth security port. This is a code/schema audit; no production system was queried.

## Reuse map

| Required capability | Existing source of truth | Stage 14 decision |
|---|---|---|
| Agent definitions, runs and steps | `ai_agent_definitions`, `ai_agent_runs`, `ai_agent_run_steps`; `AiAgentRun` | Reuse. Add only `ai_sales_campaign_run_links` to bind a recurring campaign to a run and Search Job. |
| Search Jobs and Queries | `prospecting_search_jobs`, `prospecting_search_job_products`, `prospecting_search_queries`; Stage 08/09 services | Reuse without a second query or job store. |
| Search execution and results | `prospecting_search_executions`, `prospecting_search_results`; existing Yandex provider abstraction | Reuse. Campaign code cannot choose provider, query, URL, or retry policy. |
| Safe public fetch and research | `prospecting_public_fetches`, `prospecting_public_research_records`; `SafePublicPageFetcher`, `PublicCompanyResearchWorkflow` | Reuse the existing SSRF, DNS, robots, redirect, content and DLP boundary. |
| Candidate and deterministic resolution | `prospecting_candidates` and its source/channel/product/unit-match tables; `ResolveProspectingCandidate` | Reuse. Extend the resolver with a policy-bound autonomous Unit operation; default behavior remains review. |
| Unit creation and enrichment | `Unit`, `UnitBusinessContext`, Unit source/alias/contact/provenance models | Reuse. Unit stays the dossier; Entity is never auto-created or linked. |
| Product and Good relevance | `products`, `goods`, `good_product`, `unit_product_matches`, `unit_good_matches` | Reuse. Add one normalized campaign/Product scope relation because no recurring campaign scope existed. Product is semantic scope; Good is optional origin. |
| Explainable scoring | relevance/good-fit/prospect-priority snapshots and Stage 10 scoring services | Reuse without changing weights or conflating score, confidence and eligibility. |
| Find Buyers | Stage 11 Product-first wizard, launch/progress services and Vue review panel | Extend its UI with a campaign dashboard; no second discovery domain. |
| Outreach drafts | `outreach_drafts`, revisions, claims and reviews; Stage 12 renderer/DLP/Safe DTO | Reuse. Automatic generation stops in `review_required` and carries no recipient into AI input. |
| Dispatch, provider events, replies and follow-ups | Stage 13 dispatch/reply tables and services | Do not invoke. Dispatch, provider send and follow-up flags stay false. |
| Budgets and kill switches | Stage 04 run budgets, provider settings and kill switches; later workflow feature guards | Reuse safety invariants and add frozen campaign run/day/month caps, all globally zero by default. |
| Scheduler and queues | Laravel scheduler/queue; existing IDs-only jobs | Add one default-off, non-production-only, bounded command and one IDs-only job (`tries=1`). No cron or worker change. |
| Notifications and review work | Existing in-app notification facilities plus source lifecycle statuses | No new notification store. Create a unified read projection over existing source records; notifications remain disabled. |

## Gap decision

No existing object represents an owner-approved, recurring, multi-run acquisition program with a frozen Product scope, schedule, budgets, automation policy and approval hash. Stage 14 therefore adds `ClientAcquisitionCampaign`, `ai_sales_campaign_products`, and the thin run link. It does not add Lead, Entity, Candidate, scoring, draft, dispatch, usage, tool, copied review-item, or copied progress tables.

## Boundaries found in actual code

- V1 is fixed to `buyer_discovery` / `sales` / `prospective_customer` in the model and service.
- Workflow order is the code-owned `buyer_acquisition_campaign.v1` registry; browser input cannot supply a workflow, tool, prompt, query, URL, provider, model, Entity, consent, scheduler, or dispatch value.
- Existing Yandex and Timeweb live transports remain behind their earlier guards. Stage 14 tests use only repository fixtures and fake providers.
- Legacy `Lead` remains untouched and is not a campaign source of truth.
- Existing production mail/webhook security code is reused unchanged by the campaign domain. Campaign code cannot enqueue a dispatch or send an email.
- Operational backup, restore, deployment, Nginx, callback registration and worker work is deliberately outside the feature artifact.
