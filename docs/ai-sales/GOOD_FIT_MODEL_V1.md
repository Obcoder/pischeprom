# Good fit model V1

Subject: one `UnitGoodMatch` linked to an active `UnitProductMatch` in the same Unit/context/lane. Good fit does not create or replace Product relevance.

The factor ceilings are exact distinct Product mapping 20, format/processing 20, packaging/MOQ 15, origin/grade/size 15, delivery/supply 10, approved availability 10 and approved price 10. A missing audited field is `unknown` and contributes zero.

`GoodProductMappingResolver` collapses duplicate pivot rows to distinct Product IDs. A 0 or N Product mapping has no score and requires review. A single mapping to another Product or an inactive Product match blocks. An unpublished Good caps at 20; stale commercial evidence at 50; missing essential offer data at 60; no verified commercial fields beyond mapping at 45.

The Stage 10 audit found no approved context-bound MOQ, packaging, grade, availability or price-fit source. V1 therefore records those factors as unknown. Generic descriptions, stock movements, price rows and legacy relevance are not silently promoted into evidence.
