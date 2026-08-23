# Search Provider Contracts

Search provider отделён от LLM provider и AI contour router.

## Contracts

- `SearchProviderInterface` — `code()`, supported `profiles()`, `search()`;
- `SearchProviderRequest` — Job/query identity, fixed profile, reviewed query, locale/geography, bounded max results, request hash;
- `SearchProviderResponse` — provider/profile, normalized results, usage, optional safe request ID;
- `SearchProviderResult` — rank, bounded title/snippet, public URL/domain, type;
- `SearchProviderUsage` — request/result counts, optional token counters, estimated RUB cost;
- `SearchProviderRegistry` — exact provider lookup, duplicate registration blocked;
- `SearchProviderException` — category and safe code only.

## Registered implementations

`existing_yandex` production adapter:

```text
ExistingYandexSearchProviderAdapter
  → YandexSearchService
  → existing server-side config/auth/endpoint/parser
```

`FakeSearchProvider` supplies only repository-owned fictional results for automated tests and synthetic CLI. It performs no HTTP and uses the same provider code/profile contract so orchestration remains identical.

## Execution guarantees

- provider code is server-owned: `existing_yandex`;
- profile is server-owned: `prospecting_b2b_discovery`;
- Job, plan and Product-scope hashes are revalidated;
- query job payload contains IDs only;
- queue tries = 1 and no automatic retry/fallback;
- Job row lock reserves request budget before transport;
- provider/profile response mismatch fails closed;
- only normalized fields and safe request ID are persisted;
- raw JSON/XML, headers, auth data and exception bodies are not persisted.

Search does not call `AiProviderRegistry`, cannot choose AI contours, and cannot invoke native tools.
