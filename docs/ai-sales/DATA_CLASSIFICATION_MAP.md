# Карта классификации данных

## Политика

Карта задаёт максимально консервативный доступ для новых AI Sales инструментов. Отсутствие source/verification означает запрет считать значение публичным.

Labels:

- `public` — подтверждённая публичная информация;
- `internal` — внутренние идентификаторы, состояния и служебные данные;
- `commercial_confidential` — цены, условия, сделки, стратегия и деловая переписка;
- `personal_data` — контактные и иные данные идентифицируемого физического лица;
- `secret` — credentials, tokens, private keys и authentication material.

Context scopes:

- `shared_public` — можно использовать в обоих lanes после проверки публичности;
- `sales_lane` — только работа с продажами;
- `procurement_lane` — только работа с закупками;
- `internal_only` — не передаётся агенту без узкого внутреннего инструмента и отдельного permission.

Export rules:

- `verified-public-only` — внешнему AI только allowlisted поля с provenance и verification;
- `minimized-approved` — только минимальный фрагмент после policy check/approval, с masking где возможно;
- `aggregate-only` — только вычисленный итог без строк/контрагентов;
- `no-external-export` — только локальная обработка или typed internal tool;
- `never` — ни в prompt, ни в trace, ни в tool output.

## Поля

| source.field | label | context scope | export rule | justification |
|---|---|---|---|---|
| `units.id` | internal | internal_only | no-external-export | внутренний ключ, полезен только для typed tools |
| `units.name` | public или internal | shared_public | verified-public-only | название может быть публичным, но источник сейчас не хранится |
| `units.is_customer` | internal | sales_lane | no-external-export | внутреннее коммерческое состояние |
| `units.is_supplier` | internal | procurement_lane | no-external-export | внутреннее коммерческое состояние |
| `units.created_at/updated_at` | internal | internal_only | no-external-export | служебная история, не публичный факт |
| `unit_business_contexts.*` (future) | internal | соответствующий lane | no-external-export | status, owner и next action — внутренняя CRM-информация |
| `unit_sources.public_url/provider/external_id` (future) | public/internal | shared_public | verified-public-only | URL можно экспортировать, provider identity и keys — только allowlist |
| `unit_observations.value/evidence` (future) | по содержимому | соответствующий lane | minimized-approved | непроверенный web content может содержать PII/injection |
| `uris.uri/name/comment` | public или internal | shared_public | verified-public-only | публичность URL должна быть подтверждена; comment внутренний |
| `labels/fields/industries/classifications` | internal или public | shared_public | verified-public-only | классификация может быть публичной, пользовательские labels — внутренние |
| `cities.name/regions.name` | public | shared_public | verified-public-only | общедоступная география |
| `buildings.address/coordinates` | public, internal или personal_data | соответствующий lane | minimized-approved | производственная площадка может быть публичной, частный адрес — PII |
| `email_unit + emails.address/name` | personal_data | соответствующий lane | minimized-approved | прямой идентификатор и канал связи |
| `emails.comment/source/verified_at/last_seen_at/is_active` | internal/personal_data | соответствующий lane | no-external-export | provenance и engagement metadata |
| `telephone_unit + telephones.number` | personal_data | соответствующий lane | minimized-approved | прямой идентификатор; shared number не доказывает identity |
| `consumptions.product_id/quantity/measure` | commercial_confidential | sales_lane | aggregate-only | спрос/потребность клиента |
| `quotations.good_id/price/currency/measure/denominator` | commercial_confidential | соответствующий lane | no-external-export | индивидуальные коммерческие условия |
| `manufacturers/product_unit` | public или internal | shared_public | verified-public-only | публичный ассортимент допустим, внутренние action links — нет |
| `supplier_pipeline_cards.stage/notes/next_contact_at` | commercial_confidential | procurement_lane | no-external-export | внутренняя стратегия закупок и заметки |
| `stage_unit.*` | internal | соответствующий lane после атрибуции | no-external-export | текущая stage relation не имеет lane |
| `Unit object-storage files/content` | commercial_confidential или personal_data | соответствующий lane | no-external-export | нет metadata/classification; содержимое может быть договором или PII |
| `Unit file names/path/checksum` | internal | internal_only | no-external-export | path раскрывает структуру storage; legacy path содержит имя |
| `entities.id` | internal | internal_only | no-external-export | внутренний transaction-owner key |
| `entities.name/full_name` | public, internal или personal_data | shared_public | verified-public-only | для физлица имя является PII; для организации может быть публичным |
| `entities.entity_classification_id` | internal/public | shared_public | verified-public-only | публичен только подтверждённый тип лица/организации |
| `entities.INN/KPP/OGRN` | public или personal_data | internal_only | minimized-approved | реестровые реквизиты юрлица публичны, ИП/физлица требуют защиты |
| `entities.legal_address/country_id` | public или personal_data | internal_only | minimized-approved | адрес юрлица может быть публичным, домашний адрес — PII |
| `entities.bank_account_number/bank_name/BIC/corr_account` | commercial_confidential | internal_only | never | платёжные реквизиты не нужны discovery/LLM |
| `entities.dadata_raw` | personal_data | internal_only | never | необработанный внешний payload может содержать избыточные данные |
| `entity_user.role/status/is_primary` | internal/personal_data | internal_only | no-external-export | раскрывает отношения пользователей и контрагента |
| `email_entity/entity_telephone` | personal_data | соответствующий подтверждённый lane | minimized-approved | контакт конкретного лица; нужен owner/context check |
| `sales.*` | commercial_confidential | sales_lane | aggregate-only | фактические продажи, суммы и payment state |
| `good_sale.quantity/price/total` | commercial_confidential | sales_lane | aggregate-only | строка сделки и индивидуальная цена |
| `purchases.*` | commercial_confidential | procurement_lane | aggregate-only | фактические закупки и суммы |
| `good_purchase.quantity/price/currency/total` | commercial_confidential | procurement_lane | aggregate-only | себестоимость и условия поставщика |
| `orders.* / order_items.*` | commercial_confidential/personal_data | sales_lane | no-external-export | состав, адрес доставки, customer details |
| `checks.* / check commodities/services` | commercial_confidential | соответствующий lane | no-external-export | расчёт/документ содержит цены и состав |
| `bank_connections/accounts/transactions/allocations` | commercial_confidential | internal_only | never | финансовые остатки, назначения и сверка |
| `bank_payment_order_drafts.*` | commercial_confidential | internal_only | never | платёжное поручение и реквизиты получателя |
| `goods.public_name/description/media/published attributes` | public | shared_public | verified-public-only | предназначенный к публикации catalog content |
| `goods.internal fields/stock` | commercial_confidential | internal_only | no-external-export | остатки и внутренние настройки |
| `good_price_type_values.net/gross/validity/published` | public или commercial_confidential | соответствующий lane | verified-public-only | разрешена только опубликованная актуальная цена |
| `good_price_calculations/formulas/inputs/results/margin` | commercial_confidential | internal_only | never | себестоимость, маржа и pricing logic |
| `mail_messages.subject/body/html/preview` | personal_data/commercial_confidential | атрибутированный lane | minimized-approved | деловая переписка и подписи; возможен prompt injection |
| `mail_messages.from/to/cc` | personal_data | атрибутированный lane | minimized-approved | адреса физических лиц |
| `mail_messages.raw_headers/provider identifiers` | personal_data/internal | internal_only | never | технические IDs, routing и authentication headers |
| `mail_messages.attachments` | по содержимому, по умолчанию confidential | internal_only до классификации | no-external-export | бинарный непроверенный ввод и возможные документы |
| `sendings.body/status/provider ids` | personal_data/commercial_confidential | атрибутированный lane | no-external-export | содержание и операционная история отправки |
| `sendings.open/click/IP/user_agent` | personal_data | internal_only | never | tracking/behavioral data |
| `phone_calls.number/recording/transcript/payload` | personal_data/commercial_confidential | атрибутированный lane | no-external-export | контакт, разговор и provider payload |
| `max_chats/messages` | personal_data/commercial_confidential | атрибутированный lane | no-external-export | частная переписка; Unit/Entity links могут расходиться |
| `avito_chats/messages/payload` | personal_data/commercial_confidential | атрибутированный lane | minimized-approved | external user content и prompt-injection surface |
| `leads.*` | personal_data/commercial_confidential | lane пока неоднозначен | no-external-export | legacy aggregate, nullable/independent Unit и Entity links |
| `mailing_contacts.email/consent evidence` | personal_data | sales_lane | no-external-export | контакт и доказательство согласия |
| `mailing_campaigns/recipients/content` | commercial_confidential/personal_data | sales_lane | no-external-export | аудитория, текст, status и результаты кампании |
| `unsubscribe/suppression/bounce/spam events` | personal_data/internal | internal_only | never | compliance state нельзя раскрывать или обходить |
| `users.name/email/phone/entity profile` | personal_data | internal_only | never | данные аккаунта/представителя |
| `users.password/two_factor/session/tokens` | secret | internal_only | never | authentication material |
| `.env/services.* credentials/OAuth/SMTP/mTLS/private keys` | secret | internal_only | never | компрометация внешних систем |
| `external raw payloads/webhooks` | personal_data/internal | internal_only | never | неподтверждённый ввод, подписи и provider internals |
| `AI prompt/tool arguments/results` | наследует максимум входных labels | тот же или более узкий scope | policy-dependent | производные данные не становятся менее чувствительными |
| `AI run logs/cost/error traces` | internal или confidential | internal_only | no-external-export | могут содержать excerpts, IDs и коммерческую стратегию |

## Правила применения

1. Label результата равен наиболее строгому label среди входов.
2. Context scope нельзя расширять при агрегации или summarization.
3. Shared Entity не делает её сделки shared: `Sale` остаётся sales, `Purchase` — procurement.
4. Если public provenance отсутствует, поле трактуется как internal/PII.
5. Raw model serialization запрещена. Gateway получает версионированный DTO allowlist.
6. PII export требует purpose, permission, минимизации, provider policy и audit; по умолчанию запрещён.
7. Secret и банковские данные отсекаются до логирования и до построения prompt.
8. Web/mail/attachment content всегда untrusted, даже если источник связан с известным Unit.
9. Retention и deletion должны применяться и к prompt/tool traces.
10. Human approval не может понижать `secret` до экспортируемого уровня.
