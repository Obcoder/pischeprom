# Data classification policy

## Code-owned registry

`AiDataClassificationRegistry` регистрирует каждое поле конкретного Safe DTO вместе с classification, visibility scope, allowed purposes/audiences, external exportability, redaction rule и justification. Поле, отсутствующее в registry, получает `unclassified_field` и блокируется.

Classification codes:

| Code | Default rule |
|---|---|
| `public` | Разрешение возможно только при явном registry rule и совпавших context dimensions |
| `internal` | Локальный/internal use; внешний export только если rule явно разрешает, текущие operational context fields не разрешают |
| `commercial_confidential` | Только allowlisted summary/aggregate, совпадающий lane и audience |
| `personal_data` | External AI заблокирован по умолчанию независимо от purpose |
| `secret` | BLOCK всегда |

Visibility scopes: `shared_public`, `sales_lane`, `procurement_lane`, `internal_only`. Shared public не означает auto-public: record-level observation/alias/contact metadata и registry field должны явно совпасть.

## Детерминированный порядок решения

`AiDisclosurePolicy` без LLM последовательно проверяет:

1. положительные `unit_id` и `unit_business_context_id`;
2. совместимость role/lane;
3. совместимость audience и context role/lane;
4. совместимость purpose/lane;
5. unconditional secret block;
6. external personal-data block;
7. explicit external exportability;
8. allowlists purpose и audience;
9. visibility compartment;
10. customer/procurement и supplier/sales cross-lane denial.

Любое несовпадение возвращает typed `AiPolicyDecision` denial. `AiFieldAuthorizationService` переводит denial в `PolicyViolation` с error code. `AiContextSanitizer` проверяет каждое поле, запрещает objects/resources и применяет DTO byte cap после JSON encoding.

## Всегда блокируется

- passwords, tokens, API keys, Authorization/Bearer values;
- cookies, sessions, private keys, secrets, `.env`, remember/2FA secrets;
- raw correspondence body/HTML/headers/attachments;
- произвольные model attributes, appended attributes и lazy relations;
- Entity banking/raw registry/transaction rows без отдельного Safe DTO;
- arbitrary SQL, filesystem, shell, arbitrary HTTP и generic database query как источник AI context.

Stage 03 не содержит AI provider, SDK, key, HTTP client или runtime capability matrix. Policy и sanitizer являются локальной deterministic boundary для будущего Stage 04.

## Safe DTO

Allowlisted DTO: `PublicGoodSummary`, `CustomerOfferSummary`, `UnitSharedPublicProfile`, `UnitBusinessContextSummary`, `SanitizedEntityLegalSummary`, `AggregateDemandSummary`, `AggregateSupplySummary`, `PublicBusinessContactSummary`.

DTO принимают только scalars/bounded arrays, нормализуют длину строк, ограничивают row count и объявляют `maxBytes()`. Registry identifiers дополнительно маскируются sanitizer. DTO не принимают Eloquent model, не вызывают `Model::toArray()`, не сериализуют hidden/appended attributes и не инициируют lazy loading.
