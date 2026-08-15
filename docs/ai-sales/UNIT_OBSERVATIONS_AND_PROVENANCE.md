# Unit observations, aliases и provenance

## Структуры

| Таблица | Содержимое и retention |
|---|---|
| `unit_sources` | Дедуплицированный source key, reference/URL, classification, visibility, timestamps проверки и human/system marker; физическое удаление запрещено |
| `unit_observations` | Неизменяющий canonical data факт с key, normalized value, summary, source, confidence, verification и rules/model version; физическое удаление запрещено |
| `unit_aliases` | Alias и normalized alias, type, context/source, confidence, verification, classification и scope |
| `unit_contact_context_links` | Provenance/scope link к ровно одному существующему `emails`, `telephones` или `uris` row; контактное значение не дублируется как новый contact domain |
| `unit_dossier_audit_events` | Append-only timeline с Unit name snapshot, actor, subject и отфильтрованными metadata |

Alias types: `trade_name`, `legal_hint`, `brand`, `domain_name`, `former_name`, `other`. Verification status для aliases/observations/contact links: `unverified`, `verified`, `contradicted`, `stale`.

## Инварианты provenance

- shared-public fact обязан иметь `data_classification=public`, `visibility_scope=shared_public` и не иметь lane context;
- `sales_lane`/`procurement_lane` запись обязана ссылаться на context того же lane;
- context и source должны принадлежать тому же `Unit`; context-bound source нельзя использовать в другом context;
- aliases нормализуются Unicode NFKC, lowercase и whitespace normalization, но допустимые совпадения между разными source/context не уничтожаются;
- source/evidence остаётся после review или изменения canonical значения;
- raw HTML, mail body, headers и attachments не являются observation payload и не экспортируются.

## Promotion

Создание observation никогда не вызывает `Unit::update()`. Противоречивые observations сохраняются одновременно. Review отдельно устанавливает status/reviewer/time.

Stage 03 разрешает только явную promotion verified observation с key `unit.name`. Она требует `ai_sales.observation.promote`, запрещает secret/personal data, выполняется human action и пишет append-only audit event с before/after. Другие canonical fields fail closed до появления отдельного application action.

## Public profile

`UnitSharedPublicProfileQuery` выбирает поля explicit queries и caps. В profile попадают только verified public/shared aliases и observations с code-owned allowlist business-fact keys; произвольный key вроде `mail.body` блокируется даже при ошибочной public classification. URI попадает только через verified `unit_contact_context_links` с public/shared classification; простая legacy-связь `unit_uri` сама по себе не делает URI публичным.

Запрос отключает default eager-loaded relations `Unit`. Entity, transactions, mail, files и неограниченный relation graph не загружаются.
