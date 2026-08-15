# AI budgets and kill switches

## Default posture

All execution flags default to `false`; all Stage 04 RUB caps default to zero. Fake responses report `0.0000 RUB`, so an explicitly enabled development/test fake run can exercise control flow without authorizing spend. External HTTP remains separately forbidden.

Code-owned limits include:

- global daily/monthly RUB;
- daily `local_ru` and `external_sanitized` RUB;
- daily agent definition and task-profile RUB;
- daily Unit and UnitBusinessContext RUB;
- per-run steps, searches, tokens and RUB;
- maximum output tokens and tool calls;
- zero retries, bounded request timeout and wall-clock duration.

`AiRunBudgetGuard` checks aggregate and run state before provider selection. It validates returned token/search/tool/cost usage before committing usage/output metadata. A violation terminates the run as `budget_exceeded` and does not append an `ai_usage_records` success row. Money comparisons use fixed 1/10,000 RUB units; persisted money uses decimal columns.

## Kill switches

`ai_control_settings` contains:

```text
kill_switch.global
kill_switch.local_ru
kill_switch.external_sanitized
```

`true` means blocked. A missing record also means blocked. `AiStage04FeatureGuard` requires both global and selected-contour switches open on creation and queued reauthorization. It additionally rejects any non-`fake_only` transport, `external_calls_enabled=true` or `provider_failover_enabled=true`.

Only a user with `ai_sales.control.manage` can change a switch through the protected, verified and rate-limited API. UI visibility or a disabled button is not an authorization control; the service and controller re-check permission server-side and record `updated_by`.

## Recovery

Operators cancel pending runs or activate a contour/global switch. Stage 04 does not automatically resume, retry or move blocked runs to another contour. After policy, residency, capability or budget correction, an authorized human creates a new idempotent run.

## Stage 05 probe budgets

Timeweb operational commands have an independent in-memory hard budget for request count, reserved input/output tokens, RUB and wall-clock time. All caps must be positive before HTTP. Exact-model calls additionally require a current immutable `ai_provider_pricing_snapshots` record matching the configured version; missing usage is costed at the reserved worst-case amount. Inventory uses one zero-token reservation. Budget breach blocks the next request without retry or contour failover.
