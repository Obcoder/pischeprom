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
These checks run in the GitHub Actions deployment gate.
