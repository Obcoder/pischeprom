# Scoring confidence and eligibility

`computed_score`, `effective_score`, `confidence`, `band`, `review_status` and `eligibility` are independent fields.

Confidence is derived from evidence verification, authority, recency and corroboration. Unverified evidence confidence is capped at 39; AI self-reported confidence is metadata only. Confidence bands are low 0–39, medium 40–69 and high 70–100.

Stage 10 eligibility contains only:

- `not_evaluated`
- `research_only`
- `review_required`
- `blocked_do_not_contact`
- `blocked_suppressed`
- `blocked_policy`
- `blocked_duplicate`

There is deliberately no `allowed` or `consented`. DNC/suppression/policy/duplicate blocks cannot be removed by score override. Blocked priority snapshots retain the computed score for explanation but have effective score zero.
