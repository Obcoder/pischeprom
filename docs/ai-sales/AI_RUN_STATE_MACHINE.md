# AI run state machine

## Lifecycle

`AiAgentRunStateMachine` is the only service that changes the run lifecycle and increments `lock_version` under a database row lock.

```text
queued -> preparing -> policy_check -> ready -> sent -> processing -> completed
                                                          \-> requires_action
```

From the applicable non-terminal state, a run can end as `cancelled`, `failed`, `budget_exceeded`, `blocked_by_policy`, `blocked_by_dlp`, `blocked_by_contour`, `residency_unverified` or `provider_unavailable`. Terminal states cannot transition again. `requires_action` contains only a hashed/redacted fake tool proposal; Stage 04 has no tool executor or side-effect continuation.

## Services and worker boundary

- `CreateAiAgentRun` validates permissions, definition/profile/contour consistency, flags, kill switches, prompt/schema hashes and caller idempotency.
- `PrepareAiAgentRun` reauthorizes the actor, builds `UnitSharedPublicProfile`, runs disclosure/contour/DLP/residency policy, stores audits and a Safe DTO hash, then moves to `ready`.
- `ExecuteAiAgentRunStep` reconstructs the DTO from the Unit ID, repeats authorization/policy/DLP/hash checks, verifies budgets/capability/health/residency and invokes one fake provider.
- `CompleteAiAgentRun` records normalized counters and terminates a successful run.
- `CancelAiAgentRun` cancels pending steps and prevents queued execution.

`ExecuteAiAgentRunJob` serializes only the integer run ID. It has one try, a uniqueness key, overlap protection and a bounded timeout. It never serializes an Eloquent model, Safe DTO, prompt or credential. The worker reloads the actor, Unit, context and definition; revoked permission, archived/paused context, changed lane/role, disabled definition, changed hashes, DNC drafting rule, expired wall clock or active kill switch blocks execution.

## Error normalization and retry policy

Fake timeout, rate limit, server and schema errors terminate as `failed`. Provider unavailability/capability errors terminate as `provider_unavailable`; DLP and contour errors retain their dedicated terminal status. Safe error codes/summaries contain no raw payload.

Stage 04 performs zero automatic retries and zero failovers. Both counters remain zero. In particular there is no `local_ru -> external_sanitized` transition. Cancellation, policy and budget decisions are checked before any subsequent provider use.

## Stored state

The run and step store immutable definition/policy/prompt/schema/input hashes plus safe summaries. Output persistence is limited to item types/counts, schema validity, provider selection hash/reason, verified capability codes and normalized usage. Provider output text and tool argument values are not stored.
