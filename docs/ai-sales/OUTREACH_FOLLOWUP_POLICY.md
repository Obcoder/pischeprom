# Outreach follow-up policy

Stage 13 supports recommendation metadata only. Code-owned defaults are:

- maximum follow-ups: `0`;
- automatic scheduling: disabled;
- automatic sending: disabled;
- provider retries: `0`;
- failover: disabled.

`OutreachFollowUpRecommendationService` may create one idempotent plan for a dispatch. A normal plan is `scheduled_disabled` and explains that a new draft, current permission and fresh independent reviews would be required. No runnable step or queue job is created.

Any reply cancels the plan as `cancelled_reply`. Hard bounce cancels it as `cancelled_bounce`. Complaint, unsubscribe or another effective suppression cancels it as `cancelled_suppression`. A retry must never be represented as a follow-up. Opening or clicking a message does not authorize follow-up.

Stage 14 is required before any autonomous follow-up design can be considered.
