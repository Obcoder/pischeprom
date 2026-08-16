# AI tool execution security

## Pre-query binding

Every call is bound to run ID, step ID, actor, Unit, UnitBusinessContext, lane, role, purpose, audience, contour, tool code/version/schema hash, workflow code/version/hash, policy decision ID/hash, input hash, idempotency hash, lock version and bounded row/byte/time/cost reservation.

Before a handler query, the executor:

1. resolves tool and workflow from code;
2. validates strict input schema (`additionalProperties=false`);
3. verifies input/workflow/schema hashes and fixed workflow membership;
4. reloads run, step, actor, Unit, context and agent definition;
5. rechecks status, cancellation, expiry, Unit/context, lane/role and immutable snapshot;
6. rechecks actor permissions, feature flags and global/contour kill switches;
7. verifies purpose/audience/lane/role/contour allowlists and budgets;
8. verifies the persisted allow/redact policy record;
9. runs deterministic pre-query DLP.

Any mismatch fails closed before the handler. A disabled or proposal-only tool cannot execute.

## Post-query boundary

Handlers return only declared `SafeAiDto` instances. The executor looks up every field in `AiDataClassificationRegistry`; unclassified and secret fields block. It enforces classification/visibility ceilings, runs Stage 03 disclosure/sanitization for the current Unit context, validates the output schema, enforces string/row/byte/query/time caps and runs DLP again.

The external DTO is selected by the definition before the query. An internal/local DTO cannot be made external by post-hoc redaction. Local-only and opposite-lane definitions are rejected at the policy gate.

## Persistence

Allowed persisted values are identity references, code/version/hashes, classification counters, row/byte/query/redaction/duration counters, safe state/error codes and timestamps. `AiDataAccessLog` records source type/counts without values. Raw input/output, prompts, model bodies, credentials, HTTP headers, Eloquent serialization and exception payloads are absent.

The Stage 07 migration only extends `ai_tool_calls`; it is additive and reversible. It does not create a parallel tool-call table or alter historical Stage 03–05B migrations.

## Side effects

Executable Stage 07 tools are `read_only`. `crm.propose_entity_candidate` is metadata-only, disabled, proposal-only and human-review-required. No tool creates/links/merges Entity, creates a Sale/Purchase, sends email or mutates Unit canonical fields.
