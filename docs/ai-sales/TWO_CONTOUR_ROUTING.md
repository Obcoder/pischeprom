# Two-contour routing

## Contours and deterministic decision

`AiProcessingContour` has exactly `none`, `local_ru` and `external_sanitized`. `none` is a blocking result, never a provider route. `AiProcessingRouteDecision` binds purpose, audience, lane, role, Unit/context, classification/visibility summaries, contour, reason, redaction/review state and policy versions into a deterministic SHA-256 decision hash.

Routing order is fixed:

1. validate current actor, Unit and active `UnitBusinessContext`;
2. bind the code-owned task profile to exactly one required contour;
3. enumerate every Safe DTO field through `AiDataClassificationRegistry`;
4. re-run Stage 03 disclosure authorization and sanitizer;
5. run deterministic credential/PII DLP over the bounded array;
6. require current residency evidence for `local_ru`;
7. require persisted, evidence-backed model capabilities;
8. select the single approved provider for the already-decided route;
9. verify the policy and Safe DTO hashes again immediately before execution.

The client cannot request another contour. `AiProviderRouter` cannot change the decision contour and every `ProviderSelectionDecision` has `fallbackAllowed=false`. A local failure returns `provider_unavailable`; it never invokes the external route.

## Data matrix

This table is an upper bound. The Stage 03 purpose/audience/lane/visibility rule must also allow every field.

| Data | `local_ru` | `external_sanitized` |
|---|---|---|
| `public` | explicit registry allow | explicit registry allow + `externalExportable=true` |
| `internal` | explicit local/internal allow only | BLOCK |
| `commercial_confidential` | explicit lane-safe summary only | explicit exportable summary/aggregate only |
| `personal_data` | explicit local/internal allow only | BLOCK by default |
| `secret` / credentials / `.env` | BLOCK | BLOCK |
| unclassified field | BLOCK | BLOCK |
| raw correspondence | BLOCK | BLOCK |
| opposite-lane data | BLOCK | BLOCK |

`local_ru` is therefore not blanket access. It retains Stage 03 role/lane, purpose, audience, visibility and Safe DTO restrictions.

## Task profiles

Code-owned task profiles define the minimum contour and logical model profile. Current synthetic definitions use:

| Definition | Task profile | Contour | Model mapping |
|---|---|---|---|
| `unit_internal_summary_synthetic:1` | `internal_dossier_summary` | `local_ru` | `fake-local-ru-v1` |
| `unit_public_research_synthetic:1` | `public_company_research` | `external_sanitized` | `fake-external-sanitized-v1` |

The external fake refuses a request marked `containsLocalOnlyData`, even if upstream policy is bypassed in a test. The local fake still relies on upstream disclosure/DLP and a current human residency attestation.

## Residency and capabilities

Local authorization matches the exact `provider_code + provider_route + model_id`. A usable residency row must be `verified`, declare `local_ru` and country `RU`, include `verified_by`/`verified_at`, and have a future `expires_at`. Missing, pending, stale, suspended or expired evidence yields `residency_unverified`.

Every requested capability is also matched to an `ai_provider_capabilities` row. Unknown/documented-only/suspended/expired rows, missing evidence hashes, insufficient token limits or contour mismatch yield `provider_capability_unverified`. Synthetic fake capability rows are the only seeded provider evidence in Stage 04.

Stage 05 does not seed Timeweb capability or residency evidence. A Timeweb exact model additionally requires current `ai_provider_models.active_in_inventory`, `support_state=supported`, a verified lifecycle and, for `local_ru`, short-lived human RU evidence. The synthetic-only adapter never falls back to the opposite route and is not reachable from the Unit-derived runtime.
