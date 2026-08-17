# Prospect priority model V1

Subject: one `UnitBusinessContext`. The score is a research/review queue priority and is not communication consent.

Factor ceilings are top Product relevance 45, up to two additional Products 10, evidence quality/confidence 10, verified public corporate-channel presence 10, geography/logistics 5, freshness 5, same-lane relationship 10, and review completeness 5. The top Product is not counted again in breadth. Additional Products contribute only when the Product match is reviewed/approved, its score is at least 30 and confidence is at least 70.

An unresolved duplicate caps at 40 and blocks duplicate eligibility. No Product relevance of at least 30 caps at 35. A stale dossier caps at 50. No evidence blocks. DNC, suppression and policy blocks preserve the computed research score while setting effective queue score to zero and a separate blocked eligibility.

Only channel metadata is read; addresses, numbers, normalized hashes and personal values never enter inputs or explanations. Same-lane relationship uses only distinct Sales or Purchase counts through Entity links.
