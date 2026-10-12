# Product pricing and confidential Entity prices

The Ameise product card uses `GoodPricingWorkspace` as the single pricing editor.
All calculations and assignments run through `GoodPricingCalculator` on the server.

## Storage and history

- `good_price_type_values`: shared price types used by catalogs and price lists.
- `good_entity_prices`: one confidential assignment per product and Entity, with its
  own currency, VAT, validity dates, active flag, input, result and revision.
- `good_price_histories`: append-only events containing full before/after snapshots,
  actor ID/name, timestamp, scope and price record ID. Snapshots retain currency,
  target name and measurement even when current records change or disappear.
- `entity_price_access`: explicit staff grants to active Entity members. Merely
  claiming an existing Entity by INN/email does not grant access. The recorded
  customer creator of an Entity has access while their membership remains active.

The migration records existing shared prices as `baseline` events. It does not
invent history before deployment. Price create/update/delete events and legacy
price API writes are audited in the same transaction as the price mutation.
Use model saves/deletes; direct SQL or query-builder bulk mutations bypass model
hooks. The history model rejects updates/deletions. Product unit conversion locks
and converts both price stores, retaining before/after unit snapshots and updating
per-unit calculation inputs. Removing a product keeps its existing audit entries.

Assignments lock the product and compare the submitted revision. Stale edits and
simultaneous creation of an already assigned target return a conflict. Source
prices belong to the current product; units and currencies must be comparable.
There is no implicit exchange rate. Net/gross amounts are rounded to four decimal
places, with optional upward rounding of the final gross price. Stored legacy
calculations remain available as sources and are converted from kg on the server.

## Price construction and saved calculations

The compact desktop editor has two paths: an exact manual price and a chain of
calculation elements. The chain is computed in this order:

1. Normalize the selected quotation/purchase (or manual procurement amount) to
   one accounting unit; extract purchase VAT when the source includes it.
2. Apply enabled additional elements in the displayed order. A fixed element is
   a net amount or an amount including its own VAT. A percentage explicitly uses
   either purchase net or the running net cost (including previous elements).
3. Add the chosen profit: cost × markup %, a fixed addition, or
   `cost / (1 - margin / 100) - cost`. Margin must be below 100%.
4. Add sale VAT and optionally round the final gross price upwards. The result
   includes actual net profit/margin and VAT after rounding, as well as each
   signed contribution, subtotal, formula and the rounding difference.

Manual mode ignores procurement and additional elements. Rounding defaults to
zero; an explicitly selected rounding step still applies. No exchange-rate
conversion or deduction of extra VAT from sale VAT is implied.

`good_pricing_elements` stores reusable per-product templates (fixed/percentage,
VAT, basis, currency, unit snapshot, revision and authorship). Staff can create,
edit and delete templates through `/api/goods/{good}/pricing/elements`. Each use
copies the template into the calculation: editing or removing a template cannot
rewrite existing prices or saved calculations. Chain elements can independently
be edited, reordered, disabled or removed. Fixed amounts require matching currency
and unit. Stale library revisions return 409.

`good_pricing_calculations` is the new immutable archive of complete input/result
snapshots, measurement, currency, target, actor and time. Saving an assigned price
archives its calculation in the same transaction as the price and history. The
response contains the saved snapshot, so the archive updates immediately. A
separate `POST /api/goods/{good}/pricing/calculations` saves a draft calculation
without assigning any price. A common draft can omit its eventual price type.

The archive migration recovers earlier workspace calculations from
`good_price_histories.after.pricing_input/pricing_result`. It preserves their
original values, actor and time, without recalculating or modifying any assigned
price; unique history IDs make recovery idempotent. This repairs the previous
mismatch where the archive tab only read legacy `good_price_calculations`.

Archived results can be applied as a fixed saved result or copied as an editable
construction. Applying an archived gross amount at the same VAT preserves it
exactly; conversion between units requires known mass. Editable copies use current
procurement data on recalculation and retain their extra-element snapshots.
Private archived calculations remain bound to their original Entity and cannot
be selected for a common price or another Entity. Legacy per-kg calculations stay
available separately. Neither new archive nor template data enters public offers,
customer payloads or mail catalogs.

## Confidentiality boundary

Staff-only pricing routes may read both stores and history. Public offer, catalog,
mailing and commercial-offer price selectors continue to read only the shared
store. No private-price relation is added to the general product model/payload.

Only the authenticated dashboard receives allowed, active, in-date private prices
for published products in its catalog domain. Its explicit payload omits source
costs, staff comments and history. Responses are `no-store, private`; Inertia
history is encrypted and cleared on login/logout. The cabinet UI does not put these prices in localStorage or
in the storefront cart.

`POST /dashboard/entity-orders` requires an allowed Entity and checks each price
ID, product, revision, validity and unit under transaction locks. The ordinary
`POST /orders` rejects private-price identifiers. The cabinet checkout supports
one Entity and one currency per order. Order lines retain the agreed price and
its assignment/revision; changing today's price never rewrites an existing order.
The persistent `orders.has_private_prices` marker makes email and MAX notifications
omit unit prices, line totals and the total amount. Staff can still process the
order in Ameise. A customer whose Entity access is revoked no longer receives that
Entity's confidential orders in the dashboard.

## Verification

`GoodPricingWorkspaceTest` covers calculation, currency/unit validation, revision
conflicts, migration baseline, atomic audit rollback, private-price access,
validity, checkout and notification redaction. Frontend tests exercise stale
responses, drafts, conflicts, history filtering and isolated cabinet checkout.
The archive, element CRUD, composition and unit-conversion regression suites cover
recovery, exact arithmetic, independent snapshots and atomic saves. These checks
run in the GitHub Actions deployment gate.
