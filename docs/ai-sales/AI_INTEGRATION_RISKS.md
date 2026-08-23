# Риски интеграции AI Sales Agents

## Блокирующие условия

До доступа внешнего AI к реальным данным должны появиться:

1. auth/permission boundary на новых маршрутах и инвентаризация открытых legacy routes;
2. `UnitBusinessContext` и запрет cross-lane reads;
3. policy-driven DTO export вместо сериализации Eloquent;
4. human approval для Entity, merge, отправки писем и любых транзакционных действий;
5. provenance/verification для web observations;
6. prompt-injection boundary для web, mail и attachments;
7. quotas, idempotency, circuit breakers и audit.

## Risk register

| ID | Severity | Факт/сценарий | Последствие | Обязательный контроль |
|---|---|---|---|---|
| R-01 | critical | Unit, Entity, Unit files/mail и MailMessage API имеют только `api`; Unit page только `web` | неаутентифицированное чтение/изменение/отправка | auth, permissions, policies, negative route tests; сначала инвентаризировать клиентов legacy API |
| R-02 | critical | Новый агент может создать свою Lead/card/domain рядом с существующим `Lead` и Unit | расхождение lifecycle, двойные контакты и действия | Unit — единственный dossier root; legacy Lead adapter/freeze; запрет новой Lead table/model |
| R-03 | critical | Beeline уже может автоматически создавать placeholder Entity по телефону | AI discovery станет юридическим лицом без проверки | не давать AI этот service; EntityCreationProposal + human permission + audit |
| R-04 | critical | Mixed-role Unit использует общие contacts/files/mail без context | раскрытие закупочных цен/писем sales-агенту и наоборот | context FK на новых данных, deny-by-default lane policies, separate summaries |
| R-05 | critical | Unit mail send выполняется непосредственно, без approval/compliance gate | ошибочная/неправомерная отправка, репутационный ущерб | AI создаёт только draft; recipient eligibility, consent/suppression, reviewer и idempotent send |
| R-06 | high | Attachment storage path принимается без жёсткой привязки к префиксу Unit | прикрепление чужого файла/утечка | canonicalize + require Unit-owned object metadata; signed access; no arbitrary path |
| R-07 | high | Email inference связывает сообщение со всеми Unit общего контакта | неправильная attribution и cross-dossier disclosure | saved context attribution с confidence; ambiguity queue; inference только fallback |
| R-08 | high | `entity_unit` и contact pivots не имеют composite unique | duplicate links, удвоенные суммы/counts и повторные действия | production duplicate audit, безопасный dedupe, unique indexes в отдельной миграции |
| R-09 | high | `units.name` и Entity requisites не уникальны | duplicate Unit/Entity и split history | deterministic Unit-first resolver, identity evidence, manual merge |
| R-10 | high | web/mail/Avito/attachments — недоверенный текст | prompt injection, tool misuse, exfiltration | content/data separation, tool allowlist, no instructions from content, sanitization and egress guard |
| R-11 | high | Eloquent models eager-load широкие relation graphs; `dadata_raw` не hidden | избыточный PII/confidential export | dedicated Resources/DTO, field-level policy, redaction before prompt/log |
| R-12 | high | Sales/Purchases доступны через общую Entity | агент может увидеть противоположный lane | separate typed query services and permissions; transaction IDs distinct; no generic Entity traversal |
| R-13 | high | Unit/Entity hard delete и cascade links | потеря evidence/audit, orphaned AI records | archive/SoftDeletes plan, restrict deletes, immutable audit; не менять без data migration plan |
| R-14 | high | Existing permissions не покрывают Unit/Entity/mail/sales/procurement | невозможно выразить least privilege | добавить scoped permissions/roles и policies до AI routes |
| R-15 | high | Tracking IP/UA, raw headers, calls и chats содержат PII | незаконный/избыточный экспорт и retention | classification enforcement, masking, purpose limitation, retention |
| R-16 | high | Agents могут повторять поиск/парсинг/письма после retry | runaway cost, duplicate sends и provider bans | per-run budget, per-Unit quota, idempotency keys, max steps, retry caps, circuit breaker |
| R-17 | high | AI output может предложить цену/условия из confidential formulas/margins | утечка маржи и неверное коммерческое обещание | published-price tool only; approvals; prohibit formula/cost export |
| R-18 | medium | `leads.unit_id` и `entity_id` nullable/независимы | legacy запись может связать несовместимые объекты | consistency report, compatibility adapter, no destructive rewrite |
| R-19 | medium | supplier flag, pipeline stage и generic stage могут расходиться | неверный статус mixed-role Unit | context as source of truth; compatibility projection and repair report |
| R-20 | medium | Unit files не имеют DB metadata/version/classification | невозможно безопасно определить владельца и уровень | document metadata registry, checksum, context, source, retention |
| R-21 | medium | 16 migrations pending локально | код/schema assumptions могут различаться по окружениям | deployment preflight, schema capability checks, не запускать миграции из agent |
| R-22 | medium | Redis binaries отсутствуют локально, production workers разнесены | async behavior не воспроизводится локально | explicit test sync mode; queue contract tests; health checks per connection/queue |
| R-23 | medium | Legacy mail contacts и `mailing_contacts` дублируют identity | unsubscribe/consent может не примениться к direct send | единый RecipientEligibilityService и suppression check для любого отправителя |
| R-24 | medium | Existing Yandex AI price-list module может быть принят за общий gateway | неподходящие policies/credentials переиспользуются | отдельный `AiSales` gateway, config, DTO and audit; reuse только общих security patterns |

## Parallel Lead-domain

В проекте уже существует `App\Models\Lead` с status, source, phone, Unit, Entity, mail message и calls. Он создаётся mail action, website/telephony flows и Beeline sync. Создавать ещё один Lead model/table или строить AI workflow вокруг legacy Lead запрещено.

Безопасная стратегия:

- заморозить расширение legacy Lead для AI;
- описать его как intake compatibility source;
- привязывать новые AI discoveries непосредственно к Unit/context;
- в следующем этапе построить read-only mapping report Lead → Unit/context;
- backfill выполнять только идемпотентно и после human review неоднозначных строк;
- не удалять старые Leads до отдельного migration/retention решения.

## Accidental Entity creation

Название, сайт, телефон, email или DaData suggestion не доказывают transaction identity. Даже exact INN может относиться к Entity, уже связанной с другим Unit, что не означает merge dossiers.

Запрещённые AI actions:

- прямой Entity CRUD;
- вызов telephony placeholder creation;
- автоматический Unit merge;
- автоматический attach Entity при неоднозначном match;
- изменение реквизитов из web content.

Разрешён только proposal с evidence и duplicate candidates. Approval должен быть отдельным human event.

## Cross-context disclosure

Изоляция должна происходить до загрузки relation graph, а не после построения prompt. В частности:

- sales tool не загружает purchases, supplier quotations/notes или banking;
- procurement tool не загружает sales correspondence/customer prices;
- shared-public profile формируется из проверенных public observations;
- contact может иметь несколько context assignments с разными permissions;
- один Entity link не повышает видимость всех его relations.

## Prompt injection и tool safety

Web pages, emails, signatures, attachments, Avito/MAX messages и OCR output являются данными, а не инструкциями. Parser должен:

- сохранять raw content отдельно;
- извлекать typed facts по schema;
- не передавать секреты и произвольные URLs в tools;
- запрещать модели выбирать storage path, SQL, class/morph type, queue/command;
- проверять URL/IP/DNS против SSRF до fetch и после redirect;
- сканировать файлы, ограничивать MIME/размер/pages и использовать sandboxed extraction;
- валидировать structured output сервером;
- записывать source/evidence и решение policy.

Существующий AiPriceLists модуль уже содержит полезные примеры file validation, structured schemas и SSRF controls, но его нельзя считать автоматически пригодным для CRM данных.

## Почтовая автоматизация

Этапы должны быть строго разделены:

`research → recipient eligibility → draft → human review → approved send job → provider result → compliance events`

Модель не выбирает mailbox/From произвольно, не обходит suppression/unsubscribe и не отправляет synchronously из tool call. Повторное выполнение с тем же idempotency key не создаёт второе письмо.

## Cost/runaway controls

На каждом run нужны:

- purpose, Unit/context и actor;
- лимит steps, wall time, tokens и денежных затрат;
- лимиты fetch domains/pages/bytes и recipients;
- allowlisted models/tools;
- retry cap с exponential backoff;
- cancellation flag и circuit breaker;
- уникальный idempotency key;
- агрегированные метрики без prompt/PII;
- hard stop при превышении бюджета или неоднозначной identity.

## Остаточный риск Stage 01

Stage 01 только документирует baseline. Уязвимые маршруты, hard deletes, duplicate constraints и legacy auto-creation не исправлены, потому что это изменило бы поведение и вышло за границы этапа. Пока блокирующие controls не реализованы и не протестированы, AI Sales должен работать только на synthetic/non-sensitive fixtures без egress и без send/write tools.
