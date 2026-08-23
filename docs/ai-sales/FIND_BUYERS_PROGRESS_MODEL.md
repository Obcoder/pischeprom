# Find Buyers progress model

`FindBuyersProgressQuery` is a read-only projection. It does not copy events or create a new source of truth. It aggregates existing Job Product/Good scope, query plans, executions, deduplicated results/domains, public fetch/research outcomes, Candidates, Unit Product matches, and current Stage 10 score snapshots.

The deterministic stages are:

```text
draft → query_plan_ready → review_required → search_pending → searching
→ public_research_pending/researching → candidates_created/candidate_review
→ units_enriched → scoring_pending → scored
```

Terminal or exceptional states are `completed`, `cancelled`, `failed`, and `blocked`. Stage selection uses persisted status/counts only. Progress includes the next permitted human action and always reports that live execution is unavailable at Stage 11.

The projection exposes safe Product/Good names, geography, creator/reviewer, limits, counts, grouped fetch/research status plus safe error codes, Candidate status/navigation, resolved Unit navigation, and score band/confidence/eligibility/history links. It never selects or returns search snippets, raw URLs with parameters, full HTML, raw provider bodies, protected contacts, or scoring rationales.

Match and score joins require the Candidate's Job, sales lane Candidate, sales UnitBusinessContext, and matching Unit/context IDs. Procurement matches and scores are excluded even when the same Unit exists in both lanes. DNC and suppression remain visible as blocking eligibility, never as a “hot lead” label.

`FindBuyersDashboard` groups these projections into My jobs, review required, in progress, Candidates, high priority, blocked, and completed. The dashboard remains read-only except for the separately authorized cancel action.
