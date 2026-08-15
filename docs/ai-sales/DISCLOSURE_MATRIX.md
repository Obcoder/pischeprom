# Disclosure matrix

Решение вычисляется как логическое AND всех измерений:

```text
registered field
× valid unit/context identity
× role/lane match
× purpose/lane match
× audience/role match
× classification rule
× visibility_scope match
× external_exportable
= ALLOW; любое несовпадение = BLOCK
```

## Purpose × audience × lane × classification × visibility

В таблице указаны максимальные допустимые классы. Конкретное поле всё равно должно присутствовать в code-owned registry.

| Purpose | Audience | Lane | Допустимые classification | Допустимые visibility scope | Примечание |
|---|---|---|---|---|---|
| `buyer_discovery` | `internal`, `customer`, `prospective_customer` | `sales` | `public` | `shared_public` | Supplier identity/cost/terms отсутствуют |
| `supplier_discovery` | `internal`, `supplier`, `prospective_supplier` | `procurement` | `public` | `shared_public` | Customer identity/price/terms отсутствуют |
| `unit_research` | `internal` либо audience, совпадающий с role | context lane | `public`; local-only explicit `internal` | `shared_public`; `internal_only` только local/internal | Context identity обязателен даже для shared fields |
| `contact_discovery` | matching audience | context lane | `public`; `personal_data` только local/internal | `shared_public` | Contact value считается personal data по умолчанию и не экспортируется |
| `product_matching` | matching audience | `sales` или `procurement` | `public`, allowlisted `commercial_confidential` | `shared_public` или текущий lane | Только summary/aggregate DTO |
| `prospect_scoring` | matching audience | context lane | `public` | `shared_public` | Transaction identities/rows не разрешены |
| `outreach_drafting` | customer audience | `sales` | `public`, allowlisted `commercial_confidential` | `shared_public`, `sales_lane` | Customer offer summary; procurement data BLOCK |
| `outreach_drafting` | supplier audience | `procurement` | `public`, allowlisted `commercial_confidential` | `shared_public`, `procurement_lane` | Aggregate demand; customer identity BLOCK |
| `reply_triage` | `internal` | context lane | только отдельно зарегистрированные summary fields | текущий lane или `internal_only` | Raw correspondence всегда BLOCK; в Stage 03 export DTO не зарегистрирован |
| `followup_recommendation` | `internal` | context lane | только отдельно зарегистрированные summary fields | текущий lane или `internal_only` | Raw correspondence всегда BLOCK; в Stage 03 export DTO не зарегистрирован |
| `sales_intelligence` | `internal`, customer audiences | `sales` | `public`, allowlisted `commercial_confidential` | `shared_public`, `sales_lane` | Aggregate supply/customer summary; supplier identities/costs BLOCK |
| `procurement_intelligence` | `internal`, supplier audiences | `procurement` | `public`, allowlisted `commercial_confidential` | `shared_public`, `procurement_lane` | Aggregate demand; customer identities/prices BLOCK |

## Classification × external target

| Classification | Local/internal | External AI |
|---|---|---|
| `public` | ALLOW только при registry/purpose/audience/scope match | То же + `external_exportable=true` |
| `internal` | ALLOW только explicit local rule | BLOCK для текущих operational DTO |
| `commercial_confidential` | ALLOW только summary/aggregate rule и lane match | ALLOW только explicit exportable summary/aggregate |
| `personal_data` | Только explicit internal rule | BLOCK по умолчанию |
| `secret` | BLOCK | BLOCK |
| unclassified | BLOCK | BLOCK |

## Visibility × lane/audience

| Scope | Sales/customer context | Procurement/supplier context | Internal audience |
|---|---|---|---|
| `shared_public` | ALLOW при explicit public classification | ALLOW при explicit public classification | ALLOW при registry match |
| `sales_lane` | ALLOW при purpose/audience match | BLOCK | Только с sales context |
| `procurement_lane` | BLOCK | ALLOW при purpose/audience match | Только с procurement context |
| `internal_only` | BLOCK external | BLOCK external | Local/internal только |

Customer/prospective-customer audience никогда не получает `procurement_lane`; supplier/prospective-supplier никогда не получает `sales_lane`. Для dual-role Unit dossier API сначала ограничивает contexts по permission, затем тем же набором context IDs ограничивает aliases, sources, observations и proposals. Secret records отбрасываются до resource serialization. Entity identities скрываются, если пользователь не авторизован на все активные sales/procurement lanes Unit.

Raw correspondence и credential material не имеют разрешающей строки: это unconditional BLOCK.
