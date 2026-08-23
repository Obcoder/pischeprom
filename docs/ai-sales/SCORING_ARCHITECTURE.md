# Explainable prospecting scoring architecture

Stage 10 adds three independent deterministic projections. `UnitProductMatch` owns Product relevance, `UnitGoodMatch` owns concrete Good fit, and `UnitBusinessContext` owns prospect priority. A dual-role Unit therefore has separate sales and procurement scores. There is no global Unit score and no Lead dependency.

The flow is:

```text
explicit bounded assembler
  -> immutable ScoringInput + canonical input/evidence hashes
  -> code-owned definition + pure deterministic calculator
  -> ScoreResult
  -> append-only snapshot + immutable factor rows
```

The definition registries are PHP code, not database settings. Each hash covers factors, caps, normalization order, bands, confidence rules, stale policy, allowed lane/roles and explanation templates. Unknown definitions, versions, levels, subject keys, signal keys, evidence fields or factor codes fail closed. Assemblers use explicit selects, row caps, distinct Product/transaction IDs and evidence metadata only. They never serialize an Eloquent graph or include raw page/provider/transaction/contact values.

Current definition identities:

| Definition | Version | SHA-256 |
|---|---:|---|
| `product_relevance.v1` | 1.0.0 | `7621c4672da16df6ad567771362a1961b1a50f3e935a711cf1425b12cdf85076` |
| `good_fit.v1` | 1.0.0 | `11a93072b6456587b4ea9508fdf9a97af72a71e5432d41d832dcd2924e1c26e9` |
| `prospect_priority.v1` | 1.0.0 | `e9222e350fb3ef1722a64a9d8f3f475b25e8f4dceea0d47029d9ed19ff1c8984` |

The API is behind `auth:sanctum`, verified-user middleware, the `ai-sales` rate limiter, feature flags, lane authorization and dedicated permissions. UI controls are convenience only; every state-changing operation is reauthorized server-side.
