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

## Stage 04 registry and fakes

`AiProviderRegistry` accepts only `FakeAiProviderInterface` and requires exactly one provider for each selected route. Registered implementations are:

- `FakeLocalRuAiProvider`;
- `FakeExternalSanitizedAiProvider`;
- `FailingFakeAiProvider` for deterministic failure tests.

The fakes cover structured and normal output, function-call request, usage, timeout, 429, 5xx, schema mismatch, DLP/contour block and unavailable health. They create no HTTP request. The external fake independently rejects local-only payloads.

`AiProviderRouter` accepts an already-hashed contour decision. It checks the contract profile, persisted capability evidence, fake health and exact local residency, then emits an auditable selection with `fallbackAllowed=false`. It rejects any request/selection/policy hash or contour mismatch.

## Explicit exclusions

Stage 04 has no Timeweb, ProxyAPI, AITUNNEL, OpenAI or Foreign Gateway adapter; no SDK, base URL, API key, HTTP client, search transport, billing integration or provider failover. Config may name future provider codes as inert architecture metadata only. Activating or probing a real provider belongs to a later, separately authorized stage.
