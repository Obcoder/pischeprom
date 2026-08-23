# Outreach reply correlation and triage

Incoming correspondence remains owned by the existing `mail_messages` table. Stage 13 stores bounded RFC `In-Reply-To` and `References` values during Yandex mailbox normalization, then correlates only when both conditions hold:

1. an exact RFC Message-ID matches an outgoing MailMessage that owns an OutreachDispatch;
2. the normalized inbound sender equals the dispatch's existing Email endpoint.

Company-name guessing, subject-only matching, arbitrary token matching and cross-endpoint matching are rejected. `outreach_reply_links` stores only MailMessage/dispatch IDs, method, hashes, triage metadata and review provenance. Raw reply text, HTML and headers are never copied.

Any exactly correlated human reply moves the dispatch to `replied`, cancels pending follow-up and adds a safe Unit dossier audit event. The profile `outreach_reply_triage.v1` is fake-only in Stage 13. Its result is review-required; it cannot send a response, grant permission or schedule work. Human review may classify interest, question, objection, not interested, unsubscribe, wrong contact, out of office or unknown. Unsubscribe/not-interested/wrong-contact classifications enter the same suppression path. Out of office is not engagement; unknown remains review-required.
