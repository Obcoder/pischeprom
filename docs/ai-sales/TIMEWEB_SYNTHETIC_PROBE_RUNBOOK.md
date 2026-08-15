# Timeweb synthetic probe runbook

## Preconditions

1. Work only in `local`, `testing` or `staging`; never production.
2. Keep both owner-created staging keys outside Git and configure different keys for `local_ru` and `external_sanitized`.
3. Keep production feature flags false. Only in the controlled staging process set `AI_SALES_TRANSPORT_MODE=timeweb_synthetic_only`, enable AI Sales, generic egress, the exact contour, Timeweb, its exact route and probe flags, and verify the global/contour kill switches are open. Keep provider failover disabled.
4. Set positive hard request/input/output/RUB/wall-clock caps. One invocation cannot configure more than 50 requests, 100,000 input tokens, 20,000 output tokens, 100 RUB or 600 seconds.
5. Record an evidence-backed exact-model RUB pricing snapshot with `ai:timeweb-pricing:record` before model calls.
6. Run the fake HTTP contract and regression tests with stray requests blocked.

## Inventory

Run dry-run first. It sends no prompt and reads no domain table:

```bash
php artisan ai:timeweb-models:sync --route=local_ru --dry-run --synthetic
php artisan ai:timeweb-models:sync --route=external_sanitized --dry-run --synthetic
```

Review exact IDs and safe hashes. Apply only to the same non-production database with explicit `--apply --confirm-apply`. Inventory never creates residency approval.

## Local residency

An authorized human reviews the exact local model against the panel filter or Timeweb support/contract evidence. Store only the safe reference and hash with `ai:timeweb-residency:verify`. Never paste evidence text or a key into the command.

## Capability order

Run one allowlisted exact model at a time and start with the cheapest approved profile:

```bash
php artisan ai:provider-probe timeweb \
  --route=ROUTE \
  --profile=basic \
  --model=EXACT_MODEL_ID \
  --confirm-synthetic
```

Then run `responses`, `structured`, `tools`, `store`, or `all`. Add `--record-evidence` only after reviewing the safe result metadata. Absence, rejection or ambiguous behavior is stored as `unsupported`/`unknown`, never promoted optimistically.

## Stop conditions

Stop immediately on domain-table access, raw body/key logging, redirect, host/route/model mismatch, absent local residency, blocked canary in a pending request, indistinguishable route keys, missing pricing, unknown retention claim or budget breach. Do not retry, switch contour, schedule the command or use a queue.

## Evidence handling

The report may include exact model IDs, safe request IDs, timestamps, token counts, local RUB estimates and hashes. It must not include keys, Authorization headers, prompts, outputs, provider error bodies or confidential residency documents. Successful `store=false` acceptance is not ZDR evidence.
