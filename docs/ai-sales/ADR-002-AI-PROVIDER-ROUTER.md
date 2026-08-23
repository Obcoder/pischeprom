# ADR-002: Provider-neutral AI Router

- Статус: принято
- Дата: 2026-08-15
- Область: AI transport / trust boundary

## Контекст

Историческая архитектура рассматривала собственный иностранный AI Gateway/VPS как обязательный Compute Plane. Текущий релиз должен работать через российские AI API aggregators без предоставления им доступа к Core.

Нужны primary/fallback, но домен не должен зависеть от provider JSON, server-side conversation state или одного model ID.

## Решение

```text
AiProviderInterface
├─ FakeProvider
├─ ProxyApiProvider                  primary
├─ AiTunnelProvider                  fallback
└─ DirectForeignGatewayProvider      future optional
```

Выбор выполняет локальный `AiProviderRouter`. Собственный foreign VPS, SSH key и Timeweb deployment не являются prerequisite.

Российский Laravel:

- остаётся Control Plane;
- строит request из local sanitized state;
- применяет authorization, Policy Engine и DLP;
- исполняет все tools;
- хранит usage/audit/citations;
- выполняет все writes и mail actions.

Provider получает только outbound sanitized DTO и не вызывает Core inbound.

## Контракт

Domain видит только:

- `AiProviderRequest`;
- `AiProviderResponse`;
- `AiProviderToolCall`;
- `AiProviderCitation`;
- `AiProviderUsage`;
- `AiProviderError`;
- `ProviderCapabilityProfile`.

Adapters переводят эти типы в provider-specific wire format. SDK не является частью domain contract; Stage 02 не добавляет SDK или HTTP transport.

## Primary и fallback

Primary: ProxyAPI. Fallback: AITUNNEL. FakeProvider обязателен для local/test. DirectForeignGatewayProvider может появиться позже без изменения domain services.

Fallback требует:

- enabled feature flag;
- transient allowlisted error;
- capability match;
- budget reserve;
- retry-safe step;
- reproducible sanitized input;
- отсутствия previous fallback на step.

Нельзя fallback-ить policy/DLP/auth/schema errors, invalid tool args, 401/403, application bugs, exhausted budget и необратимые actions.

## Capabilities

Router проверяет профиль capability перед request:

- Responses-compatible structured output;
- hosted web search/citations, если нужен;
- tool calling, если нужен;
- `store=false` support;
- provider-side PII/secret blocking для production ProxyAPI;
- model profile availability.

Отсутствующая обязательная capability завершает step fail-closed.

## Models

Domain выбирает logical profile, не model ID:

- high_volume_extraction;
- standard_research;
- complex_research;
- validation;
- outreach_drafting;
- reply_triage.

Config mapping version фиксируется в run/audit.

## Secrets и logging

- aggregator keys — RU-only secrets;
- staging/production keys и budgets разделены;
- keys отсутствуют в frontend, DB payload и logs;
- aggregator payload logging выключен;
- raw requests/responses локально по умолчанию не сохраняются;
- сохраняется provider request ID и safe metadata.

## Последствия

Положительные:

- нет обязательной иностранной инфраструктуры;
- provider portability;
- deterministic local failover;
- единая policy/tool boundary;
- тестирование через FakeProvider.

Стоимость:

- нужно поддерживать capability matrix и нормализацию errors/usage/citations;
- нельзя полагаться на provider conversation continuity;
- failover сложнее обычного HTTP retry;
- Router и adapters требуют contract tests.

## Отклонённые альтернативы

### Обязательный foreign VPS

Отклонён как prerequisite; остаётся optional future adapter.

### Прямой ProxyAPI client в domain services

Отклонён из-за vendor lock-in и невозможности безопасного fallback.

### Безусловный retry через AITUNNEL

Отклонён: может повторить unsafe/irreversible step и скрыть policy error.

### Provider-side DLP как единственная защита

Отклонён: local DLP обязателен независимо от aggregator.

## Supersession

ADR заменяет противоречащие положения старого `00_MASTER_ARCHITECTURE.md` и provider section [IMPLEMENTATION_DECISIONS.md](IMPLEMENTATION_DECISIONS.md). Domain/Unit решения Stage 01 сохраняются.
