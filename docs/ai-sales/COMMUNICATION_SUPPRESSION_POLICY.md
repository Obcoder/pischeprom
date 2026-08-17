# Communication Suppression Policy

Suppression always has precedence over permission, content approval, scores and draft status. Stage 12 supports endpoint, domain, Unit, context and global scopes. Stored endpoint/domain selectors are SHA-256 hashes; routine API responses and audit rows do not copy the contact value.

Blocking reasons include do-not-contact, unsubscribe, complaint, hard bounce, invalid address, explicit suppression, legal hold and manual block. The evaluator also consumes existing `UnitContactContextLink` `do_not_contact`/`suppressed` states, `mailing_suppression_list`, and terminal `MailingContact` fields. Legacy records can only block; they cannot grant permission.

Stage 12 suppressions are cleared through an explicit audited decision, never deletion. Global/domain clearing is deliberately outside the Unit endpoint. Unsubscribe, complaint, hard-bounce and legal-hold reasons also require a separate governance process and cannot be cleared through the Unit endpoint. Unknown state fails closed.
