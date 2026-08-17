# Find Buyers disclosure preview

`FindBuyersDisclosurePreview` builds metadata from `AiDataClassificationRegistry` and `AiDisclosurePolicy`; it is not a marketing-only hardcoded promise. The context is `buyer_discovery`, sales lane, prospective-customer audience and role, with an external-sanitized preview. The payload includes registry decisions, policy versions, and a canonical policy hash. Runtime disclosure must still be rechecked if a later stage enables execution.

Potentially allowed fields are an explicit Stage 03 allowlist, such as public Product name/category/application metadata, selected geography, public evidence, and sanitized structured research. Only rows whose deterministic policy decision is `allow` appear in `allowed_fields`.

Blocked classes include credentials, tokens, cookies, sessions, `.env`, private keys, raw correspondence and raw bodies, personal contact values, internal CRM context, supplier/procurement data in the customer lane, purchase prices, margins, contracts, invoices, payments, and every unclassified field. Opposite-lane data remains blocked even for a Unit that has both sales and procurement contexts.

The preview contains classifications, visibility scopes, decision/reason codes, and hashes only. It contains no secret value, personal contact value, supplier identity, raw provider body, raw page, or correspondence. Progress responses likewise expose only safe counts, bounded labels, safe error codes, and reviewed score metadata.
