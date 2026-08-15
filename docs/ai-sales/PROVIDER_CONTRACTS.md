# Provider-neutral contracts

## Adapter boundary

Domain orchestration depends on `AiProviderInterface`, never a vendor SDK or wire payload. The contract exposes `code`, fixed `route`, capability profile, support check, health check and `createResponse(AiProviderRequest)`.

Provider-neutral DTOs are:

- request/input/output items;
- tool call/result and citation;
- normalized usage and safe error;
- request requirements, capability/health/residency profiles;
- provider selection decision.

`AiProviderRequest` rejects contour `none`, invalid hashes, unbounded items, unsupported input types and timeouts outside the Stage 04 cap. It contains typed instruction and sanitized-data items, not Eloquent models, credentials, caller URL/headers or arbitrary prompt. `store()` is always `false`.

Provider-specific JSON is not represented above the adapter boundary. A future adapter must parse transient wire data into these DTOs and discard raw bodies unless a separately approved incident policy exists.

## Stage 04 fakes and Stage 05 Timeweb adapters

In default `fake_only` mode, `AiProviderRegistry` registers exactly one `FakeAiProviderInterface` for each selected route. Registered implementations are:

- `FakeLocalRuAiProvider`;
- `FakeExternalSanitizedAiProvider`;
- `FailingFakeAiProvider` for deterministic failure tests.

The fakes cover structured and normal output, function-call request, usage, timeout, 429, 5xx, schema mismatch, DLP/contour block and unavailable health. They create no HTTP request. The external fake independently rejects local-only payloads.

Stage 05 generalizes the registry allowlist to two marker contracts only: the existing fake marker and `TimewebAiProviderInterface`. Timeweb registration additionally requires `transport_mode=timeweb_synthetic_only`; the Unit run feature guard still blocks that mode. `TimewebLocalRuProvider` and `TimewebExternalSanitizedProvider` share a fixed transport but preserve route identity, keys, model allowlists and policy decisions.

The Timeweb adapters accept only `AiProviderRequest.syntheticOnly=true` with a repository fixture and a deterministic hash over the complete fixed synthetic input envelope. Chat/Responses wire objects are transient and normalize into the same Stage 04 DTOs. `previous_response_id` is never emitted.

`AiProviderRouter` accepts an already-hashed contour decision. It checks the contract profile, persisted capability evidence, provider health and exact local residency, then emits an auditable selection with `fallbackAllowed=false`. It rejects any request/selection/policy hash or contour mismatch.

## Explicit exclusions

Stage 05 adds only the Timeweb AI Gateway transport and guarded synthetic operational workflow. It adds no ProxyAPI, AITUNNEL, direct OpenAI/Foreign Gateway, vendor SDK, search transport, production runtime or provider failover. Keys remain outside Git and DB; all provider flags remain default-off.
