# Unit context backfill

## Команда

```bash
php artisan ai-sales:backfill-unit-contexts --chunk=200
php artisan ai-sales:backfill-unit-contexts --apply --chunk=200
```

Без `--apply` команда всегда dry-run. `--chunk` допускает 1–1000. Apply технически разрешён только при явном `APP_ENV` `local`, `testing` или `staging`; production завершается fail closed и требует отдельного owner-approved rollout, которого Stage 03 не выполняет.

Команда не запускается из migration, deploy hook, scheduler или queue worker.

## Deterministic signals

| Signal | Candidate context | Confidence/stage |
|---|---|---|
| Linked Entity имеет `sales` | `sales/customer` | 100 / `active` |
| Linked Entity имеет `purchases` | `procurement/supplier` | 100 / `active` |
| Только `units.is_customer=true` | `sales/prospective_customer` | 50 / `review_required` |
| Только `units.is_supplier=true` | `procurement/prospective_supplier` | 50 / `review_required` |
| Только classification | Context не создаётся | Supporting signal попадает в review report |

Если transaction signal противоречит legacy flag либо flag не подтверждён transaction, строка добавляется в bounded review report. Обе transaction роли создают оба contexts.

## Safety properties

- Unit обрабатываются `chunkById`, без unlimited graph;
- identity `(unit_id, lane, role_code)` и `firstOrCreate` обеспечивают idempotence;
- dry-run не создаёт role/context/audit rows;
- Entity, `entity_unit`, sales, purchases и legacy flags не создаются и не изменяются;
- транзакции не копируются в context;
- classifications не являются достаточным source of truth;
- apply создаёт только `market_role_unit`, `unit_business_contexts` и соответствующий append-only audit event;
- archived assignment не реанимируется автоматически, а отправляется на review.

## Обязательный rollout порядок

1. Показать `APP_ENV` и default DB connection без credentials.
2. Подтвердить, что target не production.
3. Сначала выполнить dry-run, сохранить counts/review report и сверить владельцем.
4. Отдельно одобрить apply и backup/rollback procedure.
5. Выполнить apply ограниченными chunks и повторный dry-run/idempotence check.

В рамках Stage 03 миграции и dry-run проверяются только на synthetic isolated test DB. Production backfill не выполняется.
