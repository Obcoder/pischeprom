# Unit contact channel policy

Transient Candidate channels are encrypted and short-lived. After human resolution, Stage 08 reuses canonical `emails`, `telephones`, and `uris`, their existing Unit pivots, and `unit_contact_context_links`. No durable `unit_contacts` table exists.

The context link records a normalized hash, provenance, lane/context, business-general or person-specific role, verification state, classification, confidence, review state and last verification time. Snapshot display is masked. Person-specific contacts are `personal_data` with `internal_only` visibility.

Allowed communication states are only `unknown`, `review_required`, `do_not_contact`, and `suppressed`. Every state is non-eligible for sending in Stage 08. Validation is not consent. DNC and suppression remain blocking. Raw or personal values are never exported to external AI and are absent from prospecting Resources/UI.
