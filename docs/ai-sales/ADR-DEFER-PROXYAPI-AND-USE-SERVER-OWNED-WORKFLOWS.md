# ADR: defer ProxyAPI and use server-owned workflows

- Status: accepted for Stage 07
- Date: 2026-08-16
- Owner decision: ProxyAPI evaluation is deferred with Stage 06

## Context

Stage 05B established bounded Timeweb synthetic evidence but did not establish production approval, contractual residency/ZDR or native tool support for the chosen models. Adding another gateway now would enlarge credential, routing, capability and data-processing surface without enabling the safe business path required by Stage 07.

## Decision

Stage 07 adds no ProxyAPI, AITUNNEL or direct OpenAI adapter. It uses code-owned deterministic Laravel workflows and fixed read-only handlers. Provider-native tools remain disabled. The only executable workflow is development/test synthetic and uses the existing fake provider with no HTTP.

There is no provider retry or failover, especially no automatic `local_ru -> external_sanitized` transition. Business/live workflows remain disabled until a later explicitly approved stage supplies a purpose-specific plan, evidence, permissions and acceptance tests.

## Consequences

- Tool schemas, handlers, order, contour and budgets are reviewable source code.
- Browser, untrusted content and model output cannot add or reorder actions.
- DLP and authorization run before and after each handler query.
- Timeweb Luna can later consume a prebuilt external Safe DTO without native tools if separately approved.
- Local GPT OSS workflows requiring strict schema/native tools remain blocked at the current evidence ceiling.
- Stage 08 discovery, matching, outreach and autonomous campaign work is not started.
