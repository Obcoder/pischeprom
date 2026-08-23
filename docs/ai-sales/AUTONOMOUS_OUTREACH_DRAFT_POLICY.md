# Autonomous outreach draft policy

Policy: `autonomous_outreach_draft.v1`. Default: blocked.

The operation requires a current `autonomous_reviewed` campaign approval, independent campaign/draft switches, a current sales/prospective-customer context, an approved non-stale Product match, current Product relevance and Prospect priority snapshots, code-owned score/confidence thresholds, no unresolved duplicate, no active suppression, and positive campaign/global draft caps.

The Stage 12 Safe DTO and DLP boundary remain authoritative. Recipient email/contact/PII is not supplied to the automatic AI generation input. The code-owned renderer creates the revision and evidence-backed claims. The only permitted lifecycle result is:

`OutreachDraft + Revision + Claims -> review_required`.

Automatic drafting does not grant communication permission, approve content, claims or recipient, prepare or queue dispatch, create a Sending, send mail, or schedule a follow-up. Existing dispatch and provider-send flags remain false. A score is evidence for prioritization, not permission to contact.
