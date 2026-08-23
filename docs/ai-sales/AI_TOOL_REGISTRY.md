# Code-owned AI Tool Registry

## Boundary

Stage 07 регистрирует tools только в `AiToolRegistry`. Запись в БД, browser input или provider response не может создать tool, изменить handler, input/output schema, contour или policy. `ai_control_settings` остаётся только operational control storage; schema и handler из него не читаются.

Каждый immutable `AiToolDefinition` фиксирует code/version, safe description, strict input/output JSON Schema, Safe DTO classes, permissions, purpose/audience/lane/role/contour allowlists, classification и visibility ceiling, side-effect class, idempotency semantics, row/string/byte/time/query/call/cost caps, handler, schema hash и `stage07-v1` policy version.

Глобальный `AI_TOOLS_ENABLED` по умолчанию `false`. Даже зарегистрированный enabled tool не исполняется без server-owned workflow, reauthorization, открытых kill switches и всех остальных guard. DB не содержит raw arguments/results или tool definitions.

## Definitions

| Tool | Contour ceiling | Safe output | Stage 07 state |
|---|---|---|---|
| `catalog.get_synthetic_good:1` | `external_sanitized` | `PublicGoodSummary` из constant fixture | tests/dev workflow only |
| `catalog.search_public_goods:1` | local/external | published `PublicGoodSummary[]` | registered; no live workflow |
| `catalog.get_public_good_summary:1` | local/external | published `PublicGoodSummary` | registered; no live workflow |
| `geo.get_supported_regions:1` | local/external | `SupportedRegionSummary[]` | registered; no live workflow |
| `unit.get_shared_public_profile:1` | local/external | Stage 03 `UnitSharedPublicProfile` | registered; no live workflow |
| `unit.get_business_context_summary:1` | local only | Stage 03 `UnitBusinessContextSummary` | registered; no live workflow |
| `unit.get_public_business_contacts:1` | local only | Stage 03 `PublicBusinessContactSummary[]` | external personal values intentionally remain blocked |
| `unit.get_verified_public_observation_evidence:1` | local/external | verified public facts + bounded provenance | registered; no live workflow |
| `sales.get_aggregate_demand_summary:1` | local procurement only | Stage 03 `AggregateDemandSummary` | privacy threshold; no live workflow |
| `purchases.get_aggregate_supply_summary:1` | local sales only | Stage 03 `AggregateSupplySummary` | privacy threshold; no live workflow |
| `crm.find_unit_duplicate_candidates:1` | local only | opaque `UnitDuplicateCandidateSummary[]` | authorized review metadata; no live workflow |
| `pricing.get_customer_offer_summary:1` | local sales only | Stage 03 `CustomerOfferSummary` | definition disabled pending an approved business workflow |
| `crm.propose_entity_candidate:1` | local only | none | disabled, `proposal_only`, human review required |

No generic DB/model/relation/filesystem/shell/process/arbitrary HTTP/browser/send/direct Entity/Sale/Purchase/secret tool is registered. Unknown tool or version is `BLOCK`.

## Query rules

Handlers use explicit columns and named joins, bounded limits, deterministic allowlisted sorting, no user-selected columns/relations, no Eloquent graph serialization and no raw SQL supplied by a caller/provider. Tool execution counts queries after the policy boundary and enforces the definition cap. Tests enable lazy-loading prevention for catalog/geo handlers.

Public Goods require `is_published=true`. Transaction tools emit only aggregates after the code-owned minimum cohort (five; never lower than three), without transaction rows or identities. Duplicate candidates use opaque HMAC references and remain local/internal. `CustomerOfferSummary` exposes no purchase price, supplier, cost or margin and remains disabled.

## Safe audit

The existing `ai_tool_calls` table remains the single source of truth. Stage 07 additively stores actor/policy/workflow/schema bindings, hashes, budget reservation, counters, safe error category and timestamps. It never stores raw arguments, Safe DTO output, provider body, prompt, credentials or Authorization headers.
