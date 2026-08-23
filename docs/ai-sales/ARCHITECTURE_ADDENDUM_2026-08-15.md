# Архитектурное дополнение от 15.08.2026

Статус: принято. Документ адаптирован для репозитория после выполненного Stage 01.

## Приоритет

Если исторический `00_MASTER_ARCHITECTURE.md` противоречит этому дополнению, применяется этот документ. Исторический материал и commit Stage 01 `8f7b619812c6d92ba230980cebe8455f76f74bea` не переписываются.

Сохраняют силу:

- [CURRENT_STATE.md](CURRENT_STATE.md);
- [DATA_CLASSIFICATION_MAP.md](DATA_CLASSIFICATION_MAP.md);
- [AI_INTEGRATION_RISKS.md](AI_INTEGRATION_RISKS.md);
- [UNIT_DOMAIN_MAP.md](UNIT_DOMAIN_MAP.md);
- [ENTITY_DOMAIN_MAP.md](ENTITY_DOMAIN_MAP.md).

Точные delta-решения оформлены в [ADR-001-UNIT-FIRST.md](ADR-001-UNIT-FIRST.md), [ADR-002-AI-PROVIDER-ROUTER.md](ADR-002-AI-PROVIDER-ROUTER.md) и [ADR-003-LOCAL-TOOLS-AND-STATE.md](ADR-003-LOCAL-TOOLS-AND-STATE.md).

## 1. Unit-first

Канонические определения:

```text
Unit   = «Дело»: досье объекта рынка и aggregate root холодной работы.
Entity = конкретное юридическое или физическое лицо — участник сделок.
```

Unit может иметь рабочее или неточное имя, aliases, непроверенные observations, несколько sources, несколько Entity и одновременно sales/procurement роли. Он хранит историю исследования и не исчезает после установления реквизитов.

Entity хранит точную идентичность и юридические реквизиты. `Sale`, `Purchase`, `Invoice`, `Contract` и `Payment` концептуально принадлежат Entity. В текущем коде подтверждены `Sale`, `Purchase`, `Order`, `Check` и banking records; отдельных моделей Contract/Invoice пока нет.

Запрещено создавать новый долговечный домен:

```text
leads
lead_sources
lead_contacts
lead_events
lead_good_matches
```

Существующий `App\Models\Lead` — legacy runtime-модель, предшествующая архитектуре. Она не удаляется этим дополнением, но новые AI-компоненты от неё не зависят.

Термин «лид» допустим только как UI-представление:

- Unit в `sales` / `prospective_customer` context;
- stage;
- prospect score;
- список Units для менеджера.

После human-reviewed создания или привязки Entity Unit продолжает существовать. Транзакции показываются в его карточке через связанные Entity без копирования строк.

## 2. Provider Router

Собственный иностранный VPS больше не является prerequisite:

```text
Российский Laravel Core
  → локальные Policy Engine + DLP + Tool Registry/Executor
  → AiProviderRouter
      ├─ ProxyApiProvider — primary
      ├─ AiTunnelProvider — fallback
      ├─ FakeProvider — local/test
      └─ DirectForeignGatewayProvider — future optional
```

Российский Core остаётся Control Plane и единственным местом, где находятся:

- полная БД и Eloquent;
- Unit/Entity и transaction data;
- authorization/permissions;
- PII, документы, цены, маржа и связи контрагентов;
- SMTP, MAX, banking и provider secrets;
- локальные tools;
- окончательные write actions и mail queue.

ProxyAPI, AITUNNEL и upstream AI получают только минимальный sanitized payload. Они не получают:

- сетевой доступ к Laravel/MySQL/Redis;
- входящий application endpoint;
- SQL/ORM/filesystem/shell tools;
- cookies, sessions, `.env` или credentials;
- raw email/messages;
- customer и supplier data в одном запросе;
- право создавать Entity или отправлять email.

Российский посредник не понижает data classification. Локальная policy/DLP-проверка обязательна до исходящего HTTP.

## 3. Функциональный доступ вместо доступа к БД

«Полный доступ AI к информации приложения» означает функционально необходимый доступ через зарегистрированные typed tools:

```text
model tool call
→ local ToolRegistry resolves fixed name/version/schema
→ authorization checks actor, purpose, audience, Unit and context
→ Laravel query/action runs locally
→ output classification, minimization and DLP
→ allowlisted DTO returns to provider
```

Запрещены generic SQL, generic Eloquent serialization, arbitrary HTTP, filesystem, shell и регистрация tools моделью. Write service вызывается только после локальной validation и, где требуется, human approval.

## 4. Provider-neutral state

Provider state не является source of truth:

- `previous_response_id` нельзя использовать как каноническое состояние;
- provider Conversations/Threads не являются локальной историей run;
- каждый request строится из локальных sanitized structured steps;
- `store=false` передаётся, когда transport поддерживает параметр;
- background mode не используется в первом релизе;
- raw provider request/response по умолчанию не сохраняются.

Нормализованные типы:

- `AiProviderRequest`;
- `AiProviderResponse`;
- `AiProviderToolCall`;
- `AiProviderCitation`;
- `AiProviderUsage`;
- `AiProviderError`;
- `ProviderCapabilityProfile`.

Provider-specific JSON существует только внутри adapter.

## 5. Model routing

Domain logic использует логические profiles:

- `high_volume_extraction`;
- `standard_research`;
- `complex_research`;
- `validation`;
- `outreach_drafting`;
- `reply_triage`.

Фактические provider model IDs находятся в config. Начальная логическая рекомендация:

- GPT-5.6 Luna — extraction/classification/validation;
- GPT-5.6 Terra — research/matching/scoring/drafts;
- GPT-5.6 Sol — сложные неоднозначные исследования.

Это mapping, а не hardcode. Каждый run фиксирует provider, profile, фактический model ID, usage и cost.

## 6. Failover

Router может перейти с ProxyAPI на AITUNNEL только если одновременно:

- fallback включён feature flag;
- ошибка primary входит в allowlist transient errors;
- требуемые capabilities подтверждены probe/cache;
- budget допускает повтор;
- step является безопасно повторяемым;
- sanitized input воспроизводим из local state;
- на шаге ещё не было fallback.

Failover запрещён при:

- policy/DLP block;
- schema/HTTP 400;
- 401/403;
- invalid tool arguments;
- secret/PII detection;
- authorization или subject/context mismatch;
- application error;
- исчерпанном бюджете;
- уже выполненном необратимом действии.

Provider switch сохраняется как audit event и показывается в UI. Один step допускает не более одного перехода.

## 7. Data classification и compartment isolation

Labels:

- `public`;
- `internal`;
- `commercial_confidential`;
- `personal_data`;
- `secret`.

Unit scopes:

- `shared_public`;
- `sales_lane`;
- `procurement_lane`;
- `internal_only`.

Fail-closed правила:

- `secret` блокируется всегда;
- `personal_data` блокируется для внешнего AI по умолчанию;
- unclassified field блокируется;
- противоположный lane блокируется;
- raw email/message блокируется;
- legal requisites Entity требуют отдельного purpose, permission и sanitization.

Sales request не содержит supplier identities, contacts, purchase prices/volumes/discounts, margin или supplier correspondence. Procurement request зеркально не содержит customer identities, contacts, customer-specific prices/sales или customer correspondence.

## 8. Двухслойный DLP

```text
Layer 1: обязательный локальный DLP в Laravel.
Layer 2: provider-side masking/blocking как дополнительная страховка.
```

Для ProxyAPI начальный production mode — block request при PII/secret. Provider-side demasking не используется как основной workflow. Если обязательная внешняя masking capability недоступна, Router завершает запрос безопасной ошибкой; локальный DLP работает независимо.

## 9. Логирование и хранение

Локально сохраняются:

- run metadata и sanitized structured steps;
- policy/tool versions;
- safe summaries;
- usage/cost;
- provider request IDs;
- citations;
- redaction events без исходного значения;
- hashes/snapshots schemas и policies.

По умолчанию не сохраняются raw prompt, raw tool output, raw provider response, полный текст web pages, secrets и исходные PII markers.

У агрегаторов отключается payload logging. Staging и production используют разные RU-only secrets и budgets; ключи никогда не передаются frontend.

## 10. Web search

Первый релиз использует hosted `web_search` через Responses-compatible transport. Российский сервер не становится crawler и не загружает произвольные сайты.

Web content всегда считается untrusted data. Для каждого сохранённого факта требуется URL/evidence; citations нормализуются в UnitSource. Контент страницы не может менять system policy, purpose, audience, tools или разрешать Entity creation.

## 11. Outreach

Production default:

```text
OUTREACH_SENDING = approval_required + permission/legal gate
```

Публичный email не доказывает consent. AI создаёт draft, но не создаёт permission, не снимает suppression, не обходит approval и не отправляет письмо. Unsubscribe, complaint, reply stop condition и do-not-contact прекращают follow-up.

## 12. Неизменившиеся инварианты

- fail closed;
- local authorization, Policy Engine и DLP;
- отдельный audit;
- budget/step/tool limits;
- no direct email sending by model;
- no unrestricted SQL/filesystem/shell;
- no secret export;
- strict tool schemas;
- prompt-injection defense;
- citations for discovered facts;
- human boundary для юридически значимых действий;
- additive production-safe migrations;
- feature flags и kill switches.

Этап сверки является документационным: ни provider adapters, ни credentials, ни migrations этим документом не добавляются.
