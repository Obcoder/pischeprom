# AI Sales campaign automation policy

Automation modes are `manual`, `assisted`, and `autonomous_reviewed`; the default is `manual`. The last mode is not an authorization shortcut. It requires a restricted admin permission, explicit owner-selected Unit/draft policies, a separate human reviewer approval, current hashes, and every relevant runtime guard.

All Stage 14 flags are false and all global campaign ceilings are zero by default. Enabling one switch does not enable another. In particular, campaign, scheduler, live Yandex, public research, ingestion, auto Unit, scoring, auto draft and notifications have independent gates. Existing global AI, contour, provider, outreach-dispatch and kill-switch protections remain authoritative.

Safety invariants are code-owned:

- fixed workflow and Product-first purpose;
- one active run per campaign;
- bounded run/day/month budgets;
- no implicit retry or provider failover;
- no provider-native tools;
- no arbitrary prompt, query, URL, model or provider from the browser;
- no Entity create/link, consent grant, dispatch, follow-up or scheduler override;
- missing flag, setting, approval or hash fails closed;
- a policy block becomes a safe review item, never a permissive fallback.

Stage 14 does not activate a production scheduler. `ai-sales:run-due-campaigns` is read-only unless `--apply` is given, and apply remains forbidden in production.
