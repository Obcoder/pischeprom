# Find Buyers wizard

`FindBuyersLauncher.vue` is embedded once on the internal Product page and once on the internal Good page. It first requests authorized launch context; 401/403/404 responses hide the control. Opening the launcher does not create data or call a provider.

`FindBuyersWizard.vue` has six bounded steps:

1. Product scope: one primary Product, bounded additional/excluded Products, and optional originating Good. Role conflicts are rejected server-side.
2. Buyer criteria: code-owned industry, activity, category, exclusion, and company-type dictionaries. There is no free-form AI prompt.
3. Geography: existing country/region/city dictionaries with server-validated hierarchy. The browser cannot submit a provider-specific region enum.
4. Limits: server-ceiling query, result, domain, fetch-attempt, and Candidate caps. Stage 11 budget, retries, failovers, and HTTP are zero.
5. Data safety: deterministic Stage 03 disclosure preview with its policy hash.
6. Review: Product/Good scope, fixed purpose/lane/role, query-plan state, and default-off runtime state.

Available actions are save draft, build code-owned query plan, submit for human review, and cancel. There is deliberately no execute action. Draft idempotency keys are accepted as UUIDs and persisted only as SHA-256 hashes. The browser cannot update a draft after it leaves draft state.

Good behavior is explicit: zero Product mappings show the mapping-required block; one mapping confirms the Product; N mappings show distinct choices and require selection. Navigating back in the wizard changes only client state until the user saves. Existing Product Yandex search UI and behavior are not reused or duplicated.
