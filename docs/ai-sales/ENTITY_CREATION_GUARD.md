# Entity create/link guard

## Boundary

Stage 03 вводит review object `entity_candidate_proposals` и контракт `EntityCreateLinkGuard`. Proposal хранит Unit/context, отдельное действие `create` или `link_existing`, evidence summary, allowlisted proposed legal attributes, duplicate candidates и immutable review state.

Создание proposal:

- требует authenticated/verified user, `ai_sales.entity.propose`, view permission для lane и policies Unit/context;
- проверяет принадлежность context к Unit;
- требует `ai_sales.classifications.view_internal` для proposal `link_existing`, потому что legacy Entity identities не имеют context lane metadata;
- принимает только allowlist `full_name`, `entity_classification_id`, `INN`, `KPP`, `OGRN`, `legal_address`, `country_id`;
- выполняет deterministic duplicate lookup по name/INN/OGRN;
- пишет audit event;
- не вызывает `Entity::create()`, `attach()`, merge или move relations.

## Human review actions

`create` и `link_existing` являются разными guard methods и permissions:

| Действие | Permission | Проверки |
|---|---|---|
| Proposal | `ai_sales.entity.propose` | Unit/context/lane, input allowlist, evidence |
| Link-existing proposal identity | `ai_sales.classifications.view_internal` | Entity name/ID и duplicate IDs не возвращаются обычному proposer |
| Create review | `ai_sales.entity.create` | Active human, reviewable proposal, action match, required name, повторный duplicate check |
| Link review | `ai_sales.entity.link` | Active human, reviewable proposal, action match, exact reviewed Entity ID |
| Merge/move | `ai_sales.entity.merge` | Зарезервировано как отдельная permission; Stage 03 не реализует mutation |

Guard только разрешает или отклоняет boundary; он намеренно не мутирует Entity. Полноценный final handler должен отдельно проверить обязательные реквизиты выбранного Entity type, optimistic `lock_version`, повторить duplicate check, выполнить одну human mutation и записать audit. До появления такого handler UI показывает только proposal flow.

Link Entity не удаляет Unit/context и не перемещает транзакции. Existing legacy Entity CRUD не является новой AI boundary и Stage 03 его не переписывает.
