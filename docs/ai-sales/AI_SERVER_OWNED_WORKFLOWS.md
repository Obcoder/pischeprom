# Server-owned AI workflows

## Deterministic plan

`AiWorkflowRegistry` owns workflow selection. Browser input, prompt text and provider output cannot select a workflow or change step order. `AiWorkflowExecutor` resolves exactly one workflow from the persisted agent definition/version, task profile, purpose, audience, lane, role and selected contour.

Stage 07 contains one workflow:

```text
synthetic.good_context_classification.v1:1
  -> catalog.get_synthetic_good:1 with fixed {sku: SYN-001}
  -> transient Safe DTO + deterministic DLP
  -> FakeExternalSanitizedAiProvider (no tool schemas)
  -> strict {summary: string<=1000}
  -> hashes/counters only
```

It is local/testing-only, consumes only a repository-owned fictional fixture, performs zero handler DB queries, makes zero HTTP requests and has no business/live eligibility. `ai-sales:run-synthetic-workflow` accepts only an existing prepared run UUID and a bounded idempotency reference. There is no browser execution route and no arbitrary arguments, prompt, model, provider, URL or contour option.

## Lifecycle and replay

Execution requires a current `ready` run/step and lock version, current actor permission, unchanged Unit/context/lane/role/purpose/audience, current policy decision and exact workflow/tool hashes. The fixed read tool executes before the fake provider. Provider `toolSchemas` is empty. Any provider tool call, reordered/additional step or non-structured response is a protocol violation.

The workflow uses zero retries and zero failovers. A repeated completed execution returns safe persisted counters and does not repeat the handler or provider. A partial replay, stale step, cancellation or terminal run blocks. No result is parsed as a pseudo-tool protocol from free text.

## Default-off controls

```text
AI_TOOLS_ENABLED=false
AI_WORKFLOWS_ENABLED=false
AI_PROVIDER_NATIVE_TOOLS_ENABLED=false
AI_LIVE_BUSINESS_WORKFLOWS_ENABLED=false
```

Existing `AI_SALES_ENABLED`, fake execution, per-contour flags, zero-cost budgets and database kill switches are rechecked as well. Stage 07 adds no live Timeweb workflow. Enabling Timeweb transport or external HTTP still blocks this executor.
