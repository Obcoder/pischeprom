# Untrusted content and deterministic DLP

`AiUntrustedContentEnvelope` marks external/web/document-like text as:

```text
trust_level=untrusted
instruction_authority=none
```

It contains bounded source type/reference, bounded text, content hash, classification and visibility. The envelope is data; it cannot alter a system instruction, workflow, tool list/order, contour, provider/model, DLP, budget, recipient, sending action or Entity action.

`AiToolDlpGuard` composes the Stage 04 payload scanner with deterministic Stage 07 checks. It blocks:

- API keys, tokens, JWT, private-key blocks, passwords and secret-like field names;
- Authorization/Bearer, cookies, sessions, `.env` and configuration signatures;
- external email/telephone and registry/bank identifiers;
- raw mail/message bodies, HTML, headers and attachments;
- active script/form/iframe/object content and unsafe control characters;
- prompt-injection instructions in Russian or English;
- supplier/procurement canaries in sales and customer/sales canaries in procurement.

Credential and secret findings always block, including in `local_ru`. The original finding is not logged, persisted, placed in an exception or returned by the diagnostics API. Stage 07 has no semantic/LLM security decision and no pseudo-tool parsing from text.
