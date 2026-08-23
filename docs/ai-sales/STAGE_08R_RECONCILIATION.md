# Stage 08R reconciliation

The code-owned command is:

```text
php artisan ai-sales:reconcile-prospecting-products [--chunk=100]
php artisan ai-sales:reconcile-prospecting-products --apply --yes [--chunk=100]
```

Dry-run is the default and emits only safe counters plus environment, DB driver, and a basename-only database identifier. Apply requires `--yes`, is limited to local/testing/staging, and is blocked in production. Chunk size is bounded to 1–500.

For every historical Job Good and Unit Good row, the command counts distinct `good_product.product_id` values:

- one → creates/reuses the Product relation deterministically;
- zero → records `missing_product_mapping`, does not guess;
- more than one → records `ambiguous_product_mapping`, does not guess.

Exact Job mappings create/reuse Job Products and Candidate Product evidence. Exact Unit mappings create/reuse a review-required Unit Product match and link the existing Good row as an offer fit. Existing Stage 08 rows are not deleted. Repeated apply is idempotent.

The command never merges or creates Unit/Entity, changes canonical Product/Good/Unit data, copies Sales/Purchases, reads provider data, performs HTTP, or starts Stage 09. Migration and apply verification must use an explicitly isolated test database; default MySQL and production are prohibited.
