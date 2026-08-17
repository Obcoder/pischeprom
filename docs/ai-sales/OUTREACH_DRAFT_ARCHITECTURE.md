# Outreach Draft Architecture

Stage 12 adds a review-only, product-first outreach aggregate. `Unit` remains the dossier, and every draft is bound to one sales-lane `UnitBusinessContext` whose role is `customer` or `prospective_customer`. The draft requires a current approved `UnitProductMatch`, may reference one approved `UnitGoodMatch`, and may reference an existing email through `UnitContactContextLink`. It cannot reference an unresolved `ProspectingCandidate` as a recipient.

The aggregate consists of `outreach_drafts`, append-only `outreach_draft_revisions`, evidence-bound `outreach_draft_claims`, independent append-only `outreach_draft_reviews`, and immutable `outreach_dispatch_decisions`. A manual edit always creates a new revision and invalidates prior reviews because every review binds the exact revision.

Generation is limited to `FakeStructuredOutreachProvider`. It receives `OutreachSafeDto`, an explicit allowlist containing the sales role/lane, hashed internal references, public Product/Good labels and reviewed match evidence. Recipient email/name/phone, raw correspondence, transactional rows, supplier/procurement fields and arbitrary relations are absent. The DTO checks that every needed relation was explicitly eager-loaded and applies a byte limit.

There is no outreach send/execute/dispatch route or service. `AuthorizedMailDispatchService` belongs exclusively to the existing human manual-mail workflow and is not a dependency of the outreach namespace.
