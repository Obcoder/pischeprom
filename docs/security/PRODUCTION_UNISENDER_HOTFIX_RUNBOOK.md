# Production Unisender security hotfix runbook — blocked draft V2

## Hard stop

```text
STATUS=STOP_BEFORE_BACKUP_RESTORE_AND_PRODUCTION_DEPLOYMENT
```

This runbook is preparation only. It does not authorize SSH writes, backup,
migration, Nginx changes/reload, service restart, deploy, callback smoke,
provider calls, email or historical payload cleanup.

## Audited baseline

```text
production baseline=af839e447ef4a2aa80608d4a9a1ac727800d8bdc
application path=/home/forge/xn----dtbhbbn3apgclecj7i.xn--p1ai
public names=xn----dtbhbbn3apgclecj7i.xn--p1ai, пищепром-сервер.рф
document root=/home/forge/xn----dtbhbbn3apgclecj7i.xn--p1ai/public
PHP-FPM socket=/var/run/php/php8.4-fpm.sock
global client_max_body_size=250M
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
Unisender max_parallel=10
```

The hotfix series must contain exactly the existing remediation commit
`d5bd587ec6ed2305fc4a6d4aaa9f3a9cc548d9b1` followed by the reviewed commit
whose subject is:

```text
security(mail): make Unisender pre-auth ingress stateless
```

Resolve and record the final commit with `git rev-parse` during a future
read-only preflight; do not substitute a branch tip without verifying history.

## Mandatory gates before execution

Deployment remains blocked until all of the following are separately reviewed
and owner-authorized:

1. A fresh consistent backup and isolated restore drill pass the dedicated
   backup/restore gate.
2. The final two-commit hotfix series, stable patch IDs and clean production
   baseline are verified.
3. The Nginx delta below is reviewed against privileged `nginx -T` output.
4. A dedicated `mailing-webhooks` database worker is approved; the audit found
   no existing worker consuming that queue.
5. Exact migration, monitoring and roll-forward procedures are approved.

The GitHub production workflow is not a minimal hotfix path and invokes
unrelated mutable commands. It must not be triggered as a substitute for a
reviewed minimal deployment.

## Prepared Nginx delta — do not apply

The application cap is exactly 262,144 encoded bytes. Add these zones once in
the existing Nginx `http` context:

```nginx
limit_req_zone $binary_remote_addr zone=unisender_webhook_req:1m rate=60r/s;
limit_conn_zone $binary_remote_addr zone=unisender_webhook_conn:1m;
```

Add this exact location to the audited HTTPS production `server` block, before
the generic Laravel location. It preserves the existing front-controller route
and the provider's GET verification endpoint:

```nginx
location = /webhooks/unisender-go {
    client_max_body_size 256k;

    limit_req zone=unisender_webhook_req burst=120 nodelay;
    limit_req_status 429;
    limit_req_log_level warn;

    limit_conn unisender_webhook_conn 20;
    limit_conn_status 429;

    limit_except GET POST {
        deny all;
    }

    try_files $uri $uri/ /index.php?$query_string;
}
```

The connection cap is twice the configured provider `max_parallel=10`. The
coarse edge request limit permits six complete ten-request parallel waves per
second with a further 120-request burst, while the application retains the
existing 120-per-minute limit only after signature verification. These values
avoid treating an unverified IP as provider identity and intentionally leave
headroom above legitimate configured concurrency. They must be reviewed with
traffic counters before authorization; no current official provider IP list is
assumed and no IP allowlist is introduced.

Do not change the global 250M application-upload policy. Do not add gzip request
handling: the configured webhook format is `json_post` and the application
accepts only identity encoding.

Future privileged validation, only after approval:

```text
1. inspect the exact active vhost with nginx -T
2. verify one and only one set of named zones
3. verify the exact callback location resolves to the existing public/index.php
4. run nginx -t
5. capture a redacted config delta and checksum
6. reload only under the deployment authorization
```

Any config-test failure is an immediate stop; do not reload.

## Prepared worker shape — do not install or start

The audited production workers do not consume `mailing-webhooks`. The reviewed
dedicated command remains:

```text
/usr/bin/php artisan queue:work database --queue=mailing-webhooks --sleep=1 --tries=1 --timeout=120 --max-time=3600
```

It must use the audited application working directory and `forge` identity.
Systemd installation, daemon reload and worker start require separate privileged
authorization. Do not route these jobs to a worker with retries greater than
one.

## Future minimal release sequence

This sequence is informational until every gate and exact owner phrase passes:

1. Verify production baseline and a clean checkout without resetting it.
2. Verify a fresh backup and completed isolated restore proof.
3. Acquire the application's deployment lock and enter reviewed maintenance.
4. Stop only the audited workers/SSR named in the approved runbook.
5. Install exactly the two security commits; no Stage 13/13B feature commit.
6. Install locked PHP/Node artifacts without running broad deploy hooks.
7. Apply only the additive migration
   `2026_08_17_123000_harden_unisender_provider_persistence.php` with
   `--force --isolated`; never run default `migrate` blindly.
8. Build/verify config, route and view caches.
9. Apply the reviewed webhook-specific Nginx delta and pass `nginx -t`.
10. Install but initially keep the dedicated webhook worker stopped.
11. Exit maintenance and execute only the approved invalid/valid/duplicate
    synthetic callback smokes.
12. Prove normalized-only DB state and ID-only durable job, then start the
    dedicated worker.
13. Monitor safe status counters for at least 30 minutes.

No step may purge legacy raw columns. No callback smoke may call the Unisender
API or send email.

## Future smoke invariants

Invalid content/signature/schema requests must return non-2xx and cause zero
application DB writes, including cache, sessions and queue. A valid signed
synthetic request may create only safe limiter state, one normalized request,
one normalized event and one ID-only job. A byte-identical duplicate returns
safe 200 without another request row, event or job.

Evidence may contain only safe status, counts, hashes, normalized identifiers
and timestamps. Never print or persist the key, auth, raw body, recipient,
headers, IP/User-Agent or provider response.

## Roll-forward posture

Do not roll back to the vulnerable production baseline. On deviation, stop
callback processing safely, retain the additive schema and normalized evidence,
and roll forward with a separately reviewed security correction. Never restore
raw payload persistence or resend ambiguous mail.

## Current state

```text
backup created=0
restore drill=0
code pushed/deployed=0
production migrations=0
services restarted=0
Nginx changed/reloaded=0
callback HTTP=0
provider calls=0
emails sent=0
raw payload purge=0
Stage 13B send=blocked
```

`STOP_BEFORE_BACKUP_RESTORE_AND_PRODUCTION_DEPLOYMENT`
