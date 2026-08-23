# Stage 12 Live Gate

Stage 12 has no live-email or live-AI gate. Production dispatch is explicitly out of scope.

Default-off flags:

- `AI_OUTREACH_UI_ENABLED=false`
- `AI_OUTREACH_DRAFTS_ENABLED=false`
- `AI_OUTREACH_DRAFTING_ENABLED=false`
- `AI_OUTREACH_FAKE_GENERATION_ENABLED=false`
- `AI_COMMUNICATION_PERMISSION_LEDGER_ENABLED=false`
- `AI_COMMUNICATION_SUPPRESSION_MANAGEMENT_ENABLED=false`
- `AI_OUTREACH_LIVE_GENERATION_ENABLED=false`
- `AI_OUTREACH_DISPATCH_ENABLED=false`
- `AI_OUTREACH_SENDING_ENABLED=false`
- `AI_OUTREACH_AUTO_SEND_ENABLED=false`
- `AI_OUTREACH_TRANSPORT_MODE=fake_only`

The only executable acceptance is `php artisan ai-sales:run-synthetic-outreach-draft` on local/testing isolated SQLite. It blocks MySQL and production, refuses a database containing Unit/Entity rows, uses repository-owned fictional fixtures, prevents stray HTTP, fakes Mail and Queue, proves suppression precedence and global dispatch blocking, and rolls back all fixture rows.

Any future dispatch stage requires a separate owner-approved specification, legal review, provider/retention review, a new endpoint threat model and an explicit live gate. Stage 12 approval cannot be promoted implicitly.
