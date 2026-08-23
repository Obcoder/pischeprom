# Timeweb AI Gateway Stage 05 integration

## Scope and runtime boundary

Stage 05 adds a default-off transport and two adapters over one fixed gateway:

```text
timeweb/local_ru
timeweb/external_sanitized
```

They are distinct security routes with distinct staging keys and exact server-owned model allowlists. `AiProviderRegistry` may register the adapters only in `timeweb_synthetic_only` mode. `AiStage04FeatureGuard` continues to reject that mode in the Unit-derived run pipeline, while each Timeweb adapter independently requires `AiProviderRequest.syntheticOnly=true`, a repository-owned fixture and a deterministic hash over the complete fixed synthetic input envelope. Consequently, changing environment flags cannot make a Stage 05 adapter send a Unit, Entity, Good, contact, correspondence, user or transaction DTO.

Production defaults remain disabled. There is no retry, failover, scheduler entry, queue retry, email action, Entity mutation, generic SQL, filesystem/shell tool, arbitrary HTTP endpoint or browser prompt endpoint.

## Transport

`TimewebAiGatewayTransport` permits only:

```text
GET  https://api.timeweb.ai/v1/models
POST https://api.timeweb.ai/v1/chat/completions
POST https://api.timeweb.ai/v1/responses
```

The URL validator requires HTTPS, host `api.timeweb.ai`, default/443 port and base path `/v1`; userinfo, query and fragment are rejected. Laravel HTTP client options force TLS verification, disable redirects and HTTP retries, and bound connect/total timeout plus response bytes. Only safe provider request IDs are retained when present. Provider error bodies, HTTP objects, headers, prompts and responses are discarded after normalization and never logged by this integration.

Transport errors normalize to code-owned categories for 400, 401/403, 402, 404, 409, 422, 429, 5xx, timeout, connection, TLS, DNS, invalid JSON/content type and oversized response. Safe errors contain neither provider body nor key.

## Configuration and keys

All `AI_TIMEWEB_*_ENABLED` and probe flags default to `false`; `AI_TIMEWEB_SYNTHETIC_ONLY` defaults to `true`. Live operational calls require `APP_ENV` `local`, `testing` or `staging`, `AI_SALES_TRANSPORT_MODE=timeweb_synthetic_only`, the AI Sales/global-egress/exact-contour flags, open global/contour kill switches, both separately configured route keys, different HMAC fingerprints, explicit probe enablement, positive hard request/token/RUB/time caps and CLI confirmation. Provider failover must remain disabled.

Keys remain environment secrets. They have no database column, API resource or Vue field. Admin-only control-plane output exposes only `configured` and a 12-character HMAC suffix. Model, key, route, URL and tool cannot be selected from an application HTTP request.

## Normalized inventory and evidence

`ai_provider_models` stores the exact model ID, route, safe display label, active/inactive inventory state, endpoint profile, safe metadata subset, timestamps, evidence reference/hash and bounded operator references. Raw `/models` data is never stored. Missing models are marked inactive, never deleted, and model names never create residency evidence.

`ai_provider_capabilities` is reused and additively gains:

- independent `support_state`: `supported`, `unsupported`, `unknown`;
- evidence source and safe request ID;
- adapter/policy/schema versions;
- result hash and non-secret operator reference.

Lifecycle status (`documented`, `synthetic_tested`, `staging_approved`, and later states) is separate from capability support. Authorization requires both a verified lifecycle and `support_state=supported`. Stage 05 never writes `production_canary` or `production_approved`.

`ai_provider_pricing_snapshots` contains immutable exact-model RUB rates, version/effective period and evidence hash. A model call is blocked if the configured snapshot version is absent or stale. Provider usage and the local RUB estimate stay separate.

An admin-only, HTTP-free command records a reviewed snapshot and refuses to mutate an existing version:

```bash
php artisan ai:timeweb-pricing:record \
  --route=external_sanitized \
  --model=EXACT_MODEL_ID \
  --verifier-id=USER_ID \
  --input-per-million=RUB_RATE \
  --output-per-million=RUB_RATE \
  --source-reference=public-doc:REFERENCE \
  --source-hash=SHA256 \
  --confirm-human-reviewed
```

## CLI workflows

Inventory is dry-run unless apply is explicit:

```bash
php artisan ai:timeweb-models:sync --route=local_ru --dry-run --synthetic
php artisan ai:timeweb-models:sync --route=external_sanitized --apply --confirm-apply --synthetic
```

Human RU residency verification is a separate, HTTP-free action. It requires exact active local inventory, `ai_sales.residency.verify`, a safe evidence reference, evidence SHA-256 and explicit confirmation. Expiry is capped at 30 days:

```bash
php artisan ai:timeweb-residency:verify \
  --model=EXACT_MODEL_ID \
  --verifier-id=USER_ID \
  --evidence-reference=panel-review:REFERENCE \
  --evidence-hash=SHA256 \
  --confirm-human-reviewed
```

Capability probes remain CLI-only:

```bash
php artisan ai:provider-probe timeweb \
  --route=external_sanitized \
  --profile=all \
  --confirm-synthetic
```

`--record-evidence` is required to persist only normalized capability metadata/hashes. No command is scheduled.

## Synthetic probes

The runtime fixture registry supplies fictional public fixtures and one explicitly local-only PII fixture. Block canaries exist only in the automated test fixture source. The only tool is `catalog.get_synthetic_good`; it validates exact arguments and returns a constant fixture without database access. Probes cover inventory/auth, Chat Completions, two locally reconstructed Responses requests without `previous_response_id`, native structured schema, a two-step local synthetic tool cycle, `store=false`, usage/request ID, safe error taxonomy, DLP canaries and no cross-contour fallback.

External probes accept only public synthetic fixtures. Credentials, unclassified fields, raw correspondence, personal data and customer/supplier secret markers block before HTTP. Local probes additionally require current human RU evidence for the exact inventory model; local does not mean blanket access.

## Current evidence state

No Timeweb key or exact model ID is committed. No live call was made by the implementation or automated test suite. Therefore all real Timeweb model capabilities, endpoint profiles, prices, retention behavior and RU residency remain unverified until an owner-controlled staging run completes. HTTP-fake contract tests prove only application adapter behavior; they do not claim provider capability.
