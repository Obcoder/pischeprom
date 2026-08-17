# Outreach Rendering and Revisions

The fake generator returns strict structured components. `CodeOwnedOutreachRenderer` alone produces plaintext and HTML. Every paragraph is escaped; arbitrary HTML, URLs, tracking pixels, attachments and headers are unsupported. Subject, paragraph, claim and byte limits are code-owned in `config/ai-sales.php`.

`outreach_draft_revisions` is append-only. Each row binds its parent, revision number, structured components, deterministic plaintext/HTML, renderer version/hash, DLP result/hash, claim-set hash and input hash. Editing creates a new row and resets the draft to review-required or blocked. Earlier content and reviews remain immutable for audit.

The UI displays the current revision and creates revisions through the structured contract. It never sends rendered content.
