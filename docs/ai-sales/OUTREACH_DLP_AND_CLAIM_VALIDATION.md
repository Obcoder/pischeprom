# Outreach DLP and Claim Validation

The deterministic guard runs on every generated or human-edited revision. It requires a sales context and customer/prospective-customer role and returns safe finding codes only. It blocks credential material, `.env`, private keys, raw correspondence/header material, contact data in externally generated components, supplier identity/procurement secrets, contracts/invoices/payments/registry details, purchase price, cost/margin, unsupported price/stock/MOQ/discount assertions, arbitrary URLs, deceptive guarantees/urgency, prompt-injection residue and active content.

The structured schema accepts only product-relevance and optional Good-fit claims. Every claim must carry an exact reviewed match evidence reference and SHA-256; the service compares both against the current bound model. Unknown claim fields/types, fabricated references and stale/rejected matches fail closed. Stored claim text is accompanied by a fragment hash, evidence hash and append-only audit hash. Claims approval is separate from content, permission and recipient reviews.

The DLP scanner evaluates human-visible fields rather than evidence hashes, avoiding accidental contact-pattern matches inside SHA-256 values while still scanning the complete rendered subject/plaintext/HTML.
