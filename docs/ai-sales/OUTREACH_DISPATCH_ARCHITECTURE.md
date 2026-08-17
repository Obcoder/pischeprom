# Outreach dispatch architecture

Stage 13 adds a reviewed, default-off dispatch lifecycle on top of the existing mail stack. The hardened Unisender webhook security commit is a prerequisite; deployment of this feature must not be treated as proof that the prerequisite is already deployed in production.

## Sources of truth

- `outreach_drafts` and append-only `outreach_draft_revisions` own reviewed content and claim provenance.
- `communication_permissions` and `communication_suppressions` own purpose/product/contact authorization. Suppression always wins.
- `emails` owns the recipient address; `outreach_dispatches` stores only the Email-linked contact relation.
- `mail_messages` owns the rendered outgoing correspondence and any inbound raw reply; its existing Email pivot owns the outreach `to` endpoint instead of duplicating it in JSON.
- `sendings` remains the provider delivery/tracking record.
- `mailing_events` remains the verified normalized provider-event record.
- `outreach_dispatches` binds one exact revision, recipient scope, permission, Product/Good provenance, MailMessage and Sending into the reviewed outbox lifecycle.

The controller may prepare or request queueing but never calls a provider. `OutreachDispatchService` creates the dispatch, MailMessage and Sending in one transaction. The ID-only `SendOutreachDispatchJob` is dispatched after commit. Queue replay and prepare replay resolve to one logical dispatch through unique hashes and relations.

## Delivery mapping

`OutreachDispatchMessageMapper` maps only the approved structured revision through the code-owned renderer and appends the code-owned unsubscribe URL. From and Reply-To are server-owned configuration. Requests cannot supply recipient, body, HTML, provider, URL, headers, From or Reply-To.

Delivery reuses `UnisenderGoClient` with `outreach_zero_retry`: transport retries `0`, queue tries `1`, failover `0`. Ambiguous provider acceptance requires operator review and is never resent automatically. Manual mail controllers and `AuthorizedMailDispatchService` are not an outreach path.

## Data safety

Stage 13 never reads or writes deprecated raw webhook/provider fields. No request/response/event payload, recipient copy, raw provider error or raw reply is added to outreach tables. Only bounded safe IDs, hashes, state, error codes, summaries and timestamps are persisted.

All Stage 13 runtime flags, daily send limits and provider delivery are default-off. Stage 13 performs no live provider call. An owner-controlled single-message live gate belongs to Stage 13B; autonomous operation belongs to Stage 14.
