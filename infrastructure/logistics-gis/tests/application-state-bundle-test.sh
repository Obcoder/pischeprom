#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'

if [[ "$(uname -s)" != "Linux" ]]; then
    printf 'application-state-bundle-test: SKIP (native Linux test)\n'
    exit 0
fi

test_root="$(mktemp -d)"
cleanup() {
    rm -r -- "$test_root"
}
trap cleanup EXIT

repository="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
scripts="${repository}/infrastructure/logistics-gis/scripts"
gis_base="${test_root}/gis"
state_base="${test_root}/application-state"
bundle="${test_root}/bundle"
release='russia-20260801'
pmtiles_sha='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
mkdir -p -- "${gis_base}/releases/${release}" "${gis_base}/state" "${gis_base}/locks" "$state_base"
ln -s -- "${gis_base}/releases/${release}" "${gis_base}/current"

printf '%s\n' \
    "{\"release\":\"${release}\",\"status\":\"verified\",\"coverage\":\"Russia\",\"osm_data_version\":\"20260801\",\"pmtiles\":{\"size_bytes\":32,\"sha256\":\"${pmtiles_sha}\"}}" \
    > "${gis_base}/releases/${release}/release-manifest.json"
printf '%s\n' \
    "{\"release\":\"${release}\",\"status\":\"active\",\"production_smoke\":\"passed\",\"activated_at\":\"2026-08-02T12:00:00Z\"}" \
    > "${gis_base}/state/last-activation.json"
printf '%s\n' \
    '{"healthy":true,"content_length":32,"cors_origin":"https://app.example.test","cors_allow_origin":"https://app.example.test","cors_expose_headers":"Accept-Ranges, Content-Length, Content-Range, ETag"}' \
    > "${gis_base}/state/last-range-check.json"
printf '%s\n' \
    "{\"release\":\"${release}\",\"status\":\"passed\",\"kind\":\"production\"}" \
    > "${gis_base}/state/last-production-smoke.json"
printf '%s\n' \
    "{\"status\":\"verified\",\"release\":\"${release}\",\"application_origin\":\"https://app.example.test\",\"pmtiles\":{\"url\":\"https://maps.example.test/logistics/releases/${release}/russia.pmtiles\",\"size_bytes\":32,\"sha256\":\"${pmtiles_sha}\",\"range_requests\":\"passed\",\"cors\":\"passed\"}}" \
    > "${gis_base}/state/last-map-publication.json"
printf '%s\n' '{"result":"PASS","mode":"full"}' > "${gis_base}/state/last-preflight.json"

GIS_BASE_DIR="$gis_base" "$scripts/export-application-state.sh" "$release" "$bundle"
"$scripts/install-application-state.sh" "$bundle" "$state_base"
[[ "$(realpath -- "${state_base}/current")" == "${state_base}/releases/${release}" ]]
[[ -f "${state_base}/current/last-map-publication.json" ]]

# A verified retry is idempotent and does not rewrite or rotate the state.
"$scripts/install-application-state.sh" "$bundle" "$state_base"
[[ ! -e "${state_base}/previous" ]]

tampered="${test_root}/tampered"
cp -a -- "$bundle" "$tampered"
printf ' ' >> "${tampered}/bundle.json"
if "$scripts/install-application-state.sh" "$tampered" "$state_base" >/dev/null 2>&1; then
    printf 'Tampered state bundle was accepted.\n' >&2
    exit 1
fi

unsafe="${test_root}/unsafe"
cp -a -- "$bundle" "$unsafe"
printf '%064d  ../outside\n' 0 > "${unsafe}/SHA256SUMS"
if "$scripts/install-application-state.sh" "$unsafe" "$state_base" >/dev/null 2>&1; then
    printf 'Unsafe checksum path was accepted.\n' >&2
    exit 1
fi

printf 'application-state-bundle-test: PASS\n'
