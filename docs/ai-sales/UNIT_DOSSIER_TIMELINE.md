# Unit dossier timeline

`UnitDossierTimelineQuery` is a read projection. It merges safe references from context, audit, source, observation, alias, contact-link, prospecting Job/Candidate, Unit–Good, Entity proposal/link, AI-run and authorized tool-call rows. It adds bounded message/attachment counts for context-bound visible email links and a distinct Entity-linked transaction count through `UnitTransactionAggregateQuery`.

It never copies source rows and there is no generic `unit_events` table. Summaries are bounded; only communication/file counts are read, while mail bodies, names/paths of attachments, contracts, invoices, provider bodies and transaction payloads are absent. Classified source/observation/alias/contact metadata is filtered through the Stage 03 field policy. Ordering and pagination are deterministic.

Unit + explicit UnitBusinessContext is mandatory. Sales context projects Sales, procurement context projects Purchases. A Unit in both lanes shows a warning and never receives a default combined view. Opposite-lane access fails closed through lane permission checks.
