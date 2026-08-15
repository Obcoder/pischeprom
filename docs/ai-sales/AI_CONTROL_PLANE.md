# AI Sales control plane

## Scope

Stage 04 adds a local control plane for deterministic, fake-only execution. It does not register an HTTP transport, vendor SDK, provider URL or credential. `config/ai-sales.php` fixes `transport_mode` to `fake_only`; every execution and contour feature flag defaults to `false`. `AI_EXTERNAL_CALLS_ENABLED=true` is itself a Stage 04 policy violation.

The control plane reuses the Stage 03 Unit dossier boundary:

- `Unit` remains the dossier and `UnitBusinessContext` supplies `lane` + `role_code`;
- `AiDataClassificationRegistry`, `AiDisclosurePolicy`, `AiContextSanitizer` and `UnitSharedPublicProfile` are re-run before selection and again in the queued worker;
- Entity create/link remains behind `DeterministicEntityCreateLinkGuard`; a run cannot create or link an Entity;
- no Lead model/table/domain is introduced;
- no transaction or raw correspondence relation is serialized into AI input.

## Persistence

Stage 04 creates:

| Table | Safe local state |
|---|---|
| `ai_agent_definitions` | disabled, versioned synthetic definitions and prompt/schema hashes |
| `ai_agent_runs` | actor and Unit/context snapshots, contour, budgets, status and safe errors |
| `ai_agent_run_steps` | bounded request summary/hash, normalized metadata and usage |
| `ai_tool_calls` | hashes and redacted summaries; tool execution remains pending local authorization |
| `ai_policy_decisions` | immutable classification/visibility and policy decision snapshots |
| `ai_data_access_logs` | DTO/source/count audit without field values |
| `ai_redaction_events` | detector/rule/path hash without original value |
| `ai_provider_capabilities` | evidence-backed capability state |
| `ai_model_residency_verifications` | non-secret human residency attestations |
| `ai_control_settings` | global and per-contour kill switches |

The existing append-only `ai_usage_records` ledger is reused and additively extended with nullable run/step, contour/route/capability, reasoning/cached token, search/tool and normalized RUB fields. Existing price-list columns, records and `PriceListImport` relation remain intact. Provider/model/amount/currency/request ID reuse the existing `provider`, `model`, `estimated_cost`, `cost_currency` and `external_request_id` columns.

Raw prompts, Safe DTO payloads, provider responses, tool arguments, credentials and arbitrary URLs have no persistence columns. Prompt and output text are transient; only hashes, bounded summaries and normalized metadata are stored.

## API and UI

All Stage 04 routes are under `auth:sanctum`, `verified` and the named `ai-sales` rate limiter. Server-side policy/permission checks are authoritative:

| Endpoint | Permission boundary |
|---|---|
| `GET /api/ai-sales/control-plane` | `ai_sales.control.view` + Unit dossier view |
| `PATCH /api/ai-sales/control-plane/kill-switches/{scope}` | `ai_sales.control.manage` |
| `GET /api/ai-sales/agent-definitions` | definition policy |
| `GET /api/ai-sales/runs` | `ai_sales.runs.view`, filtered to visible lanes |
| `POST /api/ai-sales/runs` | `ai_sales.research.run`, Unit/context view and lane authorization |
| `GET /api/ai-sales/runs/{public_id}` | run policy and current context lane |
| `POST /api/ai-sales/runs/{public_id}/cancel` | `ai_sales.runs.cancel` and run policy |

Run creation accepts only definition code/version, Unit/context IDs and a caller idempotency key. Contour, model, prompt, schema, URL and headers are server-owned. The Unit UI shows flags, kill switches, definitions, verification state, lane-filtered run status/usage and cancellation; it never renders raw prompt/response/key data.

## Operational safety

Definitions and feature flags are disabled by default. Kill-switch records are seeded open (`false`), but a missing setting is interpreted as active (`true`). Local execution additionally requires an unexpired, exact provider/route/model RU verification with a human `verified_by`. Capability rows missing current verified evidence fail closed.

Stage 04 is not approval to enable production execution. Database deployment/backfill, external provider integration, email delivery and automatic Entity changes are outside this stage.
