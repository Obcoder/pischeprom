# Full-Russia logistics GIS operations

This directory contains a manual, native Linux release pipeline for one paired
dataset: a Valhalla graph and an OpenMapTiles-compatible PMTiles archive built
from the same checksum-verified full-Russia Geofabrik PBF.

It deliberately contains no GIS data and performs no work from Laravel deploy,
migrations, queue workers, or GitHub Actions. The existing routing service stays
active during download and staging builds.

## Release layout

Large files live outside Git, under `${GIS_BASE_DIR:-/srv/pischeprom-gis}`:

```text
sources/                    verified immutable PBF + source manifests
staging/russia-YYYYMMDD/    incomplete Valhalla/map components
staging/planetiler-work/... resumable auxiliary downloads/scratch, never published
releases/russia-YYYYMMDD/   immutable paired releases
current -> releases/...     active Valhalla + PMTiles pair
previous -> releases/...    rollback pair
logs/                       timestamped preflight/build/smoke logs
state/                      sanitized diagnostics JSON
locks/                      download/build/activation locks
```

Create these exact directories once, owned by the already approved GIS service
account. Do not place them under the repository or `storage/app/public`.

```bash
sudo install -d -m 0750 -o pischeprom-gis -g pischeprom-gis \
  /srv/pischeprom-gis \
  /srv/pischeprom-gis/sources \
  /srv/pischeprom-gis/staging \
  /srv/pischeprom-gis/releases \
  /srv/pischeprom-gis/logs \
  /srv/pischeprom-gis/state \
  /srv/pischeprom-gis/locks
```

Use another existing account/path if the production audit requires it; set
`GIS_BASE_DIR` consistently before running anything.

The Nginx worker needs read-only traversal of `current/map`. The PHP runtime
needs read-only access only to `current/release-manifest.json` and the four
sanitized JSON files in `state/` referenced by `LOGISTICS_GIS_*`. Use the
existing service group or exact read-only bind mounts when PHP runs in a
container; do not grant PHP access to sources, graph tiles, build logs, or
staging merely to make diagnostics work.

## Required configuration

Copy `.env.example` outside Git, set exact installed versions and checksums, then
source it in the supervised shell:

```bash
set -a
. /etc/pischeprom-gis.env
set +a
```

The pipeline does not install packages. Before a build, provision and pin:

- the already used native Valhalla version and its build tools;
- Java 21 or newer;
- one explicit Planetiler release and `PLANETILER_JAR_SHA256`;
- one explicit PMTiles CLI version;
- local Noto Sans glyphs and sprite files with `SHA256SUMS` under
  `GIS_MAP_ASSETS_DIR`.

Set the exact existing runtime restart mechanism. Prefer
`VALHALLA_SYSTEMD_UNIT` when that is how production actually runs. If the
audited deployment uses another existing mechanism, point
`VALHALLA_RESTART_HELPER` to a root/operator-managed executable adapter. The
scripts never assume or create a second runtime.

`VALHALLA_SERVICE_LISTEN` defaults to `tcp://127.0.0.1:8002` and is written
into every new graph config. A legacy container may require a wildcard bind
inside its private network; that requires the explicit
`VALHALLA_ALLOW_WILDCARD_LISTEN=true` acknowledgement, while host publishing
and firewall must still keep port 8002 private/loopback-only. Activation checks
that the verified release contains the audited value.

## Safe sequence

Every `WARN` and `FAIL` is blocking. Only exit code `0` permits the next heavy
step (`2` is WARN, `3` is FAIL).

```bash
# Read-only inspection plus timestamped log/state JSON.
infrastructure/logistics-gis/scripts/preflight.sh --mode full --json

# Download only after PASS; does not activate anything.
infrastructure/logistics-gis/scripts/download-russia-pbf.sh

# Use the exact path printed by the downloader.
infrastructure/logistics-gis/scripts/build-valhalla.sh \
  /srv/pischeprom-gis/sources/russia-YYYYMMDD.osm.pbf
infrastructure/logistics-gis/scripts/build-pmtiles.sh \
  /srv/pischeprom-gis/sources/russia-YYYYMMDD.osm.pbf

# Runs a lightweight verify preflight, validates graph/map/assets checksums and
# starts an inactive Valhalla on loopback port 18002.
infrastructure/logistics-gis/scripts/finalize-release.sh russia-YYYYMMDD

# Install/adapt Nginx config and test it before activation.
sudo nginx -t

# Atomic current/previous switch, production smoke, Range/206, stale marking.
infrastructure/logistics-gis/scripts/activate-release.sh russia-YYYYMMDD
```

The activation requires `GIS_PUBLIC_PMTILES_URL`, `GIS_LARAVEL_APP_DIR`, a
healthy current Valhalla, a verified release, the existing restart adapter, and
successful staging smoke tests. It never starts a full matrix recalculation.

Before the first paired activation, the operator must register the audited
currently working graph as the exact `current` release inside `GIS_BASE_DIR` and
prove that the configured restart adapter still starts that same graph. This
one-time compatibility step is host-specific and is intentionally not guessed
from repository documentation. It provides the mandatory rollback target. A
legacy target may have no PMTiles; rollback will restore routing, record Range as
unavailable, run only the explicitly labelled legacy Санкт-Петербург → Москва
route/matrix compatibility smoke, and let the Laravel map fall back safely
instead of claiming full-Russia visual coverage.

The registered legacy directory must contain a regular
`release-manifest.json`; a minimal audited example is:

```json
{
  "release": "legacy-260725",
  "status": "legacy",
  "coverage": "Central and Northwestern federal districts",
  "osm_data_version": "260725"
}
```

Point `current` to that exact directory only after its existing route/matrix
smoke passes. If its manifest cannot contain the known OSM version, set the same
value as `GIS_CURRENT_OSM_DATA_VERSION` before the first activation. Activation
blocks before any symlink change when neither source provides it, because the
existing automatic matrix/route values must be marked stale after success.

Rollback is an exact symlink swap followed by routing and Range smoke tests:

```bash
infrastructure/logistics-gis/scripts/rollback-release.sh
```

No script removes a PBF, staging directory, active release, previous release,
log, or matrix row. Failed/incomplete artifacts require an explicit operator
review; there is intentionally no automatic cleanup command. Planetiler's
auxiliary downloads remain under `staging/planetiler-work/<release>` for audit
and an explicitly reviewed retry, and are not moved into the immutable release.

## HTTP checks

Nginx must include `nginx/logistics-map.conf.example` inside the existing HTTPS
server block. It publishes only `current/map` and keeps sources, graphs,
manifests, staging, state, and logs private.

```bash
curl --fail --head https://HOST/maps/logistics/russia.pmtiles
curl --fail --silent --show-error \
  --range 0-15 \
  --dump-header - \
  --output /dev/null \
  https://HOST/maps/logistics/russia.pmtiles

GIS_PUBLIC_PMTILES_URL=https://HOST/maps/logistics/russia.pmtiles \
  infrastructure/logistics-gis/scripts/check-pmtiles-range.sh \
  https://HOST/maps/logistics/russia.pmtiles
```

The required result is `206 Partial Content`, `Accept-Ranges: bytes`, a valid
`Content-Range`, and exactly 16 response bytes for the scripted check.
