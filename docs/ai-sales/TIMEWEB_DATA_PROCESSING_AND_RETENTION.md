# Timeweb data processing, residency and retention record

Status: Stage 05 integration implemented; production approval blocked; live evidence absent.

## 1. Public documentation

As reviewed on 2026-08-15, Timeweb documents AI Gateway as direct model access at `https://api.timeweb.ai/v1`, with model selection on every request, Chat Completions, a capability-dependent Responses API and model inventory. The integration intentionally does not use ready-made Timeweb agents, RAG, MCP or knowledge bases.

Public references:

- [Timeweb AI Gateway](https://timeweb.cloud/docs/ai-agents/api-usage/ai-gateway)
- [Supported API types](https://timeweb.cloud/docs/ai-agents/api-usage/types-of-api)
- [Available model overview](https://timeweb.cloud/docs/ai-agents/pricing/models)

Public documentation is evidence only for the documented interface. It does not establish exact-model capabilities, data residency, upstream processing location, retention, `store=false` semantics, stable request-ID headers or Zero Data Retention.

## 2. Timeweb support or contract confirmation

None recorded in this repository.

Before any local model becomes usable, a human must review the exact model in the Timeweb panel `Локальные` filter or obtain support/contract confirmation, record only a safe reference plus SHA-256, and set an expiry of at most 30 days. Confidential contract/support text must stay outside the application and Git.

Required but currently absent confirmations:

- exact model processing and storage geography;
- provider and upstream processor class;
- request/response logging and retention period;
- whether `store=false` is passed through and honored;
- incident/security contact and notification SLA;
- model deprecation/change notification;
- key revocation and rotation procedure;
- cross-border processing terms for `external_sanitized`.

## 3. Live probe observations

None. Stage 05 automated tests use Laravel HTTP fakes with stray HTTP blocked. No staging keys were available to the implementation, so `/models`, Chat Completions and Responses were not called live and no exact model ID was discovered.

When an owner runs the guarded staging CLI, the evidence record may contain only route/model/capability, support state, timestamp/expiry, adapter/policy/schema version, safe request ID, evidence/result hashes, token counters and local RUB estimate. It must not contain prompt, output, provider body, headers or key.

Acceptance of `store=false` proves only parameter acceptance. Rejection proves unsupported. A successful response without written provider confirmation remains `unknown` for retention/ZDR claims.

## 4. Unknowns

Current unknowns for every real Timeweb exact model:

| Area | State | Consequence |
|---|---|---|
| Current model inventory | unknown | No real model route can be selected |
| RU residency for `local_ru` | unknown | Local route blocked |
| Chat endpoint | unknown | Capability authorization blocked |
| Responses endpoint | unknown | Endpoint profile remains `unsupported` |
| Strict structured output | unknown | Strict-schema tasks blocked |
| Function/tool calling | unknown | Tool workflows blocked |
| `store=false` | unknown | No retention claim; route blocked when required |
| Hosted web search | unsupported by Stage 05 contract | No hosted search call |
| Usage fields/request ID | unknown | Capability evidence absent |
| Exact-model RUB pricing | unknown | Probe budget guard blocks model calls |
| Provider/upstream retention | unknown | Production external enable blocked |
| Incident/deprecation contacts | unknown | Production approval blocked |

## 5. Risk acceptance and production blockers

There is no Stage 05 production risk acceptance. Critical retention, upstream and cross-border unknowns block production `external_sanitized` unless a named owner formally accepts them outside this code change. `local_ru` remains blocked without current exact-model human evidence; a local label or model-name prefix is never evidence.

Operational controls:

- separate local/external staging keys; different HMAC fingerprints required;
- all production and external flags remain default-off;
- no automatic `local_ru -> external_sanitized` failover;
- fixed host/path, TLS verification, redirects/retries disabled;
- no request/response logging or raw persistence;
- hard request/token/RUB/time budgets and immutable pricing snapshot;
- immediate stop on route mismatch, blocked marker, unexpected logging, redirect, missing residency or budget breach;
- revoke both staging keys after an incident or suspected disclosure, then update owner-managed secrets and verify different fingerprints;
- re-sync inventory before probing, re-probe on adapter/model change, and renew RU evidence no later than expiry (maximum 30 days).

The control-plane UI is observation-only for this integration. It exposes safe inventory, capability/support state, residency expiry and key configured/fingerprint status to `ai_sales.capabilities.view`; it cannot submit a prompt, URL, model, tool or live probe.
