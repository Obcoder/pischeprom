# Unit business contexts

## Граница домена

`Unit` остаётся центральным досье организации или площадки. `Entity` остаётся подтверждённым юридическим или физическим лицом. Связь `Unit ↔ Entity` не конвертирует и не удаляет `Unit`, а `converted_entity_linked` является только стадией context.

Продажи, закупки, заказы и другие транзакции по-прежнему принадлежат `Entity`. Stage 03 не копирует их в `Unit`, не создаёт новый `Lead` и не использует legacy `Lead` как source of truth.

## Новые структуры

| Таблица | Назначение |
|---|---|
| `market_roles` | Нормализованный справочник business roles с immutable `code` и отдельно редактируемыми display names/translations |
| `market_role_unit` | M:N roles для `Unit`; удаление заменено архивированием assignment |
| `unit_business_contexts` | Независимые dossier contexts с `unit_id`, `lane`, `role_code`, stage/status, owner/reviewer, confidence и provenance marker |

Справочник содержит system codes: `customer`, `supplier`, `prospective_customer`, `prospective_supplier`, `manufacturer`, `carrier`, `service_provider`, `other`. System/используемая роль физически не удаляется; `code` не редактируется.

## Lanes и совместимость ролей

| Lane | Основные roles |
|---|---|
| `sales` | `customer`, `prospective_customer`, `manufacturer` |
| `procurement` | `supplier`, `prospective_supplier`, `manufacturer` |
| `logistics` | `carrier` |
| `service` | `service_provider` |
| `internal` | `other` |

`Unit` может одновременно иметь, например, `sales/prospective_customer` и `procurement/supplier`. Identity context — `(unit_id, lane, role_code)`. Context принадлежит ровно одному `Unit`; context-aware операции обязаны передавать его ID и проверять принадлежность сервером.

Legacy-флаги `units.is_customer` и `units.is_supplier` сохранены для совместимости. Ручное добавление соответствующего context может только включить флаг; удаление/архивирование context не сбрасывает его автоматически. Каноническим многоролевым представлением для новой функциональности являются `market_role_unit` и `unit_business_contexts`.

## Stage и status

Stage — code-owned enum с UI label: `new`, `researching`, `qualified`, `review_required`, `approved`, `rejected`, `converted_entity_linked`, `active`, `inactive`, `do_not_contact`, `archived`.

Status: `active`, `paused`, `closed`, `archived`. Stage/status `archived` устанавливает `archived_at`; физическое удаление context запрещено моделью. Owner, reviewer и primary good являются nullable FK, а `primary_segment` оставлен явным snapshot-полем.

## Переиспользованные структуры

Stage 03 не меняет назначения существующих `units`, `entities`, `entity_unit`, `sales`, `purchases`, `emails`, `telephones`, `uris`, `locations`, `entity_classifications`, `goods`, `products`, `quotations`, `files` и communication-моделей. Контактные значения не дублируются: `unit_contact_context_links` хранит только context/provenance metadata и FK на существующий `email`, `telephone` или `uri`.

## Authorization

Новые API находятся под `auth:sanctum`, `verified` и rate limit. Policies отдельно проверяют просмотр dossier, управление roles/contexts, текущий и целевой lane каждого изменения. Context-bound observation review повторно проверяет lane его context. Vue capabilities нужны только для UX и не заменяют server-side policy.

Транзакционные aggregates строятся явными запросами через distinct linked Entity IDs. Raw transaction rows не становятся полями context и не попадают в Safe DTO.
