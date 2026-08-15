# Сверка выполненного baseline 00–01

Дата сверки: 2026-08-15.

## Исходная точка

| Параметр | Значение |
|---|---|
| Ветка | `feature/ai-sales-agents` |
| Base до Stage 01 | `af839e447ef4a2aa80608d4a9a1ac727800d8bdc` |
| Commit Stage 01 | `8f7b619812c6d92ba230980cebe8455f76f74bea` |
| Subject | `chore(ai-sales): audit Unit Entity architecture and security baseline` |
| Commit time | `2026-08-15T15:00:05+03:00` |
| Git status перед Stage 02 | clean |

Git tree доказывает, что Stage 01 добавил только восемь Markdown-файлов: 1331 строка, без runtime-кода, migrations, config или tests.

## Файлы Stage 01

- [AI_INTEGRATION_RISKS.md](AI_INTEGRATION_RISKS.md);
- [BASELINE_COMMANDS.md](BASELINE_COMMANDS.md);
- [CURRENT_STATE.md](CURRENT_STATE.md);
- [DATA_CLASSIFICATION_MAP.md](DATA_CLASSIFICATION_MAP.md);
- [ENTITY_DOMAIN_MAP.md](ENTITY_DOMAIN_MAP.md);
- [IMPLEMENTATION_DECISIONS.md](IMPLEMENTATION_DECISIONS.md);
- [UNIT_DOMAIN_MAP.md](UNIT_DOMAIN_MAP.md);
- [UNIT_ENTITY_RELATIONSHIP.md](UNIT_ENTITY_RELATIONSHIP.md).

Эти файлы не пересоздаются и не переписываются. Stage 02 добавляет delta-docs и ADR.

## Что не повторяется

- branch creation и полный stack/deployment inventory;
- полный route inventory;
- полный migration baseline;
- массовый аудит integrations/secret storage;
- полные baseline failures Pint/Node/Artisan memory;
- первичная карта всех data fields;
- первичное доказательство M:N Unit↔Entity.

Безопасные команды Stage 01 используются только как smoke reference.

## Выводы Stage 01, которые сохраняются

1. `App\Models\Unit` / `units` фактически подходит как dossier aggregate root.
2. `App\Models\Entity` / `entities` остаётся transaction owner.
3. `entity_unit` реализует many-to-many в обе стороны и не имеет composite unique.
4. Sales/Purchases нельзя копировать в Unit; нужен context-aware read model.
5. Existing `Lead` — legacy subsystem, не основа нового AI domain.
6. Unit/Entity/mail routes не имеют достаточного auth/permission boundary.
7. Mixed-role Unit создаёт cross-compartment risk.
8. Нет typed aliases, sources, observations, business contexts и unified events.
9. Entity creation должна оставаться human-controlled.
10. Data classification map и risk register остаются baseline для fail-closed policy.
11. `App\Domain\AiSales`, `App\Jobs\AiSales`, `App\Http\*\AiSales` и `resources/js/Components/Unit/AiSales` пригодны как корневые namespaces/directories.
12. Existing AiPriceLists patterns полезны как примеры contracts/fakes/DTO/validation, но его Yandex credentials и policies не переиспользуются неявно.

## Дополнения и supersession

| Старое/неполное решение | Новое решение |
|---|---|
| Foreign AI Gateway/VPS как обязательный Compute Plane | `AiProviderRouter` в российском Laravel; ProxyAPI primary, AITUNNEL fallback |
| Иностранный SSH key/Timeweb deployment как prerequisite | не требуется текущему релизу; direct foreign provider остаётся future optional |
| Общий `AiGateway` contract из Stage 01 | `AiProviderInterface` + Router + нормализованные provider DTO |
| `AiRequest`/`AiStructuredResult` | `AiProviderRequest`/`AiProviderResponse` и отдельные ToolCall/Citation/Usage/Error types |
| Возможная опора на server-side provider state | local sanitized steps — source of truth; `previous_response_id` не каноничен |
| Provider failover как обычный retry | только allowlisted transient errors, capability/budget/idempotency checks, максимум один fallback |
| Внешний masking как достаточный | обязательный local DLP + provider-side block как второй слой |
| Model profile `standard_reasoning` из старого 00 | `standard_research`; добавлены drafting/triage profiles |
| Stage 01 context proposal только sales/procurement | V2 сохраняет обязательные sales/procurement compartments и допускает lanes logistics/service/internal из исходного Unit model |
| Stage 01 рекомендовал runtime-схему как следующий Stage 02 | фактический Stage 02 — docs-only reconciliation; runtime переносится в Stage 03+ |

Stage 01 не утверждал готового foreign transport, поэтому runtime-коллизии нет: supersession касается только будущего design.

## Пригодность документов Stage 01

### DATA_CLASSIFICATION_MAP

Уже покрыты Unit, Entity, contacts, locations, Goods/Product/prices, Sales/Purchases/Orders/Checks/banking, mail/sendings/calls/chats, legacy Lead, mailing compliance, users/secrets и будущие AI logs. Дополнение усиливает правила:

- unclassified блокируется;
- external PII блокируется по умолчанию;
- raw messages не экспортируются;
- provider payload/state наследует classification входов;
- opposite lane блокируется до ContextBuilder.

### AI_INTEGRATION_RISKS

Все R-01–R-24 сохраняются. К ним добавляются router-specific risks:

- blind fallback;
- provider capability mismatch;
- payload logging у агрегатора;
- provider-state lock-in;
- небезопасный demasking;
- повтор необратимого tool action.

### IMPLEMENTATION_DECISIONS

Сохраняются Unit-first services, typed transaction queries, human Entity proposal, context attribution mail, permissions и Unit UI entry. Provider contract и context conventions заменены [IMPLEMENTATION_DECISIONS_V2.md](IMPLEMENTATION_DECISIONS_V2.md).

### BASELINE_COMMANDS

Сохраняются `git diff --check`, targeted route list, `migrate:status`, Composer validation и PHPUnit с явным memory limit. Stage 02 не повторяет Pint/full frontend build, потому что runtime не меняется и failures уже документированы.

## Lead terminology reconciliation

Исполненный 00 уже запрещал новый Lead-domain. Разрыв находится в legacy runtime, где `App\Models\Lead` используется telephony/mail dashboard flows:

| Legacy понятие | Каноническое новое понятие |
|---|---|
| Lead | Unit в sales/prospective_customer context |
| Lead source | UnitSource/evidence |
| Lead contact | UnitContact/channel |
| Lead good match | Unit↔Good match в конкретном context |
| Lead score | Unit prospect score snapshot |
| Lead event | Unit/context activity event |
| Lead campaign member | UnitBusinessContext membership |
| Lead conversion | human-reviewed Entity create/link; Unit остаётся |

Legacy rows не мигрируются и не удаляются на Stage 02.

## Проверки Stage 02

| Проверка | Результат |
|---|---|
| Relative Markdown links | PASS; все ссылки новых docs разрешаются |
| Code fences | PASS; сбалансированы |
| Markdown lint | NOT CONFIGURED; markdownlint/remark/lychee отсутствуют |
| `composer validate --strict` | PASS |
| Targeted Unit `route:list -v` | PASS; 31 routes, baseline `api`-only middleware подтверждён |
| `php artisan migrate:status` | PASS read-only; те же 16 pending, ничего не запускалось |
| `php artisan schedule:list` | PASS; scheduler не запускался |
| `php -d memory_limit=512M vendor/bin/phpunit` | PASS; 384 tests, 2667 assertions, 5 skipped, 159 MiB |
| Stage 01 files unchanged | PASS |
| Diff scope | PASS; только восемь новых `docs/ai-sales/*.md` |
| Migrations/runtime/config/keys | отсутствуют |
| External AI HTTP | не выполнялся |

Unrelated baseline failures Pint, Node 16/Vite и default Artisan memory не исправлялись и не повторялись.

## Переход к Stage 03

Stage 03 должен начинаться от commit Stage 02 и:

1. сохранить default-off feature flags и отсутствие real egress;
2. создать provider-neutral contracts, Router и FakeProvider с tests;
3. создать local policy/DLP/tool boundaries до ProxyAPI/AITUNNEL transports;
4. зафиксировать local structured run state без raw payload;
5. добавить auth/permissions и negative tests для новых routes;
6. только additive schema после отдельного migration plan;
7. не подключать existing legacy Lead и Beeline Entity auto-create к AI.

Stage 02 не реализует перечисленное и не меняет production behavior.
