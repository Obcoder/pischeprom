# Communication Permission Ledger

`communication_permissions` records a reviewed scope, not an inferred consent flag. The scope is exact across Unit, sales context, existing email contact link, normalized endpoint hash, sender scope, purpose and Product. The code-owned purpose registry is `advertising_outreach`, `response_to_inquiry`, `transactional`, `relationship_service`, and `unknown`; Stage 12 creation supports only `advertising_outreach`, while `unknown` and every attempted less-restrictive reclassification fail closed. A public, corporate, imported or technically validated email never creates permission.

New entries begin as `pending_review`. One or more `communication_permission_evidence` rows are required. Evidence rows contain only a code-owned type (documented consent, web-form consent, written response, relationship evidence, reviewed import/manual evidence, or other reviewed evidence), safe reference, SHA-256, capture time and scope/audit hashes; evidence files and contact values are not copied. Evidence and `communication_permission_decisions` are append-only. A human with `ai_sales.communication_permissions.manage` may grant/reject and later revoke. Expired, rejected, revoked, absent or scope-mismatched records block.

The code-owned permissions are:

- `ai_sales.communication_permissions.view`
- `ai_sales.communication_permissions.manage`

They are granted by the seeder to the administrator through its existing all-permissions assignment and are not added to the manager role.
