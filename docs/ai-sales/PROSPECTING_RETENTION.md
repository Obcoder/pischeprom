# Prospecting retention

Code-owned profile `prospecting-transient-v1` defaults to 30 days for unresolved Candidates, 14 days after resolution, 7 days after rejection, and the shortest seven-day policy for transient personal channels.

`ai-sales:prune-prospecting-candidates` is dry-run by default. Apply requires both `--apply --yes`, runs in bounded chunks, is idempotent, and is blocked in production until a separate rollout policy. It deletes encrypted transient channels, sanitizes Candidate source display fields, clears transient text/location/domain, and retains only minimal fingerprint, evidence hashes, resolution/audit references and anonymized status.

Unit sources, observations and audit records transferred by human review remain durable dossier records. Raw provider outputs never exist. Command output contains counters only.
