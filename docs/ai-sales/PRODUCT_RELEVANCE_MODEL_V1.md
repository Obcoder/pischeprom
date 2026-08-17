# Product relevance model V1

Subject: one `UnitProductMatch` in one Unit business context and lane. Product relevance never depends on a concrete Good.

Positive ceilings are direct Product mention 25, process/end use 20, industry activity 15, verified public Product evidence 15, independent source corroboration 10, same-lane relationship 10, and geography 5. Contradiction subtracts at most 20 and stale evidence subtracts at most 10.

Caps are applied after additions and penalties: directory-only 45, no primary corporate source 55, unresolved duplicate 40, and a rejected Product match 0. No evidence requires review; policy/cross-lane violations block. A failed public fetch is absence of evidence, never contradiction evidence.

Independent sources are distinct bounded source families. Same-domain pages do not multiply confidence. The relationship factor reads only a distinct transaction count through Entity links: Sales in sales contexts, Purchases in procurement contexts. It never copies transaction values or details.

Bands are low 0–24, medium 25–49, promising 50–69, high 70–84 and very_high 85–100.
