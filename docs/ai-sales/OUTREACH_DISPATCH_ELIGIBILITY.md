# Outreach Dispatch Eligibility

Eligibility is a deterministic preview and never an authorization to send. The evaluator checks current Product/Good matches and score snapshots, current DLP-passed revision, four independent approvals (`content`, `claims`, `permission`, `recipient`), exact active permission scope, and every Stage 12/legacy suppression source.

Every Stage 12 result has `eligible=false` and includes these permanent runtime blocks:

- `stage12_dispatch_not_implemented`
- `global_dispatch_disabled`
- `ai_outreach_sending_disabled`
- `outreach_dispatch_flag_disabled`
- `auto_send_disabled`

`content_ready` may become true after all non-runtime checks pass; it still does not permit dispatch. Preview decisions can be persisted in `outreach_dispatch_decisions` with safe reason codes and an immutable decision hash. No `Sending`, mail job, provider call or tracking row is created.
